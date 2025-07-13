<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;
    
    // Récupérer les notifications de la base de données
    $stmt = $pdo->prepare("
        SELECT 
            id,
            type_notification,
            titre,
            message,
            lu,
            date_creation,
            date_lecture,
            data_extra
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY date_creation DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, (int)$limit, (int)$offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les notifications
    foreach ($notifications as &$notif) {
        $notif['date_formatee'] = formatRelativeTime($notif['date_creation']);
        $notif['icone'] = getNotificationIcon($notif['type_notification']);
        $notif['couleur'] = getNotificationColor($notif['type_notification']);
        
        // Décoder les données extra si elles existent
        if ($notif['data_extra']) {
            $notif['data_extra'] = json_decode($notif['data_extra'], true);
        }
    }
    
    // Compter le total de notifications non lues
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_non_lues
        FROM notifications 
        WHERE user_id = ? AND lu = 0
    ");
    $stmt->execute([$user_id]);
    $non_lues = $stmt->fetch(PDO::FETCH_ASSOC)['total_non_lues'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'non_lues' => (int)$non_lues
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Formate une date en temps relatif
 */
function formatRelativeTime($date_string) {
    $date = new DateTime($date_string);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->y > 0) {
        return "Il y a " . $diff->y . " an" . ($diff->y > 1 ? "s" : "");
    } elseif ($diff->m > 0) {
        return "Il y a " . $diff->m . " mois";
    } elseif ($diff->d > 0) {
        return "Il y a " . $diff->d . " jour" . ($diff->d > 1 ? "s" : "");
    } elseif ($diff->h > 0) {
        return "Il y a " . $diff->h . " heure" . ($diff->h > 1 ? "s" : "");
    } elseif ($diff->i > 0) {
        return "Il y a " . $diff->i . " minute" . ($diff->i > 1 ? "s" : "");
    } else {
        return "À l'instant";
    }
}

/**
 * Retourne l'icône appropriée selon le type de notification
 */
function getNotificationIcon($type) {
    switch ($type) {
        case 'livraison_acceptee':
            return 'fas fa-check-circle';
        case 'livraison_terminee':
            return 'fas fa-box-open';
        case 'livraison_annulee':
            return 'fas fa-times-circle';
        case 'message_nouveau':
            return 'fas fa-comment';
        case 'paiement_reussi':
            return 'fas fa-credit-card';
        case 'paiement_echec':
            return 'fas fa-exclamation-triangle';
        case 'recharge_solde':
            return 'fas fa-wallet';
        case 'promotion':
            return 'fas fa-gift';
        case 'systeme':
            return 'fas fa-info-circle';
        default:
            return 'fas fa-bell';
    }
}

/**
 * Retourne la couleur appropriée selon le type de notification
 */
function getNotificationColor($type) {
    switch ($type) {
        case 'livraison_acceptee':
        case 'livraison_terminee':
        case 'paiement_reussi':
        case 'recharge_solde':
            return 'success';
        case 'livraison_annulee':
        case 'paiement_echec':
            return 'danger';
        case 'message_nouveau':
        case 'promotion':
            return 'primary';
        case 'systeme':
            return 'info';
        default:
            return 'secondary';
    }
}
?> 