<?php
session_start();
require_once 'db.php';

// Simuler une session utilisateur pour les tests
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ID utilisateur de test
}

echo "<h1>Test de chargement des données depuis la base</h1>";

$user_id = $_SESSION['user_id'];

// Test 1: Vérifier les statistiques
echo "<h2>1. Test des statistiques</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_livraisons,
            SUM(distance) as total_distance,
            SUM(prix) as total_depense,
            AVG(prix) as prix_moyen
        FROM livraisons 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Livraisons totales:</strong> " . ($stats['total_livraisons'] ?? 0) . "</p>";
    echo "<p><strong>Distance totale:</strong> " . round($stats['total_distance'] ?? 0, 1) . " km</p>";
    echo "<p><strong>Dépenses totales:</strong> " . number_format($stats['total_depense'] ?? 0, 0, ',', ' ') . " FCFA</p>";
    echo "<p><strong>Prix moyen:</strong> " . round($stats['prix_moyen'] ?? 0) . " FCFA</p>";
    
    // Note moyenne
    $stmt = $pdo->prepare("
        SELECT AVG(note) as note_moyenne
        FROM evaluations 
        WHERE livraison_id IN (SELECT id FROM livraisons WHERE user_id = ?)
    ");
    $stmt->execute([$user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Note moyenne:</strong> " . round($note['note_moyenne'] ?? 0, 1) . "</p>";
    
    // Points de fidélité
    $stmt = $pdo->prepare("SELECT points_fidelite FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $points = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Points de fidélité:</strong> " . ($points['points_fidelite'] ?? 0) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur statistiques: " . $e->getMessage() . "</p>";
}

// Test 2: Vérifier les dernières livraisons
echo "<h2>2. Test des dernières livraisons</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               m.nom as mototaxi_nom, 
               m.telephone as mototaxi_telephone,
               m.photo as mototaxi_photo,
               (SELECT COUNT(*) FROM documents_livraison WHERE livraison_id = l.id) as nb_documents
        FROM livraisons l
        LEFT JOIN moto_taxis m ON l.moto_taxi_id = m.id
        WHERE l.user_id = ?
        ORDER BY l.date_creation DESC 
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $livraisons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($livraisons)) {
        echo "<p>Aucune livraison trouvée</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Type</th><th>Description</th><th>Statut</th><th>Prix</th><th>Moto-taxi</th><th>Documents</th><th>Date</th></tr>";
        
        foreach ($livraisons as $liv) {
            echo "<tr>";
            echo "<td>{$liv['id']}</td>";
            echo "<td>{$liv['type_livraison']}</td>";
            echo "<td>" . ($liv['description'] ?: 'N/A') . "</td>";
            echo "<td>{$liv['statut']}</td>";
            echo "<td>" . number_format($liv['prix'], 0, ',', ' ') . " FCFA</td>";
            echo "<td>" . ($liv['mototaxi_nom'] ?: 'N/A') . "</td>";
            echo "<td>{$liv['nb_documents']}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($liv['date_creation'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur livraisons: " . $e->getMessage() . "</p>";
}

// Test 3: Vérifier les notifications
echo "<h2>3. Test des notifications</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT id, type_notification, titre, message, lu, date_creation
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY date_creation DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "<p>Aucune notification trouvée</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Type</th><th>Titre</th><th>Message</th><th>Lu</th><th>Date</th></tr>";
        
        foreach ($notifications as $notif) {
            echo "<tr>";
            echo "<td>{$notif['id']}</td>";
            echo "<td>{$notif['type_notification']}</td>";
            echo "<td>{$notif['titre']}</td>";
            echo "<td>" . substr($notif['message'], 0, 50) . "...</td>";
            echo "<td>" . ($notif['lu'] ? 'Oui' : 'Non') . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($notif['date_creation'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur notifications: " . $e->getMessage() . "</p>";
}

// Test 4: Vérifier les transactions
echo "<h2>4. Test des transactions</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT id, type_transaction, montant, description, date_transaction
        FROM transactions_solde 
        WHERE user_id = ?
        ORDER BY date_transaction DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p>Aucune transaction trouvée</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Type</th><th>Montant</th><th>Description</th><th>Date</th></tr>";
        
        foreach ($transactions as $trans) {
            echo "<tr>";
            echo "<td>{$trans['id']}</td>";
            echo "<td>{$trans['type_transaction']}</td>";
            echo "<td>" . number_format($trans['montant'], 0, ',', ' ') . " FCFA</td>";
            echo "<td>" . substr($trans['description'], 0, 50) . "...</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($trans['date_transaction'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur transactions: " . $e->getMessage() . "</p>";
}

// Test 5: Vérifier les endpoints API
echo "<h2>5. Test des endpoints API</h2>";

$endpoints = [
    'get_stats.php' => 'Statistiques',
    'get_history.php' => 'Historique',
    'get_notifications.php' => 'Notifications',
    'get_transactions.php' => 'Transactions',
    'get_solde.php' => 'Solde'
];

foreach ($endpoints as $endpoint => $name) {
    echo "<h3>Test $name</h3>";
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://localhost/projet2/$endpoint");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . session_id());
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success'])) {
                echo "<p style='color:green;'>✅ $name: OK</p>";
                if (isset($data['message'])) {
                    echo "<p><small>Message: {$data['message']}</small></p>";
                }
            } else {
                echo "<p style='color:orange;'>⚠️ $name: Réponse invalide</p>";
                echo "<p><small>Réponse: " . substr($response, 0, 100) . "...</small></p>";
            }
        } else {
            echo "<p style='color:red;'>❌ $name: Erreur HTTP $httpCode</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ $name: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>6. Liens de test</h2>";
echo "<p><a href='user_home.php' target='_blank'>Accéder à user_home.php</a></p>";
echo "<p><a href='create_notification.php' target='_blank'>Créer des notifications de test</a></p>";
echo "<p><a href='test_upload.php' target='_blank'>Tester l'upload de documents</a></p>";

echo "<h2>7. Instructions</h2>";
echo "<p>1. Ouvrez <a href='user_home.php' target='_blank'>user_home.php</a> dans un nouvel onglet</p>";
echo "<p>2. Vérifiez que toutes les données se chargent correctement</p>";
echo "<p>3. Testez les fonctionnalités (créer une livraison, recharger le solde, etc.)</p>";
echo "<p>4. Vérifiez que les notifications apparaissent</p>";
?> 