<?php
session_start();
require_once 'db.php';
require_once 'create_notification.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $notification_id = $_POST['notification_id'] ?? null;
    $mark_all = isset($_POST['mark_all']) && $_POST['mark_all'] === 'true';
    
    if ($mark_all) {
        // Marquer toutes les notifications comme lues
        $success = marquerToutesNotificationsLues($user_id);
        $message = 'Toutes les notifications ont été marquées comme lues';
    } elseif ($notification_id) {
        // Marquer une notification spécifique comme lue
        $success = marquerNotificationLue($notification_id, $user_id);
        $message = 'Notification marquée comme lue';
    } else {
        throw new Exception('Paramètres manquants');
    }
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        throw new Exception('Erreur lors du marquage de la notification');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 