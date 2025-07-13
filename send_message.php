<?php
session_start();

// Inclure la configuration de base de données
try {
    require_once 'db.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['livraison_id']) || !isset($input['contenu'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$livraison_id = $input['livraison_id'];
$contenu = trim($input['contenu']);
$type = $input['type'] ?? 'texte';
$fichier = $input['fichier'] ?? null;

if (empty($contenu)) {
    echo json_encode(['success' => false, 'message' => 'Message vide']);
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
    $stmt = $pdo->prepare("SELECT id FROM livraisons WHERE id = ? AND user_id = ?");
    $stmt->execute([$livraison_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit;
    }
    
    // Insérer le message
    $stmt = $pdo->prepare("
        INSERT INTO messages (livraison_id, expediteur_id, type_expediteur, contenu, type_message, fichier, date_envoi) 
        VALUES (?, ?, 'user', ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$livraison_id, $_SESSION['user_id'], $contenu, $type, $fichier]);
    
    echo json_encode(['success' => true, 'message' => 'Message envoyé']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?> 