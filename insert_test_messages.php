<?php
require_once 'db.php';

echo "<h2>Insertion de messages de test</h2>";

try {
    // Récupérer les livraisons existantes
    $stmt = $pdo->query("SELECT id, user_id, moto_taxi_id FROM livraisons WHERE moto_taxi_id IS NOT NULL LIMIT 3");
    $livraisons = $stmt->fetchAll();
    
    if (empty($livraisons)) {
        echo "<p style='color: orange;'>Aucune livraison avec moto-taxi trouvée. Créez d'abord des livraisons.</p>";
        exit;
    }
    
    $messages_inseres = 0;
    
    foreach ($livraisons as $livraison) {
        echo "<p>Ajout de messages pour la livraison #{$livraison['id']}...</p>";
        
        // Messages du moto-taxi
        $messages_mototaxi = [
            ['type' => 'texte', 'contenu' => 'Bonjour ! Je suis en route pour récupérer votre colis.', 'delai' => 0],
            ['type' => 'texte', 'contenu' => 'J\'arrive dans 5 minutes à l\'adresse de départ.', 'delai' => 300],
            ['type' => 'gps', 'contenu' => 'Ma position actuelle', 'delai' => 600],
            ['type' => 'texte', 'contenu' => 'J\'ai récupéré le colis. En route vers la destination.', 'delai' => 900],
            ['type' => 'texte', 'contenu' => 'Livraison effectuée avec succès !', 'delai' => 1200]
        ];
        
        // Messages de l'utilisateur
        $messages_user = [
            ['type' => 'texte', 'contenu' => 'Merci ! Je vous attends.', 'delai' => 150],
            ['type' => 'texte', 'contenu' => 'Parfait, je suis prêt.', 'delai' => 450],
            ['type' => 'predefini', 'contenu' => 'Où êtes-vous ?', 'delai' => 750],
            ['type' => 'texte', 'contenu' => 'Merci beaucoup !', 'delai' => 1050]
        ];
        
        // Insérer les messages du moto-taxi
        foreach ($messages_mototaxi as $msg) {
            $date_envoi = date('Y-m-d H:i:s', time() - (1200 - $msg['delai']));
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (livraison_id, expediteur, type, contenu, date_envoi, lu) 
                VALUES (?, 'mototaxi', ?, ?, ?, 0)
            ");
            $stmt->execute([$livraison['id'], $msg['type'], $msg['contenu'], $date_envoi]);
            $messages_inseres++;
        }
        
        // Insérer les messages de l'utilisateur
        foreach ($messages_user as $msg) {
            $date_envoi = date('Y-m-d H:i:s', time() - (1200 - $msg['delai']));
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (livraison_id, expediteur, type, contenu, date_envoi, lu) 
                VALUES (?, 'user', ?, ?, ?, 1)
            ");
            $stmt->execute([$livraison['id'], $msg['type'], $msg['contenu'], $date_envoi]);
            $messages_inseres++;
        }
        
        // Ajouter un message GPS du moto-taxi
        $coords = [
            'lat' => 4.0511 + (rand(-10, 10) / 1000),
            'lng' => 9.7085 + (rand(-10, 10) / 1000)
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (livraison_id, expediteur, type, contenu, fichier, date_envoi, lu) 
            VALUES (?, 'mototaxi', 'gps', 'Ma position actuelle', ?, NOW() - INTERVAL 10 MINUTE, 0)
        ");
        $stmt->execute([$livraison['id'], json_encode($coords)]);
        $messages_inseres++;
    }
    
    echo "<p style='color: green;'>✅ {$messages_inseres} messages de test insérés avec succès !</p>";
    
    // Afficher un résumé
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
    $total = $stmt->fetchColumn();
    
    echo "<h3>Résumé :</h3>";
    echo "<p>Total de messages en base : {$total}</p>";
    
    $stmt = $pdo->query("SELECT expediteur, COUNT(*) as count FROM messages GROUP BY expediteur");
    $stats = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($stats as $stat) {
        echo "<li>{$stat['expediteur']} : {$stat['count']} messages</li>";
    }
    echo "</ul>";
    
    echo "<p><a href='messages.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voir les messages</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur : " . $e->getMessage() . "</p>";
}
?> 