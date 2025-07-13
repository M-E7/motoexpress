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
        $delivery_id = $_POST['delivery_id'];
        
        switch ($_POST['action']) {
            case 'cancel':
                $stmt = $pdo->prepare("UPDATE livraisons SET statut = 'annulee' WHERE id = ?");
                $stmt->execute([$delivery_id]);
                $message = "Livraison annulée avec succès.";
                break;
                
            case 'complete':
                try {
                    require_once 'paiement_automatique.php';
                    
                    $pdo->beginTransaction();
                    
                    // Récupérer les informations de la livraison
                    $stmt = $pdo->prepare("SELECT * FROM livraisons WHERE id = ? AND statut IN ('en_cours', 'en_attente')");
                    $stmt->execute([$delivery_id]);
                    $liv = $stmt->fetch();
                    
                    if (!$liv) {
                        throw new Exception('Livraison non trouvée ou déjà terminée.');
                    }
                    
                    // Mettre à jour le statut de la livraison
                    $stmt = $pdo->prepare("UPDATE livraisons SET statut = 'terminee', date_fin_livraison = NOW() WHERE id = ?");
                    $stmt->execute([$delivery_id]);
                    
                    // Si la livraison a un moto-taxi assigné, procéder au paiement
                    if ($liv['moto_taxi_id']) {
                        $resultat_paiement = effectuerPaiementAutomatique($delivery_id, $liv['moto_taxi_id'], 0.05, 'admin');
                        
                        if (!$resultat_paiement['success']) {
                            throw new Exception($resultat_paiement['message']);
                        }
                    }
                    
                    $pdo->commit();
                    $message = "Livraison marquée comme terminée avec paiement automatique.";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Erreur lors de la finalisation: " . $e->getMessage();
                }
                break;
        }
    }
}

// Filtres
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';

// Construction de la requête
$where_conditions = ["l.statut != 'supprime'"];
$params = [];

if ($search) {
    $where_conditions[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR mt.nom LIKE ? OR mt.prenom LIKE ? OR l.adresse_depart LIKE ? OR l.adresse_arrivee LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "l.statut = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(l.date_creation) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "l.date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "l.date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Compter le total
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM livraisons l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN moto_taxis mt ON l.moto_taxi_id = mt.id 
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_deliveries = $count_stmt->fetchColumn();
$total_pages = ceil($total_deliveries / $per_page);

// Récupérer les livraisons
$stmt = $pdo->prepare("
    SELECT l.*, 
           CONCAT(u.nom, ' ', u.prenom) as user_name,
           CONCAT(mt.nom, ' ', mt.prenom) as driver_name,
           u.telephone as user_phone,
           mt.telephone as driver_phone
    FROM livraisons l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN moto_taxis mt ON l.moto_taxi_id = mt.id
    WHERE $where_clause
    ORDER BY l.date_creation DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$deliveries = $stmt->fetchAll();

// Statistiques
$stats = [];

// Total livraisons
$stmt = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut != 'supprime'");
$stats['total'] = $stmt->fetchColumn();

// Livraisons en cours
$stmt = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut = 'en_cours'");
$stats['en_cours'] = $stmt->fetchColumn();

// Livraisons terminées
$stmt = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut = 'terminee'");
$stats['terminees'] = $stmt->fetchColumn();

// Livraisons en attente
$stmt = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut = 'en_attente'");
$stats['en_attente'] = $stmt->fetchColumn();

// Livraisons annulées
$stmt = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE statut = 'annulee'");
$stats['annulees'] = $stmt->fetchColumn();

// Revenus totaux
$stmt = $pdo->query("SELECT SUM(montant) FROM livraisons WHERE statut = 'terminee'");
$stats['revenus'] = $stmt->fetchColumn() ?? 0;

// Livraisons du jour
$stmt = $pdo->query("SELECT COUNT(*) FROM livraisons WHERE DATE(date_creation) = CURDATE()");
$stats['aujourd_hui'] = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Livraisons - MotoTaxi Admin</title>
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

        .deliveries-table {
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

        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-en_cours { background: #d1ecf1; color: #0c5460; }
        .status-terminee { background: #d4edda; color: #155724; }
        .status-annulee { background: #f8d7da; color: #721c24; }

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

        .delivery-route {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.8rem;
        }

        .delivery-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
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
                <a href="admin_deliveries.php" class="nav-link active">
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
                <div class="stat-label">Total Livraisons</div>
            </div>
            <div class="stat-card">
                <div class="stat-value text-info"><?php echo number_format($stats['en_cours']); ?></div>
                <div class="stat-label">En Cours</div>
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
                <div class="stat-value text-success"><?php echo number_format($stats['revenus'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Revenus Totaux</div>
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
                               placeholder="Client, chauffeur, adresse...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="en_cours" <?php echo $status_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="terminee" <?php echo $status_filter === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="annulee" <?php echo $status_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
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

        <!-- Tableau des livraisons -->
        <div class="deliveries-table">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-shipping-fast"></i>
                    Liste des Livraisons (<?php echo number_format($total_deliveries); ?>)
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Chauffeur</th>
                            <th>Trajet</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deliveries)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-shipping-fast fa-2x mb-2"></i>
                                    <br>Aucune livraison trouvée
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($deliveries as $delivery): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $delivery['id']; ?></strong>
                                    </td>
                                    <td>
                                        <div class="delivery-info">
                                            <i class="fas fa-user text-primary"></i>
                                            <div>
                                                <strong><?php echo htmlspecialchars($delivery['user_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($delivery['user_phone']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($delivery['driver_name']): ?>
                                            <div class="delivery-info">
                                                <i class="fas fa-motorcycle text-success"></i>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($delivery['driver_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($delivery['driver_phone']); ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Non assigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="delivery-route">
                                            <div><i class="fas fa-map-marker-alt text-danger"></i> <?php echo htmlspecialchars($delivery['adresse_depart']); ?></div>
                                            <div><i class="fas fa-map-marker-alt text-success"></i> <?php echo htmlspecialchars($delivery['adresse_arrivee']); ?></div>
                                            <?php if ($delivery['distance_km']): ?>
                                                <small class="text-muted"><?php echo number_format($delivery['distance_km'], 1); ?> km</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($delivery['montant'], 0, ',', ' '); ?> FCFA</strong>
                                    </td>
                                    <td>
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
                                    </td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($delivery['date_creation'])); ?></div>
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($delivery['date_creation'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-info btn-action" 
                                                    data-bs-toggle="modal" data-bs-target="#deliveryModal<?php echo $delivery['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($delivery['statut'] === 'en_cours'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                                                    <input type="hidden" name="action" value="complete">
                                                    <button type="submit" class="btn btn-sm btn-outline-success btn-action">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($delivery['statut'], ['en_attente', 'en_cours'])): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-action" 
                                                            onclick="return confirm('Annuler cette livraison ?')">
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
            <nav aria-label="Pagination des livraisons">
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