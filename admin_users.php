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
        $user_id = $_POST['user_id'];
        
        switch ($_POST['action']) {
            case 'suspend':
                $stmt = $pdo->prepare("UPDATE users SET statut = 'suspendu' WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "Utilisateur suspendu avec succès.";
                break;
                
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET statut = 'actif' WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "Utilisateur activé avec succès.";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("UPDATE users SET statut = 'supprime' WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "Utilisateur supprimé avec succès.";
                break;
        }
    }
}

// Filtres
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';

// Construction de la requête
$where_conditions = ["u.statut != 'supprime'"];
$params = [];

if ($search) {
    $where_conditions[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "u.statut = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(date_inscription) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "date_inscription >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "date_inscription >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Compter le total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where_clause");
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Récupérer les utilisateurs
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(l.id) as total_livraisons,
           SUM(CASE WHEN l.statut = 'terminee' THEN 1 ELSE 0 END) as livraisons_terminees,
           AVG(CASE WHEN l.statut = 'terminee' THEN l.note ELSE NULL END) as note_moyenne
    FROM users u
    LEFT JOIN livraisons l ON u.id = l.user_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY u.date_inscription DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Statistiques
$stats = [];

// Total utilisateurs
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE statut != 'supprime'");
$stats['total'] = $stmt->fetchColumn();

// Utilisateurs actifs
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE statut = 'actif'");
$stats['actifs'] = $stmt->fetchColumn();

// Nouveaux utilisateurs aujourd'hui
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(date_inscription) = CURDATE()");
$stats['nouveaux_aujourd_hui'] = $stmt->fetchColumn();

// Utilisateurs suspendus
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE statut = 'suspendu'");
$stats['suspendus'] = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - MotoTaxi Admin</title>
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

        .users-table {
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

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-actif { background: #d4edda; color: #155724; }
        .status-suspendu { background: #f8d7da; color: #721c24; }

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
                <a href="admin_users.php" class="nav-link active">
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
                <div class="stat-label">Total Utilisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-success"><?php echo number_format($stats['actifs']); ?></div>
                <div class="stat-label">Utilisateurs Actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-info"><?php echo number_format($stats['nouveaux_aujourd_hui']); ?></div>
                <div class="stat-label">Nouveaux Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-warning"><?php echo number_format($stats['suspendus']); ?></div>
                <div class="stat-label">Utilisateurs Suspendus</div>
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
                    <div class="col-md-4">
                        <label for="search" class="form-label">Rechercher</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nom, email, téléphone...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo $status_filter === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="suspendu" <?php echo $status_filter === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
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

        <!-- Tableau des utilisateurs -->
        <div class="users-table">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i>
                    Liste des Utilisateurs (<?php echo number_format($total_users); ?>)
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Contact</th>
                            <th>Statut</th>
                            <th>Solde</th>
                            <th>Livraisons</th>
                            <th>Note Moy.</th>
                            <th>Inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <br>Aucun utilisateur trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?php echo strtoupper(substr($user['nom'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-envelope text-muted"></i>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-phone text-muted"></i>
                                            <?php echo htmlspecialchars($user['telephone']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['statut']; ?>">
                                            <?php 
                                            switch($user['statut']) {
                                                case 'actif': echo 'Actif'; break;
                                                case 'suspendu': echo 'Suspendu'; break;
                                                default: echo ucfirst($user['statut']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($user['solde'], 0, ',', ' '); ?> FCFA</strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo $user['total_livraisons']; ?></strong> total
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $user['livraisons_terminees']; ?> terminées
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($user['note_moyenne']): ?>
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $user['note_moyenne'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($user['note_moyenne'], 1); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Aucune note</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?></div>
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($user['date_inscription'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info btn-action" 
                                                    data-bs-toggle="modal" data-bs-target="#userModal<?php echo $user['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($user['statut'] === 'actif'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning btn-action" 
                                                            onclick="return confirm('Suspendre cet utilisateur ?')">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-sm btn-outline-success btn-action">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger btn-action" 
                                                        onclick="return confirm('Supprimer définitivement cet utilisateur ?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
            <nav aria-label="Pagination des utilisateurs">
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