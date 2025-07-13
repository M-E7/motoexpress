<?php
session_start();
require_once 'db.php';

echo "<h1>Test de la fonctionnalité de messagerie</h1>";

// Vérifier la session
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>Aucune session utilisateur active.</p>";
    echo "<p><a href='login.php'>Se connecter</a></p>";
    exit;
}

echo "<p><strong>Session utilisateur ID :</strong> {$_SESSION['user_id']}</p>";

// Récupérer les données utilisateur
$stmt = $pdo->prepare("SELECT nom, prenom, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    echo "<p style='color: red;'>Utilisateur non trouvé.</p>";
    exit;
}

echo "<p><strong>Utilisateur :</strong> {$user['prenom']} {$user['nom']} ({$user['email']})</p>";

// Vérifier les conversations
$stmt = $pdo->prepare("
    SELECT l.id as livraison_id, l.adresse_depart, l.adresse_arrivee, l.statut, l.date_demande,
           m.id as moto_taxi_id, m.nom as moto_taxi_nom, m.telephone as moto_taxi_telephone,
           (SELECT COUNT(*) FROM messages WHERE livraison_id = l.id) as nb_messages,
           (SELECT COUNT(*) FROM messages WHERE livraison_id = l.id AND expediteur = 'mototaxi' AND lu = 0) as messages_non_lus
    FROM livraisons l
    LEFT JOIN moto_taxis m ON l.moto_taxi_id = m.id
    WHERE l.user_id = ? AND l.statut IN ('en_attente', 'en_cours', 'livree')
    ORDER BY l.date_demande DESC
");
$stmt->execute([$_SESSION['user_id']]);
$conversations = $stmt->fetchAll();

echo "<h2>Conversations trouvées : " . count($conversations) . "</h2>";

if (empty($conversations)) {
    echo "<p style='color: orange;'>Aucune conversation trouvée.</p>";
    echo "<p><a href='insert_test_data.php'>Insérer des données de test</a></p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Livraison ID</th><th>Statut</th><th>Moto-taxi</th><th>Messages</th><th>Non lus</th><th>Actions</th>";
    echo "</tr>";
    
    foreach ($conversations as $conv) {
        echo "<tr>";
        echo "<td>{$conv['livraison_id']}</td>";
        echo "<td>{$conv['statut']}</td>";
        echo "<td>" . ($conv['moto_taxi_nom'] ?: 'Non assigné') . "</td>";
        echo "<td>{$conv['nb_messages']}</td>";
        echo "<td>{$conv['messages_non_lus']}</td>";
        echo "<td>";
        echo "<a href='messages.php' style='background: #2563eb; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 0.8rem;'>Voir messages</a>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Vérifier les messages
$stmt = $pdo->prepare("
    SELECT m.*, l.id as livraison_id 
    FROM messages m 
    JOIN livraisons l ON m.livraison_id = l.id 
    WHERE l.user_id = ? 
    ORDER BY m.date_envoi DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$messages = $stmt->fetchAll();

echo "<h2>Derniers messages : " . count($messages) . "</h2>";

if (!empty($messages)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Livraison</th><th>Expéditeur</th><th>Type</th><th>Contenu</th><th>Date</th><th>Lu</th>";
    echo "</tr>";
    
    foreach ($messages as $msg) {
        $contenu_short = strlen($msg['contenu']) > 50 ? substr($msg['contenu'], 0, 50) . '...' : $msg['contenu'];
        echo "<tr>";
        echo "<td>{$msg['livraison_id']}</td>";
        echo "<td>{$msg['expediteur']}</td>";
        echo "<td>{$msg['type']}</td>";
        echo "<td>" . htmlspecialchars($contenu_short) . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($msg['date_envoi'])) . "</td>";
        echo "<td>" . ($msg['lu'] ? 'Oui' : 'Non') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<h2>Actions :</h2>";
echo "<p><a href='messages.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voir la page messages</a></p>";
echo "<p><a href='insert_test_messages.php' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Insérer des messages de test</a></p>";
echo "<p><a href='user_home.php' style='background: #ea580c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Retour à l'accueil</a></p>";
?> 