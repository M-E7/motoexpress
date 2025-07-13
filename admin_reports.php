<?php
session_start();
require_once 'db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Période de rapport
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Définir les dates selon la période
switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d');
        break;
    case 'quarter':
        $start_date = date('Y-m-01', strtotime('-3 months'));
        $end_date = date('Y-m-d');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-m-d');
        break;
    case 'custom':
        // Utiliser les dates fournies
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d');
}

// Statistiques générales
$stats = [];

// Utilisateurs
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN date_inscription >= ? THEN 1 END) as nouveaux,
        COUNT(CASE WHEN derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as actifs
    FROM users 
    WHERE statut != 'supprime'
");
$stmt->execute([$start_date]);
$stats['users'] = $stmt->fetch();

// Moto-taxis
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN date_inscription >= ? THEN 1 END) as nouveaux,
        COUNT(CASE WHEN statut = 'actif' THEN 1 END) as actifs,
        COUNT(CASE WHEN statut = 'en_attente' THEN 1 END) as en_attente
    FROM moto_taxis 
    WHERE statut != 'supprime'
");
$stmt->execute([$start_date]);
$stats['drivers'] = $stmt->fetch();

// Livraisons
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN date_creation >= ? THEN 1 END) as nouvelles,
        COUNT(CASE WHEN statut = 'terminee' THEN 1 END) as terminees,
        COUNT(CASE WHEN statut = 'en_cours' THEN 1 END) as en_cours,
        COUNT(CASE WHEN statut = 'annulee' THEN 1 END) as annulees,
        SUM(CASE WHEN statut = 'terminee' THEN montant ELSE 0 END) as revenus,
        AVG(CASE WHEN statut = 'terminee' THEN montant ELSE NULL END) as montant_moyen
    FROM livraisons 
    WHERE date_creation BETWEEN ? AND ?
");
$stmt->execute([$start_date, $start_date, $end_date]);
$stats['deliveries'] = $stmt->fetch();

// Transactions
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(montant) as montant_total,
        COUNT(CASE WHEN type = 'recharge' THEN 1 END) as recharges,
        COUNT(CASE WHEN type = 'paiement' THEN 1 END) as paiements,
        COUNT(CASE WHEN type = 'remboursement' THEN 1 END) as remboursements
    FROM transactions_solde 
    WHERE date_transaction BETWEEN ? AND ? AND statut = 'terminee'
");
$stmt->execute([$start_date, $end_date]);
$stats['transactions'] = $stmt->fetch();

// Données pour graphiques
$chart_data = [];

// Livraisons par jour (7 derniers jours)
$stmt = $pdo->prepare("
    SELECT 
        DATE(date_creation) as date,
        COUNT(*) as total,
        COUNT(CASE WHEN statut = 'terminee' THEN 1 END) as terminees,
        SUM(CASE WHEN statut = 'terminee' THEN montant ELSE 0 END) as revenus
    FROM livraisons 
    WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(date_creation)
    ORDER BY date_creation
");
$stmt->execute();
$chart_data['daily_deliveries'] = $stmt->fetchAll();

// Top 10 chauffeurs
$stmt = $pdo->prepare("
    SELECT 
        mt.nom, mt.prenom,
        COUNT(l.id) as total_livraisons,
        COUNT(CASE WHEN l.statut = 'terminee' THEN 1 END) as livraisons_terminees,
        AVG(CASE WHEN l.statut = 'terminee' THEN l.note ELSE NULL END) as note_moyenne,
        SUM(CASE WHEN l.statut = 'terminee' THEN l.montant ELSE 0 END) as revenus
    FROM moto_taxis mt
    LEFT JOIN livraisons l ON mt.id = l.moto_taxi_id
    WHERE mt.statut = 'actif' AND l.date_creation BETWEEN ? AND ?
    GROUP BY mt.id
    ORDER BY livraisons_terminees DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$chart_data['top_drivers'] = $stmt->fetchAll();

// Top 10 clients
$stmt = $pdo->prepare("
    SELECT 
        u.nom, u.prenom,
        COUNT(l.id) as total_livraisons,
        COUNT(CASE WHEN l.statut = 'terminee' THEN 1 END) as livraisons_terminees,
        SUM(CASE WHEN l.statut = 'terminee' THEN l.montant ELSE 0 END) as montant_total
    FROM users u
    LEFT JOIN livraisons l ON u.id = l.user_id
    WHERE u.statut = 'actif' AND l.date_creation BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY montant_total DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$chart_data['top_clients'] = $stmt->fetchAll();

// Répartition des livraisons par statut
$stmt = $pdo->prepare("
    SELECT 
        statut,
        COUNT(*) as total,
        SUM(montant) as montant_total
    FROM livraisons 
    WHERE date_creation BETWEEN ? AND ?
    GROUP BY statut
    ORDER BY total DESC
");
$stmt->execute([$start_date, $end_date]);
$chart_data['delivery_status'] = $stmt->fetchAll();

// Si aucun statut trouvé, créer des données par défaut
if (empty($chart_data['delivery_status'])) {
    $chart_data['delivery_status'] = [
        ['statut' => 'aucune_donnee', 'total' => 1, 'montant_total' => 0]
    ];
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="rapport_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, ['Rapport MotoTaxi', 'Période: ' . $start_date . ' à ' . $end_date]);
    fputcsv($output, []);
    
    // Statistiques générales
    fputcsv($output, ['Statistiques Générales']);
    fputcsv($output, ['Utilisateurs', $stats['users']['total'], 'Nouveaux', $stats['users']['nouveaux']]);
    fputcsv($output, ['Moto-taxis', $stats['drivers']['total'], 'Actifs', $stats['drivers']['actifs']]);
    fputcsv($output, ['Livraisons', $stats['deliveries']['total'], 'Terminées', $stats['deliveries']['terminees']]);
    fputcsv($output, ['Revenus', number_format($stats['deliveries']['revenus'], 0, ',', ' ') . ' FCFA']);
    fputcsv($output, []);
    
    // Top chauffeurs
    fputcsv($output, ['Top 10 Chauffeurs']);
    fputcsv($output, ['Nom', 'Prénom', 'Livraisons Terminées', 'Note Moyenne', 'Revenus']);
    foreach ($chart_data['top_drivers'] as $driver) {
        fputcsv($output, [
            $driver['nom'],
            $driver['prenom'],
            $driver['livraisons_terminees'],
            number_format($driver['note_moyenne'], 1),
            number_format($driver['revenus'], 0, ',', ' ') . ' FCFA'
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et Statistiques - MotoTaxi Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.5rem 0;
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0 25px 25px 0;
            margin-right: 1rem;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
        }

        .top-bar {
            background: white;
            padding: 1rem 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .bg-primary-gradient { background: linear-gradient(135deg, #3498db, #2980b9); }
        .bg-success-gradient { background: linear-gradient(135deg, #27ae60, #229954); }
        .bg-warning-gradient { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .bg-danger-gradient { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .bg-info-gradient { background: linear-gradient(135deg, #17a2b8, #138496); }
        .bg-purple-gradient { background: linear-gradient(135deg, #9b59b6, #8e44ad); }

        .filters-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .chart-card .card-body {
            height: 400px;
            position: relative;
        }

        .chart-card canvas {
            max-height: 350px !important;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8f9fa;
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .btn-export {
            background: linear-gradient(135deg, var(--success-color), #229954);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="admin_dashboard.php" class="sidebar-brand">
                <i class="fas fa-motorcycle"></i> MotoTaxi Admin
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Utilisateurs
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_drivers.php" class="nav-link">
                    <i class="fas fa-motorcycle"></i>
                    Moto-taxis
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_deliveries.php" class="nav-link">
                    <i class="fas fa-shipping-fast"></i>
                    Livraisons
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_transactions.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    Transactions
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_reports.php" class="nav-link active">
                    <i class="fas fa-chart-bar"></i>
                    Rapports
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_create.php" class="nav-link">
                    <i class="fas fa-user-plus"></i>
                    Créer Admin
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Paramètres
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin['nom'], 0, 1)); ?>
                </div>
                <div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></h5>
                    <small class="text-muted">Administrateur</small>
                </div>
            </div>
            <div>
                <a href="admin_dashboard.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left"></i> Retour au Dashboard
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn-export">
                    <i class="fas fa-download"></i> Exporter CSV
                </a>
            </div>
        </div>

        <!-- Filtres de période -->
        <div class="filters-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar"></i>
                    Période de Rapport
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="period" class="form-label">Période</label>
                        <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                            <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Ce trimestre</option>
                            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Cette année</option>
                            <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Période personnalisée</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Date de début</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Date de fin</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Générer Rapport
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistiques générales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary-gradient">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['users']['total']); ?></div>
                <div class="stat-label">Utilisateurs Totaux</div>
                <small class="text-success">+<?php echo number_format($stats['users']['nouveaux']); ?> nouveaux</small>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-success-gradient">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['drivers']['total']); ?></div>
                <div class="stat-label">Moto-taxis</div>
                <small class="text-success"><?php echo number_format($stats['drivers']['actifs']); ?> actifs</small>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-warning-gradient">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['deliveries']['total']); ?></div>
                <div class="stat-label">Livraisons</div>
                <small class="text-success"><?php echo number_format($stats['deliveries']['terminees']); ?> terminées</small>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-danger-gradient">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['deliveries']['revenus'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Revenus</div>
                <small class="text-muted">Moy: <?php echo number_format($stats['deliveries']['montant_moyen'], 0, ',', ' '); ?> FCFA</small>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-info-gradient">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['transactions']['total']); ?></div>
                <div class="stat-label">Transactions</div>
                <small class="text-success"><?php echo number_format($stats['transactions']['montant_total'], 0, ',', ' '); ?> FCFA</small>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-purple-gradient">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['deliveries']['terminees'] / max($stats['deliveries']['total'], 1) * 100, 1); ?>%</div>
                <div class="stat-label">Taux de Réussite</div>
                <small class="text-muted">Livraisons terminées</small>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="row">
            <!-- Graphique des livraisons -->
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line"></i>
                            Évolution des Livraisons (7 derniers jours)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="deliveriesChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Répartition des statuts -->
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie"></i>
                            Répartition par Statut
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 10 Chauffeurs -->
        <div class="table-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy"></i>
                    Top 10 Chauffeurs
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Chauffeur</th>
                            <th>Livraisons Terminées</th>
                            <th>Note Moyenne</th>
                            <th>Revenus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chart_data['top_drivers'] as $index => $driver): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($driver['nom'] . ' ' . $driver['prenom']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $driver['livraisons_terminees']; ?></span>
                                </td>
                                <td>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $driver['note_moyenne'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($driver['note_moyenne'], 1); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo number_format($driver['revenus'], 0, ',', ' '); ?> FCFA</strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top 10 Clients -->
        <div class="table-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-crown"></i>
                    Top 10 Clients
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Client</th>
                            <th>Livraisons</th>
                            <th>Montant Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chart_data['top_clients'] as $index => $client): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $client['livraisons_terminees']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo number_format($client['montant_total'], 0, ',', ' '); ?> FCFA</strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour charger Chart.js
        function loadChartJS() {
            return new Promise((resolve, reject) => {
                if (typeof Chart !== 'undefined') {
                    resolve();
                    return;
                }
                
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js';
                script.onload = () => {
                    console.log('Chart.js chargé avec succès');
                    resolve();
                };
                script.onerror = () => {
                    console.error('Erreur lors du chargement de Chart.js');
                    reject();
                };
                document.head.appendChild(script);
            });
        }

        // Initialiser les graphiques quand Chart.js est prêt
        document.addEventListener('DOMContentLoaded', function() {
            loadChartJS().then(() => {
                initializeCharts();
            }).catch(() => {
                console.error('Impossible de charger Chart.js');
            });
        });

        function initializeCharts() {
            console.log('Initialisation des graphiques...');
            
            // Vérifier que Chart.js est disponible
            if (typeof Chart === 'undefined') {
                console.error('Chart.js n\'est pas disponible !');
                return;
            }
            
            // Vérifier les données
            console.log('Données des statuts:', <?php echo json_encode($chart_data['delivery_status']); ?>);
            console.log('Données quotidiennes:', <?php echo json_encode($chart_data['daily_deliveries']); ?>);
            
            // Vérifier que les éléments canvas existent
            const deliveriesCanvas = document.getElementById('deliveriesChart');
            const statusCanvas = document.getElementById('statusChart');
            
            if (!deliveriesCanvas) {
                console.error('Canvas deliveriesChart non trouvé !');
                return;
            }
            
            if (!statusCanvas) {
                console.error('Canvas statusChart non trouvé !');
                return;
            }
            
            console.log('Canvas trouvés, création des graphiques...');
            
            // Graphique des livraisons
            const deliveriesCtx = deliveriesCanvas.getContext('2d');
        const deliveriesChart = new Chart(deliveriesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data['daily_deliveries'], 'date')); ?>,
                datasets: [{
                    label: 'Livraisons',
                    data: <?php echo json_encode(array_column($chart_data['daily_deliveries'], 'total')); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Terminées',
                    data: <?php echo json_encode(array_column($chart_data['daily_deliveries'], 'terminees')); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

            // Graphique des statuts
            const statusCtx = statusCanvas.getContext('2d');
        
        // Données des statuts depuis la base de données
        const statusData = <?php echo json_encode($chart_data['delivery_status']); ?>;
        const statusLabels = statusData.map(item => {
            // Traduire les statuts en français
            const translations = {
                'en_attente': 'En attente',
                'en_cours': 'En cours',
                'terminee': 'Terminée',
                'annulee': 'Annulée',
                'en_preparation': 'En préparation',
                'en_route': 'En route',
                'livree': 'Livrée',
                'aucune_donnee': 'Aucune donnée'
            };
            return translations[item.statut] || item.statut;
        });
        const statusValues = statusData.map(item => item.total);
        
        // Couleurs pour chaque statut
        const statusColors = [
            '#27ae60', // Vert - Terminée
            '#3498db', // Bleu - En cours
            '#f39c12', // Orange - En attente
            '#e74c3c', // Rouge - Annulée
            '#9b59b6', // Violet - En préparation
            '#1abc9c', // Turquoise - En route
            '#34495e', // Gris foncé - Livrée
            '#f1c40f', // Jaune
            '#e67e22', // Orange foncé
            '#95a5a6'  // Gris
        ];
        
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: statusColors.slice(0, statusLabels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        }
    </script>
</body>
</html> 