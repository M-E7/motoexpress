<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['moto_taxi_id'])) {
    header('Location: moto_taxi_login.php');
    exit;
}
$moto_taxi_id = $_SESSION['moto_taxi_id'];

// Récupérer les informations complètes du moto-taxi
$stmt = $pdo->prepare("SELECT 
    id, nom, prenom, email, telephone, solde, date_inscription, 
    photo_profil, statut, numero_permis, numero_immatriculation,
    marque_moto, modele_moto, annee_moto, couleur_moto,
    points_fidelite, latitude, longitude, derniere_position,
    note_moyenne, nombre_livraisons, derniere_connexion
    FROM moto_taxis WHERE id = ?");
$stmt->execute([$moto_taxi_id]);
$moto_taxi = $stmt->fetch();

if (!$moto_taxi) {
    header('Location: moto_taxi_login.php');
    exit;
}

// Photo de profil avec fallback
$photo_profil = $moto_taxi['photo_profil']
    ? $moto_taxi['photo_profil'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($moto_taxi['prenom'].' '.$moto_taxi['nom']) . '&background=2563eb&color=fff&size=64';

// Calculer les vraies statistiques en temps réel
try {
// Nombre de livraisons effectuées (terminées) - VRAIES VALEURS
$stmt = $pdo->prepare("SELECT COUNT(*) as nombre_livraisons FROM livraisons WHERE moto_taxi_id = ? AND statut = 'terminee'");
$stmt->execute([$moto_taxi_id]);
    $nombre_livraisons = $stmt->fetchColumn() ?: 0;

// Points fidélité calculés (1 point par livraison terminée)
$points_fidelite = $nombre_livraisons;

    // Note moyenne calculée à partir des évaluations réelles (comme dans profil.php)
    $stmt = $pdo->prepare("SELECT AVG(CASE WHEN e.id IS NOT NULL THEN (e.ponctualite + e.etat_colis + e.politesse) / 3 END) as note_moyenne 
        FROM livraisons l
        LEFT JOIN evaluations e ON l.id = e.livraison_id
    WHERE l.moto_taxi_id = ?");
$stmt->execute([$moto_taxi_id]);
    $note_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $note_moyenne = $note_result['note_moyenne'] ? round($note_result['note_moyenne'], 1) : 5.0;

    // Statistiques détaillées des livraisons - VRAIES VALEURS
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_livraisons,
        COUNT(CASE WHEN statut = 'terminee' THEN 1 END) as livraisons_terminees,
        COUNT(CASE WHEN statut = 'en_cours' THEN 1 END) as livraisons_en_cours,
        COUNT(CASE WHEN statut = 'en_attente' THEN 1 END) as livraisons_en_attente,
        COUNT(CASE WHEN statut = 'annulee' THEN 1 END) as livraisons_annulees,
        AVG(CASE WHEN statut = 'terminee' THEN montant ELSE NULL END) as montant_moyen_livraison,
        SUM(CASE WHEN statut = 'terminee' THEN montant ELSE 0 END) as total_montant_livraisons,
        SUM(CASE WHEN statut = 'terminee' THEN distance_km ELSE 0 END) as distance_totale,
        MAX(date_creation) as derniere_livraison
        FROM livraisons WHERE moto_taxi_id = ?");
    $stmt->execute([$moto_taxi_id]);
    $stats_livraisons = $stmt->fetch();

    // Valeurs par défaut si aucune donnée
    if (!$stats_livraisons) {
        $stats_livraisons = [
            'total_livraisons' => 0,
            'livraisons_terminees' => 0,
            'livraisons_en_cours' => 0,
            'livraisons_en_attente' => 0,
            'livraisons_annulees' => 0,
            'montant_moyen_livraison' => 0,
            'total_montant_livraisons' => 0,
            'distance_totale' => 0,
            'derniere_livraison' => null
        ];
    }

    // Calculer le taux de réussite
    $taux_reussite = $stats_livraisons['total_livraisons'] > 0 
        ? round((($stats_livraisons['livraisons_terminees'] - $stats_livraisons['livraisons_annulees']) / $stats_livraisons['total_livraisons']) * 100, 0)
        : 0;

    // Statistiques détaillées des transactions
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN type='gain' THEN montant ELSE 0 END) as total_gains,
        SUM(CASE WHEN type='retrait' THEN montant ELSE 0 END) as total_retraits,
        SUM(CASE WHEN type='bonus' THEN montant ELSE 0 END) as total_bonus,
        SUM(CASE WHEN type='commission' THEN montant ELSE 0 END) as total_commissions,
        SUM(CASE WHEN type='gain' AND MONTH(date_transaction)=MONTH(NOW()) AND YEAR(date_transaction)=YEAR(NOW()) THEN montant ELSE 0 END) as gains_mois,
        SUM(CASE WHEN type='gain' AND MONTH(date_transaction)=MONTH(NOW())-1 AND YEAR(date_transaction)=YEAR(NOW()) THEN montant ELSE 0 END) as gains_mois_precedent,
        COUNT(CASE WHEN type='gain' THEN 1 END) as nb_gains,
        COUNT(CASE WHEN type='retrait' THEN 1 END) as nb_retraits,
        COUNT(CASE WHEN type='bonus' THEN 1 END) as nb_bonus,
        COUNT(CASE WHEN type='commission' THEN 1 END) as nb_commissions,
        MAX(date_transaction) as derniere_transaction
        FROM transactions_moto_taxi WHERE moto_taxi_id = ?");
    $stmt->execute([$moto_taxi_id]);
    $stats = $stmt->fetch();

    // Valeurs par défaut si aucune donnée
    if (!$stats) {
        $stats = [
            'total_gains' => 0,
            'total_retraits' => 0,
            'total_bonus' => 0,
            'total_commissions' => 0,
            'gains_mois' => 0,
            'gains_mois_precedent' => 0,
            'nb_gains' => 0,
            'nb_retraits' => 0,
            'nb_bonus' => 0,
            'nb_commissions' => 0,
            'derniere_transaction' => null
        ];
    }

    // Calculer le solde total et disponible - VRAIES VALEURS
    $stmt = $pdo->prepare("SELECT SUM(montant) as retraits_en_attente FROM transactions_moto_taxi WHERE moto_taxi_id = ? AND type = 'retrait' AND statut = 'en_attente'");
    $stmt->execute([$moto_taxi_id]);
    $retraits_en_attente = $stmt->fetchColumn() ?: 0;

    // Calculer le solde total réel (somme de tous les gains moins tous les retraits)
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN type IN ('gain', 'bonus', 'commission') THEN montant ELSE 0 END) as total_gains_reels,
        SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END) as total_retraits_reels
        FROM transactions_moto_taxi WHERE moto_taxi_id = ? AND statut = 'terminee'");
    $stmt->execute([$moto_taxi_id]);
    $solde_reel = $stmt->fetch();
    
    $solde_total_reel = ($solde_reel['total_gains_reels'] ?? 0) - ($solde_reel['total_retraits_reels'] ?? 0);
    $solde_disponible = $moto_taxi['solde'] - $retraits_en_attente;
    
    // Calculer les gains en attente (livraisons terminées mais pas encore payées)
    $stmt = $pdo->prepare("SELECT SUM(montant) as gains_en_attente FROM livraisons WHERE moto_taxi_id = ? AND statut = 'terminee' AND id NOT IN (SELECT livraison_id FROM transactions_moto_taxi WHERE moto_taxi_id = ? AND type = 'gain')");
    $stmt->execute([$moto_taxi_id, $moto_taxi_id]);
    $gains_en_attente = $stmt->fetchColumn() ?: 0;

    // Statistiques de performance - VRAIES VALEURS
    $stmt = $pdo->prepare("SELECT 
        COUNT(DISTINCT DATE(l.date_creation)) as jours_actifs,
        COUNT(CASE WHEN l.date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as livraisons_semaine,
        COUNT(CASE WHEN l.date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as livraisons_mois
        FROM livraisons l 
        WHERE l.moto_taxi_id = ? AND l.statut = 'terminee'");
    $stmt->execute([$moto_taxi_id]);
    $stats_performance = $stmt->fetch();

    // Valeurs par défaut si aucune donnée
    if (!$stats_performance) {
        $stats_performance = [
            'jours_actifs' => 0,
            'livraisons_semaine' => 0,
            'livraisons_mois' => 0
        ];
    }

    // Calculer le temps moyen de livraison (estimation basée sur la distance) - VRAIES VALEURS
    $stmt = $pdo->prepare("SELECT 
        AVG(distance_km) as distance_moyenne
        FROM livraisons l 
        WHERE l.moto_taxi_id = ? AND l.statut = 'terminee'");
    $stmt->execute([$moto_taxi_id]);
    $distance_moyenne = $stmt->fetchColumn() ?: 0;

    // Estimation du temps moyen (25 km/h en moyenne)
    $temps_moyen_livraison = $distance_moyenne > 0 ? round(($distance_moyenne / 25) * 60) : 0;

} catch (PDOException $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $nombre_livraisons = 0;
    $points_fidelite = 0;
    $note_moyenne = 5.0;
    $taux_reussite = 0;
    $solde_disponible = $moto_taxi['solde'];
    $solde_total_reel = $moto_taxi['solde'];
    $gains_en_attente = 0;
    $temps_moyen_livraison = 0;
    
    $stats_livraisons = [
        'total_livraisons' => 0,
        'livraisons_terminees' => 0,
        'livraisons_en_cours' => 0,
        'livraisons_en_attente' => 0,
        'livraisons_annulees' => 0,
        'montant_moyen_livraison' => 0,
        'total_montant_livraisons' => 0,
        'distance_totale' => 0,
        'derniere_livraison' => null
    ];
    
    $stats = [
        'total_gains' => 0,
        'total_retraits' => 0,
        'total_bonus' => 0,
        'total_commissions' => 0,
        'gains_mois' => 0,
        'gains_mois_precedent' => 0,
        'nb_gains' => 0,
        'nb_retraits' => 0,
        'nb_bonus' => 0,
        'nb_commissions' => 0,
        'derniere_transaction' => null
    ];
    
    $stats_performance = [
        'jours_actifs' => 0,
        'livraisons_semaine' => 0,
        'livraisons_mois' => 0
    ];
}

// Récupérer les documents du moto-taxi
try {
    $stmt = $pdo->prepare("SELECT * FROM documents_moto_taxi WHERE moto_taxi_id = ? ORDER BY type_document, date_upload DESC");
    $stmt->execute([$moto_taxi_id]);
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Récupérer l'historique des connexions
try {
    $stmt = $pdo->prepare("SELECT * FROM historique_connexions WHERE user_id = ? AND type_user = 'moto_taxi' ORDER BY date_connexion DESC LIMIT 10");
    $stmt->execute([$moto_taxi_id]);
    $historique_connexions = $stmt->fetchAll();
} catch (PDOException $e) {
    $historique_connexions = [];
}

// Filtres et transactions
try {
$type = $_GET['type'] ?? 'tous';
$types = [
    'tous' => 'Tous',
    'gain' => 'Gains',
    'retrait' => 'Retraits',
    'commission' => 'Commissions',
    'bonus' => 'Bonus'
];
$where = "moto_taxi_id = ?";
$params = [$moto_taxi_id];
if ($type !== 'tous' && in_array($type, array_keys($types))) {
    $where .= " AND type = ?";
    $params[] = $type;
}

// Historique des transactions
$stmt = $pdo->prepare("SELECT * FROM transactions_moto_taxi WHERE $where ORDER BY date_transaction DESC LIMIT 100");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Récupérer les dernières transactions avec plus de détails
$stmt = $pdo->prepare("SELECT t.*, l.adresse_depart, l.adresse_arrivee 
    FROM transactions_moto_taxi t 
    LEFT JOIN livraisons l ON t.livraison_id = l.id 
    WHERE t.moto_taxi_id = ? 
    ORDER BY t.date_transaction DESC 
    LIMIT 10");
$stmt->execute([$moto_taxi_id]);
$recentes_transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // En cas d'erreur, utiliser des tableaux vides
    $transactions = [];
    $recentes_transactions = [];
    $type = 'tous';
    $types = [
        'tous' => 'Tous',
        'gain' => 'Gains',
        'retrait' => 'Retraits',
        'commission' => 'Commissions',
        'bonus' => 'Bonus'
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gains Moto-Taxi - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; color: #1e293b; margin: 0; }
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07); }
        .navbar .logo { display: flex; align-items: center; font-size: 1.4rem; font-weight: bold; color: #2563eb; }
        .navbar .logo i { margin-right: 0.5rem; }
        .navbar .nav-links { display: flex; align-items: center; gap: 2rem; }
        .navbar .nav-link { color: #64748b; text-decoration: none; display: flex; flex-direction: column; align-items: center; font-size: 1rem; transition: color 0.2s; }
        .navbar .nav-link.active, .navbar .nav-link:hover { color: #2563eb; }
        .navbar .nav-link i { font-size: 1.2rem; margin-bottom: 0.2rem; }
        .navbar .nav-link span { font-size: 0.8rem; }
        .navbar .logout { color: #dc2626; margin-left: 1.5rem; text-decoration: underline; font-size: 1rem; }
        .main-content { max-width: 1200px; margin: 110px auto 0 auto; padding: 2rem 1rem; }
        
        /* Header avec infos moto-taxi */
        .header-info { background: #fff; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #e2e8f0; }
        .header-info h1 { margin: 0 0 1rem 0; color: #2563eb; font-size: 1.8rem; }
        .header-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .header-stat { text-align: center; padding: 1rem; background: #f8fafc; border-radius: 8px; }
        .header-stat-value { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
        .header-stat-label { color: #64748b; font-size: 0.9rem; margin-top: 0.3rem; }
        
        /* Solde box amélioré */
        .solde-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .solde-box { background: #2563eb; color: white; border-radius: 16px; padding: 2rem; text-align: center; font-size: 2rem; font-weight: 700; box-shadow: 0 2px 8px #e2e8f0; }
        .solde-disponible { background: #16a34a; color: white; border-radius: 16px; padding: 2rem; text-align: center; font-size: 2rem; font-weight: 700; box-shadow: 0 2px 8px #e2e8f0; }
        .solde-label { font-size: 1rem; margin-bottom: 0.5rem; opacity: 0.9; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.5rem; text-align: center; }
        .stat-label { color: #64748b; font-size: 1rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.4rem; font-weight: 600; color: #2563eb; }
        .stat-subtitle { color: #94a3b8; font-size: 0.8rem; margin-top: 0.3rem; }
        
        .filters { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.5rem 1.2rem; border: none; border-radius: 20px; background: #f3f4f6; color: #374151; font-weight: 500; cursor: pointer; transition: background 0.2s, color 0.2s; text-decoration: none; }
        .filter-btn.active, .filter-btn:hover { background: #2563eb; color: white; }
        
        .transactions-list { display: flex; flex-direction: column; gap: 1rem; }
        .transaction-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.5rem; }
        .transaction-header { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 0.5rem; }
        .transaction-type { font-size: 1.1rem; font-weight: 600; text-transform: capitalize; }
        .type-gain { color: #16a34a; }
        .type-retrait { color: #dc2626; }
        .type-commission { color: #f59e0b; }
        .type-bonus { color: #2563eb; }
        .transaction-montant { font-size: 1.3rem; font-weight: 700; margin-left: auto; }
        .transaction-date { color: #64748b; font-size: 0.95rem; }
        .transaction-details { margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #e2e8f0; }
        .transaction-desc { color: #64748b; font-size: 0.9rem; }
        .transaction-status { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.8rem; font-weight: 500; margin-left: 0.5rem; }
        .status-terminee { background: #dcfce7; color: #166534; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-annulee { background: #fee2e2; color: #991b1b; }
        
        .btn { padding: 0.7rem 1.5rem; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-secondary:hover { background: #e5e7eb; }
        
        .retrait-form { background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #e2e8f0; }
        .retrait-form h3 { margin: 0 0 1rem 0; color: #2563eb; }
        .retrait-form-content { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .retrait-form input[type=number] { padding: 0.7rem; border-radius: 8px; border: 1px solid #d1d5db; font-size: 1rem; width: 160px; }
        .retrait-form select { padding: 0.7rem; border-radius: 8px; border: 1px solid #d1d5db; font-size: 1rem; }
        .retrait-form input[type=text] { padding: 0.7rem; border-radius: 8px; border: 1px solid #d1d5db; font-size: 1rem; width: 220px; }
        
        .msg-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        
        @media (max-width: 768px) {
            .solde-container { grid-template-columns: 1fr; }
            .retrait-form-content { flex-direction: column; align-items: stretch; }
            .retrait-form input, .retrait-form select { width: 100%; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo"><i class="fas fa-motorcycle"></i> MotoExpress</div>
    <div class="nav-links">
        <a href="moto_taxi_home.php" class="nav-link"><i class="fas fa-home"></i><span>Accueil</span></a>
        <a href="moto_taxi_livraisons.php" class="nav-link"><i class="fas fa-route"></i><span>Livraisons</span></a>
        <a href="moto_taxi_messages.php" class="nav-link"><i class="fas fa-comments"></i><span>Messages</span></a>
        <a href="moto_taxi_gains.php" class="nav-link active"><i class="fas fa-wallet"></i><span>Gains</span></a>
        <a href="moto_taxi_profil.php" class="nav-link"><i class="fas fa-user"></i><span>Profil</span></a>
        <a href="moto_taxi_logout.php" class="logout">Déconnexion</a>
    </div>
</nav>

<div class="main-content">
    <!-- Header avec infos moto-taxi -->
    <div class="header-info">
        <div style="display: flex; align-items: flex-start; gap: 1.5rem; margin-bottom: 1.5rem;">
            <img src="<?php echo $photo_profil; ?>" alt="Photo de profil" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #2563eb;">
            <div style="flex: 1;">
                <h1 style="margin: 0 0 0.5rem 0; color: #2563eb; font-size: 1.8rem;">
                    <i class="fas fa-motorcycle"></i> <?php echo htmlspecialchars($moto_taxi['prenom'] . ' ' . $moto_taxi['nom']); ?>
                </h1>
                <div style="color: #64748b; font-size: 1rem; margin-bottom: 0.5rem;">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($moto_taxi['telephone']); ?> | 
                    <?php if ($moto_taxi['email']): ?>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($moto_taxi['email']); ?>
                    <?php endif; ?>
                </div>
                <?php if ($moto_taxi['statut']): ?>
                    <div style="color: #64748b; font-size: 0.9rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-circle" style="color: <?php echo $moto_taxi['statut'] === 'actif' ? '#16a34a' : '#dc2626'; ?>;"></i> 
                        Statut: <?php echo ucfirst($moto_taxi['statut']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Informations professionnelles -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; margin-top: 1rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <?php if ($moto_taxi['numero_permis']): ?>
                        <div style="font-size: 0.9rem;">
                            <i class="fas fa-id-card" style="color: #2563eb;"></i> 
                            <strong>Permis:</strong> <?php echo htmlspecialchars($moto_taxi['numero_permis']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($moto_taxi['numero_immatriculation']): ?>
                        <div style="font-size: 0.9rem;">
                            <i class="fas fa-car" style="color: #2563eb;"></i> 
                            <strong>Immat.:</strong> <?php echo htmlspecialchars($moto_taxi['numero_immatriculation']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($moto_taxi['marque_moto'] && $moto_taxi['modele_moto']): ?>
                        <div style="font-size: 0.9rem;">
                            <i class="fas fa-motorcycle" style="color: #2563eb;"></i> 
                            <strong>Moto:</strong> <?php echo htmlspecialchars($moto_taxi['marque_moto'] . ' ' . $moto_taxi['modele_moto']); ?>
                            <?php if ($moto_taxi['annee_moto']): ?>
                                (<?php echo $moto_taxi['annee_moto']; ?>)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($moto_taxi['couleur_moto']): ?>
                        <div style="font-size: 0.9rem;">
                            <i class="fas fa-palette" style="color: #2563eb;"></i> 
                            <strong>Couleur:</strong> <?php echo htmlspecialchars($moto_taxi['couleur_moto']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($moto_taxi['derniere_connexion']): ?>
                        <div style="font-size: 0.9rem;">
                            <i class="fas fa-clock" style="color: #2563eb;"></i> 
                            <strong>Dernière connexion:</strong> <?php echo date('d/m/Y H:i', strtotime($moto_taxi['derniere_connexion'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($moto_taxi['latitude'] && $moto_taxi['longitude']): ?>
                        <div style="font-size: 0.9rem;">
                            <i class="fas fa-map-marker-alt" style="color: #2563eb;"></i> 
                            <strong>Position:</strong> <?php echo number_format($moto_taxi['latitude'], 4); ?>, <?php echo number_format($moto_taxi['longitude'], 4); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="header-stats">
            <div class="header-stat">
                <div class="header-stat-value"><?php echo number_format($stats_livraisons['total_livraisons'] ?? 0); ?></div>
                <div class="header-stat-label">Total livraisons</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?php echo number_format($stats_livraisons['livraisons_terminees'] ?? 0); ?></div>
                <div class="header-stat-label">Livraisons terminées</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?php echo number_format($note_moyenne ?? 5.0, 1); ?>/5</div>
                <div class="header-stat-label">Note moyenne</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?php echo $taux_reussite; ?>%</div>
                <div class="header-stat-label">Taux de réussite</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?php echo number_format($stats_livraisons['distance_totale'] ?? 0, 1); ?> km</div>
                <div class="header-stat-label">Distance totale</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?php echo number_format($moto_taxi['points_fidelite'] ?? 0); ?></div>
                <div class="header-stat-label">Points fidélité</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?php echo date('d/m/Y', strtotime($moto_taxi['date_inscription'])); ?></div>
                <div class="header-stat-label">Membre depuis</div>
            </div>
            <div class="header-stat">
                <div class="header-stat-value"><?php echo $moto_taxi['nombre_livraisons'] ?? 0; ?></div>
                <div class="header-stat-label">Livraisons (DB)</div>
            </div>
        </div>
    </div>

    <!-- Solde -->
    <div class="solde-container">
        <div class="solde-box">
            <div class="solde-label">Solde total (DB)</div>
            <i class="fas fa-wallet"></i> <?php echo number_format($moto_taxi['solde'], 0, ',', ' '); ?> FCFA
        </div>
        <div class="solde-disponible">
            <div class="solde-label">Solde disponible</div>
            <i class="fas fa-check-circle"></i> <?php echo number_format($solde_disponible, 0, ',', ' '); ?> FCFA
        </div>
        <div style="background: #f59e0b; color: white; border-radius: 16px; padding: 2rem; text-align: center; font-size: 2rem; font-weight: 700; box-shadow: 0 2px 8px #e2e8f0;">
            <div class="solde-label">Solde total réel</div>
            <i class="fas fa-calculator"></i> <?php echo number_format($solde_total_reel, 0, ',', ' '); ?> FCFA
        </div>
        <div style="background: #8b5cf6; color: white; border-radius: 16px; padding: 2rem; text-align: center; font-size: 2rem; font-weight: 700; box-shadow: 0 2px 8px #e2e8f0;">
            <div class="solde-label">Gains en attente</div>
            <i class="fas fa-clock"></i> <?php echo number_format($gains_en_attente, 0, ',', ' '); ?> FCFA
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Gains du mois</div>
            <div class="stat-value"><?php echo number_format($stats['gains_mois'] ?? 0, 0, ',', ' '); ?> FCFA</div>
            <div class="stat-subtitle"><?php echo number_format($stats['gains_mois_precedent'] ?? 0, 0, ',', ' '); ?> FCFA le mois dernier</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total gains</div>
            <div class="stat-value"><?php echo number_format($stats['total_gains'] ?? 0, 0, ',', ' '); ?> FCFA</div>
            <div class="stat-subtitle"><?php echo $stats['nb_gains'] ?? 0; ?> transactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total retraits</div>
            <div class="stat-value"><?php echo number_format($stats['total_retraits'] ?? 0, 0, ',', ' '); ?> FCFA</div>
            <div class="stat-subtitle"><?php echo $stats['nb_retraits'] ?? 0; ?> retraits</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Bonus reçus</div>
            <div class="stat-value"><?php echo number_format($stats['total_bonus'] ?? 0, 0, ',', ' '); ?> FCFA</div>
            <div class="stat-subtitle"><?php echo $stats['nb_bonus'] ?? 0; ?> bonus</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Commissions</div>
            <div class="stat-value"><?php echo number_format($stats['total_commissions'] ?? 0, 0, ',', ' '); ?> FCFA</div>
            <div class="stat-subtitle"><?php echo $stats['nb_commissions'] ?? 0; ?> commissions</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Livraisons terminées</div>
            <div class="stat-value"><?php echo number_format($stats_livraisons['livraisons_terminees'] ?? 0); ?></div>
            <div class="stat-subtitle">Montant moyen: <?php echo number_format($stats_livraisons['montant_moyen_livraison'] ?? 0, 0, ',', ' '); ?> FCFA</div>
        </div>
    </div>

    <!-- Statistiques détaillées des livraisons -->
    <div style="background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #e2e8f0;">
        <h3 style="margin: 0 0 1rem 0; color: #2563eb; font-size: 1.3rem;">
            <i class="fas fa-chart-line"></i> Statistiques détaillées des livraisons
        </h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Livraisons terminées (réelles)</div>
                <div class="stat-value" style="color: #16a34a;"><?php echo number_format($stats_livraisons['livraisons_terminees'] ?? 0); ?></div>
                <div class="stat-subtitle">Sur <?php echo number_format($stats_livraisons['total_livraisons'] ?? 0); ?> total</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Distance totale parcourue</div>
                <div class="stat-value" style="color: #2563eb;"><?php echo number_format($stats_livraisons['distance_totale'] ?? 0, 1); ?> km</div>
                <div class="stat-subtitle">Distance moyenne: <?php echo number_format($distance_moyenne ?? 0, 1); ?> km</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Montant total des livraisons</div>
                <div class="stat-value" style="color: #f59e0b;"><?php echo number_format($stats_livraisons['total_montant_livraisons'] ?? 0, 0, ',', ' '); ?> FCFA</div>
                <div class="stat-subtitle">Montant moyen: <?php echo number_format($stats_livraisons['montant_moyen_livraison'] ?? 0, 0, ',', ' '); ?> FCFA</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Livraisons en cours</div>
                <div class="stat-value" style="color: #8b5cf6;"><?php echo number_format($stats_livraisons['livraisons_en_cours'] ?? 0); ?></div>
                <div class="stat-subtitle">En cours de traitement</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Livraisons en attente</div>
                <div class="stat-value" style="color: #f59e0b;"><?php echo number_format($stats_livraisons['livraisons_en_attente'] ?? 0); ?></div>
                <div class="stat-subtitle">En attente d'acceptation</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Livraisons annulées</div>
                <div class="stat-value" style="color: #dc2626;"><?php echo number_format($stats_livraisons['livraisons_annulees'] ?? 0); ?></div>
                <div class="stat-subtitle">Livraisons annulées</div>
            </div>
        </div>
    </div>

    <!-- Statistiques de performance -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-label">Temps moyen de livraison</div>
            <div class="stat-value"><?php echo $temps_moyen_livraison; ?> min</div>
            <div class="stat-subtitle">Estimation basée sur la distance</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Jours actifs</div>
            <div class="stat-value"><?php echo $stats_performance['jours_actifs'] ?? 0; ?> jours</div>
            <div class="stat-subtitle">Jours avec au moins une livraison</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Livraisons cette semaine</div>
            <div class="stat-value"><?php echo $stats_performance['livraisons_semaine'] ?? 0; ?></div>
            <div class="stat-subtitle">7 derniers jours</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Livraisons ce mois</div>
            <div class="stat-value"><?php echo $stats_performance['livraisons_mois'] ?? 0; ?></div>
            <div class="stat-subtitle">30 derniers jours</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Livraisons en cours</div>
            <div class="stat-value"><?php echo $stats_livraisons['livraisons_en_cours'] ?? 0; ?></div>
            <div class="stat-subtitle">Livraisons actives</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Livraisons annulées</div>
            <div class="stat-value"><?php echo $stats_livraisons['livraisons_annulees'] ?? 0; ?></div>
            <div class="stat-subtitle">Livraisons annulées</div>
        </div>
    </div>

    <!-- Documents du moto-taxi -->
    <?php if (!empty($documents)): ?>
    <div style="background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #e2e8f0;">
        <h3 style="margin: 0 0 1rem 0; color: #2563eb; font-size: 1.3rem;">
            <i class="fas fa-file-alt"></i> Documents professionnels
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <?php foreach ($documents as $doc): ?>
                <div style="padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-<?php 
                            echo $doc['type_document'] === 'permis_conduire' ? 'id-card' : 
                                ($doc['type_document'] === 'carte_grise' ? 'car' : 
                                ($doc['type_document'] === 'assurance' ? 'shield-alt' : 
                                ($doc['type_document'] === 'carte_identite' ? 'address-card' : 'file'))); 
                        ?>" style="color: #2563eb;"></i>
                        <strong style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $doc['type_document']); ?></strong>
                    </div>
                    <div style="font-size: 0.9rem; color: #64748b; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($doc['nom_fichier']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem;">
                        <span style="padding: 0.2rem 0.5rem; border-radius: 12px; font-weight: 500; 
                            background: <?php echo $doc['statut'] === 'approuve' ? '#dcfce7' : 
                                ($doc['statut'] === 'rejete' ? '#fee2e2' : '#fef3c7'); ?>; 
                            color: <?php echo $doc['statut'] === 'approuve' ? '#166534' : 
                                ($doc['statut'] === 'rejete' ? '#991b1b' : '#92400e'); ?>;">
                            <?php echo ucfirst($doc['statut']); ?>
                        </span>
                        <span style="color: #64748b;">
                            <?php echo date('d/m/Y', strtotime($doc['date_upload'])); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Historique des connexions -->
    <?php if (!empty($historique_connexions)): ?>
    <div style="background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px #e2e8f0;">
        <h3 style="margin: 0 0 1rem 0; color: #2563eb; font-size: 1.3rem;">
            <i class="fas fa-history"></i> Historique des connexions
        </h3>
        <div style="max-height: 300px; overflow-y: auto;">
            <?php foreach ($historique_connexions as $connexion): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.8rem; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-<?php echo $connexion['action'] === 'connexion' ? 'sign-in-alt' : 'sign-out-alt'; ?>" 
                           style="color: <?php echo $connexion['action'] === 'connexion' ? '#16a34a' : '#dc2626'; ?>;"></i>
                        <span style="font-weight: 500;">
                            <?php echo $connexion['action'] === 'connexion' ? 'Connexion' : 'Déconnexion'; ?>
                        </span>
                    </div>
                    <div style="color: #64748b; font-size: 0.8rem;">
                        <?php echo date('d/m/Y H:i', strtotime($connexion['date_connexion'])); ?>
                        <?php if ($connexion['ip_address']): ?>
                            <span style="margin-left: 0.5rem;">(<?php echo htmlspecialchars($connexion['ip_address']); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formulaire de retrait -->
    <div class="retrait-form">
        <h3><i class="fas fa-money-bill-wave"></i> Demande de retrait</h3>
        <form method="post" action="demande_retrait.php" onsubmit="return confirm('Confirmer la demande de retrait ?');">
            <div class="retrait-form-content">
                <input type="number" name="montant" min="1000" max="<?php echo intval($solde_disponible); ?>" placeholder="Montant à retirer" required>
                <select name="moyen_paiement" required>
                    <option value="">Moyen de paiement</option>
                    <option value="Orange Money">Orange Money</option>
                    <option value="MTN Mobile Money">MTN Mobile Money</option>
                    <option value="Compte bancaire">Compte bancaire</option>
                </select>
                <input type="text" name="reference" placeholder="N° Mobile Money ou IBAN" required>
                <button type="submit" class="btn btn-primary"><i class="fas fa-money-bill-wave"></i> Demander un retrait</button>
            </div>
        </form>
    </div>

    <!-- Filtres -->
    <div class="filters">
        <?php foreach ($types as $key => $label): ?>
            <a href="?type=<?php echo $key; ?>" class="filter-btn<?php if ($type === $key) echo ' active'; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Liste des transactions -->
    <div class="transactions-list">
        <?php if (empty($transactions)): ?>
            <div style="text-align:center; color:#64748b; padding:2rem; background:#fff; border-radius:12px;">
                <i class="fas fa-inbox" style="font-size:3rem; margin-bottom:1rem; opacity:0.5;"></i>
                <h3>Aucune transaction trouvée</h3>
                <p>Vous n'avez pas encore de transactions dans cette catégorie.</p>
            </div>
        <?php else: ?>
            <?php foreach ($transactions as $tr): ?>
                <div class="transaction-card">
                    <div class="transaction-header">
                        <div class="transaction-type type-<?php echo $tr['type']; ?>">
                            <i class="fas fa-<?php echo $tr['type']==='gain'?'plus-circle':($tr['type']==='retrait'?'minus-circle':($tr['type']==='bonus'?'gift':'percent')); ?>"></i>
                            <?php echo ucfirst($tr['type']); ?>
                        </div>
                        <div class="transaction-montant"><?php echo ($tr['type']==='retrait'?'-':'').number_format($tr['montant'], 0, ',', ' '); ?> FCFA</div>
                        <div class="transaction-date">
                            <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($tr['date_transaction'])); ?>
                            <?php if(isset($tr['statut'])): ?>
                                <span class="transaction-status status-<?php echo $tr['statut']; ?>"><?php echo ucfirst($tr['statut']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if($tr['description'] || $tr['adresse_depart']): ?>
                        <div class="transaction-details">
                            <?php if($tr['description']): ?>
                                <div class="transaction-desc"><?php echo htmlspecialchars($tr['description']); ?></div>
                            <?php endif; ?>
                            <?php if($tr['adresse_depart']): ?>
                                <div class="transaction-desc">
                                    <i class="fas fa-route"></i> 
                                    <?php echo htmlspecialchars($tr['adresse_depart']); ?> → <?php echo htmlspecialchars($tr['adresse_arrivee']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html> 