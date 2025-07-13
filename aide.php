<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer les données utilisateur
$stmt = $pdo->prepare("SELECT nom, prenom, solde, email, telephone, photo_profil, points_fidelite FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Points de fidélité depuis la table users
$points = $user['points_fidelite'] ?? 0;

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

// Traitement du formulaire de contact
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'contact_support') {
        $sujet = trim($_POST['sujet'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $urgence = $_POST['urgence'] ?? 'normale';
        
        if (!$sujet || !$description) {
            $message = "Veuillez remplir tous les champs obligatoires.";
            $message_type = 'error';
        } else {
            // Ici on pourrait sauvegarder la demande de support en base
            // Pour l'instant, on simule juste
            $message = "Votre demande de support a été envoyée avec succès ! Nous vous répondrons dans les plus brefs délais.";
            $message_type = 'success';
        }
    }
}

// FAQ organisée par catégories
$faq = [
    'general' => [
        'title' => 'Général',
        'questions' => [
            [
                'question' => 'Qu\'est-ce que MotoExpress ?',
                'reponse' => 'MotoExpress est une plateforme de livraison rapide par moto-taxi. Nous connectons les utilisateurs avec des moto-taxis professionnels pour des livraisons sécurisées et rapides.'
            ],
            [
                'question' => 'Comment fonctionne le service ?',
                'reponse' => '1. Sélectionnez vos points de départ et d\'arrivée sur la carte<br>2. Choisissez un moto-taxi disponible<br>3. Suivez votre livraison en temps réel<br>4. Payez en ligne ou en espèces<br>5. Évaluez le service'
            ],
            [
                'question' => 'Quels types de colis puis-je envoyer ?',
                'reponse' => 'Nous acceptons la plupart des colis : documents, petits paquets, nourriture, etc. Les colis fragiles sont acceptés avec une option spéciale. Certaines restrictions s\'appliquent pour les objets dangereux.'
            ]
        ]
    ],
    'tarifs' => [
        'title' => 'Tarifs et Paiements',
        'questions' => [
            [
                'question' => 'Comment sont calculés les tarifs ?',
                'reponse' => 'Tarif de base : 500 FCFA<br>Coût par km : 200 FCFA<br>Majorations :<br>- Urgence : +50%<br>- Nuit (22h-6h) : +30%<br>- Week-end : +20%<br>- Pluie : +20%<br>- Heures de pointe : +40%'
            ],
            [
                'question' => 'Quels moyens de paiement acceptez-vous ?',
                'reponse' => 'Nous acceptons :<br>- Mobile Money (Orange Money, MTN Mobile Money)<br>- Cartes bancaires<br>- Espèces (au moment de la livraison)<br>- Solde MotoExpress'
            ],
            [
                'question' => 'Comment recharger mon solde ?',
                'reponse' => 'Allez dans votre profil > Solde > Recharger. Choisissez le montant et le moyen de paiement. La recharge est instantanée.'
            ]
        ]
    ],
    'livraison' => [
        'title' => 'Livraison et Suivi',
        'questions' => [
            [
                'question' => 'Combien de temps dure une livraison ?',
                'reponse' => 'Le temps varie selon la distance et le trafic. En moyenne :<br>- 15-30 min pour les livraisons en ville<br>- 30-60 min pour les livraisons inter-quartiers<br>Vous recevez une estimation précise avant confirmation.'
            ],
            [
                'question' => 'Comment suivre ma livraison ?',
                'reponse' => 'Vous pouvez suivre votre livraison en temps réel via :<br>- La carte interactive sur l\'accueil<br>- Les messages avec le moto-taxi<br>- Les notifications push<br>- L\'historique des livraisons'
            ],
            [
                'question' => 'Que faire si ma livraison est en retard ?',
                'reponse' => 'Contactez directement le moto-taxi via la messagerie. En cas de problème persistant, utilisez le support client. Nous remboursons les retards non justifiés.'
            ]
        ]
    ],
    'securite' => [
        'title' => 'Sécurité et Assurance',
        'questions' => [
            [
                'question' => 'Mes colis sont-ils assurés ?',
                'reponse' => 'Oui, tous nos moto-taxis sont assurés. En cas de dommage ou de perte, nous remboursons la valeur déclarée du colis (jusqu\'à 50 000 FCFA).'
            ],
            [
                'question' => 'Comment sont sélectionnés les moto-taxis ?',
                'reponse' => 'Tous nos moto-taxis passent par un processus de vérification rigoureux :<br>- Documents légaux vérifiés<br>- Formation obligatoire<br>- Évaluations continues<br>- Contrôles réguliers'
            ],
            [
                'question' => 'Mes données personnelles sont-elles protégées ?',
                'reponse' => 'Absolument. Nous respectons le RGPD et ne partageons jamais vos données personnelles. Votre localisation n\'est utilisée que pendant la livraison.'
            ]
        ]
    ],
    'problemes' => [
        'title' => 'Résolution de Problèmes',
        'questions' => [
            [
                'question' => 'Mon moto-taxi ne répond pas',
                'reponse' => '1. Vérifiez que vous êtes dans la zone de couverture<br>2. Attendez quelques minutes (délai de réponse : 60 secondes)<br>3. Essayez de relancer la demande<br>4. Contactez le support si le problème persiste'
            ],
            [
                'question' => 'J\'ai reçu un colis endommagé',
                'reponse' => '1. Prenez des photos du colis endommagé<br>2. Refusez la livraison si nécessaire<br>3. Contactez immédiatement le support<br>4. Nous traiterons votre réclamation sous 24h'
            ],
            [
                'question' => 'Je n\'ai pas reçu mon remboursement',
                'reponse' => 'Les remboursements sont traités sous 3-5 jours ouvrables. Vérifiez votre compte bancaire ou mobile money. Contactez le support si le délai est dépassé.'
            ]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aide - MotoExpress</title>
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
        .help-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .help-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.5rem; }
        .card-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .card-header i { font-size: 1.5rem; color: #2563eb; }
        .card-title { font-size: 1.2rem; font-weight: 600; }
        .search-box { width: 100%; padding: 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; margin-bottom: 1rem; }
        .search-box:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .faq-category { margin-bottom: 2rem; }
        .faq-category-title { font-size: 1.1rem; font-weight: 600; color: #374151; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e5e7eb; }
        .faq-item { margin-bottom: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .faq-question { background: #f9fafb; padding: 1rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; }
        .faq-question:hover { background: #f3f4f6; }
        .faq-question.active { background: #dbeafe; }
        .faq-question-text { font-weight: 500; color: #374151; }
        .faq-toggle { color: #6b7280; transition: transform 0.2s; }
        .faq-question.active .faq-toggle { transform: rotate(180deg); }
        .faq-answer { padding: 1rem; background: white; border-top: 1px solid #e5e7eb; display: none; }
        .faq-answer.show { display: block; }
        .contact-form { }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-textarea { resize: vertical; min-height: 120px; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .message.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .quick-action { background: #fff; border-radius: 8px; padding: 1.5rem; text-align: center; box-shadow: 0 2px 4px #e2e8f0; transition: transform 0.2s; cursor: pointer; }
        .quick-action:hover { transform: translateY(-2px); box-shadow: 0 4px 8px #e2e8f0; }
        .quick-action i { font-size: 2rem; color: #2563eb; margin-bottom: 0.5rem; }
        .quick-action-title { font-weight: 600; margin-bottom: 0.5rem; }
        .quick-action-desc { color: #64748b; font-size: 0.9rem; }
        .contact-info { background: #f8fafc; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .contact-item { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .contact-item i { color: #2563eb; width: 20px; }
        .urgent-badge { background: #dc2626; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; margin-left: 0.5rem; }
        .normal-badge { background: #16a34a; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; margin-left: 0.5rem; }
        .low-badge { background: #f59e0b; color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; margin-left: 0.5rem; }
        @media (max-width: 768px) { 
            .help-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
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
            <a href="profil.php" class="nav-link">
                <i class="fas fa-user"></i>
                <span>Profil</span>
            </a>
            <a href="aide.php" class="nav-link help-link active">
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
        <div class="msg">Centre d'aide</div>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Actions rapides -->
    <div class="quick-actions">
        <div class="quick-action" onclick="scrollToSection('faq')">
            <i class="fas fa-question-circle"></i>
            <div class="quick-action-title">FAQ</div>
            <div class="quick-action-desc">Questions fréquentes</div>
        </div>
        <div class="quick-action" onclick="scrollToSection('contact')">
            <i class="fas fa-headset"></i>
            <div class="quick-action-title">Support</div>
            <div class="quick-action-desc">Contacter l'équipe</div>
        </div>
        <div class="quick-action" onclick="window.open('user_home.php', '_blank')">
            <i class="fas fa-map-marked-alt"></i>
            <div class="quick-action-title">Commander</div>
            <div class="quick-action-desc">Nouvelle livraison</div>
        </div>
        <div class="quick-action" onclick="window.open('historique.php', '_blank')">
            <i class="fas fa-clock"></i>
            <div class="quick-action-title">Suivi</div>
            <div class="quick-action-desc">Mes livraisons</div>
        </div>
    </div>

    <div class="help-grid">
        <!-- FAQ -->
        <div class="help-card" id="faq">
            <div class="card-header">
                <i class="fas fa-question-circle"></i>
                <div class="card-title">Questions fréquentes</div>
            </div>
            
            <input type="text" class="search-box" placeholder="Rechercher dans la FAQ..." id="faqSearch">
            
            <?php foreach ($faq as $category => $categoryData): ?>
                <div class="faq-category" data-category="<?php echo $category; ?>">
                    <div class="faq-category-title"><?php echo $categoryData['title']; ?></div>
                    <?php foreach ($categoryData['questions'] as $index => $item): ?>
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFAQ(this)">
                                <div class="faq-question-text"><?php echo htmlspecialchars($item['question']); ?></div>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div class="faq-answer">
                                <?php echo $item['reponse']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Contact Support -->
        <div class="help-card" id="contact">
            <div class="card-header">
                <i class="fas fa-headset"></i>
                <div class="card-title">Support client</div>
            </div>
            
            <form method="post" class="contact-form">
                <input type="hidden" name="action" value="contact_support">
                
                <div class="form-group">
                    <label class="form-label">Sujet <span style="color: red;">*</span></label>
                    <select name="sujet" class="form-select" required>
                        <option value="">Choisir un sujet</option>
                        <option value="probleme_livraison">Problème de livraison</option>
                        <option value="probleme_paiement">Problème de paiement</option>
                        <option value="colis_endommage">Colis endommagé</option>
                        <option value="retard">Retard de livraison</option>
                        <option value="remboursement">Demande de remboursement</option>
                        <option value="compte">Problème de compte</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Niveau d'urgence</label>
                    <select name="urgence" class="form-select">
                        <option value="normale">Normale</option>
                        <option value="urgente">Urgente</option>
                        <option value="faible">Faible</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description détaillée <span style="color: red;">*</span></label>
                    <textarea name="description" class="form-textarea" placeholder="Décrivez votre problème en détail..." required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Envoyer
                </button>
            </form>
            
            <div class="contact-info">
                <h4>Informations de contact</h4>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <span>+237 XXX XXX XXX</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <span>support@motoexpress.com</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <span>Lun-Ven: 8h-18h, Sam: 9h-16h</span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Douala, Cameroun</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Guide d'utilisation -->
    <div class="help-card" style="margin-top: 2rem;">
        <div class="card-header">
            <i class="fas fa-book"></i>
            <div class="card-title">Guide d'utilisation</div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <div>
                <h4 style="color: #2563eb; margin-bottom: 1rem;">1. Créer une livraison</h4>
                <ol style="padding-left: 1.5rem; line-height: 1.6;">
                    <li>Allez sur la page d'accueil</li>
                    <li>Cliquez sur "Nouvelle livraison"</li>
                    <li>Sélectionnez vos points sur la carte</li>
                    <li>Choisissez vos options (urgence, fragile)</li>
                    <li>Confirmez et payez</li>
                </ol>
            </div>
            
            <div>
                <h4 style="color: #2563eb; margin-bottom: 1rem;">2. Suivre sa livraison</h4>
                <ol style="padding-left: 1.5rem; line-height: 1.6;">
                    <li>Utilisez la carte interactive</li>
                    <li>Communiquez avec le moto-taxi</li>
                    <li>Recevez des notifications</li>
                    <li>Consultez l'historique</li>
                </ol>
            </div>
            
            <div>
                <h4 style="color: #2563eb; margin-bottom: 1rem;">3. Gérer son compte</h4>
                <ol style="padding-left: 1.5rem; line-height: 1.6;">
                    <li>Mettez à jour vos informations</li>
                    <li>Rechargez votre solde</li>
                    <li>Consultez vos statistiques</li>
                    <li>Évaluez les services</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle FAQ
function toggleFAQ(element) {
    const answer = element.nextElementSibling;
    const isActive = element.classList.contains('active');
    
    // Fermer toutes les autres questions
    document.querySelectorAll('.faq-question').forEach(q => {
        q.classList.remove('active');
        q.nextElementSibling.classList.remove('show');
    });
    
    // Ouvrir/fermer la question cliquée
    if (!isActive) {
        element.classList.add('active');
        answer.classList.add('show');
    }
}

// Recherche dans la FAQ
document.getElementById('faqSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question-text').textContent.toLowerCase();
        const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
        
        if (question.includes(searchTerm) || answer.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

// Scroll vers une section
function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
}

// Auto-ouverture de la première FAQ
document.addEventListener('DOMContentLoaded', function() {
    const firstFaq = document.querySelector('.faq-question');
    if (firstFaq) {
        firstFaq.click();
    }
});

// Validation du formulaire
document.querySelector('.contact-form').addEventListener('submit', function(e) {
    const sujet = document.querySelector('select[name="sujet"]').value;
    const description = document.querySelector('textarea[name="description"]').value;
    
    if (!sujet || !description.trim()) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires.');
    }
});
</script>
</body>
</html> 