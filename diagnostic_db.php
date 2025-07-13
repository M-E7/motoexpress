<?php
/**
 * Diagnostic complet de la base de données
 * Pour identifier les problèmes avec les tables et contraintes
 */

require_once 'db.php';

echo "<h1>Diagnostic Base de Données</h1>";

try {
    // 1. Test de connexion
    echo "<h2>1. Test de connexion</h2>";
    
    if ($pdo) {
        echo "<p style='color: green;'>✅ Connexion PDO établie</p>";
        
        // Test de requête simple
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        if ($result && $result['test'] == 1) {
            echo "<p style='color: green;'>✅ Requête de test réussie</p>";
        } else {
            echo "<p style='color: red;'>❌ Échec de la requête de test</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Échec de la connexion PDO</p>";
        exit;
    }
    
    // 2. Informations sur la base de données
    echo "<h2>2. Informations sur la base de données</h2>";
    
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $db_info = $stmt->fetch();
    echo "<p><strong>Base de données active :</strong> " . $db_info['db_name'] . "</p>";
    
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "<p><strong>Version MySQL :</strong> " . $version['version'] . "</p>";
    
    // 3. Liste des tables
    echo "<h2>3. Tables existantes</h2>";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ Aucune table trouvée dans la base de données</p>";
    } else {
        echo "<p style='color: green;'>✅ " . count($tables) . " table(s) trouvée(s)</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
    
    // 4. Vérification des tables requises
    echo "<h2>4. Vérification des tables requises</h2>";
    
    $tables_requises = [
        'livraisons' => 'Table principale des livraisons',
        'moto_taxis' => 'Table des moto-taxis',
        'users' => 'Table des utilisateurs',
        'qr_codes_livraison' => 'Table des QR codes (à créer)'
    ];
    
    foreach ($tables_requises as $table => $description) {
        if (in_array($table, $tables)) {
            echo "<p style='color: green;'>✅ $table - $description</p>";
            
            // Compter les enregistrements
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                echo "<p style='margin-left: 20px; color: #666;'>Nombre d'enregistrements : $count</p>";
            } catch (Exception $e) {
                echo "<p style='margin-left: 20px; color: red;'>Erreur de comptage : " . $e->getMessage() . "</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ $table - $description (MANQUANTE)</p>";
        }
    }
    
    // 5. Structure des tables principales
    echo "<h2>5. Structure des tables principales</h2>";
    
    $tables_principales = ['livraisons', 'moto_taxis', 'users'];
    
    foreach ($tables_principales as $table) {
        if (in_array($table, $tables)) {
            echo "<h3>Table : $table</h3>";
            
            try {
                $stmt = $pdo->query("DESCRIBE `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
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
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>Erreur lors de la description : " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // 6. Test de création de la table QR codes
    echo "<h2>6. Test de création de la table QR codes</h2>";
    
    if (in_array('qr_codes_livraison', $tables)) {
        echo "<p style='color: green;'>✅ Table qr_codes_livraison existe déjà</p>";
        
        // Afficher la structure
        $stmt = $pdo->query("DESCRIBE qr_codes_livraison");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        
        // Essayer de créer la table
        echo "<h3>Création de la table QR codes</h3>";
        
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
        
        try {
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
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Exception lors de la création : " . $e->getMessage() . "</p>";
        }
    }
    
    // 7. Test des permissions
    echo "<h2>7. Test des permissions</h2>";
    
    try {
        // Test de lecture
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables");
        $count = $stmt->fetchColumn();
        echo "<p style='color: green;'>✅ Permissions de lecture OK</p>";
        
        // Test d'écriture (si possible)
        if (in_array('qr_codes_livraison', $tables)) {
            $stmt = $pdo->prepare("INSERT INTO qr_codes_livraison (livraison_id, moto_taxi_id, user_id, qr_content, qr_filename, qr_path) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([1, 1, 1, 'test', 'test.png', 'test.png']);
            
            if ($result) {
                echo "<p style='color: green;'>✅ Permissions d'écriture OK</p>";
                
                // Nettoyer le test
                $pdo->exec("DELETE FROM qr_codes_livraison WHERE qr_filename = 'test.png'");
                echo "<p style='color: green;'>✅ Permissions de suppression OK</p>";
            } else {
                echo "<p style='color: red;'>❌ Erreur permissions d'écriture</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur permissions : " . $e->getMessage() . "</p>";
    }
    
    // 8. Recommandations
    echo "<h2>8. Recommandations</h2>";
    
    $problemes = [];
    
    if (!in_array('livraisons', $tables)) {
        $problemes[] = "Table 'livraisons' manquante - Créez d'abord cette table";
    }
    
    if (!in_array('moto_taxis', $tables)) {
        $problemes[] = "Table 'moto_taxis' manquante - Créez d'abord cette table";
    }
    
    if (!in_array('users', $tables)) {
        $problemes[] = "Table 'users' manquante - Créez d'abord cette table";
    }
    
    if (!in_array('qr_codes_livraison', $tables)) {
        $problemes[] = "Table 'qr_codes_livraison' manquante - Utilisez le script de création";
    }
    
    if (empty($problemes)) {
        echo "<div style='background: #dcfce7; padding: 15px; border-radius: 8px; border: 1px solid #16a34a;'>";
        echo "<h3 style='color: #166534; margin-top: 0;'>✅ Base de données OK</h3>";
        echo "<p style='color: #166534;'>Toutes les tables requises sont présentes.</p>";
        echo "<p style='color: #166534;'>Le système QR code peut être utilisé.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #fee2e2; padding: 15px; border-radius: 8px; border: 1px solid #dc2626;'>";
        echo "<h3 style='color: #991b1b; margin-top: 0;'>⚠️ Problèmes détectés</h3>";
        echo "<ul style='color: #991b1b;'>";
        foreach ($problemes as $probleme) {
            echo "<li>$probleme</li>";
        }
        echo "</ul>";
        echo "<p style='color: #991b1b;'>Utilisez le script create_qr_table_safe.php pour créer la table QR codes.</p>";
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