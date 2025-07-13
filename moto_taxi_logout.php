<?php
session_start();

// Enregistrer la déconnexion si l'utilisateur était connecté
if (isset($_SESSION['moto_taxi_id'])) {
    require_once 'db.php';
    
    try {
        // Mettre à jour le statut du moto-taxi à inactif
        $stmt = $pdo->prepare("UPDATE moto_taxis SET statut = 'inactif' WHERE id = ?");
        $stmt->execute([$_SESSION['moto_taxi_id']]);
        
        // Enregistrer la déconnexion
        $stmt = $pdo->prepare("INSERT INTO historique_connexions (user_id, type_user, date_connexion, ip_address, action) VALUES (?, 'moto_taxi', NOW(), ?, 'deconnexion')");
        $stmt->execute([$_SESSION['moto_taxi_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Ignorer les erreurs lors de la déconnexion
    }
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: moto_taxi_login.php');
exit;
?> 