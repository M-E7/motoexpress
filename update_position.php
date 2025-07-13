<?php
session_start();
require_once 'db.php';

// Vérifier si le moto-taxi est connecté
if (!isset($_SESSION['moto_taxi_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);
$latitude = $input['latitude'] ?? 0;
$longitude = $input['longitude'] ?? 0;

if (!$latitude || !$longitude) {
    echo json_encode(['success' => false, 'message' => 'Coordonnées manquantes']);
    exit;
}

try {
    // Mettre à jour la position du moto-taxi
    $stmt = $pdo->prepare("UPDATE moto_taxis SET latitude = ?, longitude = ?, derniere_position = NOW() WHERE id = ?");
    $stmt->execute([$latitude, $longitude, $_SESSION['moto_taxi_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Position mise à jour']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
?> 