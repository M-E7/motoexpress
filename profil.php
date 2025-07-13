<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer les données utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Photo de profil - chargement depuis la base de données avec fallback
$photo = 'https://ui-avatars.com/api/?name=' . urlencode($user['prenom'].' '.$user['nom']) . '&background=2563eb&color=fff&size=64';

if ($user['photo_profil']) {
    if (strpos($user['photo_profil'], 'http') === 0) {
        $photo = $user['photo_profil'];
    } else {
        if (file_exists($user['photo_profil'])) {
            $photo = $user['photo_profil'];
        }
    }
}

// Récupérer les statistiques utilisateur
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_livraisons,
            SUM(distance_km) as distance_totale,
            SUM(montant) as depense_totale,
            AVG(CASE WHEN e.id IS NOT NULL THEN (e.ponctualite + e.etat_colis + e.politesse) / 3 END) as note_moyenne,
            COUNT(CASE WHEN l.statut = 'en_cours' THEN 1 END) as livraisons_en_cours,
            COUNT(CASE WHEN l.statut = 'annulee' THEN 1 END) as livraisons_annulees,
            MAX(l.date_creation) as derniere_livraison
        FROM livraisons l
        LEFT JOIN evaluations e ON l.id = e.livraison_id
        WHERE l.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
    
    // Valeurs par défaut si aucune donnée
    if (!$stats) {
        $stats = [
            'total_livraisons' => 0,
            'distance_totale' => 0,
            'depense_totale' => 0,
            'note_moyenne' => 5.0,
            'livraisons_en_cours' => 0,
            'livraisons_annulees' => 0,
            'derniere_livraison' => null
        ];
    }
} catch (PDOException $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $stats = [
        'total_livraisons' => 0,
        'distance_totale' => 0,
        'depense_totale' => 0,
        'note_moyenne' => 5.0,
        'livraisons_en_cours' => 0,
        'livraisons_annulees' => 0,
        'derniere_livraison' => null
    ];
}

// Récupérer l'historique des connexions
$stmt = $pdo->prepare("
    SELECT date_connexion, ip_address 
    FROM historique_connexions 
    WHERE user_id = ? 
    ORDER BY date_connexion DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$connexions = $stmt->fetchAll();

// Traitement des formulaires
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $adresse_favorite = trim($_POST['adresse_favorite'] ?? '');
            
            if (!$nom || !$prenom || !$email || !$telephone) {
                $message = "Les champs nom, prénom, email et téléphone sont obligatoires.";
                $message_type = 'error';
            } else {
                // Vérifier unicité email/téléphone
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR telephone = ?) AND id != ?");
                $stmt->execute([$email, $telephone, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $message = "Email ou téléphone déjà utilisé par un autre utilisateur.";
                    $message_type = 'error';
                } else {
                    // Gestion de la photo de profil
                    $photo_profil = $user['photo_profil'];
                    if (!empty($_FILES['photo_profil']['name'])) {
                        $target_dir = 'uploads/';
                        if (!is_dir($target_dir)) mkdir($target_dir);
                        $ext = pathinfo($_FILES['photo_profil']['name'], PATHINFO_EXTENSION);
                        $filename = uniqid('user_') . '.' . $ext;
                        $target_file = $target_dir . $filename;
                        if (move_uploaded_file($_FILES['photo_profil']['tmp_name'], $target_file)) {
                            $photo_profil = $target_file;
                        }
                    }
                    
                    // Mise à jour
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET nom = ?, prenom = ?, email = ?, telephone = ?, adresse_favorite = ?, photo_profil = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nom, $prenom, $email, $telephone, $adresse_favorite, $photo_profil, $_SESSION['user_id']]);
                    
                    $message = "Profil mis à jour avec succès !";
                    $message_type = 'success';
                    
                    // Recharger les données utilisateur
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                }
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (!$current_password || !$new_password || !$confirm_password) {
                $message = "Tous les champs sont obligatoires.";
                $message_type = 'error';
            } elseif (!password_verify($current_password, $user['mot_de_passe'])) {
                $message = "Mot de passe actuel incorrect.";
                $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = "Les nouveaux mots de passe ne correspondent pas.";
                $message_type = 'error';
            } elseif (strlen($new_password) < 6) {
                $message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
                $message_type = 'error';
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$hash, $_SESSION['user_id']]);
                
                $message = "Mot de passe modifié avec succès !";
                $message_type = 'success';
            }
            break;
            
        case 'update_settings':
            $langue = $_POST['langue'] ?? 'fr';
            $notifications = isset($_POST['notifications']) ? 1 : 0;
            $mode_sombre = isset($_POST['mode_sombre']) ? 1 : 0;
            $deux_facteurs = isset($_POST['deux_facteurs']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET langue = ?, notifications = ?, mode_sombre = ?, deux_facteurs = ?
                WHERE id = ?
            ");
            $stmt->execute([$langue, $notifications, $mode_sombre, $deux_facteurs, $_SESSION['user_id']]);
            
            $message = "Paramètres mis à jour avec succès !";
            $message_type = 'success';
            
            // Recharger les données utilisateur
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; color: #1e293b; margin: 0; }
        [data-theme="dark"] body { background: #1f2937; color: #f1f5f9; }
        .navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.navbar-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 65px;
    padding: 0 2rem;
}

.logo {
    display: flex;
    align-items: center;
    font-size: 1.4rem;
    font-weight: 700;
    color: #1a202c;
    text-decoration: none;
    letter-spacing: -0.5px;
}

.logo i {
    color: #2563eb;
    font-size: 1.6rem;
    margin-right: 0.75rem;
    transition: transform 0.3s ease;
}

.logo:hover i {
    transform: rotate(5deg);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.nav-link {
    position: relative;
    display: flex;
    align-items: center;
    padding: 0.6rem 1rem;
    color: #4a5568;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.nav-link:hover {
    color: #2563eb;
    background: rgba(37, 99, 235, 0.08);
    transform: translateY(-1px);
}

.nav-link.active {
    color: #2563eb;
    background: rgba(37, 99, 235, 0.1);
    font-weight: 600;
}

.nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    background: #2563eb;
    border-radius: 50%;
}

.nav-link i {
    font-size: 1rem;
    margin-right: 0.5rem;
    width: 16px;
    text-align: center;
}

.nav-link span {
    font-size: 0.9rem;
}

.help-link:hover {
    color: #f59e0b;
    background: rgba(245, 158, 11, 0.08);
}

.logout {
    display: flex;
    align-items: center;
    padding: 0.6rem 1.25rem;
    color: #fff;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    margin-left: 0.5rem;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);
}

.logout i {
    margin-right: 0.5rem;
    font-size: 0.9rem;
}

.logout:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.logout:active {
    transform: translateY(0);
}

/* Menu mobile */
.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: background 0.2s ease;
}

.mobile-menu-toggle:hover {
    background: rgba(0, 0, 0, 0.05);
}

.mobile-menu-toggle span {
    width: 22px;
    height: 2px;
    background: #4a5568;
    margin: 2px 0;
    transition: 0.3s;
    border-radius: 2px;
}

.mobile-menu-toggle.active span:nth-child(1) {
    transform: rotate(-45deg) translate(-4px, 4px);
}

.mobile-menu-toggle.active span:nth-child(2) {
    opacity: 0;
}

.mobile-menu-toggle.active span:nth-child(3) {
    transform: rotate(45deg) translate(-4px, -4px);
}

/* Responsive */
@media (max-width: 768px) {
    .navbar-container {
        padding: 0 1rem;
    }

    .mobile-menu-toggle {
        display: flex;
    }

    .nav-links {
        position: fixed;
        top: 65px;
        left: 0;
        width: 100%;
        height: calc(100vh - 65px);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(12px);
        flex-direction: column;
        justify-content: flex-start;
        align-items: stretch;
        padding: 1.5rem;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        gap: 0.5rem;
        border-right: 1px solid rgba(0, 0, 0, 0.08);
    }

    .nav-links.active {
        transform: translateX(0);
    }

    .nav-link {
        width: 100%;
        padding: 1rem 1.25rem;
        justify-content: flex-start;
        border-radius: 10px;
    }

    .nav-link.active::after {
        display: none;
    }

    .nav-link.active {
        background: rgba(37, 99, 235, 0.12);
    }

    .logout {
        margin-left: 0;
        margin-top: 1rem;
        justify-content: center;
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .logo {
        font-size: 1.2rem;
    }

    .logo i {
        font-size: 1.4rem;
        margin-right: 0.5rem;
    }

    .navbar-container {
        height: 60px;
    }

    .nav-links {
        top: 60px;
        height: calc(100vh - 60px);
    }
}
        .main-content { max-width: 1200px; margin: 110px auto 0 auto; padding: 2rem 1rem; }
        .welcome { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .welcome .avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #2563eb; }
        .welcome .msg { font-size: 1.3rem; font-weight: 500; }
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .profile-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.5rem; }
        .card-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .card-header i { font-size: 1.5rem; color: #2563eb; }
        .card-title { font-size: 1.2rem; font-weight: 600; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .form-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-textarea { resize: vertical; min-height: 100px; }
        .form-select { background: white; }
        .form-checkbox { margin-right: 0.5rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .message.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #f8fafc; border-radius: 8px; padding: 1rem; text-align: center; }
        .stat-number { font-size: 1.5rem; font-weight: bold; color: #2563eb; }
        .stat-label { color: #64748b; font-size: 0.9rem; }
        .connexions-list { list-style: none; padding: 0; }
        .connexion-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e2e8f0; }
        .connexion-item:last-child { border-bottom: none; }
        .connexion-info { }
        .connexion-date { color: #64748b; font-size: 0.9rem; }
        .connexion-ip { font-family: monospace; color: #6b7280; }
        .photo-upload { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .photo-preview { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
        .file-input { display: none; }
        .file-label { background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; padding: 0.5rem 1rem; cursor: pointer; transition: background 0.2s; }
        .file-label:hover { background: #e5e7eb; }
        .security-section { background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .security-title { font-weight: 600; color: #92400e; margin-bottom: 0.5rem; }
        .security-text { color: #92400e; font-size: 0.9rem; }
        @media (max-width: 768px) { 
            .profile-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-container">
        <div class="logo">
            <i class="fas fa-motorcycle"></i>
            MotoExpress
        </div>
        
        <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        
        <div class="nav-links" id="navLinks">
            <a href="user_home.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Accueil</span>
            </a>
            <a href="historique.php" class="nav-link">
                <i class="fas fa-history"></i>
                <span>Historique</span>
            </a>
            <a href="messages.php" class="nav-link">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            <a href="profil.php" class="nav-link active">
                <i class="fas fa-user"></i>
                <span>Profil</span>
            </a>
            <a href="aide.php" class="nav-link help-link">
                <i class="fas fa-question-circle"></i>
                <span>Aide</span>
            </a>
            <a href="logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="welcome">
        <img src="<?php echo $photo; ?>" alt="Photo de profil" class="avatar">
        <div class="msg">Mon Profil</div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistiques personnelles -->
    <div class="profile-card">
        <div class="card-header">
            <i class="fas fa-chart-line"></i>
            <div class="card-title">Statistiques personnelles</div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo (int)$stats['total_livraisons']; ?></div>
                <div class="stat-label">Total livraisons</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['distance_totale'] ?? 0, 1); ?> km</div>
                <div class="stat-label">Distance totale</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['depense_totale'] ?? 0, 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Dépense totale</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['note_moyenne'] ?? 5.0, 1); ?>/5</div>
                <div class="stat-label">Note moyenne</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo (int)$stats['livraisons_en_cours']; ?></div>
                <div class="stat-label">En cours</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo (int)$stats['livraisons_annulees']; ?></div>
                <div class="stat-label">Annulées</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    if ($stats['derniere_livraison']) {
                        echo date('d/m/Y', strtotime($stats['derniere_livraison']));
                    } else {
                        echo 'Aucune';
                    }
                    ?>
                </div>
                <div class="stat-label">Dernière livraison</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    if ($stats['total_livraisons'] > 0) {
                        $taux_reussite = (($stats['total_livraisons'] - $stats['livraisons_annulees']) / $stats['total_livraisons']) * 100;
                        echo number_format($taux_reussite, 0) . '%';
                    } else {
                        echo '0%';
                    }
                    ?>
                </div>
                <div class="stat-label">Taux de réussite</div>
            </div>
        </div>
    </div>

    <div class="profile-grid">
        <!-- Informations personnelles -->
        <div class="profile-card">
            <div class="card-header">
                <i class="fas fa-user"></i>
                <div class="card-title">Informations personnelles</div>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="photo-upload">
                    <img src="<?php echo $photo; ?>" alt="Photo de profil" class="photo-preview">
                    <div>
                        <input type="file" name="photo_profil" id="photo_profil" class="file-input" accept="image/*">
                        <label for="photo_profil" class="file-label">
                            <i class="fas fa-camera"></i> Changer la photo
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" class="form-input" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="prenom" class="form-input" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-input" value="<?php echo htmlspecialchars($user['telephone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Adresse favorite</label>
                    <textarea name="adresse_favorite" class="form-input form-textarea"><?php echo htmlspecialchars($user['adresse_favorite'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </form>
        </div>

        <!-- Paramètres -->
        <div class="profile-card">
            <div class="card-header">
                <i class="fas fa-cog"></i>
                <div class="card-title">Paramètres</div>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="form-group">
                    <label class="form-label">Langue</label>
                    <select name="langue" class="form-input form-select">
                        <option value="fr" <?php echo $user['langue'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                        <option value="en" <?php echo $user['langue'] === 'en' ? 'selected' : ''; ?>>English</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="notifications" class="form-checkbox" <?php echo $user['notifications'] ? 'checked' : ''; ?>>
                        Activer les notifications
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="mode_sombre" class="form-checkbox" <?php echo $user['mode_sombre'] ? 'checked' : ''; ?>>
                        Mode sombre
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="deux_facteurs" class="form-checkbox" <?php echo $user['deux_facteurs'] ? 'checked' : ''; ?>>
                        Authentification à deux facteurs
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Sauvegarder</button>
            </form>
        </div>

        <!-- Sécurité -->
        <div class="profile-card">
            <div class="card-header">
                <i class="fas fa-shield-alt"></i>
                <div class="card-title">Sécurité</div>
            </div>
            
            <div class="security-section">
                <div class="security-title">Changer le mot de passe</div>
                <div class="security-text">Assurez-vous d'utiliser un mot de passe fort et unique.</div>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Mot de passe actuel</label>
                    <input type="password" name="current_password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirm_password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
            </form>
        </div>

        <!-- Historique des connexions -->
        <div class="profile-card">
            <div class="card-header">
                <i class="fas fa-clock"></i>
                <div class="card-title">Sessions actives</div>
            </div>
            
            <ul class="connexions-list">
                <?php if (empty($connexions)): ?>
                    <li style="text-align: center; color: #64748b; padding: 1rem;">Aucune connexion récente</li>
                <?php else: ?>
                    <?php foreach ($connexions as $connexion): ?>
                        <li class="connexion-item">
                            <div class="connexion-info">
                                <div class="connexion-date"><?php echo date('d/m/Y H:i', strtotime($connexion['date_connexion'])); ?></div>
                                <div class="connexion-ip"><?php echo htmlspecialchars($connexion['ip_address']); ?></div>
                            </div>
                            <i class="fas fa-circle" style="color: #16a34a; font-size: 0.8rem;"></i>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            
            <div style="margin-top: 1rem;">
                <button class="btn btn-secondary" onclick="terminerSessions()">
                    <i class="fas fa-sign-out-alt"></i> Terminer toutes les sessions
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Prévisualisation de la photo
document.getElementById('photo_profil').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.querySelector('.photo-preview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Terminer toutes les sessions
function terminerSessions() {
    if (confirm('Êtes-vous sûr de vouloir terminer toutes les sessions ?')) {
        // Ici on pourrait implémenter la logique pour terminer les sessions
        alert('Fonctionnalité à implémenter');
    }
}

// Mode sombre
document.addEventListener('DOMContentLoaded', function() {
    const modeSombre = <?php echo $user['mode_sombre'] ? 'true' : 'false'; ?>;
    if (modeSombre) {
        document.body.setAttribute('data-theme', 'dark');
    }
});
</script>
</body>
</html> 