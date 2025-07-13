<?php
session_start();
require_once 'db.php';

// Rediriger si déjà connecté
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$login || !$password) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $stmt = $pdo->prepare("SELECT id, nom, prenom, login, mot_de_passe FROM admins WHERE login = ?");
        $stmt->execute([$login]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['mot_de_passe'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nom'] = $admin['nom'];
            $_SESSION['admin_prenom'] = $admin['prenom'];
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = "Identifiants incorrects.";
        }
    }
}

// Vérifier s'il existe déjà un superadmin
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'");
$stmt->execute();
$superadmin_exists = $stmt->fetchColumn() > 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(37,99,235,0.15);
            max-width: 400px;
            width: 100%;
            padding: 2.5rem 2rem 2rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            animation: fadeIn 1s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            font-size: 2.5rem;
            color: #2563eb;
            margin-bottom: 0.5rem;
            animation: pop 0.7s;
        }
        @keyframes pop {
            0% { transform: scale(0.7); }
            80% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        .form-group {
            width: 100%;
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }
        .form-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }
        .input-group {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        .input-with-icon {
            padding-left: 3rem;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            transition: background 0.2s, transform 0.2s;
        }
        .btn:hover {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37,99,235,0.10);
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
            width: 100%;
            text-align: center;
        }
        .footer {
            margin-top: 2rem;
            color: #64748b;
            font-size: 0.95rem;
            text-align: center;
        }
        @media (max-width: 500px) {
            .login-card { padding: 1.5rem 0.5rem; }
        }
    </style>
</head>
<body>
    <form class="login-card" method="post">
        <div class="logo"><i class="fas fa-user-shield"></i></div>
        <div class="title">Espace Administrateur</div>
        <div class="subtitle">MotoExpress Dashboard</div>
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">Identifiant</label>
            <div class="input-group">
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="login" class="form-input input-with-icon" placeholder="Nom d'utilisateur" required autofocus>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Mot de passe</label>
            <div class="input-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" class="form-input input-with-icon" placeholder="Mot de passe" required>
            </div>
        </div>
        <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Se connecter</button>
        <?php if (!$superadmin_exists): ?>
        <div style="margin-top:1rem;text-align:center;">
            <a href="superadmin_register.php" style="color:#2563eb;text-decoration:underline;font-size:0.98rem;">
                <i class="fas fa-user-plus"></i> Créer un super administrateur
            </a>
        </div>
        <?php endif; ?>
        <div class="footer">&copy; <?php echo date('Y'); ?> MotoExpress. Tous droits réservés.</div>
    </form>
</body>
</html> 