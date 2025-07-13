<?php
session_start();
require_once 'db.php';

// Vérifier si le moto-taxi est connecté
if (!isset($_SESSION['moto_taxi_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    // Récupérer les demandes de livraison disponibles
    $stmt = $pdo->prepare("
        SELECT l.*, u.nom as user_nom, u.prenom as user_prenom, u.telephone as user_telephone
        FROM livraisons l
        JOIN users u ON l.user_id = u.id
        WHERE l.statut = 'en_attente'
        AND l.date_creation >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY l.date_creation DESC
    ");
    $stmt->execute();
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'demandes' => $demandes]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération']);
}
?> 