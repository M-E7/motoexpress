<?php
session_start();
require_once 'db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Récupérer les informations de l'admin
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Si l'admin n'existe pas, rediriger vers la connexion
if (!$admin) {
    header('Location: admin_login.php');
    exit();
}

// Statistiques globales
$stats = [];

// Nombre total d'utilisateurs
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['users'] = $stmt->fetch()['total'];

// Nombre total de moto-taxis
$stmt = $pdo->query("SELECT COUNT(*) as total FROM moto_taxis");
$stats['moto_taxis'] = $stmt->fetch()['total'];

// Nombre total de livraisons
$stmt = $pdo->query("SELECT COUNT(*) as total FROM livraisons");
$stats['deliveries'] = $stmt->fetch()['total'];

// Revenus totaux (depuis les transactions moto-taxi)
$stmt = $pdo->query("SELECT SUM(montant) as total FROM transactions_moto_taxi WHERE type = 'gain'");
$stats['revenue'] = $stmt->fetch()['total'] ?? 0;

// Livraisons en cours
$stmt = $pdo->query("SELECT COUNT(*) as total FROM livraisons WHERE statut = 'en_cours'");
$stats['active_deliveries'] = $stmt->fetch()['total'];

// Moto-taxis en ligne (actifs)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM moto_taxis WHERE statut = 'actif'");
$stats['online_drivers'] = $stmt->fetch()['total'];

// Livraisons du jour
$stmt = $pdo->query("SELECT COUNT(*) as total FROM livraisons WHERE DATE(date_creation) = CURDATE()");
$stats['today_deliveries'] = $stmt->fetch()['total'];

// Revenus du jour
$stmt = $pdo->query("SELECT SUM(montant) as total FROM transactions_moto_taxi WHERE type = 'gain' AND DATE(date_transaction) = CURDATE()");
$stats['today_revenue'] = $stmt->fetch()['total'] ?? 0;

// Dernières livraisons
$stmt = $pdo->query("
    SELECT l.*, CONCAT(u.nom, ' ', u.prenom) as user_name, CONCAT(mt.nom, ' ', mt.prenom) as driver_name 
    FROM livraisons l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN moto_taxis mt ON l.moto_taxi_id = mt.id 
    ORDER BY l.date_creation DESC 
    LIMIT 10
");
$recent_deliveries = $stmt->fetchAll();

// Alertes importantes
$alerts = [];

// Moto-taxis en attente de validation
$stmt = $pdo->query("SELECT COUNT(*) as total FROM moto_taxis WHERE statut = 'en_attente'");
$pending_drivers = $stmt->fetch()['total'];
if ($pending_drivers > 0) {
    $alerts[] = [
        'type' => 'warning',
        'message' => "$pending_drivers moto-taxi(s) en attente de validation",
        'link' => 'admin_drivers.php'
    ];
}

// Livraisons en retard
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM livraisons 
    WHERE statut = 'en_cours' 
    AND date_creation < DATE_SUB(NOW(), INTERVAL 2 HOUR)
");
$late_deliveries = $stmt->fetch()['total'];
if ($late_deliveries > 0) {
    $alerts[] = [
        'type' => 'danger',
        'message' => "$late_deliveries livraison(s) en retard",
        'link' => 'admin_deliveries.php'
    ];
}

// Utilisateurs inactifs
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM users 
    WHERE derniere_connexion < DATE_SUB(NOW(), INTERVAL 30 DAY) OR derniere_connexion IS NULL
");
$inactive_users = $stmt->fetch()['total'];
if ($inactive_users > 0) {
    $alerts[] = [
        'type' => 'info',
        'message' => "$inactive_users utilisateur(s) inactif(s)",
        'link' => 'admin_users.php'
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrateur - MotoTaxi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #2c3e50;
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

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .chart-card, .alerts-card, .recent-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .card-body {
            padding: 1.5rem;
        }

        .alert-item {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            border-left: 4px solid;
            background: #f8f9fa;
        }

        .alert-warning { border-left-color: var(--warning-color); }
        .alert-danger { border-left-color: var(--danger-color); }
        .alert-info { border-left-color: var(--secondary-color); }

        .delivery-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .delivery-item:last-child {
            border-bottom: none;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-en_cours { background: #d1ecf1; color: #0c5460; }
        .status-terminee { background: #d4edda; color: #155724; }
        .status-annulee { background: #f8d7da; color: #721c24; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            background: white;
            border: none;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            color: var(--secondary-color);
        }

        .action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
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

            .content-grid {
                grid-template-columns: 1fr;
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
                <a href="admin_dashboard.php" class="nav-link active">
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
                <a href="admin_reports.php" class="nav-link">
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
                <span class="text-muted">
                    <i class="fas fa-clock"></i>
                    <?php echo date('d/m/Y H:i'); ?>
                </span>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="admin_users.php" class="action-btn">
                <i class="fas fa-user-plus"></i>
                <strong>Gérer Utilisateurs</strong>
            </a>
            <a href="admin_drivers.php" class="action-btn">
                <i class="fas fa-user-check"></i>
                <strong>Valider Moto-taxis</strong>
            </a>
            <a href="admin_deliveries.php" class="action-btn">
                <i class="fas fa-route"></i>
                <strong>Suivre Livraisons</strong>
            </a>
            <a href="admin_reports.php" class="action-btn">
                <i class="fas fa-file-alt"></i>
                <strong>Générer Rapports</strong>
            </a>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary-gradient">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
                <div class="stat-label">Utilisateurs Totaux</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-success-gradient">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['moto_taxis']); ?></div>
                <div class="stat-label">Moto-taxis</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-warning-gradient">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['deliveries']); ?></div>
                <div class="stat-label">Livraisons Totales</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-danger-gradient">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['revenue'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Revenus Totaux</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-info-gradient">
                    <i class="fas fa-route"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_deliveries']); ?></div>
                <div class="stat-label">Livraisons en Cours</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-purple-gradient">
                    <i class="fas fa-signal"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['online_drivers']); ?></div>
                <div class="stat-label">Moto-taxis en Ligne</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Charts and Recent Deliveries -->
            <div>
                <!-- Chart Card -->
                <div class="chart-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line"></i>
                            Statistiques des Livraisons
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="deliveryChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Recent Deliveries -->
                <div class="recent-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i>
                            Livraisons Récentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_deliveries)): ?>
                            <p class="text-muted text-center">Aucune livraison récente</p>
                        <?php else: ?>
                            <?php foreach ($recent_deliveries as $delivery): ?>
                                <div class="delivery-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($delivery['adresse_depart']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($delivery['user_name'] ?? 'Utilisateur'); ?> → 
                                            <?php echo htmlspecialchars($delivery['driver_name'] ?? 'Non assigné'); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($delivery['date_creation'])); ?>
                                        </small>
                                    </div>
                                    <span class="status-badge status-<?php echo $delivery['statut']; ?>">
                                        <?php 
                                        switch($delivery['statut']) {
                                            case 'en_attente': echo 'En attente'; break;
                                            case 'en_cours': echo 'En cours'; break;
                                            case 'terminee': echo 'Terminée'; break;
                                            case 'annulee': echo 'Annulée'; break;
                                            default: echo ucfirst($delivery['statut']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Alerts and Today Stats -->
            <div>
                <!-- Today's Statistics -->
                <div class="chart-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-day"></i>
                            Aujourd'hui
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="stat-value text-primary"><?php echo number_format($stats['today_deliveries']); ?></div>
                                <div class="stat-label">Livraisons</div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="stat-value text-success"><?php echo number_format($stats['today_revenue'], 0, ',', ' '); ?> FCFA</div>
                                <div class="stat-label">Revenus</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <div class="alerts-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            Alertes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($alerts)): ?>
                            <p class="text-muted text-center">Aucune alerte</p>
                        <?php else: ?>
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert-item alert-<?php echo $alert['type']; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($alert['message']); ?></span>
                                        <a href="<?php echo $alert['link']; ?>" class="btn btn-sm btn-outline-primary">
                                            Voir
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script>
        // Chart configuration
        const ctx = document.getElementById('deliveryChart').getContext('2d');
        
        // Données fictives pour le graphique (à remplacer par des données réelles)
        const deliveryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                datasets: [{
                    label: 'Livraisons',
                    data: [12, 19, 15, 25, 22, 30, 28],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Revenus (k FCFA)',
                    data: [24, 38, 30, 50, 44, 60, 56],
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    fill: true,
                    yAxisID: 'y1'
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
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nombre de livraisons'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenus (k FCFA)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Auto-refresh des données
        setInterval(() => {
            // Ici vous pouvez ajouter une requête AJAX pour actualiser les données
            console.log('Actualisation des données...');
        }, 30000); // Actualisation toutes les 30 secondes
    </script>
</body>
</html> 