<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si c'est une requête GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $livraison_id = $_GET['livraison_id'] ?? null;
    
    if (!$livraison_id) {
        throw new Exception('ID de livraison requis');
    }
    
    // Vérifier que la livraison appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM livraisons WHERE id = ? AND user_id = ?");
    $stmt->execute([$livraison_id, $user_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Livraison non trouvée ou accès non autorisé');
    }
    
    // Récupérer les documents de la livraison
    $stmt = $pdo->prepare("
        SELECT id, nom_fichier, nom_original, type_fichier, taille_fichier, date_upload
        FROM documents_livraison 
        WHERE livraison_id = ? 
        ORDER BY date_upload ASC
    ");
    $stmt->execute([$livraison_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données pour l'affichage
    foreach ($documents as &$doc) {
        $doc['taille_formatee'] = formatFileSize($doc['taille_fichier']);
        $doc['date_formatee'] = date('d/m/Y H:i', strtotime($doc['date_upload']));
        $doc['icone'] = getFileIcon($doc['type_fichier']);
    }
    
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Formate la taille d'un fichier en format lisible
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Retourne l'icône appropriée selon le type de fichier
 */
function getFileIcon($mime_type) {
    switch ($mime_type) {
        case 'application/pdf':
            return 'fas fa-file-pdf';
        case 'image/jpeg':
        case 'image/png':
        case 'image/gif':
            return 'fas fa-file-image';
        case 'application/msword':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            return 'fas fa-file-word';
        case 'application/vnd.ms-excel':
        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            return 'fas fa-file-excel';
        default:
            return 'fas fa-file';
    }
}
?> 