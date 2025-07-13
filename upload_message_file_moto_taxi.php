<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Vérifier que le moto-taxi est connecté
if (!isset($_SESSION['moto_taxi_id'])) {
    echo json_encode(['success' => false, 'message' => 'Moto-taxi non connecté']);
    exit;
}

// Vérifier qu'un fichier a été uploadé
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier uploadé']);
    exit;
}

$livraison_id = $_POST['livraison_id'] ?? null;
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
    
    $file = $_FILES['file'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Vérifier le type de fichier
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé']);
        exit;
    }
    
    // Vérifier la taille (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 5MB)']);
        exit;
    }
    
    // Créer le dossier d'upload s'il n'existe pas
    $upload_dir = 'uploads/messages/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Générer un nom de fichier unique
    $filename = 'msg_' . time() . '_' . uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Insérer le message dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO messages (livraison_id, expediteur_id, type_expediteur, contenu, type_message, fichier, date_envoi) 
            VALUES (?, ?, 'moto_taxi', 'Photo', 'photo', ?, NOW())
        ");
        
        $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id'], $filepath]);
        
        // Créer une notification pour le client
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type_user, titre, message, type_notification, lu, date_creation) 
            SELECT l.user_id, 'user', 'Nouveau message', 'Photo reçue', 'info', 0, NOW()
            FROM livraisons l WHERE l.id = ?
        ");
        $stmt->execute([$livraison_id]);
        
        echo json_encode(['success' => true, 'message' => 'Fichier uploadé avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?> 