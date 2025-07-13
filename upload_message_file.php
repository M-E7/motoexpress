<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$livraison_id = $_POST['livraison_id'] ?? null;

if (!$livraison_id || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    // Vérifier que la livraison appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM livraisons WHERE id = ? AND user_id = ?");
    $stmt->execute([$livraison_id, $_SESSION['user_id']]);
    $livraison = $stmt->fetch();
    
    if (!$livraison) {
        echo json_encode(['success' => false, 'message' => 'Livraison non trouvée']);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // Vérifier le type de fichier
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé']);
        exit;
    }
    
    // Vérifier la taille (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 5MB)']);
        exit;
    }
    
    // Créer le dossier de destination
    $upload_dir = 'uploads/messages/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'msg_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Insérer le message avec la photo
        $stmt = $pdo->prepare("
            INSERT INTO messages (livraison_id, expediteur_id, type_expediteur, contenu, type_message, fichier, date_envoi, lu) 
            VALUES (?, ?, 'user', 'Photo envoyée', 'photo', ?, NOW(), 0)
        ");
        $stmt->execute([$livraison_id, $_SESSION['user_id'], $filepath]);
        
        echo json_encode([
            'success' => true,
            'file_path' => $filepath,
            'message' => 'Fichier uploadé avec succès'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?> 