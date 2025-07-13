<?php
session_start();
require_once 'db.php';

// Vérifier s'il existe déjà un superadmin
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'");
$stmt->execute();
$superadmin_exists = $stmt->fetchColumn() > 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$superadmin_exists) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$nom || !$prenom || !$login || !$email || !$telephone || !$password || !$confirm) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Vérifier si le login existe déjà
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            $error = "Ce login existe déjà.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $permissions = json_encode([
                'all_permissions' => true,
                'manage_users' => true,
                'manage_drivers' => true,
                'manage_admins' => true,
                'view_reports' => true,
                'manage_settings' => true,
                'manage_transactions' => true
            ]);
            $stmt = $pdo->prepare("INSERT INTO admins (nom, prenom, login, mot_de_passe, email, telephone, role, permissions, statut, date_creation) VALUES (?, ?, ?, ?, ?, ?, 'super_admin', ?, 'actif', NOW())");
            $stmt->execute([$nom, $prenom, $login, $hash, $email, $telephone, $permissions]);
            $success = "Super administrateur créé avec succès. Vous pouvez maintenant vous connecter.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Super Administrateur - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { min-height: 100vh; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .register-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(37,99,235,0.15); max-width: 450px; width: 100%; padding: 2.5rem 2rem 2rem 2rem; display: flex; flex-direction: column; align-items: center; }
        .logo { font-size: 2.5rem; color: #2563eb; margin-bottom: 0.5rem; }
        .title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; font-size: 1rem; margin-bottom: 2rem; }
        .form-group { width: 100%; margin-bottom: 1.2rem; }
        .form-label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500; }
        .form-input { width: 100%; padding: 1rem; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 1rem; transition: border-color 0.3s; }
        .form-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.08); }
        .btn { width: 100%; padding: 1rem; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; transition: background 0.2s, transform 0.2s; }
        .btn:hover { background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(37,99,235,0.10); }
        .error { background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; border: 1px solid #fecaca; width: 100%; text-align: center; }
        .success { background: #dcfce7; color: #166534; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; border: 1px solid #bbf7d0; width: 100%; text-align: center; }
        .footer { margin-top: 2rem; color: #64748b; font-size: 0.95rem; text-align: center; }
        @media (max-width: 500px) { .register-card { padding: 1.5rem 0.5rem; } }
    </style>
</head>
<body>
    <form class="register-card" method="post">
        <div class="logo"><i class="fas fa-user-shield"></i></div>
        <div class="title">Inscription Super Administrateur</div>
        <div class="subtitle">MotoExpress - Espace sécurisé</div>
        <?php if ($superadmin_exists): ?>
            <div class="error"><i class="fas fa-exclamation-triangle"></i> Un super administrateur existe déjà.<br>Veuillez contacter le support si besoin.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><br><a href="admin_login.php" style="color:#2563eb;text-decoration:underline;">Aller à la connexion</a></div>
        <?php endif; ?>
        <?php if (!$superadmin_exists && !$success): ?>
        <div class="form-group">
            <label class="form-label">Nom</label>
            <input type="text" name="nom" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Prénom</label>
            <input type="text" name="prenom" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Login</label>
            <input type="text" name="login" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label">Confirmer le mot de passe</label>
            <input type="password" name="confirm" class="form-input" required>
        </div>
        <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Créer le super administrateur</button>
        <?php endif; ?>
        <div class="footer">&copy; <?php echo date('Y'); ?> MotoExpress. Tous droits réservés.</div>
    </form>
</body>
</html> 