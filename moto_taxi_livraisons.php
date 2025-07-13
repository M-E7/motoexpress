<?php
session_start();
require_once 'db.php';
require_once 'qr_generator.php';

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

// Filtre de statut
$statut = $_GET['statut'] ?? 'tous';
$statuts = [
    'tous' => 'Toutes',
    'en_attente' => 'En attente',
    'en_cours' => 'En cours',
    'terminee' => 'Terminées',
    'annulee' => 'Annulées'
];

// Préparer la requête selon le filtre
$where = "l.moto_taxi_id = ?";
$params = [$_SESSION['moto_taxi_id']];
if ($statut !== 'tous' && in_array($statut, array_keys($statuts))) {
    $where .= " AND l.statut = ?";
    $params[] = $statut;
}

$stmt = $pdo->prepare("
    SELECT l.*, u.nom as user_nom, u.prenom as user_prenom, u.telephone as user_telephone,
           qr.qr_path, qr.qr_filename, qr.date_creation as qr_date, qr.statut as qr_statut
    FROM livraisons l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN qr_codes_livraison qr ON l.id = qr.livraison_id AND qr.statut = 'actif'
    WHERE $where
    ORDER BY l.date_creation DESC
    LIMIT 100
");
$stmt->execute($params);
$livraisons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Livraisons - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; color: #1e293b; margin: 0; }
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07); }
        .navbar .logo { display: flex; align-items: center; font-size: 1.4rem; font-weight: bold; color: #2563eb; }
        .navbar .logo i { margin-right: 0.5rem; }
        .navbar .nav-links { display: flex; align-items: center; gap: 2rem; }
        .navbar .nav-link { color: #64748b; text-decoration: none; display: flex; flex-direction: column; align-items: center; font-size: 1rem; transition: color 0.2s; }
        .navbar .nav-link.active, .navbar .nav-link:hover { color: #2563eb; }
        .navbar .nav-link i { font-size: 1.2rem; margin-bottom: 0.2rem; }
        .navbar .nav-link span { font-size: 0.8rem; }
        .navbar .logout { color: #dc2626; margin-left: 1.5rem; text-decoration: underline; font-size: 1rem; }
        .main-content { max-width: 1200px; margin: 110px auto 0 auto; padding: 2rem 1rem; }
        .page-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 2rem; color: #2563eb; display: flex; align-items: center; gap: 1rem; }
        .filters { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .filter-btn { padding: 0.5rem 1.2rem; border: none; border-radius: 20px; background: #f3f4f6; color: #374151; font-weight: 500; cursor: pointer; transition: background 0.2s, color 0.2s; }
        .filter-btn.active, .filter-btn:hover { background: #2563eb; color: white; }
        .livraison-list { display: flex; flex-direction: column; gap: 1.5rem; }
        .livraison-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
        .livraison-header { display: flex; justify-content: space-between; align-items: center; }
        .livraison-client { font-weight: 600; color: #2563eb; }
        .livraison-status { display: inline-block; padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.9rem; font-weight: 500; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-en_cours { background: #dbeafe; color: #1e40af; }
        .status-terminee { background: #dcfce7; color: #166534; }
        .status-annulee { background: #fee2e2; color: #991b1b; }
        .livraison-infos { color: #64748b; font-size: 0.98rem; display: flex; flex-wrap: wrap; gap: 2rem; }
        .livraison-infos > div { min-width: 180px; }
        .livraison-actions { display: flex; gap: 1rem; margin-top: 1rem; }
        .btn { padding: 0.5rem 1.2rem; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-success { background: #16a34a; color: white; }
        .btn-success:hover { background: #15803d; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-info { background: #0891b2; color: white; }
        .btn-info:hover { background: #0e7490; }
        .btn-warning { background: #d97706; color: white; }
        .btn-warning:hover { background: #b45309; }
        .qr-modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .qr-modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 400px; text-align: center; }
        .qr-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .qr-close:hover { color: #000; }
        .qr-image { max-width: 100%; height: auto; border-radius: 8px; margin: 15px 0; }
        @media (max-width: 768px) { .livraison-infos { flex-direction: column; gap: 0.5rem; } }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo"><i class="fas fa-motorcycle"></i> MotoExpress</div>
    <div class="nav-links">
        <a href="moto_taxi_home.php" class="nav-link"><i class="fas fa-home"></i><span>Accueil</span></a>
        <a href="moto_taxi_livraisons.php" class="nav-link active"><i class="fas fa-route"></i><span>Livraisons</span></a>
        <a href="moto_taxi_messages.php" class="nav-link"><i class="fas fa-comments"></i><span>Messages</span></a>
        <a href="moto_taxi_gains.php" class="nav-link"><i class="fas fa-wallet"></i><span>Gains</span></a>
        <a href="moto_taxi_profil.php" class="nav-link"><i class="fas fa-user"></i><span>Profil</span></a>
        <a href="moto_taxi_logout.php" class="logout">Déconnexion</a>
    </div>
</nav>
<div class="main-content">
    <div class="page-title"><i class="fas fa-route"></i> Mes Livraisons</div>
    <div class="filters">
        <?php foreach ($statuts as $key => $label): ?>
            <a href="?statut=<?php echo $key; ?>" class="filter-btn<?php if ($statut === $key) echo ' active'; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="livraison-list">
        <?php if (empty($livraisons)): ?>
            <div style="text-align:center; color:#64748b; padding:2rem;">Aucune livraison trouvée.</div>
        <?php else: ?>
            <?php foreach ($livraisons as $liv): ?>
                <div class="livraison-card">
                    <div class="livraison-header">
                        <div class="livraison-client"><i class="fas fa-user"></i> <?php echo htmlspecialchars($liv['user_prenom'].' '.$liv['user_nom']); ?></div>
                        <span class="livraison-status status-<?php echo $liv['statut']; ?>"><?php echo ucfirst(str_replace('_',' ',$liv['statut'])); ?></span>
                    </div>
                    <div class="livraison-infos">
                        <div><i class="fas fa-map-marker-alt"></i> Départ : <?php echo htmlspecialchars($liv['adresse_depart']); ?></div>
                        <div><i class="fas fa-flag-checkered"></i> Arrivée : <?php echo htmlspecialchars($liv['adresse_arrivee']); ?></div>
                        <div><i class="fas fa-clock"></i> Demandée le : <?php echo date('d/m/Y H:i', strtotime($liv['date_creation'])); ?></div>
                        <div><i class="fas fa-money-bill-wave"></i> Montant : <b><?php echo number_format($liv['montant'],0,',',' '); ?> FCFA</b></div>
                        <div><i class="fas fa-box"></i> Type : <?php echo ucfirst($liv['type_livraison']); ?></div>
                        <?php if ($liv['fragile']): ?><div><i class="fas fa-exclamation-triangle"></i> Fragile</div><?php endif; ?>
                        <?php if ($liv['urgence']): ?><div><i class="fas fa-bolt"></i> Urgence</div><?php endif; ?>
                    </div>
                    <div class="livraison-actions">
                        <a href="moto_taxi_messages.php?livraison_id=<?php echo $liv['id']; ?>" class="btn btn-primary"><i class="fas fa-comments"></i> Messagerie</a>
                        <?php if ($liv['statut'] === 'en_cours'): ?>
                            <button class="btn btn-success" onclick="terminerLivraison(<?php echo $liv['id']; ?>)"><i class="fas fa-check"></i> Marquer comme terminée</button>
                            <button class="btn btn-danger" onclick="annulerLivraison(<?php echo $liv['id']; ?>)"><i class="fas fa-times"></i> Annuler</button>
                        <?php endif; ?>
                        
                        
                        <!-- QR Code pour les livraisons en cours -->
                        <?php if ($liv['statut'] === 'en_cours' && $liv['qr_path']): ?>
                            <button class="btn btn-info" onclick="afficherQRCode(<?php echo $liv['id']; ?>, '<?php echo htmlspecialchars($liv['qr_path']); ?>')">
                                <i class="fas fa-qrcode"></i> Voir QR Code
                            </button>
                        <?php elseif ($liv['statut'] === 'en_cours'): ?>
                            <button class="btn btn-warning" onclick="genererQRCode(<?php echo $liv['id']; ?>)">
                                <i class="fas fa-qrcode"></i> Générer QR Code
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal QR Code -->
<div id="qrModal" class="qr-modal">
    <div class="qr-modal-content">
        <span class="qr-close" onclick="fermerQRModal()">&times;</span>
        <h3><i class="fas fa-qrcode"></i> QR Code de Confirmation</h3>
        <div id="qrModalContent">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
    </div>
</div>
<script>
function terminerLivraison(id) {
    if(confirm('Confirmer la fin de la livraison ?\n\nLe paiement sera automatiquement effectué avec une commission de 5%.')) {
        console.log('Début de la requête pour livraison:', id);
        
        fetch('terminer_livraison.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({livraison_id: id})
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
            
            if(data.success) {
                // Afficher les détails du paiement
                let message = 'Livraison terminée avec succès!\n\n';
                message += 'Détails du paiement:\n';
                message += '• Gain: ' + new Intl.NumberFormat('fr-FR').format(data.gain) + ' FCFA\n';
                message += '• Commission (5%): ' + new Intl.NumberFormat('fr-FR').format(data.commission) + ' FCFA\n';
                message += '• Nouveau solde: ' + new Intl.NumberFormat('fr-FR').format(data.nouveau_solde) + ' FCFA';
                
                alert(message);
                location.reload();
            } else {
                alert('Erreur: ' + (data.message || 'Erreur lors de la finalisation'));
            }
        })
        .catch(error => {
            console.error('Erreur complète:', error);
            alert('Erreur de connexion: ' + error.message + '\n\nVérifiez la console pour plus de détails.');
        });
    }
}
function annulerLivraison(id) {
    if(confirm('Voulez-vous vraiment annuler cette livraison ?')) {
        fetch('annuler_livraison.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({livraison_id: id})
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur HTTP: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if(data.success) {
                alert('Livraison annulée avec succès !');
                location.reload();
            } else {
                alert('Erreur: ' + (data.message || 'Erreur lors de l\'annulation'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion lors de l\'annulation');
        });
    }
}

// Fonctions pour les QR codes
function afficherQRCode(livraisonId, qrPath) {
    const modal = document.getElementById('qrModal');
    const content = document.getElementById('qrModalContent');
    
    content.innerHTML = `
        <div class="mb-3">
            <h6 class="text-muted">Livraison #${livraisonId}</h6>
        </div>
        <div class="mb-3">
            <img src="${qrPath}" alt="QR Code" class="qr-image">
        </div>
        <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; border: 1px solid #0ea5e9;">
            <i class="fas fa-info-circle" style="color: #0ea5e9;"></i>
            <strong>Instructions :</strong>
            <ul style="text-align: left; margin: 10px 0;">
                <li>Présentez ce QR code au client</li>
                <li>Le client doit le scanner avec son téléphone</li>
                <li>Cela confirme la rencontre entre vous</li>
            </ul>
        </div>
        <div style="margin-top: 15px;">
            <button class="btn btn-primary" onclick="telechargerQRCode('${qrPath}')">
                <i class="fas fa-download"></i> Télécharger
            </button>
        </div>
    `;
    
    modal.style.display = 'block';
}

function genererQRCode(livraisonId) {
    if(confirm('Générer un QR code pour cette livraison ?')) {
        fetch('generer_qr_livraison.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({livraison_id: livraisonId})
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('QR code généré avec succès !');
                location.reload();
            } else {
                alert('Erreur : ' + (data.message || 'Erreur lors de la génération'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
        });
    }
}

function fermerQRModal() {
    document.getElementById('qrModal').style.display = 'none';
}

function telechargerQRCode(qrPath) {
    const link = document.createElement('a');
    link.href = qrPath;
    link.download = 'qr_livraison.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('qrModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>
</body>
</html> 