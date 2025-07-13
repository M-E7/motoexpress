<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['moto_taxi_id'])) {
    header('Location: moto_taxi_login.php');
    exit;
}
$moto_taxi_id = $_SESSION['moto_taxi_id'];

// Récupérer les infos moto-taxi
$stmt = $pdo->prepare("SELECT * FROM moto_taxis WHERE id = ?");
$stmt->execute([$moto_taxi_id]);
$moto = $stmt->fetch();
if (!$moto) { session_destroy(); header('Location: moto_taxi_login.php'); exit; }

// Récupérer les documents
$stmt = $pdo->prepare("SELECT * FROM documents_moto_taxi WHERE moto_taxi_id = ?");
$stmt->execute([$moto_taxi_id]);
$docs = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);

// Statistiques
$stmt = $pdo->prepare("SELECT COUNT(*) as nb, AVG(note_generale) as note FROM evaluations WHERE evaluateur_id = ? AND type_evaluateur = 'moto_taxi'");
$stmt->execute([$moto_taxi_id]);
$stats = $stmt->fetch();

// Historique connexions
$stmt = $pdo->prepare("SELECT * FROM historique_connexions WHERE user_id = ? AND type_user = 'moto_taxi' ORDER BY date_connexion DESC LIMIT 10");
$stmt->execute([$moto_taxi_id]);
$connexions = $stmt->fetchAll();

// Gestion des messages de succès/erreur
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Moto-Taxi - MotoExpress</title>
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
        .main-content { max-width: 900px; margin: 110px auto 0 auto; padding: 2rem 1rem; }
        .profil-header { display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; }
        .avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #2563eb; }
        .profil-info { flex: 1; }
        .profil-info h2 { margin: 0 0 0.5rem 0; color: #2563eb; }
        .profil-info .stat { color: #64748b; font-size: 1rem; margin-right: 1.5rem; }
        .profil-info .stat i { color: #2563eb; margin-right: 0.3rem; }
        .section { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 2rem; margin-bottom: 2rem; }
        .section-title { font-size: 1.2rem; font-weight: 600; color: #2563eb; margin-bottom: 1.2rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500; }
        .form-input, .form-select { width: 100%; padding: 0.8rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #2563eb; }
        .btn { padding: 0.7rem 1.5rem; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-secondary:hover { background: #e5e7eb; }
        .msg-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .docs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
        .doc-card { background: #f8fafc; border-radius: 8px; padding: 1rem; text-align: center; }
        .doc-card .doc-title { font-weight: 500; color: #2563eb; margin-bottom: 0.5rem; }
        .doc-card .doc-link { color: #0369a1; text-decoration: underline; font-size: 0.95rem; }
        .doc-card .doc-status { font-size: 0.9rem; border-radius: 8px; padding: 0.2rem 0.6rem; display: inline-block; margin-top: 0.5rem; }
        .doc-status-en_attente { background: #fef3c7; color: #92400e; }
        .doc-status-approuve { background: #dcfce7; color: #166534; }
        .doc-status-rejete { background: #fee2e2; color: #991b1b; }
        .conn-list { margin-top: 1rem; }
        .conn-item { color: #64748b; font-size: 0.97rem; margin-bottom: 0.5rem; }
        .param-group { margin-bottom: 1rem; }
        @media (max-width: 700px) { .profil-header { flex-direction: column; gap: 1rem; } }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo"><i class="fas fa-motorcycle"></i> MotoExpress</div>
    <div class="nav-links">
        <a href="moto_taxi_home.php" class="nav-link"><i class="fas fa-home"></i><span>Accueil</span></a>
        <a href="moto_taxi_livraisons.php" class="nav-link"><i class="fas fa-route"></i><span>Livraisons</span></a>
        <a href="moto_taxi_messages.php" class="nav-link"><i class="fas fa-comments"></i><span>Messages</span></a>
        <a href="moto_taxi_gains.php" class="nav-link"><i class="fas fa-wallet"></i><span>Gains</span></a>
        <a href="moto_taxi_profil.php" class="nav-link active"><i class="fas fa-user"></i><span>Profil</span></a>
        <a href="moto_taxi_logout.php" class="logout">Déconnexion</a>
    </div>
</nav>
<div class="main-content">
    <?php if($success): ?><div class="msg-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if($error): ?><div class="msg-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="profil-header">
        <img src="<?php echo $moto['photo_profil'] ? htmlspecialchars($moto['photo_profil']) : 'https://ui-avatars.com/api/?name='.urlencode($moto['prenom'].' '.$moto['nom']).'&background=2563eb&color=fff&size=128'; ?>" class="avatar" alt="Photo de profil">
        <div class="profil-info">
            <h2><?php echo htmlspecialchars($moto['prenom'].' '.$moto['nom']); ?></h2>
            <span class="stat"><i class="fas fa-calendar"></i> Inscrit le <?php echo date('d/m/Y', strtotime($moto['date_inscription'])); ?></span>
            <span class="stat"><i class="fas fa-motorcycle"></i> Livraisons : <?php echo intval($stats['nb']); ?></span>
            <span class="stat"><i class="fas fa-star"></i> Note : <?php echo number_format($stats['note'],1); ?>/5</span>
        </div>
    </div>
    <div class="section">
        <div class="section-title"><i class="fas fa-user"></i> Informations personnelles</div>
        <form method="post" action="update_moto_taxi_profil.php" enctype="multipart/form-data">
            <div class="form-group"><label class="form-label">Nom</label><input type="text" name="nom" class="form-input" value="<?php echo htmlspecialchars($moto['nom']); ?>" required></div>
            <div class="form-group"><label class="form-label">Prénom</label><input type="text" name="prenom" class="form-input" value="<?php echo htmlspecialchars($moto['prenom']); ?>" required></div>
            <div class="form-group"><label class="form-label">Téléphone</label><input type="tel" name="telephone" class="form-input" value="<?php echo htmlspecialchars($moto['telephone']); ?>" required></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($moto['email']); ?>"></div>
            <div class="form-group"><label class="form-label">Photo de profil</label><input type="file" name="photo_profil" class="form-input"></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
    </div>
    <div class="section">
        <div class="section-title"><i class="fas fa-lock"></i> Changer le mot de passe</div>
        <form method="post" action="update_moto_taxi_password.php">
            <div class="form-group"><label class="form-label">Mot de passe actuel</label><input type="password" name="old_password" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Nouveau mot de passe</label><input type="password" name="new_password" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Confirmer le nouveau mot de passe</label><input type="password" name="confirm_password" class="form-input" required></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Changer</button>
        </form>
    </div>
    <div class="section">
        <div class="section-title"><i class="fas fa-motorcycle"></i> Informations moto</div>
        <div class="form-group"><label class="form-label">Numéro de permis</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($moto['numero_permis']); ?>" readonly></div>
        <div class="form-group"><label class="form-label">Numéro d'immatriculation</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($moto['numero_immatriculation']); ?>" readonly></div>
        <div class="form-group"><label class="form-label">Marque</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($moto['marque_moto']); ?>" readonly></div>
        <div class="form-group"><label class="form-label">Modèle</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($moto['modele_moto']); ?>" readonly></div>
        <div class="form-group"><label class="form-label">Année</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($moto['annee_moto']); ?>" readonly></div>
        <div class="form-group"><label class="form-label">Couleur</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($moto['couleur_moto']); ?>" readonly></div>
    </div>
    <div class="section">
        <div class="section-title"><i class="fas fa-file-alt"></i> Documents</div>
        <div class="docs-grid">
            <?php foreach(['permis_conduire'=>'Permis','carte_grise'=>'Carte grise','assurance'=>'Assurance','carte_identite'=>'CNI'] as $type=>$label): ?>
                <div class="doc-card">
                    <div class="doc-title"><?php echo $label; ?></div>
                    <?php if(isset($docs[$type])): ?>
                        <a href="<?php echo htmlspecialchars($docs[$type]['chemin_fichier']); ?>" class="doc-link" target="_blank"><i class="fas fa-download"></i> Voir</a>
                        <div class="doc-status doc-status-<?php echo $docs[$type]['statut']; ?>"><?php echo ucfirst($docs[$type]['statut']); ?></div>
                    <?php else: ?>
                        <form method="post" action="upload_moto_taxi_doc.php" enctype="multipart/form-data">
                            <input type="hidden" name="type_document" value="<?php echo $type; ?>">
                            <input type="file" name="document" class="form-input" required>
                            <button type="submit" class="btn btn-secondary" style="margin-top:0.5rem;"><i class="fas fa-upload"></i> Ajouter</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="section">
        <div class="section-title"><i class="fas fa-history"></i> Connexions récentes</div>
        <div class="conn-list">
            <?php foreach($connexions as $c): ?>
                <div class="conn-item"><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($c['date_connexion'])); ?> | IP: <?php echo htmlspecialchars($c['ip_address']); ?> | <?php echo ucfirst($c['action']); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="section">
        <div class="section-title"><i class="fas fa-cog"></i> Paramètres</div>
        <form method="post" action="update_moto_taxi_settings.php">
            <div class="param-group"><label><input type="checkbox" name="notifications" value="1" <?php if($moto['notifications']??1) echo 'checked'; ?>> Notifications</label></div>
            <div class="param-group"><label>Langue <select name="langue" class="form-select"><option value="fr"<?php if(($moto['langue']??'fr')==='fr')echo ' selected';?>>Français</option><option value="en"<?php if(($moto['langue']??'fr')==='en')echo ' selected';?>>English</option></select></label></div>
            <div class="param-group"><label><input type="checkbox" name="mode_sombre" value="1" <?php if($moto['mode_sombre']??0) echo 'checked'; ?>> Mode sombre</label></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
    </div>
</div>
</body>
</html> 