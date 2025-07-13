<?php
session_start();
require_once 'db.php';

// Vérifier si le moto-taxi est connecté
if (!isset($_SESSION['moto_taxi_id'])) {
    header('Location: moto_taxi_login.php');
    exit;
}

// Récupérer les données du moto-taxi
$stmt = $pdo->prepare("SELECT * FROM moto_taxis WHERE id = ?");
$stmt->execute([$_SESSION['moto_taxi_id']]);
$moto_taxi = $stmt->fetch();

if (!$moto_taxi) {
    session_destroy();
    header('Location: moto_taxi_login.php');
    exit;
}

// Vérifier le statut du compte
if ($moto_taxi['statut'] !== 'actif') {
    $error_message = "Votre compte n'est pas encore activé. Veuillez attendre la validation de l'administrateur.";
}

// Photo de profil
$photo = 'https://ui-avatars.com/api/?name=' . urlencode($moto_taxi['prenom'].' '.$moto_taxi['nom']) . '&background=2563eb&color=fff&size=64';

if ($moto_taxi['photo_profil']) {
    if (strpos($moto_taxi['photo_profil'], 'http') === 0) {
        $photo = $moto_taxi['photo_profil'];
    } else {
        if (file_exists($moto_taxi['photo_profil'])) {
            $photo = $moto_taxi['photo_profil'];
        }
    }
}

// Récupérer les statistiques
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_livraisons,
        COUNT(CASE WHEN statut = 'terminee' THEN 1 END) as livraisons_terminees,
        COUNT(CASE WHEN statut = 'en_cours' THEN 1 END) as livraisons_en_cours,
        SUM(CASE WHEN statut = 'terminee' THEN montant ELSE 0 END) as gains_totaux,
        AVG(CASE WHEN statut = 'terminee' THEN note ELSE NULL END) as note_moyenne
    FROM livraisons 
    WHERE moto_taxi_id = ?
");
$stmt->execute([$_SESSION['moto_taxi_id']]);
$stats = $stmt->fetch();

// Récupérer les dernières livraisons
$stmt = $pdo->prepare("
    SELECT l.*, u.nom as user_nom, u.prenom as user_prenom, u.telephone as user_telephone
    FROM livraisons l
    JOIN users u ON l.user_id = u.id
    WHERE l.moto_taxi_id = ?
    ORDER BY l.date_creation DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['moto_taxi_id']]);
$recent_livraisons = $stmt->fetchAll();

// Récupérer les demandes de livraison disponibles (dans un rayon de 5km)
$stmt = $pdo->prepare("
    SELECT l.*, u.nom as user_nom, u.prenom as user_prenom, u.telephone as user_telephone
    FROM livraisons l
    JOIN users u ON l.user_id = u.id
    WHERE l.statut = 'en_attente'
    AND l.date_creation >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY l.date_creation DESC
");
$stmt->execute();
$demandes_disponibles = $stmt->fetchAll();

// Récupérer les notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND type_user = 'moto_taxi'
    ORDER BY date_creation DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['moto_taxi_id']]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil Moto-Taxi - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f4f4f4; 
            color: #1e293b; 
        }
        .navbar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            z-index: 1000; 
            background: #fff; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 1rem 2rem; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07); 
        }
        .navbar .logo { 
            display: flex; 
            align-items: center; 
            font-size: 1.4rem; 
            font-weight: bold; 
            color: #2563eb; 
        }
        .navbar .logo i { margin-right: 0.5rem; }
        .navbar .nav-links { 
            display: flex; 
            align-items: center; 
            gap: 2rem; 
        }
        .navbar .nav-link { 
            color: #64748b; 
            text-decoration: none; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            font-size: 1rem; 
            transition: color 0.2s; 
        }
        .navbar .nav-link.active, .navbar .nav-link:hover { color: #2563eb; }
        .navbar .nav-link i { font-size: 1.2rem; margin-bottom: 0.2rem; }
        .navbar .nav-link span { font-size: 0.8rem; }
        .navbar .logout { color: #dc2626; margin-left: 1.5rem; text-decoration: underline; font-size: 1rem; }
        .main-content { 
            max-width: 1400px; 
            margin: 110px auto 0 auto; 
            padding: 2rem 1rem; 
        }
        .welcome { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            margin-bottom: 2rem; 
        }
        .welcome .avatar { 
            width: 56px; 
            height: 56px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #2563eb; 
        }
        .welcome .msg { font-size: 1.3rem; font-weight: 500; }
        .status-toggle { 
            margin-left: auto; 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
        }
        .status-indicator { 
            display: flex; 
            align-items: center; 
            gap: 0.5rem; 
            padding: 0.5rem 1rem; 
            border-radius: 20px; 
            font-weight: 500; 
        }
        .status-online { background: #dcfce7; color: #166534; }
        .status-offline { background: #fee2e2; color: #991b1b; }
        .toggle-switch { 
            position: relative; 
            width: 60px; 
            height: 30px; 
        }
        .toggle-switch input { 
            opacity: 0; 
            width: 0; 
            height: 0; 
        }
        .slider { 
            position: absolute; 
            cursor: pointer; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background-color: #ccc; 
            transition: .4s; 
            border-radius: 30px; 
        }
        .slider:before { 
            position: absolute; 
            content: ""; 
            height: 22px; 
            width: 22px; 
            left: 4px; 
            bottom: 4px; 
            background-color: white; 
            transition: .4s; 
            border-radius: 50%; 
        }
        input:checked + .slider { background-color: #2563eb; }
        input:checked + .slider:before { transform: translateX(30px); }
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 2rem; 
            margin-bottom: 2rem; 
        }
        .card { 
            background: #fff; 
            border-radius: 12px; 
            box-shadow: 0 2px 8px #e2e8f0; 
            padding: 1.5rem; 
        }
        .card-header { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            margin-bottom: 1.5rem; 
        }
        .card-header i { font-size: 1.5rem; color: #2563eb; }
        .card-title { font-size: 1.2rem; font-weight: 600; }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 1rem; 
        }
        .stat-item { 
            text-align: center; 
            padding: 1rem; 
            background: #f8fafc; 
            border-radius: 8px; 
        }
        .stat-value { 
            font-size: 1.5rem; 
            font-weight: 600; 
            color: #2563eb; 
        }
        .stat-label { 
            color: #64748b; 
            font-size: 0.9rem; 
            margin-top: 0.5rem; 
        }
        .map-container { 
            height: 400px; 
            border-radius: 8px; 
            overflow: hidden; 
        }
        .demande-item { 
            border: 1px solid #e5e7eb; 
            border-radius: 8px; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            transition: all 0.2s; 
        }
        .demande-item:hover { 
            border-color: #2563eb; 
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1); 
        }
        .demande-header { 
            display: flex; 
            justify-content: between; 
            align-items: center; 
            margin-bottom: 0.5rem; 
        }
        .demande-client { 
            font-weight: 500; 
            color: #374151; 
        }
        .demande-prix { 
            font-weight: 600; 
            color: #16a34a; 
            font-size: 1.1rem; 
        }
        .demande-details { 
            color: #64748b; 
            font-size: 0.9rem; 
            margin-bottom: 1rem; 
        }
        .demande-actions { 
            display: flex; 
            gap: 0.5rem; 
        }
        .btn { 
            padding: 0.5rem 1rem; 
            border: none; 
            border-radius: 6px; 
            font-weight: 500; 
            cursor: pointer; 
            transition: all 0.2s; 
            text-decoration: none; 
            display: inline-block; 
            text-align: center; 
            font-size: 0.9rem; 
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .notification-item { 
            padding: 0.75rem; 
            border-bottom: 1px solid #e5e7eb; 
            transition: background 0.2s; 
        }
        .notification-item:hover { background: #f8fafc; }
        .notification-item:last-child { border-bottom: none; }
        .notification-title { 
            font-weight: 500; 
            color: #374151; 
            margin-bottom: 0.25rem; 
        }
        .notification-time { 
            color: #64748b; 
            font-size: 0.8rem; 
        }
        .livraison-item { 
            padding: 0.75rem; 
            border-left: 3px solid #2563eb; 
            background: #f8fafc; 
            margin-bottom: 0.5rem; 
            border-radius: 0 6px 6px 0; 
        }
        .livraison-status { 
            display: inline-block; 
            padding: 0.2rem 0.5rem; 
            border-radius: 12px; 
            font-size: 0.8rem; 
            font-weight: 500; 
        }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-en_cours { background: #dbeafe; color: #1e40af; }
        .status-terminee { background: #dcfce7; color: #166534; }
        .status-annulee { background: #fee2e2; color: #991b1b; }
        .error-message { 
            background: #fee2e2; 
            color: #991b1b; 
            padding: 1rem; 
            border-radius: 8px; 
            margin-bottom: 1rem; 
            border: 1px solid #fecaca; 
        }
        @media (max-width: 768px) { 
            .dashboard-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo"><i class="fas fa-motorcycle"></i> MotoExpress</div>
        <div class="nav-links">
            <a href="moto_taxi_home.php" class="nav-link active"><i class="fas fa-home"></i><span>Accueil</span></a>
            <a href="moto_taxi_livraisons.php" class="nav-link"><i class="fas fa-route"></i><span>Livraisons</span></a>
            <a href="moto_taxi_messages.php" class="nav-link"><i class="fas fa-comments"></i><span>Messages</span></a>
            <a href="moto_taxi_gains.php" class="nav-link"><i class="fas fa-wallet"></i><span>Gains</span></a>
            <a href="moto_taxi_profil.php" class="nav-link"><i class="fas fa-user"></i><span>Profil</span></a>
            <a href="moto_taxi_logout.php" class="logout">Déconnexion</a>
        </div>
    </nav>

    <div class="main-content">
        <div class="welcome">
            <img src="<?php echo $photo; ?>" alt="Photo de profil" class="avatar">
            <div class="msg">Bonjour <?php echo htmlspecialchars($moto_taxi['prenom']); ?> !</div>
            <div class="status-toggle">
                <div class="status-indicator <?php echo $moto_taxi['statut'] === 'actif' ? 'status-online' : 'status-offline'; ?>">
                    <i class="fas fa-circle"></i>
                    <?php echo $moto_taxi['statut'] === 'actif' ? 'En ligne' : 'Hors ligne'; ?>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="statusToggle" <?php echo $moto_taxi['statut'] === 'actif' ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
        <div style="margin-bottom:1rem;">
            <button id="btn-geoloc" class="btn btn-primary" type="button"><i class="fas fa-crosshairs"></i> Ma position actuelle</button>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Carte et demandes -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-map-marked-alt"></i>
                    <div class="card-title">Carte et demandes</div>
                </div>
                
                <div class="map-container" id="map"></div>
                
                <div style="margin-top: 1rem;">
                    <h4 style="margin-bottom: 1rem;">Demandes disponibles</h4>
                    <?php if (empty($demandes_disponibles)): ?>
                        <p style="color: #64748b; text-align: center; padding: 2rem;">Aucune demande disponible pour le moment</p>
                    <?php else: ?>
                        <?php foreach ($demandes_disponibles as $demande): ?>
                            <div class="demande-item">
                                <div class="demande-header">
                                    <div class="demande-client">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($demande['user_prenom'] . ' ' . $demande['user_nom']); ?>
                                    </div>
                                    <div class="demande-prix">
                                        <?php echo number_format($demande['montant'], 0, ',', ' '); ?> FCFA
                                    </div>
                                </div>
                                <div class="demande-details">
                                    <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($demande['adresse_depart']); ?></div>
                                    <div><i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($demande['adresse_arrivee']); ?></div>
                                    <div><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($demande['date_creation'])); ?></div>
                                </div>
                                <div class="demande-actions">
                                    <button class="btn btn-success" onclick="accepterLivraison(<?php echo $demande['id']; ?>)">
                                        <i class="fas fa-check"></i> Accepter
                                    </button>
                                    <button class="btn btn-secondary" onclick="voirDetails(<?php echo $demande['id']; ?>)">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistiques et notifications -->
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Statistiques -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i>
                        <div class="card-title">Statistiques</div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['total_livraisons'] ?? 0; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['livraisons_terminees'] ?? 0; ?></div>
                            <div class="stat-label">Terminées</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['livraisons_en_cours'] ?? 0; ?></div>
                            <div class="stat-label">En cours</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['gains_totaux'] ?? 0, 0, ',', ' '); ?></div>
                            <div class="stat-label">Gains (FCFA)</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($stats['note_moyenne'] ?? 0, 1); ?></div>
                            <div class="stat-label">Note moyenne</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($moto_taxi['solde'], 0, ',', ' '); ?></div>
                            <div class="stat-label">Solde (FCFA)</div>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bell"></i>
                        <div class="card-title">Notifications</div>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <p style="color: #64748b; text-align: center; padding: 1rem;">Aucune notification</p>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['titre']); ?></div>
                                <div class="notification-time"><?php echo date('d/m H:i', strtotime($notif['date_creation'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Dernières livraisons -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <div class="card-title">Dernières livraisons</div>
                    </div>
                    <?php if (empty($recent_livraisons)): ?>
                        <p style="color: #64748b; text-align: center; padding: 1rem;">Aucune livraison</p>
                    <?php else: ?>
                        <?php foreach ($recent_livraisons as $livraison): ?>
                            <div class="livraison-item">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <div style="font-weight: 500;">
                                        <?php echo htmlspecialchars($livraison['user_prenom'] . ' ' . $livraison['user_nom']); ?>
                                    </div>
                                    <div style="font-weight: 600; color: #16a34a;">
                                        <?php echo number_format($livraison['montant'], 0, ',', ' '); ?> FCFA
                                    </div>
                                </div>
                                <div style="color: #64748b; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($livraison['adresse_depart']); ?> → <?php echo htmlspecialchars($livraison['adresse_arrivee']); ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="livraison-status status-<?php echo $livraison['statut']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $livraison['statut'])); ?>
                                    </span>
                                    <span style="color: #64748b; font-size: 0.8rem;">
                                        <?php echo date('d/m H:i', strtotime($livraison['date_creation'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialisation de la carte
        let initialLat = <?php echo $moto_taxi['latitude'] ? floatval($moto_taxi['latitude']) : 4.0511; ?>;
        let initialLng = <?php echo $moto_taxi['longitude'] ? floatval($moto_taxi['longitude']) : 9.7679; ?>;
        let map = L.map('map').setView([initialLat, initialLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Marqueur draggable pour le moto-taxi
        let motoTaxiMarker = L.marker([initialLat, initialLng], {draggable: true}).addTo(map);
        motoTaxiMarker.bindPopup('Votre position').openPopup();

        // Quand on déplace le marqueur, on envoie la nouvelle position au backend
        motoTaxiMarker.on('dragend', function(e) {
            let pos = e.target.getLatLng();
            map.setView([pos.lat, pos.lng], map.getZoom());
            updatePosition(pos.lat, pos.lng);
        });

        // Bouton pour récupérer la position réelle via GPS
        const btnGeoloc = document.getElementById('btn-geoloc');
        if (btnGeoloc) {
            btnGeoloc.addEventListener('click', function() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        motoTaxiMarker.setLatLng([lat, lng]);
                        map.setView([lat, lng], 15);
                        updatePosition(lat, lng);
                    }, function(error) {
                        alert('Impossible de récupérer la position GPS.');
                    });
                } else {
                    alert('La géolocalisation n\'est pas supportée par ce navigateur.');
                }
            });
        }

        // Fonction pour envoyer la position au backend
        function updatePosition(lat, lng) {
            fetch('update_position.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    latitude: lat, 
                    longitude: lng 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Position mise à jour');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
        }

        // Gestion du statut en ligne/hors ligne
        document.getElementById('statusToggle').addEventListener('change', function() {
            const isOnline = this.checked;
            const statusIndicator = document.querySelector('.status-indicator');
            
            if (isOnline) {
                statusIndicator.className = 'status-indicator status-online';
                statusIndicator.innerHTML = '<i class="fas fa-circle"></i> En ligne';
                updateStatus('actif');
            } else {
                statusIndicator.className = 'status-indicator status-offline';
                statusIndicator.innerHTML = '<i class="fas fa-circle"></i> Hors ligne';
                updateStatus('inactif');
            }
        });

        function updateStatus(status) {
            fetch('update_moto_taxi_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Statut mis à jour');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
        }

        function accepterLivraison(livraisonId) {
            if (confirm('Voulez-vous accepter cette livraison ?')) {
                console.log('Début de la requête pour livraison:', livraisonId);
                
                fetch('accepter_livraison.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ livraison_id: livraisonId })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status + ' ' + response.statusText);
                    }
                    
                    return response.text().then(text => {
                        console.log('Response text:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Erreur parsing JSON:', e);
                            throw new Error('Réponse invalide du serveur: ' + text);
                        }
                    });
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        alert('Livraison acceptée avec succès !');
                        location.reload();
                    } else {
                        alert('Erreur: ' + (data.message || 'Erreur lors de l\'acceptation'));
                    }
                })
                .catch(error => {
                    console.error('Erreur complète:', error);
                    alert('Erreur de connexion lors de l\'acceptation: ' + error.message);
                });
            }
        }

        function voirDetails(livraisonId) {
            // Ouvrir une modal ou rediriger vers la page de détails
            window.open('livraison_details.php?id=' + livraisonId, '_blank');
        }

        // Actualisation automatique des demandes
        setInterval(function() {
            // Recharger les demandes disponibles
            fetch('get_demandes_disponibles.php')
            .then(response => response.json())
            .then(data => {
                // Mettre à jour l'affichage des demandes
                console.log('Demandes mises à jour');
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
        }, 30000); // Toutes les 30 secondes
    </script>
</body>
</html> 