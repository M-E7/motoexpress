<?php
session_start();

// Vérifier que le moto-taxi est connecté
if (!isset($_SESSION['moto_taxi_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Moto-taxi non connecté']);
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
        echo json_encode(['success' => false, 'message' => 'Table messages non trouvée']);
        exit;
    }
    
    // Vérifier que la livraison appartient au moto-taxi
    $stmt = $pdo->prepare("
        SELECT l.*, u.nom as user_nom, u.prenom as user_prenom, u.photo_profil as user_photo 
        FROM livraisons l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.id = ? AND l.moto_taxi_id = ?
    ");
    $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id']]);
    $livraison = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$livraison) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit;
    }
    
    // Récupérer les messages
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE livraison_id = ? 
        ORDER BY date_envoi ASC
    ");
    $stmt->execute([$livraison_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'livraison_id' => $livraison_id,
        'user_name' => $livraison['user_prenom'] . ' ' . $livraison['user_nom'],
        'user_photo' => $livraison['user_photo'],
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