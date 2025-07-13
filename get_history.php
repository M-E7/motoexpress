<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Paramètres de filtrage
    $statut = $_GET['statut'] ?? '';
    $type_livraison = $_GET['type_livraison'] ?? '';
    $date_debut = $_GET['date_debut'] ?? '';
    $date_fin = $_GET['date_fin'] ?? '';
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;
    
    // Construire la requête SQL
    $sql = "
        SELECT l.*, 
               m.nom as mototaxi_nom, 
               m.telephone as mototaxi_telephone,
               m.photo_profil as mototaxi_photo,
               (SELECT COUNT(*) FROM documents_livraison WHERE livraison_id = l.id) as nb_documents
        FROM livraisons l
        LEFT JOIN moto_taxis m ON l.moto_taxi_id = m.id
        WHERE l.user_id = ?
    ";
    
    $params = [$user_id];
    
    // Ajouter les filtres
    if (!empty($statut)) {
        $sql .= " AND l.statut = ?";
        $params[] = $statut;
    }
    
    if (!empty($type_livraison)) {
        $sql .= " AND l.type_livraison = ?";
        $params[] = $type_livraison;
    }
    
    if (!empty($date_debut)) {
        $sql .= " AND DATE(l.date_creation) >= ?";
        $params[] = $date_debut;
    }
    
    if (!empty($date_fin)) {
        $sql .= " AND DATE(l.date_creation) <= ?";
        $params[] = $date_fin;
    }
    
    // Ajouter l'ordre et la pagination
    $sql .= " ORDER BY l.date_creation DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $livraisons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données
    foreach ($livraisons as &$livraison) {
        $livraison['prix_formate'] = number_format($livraison['montant'], 0, ',', ' ') . ' FCFA';
        $livraison['distance_formatee'] = number_format($livraison['distance_km'], 1, ',', ' ') . ' km';
        $livraison['date_formatee'] = date('d/m/Y H:i', strtotime($livraison['date_creation']));
        $livraison['statut_label'] = getStatutLabel($livraison['statut']);
        $livraison['statut_color'] = getStatutColor($livraison['statut']);
        $livraison['type_label'] = getTypeLabel($livraison['type_livraison']);
        
        // Ajouter l'URL de la photo du moto-taxi
        if ($livraison['mototaxi_photo']) {
            $livraison['mototaxi_photo_url'] = 'uploads/moto_taxis/' . $livraison['mototaxi_photo'];
        } else {
            $livraison['mototaxi_photo_url'] = 'assets/images/default-avatar.png';
        }
    }
    
    // Compter le total pour la pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM livraisons l
        WHERE l.user_id = ?
    ";
    
    $count_params = [$user_id];
    
    if (!empty($statut)) {
        $count_sql .= " AND l.statut = ?";
        $count_params[] = $statut;
    }
    
    if (!empty($type_livraison)) {
        $count_sql .= " AND l.type_livraison = ?";
        $count_params[] = $type_livraison;
    }
    
    if (!empty($date_debut)) {
        $count_sql .= " AND DATE(l.date_creation) >= ?";
        $count_params[] = $date_debut;
    }
    
    if (!empty($date_fin)) {
        $count_sql .= " AND DATE(l.date_creation) <= ?";
        $count_params[] = $date_fin;
    }
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $total = $stmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'livraisons' => $livraisons,
        'total' => $total,
        'has_more' => ($offset + $limit) < $total
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Retourne le label du statut
 */
function getStatutLabel($statut) {
    switch ($statut) {
        case 'en_attente':
            return 'En attente';
        case 'acceptee':
            return 'Acceptée';
        case 'en_cours':
            return 'En cours';
        case 'terminee':
            return 'Terminée';
        case 'annulee':
            return 'Annulée';
        default:
            return ucfirst($statut);
    }
}

/**
 * Retourne la couleur du statut
 */
function getStatutColor($statut) {
    switch ($statut) {
        case 'en_attente':
            return 'warning';
        case 'acceptee':
            return 'info';
        case 'en_cours':
            return 'primary';
        case 'terminee':
            return 'success';
        case 'annulee':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Retourne le label du type de livraison
 */
function getTypeLabel($type) {
    switch ($type) {
        case 'livraison':
            return 'Livraison';
        case 'retrait':
            return 'Retrait';
        case 'course':
            return 'Course';
        default:
            return ucfirst($type);
    }
}
?> 