<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Vérifier que le moto-taxi est connecté
if (!isset($_SESSION['moto_taxi_id'])) {
    echo json_encode(['success' => false, 'message' => 'Moto-taxi non connecté']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$livraison_id = $input['livraison_id'] ?? null;

if (!$livraison_id) {
    echo json_encode(['success' => false, 'message' => 'ID de livraison manquant']);
    exit;
}

try {
    // Vérifier que la livraison appartient au moto-taxi
    $stmt = $pdo->prepare("SELECT id FROM livraisons WHERE id = ? AND moto_taxi_id = ?");
    $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit;
    }
    
    // Marquer les messages comme lus
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET lu = 1 
        WHERE livraison_id = ? AND type_expediteur = 'user'
    ");
    $stmt->execute([$livraison_id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
?> 