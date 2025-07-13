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

// Récupérer les conversations actives (livraisons en cours ou récentes)
$stmt = $pdo->prepare("
    SELECT l.id as livraison_id, l.adresse_depart, l.adresse_arrivee, l.statut, l.date_creation,
           m.id as moto_taxi_id, m.nom as mototaxi_nom, m.telephone as moto_taxi_telephone, m.photo_profil as moto_taxi_photo,
           (SELECT COUNT(*) FROM messages WHERE livraison_id = l.id AND type_expediteur = 'moto_taxi' AND lu = 0) as messages_non_lus,
           (SELECT MAX(date_envoi) FROM messages WHERE livraison_id = l.id) as dernier_message
    FROM livraisons l
    LEFT JOIN moto_taxis m ON l.moto_taxi_id = m.id
    WHERE l.user_id = ? AND l.statut IN ('en_attente', 'en_cours', 'livree')
    ORDER BY dernier_message DESC, l.date_creation DESC
");
$stmt->execute([$_SESSION['user_id']]);
$conversations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - MotoExpress</title>
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
        .chat-container { display: grid; grid-template-columns: 300px 1fr; gap: 0; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #e2e8f0; height: calc(100vh - 200px); overflow: hidden; }
        .conversations-list { background: #f8fafc; border-right: 1px solid #e2e8f0; overflow-y: auto; max-height: calc(100vh - 200px); }
        .conversation-item { padding: 1rem; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 1rem; }
        .conversation-item:hover { background: #e2e8f0; }
        .conversation-item.active { background: #dbeafe; border-right: 3px solid #2563eb; }
        .conversation-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: #2563eb; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .conversation-info { flex: 1; min-width: 0; }
        .conversation-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; }
        .conversation-name { font-weight: 600; font-size: 0.9rem; }
        .conversation-time { font-size: 0.8rem; color: #64748b; }
        .conversation-preview { font-size: 0.85rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .unread-badge { background: #2563eb; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; }
        .chat-area { display: flex; flex-direction: column; height: 100%; }
        .chat-header { padding: 1rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1rem; background: #fff; flex-shrink: 0; }
        .chat-header-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #16a34a; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .chat-header-info { flex: 1; }
        .chat-header-name { font-weight: 600; }
        .chat-header-status { font-size: 0.8rem; color: #64748b; }
        .chat-header-actions { display: flex; gap: 0.5rem; }
        .btn-icon { background: none; border: none; padding: 0.5rem; border-radius: 50%; cursor: pointer; transition: background 0.2s; }
        .btn-icon:hover { background: #f1f5f9; }
        .messages-container { flex: 1; padding: 1rem; overflow-y: auto; background: #f8fafc; min-height: 0; max-height: 400px; }
        .message { margin-bottom: 1rem; display: flex; }
        .message.user { justify-content: flex-end; }
        .message.mototaxi { justify-content: flex-start; }
        .message-bubble { max-width: 70%; padding: 0.8rem 1rem; border-radius: 18px; position: relative; }
        .message.user .message-bubble { background: #2563eb; color: white; border-bottom-right-radius: 4px; }
        .message.mototaxi .message-bubble { background: white; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }
        .message-time { font-size: 0.7rem; margin-top: 0.3rem; opacity: 0.7; }
        .message.user .message-time { text-align: right; }
        .message.mototaxi .message-time { text-align: left; }
        .message-input-container { padding: 1rem; border-top: 1px solid #e2e8f0; background: #fff; display: flex; gap: 0.5rem; align-items: flex-end; flex-shrink: 0; }
        .message-input { flex: 1; border: 1px solid #e2e8f0; border-radius: 20px; padding: 0.8rem 1rem; resize: none; outline: none; font-family: inherit; }
        .message-input:focus { border-color: #2563eb; }
        .message-actions { display: flex; gap: 0.5rem; }
        .btn-send { background: #2563eb; color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; transition: background 0.2s; }
        .btn-send:hover { background: #1e40af; }
        .btn-send:disabled { background: #cbd5e1; cursor: not-allowed; }
        .quick-messages { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .quick-message { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 15px; padding: 0.3rem 0.8rem; font-size: 0.8rem; cursor: pointer; transition: background 0.2s; }
        .quick-message:hover { background: #e2e8f0; }
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; color: #cbd5e1; }
        .message-type-indicator { font-size: 0.8rem; margin-bottom: 0.3rem; opacity: 0.7; }
        .message-photo { max-width: 200px; border-radius: 8px; margin-top: 0.5rem; }
        .message-location { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem; margin-top: 0.5rem; }
        .location-icon { color: #dc2626; margin-right: 0.3rem; }
        .message-audio { margin-top: 0.5rem; }
        .message-audio audio { border-radius: 20px; }
        @media (max-width: 768px) { 
            .chat-container { grid-template-columns: 1fr; }
            .conversations-list { display: none; }
            .conversations-list.show { display: block; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; }
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
            <a href="messages.php" class="nav-link active">
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
    <div class="welcome">
        <img src="<?php echo $photo; ?>" alt="Photo de profil" class="avatar">
        <div class="msg">Messages</div>
    </div>

    <div class="chat-container">
        <!-- Liste des conversations -->
        <div class="conversations-list" id="conversationsList">
            <?php if (empty($conversations)): ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Aucune conversation</h3>
                    <p>Vous n'avez pas encore de livraison en cours.</p>
                    <a href="user_home.php" class="btn btn-primary">Commander maintenant</a>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-item" onclick="loadConversation(<?php echo $conv['livraison_id']; ?>)">
                        <div class="conversation-avatar">
                            <?php 
                            $photo = $conv['moto_taxi_photo'] ? 
                                (strpos($conv['moto_taxi_photo'], 'uploads/') === 0 ? $conv['moto_taxi_photo'] : 'uploads/moto_taxis/' . $conv['moto_taxi_photo']) : 
                                'https://ui-avatars.com/api/?name=' . urlencode($conv['mototaxi_nom'] ?: 'M') . '&background=2563eb&color=fff&size=64';
                            ?>
                            <img src="<?php echo $photo; ?>" alt="Photo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($conv['mototaxi_nom'] ?: 'M'); ?>&background=2563eb&color=fff&size=64';">
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-header">
                                <span class="conversation-name"><?php echo htmlspecialchars($conv['mototaxi_nom'] ?: 'Moto-taxi'); ?></span>
                                <span class="conversation-time">
                                    <?php echo $conv['dernier_message'] ? date('H:i', strtotime($conv['dernier_message'])) : ''; ?>
                                </span>
                            </div>
                            <div class="conversation-preview">
                                Livraison #<?php echo $conv['livraison_id']; ?> - <?php echo ucfirst($conv['statut']); ?>
                            </div>
                        </div>
                        <?php if ($conv['messages_non_lus'] > 0): ?>
                            <div class="unread-badge"><?php echo $conv['messages_non_lus']; ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Zone de chat -->
        <div class="chat-area" id="chatArea">
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>Sélectionnez une conversation</h3>
                <p>Choisissez une livraison pour commencer à discuter avec le moto-taxi.</p>
            </div>
        </div>
    </div>
</div>

<script>
let currentLivraisonId = null;
let messageInterval = null;

// Charger une conversation
function loadConversation(livraisonId) {
    currentLivraisonId = livraisonId;
    
    // Mettre à jour l'interface
    document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    // Charger les messages
    loadMessages(livraisonId);
    
    // Démarrer le rafraîchissement automatique
    if (messageInterval) clearInterval(messageInterval);
    messageInterval = setInterval(() => loadMessages(livraisonId), 3000);
}

// Charger les messages d'une livraison
function loadMessages(livraisonId) {
    console.log('Chargement des messages pour livraison:', livraisonId);
    fetch(`get_messages.php?livraison_id=${livraisonId}`)
        .then(response => {
            console.log('Réponse reçue:', response);
            return response.json();
        })
        .then(data => {
            console.log('Données reçues:', data);
            if (data.success) {
                renderChat(data);
            } else {
                console.error('Erreur dans la réponse:', data.message);
                // Afficher un message d'erreur dans le chat
                const chatArea = document.getElementById('chatArea');
                chatArea.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Erreur de chargement</h3>
                        <p>${data.message || 'Impossible de charger les messages'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des messages:', error);
            // Afficher un message d'erreur dans le chat
            const chatArea = document.getElementById('chatArea');
            chatArea.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Erreur de connexion</h3>
                    <p>Impossible de se connecter au serveur</p>
                </div>
            `;
        });
}

// Afficher le chat
function renderChat(data) {
    const chatArea = document.getElementById('chatArea');
    
    // Sauvegarder le contenu du textarea s'il existe
    const messageInput = document.getElementById('messageInput');
    const savedMessage = messageInput ? messageInput.value : '';
    
    chatArea.innerHTML = `
        <div class="chat-header" style="flex-shrink: 0;">
            <div class="chat-header-avatar">
                ${data.moto_taxi_photo ? 
                    `<img src="${data.moto_taxi_photo.startsWith('uploads/') ? data.moto_taxi_photo : 'uploads/moto_taxis/' + data.moto_taxi_photo}" alt="Photo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">` :
                    'M'
                }
            </div>
            <div class="chat-header-info">
                <div class="chat-header-name">${data.mototaxi_nom}</div>
                <div class="chat-header-status">Livraison #${data.livraison_id} - ${data.statut}</div>
            </div>
            <div class="chat-header-actions">
                <button class="btn-icon" onclick="shareLocation()" title="Partager ma position">
                    <i class="fas fa-location-arrow"></i>
                </button>
                <button class="btn-icon" onclick="callMototaxi()" title="Appeler">
                    <i class="fas fa-phone"></i>
                </button>
            </div>
        </div>
        
        <div class="messages-container" id="messagesContainer" style="flex: 1; overflow-y: auto; min-height: 0; max-height: calc(100vh - 400px);">
            ${renderMessages(data.messages)}
        </div>
        
        <div class="message-input-container" style="flex-shrink: 0;">
            <div style="width: 100%;">
                <div class="quick-messages">
                    <div class="quick-message" onclick="sendQuickMessage('Où êtes-vous ?')">Où êtes-vous ?</div>
                    <div class="quick-message" onclick="sendQuickMessage('J\'arrive dans 5 minutes')">J'arrive dans 5 min</div>
                    <div class="quick-message" onclick="sendQuickMessage('Merci !')">Merci !</div>
                    <div class="quick-message" onclick="sendQuickMessage('Problème avec la livraison')">Problème</div>
                </div>
                <textarea class="message-input" id="messageInput" placeholder="Tapez votre message..." rows="1" onkeypress="handleKeyPress(event)"></textarea>
            </div>
            <div class="message-actions">
                <button class="btn-icon" onclick="attachFile()" title="Joindre un fichier">
                    <i class="fas fa-paperclip"></i>
                </button>
                <button class="btn-icon" onclick="recordVoice()" title="Message vocal">
                    <i class="fas fa-microphone"></i>
                </button>
                <button class="btn-send" onclick="sendMessage()" id="sendButton">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    `;
    
    // Faire défiler vers le bas
    const messagesContainer = document.getElementById('messagesContainer');
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    // Restaurer le contenu du textarea
    const newMessageInput = document.getElementById('messageInput');
    if (newMessageInput && savedMessage) {
        newMessageInput.value = savedMessage;
        // Remettre le focus sur le textarea
        newMessageInput.focus();
        // Remettre le curseur à la fin du texte
        newMessageInput.setSelectionRange(savedMessage.length, savedMessage.length);
    }
    
    // Marquer les messages comme lus
    markMessagesAsRead(data.livraison_id);
    
    // Rebinder les événements pour le nouveau textarea
    const messageInputElement = document.getElementById('messageInput');
    if (messageInputElement) {
        // Auto-resize
        messageInputElement.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        
        // Focus automatique
        messageInputElement.focus();
    }
}

// Afficher les messages
function renderMessages(messages) {
    if (messages.length === 0) {
        // Ajouter des messages de test pour forcer le scroll
        let testMessages = '';
        for (let i = 1; i <= 20; i++) {
            testMessages += `
                <div class="message ${i % 2 === 0 ? 'user' : 'mototaxi'}">
                    <div class="message-bubble">
                        <div class="message-type-indicator">
                            <i class="fas fa-comment"></i> Message
                        </div>
                        <div>Message de test numéro ${i} pour tester le scroll</div>
                        <div class="message-time">${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</div>
                    </div>
                </div>
            `;
        }
        return testMessages;
    }
    
    return messages.map(message => {
        // Correction : les messages du moto-taxi doivent s'afficher à gauche (mototaxi), ceux de l'utilisateur à droite (user)
        const isUser = message.expediteur === 'user';
        const messageClass = isUser ? 'user' : 'mototaxi';
        const time = new Date(message.date_envoi).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        
        let content = `<div class="message ${messageClass}">
            <div class="message-bubble">
                <div class="message-type-indicator">
                    <i class="fas fa-${getMessageIcon(message.type)}"></i> ${getMessageTypeLabel(message.type)}
                </div>
                <div>${escapeHtml(message.contenu)}</div>`;
        
        // Ajouter le contenu spécifique au type de message
        if (message.type === 'photo' && message.fichier) {
            content += `<img src="${message.fichier}" alt="Photo" class="message-photo" onclick="openImage('${message.fichier}')">`;
        } else if (message.type === 'audio' && message.fichier) {
            content += `<div class="message-audio">
                <audio controls style="max-width: 200px;">
                    <source src="${message.fichier}" type="audio/wav">
                    Votre navigateur ne supporte pas l'élément audio.
                </audio>
            </div>`;
        } else if (message.type === 'gps' && message.fichier) {
            const coords = JSON.parse(message.fichier);
            content += `<div class="message-location">
                <i class="fas fa-map-marker-alt location-icon"></i>
                <a href="https://maps.google.com/?q=${coords.lat},${coords.lng}" target="_blank">
                    Voir sur la carte
                </a>
            </div>`;
        }
        
        content += `<div class="message-time">${time}</div>
            </div>
        </div>`;
        
        return content;
    }).join('');
}

// Obtenir l'icône du type de message
function getMessageIcon(type) {
    const icons = {
        'texte': 'comment',
        'photo': 'image',
        'audio': 'microphone',
        'gps': 'map-marker-alt',
        'statut': 'info-circle',
        'predefini': 'comment-dots'
    };
    return icons[type] || 'comment';
}

// Obtenir le label du type de message
function getMessageTypeLabel(type) {
    const labels = {
        'texte': 'Message',
        'photo': 'Photo',
        'audio': 'Message vocal',
        'gps': 'Localisation',
        'statut': 'Statut',
        'predefini': 'Message prédéfini'
    };
    return labels[type] || 'Message';
}

// Échapper le HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Envoyer un message
function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message || !currentLivraisonId) return;
    
    const sendButton = document.getElementById('sendButton');
    sendButton.disabled = true;
    
    fetch('send_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            livraison_id: currentLivraisonId,
            contenu: message,
            type: 'texte'
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Réponse send_message:', data);
        if (data.success) {
            input.value = '';
            loadMessages(currentLivraisonId);
        } else {
            alert('Erreur lors de l\'envoi du message: ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'envoi du message');
    })
    .finally(() => {
        sendButton.disabled = false;
    });
}

// Envoyer un message rapide
function sendQuickMessage(message) {
    if (!currentLivraisonId) return;
    
    fetch('send_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            livraison_id: currentLivraisonId,
            contenu: message,
            type: 'predefini'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMessages(currentLivraisonId);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

// Gérer la touche Entrée
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

// Marquer les messages comme lus
function markMessagesAsRead(livraisonId) {
    fetch('mark_messages_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            livraison_id: livraisonId
        })
    });
}

// Partager la localisation
function shareLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const coords = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            
            fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    livraison_id: currentLivraisonId,
                    contenu: 'Ma position actuelle',
                    type: 'gps',
                    fichier: JSON.stringify(coords)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMessages(currentLivraisonId);
                }
            });
        });
    } else {
        alert('La géolocalisation n\'est pas supportée par votre navigateur');
    }
}

// Appeler le moto-taxi
function callMototaxi() {
    // Récupérer le numéro du moto-taxi depuis la conversation active
    const activeConversation = document.querySelector('.conversation-item.active');
    if (activeConversation) {
        // Ici on pourrait récupérer le numéro depuis les données
        alert('Fonctionnalité d\'appel à implémenter');
    }
}

// Joindre un fichier
function attachFile() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            uploadFile(file);
        }
    };
    input.click();
}

// Variables pour l'enregistrement vocal
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;

// Enregistrer un message vocal
function recordVoice() {
    const recordButton = document.querySelector('.btn-icon[onclick="recordVoice()"]');
    
    if (!isRecording) {
        // Démarrer l'enregistrement
        startRecording();
        if (recordButton) {
            recordButton.innerHTML = '<i class="fas fa-stop"></i>';
            recordButton.title = 'Arrêter l\'enregistrement';
            recordButton.style.background = '#dc2626';
        }
    } else {
        // Arrêter l'enregistrement
        stopRecording();
        if (recordButton) {
            recordButton.innerHTML = '<i class="fas fa-microphone"></i>';
            recordButton.title = 'Message vocal';
            recordButton.style.background = '';
        }
    }
}

// Démarrer l'enregistrement
function startRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('L\'enregistrement vocal n\'est pas supporté par votre navigateur');
        return;
    }
    
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            
            mediaRecorder.ondataavailable = (event) => {
                audioChunks.push(event.data);
            };
            
            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                sendVoiceMessage(audioBlob);
                
                // Arrêter le stream
                stream.getTracks().forEach(track => track.stop());
            };
            
            mediaRecorder.start();
            isRecording = true;
            
            // Afficher un indicateur d'enregistrement
            showRecordingIndicator();
        })
        .catch(error => {
            console.error('Erreur lors de l\'accès au microphone:', error);
            alert('Impossible d\'accéder au microphone. Vérifiez les permissions.');
        });
}

// Arrêter l'enregistrement
function stopRecording() {
    if (mediaRecorder && isRecording) {
        mediaRecorder.stop();
        isRecording = false;
        hideRecordingIndicator();
    }
}

// Envoyer le message vocal
function sendVoiceMessage(audioBlob) {
    const formData = new FormData();
    formData.append('audio', audioBlob, 'message_vocal.wav');
    formData.append('livraison_id', currentLivraisonId);
    
    fetch('upload_voice_message.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMessages(currentLivraisonId);
        } else {
            alert('Erreur lors de l\'envoi du message vocal: ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'envoi du message vocal');
    });
}

// Afficher l'indicateur d'enregistrement
function showRecordingIndicator() {
    const chatArea = document.getElementById('chatArea');
    const indicator = document.createElement('div');
    indicator.id = 'recordingIndicator';
    indicator.innerHTML = `
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                    background: rgba(0,0,0,0.8); color: white; padding: 1rem; border-radius: 10px; 
                    z-index: 1000; display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 10px; height: 10px; background: #dc2626; border-radius: 50%; animation: pulse 1s infinite;"></div>
            <span>Enregistrement en cours...</span>
        </div>
        <style>
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
        </style>
    `;
    document.body.appendChild(indicator);
}

// Masquer l'indicateur d'enregistrement
function hideRecordingIndicator() {
    const indicator = document.getElementById('recordingIndicator');
    if (indicator) {
        indicator.remove();
    }
}

// Ouvrir une image
function openImage(src) {
    window.open(src, '_blank');
}

// Uploader un fichier
function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('livraison_id', currentLivraisonId);
    
    fetch('upload_message_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadMessages(currentLivraisonId);
        } else {
            alert('Erreur lors de l\'upload du fichier');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'upload du fichier');
    });
}

// Auto-resize du textarea (géré dans renderChat maintenant)
</script>
</body>
</html> 