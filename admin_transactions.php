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

// Traitement des actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $transaction_id = $_POST['transaction_id'];
        
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE transactions_solde SET statut = 'terminee' WHERE id = ?");
                $stmt->execute([$transaction_id]);
                $message = "Transaction approuvée avec succès.";
                break;
                
            case 'cancel':
                $stmt = $pdo->prepare("UPDATE transactions_solde SET statut = 'annulee' WHERE id = ?");
                $stmt->execute([$transaction_id]);
                $message = "Transaction annulée avec succès.";
                break;
        }
    }
}

// Filtres
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$user_type = $_GET['user_type'] ?? 'all';

// Construction de la requête
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR mt.nom LIKE ? OR mt.prenom LIKE ? OR ts.reference_transaction LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($type_filter) {
    $where_conditions[] = "ts.type = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $where_conditions[] = "ts.statut = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(ts.date_transaction) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "ts.date_transaction >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "ts.date_transaction >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Compter le total
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM transactions_solde ts
    LEFT JOIN users u ON ts.user_id = u.id
    LEFT JOIN moto_taxis mt ON ts.user_id = mt.id
    $where_clause
");
$count_stmt->execute($params);
$total_transactions = $count_stmt->fetchColumn();
$total_pages = ceil($total_transactions / $per_page);

// Récupérer les transactions
$stmt = $pdo->prepare("
    SELECT ts.*, 
           CONCAT(u.nom, ' ', u.prenom) as user_name,
           CONCAT(mt.nom, ' ', mt.prenom) as driver_name,
           u.telephone as user_phone,
           mt.telephone as driver_phone,
           CASE WHEN u.id IS NOT NULL THEN 'user' ELSE 'driver' END as user_type
    FROM transactions_solde ts
    LEFT JOIN users u ON ts.user_id = u.id
    LEFT JOIN moto_taxis mt ON ts.user_id = mt.id
    $where_clause
    ORDER BY ts.date_transaction DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Statistiques
$stats = [];

// Total transactions
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde");
$stats['total'] = $stmt->fetchColumn();

// Montant total de toutes les transactions
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde");
$stats['montant_total_toutes'] = $stmt->fetchColumn() ?? 0;

// Montant total des transactions terminées
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE statut = 'terminee'");
$stats['montant_total'] = $stmt->fetchColumn() ?? 0;

// Montant total des transactions en attente
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE statut = 'en_attente'");
$stats['montant_en_attente'] = $stmt->fetchColumn() ?? 0;

// Montant total des transactions annulées
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE statut = 'annulee'");
$stats['montant_annulees'] = $stmt->fetchColumn() ?? 0;

// Transactions terminées
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde WHERE statut = 'terminee'");
$stats['terminees'] = $stmt->fetchColumn();

// Transactions en attente
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde WHERE statut = 'en_attente'");
$stats['en_attente'] = $stmt->fetchColumn();

// Transactions annulées
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde WHERE statut = 'annulee'");
$stats['annulees'] = $stmt->fetchColumn();

// Transactions échouées
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde WHERE statut = 'echouee'");
$stats['echouees'] = $stmt->fetchColumn();

// Montant des transactions échouées
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE statut = 'echouee'");
$stats['montant_echouees'] = $stmt->fetchColumn() ?? 0;

// Transactions du jour
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde WHERE DATE(date_transaction) = CURDATE()");
$stats['aujourd_hui'] = $stmt->fetchColumn();

// Montant du jour (toutes les transactions)
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE DATE(date_transaction) = CURDATE()");
$stats['montant_aujourd_hui_toutes'] = $stmt->fetchColumn() ?? 0;

// Montant du jour (terminées seulement)
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE DATE(date_transaction) = CURDATE() AND statut = 'terminee'");
$stats['montant_aujourd_hui'] = $stmt->fetchColumn() ?? 0;

// Transactions de cette semaine
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde WHERE YEARWEEK(date_transaction) = YEARWEEK(NOW())");
$stats['cette_semaine'] = $stmt->fetchColumn();

// Montant de cette semaine
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE YEARWEEK(date_transaction) = YEARWEEK(NOW()) AND statut = 'terminee'");
$stats['montant_semaine'] = $stmt->fetchColumn() ?? 0;

// Transactions de ce mois
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions_solde WHERE MONTH(date_transaction) = MONTH(NOW()) AND YEAR(date_transaction) = YEAR(NOW())");
$stats['ce_mois'] = $stmt->fetchColumn();

// Montant de ce mois
$stmt = $pdo->query("SELECT SUM(montant) FROM transactions_solde WHERE MONTH(date_transaction) = MONTH(NOW()) AND YEAR(date_transaction) = YEAR(NOW()) AND statut = 'terminee'");
$stats['montant_mois'] = $stmt->fetchColumn() ?? 0;

// Répartition par type (toutes les transactions)
$stmt = $pdo->query("
    SELECT type, COUNT(*) as count, SUM(montant) as total 
    FROM transactions_solde 
    GROUP BY type
");
$stats['par_type_toutes'] = $stmt->fetchAll();

// Répartition par type (terminées seulement)
$stmt = $pdo->query("
    SELECT type, COUNT(*) as count, SUM(montant) as total 
    FROM transactions_solde 
    WHERE statut = 'terminee' 
    GROUP BY type
");
$stats['par_type'] = $stmt->fetchAll();

// Répartition par statut
$stmt = $pdo->query("
    SELECT statut, COUNT(*) as count, SUM(montant) as total 
    FROM transactions_solde 
    GROUP BY statut
");
$stats['par_statut'] = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Transactions - MotoTaxi Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
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

        .transactions-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
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

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-terminee { background: #d4edda; color: #155724; }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-annulee { background: #f8d7da; color: #721c24; }
        .status-echouee { background: #f8d7da; color: #721c24; }

        .type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .type-recharge { background: #d1ecf1; color: #0c5460; }
        .type-paiement { background: #d4edda; color: #155724; }
        .type-remboursement { background: #f8d7da; color: #721c24; }
        .type-commission { background: #fff3cd; color: #856404; }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            margin: 0.1rem;
        }

        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            border-radius: 10px;
            margin: 0 0.1rem;
            border: none;
            color: var(--primary-color);
        }

        .page-link:hover {
            background-color: var(--secondary-color);
            color: white;
        }

        .page-item.active .page-link {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .transaction-info {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.8rem;
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
                <a href="admin_transactions.php" class="nav-link active">
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
                <a href="admin_dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Retour au Dashboard
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value text-primary"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-success"><?php echo number_format($stats['montant_total'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Montant Total (Terminées)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-info"><?php echo number_format($stats['montant_total_toutes'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Montant Total (Toutes)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-success"><?php echo number_format($stats['terminees']); ?></div>
                <div class="stat-label">Terminées</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-warning"><?php echo number_format($stats['en_attente']); ?></div>
                <div class="stat-label">En Attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-danger"><?php echo number_format($stats['annulees']); ?></div>
                <div class="stat-label">Annulées</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-secondary"><?php echo number_format($stats['echouees']); ?></div>
                <div class="stat-label">Échouées</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-info"><?php echo number_format($stats['aujourd_hui']); ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-primary"><?php echo number_format($stats['montant_aujourd_hui'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Montant Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-success"><?php echo number_format($stats['cette_semaine']); ?></div>
                <div class="stat-label">Cette Semaine</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-warning"><?php echo number_format($stats['montant_semaine'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Montant Semaine</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-info"><?php echo number_format($stats['ce_mois']); ?></div>
                <div class="stat-label">Ce Mois</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-primary"><?php echo number_format($stats['montant_mois'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Montant Mois</div>
            </div>
        </div>

        <!-- Répartition par type -->
        <div class="filters-card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie"></i>
                    Répartition par Type (Transactions Terminées)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats['par_type'] as $type): ?>
                        <div class="col-md-3 mb-2">
                            <div class="transaction-info">
                                <strong><?php echo ucfirst($type['type']); ?></strong>
                                <div><?php echo number_format($type['count']); ?> transactions</div>
                                <div class="text-success"><?php echo number_format($type['total'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Répartition par type (toutes les transactions) -->
        <div class="filters-card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar"></i>
                    Répartition par Type (Toutes les Transactions)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats['par_type_toutes'] as $type): ?>
                        <div class="col-md-3 mb-2">
                            <div class="transaction-info">
                                <strong><?php echo ucfirst($type['type']); ?></strong>
                                <div><?php echo number_format($type['count']); ?> transactions</div>
                                <div class="text-primary"><?php echo number_format($type['total'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Répartition par statut -->
        <div class="filters-card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line"></i>
                    Répartition par Statut
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats['par_statut'] as $statut): ?>
                        <div class="col-md-3 mb-2">
                            <div class="transaction-info">
                                <strong>
                                    <?php 
                                    switch($statut['statut']) {
                                        case 'terminee': echo 'Terminées'; break;
                                        case 'en_attente': echo 'En attente'; break;
                                        case 'annulee': echo 'Annulées'; break;
                                        case 'echouee': echo 'Échouées'; break;
                                        default: echo ucfirst($statut['statut']);
                                    }
                                    ?>
                                </strong>
                                <div><?php echo number_format($statut['count']); ?> transactions</div>
                                <div class="text-<?php 
                                    switch($statut['statut']) {
                                        case 'terminee': echo 'success'; break;
                                        case 'en_attente': echo 'warning'; break;
                                        case 'annulee': echo 'danger'; break;
                                        case 'echouee': echo 'secondary'; break;
                                        default: echo 'primary';
                                    }
                                ?>"><?php echo number_format($statut['total'], 0, ',', ' '); ?> FCFA</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Résumé des montants par statut -->
        <div class="filters-card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave"></i>
                    Résumé des Montants par Statut
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <div class="transaction-info">
                            <strong class="text-success">Terminées</strong>
                            <div><?php echo number_format($stats['terminees']); ?> transactions</div>
                            <div class="text-success"><?php echo number_format($stats['montant_total'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="transaction-info">
                            <strong class="text-warning">En Attente</strong>
                            <div><?php echo number_format($stats['en_attente']); ?> transactions</div>
                            <div class="text-warning"><?php echo number_format($stats['montant_en_attente'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="transaction-info">
                            <strong class="text-danger">Annulées</strong>
                            <div><?php echo number_format($stats['annulees']); ?> transactions</div>
                            <div class="text-danger"><?php echo number_format($stats['montant_annulees'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="transaction-info">
                            <strong class="text-secondary">Échouées</strong>
                            <div><?php echo number_format($stats['echouees']); ?> transactions</div>
                            <div class="text-secondary"><?php echo number_format($stats['montant_echouees'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter"></i>
                    Filtres et Recherche
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Rechercher</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Client, référence...">
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Tous les types</option>
                            <option value="recharge" <?php echo $type_filter === 'recharge' ? 'selected' : ''; ?>>Recharge</option>
                            <option value="paiement" <?php echo $type_filter === 'paiement' ? 'selected' : ''; ?>>Paiement</option>
                            <option value="remboursement" <?php echo $type_filter === 'remboursement' ? 'selected' : ''; ?>>Remboursement</option>
                            <option value="commission" <?php echo $type_filter === 'commission' ? 'selected' : ''; ?>>Commission</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="terminee" <?php echo $status_filter === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="annulee" <?php echo $status_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            <option value="echouee" <?php echo $status_filter === 'echouee' ? 'selected' : ''; ?>>Échouée</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_filter" class="form-label">Période</label>
                        <select class="form-select" id="date_filter" name="date_filter">
                            <option value="">Toutes les périodes</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des transactions -->
        <div class="transactions-table">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave"></i>
                    Liste des Transactions (<?php echo number_format($total_transactions); ?>)
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client/Chauffeur</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Solde Avant/Après</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                    <br>Aucune transaction trouvée
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $transaction['id']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($transaction['reference_transaction']); ?></small>
                                    </td>
                                    <td>
                                        <div class="transaction-info">
                                            <div>
                                                <i class="fas fa-<?php echo $transaction['user_type'] === 'user' ? 'user' : 'motorcycle'; ?> text-primary"></i>
                                                <strong><?php echo htmlspecialchars($transaction['user_name'] ?? $transaction['driver_name']); ?></strong>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($transaction['user_phone'] ?? $transaction['driver_phone']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="type-badge type-<?php echo $transaction['type']; ?>">
                                            <?php 
                                            switch($transaction['type']) {
                                                case 'recharge': echo 'Recharge'; break;
                                                case 'paiement': echo 'Paiement'; break;
                                                case 'remboursement': echo 'Remboursement'; break;
                                                case 'commission': echo 'Commission'; break;
                                                default: echo ucfirst($transaction['type']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($transaction['montant'], 0, ',', ' '); ?> FCFA</strong>
                                    </td>
                                    <td>
                                        <div class="transaction-info">
                                            <div><?php echo number_format($transaction['solde_avant'], 0, ',', ' '); ?> → <?php echo number_format($transaction['solde_apres'], 0, ',', ' '); ?> FCFA</div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $transaction['statut']; ?>">
                                            <?php 
                                            switch($transaction['statut']) {
                                                case 'terminee': echo 'Terminée'; break;
                                                case 'en_attente': echo 'En attente'; break;
                                                case 'annulee': echo 'Annulée'; break;
                                                case 'echouee': echo 'Échouée'; break;
                                                default: echo ucfirst($transaction['statut']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($transaction['date_transaction'])); ?></div>
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($transaction['date_transaction'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info btn-action" 
                                                    data-bs-toggle="modal" data-bs-target="#transactionModal<?php echo $transaction['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($transaction['statut'] === 'en_attente'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-sm btn-outline-success btn-action">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-action" 
                                                            onclick="return confirm('Annuler cette transaction ?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Pagination des transactions">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 