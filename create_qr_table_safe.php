<?php
/**
 * Script sécurisé pour créer la table QR codes
 * Sans contraintes de clés étrangères pour éviter les erreurs
 */

require_once 'db.php';

echo "<h1>Création de la table QR Codes</h1>";

try {
    // 1. Vérifier les tables existantes
    echo "<h2>1. Tables existantes</h2>";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Tables trouvées :</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // 2. Vérifier si la table QR codes existe déjà
    echo "<h2>2. Vérification de la table QR codes</h2>";
    
    if (in_array('qr_codes_livraison', $tables)) {
        echo "<p style='color: green;'>✅ Table qr_codes_livraison existe déjà</p>";
        
        // Afficher la structure
        $stmt = $pdo->query("DESCRIBE qr_codes_livraison");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Structure actuelle :</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: orange;'>⚠️ Table qr_codes_livraison n'existe pas</p>";
        
        // 3. Créer la table sans contraintes de clés étrangères
        echo "<h2>3. Création de la table QR codes</h2>";
        
        $sql = "
        CREATE TABLE `qr_codes_livraison` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `livraison_id` int(11) NOT NULL,
          `moto_taxi_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `qr_content` text NOT NULL,
          `qr_filename` varchar(255) NOT NULL,
          `qr_path` varchar(500) NOT NULL,
          `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `date_utilisation` datetime NULL,
          `statut` enum('actif','utilise','expire') NOT NULL DEFAULT 'actif',
          PRIMARY KEY (`id`),
          KEY `idx_livraison_id` (`livraison_id`),
          KEY `idx_moto_taxi_id` (`moto_taxi_id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_statut` (`statut`),
          KEY `idx_date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $result = $pdo->exec($sql);
        
        if ($result !== false) {
            echo "<p style='color: green;'>✅ Table qr_codes_livraison créée avec succès</p>";
            
            // Vérifier la création
            $stmt = $pdo->query("SHOW TABLES LIKE 'qr_codes_livraison'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ Vérification : Table bien créée</p>";
            } else {
                echo "<p style='color: red;'>❌ Erreur : Table non trouvée après création</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de la création de la table</p>";
            $error = $pdo->errorInfo();
            echo "<p>Erreur : " . $error[2] . "</p>";
        }
    }
    
    // 4. Vérifier les tables référencées
    echo "<h2>4. Vérification des tables référencées</h2>";
    
    $tables_requises = ['livraisons', 'moto_taxis', 'users'];
    
    foreach ($tables_requises as $table) {
        if (in_array($table, $tables)) {
            echo "<p style='color: green;'>✅ Table $table existe</p>";
            
            // Compter les enregistrements
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<p>Nombre d'enregistrements : $count</p>";
            
        } else {
            echo "<p style='color: red;'>❌ Table $table manquante</p>";
        }
    }
    
    // 5. Créer le dossier pour les QR codes
    echo "<h2>5. Création du dossier QR codes</h2>";
    
    $dossier_qr = 'uploads/qr_codes/';
    if (!is_dir($dossier_qr)) {
        if (mkdir($dossier_qr, 0777, true)) {
            echo "<p style='color: green;'>✅ Dossier créé : $dossier_qr</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de la création du dossier</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Dossier existe déjà : $dossier_qr</p>";
    }
    
    // 6. Test d'insertion
    echo "<h2>6. Test d'insertion</h2>";
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes_livraison (
                livraison_id, moto_taxi_id, user_id, qr_content, 
                qr_filename, qr_path, statut
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $test_data = [
            1, // livraison_id
            1, // moto_taxi_id
            1, // user_id
            json_encode(['test' => 'data']), // qr_content
            'test_qr.png', // qr_filename
            'uploads/qr_codes/test_qr.png', // qr_path
            'actif' // statut
        ];
        
        $result = $stmt->execute($test_data);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Test d'insertion réussi</p>";
            
            // Supprimer l'enregistrement de test
            $pdo->exec("DELETE FROM qr_codes_livraison WHERE qr_filename = 'test_qr.png'");
            echo "<p style='color: green;'>✅ Enregistrement de test supprimé</p>";
            
        } else {
            echo "<p style='color: red;'>❌ Erreur lors du test d'insertion</p>";
            $error = $stmt->errorInfo();
            echo "<p>Erreur : " . $error[2] . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Exception lors du test : " . $e->getMessage() . "</p>";
    }
    
    // 7. Résumé final
    echo "<h2>7. Résumé</h2>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'qr_codes_livraison'");
    if ($stmt->rowCount() > 0) {
        echo "<div style='background: #dcfce7; padding: 15px; border-radius: 8px; border: 1px solid #16a34a;'>";
        echo "<h3 style='color: #166534; margin-top: 0;'>✅ Système QR Code Prêt</h3>";
        echo "<p style='color: #166534;'>La table qr_codes_livraison a été créée avec succès.</p>";
        echo "<p style='color: #166534;'>Le système QR code est maintenant opérationnel.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #fee2e2; padding: 15px; border-radius: 8px; border: 1px solid #dc2626;'>";
        echo "<h3 style='color: #991b1b; margin-top: 0;'>❌ Erreur de Configuration</h3>";
        echo "<p style='color: #991b1b;'>La table n'a pas pu être créée. Vérifiez les permissions et la connexion à la base de données.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #fee2e2; padding: 15px; border-radius: 8px; border: 1px solid #dc2626;'>";
    echo "<h3 style='color: #991b1b; margin-top: 0;'>❌ Erreur Critique</h3>";
    echo "<p style='color: #991b1b;'>Erreur : " . $e->getMessage() . "</p>";
    echo "<p style='color: #991b1b;'>Fichier : " . $e->getFile() . "</p>";
    echo "<p style='color: #991b1b;'>Ligne : " . $e->getLine() . "</p>";
    echo "</div>";
}
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background: #f5f5f5; 
    line-height: 1.6;
}
h1, h2, h3 { 
    color: #2563eb; 
    margin-top: 20px;
}
p { 
    margin: 8px 0; 
    padding: 5px; 
    border-radius: 3px; 
}
ul { 
    margin: 10px 0; 
    padding-left: 20px; 
}
table { 
    margin: 10px 0; 
    background: white; 
}
th, td { 
    padding: 8px; 
    text-align: left; 
    border: 1px solid #ddd; 
}
th { 
    background: #f8f9fa; 
    font-weight: bold; 
}
</style> 