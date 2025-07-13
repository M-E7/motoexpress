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
        switch ($_POST['action']) {
            case 'update_tarification':
                $tarif_base = $_POST['tarif_base'];
                $tarif_km = $_POST['tarif_km'];
                $tarif_urgence = $_POST['tarif_urgence'];
                $tarif_nuit = $_POST['tarif_nuit'];
                $tarif_weekend = $_POST['tarif_weekend'];
                
                // Mettre à jour les paramètres de tarification
                $params = [
                    ['tarif_base', $tarif_base, 'Tarif de base en FCFA'],
                    ['cout_par_km', $tarif_km, 'Coût par kilomètre en FCFA'],
                    ['majoration_urgence', $tarif_urgence, 'Majoration pour livraison urgente'],
                    ['majoration_nuit', $tarif_nuit, 'Majoration pour livraison de nuit'],
                    ['majoration_weekend', $tarif_weekend, 'Majoration pour livraison le weekend']
                ];
                
                foreach ($params as $param) {
                    $stmt = $pdo->prepare("INSERT INTO parametres_tarification (nom_parametre, valeur, description, date_maj) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE valeur = ?, description = ?, date_maj = NOW()");
                    $stmt->execute([$param[0], $param[1], $param[2], $param[1], $param[2]]);
                }
                
                $message = "Paramètres de tarification mis à jour.";
                break;
                
            case 'update_system':
                // Créer la table parametres_systeme si elle n'existe pas
                $pdo->exec("CREATE TABLE IF NOT EXISTS parametres_systeme (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nom_parametre VARCHAR(100) NOT NULL UNIQUE,
                    valeur TEXT NOT NULL,
                    description TEXT,
                    date_maj DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                
                $nom_plateforme = $_POST['nom_plateforme'];
                $email_contact = $_POST['email_contact'];
                $telephone_contact = $_POST['telephone_contact'];
                $adresse_siege = $_POST['adresse_siege'];
                $devise = $_POST['devise'];
                $fuseau_horaire = $_POST['fuseau_horaire'];
                
                $params = [
                    ['nom_plateforme', $nom_plateforme, 'Nom de la plateforme'],
                    ['email_contact', $email_contact, 'Email de contact'],
                    ['telephone_contact', $telephone_contact, 'Téléphone de contact'],
                    ['adresse_siege', $adresse_siege, 'Adresse du siège'],
                    ['devise', $devise, 'Devise utilisée'],
                    ['fuseau_horaire', $fuseau_horaire, 'Fuseau horaire']
                ];
                
                foreach ($params as $param) {
                    $stmt = $pdo->prepare("INSERT INTO parametres_systeme (nom_parametre, valeur, description, date_maj) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE valeur = ?, description = ?, date_maj = NOW()");
                    $stmt->execute([$param[0], $param[1], $param[2], $param[1], $param[2]]);
                }
                
                $message = "Paramètres système mis à jour.";
                break;
        }
    }
}

// Récupérer les paramètres actuels
$tarification = [];
$stmt = $pdo->query("SELECT nom_parametre, valeur FROM parametres_tarification");
while ($row = $stmt->fetch()) {
    $tarification[$row['nom_parametre']] = $row['valeur'];
}

$systeme = [];
try {
    $stmt = $pdo->query("SELECT nom_parametre, valeur FROM parametres_systeme");
    while ($row = $stmt->fetch()) {
        $systeme[$row['nom_parametre']] = $row['valeur'];
    }
} catch (PDOException $e) {
    // Table n'existe pas encore
}

// Statistiques système
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE statut != 'supprime'");
$stats['users'] = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM moto_taxis WHERE statut != 'supprime'");
$stats['drivers'] = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM livraisons");
$stats['deliveries'] = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT SUM(montant) FROM livraisons WHERE statut = 'terminee'");
$stats['revenue'] = $stmt->fetchColumn() ?? 0;
$stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()");
$stats['db_size'] = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Paramètres Système - MotoTaxi Admin</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
    <style>
        :root { --primary-color: #2c3e50; --secondary-color: #3498db; --success-color: #27ae60; --warning-color: #f39c12; --danger-color: #e74c3c; --light-bg: #f8f9fa; }
        body { background-color: var(--light-bg); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); min-height: 100vh; position: fixed; left: 0; top: 0; width: 280px; z-index: 1000; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 2rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand { color: white; font-size: 1.5rem; font-weight: bold; text-decoration: none; }
        .sidebar-nav { padding: 1rem 0; }
        .nav-item { margin: 0.5rem 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 0.75rem 1.5rem; display: flex; align-items: center; text-decoration: none; transition: all 0.3s ease; border-radius: 0 25px 25px 0; margin-right: 1rem; }
        .nav-link:hover, .nav-link.active { color: white; background-color: rgba(255,255,255,0.1); transform: translateX(5px); }
        .nav-link i { margin-right: 0.75rem; width: 20px; text-align: center; }
        .main-content { margin-left: 280px; padding: 2rem; }
        .top-bar { background: white; padding: 1rem 2rem; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .admin-info { display: flex; align-items: center; gap: 1rem; }
        .admin-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-value { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .settings-card { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; }
        .card-header { padding: 1.5rem; border-bottom: 1px solid #e9ecef; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; border-radius: 15px 15px 0 0; }
        .card-body { padding: 2rem; }
        .form-label { font-weight: 600; color: var(--primary-color); margin-bottom: 0.5rem; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color: var(--secondary-color); box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25); }
        .btn-primary { background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none; border-radius: 10px; padding: 0.75rem 2rem; font-weight: 600; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4); }
        .info-box { background: #f8f9fa; border-left: 4px solid var(--secondary-color); padding: 1rem; margin-bottom: 1rem; border-radius: 0 10px 10px 0; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; } .sidebar.show { transform: translateX(0); } .main-content { margin-left: 0; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class='sidebar'>
        <div class='sidebar-header'>
            <a href='admin_dashboard.php' class='sidebar-brand'>
                <i class='fas fa-motorcycle'></i> MotoTaxi Admin
            </a>
        </div>
        <nav class='sidebar-nav'>
            <div class='nav-item'><a href='admin_dashboard.php' class='nav-link'><i class='fas fa-tachometer-alt'></i>Dashboard</a></div>
            <div class='nav-item'><a href='admin_users.php' class='nav-link'><i class='fas fa-users'></i>Utilisateurs</a></div>
            <div class='nav-item'><a href='admin_drivers.php' class='nav-link'><i class='fas fa-motorcycle'></i>Moto-taxis</a></div>
            <div class='nav-item'><a href='admin_deliveries.php' class='nav-link'><i class='fas fa-shipping-fast'></i>Livraisons</a></div>
            <div class='nav-item'><a href='admin_transactions.php' class='nav-link'><i class='fas fa-money-bill-wave'></i>Transactions</a></div>
            <div class='nav-item'><a href='admin_reports.php' class='nav-link'><i class='fas fa-chart-bar'></i>Rapports</a></div>
            <div class='nav-item'><a href='admin_create.php' class='nav-link'><i class='fas fa-user-plus'></i>Créer Admin</a></div>
            <div class='nav-item'><a href='admin_settings.php' class='nav-link active'><i class='fas fa-cog'></i>Paramètres</a></div>
            <div class='nav-item'><a href='admin_logout.php' class='nav-link'><i class='fas fa-sign-out-alt'></i>Déconnexion</a></div>
        </nav>
    </div>
    <!-- Main Content -->
    <div class='main-content'>
        <!-- Top Bar -->
        <div class='top-bar'>
            <div class='admin-info'>
                <div class='admin-avatar'><?php echo strtoupper(substr($admin['nom'], 0, 1)); ?></div>
                <div><h5 class='mb-0'><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></h5><small class='text-muted'>Administrateur</small></div>
            </div>
            <div><a href='admin_dashboard.php' class='btn btn-outline-primary'><i class='fas fa-arrow-left'></i> Retour au Dashboard</a></div>
        </div>
        <?php if ($message): ?><div class='alert alert-success alert-dismissible fade show' role='alert'><i class='fas fa-check-circle'></i> <?php echo htmlspecialchars($message); ?><button type='button' class='btn-close' data-bs-dismiss='alert'></button></div><?php endif; ?>
        <?php if ($error): ?><div class='alert alert-danger alert-dismissible fade show' role='alert'><i class='fas fa-exclamation-triangle'></i> <?php echo htmlspecialchars($error); ?><button type='button' class='btn-close' data-bs-dismiss='alert'></button></div><?php endif; ?>
        <!-- Statistiques système -->
        <div class='stats-grid'>
            <div class='stat-card'><div class='stat-value text-primary'><?php echo number_format($stats['users']); ?></div><div class='stat-label'>Utilisateurs</div></div>
            <div class='stat-card'><div class='stat-value text-success'><?php echo number_format($stats['drivers']); ?></div><div class='stat-label'>Moto-taxis</div></div>
            <div class='stat-card'><div class='stat-value text-warning'><?php echo number_format($stats['deliveries']); ?></div><div class='stat-label'>Livraisons</div></div>
            <div class='stat-card'><div class='stat-value text-danger'><?php echo number_format($stats['revenue'], 0, ',', ' '); ?> FCFA</div><div class='stat-label'>Revenus</div></div>
            <div class='stat-card'><div class='stat-value text-info'><?php echo $stats['db_size']; ?> MB</div><div class='stat-label'>Taille Base de Données</div></div>
        </div>
        <!-- Paramètres de tarification -->
        <div class='settings-card'>
            <div class='card-header'><h5 class='mb-0'><i class='fas fa-money-bill-wave'></i> Paramètres de Tarification</h5></div>
            <div class='card-body'>
                <form method='POST'><input type='hidden' name='action' value='update_tarification'>
                    <div class='row'>
                        <div class='col-md-4'><label class='form-label'>Tarif de base (FCFA)</label><input type='number' class='form-control' name='tarif_base' value='<?php echo $tarification['tarif_base'] ?? 500; ?>' min='0' required></div>
                        <div class='col-md-4'><label class='form-label'>Tarif par km (FCFA)</label><input type='number' class='form-control' name='tarif_km' value='<?php echo $tarification['tarif_km'] ?? 100; ?>' min='0' required></div>
                        <div class='col-md-4'><label class='form-label'>Supplément urgence (FCFA)</label><input type='number' class='form-control' name='tarif_urgence' value='<?php echo $tarification['majoration_urgence'] ?? 200; ?>' min='0' required></div>
                    </div>
                    <div class='row mt-3'>
                        <div class='col-md-6'><label class='form-label'>Supplément nuit (FCFA)</label><input type='number' class='form-control' name='tarif_nuit' value='<?php echo $tarification['majoration_nuit'] ?? 150; ?>' min='0' required></div>
                        <div class='col-md-6'><label class='form-label'>Supplément weekend (FCFA)</label><input type='number' class='form-control' name='tarif_weekend' value='<?php echo $tarification['majoration_weekend'] ?? 100; ?>' min='0' required></div>
                    </div>
                    <div class='text-center mt-3'><button type='submit' class='btn btn-primary'><i class='fas fa-save'></i> Sauvegarder</button></div>
                </form>
            </div>
        </div>
        <!-- Paramètres système -->
        <div class='settings-card'>
            <div class='card-header'><h5 class='mb-0'><i class='fas fa-cog'></i> Paramètres Système</h5></div>
            <div class='card-body'>
                <form method='POST'><input type='hidden' name='action' value='update_system'>
                    <div class='row'>
                        <div class='col-md-6'><label class='form-label'>Nom de la plateforme</label><input type='text' class='form-control' name='nom_plateforme' value='<?php echo htmlspecialchars($systeme['nom_plateforme'] ?? 'MotoTaxi'); ?>' required></div>
                        <div class='col-md-6'><label class='form-label'>Devise</label><select class='form-select' name='devise' required><option value='FCFA' <?php echo ($systeme['devise'] ?? 'FCFA') === 'FCFA' ? 'selected' : ''; ?>>FCFA</option><option value='EUR' <?php echo ($systeme['devise'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR</option><option value='USD' <?php echo ($systeme['devise'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD</option></select></div>
                    </div>
                    <div class='row mt-3'>
                        <div class='col-md-6'><label class='form-label'>Email de contact</label><input type='email' class='form-control' name='email_contact' value='<?php echo htmlspecialchars($systeme['email_contact'] ?? ''); ?>' required></div>
                        <div class='col-md-6'><label class='form-label'>Téléphone de contact</label><input type='tel' class='form-control' name='telephone_contact' value='<?php echo htmlspecialchars($systeme['telephone_contact'] ?? ''); ?>' required></div>
                    </div>
                    <div class='row mt-3'>
                        <div class='col-md-8'><label class='form-label'>Adresse du siège</label><textarea class='form-control' name='adresse_siege' rows='2' required><?php echo htmlspecialchars($systeme['adresse_siege'] ?? ''); ?></textarea></div>
                        <div class='col-md-4'><label class='form-label'>Fuseau horaire</label><select class='form-select' name='fuseau_horaire' required><option value='Africa/Abidjan' <?php echo ($systeme['fuseau_horaire'] ?? 'Africa/Abidjan') === 'Africa/Abidjan' ? 'selected' : ''; ?>>Afrique de l'Ouest (UTC+0)</option><option value='Europe/Paris' <?php echo ($systeme['fuseau_horaire'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Europe (UTC+1/+2)</option><option value='America/New_York' <?php echo ($systeme['fuseau_horaire'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Amérique (UTC-5/-4)</option></select></div>
                    </div>
                    <div class='text-center mt-3'><button type='submit' class='btn btn-primary'><i class='fas fa-save'></i> Sauvegarder</button></div>
                </form>
            </div>
        </div>
        <!-- Informations système -->
        <div class='settings-card'>
            <div class='card-header'><h5 class='mb-0'><i class='fas fa-info-circle'></i> Informations Système</h5></div>
            <div class='card-body'>
                <div class='row'>
                    <div class='col-md-6'><h6><i class='fas fa-server'></i> Serveur</h6><ul class='list-unstyled'><li><strong>PHP Version :</strong> <?php echo phpversion(); ?></li><li><strong>Serveur Web :</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu'; ?></li><li><strong>Base de données :</strong> MySQL</li><li><strong>Date du serveur :</strong> <?php echo date('d/m/Y H:i:s'); ?></li></ul></div>
                    <div class='col-md-6'><h6><i class='fas fa-database'></i> Base de Données</h6><ul class='list-unstyled'><li><strong>Taille :</strong> <?php echo $stats['db_size']; ?> MB</li><li><strong>Tables :</strong> 15+ tables</li><li><strong>Dernière sauvegarde :</strong> <?php echo date('d/m/Y H:i'); ?></li><li><strong>Statut :</strong> <span class='badge bg-success'>Connecté</span></li></ul></div>
                </div>
            </div>
        </div>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html> 