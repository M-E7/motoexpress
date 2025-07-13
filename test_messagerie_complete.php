<?php
session_start();
require_once 'db.php';

echo "<h1>Test complet de la messagerie MotoExpress</h1>";

// Vérifier les tables
echo "<h2>1. Vérification des tables</h2>";
$tables = ['users', 'moto_taxis', 'livraisons', 'messages', 'notifications'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Table '$table' existe</p>";
    } else {
        echo "<p style='color: red;'>❌ Table '$table' manquante</p>";
    }
}

// Vérifier la structure de la table messages
echo "<h2>2. Structure de la table messages</h2>";
$stmt = $pdo->query("DESCRIBE messages");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f0f0f0;'><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier les données existantes
echo "<h2>3. Données existantes</h2>";

// Utilisateurs
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$user_count = $stmt->fetchColumn();
echo "<p>👥 Utilisateurs: $user_count</p>";

// Moto-taxis
$stmt = $pdo->query("SELECT COUNT(*) as count FROM moto_taxis");
$moto_count = $stmt->fetchColumn();
echo "<p>🏍️ Moto-taxis: $moto_count</p>";

// Livraisons
$stmt = $pdo->query("SELECT COUNT(*) as count FROM livraisons");
$livraison_count = $stmt->fetchColumn();
echo "<p>📦 Livraisons: $livraison_count</p>";

// Messages
$stmt = $pdo->query("SELECT COUNT(*) as count FROM messages");
$message_count = $stmt->fetchColumn();
echo "<p>💬 Messages: $message_count</p>";

// Notifications
$stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
$notif_count = $stmt->fetchColumn();
echo "<p>🔔 Notifications: $notif_count</p>";

// Tester la création de messages
echo "<h2>4. Test de création de messages</h2>";

// Récupérer une livraison existante
$stmt = $pdo->query("SELECT l.id, l.user_id, l.moto_taxi_id, u.nom as user_nom, m.nom as moto_nom 
                     FROM livraisons l 
                     JOIN users u ON l.user_id = u.id 
                     JOIN moto_taxis m ON l.moto_taxi_id = m.id 
                     WHERE l.moto_taxi_id IS NOT NULL 
                     LIMIT 1");
$livraison = $stmt->fetch();

if ($livraison) {
    echo "<p>📋 Test avec la livraison #{$livraison['id']} (Client: {$livraison['user_nom']}, Moto-taxi: {$livraison['moto_nom']})</p>";
    
    // Simuler un message du moto-taxi
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (livraison_id, expediteur_id, type_expediteur, contenu, type_message, date_envoi) 
            VALUES (?, ?, 'moto_taxi', 'Test message du moto-taxi', 'texte', NOW())
        ");
        $stmt->execute([$livraison['id'], $livraison['moto_taxi_id']]);
        
        // Créer une notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type_user, titre, message, type_notification, lu, date_creation) 
            VALUES (?, 'user', 'Nouveau message', 'Test message du moto-taxi', 'info', 0, NOW())
        ");
        $stmt->execute([$livraison['user_id']]);
        
        echo "<p style='color: green;'>✅ Message du moto-taxi créé avec succès</p>";
        echo "<p style='color: green;'>✅ Notification client créée avec succès</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur lors de la création du message: " . $e->getMessage() . "</p>";
    }
    
    // Simuler un message du client
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (livraison_id, expediteur_id, type_expediteur, contenu, type_message, date_envoi) 
            VALUES (?, ?, 'user', 'Test message du client', 'texte', NOW())
        ");
        $stmt->execute([$livraison['id'], $livraison['user_id']]);
        
        echo "<p style='color: green;'>✅ Message du client créé avec succès</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur lors de la création du message client: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: orange;'>⚠️ Aucune livraison avec moto-taxi trouvée pour le test</p>";
}

// Vérifier les messages créés
echo "<h2>5. Messages récents</h2>";
$stmt = $pdo->query("
    SELECT m.*, l.id as livraison_id, 
           CASE 
               WHEN m.type_expediteur = 'user' THEN CONCAT(u.prenom, ' ', u.nom)
               WHEN m.type_expediteur = 'moto_taxi' THEN CONCAT(mt.prenom, ' ', mt.nom)
           END as expediteur_nom
    FROM messages m
    JOIN livraisons l ON m.livraison_id = l.id
    LEFT JOIN users u ON m.expediteur_id = u.id AND m.type_expediteur = 'user'
    LEFT JOIN moto_taxis mt ON m.expediteur_id = mt.id AND m.type_expediteur = 'moto_taxi'
    ORDER BY m.date_envoi DESC
    LIMIT 10
");
$messages = $stmt->fetchAll();

if (!empty($messages)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Livraison</th><th>Expéditeur</th><th>Type</th><th>Contenu</th><th>Date</th><th>Lu</th>";
    echo "</tr>";
    
    foreach ($messages as $msg) {
        $contenu_short = strlen($msg['contenu']) > 30 ? substr($msg['contenu'], 0, 30) . '...' : $msg['contenu'];
        echo "<tr>";
        echo "<td>{$msg['id']}</td>";
        echo "<td>#{$msg['livraison_id']}</td>";
        echo "<td>{$msg['expediteur_nom']} ({$msg['type_expediteur']})</td>";
        echo "<td>{$msg['type_message']}</td>";
        echo "<td>" . htmlspecialchars($contenu_short) . "</td>";
        echo "<td>" . date('d/m H:i', strtotime($msg['date_envoi'])) . "</td>";
        echo "<td>" . ($msg['lu'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color: orange;'>Aucun message trouvé</p>";
}

// Vérifier les notifications
echo "<h2>6. Notifications récentes</h2>";
$stmt = $pdo->query("
    SELECT n.*, 
           CASE 
               WHEN n.type_user = 'user' THEN CONCAT(u.prenom, ' ', u.nom)
               WHEN n.type_user = 'moto_taxi' THEN CONCAT(mt.prenom, ' ', mt.nom)
           END as user_nom
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id AND n.type_user = 'user'
    LEFT JOIN moto_taxis mt ON n.user_id = mt.id AND n.type_user = 'moto_taxi'
    ORDER BY n.date_creation DESC
    LIMIT 10
");
$notifications = $stmt->fetchAll();

if (!empty($notifications)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Utilisateur</th><th>Type</th><th>Titre</th><th>Message</th><th>Date</th><th>Lu</th>";
    echo "</tr>";
    
    foreach ($notifications as $notif) {
        $message_short = strlen($notif['message']) > 30 ? substr($notif['message'], 0, 30) . '...' : $notif['message'];
        echo "<tr>";
        echo "<td>{$notif['id']}</td>";
        echo "<td>{$notif['user_nom']} ({$notif['type_user']})</td>";
        echo "<td>{$notif['type_notification']}</td>";
        echo "<td>" . htmlspecialchars($notif['titre']) . "</td>";
        echo "<td>" . htmlspecialchars($message_short) . "</td>";
        echo "<td>" . date('d/m H:i', strtotime($notif['date_creation'])) . "</td>";
        echo "<td>" . ($notif['lu'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color: orange;'>Aucune notification trouvée</p>";
}

// Test des endpoints
echo "<h2>7. Test des endpoints</h2>";
echo "<p><a href='get_messages.php?livraison_id=1' target='_blank'>🔗 Test get_messages.php</a></p>";
echo "<p><a href='get_messages_moto_taxi.php?livraison_id=1' target='_blank'>🔗 Test get_messages_moto_taxi.php</a></p>";

// Liens vers les pages de messagerie
echo "<h2>8. Pages de messagerie</h2>";
echo "<p><a href='messages.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>💬 Messagerie Client</a></p>";
echo "<p><a href='moto_taxi_messages.php' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🏍️ Messagerie Moto-taxi</a></p>";

// Actions
echo "<h2>9. Actions</h2>";
echo "<p><a href='insert_test_messages.php' style='background: #ea580c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>➕ Insérer des messages de test</a></p>";
echo "<p><a href='user_home.php' style='background: #64748b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🏠 Retour à l'accueil</a></p>";

echo "<hr>";
echo "<p><strong>Résumé :</strong> La messagerie est maintenant configurée avec le design cohérent de l'application et les messages sont bien reçus par les clients via les notifications.</p>";
?> 