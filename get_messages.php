<?php
session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Inclure la configuration de base de données
try {
    require_once 'db.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

$livraison_id = $_GET['livraison_id'] ?? null;

if (!$livraison_id) {
    echo json_encode(['success' => false, 'message' => 'ID de livraison manquant']);
    exit;
}

try {
    // Vérifier que la table messages existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Table messages non trouvée. Veuillez exécuter create_messages_table.sql']);
        exit;
    }
    
    // Vérifier que la livraison appartient à l'utilisateur
    $stmt = $pdo->prepare("
        SELECT l.*, mt.nom as mototaxi_nom, mt.photo_profil as moto_taxi_photo 
        FROM livraisons l 
        LEFT JOIN moto_taxis mt ON l.moto_taxi_id = mt.id 
        WHERE l.id = ? AND l.user_id = ?
    ");
    $stmt->execute([$livraison_id, $_SESSION['user_id']]);
    $livraison = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$livraison) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit;
    }
    
    // Récupérer les messages
    $stmt = $pdo->prepare("
        SELECT m.*, 
               CASE 
                   WHEN m.type_expediteur = 'user' THEN 'user'
                   WHEN m.type_expediteur = 'moto_taxi' THEN 'mototaxi'
               END as expediteur
        FROM messages m
        WHERE m.livraison_id = ? 
        ORDER BY m.date_envoi ASC
    ");
    $stmt->execute([$livraison_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'livraison_id' => $livraison_id,
        'mototaxi_nom' => $livraison['mototaxi_nom'] ?? 'Moto-taxi',
        'moto_taxi_photo' => $livraison['moto_taxi_photo'],
        'statut' => $livraison['statut'],
        'messages' => $messages
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?> 