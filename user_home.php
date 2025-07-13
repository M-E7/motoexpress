<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nom, prenom, solde, email, telephone, photo_profil, points_fidelite FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Points de fidélité depuis la table users
$points = $user['points_fidelite'] ?? 0;

// Photo de profil - chargement depuis la base de données avec fallback
$photo = $user['photo_profil'] 
    ? $user['photo_profil'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['prenom'].' '.$user['nom']) . '&background=2563eb&color=fff&size=64';


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; color: #1e293b; margin: 0; }
        [data-theme="dark"] body { background: #1f2937; color: #f1f5f9; }
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07); }
        [data-theme="dark"] .navbar { background: #1f2937; border-bottom: 1px solid #374151; }
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


/* 2. Bouton Néon */
.btn-secondary {
    background: transparent;
    color:rgb(67, 25, 203);
    border: 2px solidrgb(73, 18, 225);
    padding: 16px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    overflow: hidden;
}

.btn-secondary:hover {
    color: #000;
    background:rgb(44, 15, 187);
    box-shadow: 
        0 0 20pxrgb(54, 7, 135),
        0 0 40pxrgb(86, 22, 223),
        0 0 60pxrgb(24, 42, 202);
    text-shadow: 0 0 10px #000;
}

.btn-secondary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent,rgb(30, 33, 210), transparent);
    transition: left 0.5s;
    z-index: -1;
}

.btn-secondary:hover::before {
    left: 100%;
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
.form-control {
            width: 100%;
            padding: 16px 20px;
            font-size: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            background: #fafafa;
            transition: all 0.3s ease;
            font-family: inherit;
            outline: none;
        }

        .main-content { max-width: 1100px; margin: 110px auto 0 auto; padding: 2rem 1rem; }
        .welcome { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .welcome .avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #2563eb; }
        .welcome .msg { font-size: 1.3rem; font-weight: 500; }
        .quick-actions { display: flex; gap: 1.5rem; margin-bottom: 2rem; }
        .quick-action { flex: 1; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.2rem; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: box-shadow 0.2s; border: 2px solid transparent; }
        .quick-action:hover { box-shadow: 0 6px 16px #c7d2fe; border-color: #2563eb; }
        .quick-action i { font-size: 2rem; margin-bottom: 0.5rem; color: #2563eb; }
        .quick-action span { font-weight: 500; }
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        .map-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.5rem; margin-bottom: 2rem; }
        #map { width: 100%; height: 340px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .solde-card, .notif-card, .stats-card, .fidelite-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.2rem; margin-bottom: 1.5rem; }
        .solde-row { display: flex; align-items: center; justify-content: space-between; }
        .solde-row .solde { font-size: 1.5rem; font-weight: bold; color: #16a34a; }
        .solde-row .btn { background: #2563eb; color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.2rem; font-weight: 500; cursor: pointer; transition: background 0.2s; }
        .solde-row .btn:hover { background: #1e40af; }
        .fidelite-points { font-size: 1.2rem; color: #ea580c; font-weight: bold; }
        .notif-list, .history-list { list-style: none; padding: 0; margin: 0; }
        .notif-list li, .history-list li { display: flex; align-items: center; gap: 1rem; padding: 0.7rem 0; border-bottom: 1px solid #e2e8f0; cursor: pointer; }
        .notif-list li:last-child, .history-list li:last-child { border-bottom: none; }
        .notif-icon { font-size: 1.2rem; color: #2563eb; }
        .notif-msg { flex: 1; }
        .notif-date { font-size: 0.8rem; color: #64748b; }
        .history-list .status { font-size: 0.9rem; font-weight: 500; margin-left: 0.5rem; }
        .history-list .status.livree { color: #16a34a; }
        .history-list .status.encours { color: #2563eb; }
        .history-list .status.annulee { color: #dc2626; }
.history-list .icon { font-size: 1.3rem; color: #2563eb; }

.transactions-list { list-style: none; padding: 0; margin: 0; }
.transactions-list li { display: flex; align-items: center; gap: 1rem; padding: 0.7rem 0; border-bottom: 1px solid #e2e8f0; }
.transactions-list li:last-child { border-bottom: none; }
.transaction-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
.transaction-icon.credit { background-color: #dcfce7; color: #16a34a; }
.transaction-icon.debit { background-color: #fee2e2; color: #dc2626; }
.transaction-icon.bonus { background-color: #fef3c7; color: #d97706; }
.transaction-icon.remboursement { background-color: #dbeafe; color: #2563eb; }
        .stats-row { display: flex; gap: 1.5rem; }
        .stat-box { flex: 1; background: #f8fafc; border-radius: 8px; padding: 1rem; text-align: center; }
        .stat-label { color: #64748b; font-size: 0.9rem; }
        .stat-value { font-size: 1.3rem; font-weight: bold; color: #2563eb; }
        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }
        @media (max-width: 600px) { .main-content { padding: 0.5rem; } .quick-actions { flex-direction: column; gap: 1rem; } }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo"><i class="fas fa-motorcycle"></i> MotoExpress</div>
    <div class="nav-links">
        <a href="user_home.php" class="nav-link active"><i class="fas fa-home"></i><span>Accueil</span></a>
        <a href="historique.php" class="nav-link"><i class="fas fa-history"></i><span>Historique</span></a>
        <a href="messages.php" class="nav-link"><i class="fas fa-comments"></i><span>Messages</span></a>
        <a href="profil.php" class="nav-link"><i class="fas fa-user"></i><span>Profil</span></a>
        <a href="aide.php" class="nav-link help-link"><i class="fas fa-question-circle"></i><span>Aide</span></a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>
</nav>
<div class="main-content">
    <!-- 1. Message de bienvenue personnalisé -->
    <div class="welcome">
        <img src="<?php echo $photo; ?>" alt="Photo de profil" class="avatar"
             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['prenom'].' '.$user['nom']); ?>&background=2563eb&color=fff&size=64';">
        <div class="msg">Bienvenue, <?php echo htmlspecialchars($user['prenom']); ?> 👋</div>
    </div>
    <!-- 2. Carte interactive + 3. Actions rapides -->
    <div class="dashboard-grid">
        <div>
            <div class="map-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <div style="font-weight:600;font-size:1.1rem;"><i class="fas fa-map-marked-alt"></i> Carte des moto-taxis</div>
                    <div style="display:flex;gap:1rem;">
                        <button class="quick-action" onclick="showLivraisonForm('livraison')"><i class="fas fa-paper-plane"></i><span>Nouvelle livraison</span></button>
                        <button class="quick-action" onclick="showLivraisonForm('retrait')"><i class="fas fa-box-open"></i><span>Demande de retrait</span></button>
                        <button class="quick-action" onclick="scanQRCode()"><i class="fas fa-qrcode"></i><span>Scanner QR</span></button>
                    </div>
                </div>
                <div id="map"></div>
                <!-- Formulaire livraison/retrait -->
                <div id="livraisonFormContainer" style="display:none;margin-top:2rem;"></div>
            </div>
            <!-- 4. Historique des 3 dernières livraisons -->
            <div class="solde-card">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:0.7rem;"><i class="fas fa-clock"></i> Dernières livraisons</div>
                <ul class="history-list" id="historyList"></ul>
            </div>
            
            <!-- 4.5. Dernières transactions -->
            <div class="solde-card">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:0.7rem;">
                    <i class="fas fa-credit-card"></i> Dernières transactions
                    <div style="display:flex;gap:0.3rem;margin-top:0.5rem;">
                        <button class="btn btn-sm" onclick="filterTransactions('')" style="font-size:0.8rem;padding:0.2rem 0.5rem;">Tous</button>
                        <button class="btn btn-sm" onclick="filterTransactions('credit')" style="font-size:0.8rem;padding:0.2rem 0.5rem;background:#16a34a;color:white;">Crédits</button>
                        <button class="btn btn-sm" onclick="filterTransactions('debit')" style="font-size:0.8rem;padding:0.2rem 0.5rem;background:#dc2626;color:white;">Débits</button>
                    </div>
                </div>
                <div id="transactionsList" class="transactions-list"></div>
                <div id="transactionsStats" style="padding:0.5rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:none;font-size:0.85em;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                        <div><strong>Total crédits:</strong> <span id="totalCredits" style="color:#16a34a;">0 FCFA</span></div>
                        <div><strong>Total débits:</strong> <span id="totalDebits" style="color:#dc2626;">0 FCFA</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <!-- 5. Solde et points fidélité -->
            <div class="solde-card">
                <div class="solde-row">
                    <span><i class="fas fa-wallet"></i> Solde</span>
                    <span class="solde" id="userBalance"><?php echo number_format($user['solde'], 0, ',', ' '); ?> FCFA</span>
                    <button class="btn" onclick="showRechargeModal()">Recharger</button>
                </div>
            </div>
            <div class="fidelite-card">
                <span><i class="fas fa-gift"></i> Points fidélité</span>
                <div class="fidelite-points" id="userPoints"><?php echo (int)$points; ?> pts</div>
            </div>
            <!-- 6. Notifications récentes -->
            <div class="notif-card">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:0.7rem;"><i class="fas fa-bell"></i> Notifications</div>
                <ul class="notif-list" id="notifList"></ul>
            </div>
            <!-- 7. Statistiques personnelles -->
            <div class="stats-card">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:0.7rem;"><i class="fas fa-chart-line"></i> Statistiques</div>
                <div class="stats-row">
                    <div class="stat-box"><div class="stat-value" id="statLivraisons">0</div><div class="stat-label">Livraisons</div></div>
                    <div class="stat-box"><div class="stat-value" id="statDistance">0 km</div><div class="stat-label">Distance</div></div>
                </div>
                <div class="stats-row" style="margin-top:0.7rem;">
                    <div class="stat-box"><div class="stat-value" id="statDepense">0 FCFA</div><div class="stat-label">Dépensé</div></div>
                    <div class="stat-box"><div class="stat-value" id="statNote">5.0</div><div class="stat-label">Note moyenne</div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
let map, userMarker, motoMarkers = [];

function fetchMotoTaxis(callback) {
    fetch('get_moto_taxis.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                callback(res.moto_taxis);
            } else {
                callback([]);
            }
        })
        .catch(() => callback([]));
}

function initMap() {
    map = L.map('map').setView([4.0511, 9.7085], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            const lat = pos.coords.latitude, lon = pos.coords.longitude;
            map.setView([lat, lon], 15);
            userMarker = L.marker([lat, lon], {icon: L.divIcon({html:'<i class=\'fas fa-user-circle\' style=\'color:#2563eb;font-size:2rem;\'></i>',iconSize:[32,32],className:'user-icon'})}).addTo(map).bindPopup('Vous êtes ici');
            showMotoTaxis(lat, lon);
        }, function() { showMotoTaxis(4.0511, 9.7085); });
    } else { showMotoTaxis(4.0511, 9.7085); }
}

function showMotoTaxis(ulat, ulon) {
    fetchMotoTaxis(function(motoTaxis) {
        motoMarkers.forEach(m=>map.removeLayer(m));
        motoMarkers = [];
        motoTaxis.forEach((m, idx) => {
            if (m.lat && m.lon) {
                const marker = L.marker([m.lat, m.lon], {icon: L.divIcon({html:'<i class=\'fas fa-motorcycle\' style=\'color:#16a34a;font-size:1.5rem;\'></i>',iconSize:[24,24],className:'moto-icon'})}).addTo(map).bindPopup(`${m.nom} (${m.note ? m.note + '★' : ''})`);
                marker.on('click', function() {
                    if (document.getElementById('livraisonFormContainer').style.display === 'block') {
                        if (window.selectMotoTaxiById) window.selectMotoTaxiById(m.id);
                    }
                });
                motoMarkers.push(marker);
            }
        });
    });
}

function getDistance(lat1,lon1,lat2,lon2) {
    const R=6371, dLat=(lat2-lat1)*Math.PI/180, dLon=(lon2-lon1)*Math.PI/180, a=Math.sin(dLat/2)*Math.sin(dLat/2)+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2), c=2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
    return R*c;
}
window.onload = function() {
  initMap();
  loadTransactions();
  renderNotif();
  refreshStats();
  refreshHistory();
  refreshSolde();
};
// 4. Historique des 3 dernières livraisons (dynamique)
const historyList = document.getElementById('historyList');
function renderHistory() {
    fetch('get_history.php?limit=3')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            
            historyList.innerHTML = '';
            
            if (res.livraisons.length === 0) {
                historyList.innerHTML = '<li style="text-align:center;color:#64748b;padding:1rem;">Aucune livraison</li>';
                return;
            }
            
            res.livraisons.forEach(h => {
                const li = document.createElement('li');
                const documentsIcon = h.nb_documents > 0 ? `<i class="fas fa-file-alt" style="color:#3b82f6;margin-left:5px;" title="${h.nb_documents} document(s)"></i>` : '';
                
                li.innerHTML = `<span class="icon"><i class="fas fa-box"></i></span>
                    <span>${h.description || 'Colis'} ${documentsIcon}</span>
                    <span class="status ${h.statut}">${h.statut_label}</span>
                    <span style="color:#64748b;font-size:0.9em;">${h.mototaxi_nom || ''}</span>
                    <span style="margin-left:auto;font-size:0.85em;color:#64748b;">${h.date_formatee}</span>`;
                
                li.onclick = () => showLivraisonDetails(h);
                historyList.appendChild(li);
            });
        })
        .catch(() => {
            historyList.innerHTML = '<li style="text-align:center;color:#dc2626;padding:1rem;">Erreur de chargement</li>';
        });
}
// 6. Notifications récentes (dynamiques)
const notifList = document.getElementById('notifList');
function renderNotif() {
    fetch('get_notifications.php?limit=5')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            
            notifList.innerHTML = '';
            
            if (res.notifications.length === 0) {
                notifList.innerHTML = '<li style="text-align:center;color:#64748b;padding:1rem;">Aucune notification</li>';
                return;
            }
            
            res.notifications.forEach(n => {
                const li = document.createElement('li');
                li.innerHTML = `<span class="notif-icon"><i class="${n.icone}"></i></span>
                    <span class="notif-msg">${n.message}</span>
                    <span class="notif-date">${n.date_formatee}</span>`;
                
                // Ajouter une classe si la notification n'est pas lue
                if (!n.lu) {
                    li.style.backgroundColor = '#f0f9ff';
                    li.style.borderLeft = '3px solid #3b82f6';
                }
                
                notifList.appendChild(li);
            });
            
            // Mettre à jour le badge de notifications si nécessaire
            updateNotificationBadge(res.non_lues);
        })
        .catch(() => {
            notifList.innerHTML = '<li style="text-align:center;color:#dc2626;padding:1rem;">Erreur de chargement</li>';
        });
}

// Mettre à jour le badge de notifications
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}
// 7. Statistiques personnelles (dynamiques)
// Les statistiques sont chargées par la fonction refreshStats() au démarrage
// 5. Recharge dynamique
function showRechargeModal() {
  const montant = prompt('Montant à recharger (en FCFA):');
  if (!montant || isNaN(montant) || montant <= 0) {
    alert('Montant invalide');
    return;
  }
  
  const methode = prompt('Méthode de paiement (Orange Money, MTN, Moov):');
  if (!methode) {
    alert('Méthode de paiement requise');
    return;
  }
  
  // Afficher un message de chargement
  const loadingMsg = document.createElement('div');
  loadingMsg.innerHTML = '<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:2rem;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;"><i class="fas fa-spinner fa-spin"></i> Traitement en cours...</div>';
  document.body.appendChild(loadingMsg);
  
  // Envoyer la requête
  const formData = new FormData();
  formData.append('montant', montant);
  formData.append('methode_paiement', methode);
  
  fetch('recharge_solde.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(res => {
    document.body.removeChild(loadingMsg);
    
    if (res.success) {
      alert('Recharge réussie ! Nouveau solde: ' + res.nouveau_solde.toLocaleString() + ' FCFA');
      refreshSolde();
      refreshHistory();
      renderNotif();
    } else {
      alert('Erreur: ' + res.message);
    }
  })
  .catch(() => {
    document.body.removeChild(loadingMsg);
    alert('Erreur de connexion');
  });
}
// 3. Actions rapides
function scanQRCode() {
  // Créer une modal pour le scanner QR
  const modal = document.createElement('div');
  modal.id = 'qrScannerModal';
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
  `;
  
  modal.innerHTML = `
    <div style="background: white; border-radius: 12px; padding: 1.5rem; max-width: 500px; width: 90%; text-align: center;">
      <h3 style="margin-bottom: 1rem; color: #1e293b;">
        <i class="fas fa-qrcode"></i> Scanner QR Code
      </h3>
      
      <div id="qrScannerContainer" style="margin-bottom: 1rem;">
        <video id="qrVideo" style="width: 100%; max-width: 400px; border-radius: 8px; border: 2px solid #e2e8f0;"></video>
        <canvas id="qrCanvas" style="display: none;"></canvas>
      </div>
      
      <div id="qrResult" style="margin-bottom: 1rem; padding: 0.5rem; border-radius: 6px; display: none;"></div>
      
      <div style="display: flex; gap: 1rem; justify-content: center;">
        <button id="startScanBtn" class="btn btn-primary" onclick="startQRScanner()">
          <i class="fas fa-play"></i> Démarrer le scan
        </button>
        <button id="stopScanBtn" class="btn btn-secondary" onclick="stopQRScanner()" style="display: none;">
          <i class="fas fa-stop"></i> Arrêter
        </button>
        <button class="btn btn-secondary" onclick="closeQRScanner()">
          <i class="fas fa-times"></i> Fermer
        </button>
      </div>
      
      <div style="margin-top: 1rem; font-size: 0.9rem; color: #64748b;">
        <i class="fas fa-info-circle"></i> 
        Placez le QR code dans le cadre pour le scanner automatiquement
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  
  // Démarrer automatiquement le scanner
  setTimeout(() => {
    startQRScanner();
  }, 500);
}
// Affichage du formulaire livraison/retrait sous la carte
function showLivraisonForm(type) {
  const container = document.getElementById('livraisonFormContainer');
  let titre = type === 'livraison' ? 'Nouvelle livraison' : 'Demande de retrait';
  container.innerHTML = `
    <div style="background:#fff;padding:1.5rem 1rem;border-radius:12px;box-shadow:0 2px 8px #e2e8f0;max-width:500px;margin:auto;">
      <h2 style="font-size:1.1rem;margin-bottom:1rem;"><i class="fas fa-paper-plane"></i> ${titre}</h2>
      <form id="livraisonForm" enctype="multipart/form-data">
        <div class="form-group">
          <label>Type de livraison</label>
          <input type="text" class="form-control" value="${type === 'livraison' ? 'Livraison' : 'Retrait'}" readonly>
          <input type="hidden" name="type_livraison" value="${type}">
        </div>
        <div class="form-group">
          <label>Point de départ</label>
          <input type="text" id="departAdresse" name="adresse_depart" class="form-control" placeholder="Cliquez sur la carte" readonly required>
          <input type="text" id="departLatVisible" class="form-control" placeholder="Latitude départ (optionnel)" readonly style="margin-top:0.3rem;">
          <input type="hidden" id="departLat" name="latitude_depart">
          <input type="text" id="departLonVisible" class="form-control" placeholder="Longitude départ (optionnel)" readonly style="margin-top:0.3rem;">
          <input type="hidden" id="departLon" name="longitude_depart">
        </div>
        <div class="form-group">
          <label>Point d'arrivée</label>
          <input type="text" id="arriveeAdresse" name="adresse_arrivee" class="form-control" placeholder="Cliquez sur la carte" readonly required>
          <input type="text" id="arriveeLatVisible" class="form-control" placeholder="Latitude arrivée (optionnel)" readonly style="margin-top:0.3rem;">
          <input type="hidden" id="arriveeLat" name="latitude_arrivee">
          <input type="text" id="arriveeLonVisible" class="form-control" placeholder="Longitude arrivée (optionnel)" readonly style="margin-top:0.3rem;">
          <input type="hidden" id="arriveeLon" name="longitude_arrivee">
        </div>
        <div class="form-group">
          <label>Description du colis (optionnel)</label>
          <input type="text" id="description" name="description" class="form-control" maxlength="100" placeholder="Ex: Documents, colis fragile...">
        </div>
        <div class="form-group" style="display:flex;gap:1rem;align-items:center;">
          <label><input type="checkbox" id="cassable" name="cassable"> Cassable</label>
          <label><input type="checkbox" id="urgence" name="urgence"> Urgent (+50%)</label>
        </div>
        ${type === 'retrait' ? `<div class="form-group"><label>Documents justificatifs (PDF, images)</label><input type="file" name="documents[]" id="documents" class="form-control" multiple accept=".pdf,image/*"></div>` : ''}
        <div class="form-group" id="infosLivraison" style="background:#f8fafc;padding:0.7rem 1rem;border-radius:8px;margin-bottom:1rem;display:none;"></div>
        <div class="form-group" id="motoTaxisList" style="display:none;"></div><div id="motoTaxiSelectedRecap"></div>
        <div class="form-group">
          <label>Moto-taxi sélectionné</label>
          <input type="text" name="moto_taxi_nom" id="motoTaxiNomField" class="form-control" placeholder="Sélectionnez un moto-taxi" readonly>
          <input type="hidden" name="moto_taxi_id" id="motoTaxiIdField">
        </div>
        <div class="form-group">
          <button type="submit" class="btn-secondary" style="width:100%;">Valider la demande</button>
        </div>
        <div id="livraisonResult" style="margin-top:1rem;font-size:0.95em;"></div>
      </form>
      <div style="text-align:right;margin-top:0.5rem;"><button onclick="hideLivraisonForm()" class="btn btn-secondary">Annuler</button></div>
    </div>
  `;
  container.style.display = 'block';
  window.livraisonFormType = type;
  bindLivraisonFormEvents();
}
function hideLivraisonForm() {
  document.getElementById('livraisonFormContainer').style.display = 'none';
}
function bindLivraisonFormEvents() {
  let selectionType = null;
  let depart = null, arrivee = null;
  let meteo = Math.random() < 0.2 ? 'pluie' : 'clair';
  let traffic = 1 + Math.random() * 0.5;
  let mototaxisDyn = [];
  let selectedMoto = null;
  let motoMarkersDyn = [];
  const btnValider = document.querySelector('#livraisonForm button[type="submit"]');
  btnValider.disabled = true;

  document.getElementById('departAdresse').onclick = function() { selectionType = 'depart'; };
  document.getElementById('arriveeAdresse').onclick = function() { selectionType = 'arrivee'; };
  map.on('click', function(e) {
    if (document.getElementById('livraisonFormContainer').style.display !== 'block') return;
    if (!selectionType) return;
    const lat = e.latlng.lat, lon = e.latlng.lng;
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`)
      .then(r=>r.json())
      .then(data=>{
        const adresse = data.display_name || ('Lat: '+lat.toFixed(5)+', Lon: '+lon.toFixed(5));
        if (selectionType === 'depart') {
          document.getElementById('departAdresse').value = adresse;
          document.getElementById('departLat').value = lat;
          document.getElementById('departLon').value = lon;
          document.getElementById('departLatVisible').value = lat;
          document.getElementById('departLonVisible').value = lon;
          depart = {lat, lon, adresse};
        } else {
          document.getElementById('arriveeAdresse').value = adresse;
          document.getElementById('arriveeLat').value = lat;
          document.getElementById('arriveeLon').value = lon;
          document.getElementById('arriveeLatVisible').value = lat;
          document.getElementById('arriveeLonVisible').value = lon;
          arrivee = {lat, lon, adresse};
        }
        selectionType = null;
        if (depart && arrivee) updateLivraisonInfos(depart, arrivee);
      });
  });
  document.getElementById('urgence').onchange = function() {
    if (depart && arrivee) updateLivraisonInfos(depart, arrivee);
  };
  function updateLivraisonInfos(dep, arr) {
    function haversine(lat1, lon1, lat2, lon2) {
      const R = 6371;
      const dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
      const a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
      return R * c;
    }
    const distance = haversine(dep.lat, dep.lon, arr.lat, arr.lon);
    let prix = 500 + distance * 200;
    if (document.getElementById('urgence').checked) prix *= 1.5;
    const hour = new Date().getHours();
    if (hour >= 20 || hour < 6) prix *= 1.3;
    if (meteo === 'pluie') prix *= 1.2;
    prix = Math.round(prix);
    let temps = Math.round((distance / 25) * 60 * traffic + (meteo === 'pluie' ? 2 : 0));
    document.getElementById('infosLivraison').style.display = 'block';
    document.getElementById('infosLivraison').innerHTML =
      `<b>Distance :</b> ${distance.toFixed(2)} km<br>`+
      `<b>Prix estimé :</b> ${prix} FCFA<br>`+
      `<b>Temps estimé :</b> ${temps} min<br>`+
      `<b>Météo :</b> ${meteo === 'pluie' ? 'Pluie' : 'Clair'}<br>`+
      `<b>Trafic :</b> ${traffic > 1.2 ? 'Dense' : 'Normal'}`;
    // Moto-taxis dynamiques
    fetch('get_moto_taxis.php')
      .then(r=>r.json())
      .then(res => {
        const motos = res.moto_taxis || [];
        mototaxisDyn = motos;
        let html = '<b>Moto-taxis disponibles :</b><ul style="list-style:none;padding:0;">';
        // Nettoyer les anciens marqueurs dynamiques
        motoMarkersDyn.forEach(m=>map.removeLayer(m));
        motoMarkersDyn = [];
        motos.forEach((m, idx) => {
          // Gérer la photo de profil avec fallback
          const photoUrl = m.photo_profil && m.photo_profil.trim() !== '' 
            ? m.photo_profil 
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(m.nom)}&background=2563eb&color=fff&size=64`;
          
          html += `<li id="motoTaxiListItem${idx}" style="margin-bottom:0.5rem;cursor:pointer;display:flex;align-items:center;gap:0.5rem;${selectedMoto && selectedMoto.id===m.id ? 'background:#e0f2fe;' : ''}" onclick="window.selectMotoTaxi(${idx})">
            <img src="${photoUrl}" alt="${m.nom}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" 
                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(m.nom)}&background=2563eb&color=fff&size=64';">
            <b>${m.nom}</b> (${m.note_moyenne}★) - ${m.lat && dep ? (getDistance(dep.lat, dep.lon, m.lat, m.lon).toFixed(2)+' km') : ''}, ${m.temps_reponse_moyen} min
            ${selectedMoto && selectedMoto.id===m.id ? '<span style=\'color:#2563eb;font-weight:bold;margin-left:8px;\'>(choisi)</span>' : ''}
          </li>`;
          // Créer le marqueur dynamique sur la carte
          const marker = L.marker([m.lat, m.lon], {icon: L.divIcon({html:'<i class=\'fas fa-motorcycle\' style=\'color:'+(selectedMoto && selectedMoto.id===m.id ? '#2563eb' : '#16a34a')+';font-size:'+(selectedMoto && selectedMoto.id===m.id ? '2rem' : '1.5rem')+';\'></i>',iconSize:[selectedMoto && selectedMoto.id===m.id ? 32 : 24,selectedMoto && selectedMoto.id===m.id ? 32 : 24],className:selectedMoto && selectedMoto.id===m.id ? 'moto-icon-selected' : 'moto-icon'})}).addTo(map).bindPopup(`${m.nom} (${m.note_moyenne}★)`);
          marker.on('click', function() {
            window.selectMotoTaxi(idx);
          });
          motoMarkersDyn.push(marker);
        });
        html += '</ul>';
        document.getElementById('motoTaxisList').style.display = 'block';
        document.getElementById('motoTaxisList').innerHTML = html;
        // Expose la liste pour sélection
        window.mototaxisDyn = mototaxisDyn;
        window.selectMotoTaxi = function(idx) {
          selectedMoto = mototaxisDyn[idx];
          const idField = document.getElementById('motoTaxiIdField');
          const nomField = document.getElementById('motoTaxiNomField');
          if (idField) idField.value = selectedMoto.id;
          if (nomField) nomField.value = selectedMoto.nom;
          // Mettre à jour la sélection sur la carte
          motoMarkersDyn.forEach((marker, i) => {
            if (i === idx) {
              marker.setIcon(L.divIcon({html:'<i class=\'fas fa-motorcycle\' style=\'color:#2563eb;font-size:2rem;\'></i>',iconSize:[32,32],className:'moto-icon-selected'}));
              marker.openPopup();
            } else {
              marker.setIcon(L.divIcon({html:'<i class=\'fas fa-motorcycle\' style=\'color:#16a34a;font-size:1.5rem;\'></i>',iconSize:[24,24],className:'moto-icon'}));
              marker.closePopup();
            }
          });
          // Mettre à jour le surlignage de la liste
          mototaxisDyn.forEach((m, i) => {
            const li = document.getElementById('motoTaxiListItem'+i);
            if (li) {
              if (i === idx) {
                li.style.background = '#e0f2fe';
              } else {
                li.style.background = '';
              }
            }
          });
          btnValider.disabled = false;
          // Afficher le récapitulatif du moto-taxi sélectionné
          const recapDiv = document.getElementById('motoTaxiSelectedRecap');
          if (recapDiv) {
            // Gérer la photo de profil avec fallback pour le récapitulatif
            const recapPhotoUrl = selectedMoto.photo_profil && selectedMoto.photo_profil.trim() !== '' 
              ? selectedMoto.photo_profil 
              : `https://ui-avatars.com/api/?name=${encodeURIComponent(selectedMoto.nom)}&background=2563eb&color=fff&size=64`;
            
            recapDiv.innerHTML = `<div style='display:flex;align-items:center;gap:1rem;background:#e0f2fe;padding:0.7rem 1rem;border-radius:8px;margin-top:0.5rem;'>
              <img src="${recapPhotoUrl}" alt="${selectedMoto.nom}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" 
                   onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(selectedMoto.nom)}&background=2563eb&color=fff&size=64';">
              <div><b>${selectedMoto.nom}</b> <span style='color:#f59e42;'>${selectedMoto.note_moyenne}★</span><br><span style='font-size:0.95em;color:#64748b;'>${selectedMoto.lat && dep ? (getDistance(dep.lat, dep.lon, selectedMoto.lat, selectedMoto.lon).toFixed(2)+' km') : ''}, ${selectedMoto.temps_reponse_moyen} min</span></div>
              <span style='margin-left:auto;color:#2563eb;font-weight:bold;'>Sélectionné</span>
            </div>`;
          }
          btnValider.disabled = false;
        };
        // Permettre la sélection par id (depuis la carte générale)
        window.selectMotoTaxiById = function(motoId) {
          const idx = mototaxisDyn.findIndex(m=>m.id==motoId);
          if(idx!==-1) window.selectMotoTaxi(idx);
        };
      });
  }
  document.getElementById('livraisonForm').onsubmit = function(e) {
    e.preventDefault();
    const motoTaxiId = document.getElementById('motoTaxiIdField').value;
    console.log('Moto-taxi ID envoyé:', motoTaxiId); // debug
    if (!motoTaxiId) {
      document.getElementById('livraisonResult').innerHTML = '<span style="color:#dc2626;">Veuillez sélectionner un moto-taxi.</span>';
      return;
    }
    const data = new FormData(this);
    fetch('create_livraison.php', { method: 'POST', body: data })
      .then(r => {
        if (!r.ok) return r.json().then(res => { throw res; });
        return r.json();
      })
      .then(res => {
        if (res.success) {
          document.getElementById('livraisonResult').innerHTML = '<span style="color:#16a34a;">' + res.message + '</span>';
          refreshSolde();
          refreshHistory();
          refreshStats();
          refreshTransactions();
          setTimeout(hideLivraisonForm, 2000);
        } else {
          document.getElementById('livraisonResult').innerHTML = '<span style="color:#dc2626;">' + res.message + '</span>';
        }
      })
      .catch((err) => {
        document.getElementById('livraisonResult').innerHTML = '<span style="color:#dc2626;">' + (err && err.message ? err.message : 'Erreur serveur') + '</span>';
      });
  };
}
// Rafraîchir le solde utilisateur
function refreshSolde() {
  fetch('get_solde.php').then(r=>r.json()).then(res=>{
    if(res.solde!==undefined) document.getElementById('userBalance').textContent = res.solde.toLocaleString()+' FCFA';
  });
}
// Rafraîchir l'historique
function refreshHistory() {
  fetch('get_history.php').then(r=>r.json()).then(res=>{
    if (!res.success) return;
    
    const historyList = document.getElementById('historyList');
    historyList.innerHTML = '';
    
    res.livraisons.forEach(h => {
      const li = document.createElement('li');
      const documentsIcon = h.nb_documents > 0 ? `<i class="fas fa-file-alt" style="color:#3b82f6;margin-left:5px;" title="${h.nb_documents} document(s)"></i>` : '';
      
      li.innerHTML = `<span class="icon"><i class="fas fa-box"></i></span>
        <span>${h.description || 'Colis'} ${documentsIcon}</span>
        <span class="status ${h.statut}">${h.statut_label}</span>
        <span style="color:#64748b;font-size:0.9em;">${h.mototaxi_nom || ''}</span>
        <span style="margin-left:auto;font-size:0.85em;color:#64748b;">${h.date_formatee}</span>`;
      
      li.onclick = () => showLivraisonDetails(h);
      historyList.appendChild(li);
    });
  });
}

// Afficher les détails d'une livraison
function showLivraisonDetails(livraison) {
  let detailsHtml = `
    <div style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95)); backdrop-filter: blur(20px); padding: 1.5rem; border-radius: 24px; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15); max-width: 700px; margin: auto; position: relative; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.2);">
  
  <!-- Barre gradient animée -->
  <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c); background-size: 400% 400%; animation: gradientShift 3s ease infinite;"></div>
  
  <style>
    @keyframes gradientShift {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }
    .detail-card {
      background: linear-gradient(135deg, #f8fafc, #e2e8f0);
      padding: 1rem;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    .detail-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: linear-gradient(90deg, #667eea, #764ba2);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }
    .detail-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    .detail-card:hover::before {
      transform: scaleX(1);
    }
    .badge {
      padding: 0.1rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-success { background: #10b981; color: white; }
    .badge-warning { background: #f59e0b; color: white; }
    .badge-danger { background: #ef4444; color: white; }
    .badge-info { background: #3b82f6; color: white; }
    .btn {
      padding: 0.4rem 2rem;
      border-radius: 50px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .btn-secondary {
      background: linear-gradient(135deg, #6b7280, #4b5563);
      color: white;
      box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
    }
    .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(107, 114, 128, 0.4);
    }
  </style>

  <h3 style="margin-bottom: 0.1rem; color: #1e293b; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 1rem; padding-bottom: 0.1rem; border-bottom: 2px solid #f1f5f9;">
    <i class="fas fa-box" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 10px; border-radius: 50%; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);"></i>
    Détails de la livraison #${livraison.id}
  </h3>
  
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 0.5rem;">
    <div class="detail-card">
      <strong style="color: #374151; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem;">
        <i class="fas fa-tag" style="color: #667eea;"></i> Type:
      </strong>
      <div style="color: #1f2937; font-weight: 500; font-size: 1rem;">${livraison.type_label}</div>
    </div>
    
    <div class="detail-card">
      <strong style="color: #374151; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem;">
        <i class="fas fa-info-circle" style="color: #667eea;"></i> Statut:
      </strong>
      <div style="color: #1f2937; font-weight: 500; font-size: 1rem;">
        <span class="badge badge-${livraison.statut_color}">${livraison.statut_label}</span>
      </div>
    </div>
    
    <div class="detail-card">
      <strong style="color: #374151; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem;">
        <i class="fas fa-route" style="color: #667eea;"></i> Distance:
      </strong>
      <div style="color: #1f2937; font-weight: 500; font-size: 1rem;">${livraison.distance_formatee}</div>
    </div>
    
    <div class="detail-card">
      <strong style="color: #374151; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem;">
        <i class="fas fa-euro-sign" style="color: #667eea;"></i> Prix:
      </strong>
      <div style="color: #1f2937; font-weight: 500; font-size: 1rem;">${livraison.prix_formate}</div>
    </div>
    
    <div class="detail-card">
      <strong style="color: #374151; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem;">
        <i class="fas fa-calendar" style="color: #667eea;"></i> Date:
      </strong>
      <div style="color: #1f2937; font-weight: 500; font-size: 1rem;">${livraison.date_formatee}</div>
    </div>
    
    <div class="detail-card">
      <strong style="color: #374151; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem;">
        <i class="fas fa-exclamation-triangle" style="color: #667eea;"></i> Fragile:
      </strong>
      <div style="color: #1f2937; font-weight: 500; font-size: 1rem;">${livraison.fragile ? 'Oui' : 'Non'}</div>
    </div>
  </div>
  
  <div style="background: linear-gradient(135deg, #fef3c7, #fde68a); padding: 0.5rem; border-radius: 20px; margin-bottom: 1rem; border: 1px solid #fbbf24;">
    <strong style="color: #92400e; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
      <i class="fas fa-map-marker-alt" style="color: #f59e0b;"></i> Départ:
    </strong>
    <div style="color: #92400e; margin-bottom: 0.1rem;">${livraison.adresse_depart}</div>
    <strong style="color: #92400e; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
      <i class="fas fa-flag-checkered" style="color: #f59e0b;"></i> Arrivée:
    </strong>
    <div style="color: #92400e;">${livraison.adresse_arrivee}</div>
  </div>
  
  ${livraison.description ? `
    <div style="background: linear-gradient(135deg, #dbeafe, #bfdbfe); padding: 1.5rem; border-radius: 20px; margin-bottom: 1rem; border: 1px solid #60a5fa;">
      <strong style="color: #1e40af; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
        <i class="fas fa-file-text" style="color: #3b82f6;"></i> Description:
      </strong>
      <div style="color: #1e40af; margin-top: 0.5rem;">${livraison.description}</div>
    </div>
  ` : ''}
  
  ${livraison.mototaxi_nom ? `
    <div style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); padding: 0.5rem; border-radius: 20px; margin-bottom: 1rem; border: 1px solid #0ea5e9; position: relative;">
      <div style="position: absolute; top: 10px; right: 10px; width: 30px; height: 20px; background: linear-gradient(45deg, #0ea5e9, #0284c7); border-radius: 50%; opacity: 0.1;"></div>
      <strong style="color: #0c4a6e; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-motorcycle" style="color: #0ea5e9;"></i> Moto-taxi assigné:
      </strong>
      <div style="display: flex; align-items: center; gap: 1rem;">
        <img src="${livraison.mototaxi_photo_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(livraison.mototaxi_nom)}&background=2563eb&color=fff&size=64`}"
             alt="${livraison.mototaxi_nom}" 
             style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #0ea5e9; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);"
             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(livraison.mototaxi_nom)}&background=2563eb&color=fff&size=64';">
        <div>
          <div style="font-weight: 700; color: #0c4a6e; font-size: 1.1rem;">${livraison.mototaxi_nom}</div>
          <div style="color: #0369a1; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-phone" style="color: #0ea5e9;"></i>
            ${livraison.mototaxi_telephone}
          </div>
        </div>
      </div>
    </div>
  ` : ''}
  
  <div id="documentsContainer" style="display: none; background: linear-gradient(135deg, #f3e8ff, #e9d5ff); padding: 1.5rem; border-radius: 20px; margin-bottom: 2rem; border: 1px solid #a855f7;">
    <h4 style="margin-bottom: 1rem; color: #6b21a8; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
      <i class="fas fa-file-alt" style="color: #a855f7;"></i> Documents
    </h4>
    <div id="documentsList"></div>
  </div>
  
  <div style="text-align: center; margin-top: 2rem;">
    <button onclick="closeLivraisonDetails()" class="btn btn-secondary">
      <i class="fas fa-times"></i>
      Fermer
    </button>
  </div>
</div>
  `;
  
  // Créer une modal pour afficher les détails
  const modal = document.createElement('div');
  modal.id = 'livraisonDetailsModal';
  modal.style.cssText = `
    position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);
    display:flex;align-items:center;justify-content:center;z-index:9999;
  `;
  modal.innerHTML = detailsHtml;
  document.body.appendChild(modal);
  
  // Charger les documents si c'est une demande de retrait
  if (livraison.type_livraison === 'retrait' && livraison.nb_documents > 0) {
    loadLivraisonDocuments(livraison.id);
  }
}

// Charger les documents d'une livraison
function loadLivraisonDocuments(livraisonId) {
  fetch(`get_documents_livraison.php?livraison_id=${livraisonId}`)
    .then(r => r.json())
    .then(res => {
      if (res.success && res.documents.length > 0) {
        const documentsList = document.getElementById('documentsList');
        const documentsContainer = document.getElementById('documentsContainer');
        
        let html = '<div style="display:grid;gap:0.5rem;">';
        res.documents.forEach(doc => {
          html += `
            <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:0.75rem;border-radius:6px;">
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <i class="${doc.icone}" style="color:#3b82f6;"></i>
                <div>
                  <div style="font-weight:500;">${doc.nom_original}</div>
                  <div style="font-size:0.85em;color:#64748b;">${doc.taille_formatee} • ${doc.date_formatee}</div>
                </div>
              </div>
              <button onclick="downloadDocument(${doc.id})" class="btn btn-sm btn-primary">
                <i class="fas fa-download"></i> Télécharger
              </button>
            </div>
          `;
        });
        html += '</div>';
        
        documentsList.innerHTML = html;
        documentsContainer.style.display = 'block';
      }
    });
}

// Télécharger un document
function downloadDocument(documentId) {
  window.open(`download_document.php?document_id=${documentId}`, '_blank');
}

// Fermer les détails de livraison
function closeLivraisonDetails() {
  const modal = document.getElementById('livraisonDetailsModal');
  if (modal) {
    modal.remove();
  }
}
// Rafraîchir les statistiques
function refreshStats() {
  fetch('get_stats.php')
    .then(r => r.json())
    .then(res => {
      if (res.success && res.stats) {
        const stats = res.stats;
        
        // Statistiques principales avec gestion des valeurs par défaut
        const statLivraisons = document.getElementById('statLivraisons');
        const statDistance = document.getElementById('statDistance');
        const statDepense = document.getElementById('statDepense');
        const statNote = document.getElementById('statNote');
        
        if (statLivraisons) statLivraisons.textContent = stats.livraisons || 0;
        if (statDistance) statDistance.textContent = (stats.distance || 0) + ' km';
        if (statDepense) statDepense.textContent = (stats.depense || 0).toLocaleString() + ' FCFA';
        if (statNote) statNote.textContent = (stats.note || 5.0).toFixed(1);
        
        // Mettre à jour les statistiques détaillées si elles existent
        updateDetailedStats(stats);
        
        // Mettre à jour les points de fidélité
        updateLoyaltyPoints(stats.points_fidelite);
      } else {
        console.error('Erreur dans la réponse des statistiques:', res.message);
        // Afficher des valeurs par défaut en cas d'erreur
        setDefaultStats();
      }
    })
    .catch((error) => {
      console.error('Erreur lors du chargement des statistiques:', error);
      // Afficher des valeurs par défaut en cas d'erreur
      setDefaultStats();
    });
}

// Fonction pour définir les statistiques par défaut
function setDefaultStats() {
  const statLivraisons = document.getElementById('statLivraisons');
  const statDistance = document.getElementById('statDistance');
  const statDepense = document.getElementById('statDepense');
  const statNote = document.getElementById('statNote');
  
  if (statLivraisons) statLivraisons.textContent = '0';
  if (statDistance) statDistance.textContent = '0 km';
  if (statDepense) statDepense.textContent = '0 FCFA';
  if (statNote) statNote.textContent = '5.0';
}

// Mettre à jour les statistiques détaillées
function updateDetailedStats(stats) {
  // Statistiques des 30 derniers jours
  if (stats.stats_30j) {
    const stats30j = stats.stats_30j;
    const elements = {
      'stats-30j-livraisons': stats30j.livraisons,
      'stats-30j-distance': stats30j.distance + ' km',
      'stats-30j-depense': stats30j.depense.toLocaleString() + ' FCFA'
    };
    
    Object.keys(elements).forEach(id => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = elements[id];
      }
    });
  }
  
  // Statistiques par type
  if (stats.par_type && stats.par_type.length > 0) {
    updateTypeStats(stats.par_type);
  }
}

// Mettre à jour les statistiques par type de livraison
function updateTypeStats(typeStats) {
  const container = document.getElementById('typeStatsContainer');
  if (!container) return;
  
  let html = '<div style="display:grid;gap:0.5rem;">';
  typeStats.forEach(type => {
    const typeLabel = getTypeLabel(type.type_livraison);
    const totalPrix = number_format(type.total_prix, 0, ',', ' ') + ' FCFA';
    
    html += `
      <div style="display:flex;justify-content:space-between;padding:0.5rem;background:#f8fafc;border-radius:6px;">
        <span><strong>${typeLabel}:</strong> ${type.nombre}</span>
        <span style="color:#64748b;">${totalPrix}</span>
      </div>
    `;
  });
  html += '</div>';
  
  container.innerHTML = html;
}

// Mettre à jour les points de fidélité
function updateLoyaltyPoints(points) {
  const element = document.getElementById('loyaltyPoints');
  if (element) {
    element.textContent = points.toLocaleString();
  }
  
  // Mettre à jour aussi l'élément userPoints
  const userPointsElement = document.getElementById('userPoints');
  if (userPointsElement) {
    userPointsElement.textContent = points.toLocaleString() + ' pts';
  }
}

// Fonction utilitaire pour formater les nombres
function number_format(number, decimals, dec_point, thousands_sep) {
  return new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  }).format(number);
}

// Charger les transactions
function loadTransactions(type = '') {
  const url = type ? `get_transactions.php?type=${type}&limit=5` : 'get_transactions.php?limit=5';
  
  fetch(url)
    .then(r => r.json())
    .then(res => {
      if (!res.success) return;
      
      const transactionsList = document.getElementById('transactionsList');
      const transactionsStats = document.getElementById('transactionsStats');
      
      transactionsList.innerHTML = '';
      
      if (res.transactions.length === 0) {
        transactionsList.innerHTML = '<li style="text-align:center;color:#64748b;padding:1rem;">Aucune transaction</li>';
        transactionsStats.style.display = 'none';
        return;
      }
      
      res.transactions.forEach(trans => {
        const li = document.createElement('li');
        const montantColor = trans.type_transaction === 'credit' ? '#16a34a' : '#dc2626';
        const montantPrefix = trans.type_transaction === 'credit' ? '+' : '-';
        
        li.innerHTML = `
          <div class="transaction-icon ${trans.type_transaction}">
            <i class="${trans.icone}"></i>
          </div>
          <div style="flex:1;">
            <div style="font-weight:500;font-size:0.9rem;">${trans.description}</div>
            <div style="color:#64748b;font-size:0.8rem;">${trans.date_relative}</div>
          </div>
          <div style="text-align:right;">
            <div style="font-weight:600;color:${montantColor};">${montantPrefix}${trans.montant_formate}</div>
            <div style="color:#64748b;font-size:0.8rem;">Solde: ${trans.solde_apres_formate}</div>
          </div>
        `;
        
        transactionsList.appendChild(li);
      });
      
      // Afficher les statistiques si des transactions existent
      if (res.stats && res.transactions.length > 0) {
        document.getElementById('totalCredits').textContent = res.stats.total_credits.toLocaleString() + ' FCFA';
        document.getElementById('totalDebits').textContent = res.stats.total_debits.toLocaleString() + ' FCFA';
        transactionsStats.style.display = 'block';
      } else {
        transactionsStats.style.display = 'none';
      }
    })
    .catch(() => {
      const transactionsList = document.getElementById('transactionsList');
      transactionsList.innerHTML = '<li style="text-align:center;color:#dc2626;padding:1rem;">Erreur de chargement</li>';
    });
}

// Filtrer les transactions
function filterTransactions(type) {
  loadTransactions(type);
  
  // Mettre à jour l'apparence des boutons
  const buttons = document.querySelectorAll('[onclick^="filterTransactions"]');
  buttons.forEach(btn => {
    btn.style.background = '';
    btn.style.color = '';
  });
  
  // Mettre en surbrillance le bouton actif
  const activeButton = document.querySelector(`[onclick="filterTransactions('${type}')"]`);
  if (activeButton) {
    if (type === 'credit') {
      activeButton.style.background = '#16a34a';
      activeButton.style.color = 'white';
    } else if (type === 'debit') {
      activeButton.style.background = '#dc2626';
      activeButton.style.color = 'white';
    } else {
      activeButton.style.background = '#2563eb';
      activeButton.style.color = 'white';
    }
  }
}

// Rafraîchir les transactions
function refreshTransactions() {
  loadTransactions();
}

// Variables pour le scanner QR
let qrStream = null;
let qrScanning = false;
let qrScanInterval = null;

// Démarrer le scanner QR
function startQRScanner() {
  const video = document.getElementById('qrVideo');
  const startBtn = document.getElementById('startScanBtn');
  const stopBtn = document.getElementById('stopScanBtn');
  const resultDiv = document.getElementById('qrResult');
  
  if (!video) return;
  
  // Vérifier si l'API getUserMedia est supportée
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    showQRResult('Votre navigateur ne supporte pas l\'accès à la caméra', 'error');
    return;
  }
  
  // Demander l'accès à la caméra
  navigator.mediaDevices.getUserMedia({ 
    video: { 
      facingMode: 'environment', // Caméra arrière si disponible
      width: { ideal: 640 },
      height: { ideal: 480 }
    } 
  })
  .then(function(stream) {
    qrStream = stream;
    video.srcObject = stream;
    video.play();
    
    // Changer l'état des boutons
    startBtn.style.display = 'none';
    stopBtn.style.display = 'inline-block';
    qrScanning = true;
    
    // Masquer le résultat précédent
    resultDiv.style.display = 'none';
    
    // Démarrer la détection QR
    startQRDetection();
  })
  .catch(function(err) {
    console.error('Erreur d\'accès à la caméra:', err);
    showQRResult('Impossible d\'accéder à la caméra. Vérifiez les permissions.', 'error');
  });
}

// Arrêter le scanner QR
function stopQRScanner() {
  const video = document.getElementById('qrVideo');
  const startBtn = document.getElementById('startScanBtn');
  const stopBtn = document.getElementById('stopScanBtn');
  
  qrScanning = false;
  
  if (qrScanInterval) {
    clearInterval(qrScanInterval);
    qrScanInterval = null;
  }
  
  if (qrStream) {
    qrStream.getTracks().forEach(track => track.stop());
    qrStream = null;
  }
  
  if (video) {
    video.srcObject = null;
  }
  
  startBtn.style.display = 'inline-block';
  stopBtn.style.display = 'none';
}

// Fermer le scanner QR
function closeQRScanner() {
  stopQRScanner();
  const modal = document.getElementById('qrScannerModal');
  if (modal) {
    modal.remove();
  }
}

// Démarrer la détection QR
function startQRDetection() {
  const video = document.getElementById('qrVideo');
  const canvas = document.getElementById('qrCanvas');
  const ctx = canvas.getContext('2d');
  
  qrScanInterval = setInterval(() => {
    if (!qrScanning || !video || video.readyState !== video.HAVE_ENOUGH_DATA) {
      return;
    }
    
    // Définir la taille du canvas
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Dessiner l'image de la vidéo sur le canvas
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Récupérer les données de l'image
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    
    // Détecter le QR code
    const code = jsQR(imageData.data, imageData.width, imageData.height, {
      inversionAttempts: "dontInvert",
    });
    
    if (code) {
      // QR code détecté !
      handleQRCodeResult(code.data);
    }
  }, 100); // Vérifier toutes les 100ms
}

// Traiter le résultat du QR code
function handleQRCodeResult(data) {
  console.log('QR Code détecté:', data);
  
  // Arrêter le scanner
  stopQRScanner();
  
  try {
    // Essayer de parser le QR code comme JSON
    const qrData = JSON.parse(data);
    
    // Vérifier le type de QR code
    if (qrData.type === 'livraison') {
      // QR code pour une livraison
      showQRResult(`Livraison détectée: ${qrData.description || 'Colis'}`, 'success');
      setTimeout(() => {
        // Ouvrir le formulaire de livraison avec les données pré-remplies
        showLivraisonFormFromQR(qrData);
      }, 1500);
    } else if (qrData.type === 'moto_taxi') {
      // QR code pour un moto-taxi
      showQRResult(`Moto-taxi détecté: ${qrData.nom}`, 'success');
      setTimeout(() => {
        // Sélectionner le moto-taxi sur la carte
        selectMotoTaxiFromQR(qrData.id);
      }, 1500);
    } else if (qrData.type === 'paiement') {
      // QR code pour un paiement
      showQRResult(`Paiement détecté: ${qrData.montant} FCFA`, 'success');
      setTimeout(() => {
        // Traiter le paiement
        processPaymentFromQR(qrData);
      }, 1500);
    } else {
      // QR code générique
      showQRResult(`QR Code détecté: ${data}`, 'info');
    }
  } catch (e) {
    // Si ce n'est pas du JSON, traiter comme texte simple
    if (data.startsWith('http')) {
      // C'est une URL
      showQRResult(`URL détectée: ${data}`, 'info');
      setTimeout(() => {
        window.open(data, '_blank');
      }, 1500);
    } else {
      // Texte simple
      showQRResult(`Texte détecté: ${data}`, 'info');
    }
  }
}

// Afficher le résultat du scan QR
function showQRResult(message, type = 'info') {
  const resultDiv = document.getElementById('qrResult');
  if (!resultDiv) return;
  
  const colors = {
    success: '#dcfce7',
    error: '#fee2e2',
    info: '#dbeafe'
  };
  
  const textColors = {
    success: '#166534',
    error: '#991b1b',
    info: '#1e40af'
  };
  
  resultDiv.style.display = 'block';
  resultDiv.style.background = colors[type] || colors.info;
  resultDiv.style.color = textColors[type] || textColors.info;
  resultDiv.style.border = `1px solid ${textColors[type] || textColors.info}`;
  resultDiv.innerHTML = `
    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
    ${message}
  `;
}

// Ouvrir le formulaire de livraison avec les données du QR
function showLivraisonFormFromQR(qrData) {
  closeQRScanner();
  
  // Pré-remplir le formulaire avec les données du QR
  if (qrData.adresse_depart) {
    document.getElementById('departAdresse').value = qrData.adresse_depart;
  }
  if (qrData.adresse_arrivee) {
    document.getElementById('arriveeAdresse').value = qrData.adresse_arrivee;
  }
  if (qrData.description) {
    document.getElementById('description').value = qrData.description;
  }
  
  showQRResult('Formulaire de livraison ouvert avec les données du QR code', 'success');
}

// Sélectionner un moto-taxi depuis le QR
function selectMotoTaxiFromQR(motoTaxiId) {
  closeQRScanner();
  
  // Trouver le moto-taxi sur la carte
  if (window.selectMotoTaxiById) {
    window.selectMotoTaxiById(motoTaxiId);
    showQRResult('Moto-taxi sélectionné sur la carte', 'success');
  } else {
    showQRResult('Moto-taxi non trouvé sur la carte', 'error');
  }
}

// Traiter un paiement depuis le QR
function processPaymentFromQR(qrData) {
  closeQRScanner();
  
  // Simuler un paiement
  const montant = qrData.montant || 0;
  const description = qrData.description || 'Paiement QR';
  
  if (confirm(`Confirmer le paiement de ${montant} FCFA pour: ${description} ?`)) {
    // Ici on pourrait appeler une API de paiement
    showQRResult(`Paiement de ${montant} FCFA traité avec succès`, 'success');
    refreshSolde();
  } else {
    showQRResult('Paiement annulé', 'info');
  }
}
</script>
</body>
</html> 