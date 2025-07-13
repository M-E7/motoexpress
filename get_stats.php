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
    
    // Statistiques principales des livraisons (toutes les livraisons, pas seulement terminées)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_livraisons,
            SUM(distance_km) as total_distance,
            SUM(montant) as total_depense,
            AVG(montant) as prix_moyen,
            COUNT(CASE WHEN statut = 'livree' THEN 1 END) as livraisons_terminees,
            COUNT(CASE WHEN statut = 'annulee' THEN 1 END) as livraisons_annulees,
            COUNT(CASE WHEN statut = 'en_cours' THEN 1 END) as livraisons_en_cours
        FROM livraisons 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $livraisons_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Note moyenne des évaluations (calculée comme dans profil.php)
    $stmt = $pdo->prepare("
        SELECT AVG(CASE WHEN e.id IS NOT NULL THEN (e.ponctualite + e.etat_colis + e.politesse) / 3 END) as note_moyenne
        FROM livraisons l
        LEFT JOIN evaluations e ON l.id = e.livraison_id
        WHERE l.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $note_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $note_moyenne = $note_result['note_moyenne'] ? round($note_result['note_moyenne'], 1) : 5.0;
    
    // Points de fidélité
    $stmt = $pdo->prepare("
        SELECT points_fidelite 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $points_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $points_fidelite = $points_result['points_fidelite'] ?? 0;
    
    // Statistiques par type de livraison
    $stmt = $pdo->prepare("
        SELECT 
            type_livraison,
            COUNT(*) as nombre,
            SUM(montant) as total_prix
        FROM livraisons 
        WHERE user_id = ? 
        GROUP BY type_livraison
    ");
    $stmt->execute([$user_id]);
    $stats_par_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques des 30 derniers jours
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as livraisons_30j,
            SUM(montant) as depense_30j,
            SUM(distance_km) as distance_30j
        FROM livraisons 
        WHERE user_id = ? 
        AND date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user_id]);
    $stats_30j = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Préparer la réponse
    $stats = [
        'livraisons' => (int)$livraisons_stats['total_livraisons'],
        'distance' => round($livraisons_stats['total_distance'] ?? 0, 1),
        'depense' => (int)($livraisons_stats['total_depense'] ?? 0),
        'note' => $note_moyenne,
        'points_fidelite' => (int)$points_fidelite,
        'livraisons_terminees' => (int)$livraisons_stats['livraisons_terminees'],
        'livraisons_annulees' => (int)$livraisons_stats['livraisons_annulees'],
        'livraisons_en_cours' => (int)$livraisons_stats['livraisons_en_cours'],
        'prix_moyen' => round($livraisons_stats['prix_moyen'] ?? 0),
        'stats_30j' => [
            'livraisons' => (int)$stats_30j['livraisons_30j'],
            'depense' => (int)($stats_30j['depense_30j'] ?? 0),
            'distance' => round($stats_30j['distance_30j'] ?? 0, 1)
        ],
        'par_type' => $stats_par_type
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 