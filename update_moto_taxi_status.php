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
$status = $input['status'] ?? '';

if (!in_array($status, ['actif', 'inactif'])) {
    echo json_encode(['success' => false, 'message' => 'Statut invalide']);
    exit;
}

try {
    // Mettre à jour le statut
    $stmt = $pdo->prepare("UPDATE moto_taxis SET statut = ? WHERE id = ?");
    $stmt->execute([$status, $_SESSION['moto_taxi_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
?> 