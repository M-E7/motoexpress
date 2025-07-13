<?php
/**
 * Test du système QR code pour les livraisons
 */

session_start();
require_once 'db.php';
require_once 'qr_generator.php';

// Simuler une session moto-taxi pour les tests
if (!isset($_SESSION['moto_taxi_id'])) {
    $_SESSION['moto_taxi_id'] = 1;
}

echo "<h1>Test du Système QR Code</h1>";

try {
    // 1. Vérifier la table QR codes
    echo "<h2>1. Vérification de la table QR codes</h2>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'qr_codes_livraison'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Table qr_codes_livraison existe</p>";
        
        // Vérifier les colonnes
        $stmt = $pdo->query("DESCRIBE qr_codes_livraison");
        $colonnes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Colonnes trouvées: " . implode(', ', $colonnes) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Table qr_codes_livraison manquante</p>";
        echo "<p>Exécutez le script SQL pour créer la table.</p>";
    }
    
    // 2. Vérifier les livraisons disponibles
    echo "<h2>2. Livraisons disponibles pour test</h2>";
    
    $stmt = $pdo->prepare("
        SELECT l.*, u.nom as user_nom, u.prenom as user_prenom
        FROM livraisons l
        JOIN users u ON l.user_id = u.id
        WHERE l.moto_taxi_id = ? AND l.statut = 'en_cours'
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['moto_taxi_id']]);
    $livraisons = $stmt->fetchAll();
    
    if (empty($livraisons)) {
        echo "<p style='color: orange;'>⚠️ Aucune livraison en cours trouvée</p>";
        
        // Créer une livraison de test
        echo "<h3>Création d'une livraison de test</h3>";
        
        $stmt = $pdo->prepare("
            INSERT INTO livraisons (
                user_id, moto_taxi_id, adresse_depart, adresse_arrivee, 
                montant, type_livraison, statut, date_creation
            ) VALUES (1, ?, 'Douala Centre', 'Douala Akwa', 1500, 'colis', 'en_cours', NOW())
        ");
        $stmt->execute([$_SESSION['moto_taxi_id']]);
        
        $livraison_test_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ Livraison de test créée (ID: $livraison_test_id)</p>";
        
        $livraisons = [['id' => $livraison_test_id, 'montant' => 1500]];
    } else {
        echo "<p style='color: green;'>✅ " . count($livraisons) . " livraison(s) en cours trouvée(s)</p>";
    }
    
    // 3. Test de génération de QR code
    echo "<h2>3. Test de génération de QR code</h2>";
    
    if (!empty($livraisons)) {
        $livraison_test = $livraisons[0];
        
        echo "<p>Test avec la livraison #{$livraison_test['id']}</p>";
        
        $qr_result = genererQRCodeLivraison($livraison_test['id'], $_SESSION['moto_taxi_id']);
        
        if ($qr_result['success']) {
            echo "<p style='color: green;'>✅ QR code généré avec succès</p>";
            echo "<p>Fichier: {$qr_result['qr_filename']}</p>";
            echo "<p>Chemin: {$qr_result['qr_path']}</p>";
            
            // Afficher le QR code
            if (file_exists($qr_result['qr_path'])) {
                echo "<div style='text-align: center; margin: 20px 0;'>";
                echo "<h4>QR Code généré:</h4>";
                echo "<img src='{$qr_result['qr_path']}' alt='QR Code Test' style='max-width: 200px; border: 1px solid #ccc;'>";
                echo "</div>";
            }
            
            // Afficher le contenu du QR code
            echo "<h4>Contenu du QR code:</h4>";
            echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
            echo htmlspecialchars($qr_result['qr_content']);
            echo "</pre>";
            
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de la génération: {$qr_result['message']}</p>";
        }
    }
    
    // 4. Test de validation de QR code
    echo "<h2>4. Test de validation de QR code</h2>";
    
    if (isset($qr_result) && $qr_result['success']) {
        $validation_result = validerQRCode($qr_result['qr_content']);
        
        if ($validation_result['success']) {
            echo "<p style='color: green;'>✅ QR code validé avec succès</p>";
            echo "<p>Livraison ID: {$validation_result['qr_data']['livraison_id']}</p>";
            echo "<p>Moto-taxi ID: {$validation_result['qr_data']['moto_taxi_id']}</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur de validation: {$validation_result['message']}</p>";
        }
    }
    
    // 5. Test de récupération de QR code
    echo "<h2>5. Test de récupération de QR code</h2>";
    
    if (!empty($livraisons)) {
        $qr_info = getQRCodeLivraison($livraisons[0]['id']);
        
        if ($qr_info) {
            echo "<p style='color: green;'>✅ QR code récupéré avec succès</p>";
            echo "<p>Statut: {$qr_info['statut']}</p>";
            echo "<p>Date de création: {$qr_info['date_creation']}</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Aucun QR code trouvé pour cette livraison</p>";
        }
    }
    
    // 6. Test de marquage comme utilisé
    echo "<h2>6. Test de marquage comme utilisé</h2>";
    
    if (!empty($livraisons)) {
        $marquage_result = marquerQRCodeUtilise($livraisons[0]['id']);
        
        if ($marquage_result) {
            echo "<p style='color: green;'>✅ QR code marqué comme utilisé</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur lors du marquage</p>";
        }
    }
    
    // 7. Test des fonctions utilitaires
    echo "<h2>7. Test des fonctions utilitaires</h2>";
    
    $calcul = calculerGainEtCommission(1000, 0.05);
    echo "<p>Calcul gain/commission (1000 FCFA, 5%):</p>";
    echo "<ul>";
    echo "<li>Gain: " . number_format($calcul['gain'], 0, ',', ' ') . " FCFA</li>";
    echo "<li>Commission: " . number_format($calcul['commission'], 0, ',', ' ') . " FCFA</li>";
    echo "</ul>";
    
    // 8. Test de création de dossier
    echo "<h2>8. Test de création de dossier</h2>";
    
    $dossier_qr = 'uploads/qr_codes/';
    if (!is_dir($dossier_qr)) {
        if (mkdir($dossier_qr, 0777, true)) {
            echo "<p style='color: green;'>✅ Dossier QR codes créé: $dossier_qr</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de la création du dossier</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Dossier QR codes existe déjà: $dossier_qr</p>";
    }
    
    // 9. Test d'accès aux fichiers
    echo "<h2>9. Test d'accès aux fichiers</h2>";
    
    $fichiers_requis = [
        'qr_generator.php' => 'Générateur QR',
        'db.php' => 'Connexion base de données',
        'paiement_automatique.php' => 'Paiement automatique'
    ];
    
    foreach ($fichiers_requis as $fichier => $description) {
        if (file_exists($fichier)) {
            echo "<p style='color: green;'>✅ $description ($fichier)</p>";
        } else {
            echo "<p style='color: red;'>❌ $description manquant ($fichier)</p>";
        }
    }
    
    // 10. Test de l'API QR
    echo "<h2>10. Test de l'API QR</h2>";
    
    $test_content = json_encode(['test' => 'data', 'timestamp' => time()]);
    $test_file = 'uploads/qr_codes/test_qr.png';
    
    $api_result = genererQRCodeImage($test_content, $test_file);
    
    if ($api_result) {
        echo "<p style='color: green;'>✅ API QR fonctionnelle</p>";
        if (file_exists($test_file)) {
            echo "<p>Fichier de test créé: $test_file</p>";
            echo "<img src='$test_file' alt='Test QR' style='max-width: 100px;'>";
        }
    } else {
        echo "<p style='color: red;'>❌ Erreur API QR</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur générale: " . $e->getMessage() . "</p>";
    echo "<p>Fichier: " . $e->getFile() . "</p>";
    echo "<p>Ligne: " . $e->getLine() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2, h3 { color: #2563eb; }
p { margin: 5px 0; padding: 5px; border-radius: 3px; }
pre { overflow-x: auto; }
</style> 