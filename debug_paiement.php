<?php
/**
 * Fichier de diagnostic pour l'erreur de connexion lors du prélèvement de commission
 */

session_start();
require_once 'db.php';

// Simuler une session moto-taxi pour les tests
if (!isset($_SESSION['moto_taxi_id'])) {
    $_SESSION['moto_taxi_id'] = 1; // ID de test
}

echo "<h1>Diagnostic - Erreur de Connexion Paiement</h1>";

try {
    // 1. Vérifier la connexion à la base de données
    echo "<h2>1. Test de connexion à la base de données</h2>";
    
    if ($pdo) {
        echo "<p style='color: green;'>✅ Connexion PDO établie</p>";
        
        // Test de requête simple
        $stmt = $pdo->query("SELECT 1");
        if ($stmt) {
            echo "<p style='color: green;'>✅ Requête de test réussie</p>";
        } else {
            echo "<p style='color: red;'>❌ Échec de la requête de test</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Échec de la connexion PDO</p>";
    }
    
    // 2. Vérifier les tables nécessaires
    echo "<h2>2. Vérification des tables</h2>";
    
    $tables_requises = [
        'livraisons' => ['id', 'moto_taxi_id', 'statut', 'montant', 'date_fin_livraison'],
        'moto_taxis' => ['id', 'solde', 'nombre_livraisons', 'points_fidelite'],
        'transactions_moto_taxi' => ['id', 'moto_taxi_id', 'type', 'montant', 'solde_avant', 'solde_apres', 'livraison_id', 'description', 'statut', 'date_transaction'],
        'notifications' => ['id', 'user_id', 'type_user', 'titre', 'message', 'date_creation']
    ];
    
    foreach ($tables_requises as $table => $colonnes) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Table '$table' existe</p>";
            
            // Vérifier les colonnes
            $stmt = $pdo->query("DESCRIBE $table");
            $colonnes_existantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($colonnes as $colonne) {
                if (in_array($colonne, $colonnes_existantes)) {
                    echo "<span style='color: green;'>✅</span> ";
                } else {
                    echo "<span style='color: red;'>❌</span> ";
                }
                echo "$table.$colonne<br>";
            }
        } else {
            echo "<p style='color: red;'>❌ Table '$table' manquante</p>";
        }
    }
    
    // 3. Vérifier les données de test
    echo "<h2>3. Données de test</h2>";
    
    // Moto-taxi de test
    $stmt = $pdo->prepare("SELECT * FROM moto_taxis WHERE id = ?");
    $stmt->execute([$_SESSION['moto_taxi_id']]);
    $moto_taxi = $stmt->fetch();
    
    if ($moto_taxi) {
        echo "<p style='color: green;'>✅ Moto-taxi de test trouvé: {$moto_taxi['nom']} {$moto_taxi['prenom']}</p>";
        echo "<p>Solde actuel: " . number_format($moto_taxi['solde'], 0, ',', ' ') . " FCFA</p>";
    } else {
        echo "<p style='color: red;'>❌ Moto-taxi de test non trouvé (ID: {$_SESSION['moto_taxi_id']})</p>";
    }
    
    // Livraisons en cours
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM livraisons WHERE moto_taxi_id = ? AND statut = 'en_cours'");
    $stmt->execute([$_SESSION['moto_taxi_id']]);
    $livraisons_en_cours = $stmt->fetchColumn();
    
    echo "<p>Livraisons en cours: $livraisons_en_cours</p>";
    
    // 4. Test de la fonction de paiement
    echo "<h2>4. Test de la fonction de paiement</h2>";
    
    if (file_exists('paiement_automatique.php')) {
        require_once 'paiement_automatique.php';
        echo "<p style='color: green;'>✅ Fichier paiement_automatique.php chargé</p>";
        
        // Test de la fonction de calcul
        $test_calcul = calculerGainEtCommission(1000, 0.05);
        echo "<p>Test calcul (1000 FCFA): Gain = " . number_format($test_calcul['gain'], 0, ',', ' ') . " FCFA, Commission = " . number_format($test_calcul['commission'], 0, ',', ' ') . " FCFA</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Fichier paiement_automatique.php manquant</p>";
    }
    
    // 5. Test de transaction
    echo "<h2>5. Test de transaction</h2>";
    
    try {
        $pdo->beginTransaction();
        
        // Test d'une requête simple
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM moto_taxis WHERE id = ?");
        $stmt->execute([$_SESSION['moto_taxi_id']]);
        $count = $stmt->fetchColumn();
        
        $pdo->commit();
        echo "<p style='color: green;'>✅ Test de transaction réussi (count: $count)</p>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<p style='color: red;'>❌ Erreur de transaction: " . $e->getMessage() . "</p>";
    }
    
    // 6. Test de requête complexe
    echo "<h2>6. Test de requête complexe</h2>";
    
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, mt.solde as moto_solde
            FROM livraisons l
            JOIN moto_taxis mt ON l.moto_taxi_id = mt.id
            WHERE l.moto_taxi_id = ? AND l.statut = 'en_cours'
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['moto_taxi_id']]);
        $livraison_test = $stmt->fetch();
        
        if ($livraison_test) {
            echo "<p style='color: green;'>✅ Requête complexe réussie</p>";
            echo "<p>Livraison #{$livraison_test['id']} - Montant: " . number_format($livraison_test['montant'], 0, ',', ' ') . " FCFA</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Aucune livraison en cours trouvée pour le test</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur requête complexe: " . $e->getMessage() . "</p>";
    }
    
    // 7. Test de simulation de paiement
    echo "<h2>7. Test de simulation de paiement</h2>";
    
    if (function_exists('effectuerPaiementAutomatique')) {
        // Trouver une livraison en cours pour le test
        $stmt = $pdo->prepare("SELECT id FROM livraisons WHERE moto_taxi_id = ? AND statut = 'en_cours' LIMIT 1");
        $stmt->execute([$_SESSION['moto_taxi_id']]);
        $livraison_id_test = $stmt->fetchColumn();
        
        if ($livraison_id_test) {
            echo "<p>Livraison de test trouvée: #$livraison_id_test</p>";
            echo "<button onclick='testPaiementComplet($livraison_id_test)' style='background: #dc2626; color: white; border: none; padding: 10px; margin: 10px 0;'>TEST Paiement Complet</button>";
        } else {
            echo "<p style='color: orange;'>⚠️ Aucune livraison en cours disponible pour le test</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Fonction effectuerPaiementAutomatique non disponible</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur générale: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>

<script>
function testPaiementComplet(livraisonId) {
    if (confirm('ATTENTION: Ceci va effectuer un vrai paiement!\n\nÊtes-vous sûr de vouloir tester le paiement complet ?')) {
        console.log('Test de paiement pour livraison:', livraisonId);
        
        fetch('terminer_livraison.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({livraison_id: livraisonId})
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                alert('✅ Test réussi!\n\nGain: ' + new Intl.NumberFormat('fr-FR').format(data.gain) + ' FCFA\nCommission: ' + new Intl.NumberFormat('fr-FR').format(data.commission) + ' FCFA\nNouveau solde: ' + new Intl.NumberFormat('fr-FR').format(data.nouveau_solde) + ' FCFA');
                location.reload();
            } else {
                alert('❌ Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur fetch:', error);
            alert('❌ Erreur de connexion: ' + error.message);
        });
    }
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2 { color: #2563eb; }
p { margin: 5px 0; }
button { cursor: pointer; border-radius: 5px; }
</style> 