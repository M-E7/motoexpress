<?php
/**
 * Test simple de connexion pour diagnostiquer l'erreur
 */

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Connexion Simple</h1>";

try {
    // 1. Test de connexion à la base de données
    echo "<h2>1. Test de connexion PDO</h2>";
    
    if (file_exists('db.php')) {
        echo "<p style='color: green;'>✅ Fichier db.php trouvé</p>";
        
        require_once 'db.php';
        
        if (isset($pdo)) {
            echo "<p style='color: green;'>✅ Variable \$pdo définie</p>";
            
            // Test de connexion
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            if ($result && $result['test'] == 1) {
                echo "<p style='color: green;'>✅ Connexion PDO fonctionnelle</p>";
            } else {
                echo "<p style='color: red;'>❌ Connexion PDO échouée</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Variable \$pdo non définie</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Fichier db.php manquant</p>";
    }
    
    // 2. Test de session
    echo "<h2>2. Test de session</h2>";
    
    session_start();
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "<p style='color: green;'>✅ Session active</p>";
        
        // Simuler une session moto-taxi
        if (!isset($_SESSION['moto_taxi_id'])) {
            $_SESSION['moto_taxi_id'] = 1;
            echo "<p>Session moto-taxi simulée (ID: 1)</p>";
        } else {
            echo "<p>Session moto-taxi existante (ID: {$_SESSION['moto_taxi_id']})</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Session non active</p>";
    }
    
    // 3. Test de requête simple
    echo "<h2>3. Test de requête simple</h2>";
    
    if (isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM moto_taxis");
            $count = $stmt->fetchColumn();
            echo "<p style='color: green;'>✅ Requête réussie - Nombre de moto-taxis: $count</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erreur requête: " . $e->getMessage() . "</p>";
        }
    }
    
    // 4. Test de requête avec paramètres
    echo "<h2>4. Test de requête avec paramètres</h2>";
    
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM moto_taxis WHERE id = ?");
            $stmt->execute([1]);
            $moto = $stmt->fetch();
            
            if ($moto) {
                echo "<p style='color: green;'>✅ Requête avec paramètres réussie</p>";
                echo "<p>Moto-taxi trouvé: {$moto['nom']} {$moto['prenom']}</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ Aucun moto-taxi trouvé avec ID 1</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erreur requête avec paramètres: " . $e->getMessage() . "</p>";
        }
    }
    
    // 5. Test de transaction
    echo "<h2>5. Test de transaction</h2>";
    
    if (isset($pdo)) {
        try {
            $pdo->beginTransaction();
            
            // Test d'une requête dans une transaction
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM livraisons WHERE statut = 'en_cours'");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            $pdo->commit();
            echo "<p style='color: green;'>✅ Transaction réussie - Livraisons en cours: $count</p>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<p style='color: red;'>❌ Erreur transaction: " . $e->getMessage() . "</p>";
        }
    }
    
    // 6. Test de simulation de terminer_livraison.php
    echo "<h2>6. Test de simulation terminer_livraison.php</h2>";
    
    if (isset($pdo) && isset($_SESSION['moto_taxi_id'])) {
        try {
            // Simuler les données d'entrée
            $livraison_id = 1; // ID de test
            
            // Vérifier que la livraison existe
            $stmt = $pdo->prepare("SELECT * FROM livraisons WHERE id = ? AND moto_taxi_id = ? AND statut = 'en_cours'");
            $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id']]);
            $livraison = $stmt->fetch();
            
            if ($livraison) {
                echo "<p style='color: green;'>✅ Livraison de test trouvée</p>";
                echo "<p>Livraison #{$livraison['id']} - Montant: " . number_format($livraison['montant'], 0, ',', ' ') . " FCFA</p>";
                
                // Test de la fonction de calcul
                if (file_exists('paiement_automatique.php')) {
                    require_once 'paiement_automatique.php';
                    
                    if (function_exists('calculerGainEtCommission')) {
                        $calcul = calculerGainEtCommission($livraison['montant'], 0.05);
                        echo "<p>Calcul test - Gain: " . number_format($calcul['gain'], 0, ',', ' ') . " FCFA, Commission: " . number_format($calcul['commission'], 0, ',', ' ') . " FCFA</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Fonction calculerGainEtCommission non disponible</p>";
                    }
                } else {
                    echo "<p style='color: red;'>❌ Fichier paiement_automatique.php manquant</p>";
                }
            } else {
                echo "<p style='color: orange;'>⚠️ Aucune livraison en cours trouvée pour le test</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erreur simulation: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur générale: " . $e->getMessage() . "</p>";
    echo "<p>Fichier: " . $e->getFile() . "</p>";
    echo "<p>Ligne: " . $e->getLine() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2 { color: #2563eb; }
p { margin: 5px 0; padding: 5px; border-radius: 3px; }
</style> 