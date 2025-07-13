<?php
// Fichier de vérification pour le déploiement
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification du Déploiement - MotoExpress</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        h1 { color: #333; text-align: center; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        .check-item { margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Vérification du Déploiement - MotoExpress</h1>
        
        <h2>📋 Informations du Serveur</h2>
        <div class="check-item">
            <strong>Version PHP:</strong> <?php echo phpversion(); ?>
        </div>
        <div class="check-item">
            <strong>Serveur Web:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Non détecté'; ?>
        </div>
        <div class="check-item">
            <strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Non détecté'; ?>
        </div>
        <div class="check-item">
            <strong>URL actuelle:</strong> <?php echo $_SERVER['REQUEST_URI'] ?? 'Non détecté'; ?>
        </div>

        <h2>🔧 Vérifications PHP</h2>
        <?php
        $php_checks = [
            'session' => function_exists('session_start'),
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'json' => extension_loaded('json'),
            'curl' => extension_loaded('curl'),
            'gd' => extension_loaded('gd'),
            'fileinfo' => extension_loaded('fileinfo')
        ];

        foreach ($php_checks as $extension => $loaded) {
            $status = $loaded ? 'success' : 'error';
            $icon = $loaded ? '✅' : '❌';
            echo "<div class='status $status'>$icon Extension $extension: " . ($loaded ? 'Disponible' : 'Manquante') . "</div>";
        }
        ?>

        <h2>📁 Vérifications des Fichiers</h2>
        <?php
        $required_files = [
            'index.php' => 'Point d\'entrée principal',
            'db.php' => 'Configuration de base de données',
            'login.php' => 'Page de connexion utilisateur',
            'moto_taxi_login.php' => 'Page de connexion moto-taxi',
            'admin_login.php' => 'Page de connexion admin',
            'user_home.php' => 'Accueil utilisateur',
            'moto_taxi_home.php' => 'Accueil moto-taxi',
            'admin_dashboard.php' => 'Dashboard admin'
        ];

        foreach ($required_files as $file => $description) {
            $exists = file_exists($file);
            $status = $exists ? 'success' : 'error';
            $icon = $exists ? '✅' : '❌';
            echo "<div class='status $status'>$icon $file ($description): " . ($exists ? 'Présent' : 'Manquant') . "</div>";
        }
        ?>

        <h2>🗄️ Vérification de la Base de Données</h2>
        <?php
        if (file_exists('db.php')) {
            try {
                require_once 'db.php';
                $test_query = $pdo->query('SELECT 1');
                echo "<div class='status success'>✅ Connexion à la base de données: Réussie</div>";
                
                // Vérifier les tables principales
                $tables = ['users', 'moto_taxis', 'livraisons', 'transactions_solde'];
                foreach ($tables as $table) {
                    try {
                        $pdo->query("SELECT COUNT(*) FROM $table LIMIT 1");
                        echo "<div class='status success'>✅ Table $table: Présente</div>";
                    } catch (Exception $e) {
                        echo "<div class='status error'>❌ Table $table: Manquante ou inaccessible</div>";
                    }
                }
            } catch (Exception $e) {
                echo "<div class='status error'>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='status error'>❌ Fichier db.php manquant</div>";
        }
        ?>

        <h2>🔐 Vérifications de Sécurité</h2>
        <?php
        $security_checks = [
            '.htaccess' => file_exists('.htaccess'),
            'uploads/' => is_dir('uploads/') && is_writable('uploads/'),
            'session_secure' => ini_get('session.cookie_httponly'),
            'display_errors' => !ini_get('display_errors')
        ];

        foreach ($security_checks as $check => $result) {
            $status = $result ? 'success' : 'warning';
            $icon = $result ? '✅' : '⚠️';
            echo "<div class='status $status'>$icon $check: " . ($result ? 'OK' : 'À vérifier') . "</div>";
        }
        ?>

        <h2>📱 Test des Fonctionnalités</h2>
        <div class="check-item">
            <a href="index.php" style="color: #007bff; text-decoration: none;">🏠 Tester la page d'accueil</a>
        </div>
        <div class="check-item">
            <a href="login.php" style="color: #007bff; text-decoration: none;">👤 Tester la page de connexion</a>
        </div>
        <div class="check-item">
            <a href="moto_taxi_login.php" style="color: #007bff; text-decoration: none;">🏍️ Tester la connexion moto-taxi</a>
        </div>

        <h2>🚀 Recommandations</h2>
        <div class="status info">
            <strong>Pour un déploiement en production :</strong>
            <ul>
                <li>Activez HTTPS avec un certificat SSL</li>
                <li>Configurez les variables d'environnement</li>
                <li>Désactivez l'affichage des erreurs PHP</li>
                <li>Configurez un système de logs</li>
                <li>Mettez en place un système de sauvegarde</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
            <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?>
        </div>
    </div>
</body>
</html> 