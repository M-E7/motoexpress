<?php
// Désactiver l'affichage des erreurs pour éviter qu'elles polluent le JSON
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once 'db.php';
require_once 'qr_generator.php';

// Définir le header JSON dès le début
header('Content-Type: application/json');

// Vérifier si le moto-taxi est connecté
if (!isset($_SESSION['moto_taxi_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);
$livraison_id = $input['livraison_id'] ?? 0;

if (!$livraison_id) {
    echo json_encode(['success' => false, 'message' => 'ID de livraison manquant']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Vérifier que la livraison est disponible
    $stmt = $pdo->prepare("SELECT * FROM livraisons WHERE id = ? AND statut = 'en_attente'");
    $stmt->execute([$livraison_id]);
    $livraison = $stmt->fetch();
    
    if (!$livraison) {
        throw new Exception('Livraison non disponible');
    }
    
    // Vérifier que le moto-taxi n'a pas déjà une livraison en cours
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM livraisons WHERE moto_taxi_id = ? AND statut = 'en_cours'");
    $stmt->execute([$_SESSION['moto_taxi_id']]);
    $livraisons_en_cours = $stmt->fetchColumn();
    
    if ($livraisons_en_cours > 0) {
        throw new Exception('Vous avez déjà une livraison en cours');
    }
    
    // Accepter la livraison
    $stmt = $pdo->prepare("UPDATE livraisons SET moto_taxi_id = ?, statut = 'en_cours', date_acceptation = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['moto_taxi_id'], $livraison_id]);
    
    // Générer le QR code pour cette livraison
    $qr_result = genererQRCodeLivraison($livraison_id, $_SESSION['moto_taxi_id']);
    
    if (!$qr_result['success']) {
        throw new Exception('Erreur lors de la génération du QR code: ' . $qr_result['message']);
    }
    
    // Créer une notification pour l'utilisateur
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type_user, titre, message, date_creation) 
        VALUES (?, 'user', 'Livraison acceptée', 'Votre livraison a été acceptée par un moto-taxi. Un QR code a été généré pour confirmer la rencontre.', NOW())
    ");
    $stmt->execute([$livraison['user_id']]);
    
    // Créer une notification pour le moto-taxi
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type_user, titre, message, date_creation) 
        VALUES (?, 'moto_taxi', 'Livraison acceptée', 'Vous avez accepté une nouvelle livraison. Un QR code a été généré pour la confirmation.', NOW())
    ");
    $stmt->execute([$_SESSION['moto_taxi_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Livraison acceptée avec succès',
        'qr_path' => $qr_result['qr_path'],
        'qr_filename' => $qr_result['qr_filename']
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 