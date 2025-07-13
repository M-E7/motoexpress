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

// Récupérer l'historique des livraisons
$stmt = $pdo->prepare("
    SELECT l.*, 
           m.nom as mototaxi_nom,
           m.telephone as mototaxi_telephone,
           e.ponctualite, e.etat_colis, e.politesse, e.commentaire,
           (e.ponctualite + e.etat_colis + e.politesse) / 3 as note_moyenne
    FROM livraisons l
    LEFT JOIN moto_taxis m ON l.moto_taxi_id = m.id
    LEFT JOIN evaluations e ON l.id = e.livraison_id
    WHERE l.user_id = ?
    ORDER BY l.date_creation DESC
");
$stmt->execute([$_SESSION['user_id']]);
$livraisons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - MotoExpress</title>
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
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-title { font-size: 1.8rem; font-weight: 600; color: #1e293b; }
        .filters { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .filter-btn { background: #fff; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .filter-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
        .filter-btn:hover { background: #f8fafc; }
        .filter-btn.active:hover { background: #1e40af; }
        .livraison-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.5rem; margin-bottom: 1.5rem; transition: box-shadow 0.2s; }
        .livraison-card:hover { box-shadow: 0 4px 12px #c7d2fe; }
        .livraison-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .livraison-id { font-weight: 600; color: #2563eb; }
        .livraison-date { color: #64748b; font-size: 0.9rem; }
        .livraison-status { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-livree { background: #dcfce7; color: #16a34a; }
        .status-encours { background: #dbeafe; color: #2563eb; }
        .status-en_attente { background: #fef3c7; color: #d97706; }
        .status-annulee { background: #fee2e2; color: #dc2626; }
        .livraison-details { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem; }
        .detail-group { }
        .detail-label { color: #64748b; font-size: 0.9rem; margin-bottom: 0.3rem; }
        .detail-value { font-weight: 500; }
        .livraison-actions { display: flex; gap: 1rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 500; cursor: pointer; transition: background 0.2s; border: none; font-size: 0.9rem; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .evaluation-section { background: #f8fafc; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .evaluation-stars { color: #fbbf24; font-size: 1.2rem; }
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; color: #cbd5e1; }
        .stats-bar { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .stat-item { background: #fff; padding: 1rem; border-radius: 8px; flex: 1; text-align: center; box-shadow: 0 2px 4px #e2e8f0; }
        .stat-number { font-size: 1.5rem; font-weight: bold; color: #2563eb; }
        .stat-label { color: #64748b; font-size: 0.9rem; }
        @media (max-width: 768px) { 
            .livraison-details { grid-template-columns: 1fr; }
            .filters { flex-wrap: wrap; }
            .page-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
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
            <a href="historique.php" class="nav-link active">
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
    <!-- En-tête de page -->
    <div class="page-header">
        <div>
            <div class="welcome">
                <img src="<?php echo $photo; ?>" alt="Photo de profil" class="avatar">
                <div class="msg">Historique des livraisons</div>
            </div>
        </div>
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number" id="totalLivraisons"><?php echo count($livraisons); ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="livraisonsLivrees"><?php echo count(array_filter($livraisons, fn($l) => in_array($l['statut'], ['livree', 'terminee']))); ?></div>
                <div class="stat-label">Livrées</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="livraisonsEnCours"><?php echo count(array_filter($livraisons, fn($l) => $l['statut'] === 'en_cours')); ?></div>
                <div class="stat-label">En cours</div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters">
        <button class="filter-btn active" onclick="filterLivraisons('all')">Toutes</button>
        <button class="filter-btn" onclick="filterLivraisons('terminee')">Livrées</button>
        <button class="filter-btn" onclick="filterLivraisons('en_cours')">En cours</button>
        <button class="filter-btn" onclick="filterLivraisons('en_attente')">En attente</button>
        <button class="filter-btn" onclick="filterLivraisons('annulee')">Annulées</button>
    </div>

    <!-- Liste des livraisons -->
    <div id="livraisonsList">
        <?php if (empty($livraisons)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucune livraison trouvée</h3>
                <p>Vous n'avez pas encore effectué de livraison ou de retrait.</p>
                <a href="user_home.php" class="btn btn-primary">Commander maintenant</a>
            </div>
        <?php else: ?>
            <?php foreach ($livraisons as $livraison): ?>
                <div class="livraison-card" data-status="<?php echo $livraison['statut']; ?>">
                    <div class="livraison-header">
                        <div>
                            <span class="livraison-id">#<?php echo $livraison['id']; ?></span>
                            <span class="livraison-date"><?php echo date('d/m/Y H:i', strtotime($livraison['date_creation'])); ?></span>
                        </div>
                        <span class="livraison-status status-<?php echo $livraison['statut']; ?>">
                            <?php 
                            $statusLabels = [
                                'en_attente' => 'En attente',
                                'en_cours' => 'En cours',
                                'livree' => 'Livrée',
                                'annulee' => 'Annulée'
                            ];
                            echo $statusLabels[$livraison['statut']] ?? $livraison['statut'];
                            ?>
                        </span>
                    </div>
                    
                    <div class="livraison-details">
                        <div class="detail-group">
                            <div class="detail-label">Adresse de départ</div>
                            <div class="detail-value"><?php echo htmlspecialchars($livraison['adresse_depart']); ?></div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Adresse d'arrivée</div>
                            <div class="detail-value"><?php echo htmlspecialchars($livraison['adresse_arrivee']); ?></div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Distance</div>
                            <div class="detail-value"><?php echo number_format($livraison['distance_km'], 1); ?> km</div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-value"><?php echo number_format($livraison['montant'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                    </div>

                    <?php if ($livraison['mototaxi_nom']): ?>
                        <div class="detail-group" style="margin-bottom: 1rem;">
                            <div class="detail-label">Moto-taxi</div>
                            <div class="detail-value"><?php echo htmlspecialchars($livraison['mototaxi_nom']); ?> (<?php echo $livraison['mototaxi_telephone']; ?>)</div>
                        </div>
                    <?php endif; ?>

                    <?php if ($livraison['note_moyenne']): ?>
                        <div class="evaluation-section">
                            <div class="detail-label">Votre évaluation</div>
                            <div class="evaluation-stars">
                                <?php 
                                $note = round($livraison['note_moyenne']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $note ? '★' : '☆';
                                }
                                ?>
                                <span style="color: #64748b; margin-left: 0.5rem;">(<?php echo number_format($livraison['note_moyenne'], 1); ?>)</span>
                            </div>
                            <?php if ($livraison['commentaire']): ?>
                                <div style="margin-top: 0.5rem; font-style: italic;">"<?php echo htmlspecialchars($livraison['commentaire']); ?>"</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="livraison-actions">
                        <button class="btn btn-secondary" onclick="showLivraisonDetails(<?php echo $livraison['id']; ?>)">
                            <i class="fas fa-eye"></i> Détails
                        </button>
                        <?php if ($livraison['statut'] === 'livree' && !$livraison['note_moyenne']): ?>
                            <button class="btn btn-success" onclick="evaluateLivraison(<?php echo $livraison['id']; ?>)">
                                <i class="fas fa-star"></i> Évaluer
                            </button>
                        <?php endif; ?>
                        <?php if ($livraison['statut'] === 'en_cours'): ?>
                            <button class="btn btn-primary" onclick="contactMototaxi(<?php echo $livraison['id']; ?>)">
                                <i class="fas fa-phone"></i> Contacter
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function filterLivraisons(status) {
    // Mettre à jour les boutons de filtre
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filtrer les livraisons
    const cards = document.querySelectorAll('.livraison-card');
    cards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Mettre à jour les statistiques
    updateStats(status);
}

function updateStats(filterStatus) {
    const cards = document.querySelectorAll('.livraison-card');
    let total = 0, livrees = 0, enCours = 0;
    
    cards.forEach(card => {
        if (filterStatus === 'all' || card.dataset.status === filterStatus) {
            total++;
            if (card.dataset.status === 'livree' || card.dataset.status === 'terminee') livrees++;
            if (card.dataset.status === 'en_cours') enCours++;
        }
    });
    
    document.getElementById('totalLivraisons').textContent = total;
    document.getElementById('livraisonsLivrees').textContent = livrees;
    document.getElementById('livraisonsEnCours').textContent = enCours;
}

function showLivraisonDetails(livraisonId) {
    // Ouvrir une modal ou rediriger vers une page de détails
    alert('Détails de la livraison #' + livraisonId + ' - Fonctionnalité à implémenter');
}

function evaluateLivraison(livraisonId) {
    // Ouvrir une modal d'évaluation
    alert('Évaluation de la livraison #' + livraisonId + ' - Fonctionnalité à implémenter');
}

function contactMototaxi(livraisonId) {
    // Ouvrir le chat ou appeler le moto-taxi
    alert('Contact du moto-taxi pour la livraison #' + livraisonId + ' - Fonctionnalité à implémenter');
}
</script>
</body>
</html> 