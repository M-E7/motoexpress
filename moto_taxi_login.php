<?php
session_start();
require_once 'db.php';

// Rediriger si déjà connecté
if (isset($_SESSION['moto_taxi_id'])) {
    header('Location: moto_taxi_home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telephone = trim($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$telephone || !$password) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // Vérifier les identifiants
        $stmt = $pdo->prepare("SELECT id, nom, prenom, telephone, mot_de_passe, statut, solde, photo_profil, points_fidelite FROM moto_taxis WHERE telephone = ?");
        $stmt->execute([$telephone]);
        $moto_taxi = $stmt->fetch();
        
        if ($moto_taxi && password_verify($password, $moto_taxi['mot_de_passe'])) {
            if ($moto_taxi['statut'] === 'actif') {
                // Connexion réussie
                $_SESSION['moto_taxi_id'] = $moto_taxi['id'];
                $_SESSION['moto_taxi_nom'] = $moto_taxi['nom'];
                $_SESSION['moto_taxi_prenom'] = $moto_taxi['prenom'];
                $_SESSION['moto_taxi_telephone'] = $moto_taxi['telephone'];
                
                // Enregistrer la connexion
                $stmt = $pdo->prepare("INSERT INTO historique_connexions (user_id, type_user, date_connexion, ip_address) VALUES (?, 'moto_taxi', NOW(), ?)");
                $stmt->execute([$moto_taxi['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
                
                header('Location: moto_taxi_home.php');
                exit;
            } else {
                $error = "Votre compte est suspendu. Contactez l'administrateur.";
            }
        } else {
            $error = "Numéro de téléphone ou mot de passe incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Moto-Taxi - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        .logo {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .form-container {
            padding: 2rem;
        }
        .form-group {
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
            transition: all 0.3s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            margin-top: 1rem;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
        }
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .features {
            background: #f8fafc;
            padding: 1.5rem;
            text-align: center;
        }
        .features h3 {
            color: #374151;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .feature-list {
            list-style: none;
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .feature-list li {
            margin-bottom: 0.5rem;
        }
        .feature-list i {
            color: #2563eb;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-motorcycle"></i>
            </div>
            <div class="title">MotoExpress</div>
            <div class="subtitle">Espace Moto-Taxi</div>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label class="form-label">Numéro de téléphone</label>
                    <div class="input-group">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="telephone" class="form-input input-with-icon" 
                               placeholder="Ex: 237612345678" required 
                               value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mot de passe</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" class="form-input input-with-icon" 
                               placeholder="Votre mot de passe" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>
            
            <div class="divider">
                <span>ou</span>
            </div>
            
            <a href="moto_taxi_register.php" class="btn btn-secondary">
                <i class="fas fa-user-plus"></i> Créer un compte
            </a>
        </div>
        
        <div class="features">
            <h3>Fonctionnalités Moto-Taxi</h3>
            <ul class="feature-list">
                <li><i class="fas fa-check"></i> Recevoir des demandes de livraison</li>
                <li><i class="fas fa-check"></i> Suivi GPS en temps réel</li>
                <li><i class="fas fa-check"></i> Messagerie avec les clients</li>
                <li><i class="fas fa-check"></i> Historique des courses</li>
                <li><i class="fas fa-check"></i> Gestion des gains</li>
                <li><i class="fas fa-check"></i> Évaluations et statistiques</li>
            </ul>
        </div>
    </div>
</body>
</html> 