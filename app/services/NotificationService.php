<?php
/**
 * Service de Notifications (SMS, WhatsApp, Email)
 * Complexe Sportif Kaira
 *
 * Ce service gère l'envoi de notifications multicanal.
 * Intégration avec:
 * - Orange SMS Sénégal (API SMS)
 * - Twilio (SMS/WhatsApp)
 * - Infobip
 * - SendGrid/SMTP (Email)
 */

class NotificationService
{
    private static ?self $instance = null;

    // Configuration des providers
    private array $config = [];

    // Mode de test
    private bool $testMode = false;

    // Logs des envois
    private array $logs = [];

    private function __construct()
    {
        $this->loadConfig();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Charger la configuration depuis les paramètres
     */
    private function loadConfig(): void
    {
        $this->config = [
            // SMS
            'sms_enabled' => $this->getParam('sms_enabled', false),
            'sms_provider' => $this->getParam('sms_provider', 'orange'), // orange, twilio, infobip
            'sms_api_key' => $this->getParam('sms_api_key', ''),
            'sms_api_secret' => $this->getParam('sms_api_secret', ''),
            'sms_sender_id' => $this->getParam('sms_sender_id', 'CSK'),

            // WhatsApp
            'whatsapp_enabled' => $this->getParam('whatsapp_enabled', false),
            'whatsapp_provider' => $this->getParam('whatsapp_provider', 'twilio'), // twilio, meta
            'whatsapp_api_key' => $this->getParam('whatsapp_api_key', ''),
            'whatsapp_phone' => $this->getParam('whatsapp_phone', ''),

            // Email
            'email_enabled' => $this->getParam('email_enabled', true),
            'smtp_host' => $this->getParam('smtp_host', 'smtp.gmail.com'),
            'smtp_port' => $this->getParam('smtp_port', 587),
            'smtp_user' => $this->getParam('smtp_user', ''),
            'smtp_pass' => $this->getParam('smtp_pass', ''),
            'email_from' => $this->getParam('email_from', 'noreply@csk.sn'),
            'email_from_name' => $this->getParam('email_from_name', APP_NAME ?? 'CSK')
        ];

        $this->testMode = (bool)$this->getParam('notification_test_mode', false);
    }

    /**
     * Récupérer un paramètre depuis la base de données
     */
    private function getParam(string $cle, $default = '')
    {
        try {
            $param = Database::fetchOne("SELECT valeur FROM parametres WHERE cle = :cle", ['cle' => $cle]);
            return $param ? $param['valeur'] : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Envoyer une notification (auto-détection du canal)
     */
    public function send(string $to, string $message, string $channel = 'auto'): array
    {
        $results = [];

        if ($channel === 'auto') {
            // Déterminer le canal optimal
            if ($this->isEmail($to)) {
                $results['email'] = $this->sendEmail($to, 'Notification CSK', $message);
            } else {
                // Numéro de téléphone
                if ($this->config['whatsapp_enabled']) {
                    $results['whatsapp'] = $this->sendWhatsApp($to, $message);
                } elseif ($this->config['sms_enabled']) {
                    $results['sms'] = $this->sendSMS($to, $message);
                }
            }
        } else {
            switch ($channel) {
                case 'sms':
                    $results['sms'] = $this->sendSMS($to, $message);
                    break;
                case 'whatsapp':
                    $results['whatsapp'] = $this->sendWhatsApp($to, $message);
                    break;
                case 'email':
                    $results['email'] = $this->sendEmail($to, 'Notification CSK', $message);
                    break;
            }
        }

        return $results;
    }

    /**
     * Envoyer un SMS
     */
    public function sendSMS(string $phone, string $message): array
    {
        $phone = $this->formatPhone($phone);

        if (!$this->config['sms_enabled']) {
            return ['success' => false, 'error' => 'SMS désactivé'];
        }

        // Mode test
        if ($this->testMode) {
            return $this->logAndReturn('sms', $phone, $message, true, 'Mode test');
        }

        switch ($this->config['sms_provider']) {
            case 'orange':
                return $this->sendOrangeSMS($phone, $message);
            case 'twilio':
                return $this->sendTwilioSMS($phone, $message);
            case 'infobip':
                return $this->sendInfobipSMS($phone, $message);
            default:
                return ['success' => false, 'error' => 'Provider SMS non configuré'];
        }
    }

    /**
     * Envoyer via Orange SMS Sénégal
     */
    private function sendOrangeSMS(string $phone, string $message): array
    {
        $url = 'https://api.orange.com/smsmessaging/v1/outbound/tel:+' . $this->config['sms_sender_id'] . '/requests';

        $data = [
            'outboundSMSMessageRequest' => [
                'address' => 'tel:+' . $phone,
                'senderAddress' => 'tel:+' . $this->config['sms_sender_id'],
                'outboundSMSTextMessage' => [
                    'message' => $message
                ]
            ]
        ];

        $token = $this->getOrangeToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Impossible d\'obtenir le token Orange'];
        }

        $response = $this->httpPost($url, $data, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        return $this->logAndReturn('sms', $phone, $message,
            isset($response['outboundSMSMessageRequest']),
            $response['error'] ?? null
        );
    }

    /**
     * Obtenir le token OAuth Orange
     */
    private function getOrangeToken(): ?string
    {
        $url = 'https://api.orange.com/oauth/v3/token';

        $response = $this->httpPost($url, [
            'grant_type' => 'client_credentials'
        ], [
            'Authorization: Basic ' . base64_encode($this->config['sms_api_key'] . ':' . $this->config['sms_api_secret']),
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        return $response['access_token'] ?? null;
    }

    /**
     * Envoyer via Twilio
     */
    private function sendTwilioSMS(string $phone, string $message): array
    {
        $sid = $this->config['sms_api_key'];
        $token = $this->config['sms_api_secret'];
        $from = $this->config['sms_sender_id'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $response = $this->httpPost($url, [
            'From' => $from,
            'To' => '+' . $phone,
            'Body' => $message
        ], [
            'Authorization: Basic ' . base64_encode($sid . ':' . $token)
        ]);

        return $this->logAndReturn('sms', $phone, $message,
            isset($response['sid']),
            $response['message'] ?? null
        );
    }

    /**
     * Envoyer via Infobip
     */
    private function sendInfobipSMS(string $phone, string $message): array
    {
        $url = 'https://api.infobip.com/sms/2/text/advanced';

        $data = [
            'messages' => [[
                'destinations' => [['to' => $phone]],
                'from' => $this->config['sms_sender_id'],
                'text' => $message
            ]]
        ];

        $response = $this->httpPost($url, $data, [
            'Authorization: App ' . $this->config['sms_api_key'],
            'Content-Type: application/json'
        ]);

        return $this->logAndReturn('sms', $phone, $message,
            isset($response['messages']),
            $response['requestError']['serviceException']['text'] ?? null
        );
    }

    /**
     * Envoyer un WhatsApp
     */
    public function sendWhatsApp(string $phone, string $message): array
    {
        $phone = $this->formatPhone($phone);

        if (!$this->config['whatsapp_enabled']) {
            return ['success' => false, 'error' => 'WhatsApp désactivé'];
        }

        if ($this->testMode) {
            return $this->logAndReturn('whatsapp', $phone, $message, true, 'Mode test');
        }

        switch ($this->config['whatsapp_provider']) {
            case 'twilio':
                return $this->sendTwilioWhatsApp($phone, $message);
            case 'meta':
                return $this->sendMetaWhatsApp($phone, $message);
            default:
                return ['success' => false, 'error' => 'Provider WhatsApp non configuré'];
        }
    }

    /**
     * Envoyer WhatsApp via Twilio
     */
    private function sendTwilioWhatsApp(string $phone, string $message): array
    {
        $sid = $this->config['whatsapp_api_key'];
        $token = $this->config['sms_api_secret'];
        $from = 'whatsapp:+' . $this->config['whatsapp_phone'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $response = $this->httpPost($url, [
            'From' => $from,
            'To' => 'whatsapp:+' . $phone,
            'Body' => $message
        ], [
            'Authorization: Basic ' . base64_encode($sid . ':' . $token)
        ]);

        return $this->logAndReturn('whatsapp', $phone, $message,
            isset($response['sid']),
            $response['message'] ?? null
        );
    }

    /**
     * Envoyer WhatsApp via Meta (Facebook)
     */
    private function sendMetaWhatsApp(string $phone, string $message): array
    {
        $token = $this->config['whatsapp_api_key'];
        $phoneId = $this->config['whatsapp_phone'];

        $url = "https://graph.facebook.com/v17.0/{$phoneId}/messages";

        $response = $this->httpPost($url, [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message]
        ], [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        return $this->logAndReturn('whatsapp', $phone, $message,
            isset($response['messages']),
            $response['error']['message'] ?? null
        );
    }

    /**
     * Envoyer un email
     */
    public function sendEmail(string $to, string $subject, string $body, bool $isHtml = false): array
    {
        if (!$this->config['email_enabled']) {
            return ['success' => false, 'error' => 'Email désactivé'];
        }

        if ($this->testMode) {
            return $this->logAndReturn('email', $to, $subject . ': ' . $body, true, 'Mode test');
        }

        // Utiliser PHPMailer si disponible, sinon mail() natif
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendEmailPHPMailer($to, $subject, $body, $isHtml);
        }

        // Mail PHP natif
        $headers = [
            'From: ' . $this->config['email_from_name'] . ' <' . $this->config['email_from'] . '>',
            'Reply-To: ' . $this->config['email_from'],
            'X-Mailer: PHP/' . phpversion()
        ];

        if ($isHtml) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
        }

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));

        return $this->logAndReturn('email', $to, $subject, $sent);
    }

    /**
     * Envoyer email via PHPMailer
     */
    private function sendEmailPHPMailer(string $to, string $subject, string $body, bool $isHtml): array
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_user'];
            $mail->Password = $this->config['smtp_pass'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp_port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->config['email_from'], $this->config['email_from_name']);
            $mail->addAddress($to);
            $mail->Subject = $subject;

            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
            }

            $mail->send();
            return $this->logAndReturn('email', $to, $subject, true);

        } catch (\Exception $e) {
            return $this->logAndReturn('email', $to, $subject, false, $e->getMessage());
        }
    }

    /**
     * Envoyer une notification de réservation
     */
    public function sendReservationConfirmation(array $reservation): array
    {
        $message = sprintf(
            "✅ Réservation confirmée!\n\n" .
            "📍 %s\n" .
            "📅 %s\n" .
            "⏰ %s - %s\n" .
            "💰 %s\n" .
            "🎫 Ticket: %s\n\n" .
            "Merci de votre confiance!\n" .
            "%s",
            $reservation['terrain_nom'],
            formatDate($reservation['date_reservation'], 'l d F Y'),
            formatTimeFull($reservation['heure_debut']),
            formatTimeFull($reservation['heure_fin']),
            formatPrice($reservation['montant_total']),
            $reservation['numero_ticket'],
            APP_NAME
        );

        return $this->send($reservation['client_telephone'], $message);
    }

    /**
     * Envoyer un rappel de réservation
     */
    public function sendReservationReminder(array $reservation): array
    {
        $message = sprintf(
            "⏰ Rappel: Votre réservation demain!\n\n" .
            "📍 %s\n" .
            "📅 %s à %s\n" .
            "🎫 %s\n\n" .
            "À demain!\n" .
            "%s",
            $reservation['terrain_nom'],
            formatDate($reservation['date_reservation'], 'd/m/Y'),
            formatTimeFull($reservation['heure_debut']),
            $reservation['numero_ticket'],
            APP_NAME
        );

        return $this->send($reservation['client_telephone'], $message);
    }

    /**
     * Envoyer un rappel de cotisation
     */
    public function sendCotisationReminder(array $membre, array $cotisation): array
    {
        $message = sprintf(
            "📋 Rappel de cotisation\n\n" .
            "Bonjour %s,\n" .
            "La cotisation de %s est en attente.\n" .
            "Montant: %s\n" .
            "Échéance: %s\n\n" .
            "Merci de régulariser rapidement.\n" .
            "%s",
            $membre['prenom'],
            $cotisation['mois'] ?? 'ce mois',
            formatPrice($cotisation['montant']),
            formatDate($cotisation['date_echeance'] ?? date('Y-m-d'), 'd/m/Y'),
            ACADEMIE_NAME
        );

        $phone = $membre['telephone_parent'] ?? $membre['telephone'];
        return $this->send($phone, $message);
    }

    /**
     * Envoyer une notification de séance
     */
    public function sendSeanceNotification(array $seance, array $membres): array
    {
        $message = sprintf(
            "⚽ Entraînement programmé\n\n" .
            "📅 %s\n" .
            "⏰ %s - %s\n" .
            "📍 %s\n" .
            "👨‍🏫 %s\n\n" .
            "À bientôt sur le terrain!\n" .
            "%s",
            formatDate($seance['date_seance'], 'l d F'),
            formatTime($seance['heure_debut']),
            formatTime($seance['heure_fin']),
            $seance['terrain_nom'] ?? 'À confirmer',
            $seance['entraineur_nom'] ?? '',
            ACADEMIE_NAME
        );

        $results = [];
        foreach ($membres as $membre) {
            $phone = $membre['telephone_parent'] ?? $membre['telephone'];
            if ($phone) {
                $results[$membre['id']] = $this->send($phone, $message);
            }
        }

        return $results;
    }

    /**
     * Formater un numéro de téléphone sénégalais
     */
    private function formatPhone(string $phone): string
    {
        // Nettoyer
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ajouter indicatif Sénégal si nécessaire
        if (strlen($phone) === 9 && preg_match('/^(77|78|76|70|75)/', $phone)) {
            $phone = '221' . $phone;
        }

        // Si commence par 0, remplacer par 221
        if (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '221' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Vérifier si c'est un email
     */
    private function isEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Requête HTTP POST
     */
    private function httpPost(string $url, array $data, array $headers = []): array
    {
        $ch = curl_init();

        $isJson = in_array('Content-Type: application/json', $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $isJson ? json_encode($data) : http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => $error];
        }

        return json_decode($response, true) ?? ['error' => 'Réponse invalide'];
    }

    /**
     * Logger et retourner le résultat
     */
    private function logAndReturn(string $channel, string $to, string $message, bool $success, ?string $error = null): array
    {
        $log = [
            'channel' => $channel,
            'to' => $to,
            'message' => substr($message, 0, 100),
            'success' => $success,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->logs[] = $log;

        // Enregistrer en base
        try {
            Database::insert('notifications_log', [
                'type' => $channel,
                'destinataire' => $to,
                'message' => $message,
                'statut' => $success ? 'envoye' : 'echec',
                'erreur' => $error
            ]);
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas
        }

        return [
            'success' => $success,
            'channel' => $channel,
            'error' => $error
        ];
    }

    /**
     * Obtenir les logs de la session
     */
    public function getLogs(): array
    {
        return $this->logs;
    }
}

/**
 * Fonction helper pour envoyer rapidement une notification
 */
function notify(string $to, string $message, string $channel = 'auto'): array
{
    return NotificationService::getInstance()->send($to, $message, $channel);
}
