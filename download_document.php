<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Accès non autorisé';
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $document_id = $_GET['document_id'] ?? null;
    
    if (!$document_id) {
        throw new Exception('ID de document requis');
    }
    
    // Récupérer les informations du document et vérifier les permissions
    $stmt = $pdo->prepare("
        SELECT dl.*, l.user_id as livraison_user_id
        FROM documents_livraison dl
        JOIN livraisons l ON dl.livraison_id = l.id
        WHERE dl.id = ?
    ");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Document non trouvé');
    }
    
    // Vérifier que l'utilisateur a le droit d'accéder à ce document
    if ($document['livraison_user_id'] != $user_id) {
        throw new Exception('Accès non autorisé à ce document');
    }
    
    $file_path = $document['chemin_fichier'];
    
    // Vérifier que le fichier existe
    if (!file_exists($file_path)) {
        throw new Exception('Fichier non trouvé sur le serveur');
    }
    
    // Définir les headers pour le téléchargement
    header('Content-Type: ' . $document['type_fichier']);
    header('Content-Disposition: attachment; filename="' . $document['nom_original'] . '"');
    header('Content-Length: ' . $document['taille_fichier']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Lire et envoyer le fichier
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    http_response_code(400);
    echo 'Erreur: ' . $e->getMessage();
}
?> 