<?php
session_start();
require_once 'db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Tous les champs obligatoires doivent être remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = "Un administrateur avec cette adresse email existe déjà.";
        } else {
            try {
                // Hasher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Convertir les permissions en JSON
                $permissions_json = json_encode($permissions);
                
                // Insérer le nouvel admin
                $stmt = $pdo->prepare("
                    INSERT INTO admins (name, email, password, role, permissions, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([$name, $email, $hashed_password, $role, $permissions_json]);
                
                $message = "Administrateur créé avec succès !";
                
                // Réinitialiser le formulaire
                $_POST = array();
                
            } catch (PDOException $e) {
                $error = "Erreur lors de la création de l'administrateur : " . $e->getMessage();
            }
        }
    }
}

// Récupérer les informations de l'admin connecté
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Administrateur - MotoTaxi</title>
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

        .form-card {
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
            padding: 2rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .permission-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .permission-item:hover {
            border-color: var(--secondary-color);
            background: #e3f2fd;
        }

        .form-check-input:checked + .form-check-label {
            color: var(--secondary-color);
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .strength-weak { color: var(--danger-color); }
        .strength-medium { color: var(--warning-color); }
        .strength-strong { color: var(--success-color); }

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

            .permissions-grid {
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

        <!-- Form Card -->
        <div class="form-card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-user-plus"></i>
                    Créer un Nouvel Administrateur
                </h4>
            </div>
            
            <div class="card-body">
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

                <form method="POST" action="">
                    <div class="row">
                        <!-- Informations de base -->
                        <div class="col-md-6">
                            <h5 class="mb-3 text-primary">
                                <i class="fas fa-user"></i>
                                Informations Personnelles
                            </h5>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <i class="fas fa-user"></i> Nom complet *
                                </label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Adresse email *
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">
                                    <i class="fas fa-user-tag"></i> Rôle *
                                </label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Sélectionner un rôle</option>
                                    <option value="super_admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'super_admin') ? 'selected' : ''; ?>>
                                        Super Administrateur
                                    </option>
                                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>
                                        Administrateur
                                    </option>
                                    <option value="moderator" <?php echo (isset($_POST['role']) && $_POST['role'] === 'moderator') ? 'selected' : ''; ?>>
                                        Modérateur
                                    </option>
                                    <option value="support" <?php echo (isset($_POST['role']) && $_POST['role'] === 'support') ? 'selected' : ''; ?>>
                                        Support Client
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Sécurité -->
                        <div class="col-md-6">
                            <h5 class="mb-3 text-primary">
                                <i class="fas fa-shield-alt"></i>
                                Sécurité
                            </h5>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Mot de passe *
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required minlength="6">
                                <div class="password-strength" id="password-strength"></div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock"></i> Confirmer le mot de passe *
                                </label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required minlength="6">
                                <div class="password-strength" id="confirm-strength"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div class="mt-4">
                        <h5 class="mb-3 text-primary">
                            <i class="fas fa-key"></i>
                            Permissions
                        </h5>
                        
                        <div class="permissions-grid">
                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_users" 
                                           name="permissions[]" value="manage_users"
                                           <?php echo (isset($_POST['permissions']) && in_array('manage_users', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_users">
                                        <i class="fas fa-users"></i>
                                        <strong>Gestion des Utilisateurs</strong>
                                        <br>
                                        <small class="text-muted">Créer, modifier, supprimer des utilisateurs</small>
                                    </label>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_drivers" 
                                           name="permissions[]" value="manage_drivers"
                                           <?php echo (isset($_POST['permissions']) && in_array('manage_drivers', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_drivers">
                                        <i class="fas fa-motorcycle"></i>
                                        <strong>Gestion des Moto-taxis</strong>
                                        <br>
                                        <small class="text-muted">Valider, suspendre, gérer les chauffeurs</small>
                                    </label>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_deliveries" 
                                           name="permissions[]" value="manage_deliveries"
                                           <?php echo (isset($_POST['permissions']) && in_array('manage_deliveries', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_deliveries">
                                        <i class="fas fa-shipping-fast"></i>
                                        <strong>Gestion des Livraisons</strong>
                                        <br>
                                        <small class="text-muted">Suivre, modifier, annuler les livraisons</small>
                                    </label>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_transactions" 
                                           name="permissions[]" value="manage_transactions"
                                           <?php echo (isset($_POST['permissions']) && in_array('manage_transactions', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_transactions">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <strong>Gestion des Transactions</strong>
                                        <br>
                                        <small class="text-muted">Voir, valider, annuler les transactions</small>
                                    </label>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_reports" 
                                           name="permissions[]" value="view_reports"
                                           <?php echo (isset($_POST['permissions']) && in_array('view_reports', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_reports">
                                        <i class="fas fa-chart-bar"></i>
                                        <strong>Rapports et Statistiques</strong>
                                        <br>
                                        <small class="text-muted">Accéder aux rapports et analyses</small>
                                    </label>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_settings" 
                                           name="permissions[]" value="manage_settings"
                                           <?php echo (isset($_POST['permissions']) && in_array('manage_settings', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_settings">
                                        <i class="fas fa-cog"></i>
                                        <strong>Paramètres Système</strong>
                                        <br>
                                        <small class="text-muted">Modifier les paramètres de la plateforme</small>
                                    </label>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_admins" 
                                           name="permissions[]" value="manage_admins"
                                           <?php echo (isset($_POST['permissions']) && in_array('manage_admins', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_admins">
                                        <i class="fas fa-user-shield"></i>
                                        <strong>Gestion des Administrateurs</strong>
                                        <br>
                                        <small class="text-muted">Créer, modifier, supprimer des admins</small>
                                    </label>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_support" 
                                           name="permissions[]" value="support_access"
                                           <?php echo (isset($_POST['permissions']) && in_array('support_access', $_POST['permissions'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_support">
                                        <i class="fas fa-headset"></i>
                                        <strong>Support Client</strong>
                                        <br>
                                        <small class="text-muted">Accéder aux tickets de support</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="mt-4 text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus"></i>
                            Créer l'Administrateur
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation du mot de passe en temps réel
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('password-strength');
        const confirmStrength = document.getElementById('confirm-strength');

        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = '';

            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            if (strength < 3) {
                feedback = '<span class="strength-weak">Faible</span>';
            } else if (strength < 5) {
                feedback = '<span class="strength-medium">Moyen</span>';
            } else {
                feedback = '<span class="strength-strong">Fort</span>';
            }

            return feedback;
        }

        function checkPasswordMatch() {
            const passwordValue = password.value;
            const confirmValue = confirmPassword.value;

            if (confirmValue && passwordValue !== confirmValue) {
                confirmStrength.innerHTML = '<span class="strength-weak">Les mots de passe ne correspondent pas</span>';
            } else if (confirmValue) {
                confirmStrength.innerHTML = '<span class="strength-strong">Les mots de passe correspondent</span>';
            } else {
                confirmStrength.innerHTML = '';
            }
        }

        password.addEventListener('input', function() {
            passwordStrength.innerHTML = checkPasswordStrength(this.value);
            if (confirmPassword.value) {
                checkPasswordMatch();
            }
        });

        confirmPassword.addEventListener('input', checkPasswordMatch);

        // Auto-sélection des permissions selon le rôle
        document.getElementById('role').addEventListener('change', function() {
            const role = this.value;
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            
            // Décocher toutes les permissions
            checkboxes.forEach(cb => cb.checked = false);
            
            // Sélectionner les permissions selon le rôle
            switch(role) {
                case 'super_admin':
                    checkboxes.forEach(cb => cb.checked = true);
                    break;
                case 'admin':
                    ['manage_users', 'manage_drivers', 'manage_deliveries', 'manage_transactions', 'view_reports'].forEach(perm => {
                        const cb = document.querySelector(`input[value="${perm}"]`);
                        if (cb) cb.checked = true;
                    });
                    break;
                case 'moderator':
                    ['manage_drivers', 'manage_deliveries', 'view_reports', 'support_access'].forEach(perm => {
                        const cb = document.querySelector(`input[value="${perm}"]`);
                        if (cb) cb.checked = true;
                    });
                    break;
                case 'support':
                    ['support_access', 'view_reports'].forEach(perm => {
                        const cb = document.querySelector(`input[value="${perm}"]`);
                        if (cb) cb.checked = true;
                    });
                    break;
            }
        });
    </script>
</body>
</html> 