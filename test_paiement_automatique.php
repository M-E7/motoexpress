<?php
/**
 * Fichier de test pour le système de paiement automatique
 * À utiliser uniquement en développement
 */

session_start();
require_once 'db.php';
require_once 'paiement_automatique.php';

// Simuler une session moto-taxi pour les tests
if (!isset($_SESSION['moto_taxi_id'])) {
    $_SESSION['moto_taxi_id'] = 1; // ID de test
}

echo "<h1>Test du système de paiement automatique</h1>";

try {
    // 1. Vérifier la structure de la base de données
    echo "<h2>1. Vérification de la structure</h2>";
    
    $tables = ['livraisons', 'moto_taxis', 'transactions_moto_taxi', 'notifications'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Table '$table' existe</p>";
        } else {
            echo "<p style='color: red;'>❌ Table '$table' manquante</p>";
        }
    }
    
    // 2. Vérifier les données existantes
    echo "<h2>2. Données existantes</h2>";
    
    // Moto-taxis
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM moto_taxis");
    $moto_count = $stmt->fetchColumn();
    echo "<p>🏍️ Moto-taxis: $moto_count</p>";
    
    // Livraisons
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM livraisons");
    $livraison_count = $stmt->fetchColumn();
    echo "<p>📦 Livraisons: $livraison_count</p>";
    
    // Transactions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions_moto_taxi");
    $transaction_count = $stmt->fetchColumn();
    echo "<p>💰 Transactions: $transaction_count</p>";
    
    // 3. Tester la fonction de calcul
    echo "<h2>3. Test de la fonction de calcul</h2>";
    
    $montant_test = 1000;
    $resultat_calcul = calculerGainEtCommission($montant_test, 0.05);
    
    echo "<p>Montant de test: " . number_format($montant_test, 0, ',', ' ') . " FCFA</p>";
    echo "<p>Gain (95%): " . number_format($resultat_calcul['gain'], 0, ',', ' ') . " FCFA</p>";
    echo "<p>Commission (5%): " . number_format($resultat_calcul['commission'], 0, ',', ' ') . " FCFA</p>";
    
    // 4. Vérifier les livraisons en cours
    echo "<h2>4. Livraisons en cours</h2>";
    
    $stmt = $pdo->prepare("
        SELECT l.*, mt.nom as moto_nom, mt.prenom as moto_prenom, mt.solde as moto_solde
        FROM livraisons l
        JOIN moto_taxis mt ON l.moto_taxi_id = mt.id
        WHERE l.statut = 'en_cours' AND l.moto_taxi_id = ?
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['moto_taxi_id']]);
    $livraisons_en_cours = $stmt->fetchAll();
    
    if (empty($livraisons_en_cours)) {
        echo "<p style='color: orange;'>⚠️ Aucune livraison en cours trouvée pour les tests</p>";
    } else {
        echo "<p>Livraisons en cours trouvées:</p>";
        foreach ($livraisons_en_cours as $liv) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Livraison #{$liv['id']}</strong><br>";
            echo "Moto-taxi: {$liv['moto_nom']} {$liv['moto_prenom']}<br>";
            echo "Montant: " . number_format($liv['montant'], 0, ',', ' ') . " FCFA<br>";
            echo "Solde actuel: " . number_format($liv['moto_solde'], 0, ',', ' ') . " FCFA<br>";
            
            // Calculer ce qui serait payé
            $calcul = calculerGainEtCommission($liv['montant'], 0.05);
            $nouveau_solde = $liv['moto_solde'] + $calcul['gain'];
            
            echo "Gain estimé: " . number_format($calcul['gain'], 0, ',', ' ') . " FCFA<br>";
            echo "Commission: " . number_format($calcul['commission'], 0, ',', ' ') . " FCFA<br>";
            echo "Nouveau solde estimé: " . number_format($nouveau_solde, 0, ',', ' ') . " FCFA<br>";
            
            // Bouton de test (à utiliser avec précaution)
            echo "<button onclick='testPaiement({$liv['id']})' style='background: #dc2626; color: white; border: none; padding: 5px 10px; margin-top: 5px;'>TEST Paiement</button>";
            echo "</div>";
        }
    }
    
    // 5. Historique des transactions récentes
    echo "<h2>5. Historique des transactions récentes</h2>";
    
    $stmt = $pdo->prepare("
        SELECT * FROM transactions_moto_taxi 
        WHERE moto_taxi_id = ? 
        ORDER BY date_transaction DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['moto_taxi_id']]);
    $transactions = $stmt->fetchAll();
    
    if (empty($transactions)) {
        echo "<p>Aucune transaction trouvée</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>Date</th><th>Type</th><th>Montant</th><th>Solde avant</th><th>Solde après</th><th>Description</th></tr>";
        foreach ($transactions as $trans) {
            echo "<tr>";
            echo "<td>" . date('d/m/Y H:i', strtotime($trans['date_transaction'])) . "</td>";
            echo "<td>" . ucfirst($trans['type']) . "</td>";
            echo "<td>" . number_format($trans['montant'], 0, ',', ' ') . " FCFA</td>";
            echo "<td>" . number_format($trans['solde_avant'], 0, ',', ' ') . " FCFA</td>";
            echo "<td>" . number_format($trans['solde_apres'], 0, ',', ' ') . " FCFA</td>";
            echo "<td>" . htmlspecialchars($trans['description']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>

<script>
function testPaiement(livraisonId) {
    if (confirm('ATTENTION: Ceci est un test de paiement automatique!\n\nÊtes-vous sûr de vouloir terminer cette livraison et effectuer le paiement ?')) {
        fetch('terminer_livraison.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({livraison_id: livraisonId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Test réussi!\n\nGain: ' + new Intl.NumberFormat('fr-FR').format(data.gain) + ' FCFA\nCommission: ' + new Intl.NumberFormat('fr-FR').format(data.commission) + ' FCFA\nNouveau solde: ' + new Intl.NumberFormat('fr-FR').format(data.nouveau_solde) + ' FCFA');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
        });
    }
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #2563eb; }
button { cursor: pointer; }
table { margin-top: 10px; }
td, th { padding: 8px; text-align: left; }
</style> 