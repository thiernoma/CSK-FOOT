<?php
/**
 * Service de génération PDF
 * Utilise TCPDF pour générer les documents
 * Complexe Sportif Kaira
 */

// Charger TCPDF (à installer via Composer ou téléchargement manuel)
// composer require tecnickcom/tcpdf
if (file_exists(ROOT_PATH . 'vendor/autoload.php')) {
    require_once ROOT_PATH . 'vendor/autoload.php';
}

class PdfGenerator
{
    protected $pdf;
    protected $primaryColor = [26, 58, 107]; // #1A3A6B
    protected $accentColor = [232, 99, 26];  // #E8631A

    public function __construct()
    {
        if (!class_exists('TCPDF')) {
            throw new Exception('TCPDF n\'est pas installé. Exécutez: composer require tecnickcom/tcpdf');
        }

        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->setupDocument();
    }

    /**
     * Configuration initiale du document
     */
    protected function setupDocument(): void
    {
        $this->pdf->SetCreator(APP_NAME);
        $this->pdf->SetAuthor(ACADEMIE_NAME);
        $this->pdf->SetMargins(15, 25, 15);
        $this->pdf->SetAutoPageBreak(true, 20);
        $this->pdf->SetFont('helvetica', '', 10);

        // Désactiver l'en-tête et pied de page par défaut
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
    }

    /**
     * Ajouter un en-tête personnalisé
     */
    protected function addHeader(string $title, string $subtitle = ''): void
    {
        // Logo
        $logoPath = function_exists('getLogoFsPath') ? getLogoFsPath() : ROOT_PATH . 'public/images/logo-akf.png';
        if (file_exists($logoPath)) {
            $this->pdf->Image($logoPath, 15, 10, 25);
        }

        // Titre principal
        $this->pdf->SetXY(45, 12);
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->SetTextColor(...$this->primaryColor);
        $this->pdf->Cell(0, 8, APP_NAME, 0, 1, 'L');

        // Sous-titre
        $this->pdf->SetXY(45, 20);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetTextColor(100, 100, 100);
        $this->pdf->Cell(0, 5, ACADEMIE_NAME . ' - ' . APP_SLOGAN, 0, 1, 'L');

        // Titre du document
        $this->pdf->SetXY(15, 35);
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetTextColor(...$this->primaryColor);
        $this->pdf->Cell(0, 8, $title, 0, 1, 'C');

        if ($subtitle) {
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->SetTextColor(100, 100, 100);
            $this->pdf->Cell(0, 5, $subtitle, 0, 1, 'C');
        }

        // Ligne de séparation
        $this->pdf->SetDrawColor(...$this->accentColor);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line(15, 48, 195, 48);

        $this->pdf->SetY(55);
    }

    /**
     * Ajouter un pied de page
     */
    protected function addFooter(): void
    {
        $this->pdf->SetY(-15);
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetTextColor(128, 128, 128);

        $footer = sprintf(
            'Généré le %s | %s | Page %d/%d',
            date('d/m/Y à H:i'),
            APP_NAME,
            $this->pdf->getAliasNumPage(),
            $this->pdf->getAliasNbPages()
        );

        $this->pdf->Cell(0, 10, $footer, 0, 0, 'C');
    }

    /**
     * Générer une facture de réservation
     */
    public function generateFacture(array $reservation): string
    {
        $this->pdf->AddPage();
        $this->addHeader('FACTURE', 'N° ' . $reservation['numero_ticket']);

        // Informations client
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell(90, 7, 'Informations client', 0, 0, 'L');
        $this->pdf->Cell(90, 7, 'Détails réservation', 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', 10);

        // Colonne gauche - Client
        $y = $this->pdf->GetY();
        $this->pdf->MultiCell(85, 5,
            "Nom: " . $reservation['client_nom'] . "\n" .
            "Téléphone: " . $reservation['client_telephone'] . "\n" .
            "Email: " . ($reservation['client_email'] ?? '-'),
            0, 'L', false, 0
        );

        // Colonne droite - Réservation
        $this->pdf->SetXY(105, $y);
        $this->pdf->MultiCell(85, 5,
            "Date: " . formatDate($reservation['date_reservation'], 'd/m/Y') . "\n" .
            "Horaire: " . formatTimeFull($reservation['heure_debut']) . ' - ' . formatTimeFull($reservation['heure_fin']) . "\n" .
            "Terrain: " . $reservation['terrain_nom'],
            0, 'L', false, 1
        );

        $this->pdf->Ln(10);

        // Tableau des prestations
        $this->pdf->SetFillColor(...$this->primaryColor);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetFont('helvetica', 'B', 10);

        $this->pdf->Cell(100, 8, 'Description', 1, 0, 'L', true);
        $this->pdf->Cell(40, 8, 'Durée', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, 'Montant', 1, 1, 'R', true);

        // Lignes
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetFillColor(245, 245, 245);

        $duree = opDurationHours($reservation['heure_debut'], $reservation['heure_fin']);

        $this->pdf->Cell(100, 8, 'Location terrain ' . $reservation['terrain_nom'], 1, 0, 'L', true);
        $this->pdf->Cell(40, 8, $duree . 'h', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, formatPrice($reservation['montant_total']), 1, 1, 'R', true);

        // Total
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetFillColor(...$this->primaryColor);
        $this->pdf->SetTextColor(255, 255, 255);

        $this->pdf->Cell(140, 10, 'TOTAL', 1, 0, 'R', true);
        $this->pdf->Cell(40, 10, formatPrice($reservation['montant_total']), 1, 1, 'R', true);

        // Statut paiement
        $this->pdf->Ln(10);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 10);

        $statutPaiement = $reservation['statut_paiement'] === 'paye' ? 'PAYÉ' :
                         ($reservation['statut_paiement'] === 'partiel' ? 'PAIEMENT PARTIEL' : 'EN ATTENTE');

        $this->pdf->Cell(0, 8, 'Statut du paiement: ' . $statutPaiement, 0, 1, 'L');

        if ($reservation['montant_paye'] > 0) {
            $this->pdf->Cell(0, 6, 'Montant payé: ' . formatPrice($reservation['montant_paye']), 0, 1, 'L');
            $reste = $reservation['montant_total'] - $reservation['montant_paye'];
            if ($reste > 0) {
                $this->pdf->SetTextColor(200, 0, 0);
                $this->pdf->Cell(0, 6, 'Reste à payer: ' . formatPrice($reste), 0, 1, 'L');
            }
        }

        // Conditions
        $this->pdf->Ln(15);
        $this->pdf->SetTextColor(100, 100, 100);
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->MultiCell(0, 4,
            "Conditions générales:\n" .
            "- Toute annulation doit être effectuée 24h à l'avance.\n" .
            "- Le terrain doit être libéré à l'heure convenue.\n" .
            "- Les équipements endommagés seront facturés.",
            0, 'L'
        );

        $this->addFooter();

        return $this->pdf->Output('facture_' . $reservation['numero_ticket'] . '.pdf', 'S');
    }

    /**
     * Générer un reçu de paiement
     */
    public function generateRecu(array $paiement): string
    {
        $this->pdf->AddPage();
        $this->addHeader('REÇU DE PAIEMENT', 'N° ' . str_pad($paiement['id'], 6, '0', STR_PAD_LEFT));

        // Informations
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->Cell(50, 7, 'Date du paiement:', 0, 0, 'L');
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 7, formatDate($paiement['date_paiement'], 'd/m/Y'), 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 7, 'Mode de paiement:', 0, 0, 'L');
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 7, ucfirst($paiement['mode_paiement']), 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 7, 'Réservation:', 0, 0, 'L');
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 7, $paiement['numero_ticket'] ?? 'N/A', 0, 1, 'L');

        $this->pdf->Ln(10);

        // Montant encadré
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetDrawColor(...$this->primaryColor);
        $this->pdf->SetLineWidth(0.3);

        $this->pdf->Rect(40, $this->pdf->GetY(), 130, 30, 'DF');

        $this->pdf->SetXY(40, $this->pdf->GetY() + 5);
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->Cell(130, 8, 'Montant reçu', 0, 1, 'C');

        $this->pdf->SetX(40);
        $this->pdf->SetFont('helvetica', 'B', 20);
        $this->pdf->SetTextColor(...$this->primaryColor);
        $this->pdf->Cell(130, 12, formatPrice($paiement['montant']), 0, 1, 'C');

        $this->pdf->Ln(20);

        // Signature
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(90, 7, 'Signature du client:', 0, 0, 'L');
        $this->pdf->Cell(90, 7, 'Cachet et signature:', 0, 1, 'L');

        $this->pdf->Ln(25);
        $this->pdf->Line(15, $this->pdf->GetY(), 85, $this->pdf->GetY());
        $this->pdf->Line(105, $this->pdf->GetY(), 195, $this->pdf->GetY());

        $this->addFooter();

        return $this->pdf->Output('recu_' . $paiement['id'] . '.pdf', 'S');
    }

    /**
     * Générer la carte de membre académie
     */
    public function generateCarteMembre(array $membre): string
    {
        // Format carte de visite (85.6mm x 53.98mm)
        $this->pdf = new TCPDF('L', 'mm', [85.6, 54], true, 'UTF-8', false);
        $this->pdf->SetMargins(3, 3, 3);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->AddPage();

        // Fond
        $this->pdf->SetFillColor(...$this->primaryColor);
        $this->pdf->Rect(0, 0, 85.6, 20, 'F');

        // Logo et nom académie
        $logoPath = function_exists('getLogoFsPath') ? getLogoFsPath() : ROOT_PATH . 'public/images/logo-akf.png';
        if (file_exists($logoPath)) {
            $this->pdf->Image($logoPath, 3, 2, 16);
        }

        $this->pdf->SetXY(22, 4);
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(60, 5, ACADEMIE_NAME, 0, 1, 'L');

        $this->pdf->SetXY(22, 9);
        $this->pdf->SetFont('helvetica', '', 7);
        $this->pdf->Cell(60, 4, 'CARTE DE MEMBRE', 0, 1, 'L');

        $this->pdf->SetXY(22, 13);
        $this->pdf->SetFont('helvetica', '', 6);
        $this->pdf->SetTextColor(200, 200, 200);
        $this->pdf->Cell(60, 4, APP_SLOGAN, 0, 1, 'L');

        // Photo placeholder
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Rect(5, 23, 20, 25, 'F');

        $photoPath = ROOT_PATH . 'public/uploads/membres/' . $membre['photo'];
        if (!empty($membre['photo']) && file_exists($photoPath)) {
            $this->pdf->Image($photoPath, 5, 23, 20, 25);
        } else {
            $this->pdf->SetXY(5, 32);
            $this->pdf->SetFont('helvetica', '', 6);
            $this->pdf->SetTextColor(150, 150, 150);
            $this->pdf->Cell(20, 5, 'PHOTO', 0, 0, 'C');
        }

        // Informations membre
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->SetXY(28, 23);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(55, 5, strtoupper($membre['nom'] . ' ' . $membre['prenom']), 0, 1, 'L');

        $this->pdf->SetXY(28, 29);
        $this->pdf->SetFont('helvetica', '', 7);
        $this->pdf->Cell(55, 4, 'N° Licence: ' . $membre['numero_licence'], 0, 1, 'L');

        $this->pdf->SetXY(28, 34);
        $this->pdf->Cell(55, 4, 'Catégorie: ' . strtoupper($membre['categorie']), 0, 1, 'L');

        $this->pdf->SetXY(28, 39);
        $this->pdf->Cell(55, 4, 'Né(e) le: ' . formatDate($membre['date_naissance'], 'd/m/Y'), 0, 1, 'L');

        // Validité
        $this->pdf->SetXY(28, 44);
        $this->pdf->SetFont('helvetica', 'B', 7);
        $this->pdf->SetTextColor(...$this->accentColor);
        $this->pdf->Cell(55, 4, 'Valide jusqu\'au: ' . formatDate($membre['date_fin_cotisation'] ?? date('Y-12-31'), 'd/m/Y'), 0, 1, 'L');

        // QR Code avec numéro licence
        // $this->pdf->write2DBarcode($membre['numero_licence'], 'QRCODE,L', 70, 38, 12, 12);

        return $this->pdf->Output('carte_' . $membre['numero_licence'] . '.pdf', 'S');
    }

    /**
     * Générer un rapport financier
     */
    public function generateRapportFinancier(array $data, string $periode): string
    {
        $this->pdf->AddPage();
        $this->addHeader('RAPPORT FINANCIER', $periode);

        // Résumé
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetTextColor(...$this->primaryColor);
        $this->pdf->Cell(0, 8, 'Résumé', 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetTextColor(0, 0, 0);

        // Tableau résumé
        $this->pdf->SetFillColor(245, 245, 245);

        $this->pdf->Cell(90, 8, 'Total des réservations', 1, 0, 'L', true);
        $this->pdf->Cell(90, 8, formatPrice($data['total_reservations'] ?? 0), 1, 1, 'R', true);

        $this->pdf->Cell(90, 8, 'Total des paiements reçus', 1, 0, 'L');
        $this->pdf->Cell(90, 8, formatPrice($data['total_paiements'] ?? 0), 1, 1, 'R');

        $this->pdf->Cell(90, 8, 'Cotisations académie', 1, 0, 'L', true);
        $this->pdf->Cell(90, 8, formatPrice($data['total_cotisations'] ?? 0), 1, 1, 'R', true);

        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(...$this->primaryColor);
        $this->pdf->SetTextColor(255, 255, 255);

        $total = ($data['total_paiements'] ?? 0) + ($data['total_cotisations'] ?? 0);
        $this->pdf->Cell(90, 10, 'TOTAL RECETTES', 1, 0, 'L', true);
        $this->pdf->Cell(90, 10, formatPrice($total), 1, 1, 'R', true);

        // Détails par mode de paiement
        if (!empty($data['par_mode'])) {
            $this->pdf->Ln(10);
            $this->pdf->SetTextColor(...$this->primaryColor);
            $this->pdf->Cell(0, 8, 'Répartition par mode de paiement', 0, 1, 'L');

            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->SetFont('helvetica', '', 10);

            foreach ($data['par_mode'] as $mode) {
                $this->pdf->Cell(90, 7, ucfirst($mode['mode_paiement']), 1, 0, 'L');
                $this->pdf->Cell(90, 7, formatPrice($mode['total']), 1, 1, 'R');
            }
        }

        // Détails par terrain
        if (!empty($data['par_terrain'])) {
            $this->pdf->Ln(10);
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->SetTextColor(...$this->primaryColor);
            $this->pdf->Cell(0, 8, 'Répartition par terrain', 0, 1, 'L');

            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->SetFont('helvetica', '', 10);

            foreach ($data['par_terrain'] as $terrain) {
                $this->pdf->Cell(90, 7, $terrain['nom'], 1, 0, 'L');
                $this->pdf->Cell(45, 7, $terrain['nb_reservations'] . ' réservations', 1, 0, 'C');
                $this->pdf->Cell(45, 7, formatPrice($terrain['total']), 1, 1, 'R');
            }
        }

        $this->addFooter();

        return $this->pdf->Output('rapport_financier_' . date('Y-m-d') . '.pdf', 'S');
    }

    /**
     * Générer une liste de présence
     */
    public function generateListePresence(array $seance, array $presences): string
    {
        $this->pdf->AddPage();
        $this->addHeader('FEUILLE DE PRÉSENCE', formatDate($seance['date_seance'], 'd/m/Y'));

        // Infos séance
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->Cell(40, 7, 'Catégorie:', 0, 0, 'L');
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(60, 7, strtoupper($seance['categorie']), 0, 0, 'L');

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(30, 7, 'Horaire:', 0, 0, 'L');
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 7, formatTime($seance['heure_debut']) . ' - ' . formatTime($seance['heure_fin']), 0, 1, 'L');

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(40, 7, 'Entraîneur:', 0, 0, 'L');
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(60, 7, $seance['entraineur_nom'] ?? '-', 0, 0, 'L');

        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(30, 7, 'Terrain:', 0, 0, 'L');
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 7, $seance['terrain_nom'] ?? '-', 0, 1, 'L');

        $this->pdf->Ln(5);

        // Tableau des présences
        $this->pdf->SetFillColor(...$this->primaryColor);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetFont('helvetica', 'B', 9);

        $this->pdf->Cell(10, 8, 'N°', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Nom & Prénom', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, 'N° Licence', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Présent', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Absent', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Retard', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Signature', 1, 1, 'C', true);

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 9);

        $i = 1;
        foreach ($presences as $presence) {
            $fill = $i % 2 === 0;
            $this->pdf->SetFillColor(250, 250, 250);

            $this->pdf->Cell(10, 7, $i, 1, 0, 'C', $fill);
            $this->pdf->Cell(50, 7, $presence['membre_nom'] . ' ' . $presence['membre_prenom'], 1, 0, 'L', $fill);
            $this->pdf->Cell(30, 7, $presence['numero_licence'], 1, 0, 'C', $fill);
            $this->pdf->Cell(20, 7, $presence['statut'] === 'present' ? 'X' : '', 1, 0, 'C', $fill);
            $this->pdf->Cell(20, 7, $presence['statut'] === 'absent' ? 'X' : '', 1, 0, 'C', $fill);
            $this->pdf->Cell(20, 7, $presence['statut'] === 'retard' ? 'X' : '', 1, 0, 'C', $fill);
            $this->pdf->Cell(30, 7, '', 1, 1, 'C', $fill);

            $i++;
        }

        // Statistiques
        $this->pdf->Ln(10);
        $this->pdf->SetFont('helvetica', 'B', 10);

        $presents = count(array_filter($presences, fn($p) => $p['statut'] === 'present'));
        $absents = count(array_filter($presences, fn($p) => $p['statut'] === 'absent'));
        $retards = count(array_filter($presences, fn($p) => $p['statut'] === 'retard'));

        $this->pdf->Cell(60, 7, 'Présents: ' . $presents, 0, 0, 'L');
        $this->pdf->Cell(60, 7, 'Absents: ' . $absents, 0, 0, 'L');
        $this->pdf->Cell(60, 7, 'Retards: ' . $retards, 0, 1, 'L');

        // Signature entraîneur
        $this->pdf->Ln(15);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 7, 'Signature de l\'entraîneur:', 0, 1, 'L');
        $this->pdf->Ln(15);
        $this->pdf->Line(15, $this->pdf->GetY(), 80, $this->pdf->GetY());

        $this->addFooter();

        return $this->pdf->Output('presence_' . $seance['date_seance'] . '.pdf', 'S');
    }

    /**
     * Télécharger le PDF
     */
    public function download(string $content, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $content;
        exit;
    }

    /**
     * Afficher le PDF dans le navigateur
     */
    public function display(string $content, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
