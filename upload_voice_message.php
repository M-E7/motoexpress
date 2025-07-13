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

// Vérifier qu'un fichier audio a été envoyé
if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier audio reçu']);
    exit;
}

$livraison_id = $_POST['livraison_id'] ?? null;

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
    
    // Vérifier que la livraison appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM livraisons WHERE id = ? AND user_id = ?");
    $stmt->execute([$livraison_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit;
    }
    
    // Créer le dossier pour les messages vocaux s'il n'existe pas
    $upload_dir = 'uploads/voice_messages/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Générer un nom de fichier unique
    $file_extension = pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
    $unique_filename = 'voice_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $unique_filename;
    
    // Déplacer le fichier uploadé
    if (move_uploaded_file($_FILES['audio']['tmp_name'], $file_path)) {
        // Insérer le message vocal dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO messages (livraison_id, expediteur_id, type_expediteur, contenu, type_message, fichier, date_envoi) 
            VALUES (?, ?, 'user', 'Message vocal', 'audio', ?, NOW())
        ");
        
        $stmt->execute([
            $livraison_id, 
            $_SESSION['user_id'],
            $file_path
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Message vocal envoyé',
            'file_path' => $file_path
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?> 