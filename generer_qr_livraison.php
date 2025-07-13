<?php
session_start();
require_once 'db.php';
require_once 'qr_generator.php';

// Vérifier si le moto-taxi est connecté
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
    // Vérifier que la livraison appartient au moto-taxi et est en cours
    $stmt = $pdo->prepare("SELECT * FROM livraisons WHERE id = ? AND moto_taxi_id = ? AND statut = 'en_cours'");
    $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id']]);
    $livraison = $stmt->fetch();
    
    if (!$livraison) {
        throw new Exception('Livraison non trouvée ou ne vous appartient pas.');
    }
    
    // Vérifier si un QR code existe déjà
    $stmt = $pdo->prepare("SELECT * FROM qr_codes_livraison WHERE livraison_id = ? AND statut = 'actif'");
    $stmt->execute([$livraison_id]);
    $qr_existant = $stmt->fetch();
    
    if ($qr_existant) {
        throw new Exception('Un QR code existe déjà pour cette livraison.');
    }
    
    // Générer le QR code
    $qr_result = genererQRCodeLivraison($livraison_id, $_SESSION['moto_taxi_id']);
    
    if (!$qr_result['success']) {
        throw new Exception('Erreur lors de la génération du QR code: ' . $qr_result['message']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'QR code généré avec succès',
        'qr_path' => $qr_result['qr_path'],
        'qr_filename' => $qr_result['qr_filename']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 