<?php
session_start();

// Redirection intelligente selon le type d'utilisateur connecté
if (isset($_SESSION['user_id'])) {
    // Utilisateur connecté
    header('Location: user_home.php');
    exit;
} elseif (isset($_SESSION['moto_taxi_id'])) {
    // Moto-taxi connecté
    header('Location: moto_taxi_home.php');
    exit;
} elseif (isset($_SESSION['admin_id'])) {
    // Admin connecté
    header('Location: admin_dashboard.php');
    exit;
} else {
    // Aucun utilisateur connecté - page d'accueil publique
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MotoExpress - Livraison rapide et sécurisée</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #1e293b;
            }

            .container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 24px;
                padding: 3rem;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
                text-align: center;
                max-width: 500px;
                width: 90%;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .logo {
                font-size: 2.5rem;
                font-weight: bold;
                color: #2563eb;
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .logo i {
                animation: bounce 2s infinite;
            }

            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% {
                    transform: translateY(0);
                }
                40% {
                    transform: translateY(-10px);
                }
                60% {
                    transform: translateY(-5px);
                }
            }

            h1 {
                font-size: 1.8rem;
                margin-bottom: 1rem;
                color: #1e293b;
            }

            .subtitle {
                color: #64748b;
                margin-bottom: 2rem;
                font-size: 1.1rem;
            }

            .buttons {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                margin-bottom: 2rem;
            }

            .btn {
                padding: 1rem 2rem;
                border: none;
                border-radius: 12px;
                font-size: 1rem;
                font-weight: 600;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                transition: all 0.3s ease;
                cursor: pointer;
            }

            .btn-primary {
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: white;
                box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
            }

            .btn-secondary {
                background: linear-gradient(135deg, #16a34a, #15803d);
                color: white;
                box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
            }

            .btn-secondary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(22, 163, 74, 0.4);
            }

            .btn-outline {
                background: transparent;
                color: #2563eb;
                border: 2px solid #2563eb;
            }

            .btn-outline:hover {
                background: #2563eb;
                color: white;
                transform: translateY(-2px);
            }

            .features {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
                margin-top: 2rem;
            }

            .feature {
                padding: 1rem;
                background: rgba(37, 99, 235, 0.1);
                border-radius: 12px;
                border: 1px solid rgba(37, 99, 235, 0.2);
            }

            .feature i {
                font-size: 1.5rem;
                color: #2563eb;
                margin-bottom: 0.5rem;
            }

            .feature h3 {
                font-size: 0.9rem;
                margin-bottom: 0.25rem;
                color: #1e293b;
            }

            .feature p {
                font-size: 0.8rem;
                color: #64748b;
            }

            @media (max-width: 480px) {
                .container {
                    padding: 2rem 1.5rem;
                }

                .logo {
                    font-size: 2rem;
                }

                h1 {
                    font-size: 1.5rem;
                }

                .buttons {
                    gap: 0.75rem;
                }

                .btn {
                    padding: 0.875rem 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">
                <i class="fas fa-motorcycle"></i>
                MotoExpress
            </div>
            
            <h1>Livraison rapide et sécurisée</h1>
            <p class="subtitle">Connectez-vous pour accéder à nos services de livraison</p>
            
            <div class="buttons">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-user"></i>
                    Se connecter (Client)
                </a>
                
                <a href="moto_taxi_login.php" class="btn btn-secondary">
                    <i class="fas fa-motorcycle"></i>
                    Espace Moto-taxi
                </a>
                
                <a href="admin_login.php" class="btn btn-outline">
                    <i class="fas fa-cog"></i>
                    Administration
                </a>
            </div>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-bolt"></i>
                    <h3>Rapide</h3>
                    <p>Livraison en moins de 30 min</p>
                </div>
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Sécurisé</h3>
                    <p>Suivi en temps réel</p>
                </div>
                <div class="feature">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Précis</h3>
                    <p>Géolocalisation GPS</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?> 