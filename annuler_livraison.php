<?php
// Désactiver l'affichage des erreurs pour éviter qu'elles polluent le JSON
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once 'db.php';

// Définir le header JSON dès le début
header('Content-Type: application/json');

if (!isset($_SESSION['moto_taxi_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$livraison_id = $input['livraison_id'] ?? 0;
if (!$livraison_id) {
    echo json_encode(['success' => false, 'message' => 'ID de livraison manquant']);
    exit;
}
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM livraisons WHERE id = ? AND moto_taxi_id = ? AND statut = 'en_cours'");
    $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id']]);
    $liv = $stmt->fetch();
    if (!$liv) throw new Exception('Livraison non trouvée ou déjà annulée.');
    $stmt = $pdo->prepare("UPDATE livraisons SET statut = 'annulee' WHERE id = ?");
    $stmt->execute([$livraison_id]);
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type_user, titre, message, date_creation) VALUES (?, 'user', 'Livraison annulée', 'Votre livraison a été annulée par le moto-taxi.', NOW())");
    $stmt->execute([$liv['user_id']]);
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 