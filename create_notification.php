<?php
session_start();
require_once 'db.php';

/**
 * Crée une notification pour un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $type_notification Type de notification
 * @param string $titre Titre de la notification
 * @param string $message Message de la notification
 * @param array $data_extra Données supplémentaires (optionnel)
 * @return bool Succès de l'opération
 */
function createNotification($user_id, $type_notification, $titre, $message, $date_lecture = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, type_notification, titre, message, date_lecture, lu, date_creation
            ) VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $data_extra_json = $data_extra ? json_encode($data_extra) : null;
        
        $stmt->execute([
            $user_id, $type_notification, $titre, $message, $data_extra_json
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Crée une notification de livraison acceptée
 */
function notifyLivraisonAcceptee($user_id, $livraison_id, $mototaxi_nom) {
    $titre = "Livraison acceptée";
    $message = "Votre livraison #$livraison_id a été acceptée par $mototaxi_nom";
    $data_extra = ['livraison_id' => $livraison_id, 'mototaxi_nom' => $mototaxi_nom];
    
    return createNotification($user_id, 'livraison_acceptee', $titre, $message, $data_extra);
}

/**
 * Crée une notification de livraison terminée
 */
function notifyLivraisonTerminee($user_id, $livraison_id) {
    $titre = "Livraison terminée";
    $message = "Votre livraison #$livraison_id a été livrée avec succès";
    $data_extra = ['livraison_id' => $livraison_id];
    
    return createNotification($user_id, 'livraison_terminee', $titre, $message, $data_extra);
}

/**
 * Crée une notification de livraison annulée
 */
function notifyLivraisonAnnulee($user_id, $livraison_id, $raison = '') {
    $titre = "Livraison annulée";
    $message = "Votre livraison #$livraison_id a été annulée" . ($raison ? " : $raison" : "");
    $data_extra = ['livraison_id' => $livraison_id, 'raison' => $raison];
    
    return createNotification($user_id, 'livraison_annulee', $titre, $message, $data_extra);
}

/**
 * Crée une notification de nouveau message
 */
function notifyNouveauMessage($user_id, $expediteur_nom, $livraison_id = null) {
    $titre = "Nouveau message";
    $message = "Nouveau message de $expediteur_nom";
    $data_extra = ['expediteur_nom' => $expediteur_nom, 'livraison_id' => $livraison_id];
    
    return createNotification($user_id, 'message_nouveau', $titre, $message, $data_extra);
}

/**
 * Crée une notification de paiement réussi
 */
function notifyPaiementReussi($user_id, $montant, $type_paiement = 'recharge') {
    $titre = "Paiement réussi";
    $message = "Paiement de " . number_format($montant, 0, ',', ' ') . " FCFA réussi";
    $data_extra = ['montant' => $montant, 'type_paiement' => $type_paiement];
    
    return createNotification($user_id, 'paiement_reussi', $titre, $message, $data_extra);
}

/**
 * Crée une notification d'échec de paiement
 */
function notifyPaiementEchec($user_id, $montant, $raison = '') {
    $titre = "Échec de paiement";
    $message = "Échec du paiement de " . number_format($montant, 0, ',', ' ') . " FCFA" . ($raison ? " : $raison" : "");
    $data_extra = ['montant' => $montant, 'raison' => $raison];
    
    return createNotification($user_id, 'paiement_echec', $titre, $message, $data_extra);
}

/**
 * Crée une notification de recharge de solde
 */
function notifyRechargeSolde($user_id, $montant, $nouveau_solde) {
    $titre = "Solde rechargé";
    $message = "Votre solde a été rechargé de " . number_format($montant, 0, ',', ' ') . " FCFA. Nouveau solde: " . number_format($nouveau_solde, 0, ',', ' ') . " FCFA";
    $data_extra = ['montant' => $montant, 'nouveau_solde' => $nouveau_solde];
    
    return createNotification($user_id, 'recharge_solde', $titre, $message, $data_extra);
}

/**
 * Crée une notification de promotion
 */
function notifyPromotion($user_id, $titre_promo, $description) {
    $titre = "Promotion disponible";
    $message = "$titre_promo : $description";
    $data_extra = ['titre_promo' => $titre_promo, 'description' => $description];
    
    return createNotification($user_id, 'promotion', $titre, $message, $data_extra);
}

/**
 * Crée une notification système
 */
function notifySysteme($user_id, $titre, $message, $data_extra = null) {
    return createNotification($user_id, 'systeme', $titre, $message, $data_extra);
}

/**
 * Marque une notification comme lue
 */
function marquerNotificationLue($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lu = 1 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        error_log("Erreur marquage notification lue: " . $e->getMessage());
        return false;
    }
}

/**
 * Marque toutes les notifications d'un utilisateur comme lues
 */
function marquerToutesNotificationsLues($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lu = 1 
            WHERE user_id = ?
        ");
        
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Erreur marquage toutes notifications lues: " . $e->getMessage());
        return false;
    }
}

// Si le script est appelé directement (pour les tests)
if (basename($_SERVER['PHP_SELF']) === 'create_notification.php') {
    // Simuler une session utilisateur pour les tests
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
    }
    
    echo "<h1>Test des notifications</h1>";
    
    // Test de création de notifications
    $user_id = $_SESSION['user_id'];
    
    echo "<h2>Création de notifications de test</h2>";
    
    // Notification de livraison acceptée
    if (notifyLivraisonAcceptee($user_id, 123, "Amadou")) {
        echo "<p style='color:green;'>✅ Notification livraison acceptée créée</p>";
    } else {
        echo "<p style='color:red;'>❌ Erreur création notification livraison acceptée</p>";
    }
    
    // Notification de paiement réussi
    if (notifyPaiementReussi($user_id, 5000)) {
        echo "<p style='color:green;'>✅ Notification paiement réussi créée</p>";
    } else {
        echo "<p style='color:red;'>❌ Erreur création notification paiement réussi</p>";
    }
    
    // Notification de promotion
    if (notifyPromotion($user_id, "Réduction 20%", "20% de réduction sur votre prochaine livraison")) {
        echo "<p style='color:green;'>✅ Notification promotion créée</p>";
    } else {
        echo "<p style='color:red;'>❌ Erreur création notification promotion</p>";
    }
    
    echo "<p><a href='user_home.php'>Retour à l'accueil</a></p>";
}
?> 