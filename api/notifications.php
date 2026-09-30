<?php
/**
 * API Notifications
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Notification.php';

header('Content-Type: application/json; charset=utf-8');

// Vérifier l'authentification
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$userId = Auth::id();
$action = get('action', 'list');

try {
    switch ($action) {
        case 'list':
            // Liste des notifications
            $limit = min(50, (int)get('limit', 20));
            $unreadOnly = get('unread') === '1';

            $notifications = Notification::getForUser($userId, $limit, $unreadOnly);
            $unreadCount = Notification::countUnread($userId);

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'count':
            // Juste le compteur
            $count = Notification::countUnread($userId);
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;

        case 'read':
            // Marquer comme lu
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée');
            }

            $id = (int)post('id');
            if ($id) {
                Notification::markAsRead($id, $userId);
            }

            echo json_encode(['success' => true]);
            break;

        case 'read_all':
            // Marquer tout comme lu
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée');
            }

            Notification::markAllAsRead($userId);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Action inconnue');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
