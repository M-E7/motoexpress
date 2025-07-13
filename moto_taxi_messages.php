<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['moto_taxi_id'])) {
    header('Location: moto_taxi_login.php');
    exit;
}
$moto_taxi_id = $_SESSION['moto_taxi_id'];

// Récupérer les infos du moto-taxi
$stmt = $pdo->prepare("SELECT nom, prenom, photo_profil FROM moto_taxis WHERE id = ?");
$stmt->execute([$moto_taxi_id]);
$moto_taxi = $stmt->fetch();

// Liste des conversations (livraisons où le moto-taxi est impliqué)
$stmt = $pdo->prepare("
    SELECT l.id, l.statut, l.adresse_depart, l.adresse_arrivee, l.date_creation, 
           u.nom, u.prenom, u.photo_profil as user_photo,
           (SELECT COUNT(*) FROM messages WHERE livraison_id = l.id AND type_expediteur = 'user' AND lu = 0) as messages_non_lus,
           (SELECT MAX(date_envoi) FROM messages WHERE livraison_id = l.id) as dernier_message,
           (SELECT contenu FROM messages WHERE livraison_id = l.id ORDER BY date_envoi DESC LIMIT 1) as dernier_contenu
    FROM livraisons l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.moto_taxi_id = ? 
    ORDER BY dernier_message DESC, l.date_creation DESC 
    LIMIT 50
");
$stmt->execute([$moto_taxi_id]);
$conversations = $stmt->fetchAll();

// Conversation sélectionnée
$livraison_id = isset($_GET['livraison_id']) ? intval($_GET['livraison_id']) : ($conversations[0]['id'] ?? null);

// Récupérer les messages de la conversation sélectionnée
$messages = [];
$current_client = null;
if ($livraison_id) {
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE livraison_id = ? ORDER BY date_envoi ASC");
    $stmt->execute([$livraison_id]);
    $messages = $stmt->fetchAll();
    
    // Récupérer les infos du client pour cette livraison
    foreach ($conversations as $conv) {
        if ($conv['id'] == $livraison_id) {
            $current_client = $conv;
            break;
        }
    }
}

// Messages prédéfinis
$stmt = $pdo->prepare("SELECT * FROM messages_predefinis WHERE categorie = 'moto_taxi' AND actif = 1 ORDER BY ordre_affichage ASC");
$stmt->execute();
$quick_msgs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - MotoExpress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            color: #1e293b;
            margin: 0;
            height: 100vh;
            overflow: hidden;
        }

        /* Navbar */
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

        .navbar .logo i {
            margin-right: 0.5rem;
        }

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

        .navbar .nav-link.active, .navbar .nav-link:hover {
            color: #2563eb;
        }

        .navbar .nav-link i {
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }

        .navbar .nav-link span {
            font-size: 0.8rem;
        }

        .navbar .logout {
            color: #dc2626;
            margin-left: 1.5rem;
            text-decoration: underline;
            font-size: 1rem;
        }

        /* Main Container */
        .main-container {
            display: flex;
            height: calc(100vh - 110px);
            margin-top: 110px;
            background: #fff;
        }

        /* Conversations List */
        .conversations-list {
            width: 400px;
            background: #fff;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }

        .conversations-header {
            background: #f8fafc;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .conversations-header .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .conversations-header .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .conversations-header .info h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .conversations-header .info p {
            font-size: 0.8rem;
            color: #64748b;
        }

        .conversations-search {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .search-input {
            width: 100%;
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 8px;
            background: #f1f5f9;
            font-size: 0.9rem;
            outline: none;
        }

        .search-input::placeholder {
            color: #64748b;
        }

        .conversations-container {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .conversation-item:hover {
            background: #f8fafc;
        }

        .conversation-item.active {
            background: #dbeafe;
        }

        .conversation-avatar {
            width: 49px;
            height: 49px;
            border-radius: 50%;
            background: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }

        .conversation-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .conversation-content {
            flex: 1;
            min-width: 0;
        }

        .conversation-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.2rem;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
        }

        .conversation-time {
            font-size: 0.75rem;
            color: #64748b;
        }

        .conversation-preview {
            font-size: 0.8rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background: #2563eb;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
            flex-shrink: 0;
        }

        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
            position: relative;
            min-height: 400px;
        }

        .chat-header {
            background: #fff;
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }

        .chat-header-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .chat-header-info {
            flex: 1;
            min-width: 0;
        }

        .chat-header-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
        }

        .chat-header-status {
            font-size: 0.8rem;
            color: #64748b;
        }

        .chat-header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.2s;
            color: #64748b;
        }

        .btn-icon:hover {
            background: #f1f5f9;
        }

        /* Messages Container */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8fafc;
            min-height: 200px;
        }

        .message {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: flex-end;
        }

        .message.user {
            justify-content: flex-start;
        }

        .message.moto_taxi {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 65%;
            padding: 0.6rem 0.8rem;
            border-radius: 7.5px;
            position: relative;
            word-wrap: break-word;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .message.user .message-bubble {
            background: white;
            color: #1e293b;
            border-bottom-left-radius: 2px;
            border: 1px solid #e2e8f0;
        }

        .message.moto_taxi .message-bubble {
            background: #dbeafe;
            color: #1e293b;
            border-bottom-right-radius: 2px;
        }

        .message-time {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }

        .message.user .message-time {
            justify-content: flex-start;
        }

        .message.moto_taxi .message-time {
            justify-content: flex-end;
        }

        .message-status {
            font-size: 0.6rem;
            margin-left: 0.2rem;
        }

        .message-photo {
            max-width: 200px;
            border-radius: 8px;
            margin-top: 0.5rem;
            cursor: pointer;
        }

        .message-location {
            background: rgba(255,255,255,0.8);
            border-radius: 8px;
            padding: 0.5rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message-location a {
            color: #00a884;
            text-decoration: none;
            font-weight: 500;
        }

        .message-audio {
            margin-top: 0.5rem;
        }

        .message-audio audio {
            border-radius: 20px;
            max-width: 200px;
        }

        /* Input Area */
        .input-area {
            background: #fff;
            padding: 0.8rem 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            flex-shrink: 0;
            min-height: 80px;
            position: relative;
            z-index: 10;
        }

        .input-container {
            flex: 1;
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.5rem;
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            border: 1px solid #e2e8f0;
        }

        .message-input {
            flex: 1;
            border: none;
            outline: none;
            resize: none;
            font-family: inherit;
            font-size: 0.9rem;
            max-height: 100px;
            min-height: 20px;
            padding: 0.3rem 0;
        }

        .input-actions {
            display: flex;
            gap: 0.3rem;
        }

        .btn-send {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-send:hover {
            background: #1e40af;
        }

        .btn-send:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        /* Quick Messages */
        .quick-messages {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .quick-message {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.2s;
            color: #1e293b;
        }

        .quick-message:hover {
            background: #f1f5f9;
        }

        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #64748b;
            text-align: center;
            padding: 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .empty-state p {
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .conversations-list {
                width: 100%;
                position: absolute;
                top: 0;
                left: 0;
                height: 100%;
                z-index: 10;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .conversations-list.show {
                transform: translateX(0);
            }

            .chat-area {
                width: 100%;
            }

            .navbar .nav-links {
                gap: 1rem;
            }

            .navbar .nav-link span {
                display: none;
            }
        }

        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #667781;
        }

        .loading i {
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Status indicators */
        .status-en_attente { color: #f59e0b; }
        .status-en_cours { color: #3b82f6; }
        .status-terminee { color: #10b981; }
        .status-annulee { color: #ef4444; }

        /* Disabled state */
        .message-input:disabled {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        .btn-icon:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-send:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        .quick-message:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
<nav class="navbar">
        <div class="logo">
            <i class="fas fa-motorcycle"></i>
            MotoExpress
        </div>
    <div class="nav-links">
            <a href="moto_taxi_home.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Accueil</span>
            </a>
            <a href="moto_taxi_livraisons.php" class="nav-link">
                <i class="fas fa-route"></i>
                <span>Livraisons</span>
            </a>
            <a href="moto_taxi_messages.php" class="nav-link active">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            <a href="moto_taxi_gains.php" class="nav-link">
                <i class="fas fa-wallet"></i>
                <span>Gains</span>
            </a>
            <a href="moto_taxi_profil.php" class="nav-link">
                <i class="fas fa-user"></i>
                <span>Profil</span>
            </a>
        <a href="moto_taxi_logout.php" class="logout">Déconnexion</a>
    </div>
</nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Conversations List -->
        <div class="conversations-list" id="conversationsList">
            <div class="conversations-header">
                <div class="avatar">
                    <?php if ($moto_taxi['photo_profil']): ?>
                        <img src="<?php echo htmlspecialchars($moto_taxi['photo_profil']); ?>" alt="Photo">
                    <?php else: ?>
                        <?php echo strtoupper(substr($moto_taxi['prenom'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="info">
                    <h3><?php echo htmlspecialchars($moto_taxi['prenom'] . ' ' . $moto_taxi['nom']); ?></h3>
                    <p>Moto-taxi</p>
                </div>
            </div>

            <div class="conversations-search">
                <input type="text" class="search-input" placeholder="Rechercher une conversation..." id="searchInput">
            </div>

            <div class="conversations-container">
                <?php if (empty($conversations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Aucune conversation</h3>
                        <p>Vous n'avez pas encore de livraison en cours.</p>
                    </div>
                <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item" 
                             onclick="loadConversation(<?php echo $conv['id']; ?>, this)">
                            <div class="conversation-avatar">
                                <?php 
                                $user_photo = $conv['user_photo'] ? 
                                    (strpos($conv['user_photo'], 'uploads/') === 0 ? $conv['user_photo'] : 'uploads/users/' . $conv['user_photo']) : 
                                    'https://ui-avatars.com/api/?name=' . urlencode($conv['prenom'] . ' ' . $conv['nom']) . '&background=00a884&color=fff&size=64';
                                ?>
                                <img src="<?php echo $user_photo; ?>" alt="Photo" 
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($conv['prenom'] . ' ' . $conv['nom']); ?>&background=00a884&color=fff&size=64';">
                            </div>
                            <div class="conversation-content">
                                <div class="conversation-header-row">
                                    <span class="conversation-name"><?php echo htmlspecialchars($conv['prenom'] . ' ' . $conv['nom']); ?></span>
                                    <span class="conversation-time">
                                        <?php echo $conv['dernier_message'] ? date('H:i', strtotime($conv['dernier_message'])) : ''; ?>
                                    </span>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo htmlspecialchars($conv['dernier_contenu'] ?: 'Livraison #' . $conv['id']); ?>
                                </div>
                            </div>
                            <?php if ($conv['messages_non_lus'] > 0): ?>
                                <div class="unread-badge"><?php echo $conv['messages_non_lus']; ?></div>
                            <?php endif; ?>
                        </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area" id="chatArea">
            <div class="chat-header">
                <div class="chat-header-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="chat-header-info">
                    <div class="chat-header-name">Sélectionnez une conversation</div>
                    <div class="chat-header-status">Choisissez une livraison pour commencer</div>
                </div>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Aucune conversation sélectionnée</h3>
                    <p>Choisissez une livraison dans la liste pour commencer à discuter avec le client.</p>
                </div>
            </div>
            
            <div class="input-area">
                <div class="input-container">
                    <div class="quick-messages">
                        <div class="quick-message" onclick="sendQuickMessage('J\'arrive dans 5 minutes')">J'arrive dans 5 min</div>
                        <div class="quick-message" onclick="sendQuickMessage('Je suis arrivé')">Je suis arrivé</div>
                        <div class="quick-message" onclick="sendQuickMessage('Livraison effectuée')">Livraison effectuée</div>
                        <div class="quick-message" onclick="sendQuickMessage('Problème avec la livraison')">Problème</div>
                    </div>
                    <textarea class="message-input" id="messageInput" placeholder="Tapez votre message..." rows="1" onkeypress="handleKeyPress(event)"></textarea>
                    <div class="input-actions">
                        <button class="btn-icon" onclick="attachFile()" title="Joindre un fichier">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button class="btn-icon" onclick="recordVoice()" title="Message vocal">
                            <i class="fas fa-microphone"></i>
                        </button>
                    </div>
                </div>
                <button class="btn-send" onclick="sendMessage()" id="sendButton">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentLivraisonId = null;
        let messageInterval = null;

        // Charger une conversation
        function loadConversation(livraisonId, element) {
            currentLivraisonId = livraisonId;
            
            // Mettre à jour l'interface
            document.querySelectorAll('.conversation-item').forEach(item => item.classList.remove('active'));
            if (element) element.classList.add('active');
            
            // Charger les messages
            loadMessages(livraisonId);
            
            // Arrêter le rafraîchissement automatique
            if (messageInterval) {
                clearInterval(messageInterval);
                messageInterval = null;
            }
        }



        // Charger les messages d'une livraison
        function loadMessages(livraisonId) {
            const chatArea = document.getElementById('chatArea');
            chatArea.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i> Chargement...</div>';
            
            fetch(`get_messages_moto_taxi.php?livraison_id=${livraisonId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderChat(data);
                    } else {
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
                    console.error('Erreur:', error);
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
            
            // Sauvegarder le contenu du textarea et la position du curseur s'il existe
            const messageInput = document.getElementById('messageInput');
            const savedMessage = messageInput ? messageInput.value : '';
            const savedCursorPosition = messageInput ? messageInput.selectionStart : 0;
            const wasFocused = messageInput === document.activeElement;
            
            chatArea.innerHTML = `
                <div class="chat-header">
                    <div class="chat-header-avatar">
                        ${data.user_photo ? 
                            `<img src="${data.user_photo.startsWith('uploads/') ? data.user_photo : 'uploads/users/' + data.user_photo}" alt="Photo" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(data.user_name)}&background=00a884&color=fff&size=64';">` :
                            `<img src="https://ui-avatars.com/api/?name=${encodeURIComponent(data.user_name)}&background=00a884&color=fff&size=64" alt="Photo">`
                        }
                    </div>
                    <div class="chat-header-info">
                        <div class="chat-header-name">${data.user_name}</div>
                        <div class="chat-header-status">Livraison #${data.livraison_id} - <span class="status-${data.statut}">${data.statut}</span></div>
                    </div>
                    <div class="chat-header-actions">
                        <button class="btn-icon" onclick="shareLocation()" title="Partager ma position">
                            <i class="fas fa-location-arrow"></i>
                        </button>
                        <button class="btn-icon" onclick="callClient()" title="Appeler">
                            <i class="fas fa-phone"></i>
                        </button>
                    </div>
                </div>
                
                <div class="messages-container" id="messagesContainer">
                    ${renderMessages(data.messages)}
                </div>
                
                <div class="input-area">
                    <div class="input-container">
                        <div class="quick-messages">
                            <div class="quick-message" onclick="sendQuickMessage('J\'arrive dans 5 minutes')">J'arrive dans 5 min</div>
                            <div class="quick-message" onclick="sendQuickMessage('Je suis arrivé')">Je suis arrivé</div>
                            <div class="quick-message" onclick="sendQuickMessage('Livraison effectuée')">Livraison effectuée</div>
                            <div class="quick-message" onclick="sendQuickMessage('Problème avec la livraison')">Problème</div>
                        </div>
                        <textarea class="message-input" id="messageInput" placeholder="Tapez votre message..." rows="1" onkeypress="handleKeyPress(event)">${savedMessage}</textarea>
                        <div class="input-actions">
                            <button class="btn-icon" onclick="attachFile()" title="Joindre un fichier">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button class="btn-icon" onclick="recordVoice()" title="Message vocal">
                                <i class="fas fa-microphone"></i>
                            </button>
                        </div>
                    </div>
                    <button class="btn-send" onclick="sendMessage()" id="sendButton">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            `;
            
            // Faire défiler vers le bas
            const messagesContainer = document.getElementById('messagesContainer');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Restaurer le focus et la position du curseur
            const newMessageInput = document.getElementById('messageInput');
            if (newMessageInput) {
                // Auto-resize du textarea
                newMessageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
                
                // Restaurer le focus et la position du curseur si c'était le cas avant
                if (wasFocused) {
                    newMessageInput.focus();
                    newMessageInput.setSelectionRange(savedCursorPosition, savedCursorPosition);
                }
                
                // Appliquer l'auto-resize initial si il y a du texte
                if (savedMessage) {
                    newMessageInput.style.height = 'auto';
                    newMessageInput.style.height = newMessageInput.scrollHeight + 'px';
                }
            }
            
            // Marquer les messages comme lus
            markMessagesAsRead(data.livraison_id);
        }

        // Afficher les messages
        function renderMessages(messages) {
            if (messages.length === 0) {
                return '<div class="empty-state"><i class="fas fa-comments"></i><h3>Aucun message</h3><p>Commencez la conversation !</p></div>';
            }
            
            return messages.map(message => {
                const isMotoTaxi = message.type_expediteur === 'moto_taxi';
                const messageClass = isMotoTaxi ? 'moto_taxi' : 'user';
                const time = new Date(message.date_envoi).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                
                let content = `<div class="message ${messageClass}">
                    <div class="message-bubble">
                        <div>${escapeHtml(message.contenu)}</div>`;
                
                // Ajouter le contenu spécifique au type de message
                if (message.type_message === 'photo' && message.fichier) {
                    content += `<img src="${message.fichier}" alt="Photo" class="message-photo" onclick="openImage('${message.fichier}')">`;
                } else if (message.type_message === 'audio' && message.fichier) {
                    content += `<div class="message-audio">
                        <audio controls>
                            <source src="${message.fichier}" type="audio/wav">
                            Votre navigateur ne supporte pas l'élément audio.
                        </audio>
                    </div>`;
                } else if (message.type_message === 'gps' && message.coordonnees_gps) {
                    const coords = JSON.parse(message.coordonnees_gps);
                    content += `<div class="message-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <a href="https://maps.google.com/?q=${coords.lat},${coords.lng}" target="_blank">
                            Voir sur la carte
                        </a>
                    </div>`;
                }
                
                content += `<div class="message-time">
                    ${time}
                    ${isMotoTaxi ? '<span class="message-status"><i class="fas fa-check-double"></i></span>' : ''}
    </div>
</div>
                </div>`;
                
                return content;
            }).join('');
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
            
            if (!message) {
                alert('Veuillez saisir un message');
                return;
            }
            
            if (!currentLivraisonId) {
                alert('Veuillez sélectionner une conversation pour envoyer un message');
                return;
            }
            
            const sendButton = document.getElementById('sendButton');
            sendButton.disabled = true;
            
            fetch('send_message_moto_taxi.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    livraison_id: currentLivraisonId,
                    contenu: message,
                    type_message: 'texte'
                })
            })
            .then(response => response.json())
            .then(data => {
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
            if (!currentLivraisonId) {
                alert('Veuillez sélectionner une conversation pour envoyer un message');
                return;
            }
            
            fetch('send_message_moto_taxi.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    livraison_id: currentLivraisonId,
                    contenu: message,
                    type_message: 'predefini'
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
            fetch('mark_messages_read_moto_taxi.php', {
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
                    
                    fetch('send_message_moto_taxi.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            livraison_id: currentLivraisonId,
                            contenu: 'Ma position actuelle',
                            type_message: 'gps',
                            coordonnees_gps: JSON.stringify(coords)
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

        // Appeler le client
        function callClient() {
            alert('Fonctionnalité d\'appel à implémenter');
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

        // Uploader un fichier
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('livraison_id', currentLivraisonId);
            
            fetch('upload_message_file_moto_taxi.php', {
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

        // Ouvrir une image
        function openImage(src) {
            window.open(src, '_blank');
        }

        // Recherche de conversations
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const conversationItems = document.querySelectorAll('.conversation-item');
            
            conversationItems.forEach(item => {
                const name = item.querySelector('.conversation-name').textContent.toLowerCase();
                const preview = item.querySelector('.conversation-preview').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || preview.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Ne pas charger automatiquement la première conversation
</script>
</body>
</html> 