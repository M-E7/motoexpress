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

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['livraison_id']) || !isset($input['contenu'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$livraison_id = $input['livraison_id'];
$contenu = trim($input['contenu']);
$type_message = $input['type_message'] ?? 'texte';
$coordonnees_gps = $input['coordonnees_gps'] ?? null;

if (empty($contenu)) {
    echo json_encode(['success' => false, 'message' => 'Message vide']);
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
    $stmt = $pdo->prepare("SELECT id FROM livraisons WHERE id = ? AND moto_taxi_id = ?");
    $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit;
    }
    
    // Insérer le message
    $stmt = $pdo->prepare("
        INSERT INTO messages (livraison_id, expediteur_id, type_expediteur, contenu, type_message, coordonnees_gps, date_envoi) 
        VALUES (?, ?, 'moto_taxi', ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id'], $contenu, $type_message, $coordonnees_gps]);
    
    // Créer une notification pour le client
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type_user, titre, message, type_notification, lu, date_creation) 
        SELECT l.user_id, 'user', 'Nouveau message', ?, 'info', 0, NOW()
        FROM livraisons l WHERE l.id = ?
    ");
    $stmt->execute([$contenu, $livraison_id]);
    
    echo json_encode(['success' => true, 'message' => 'Message envoyé']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?> 