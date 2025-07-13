<?php
require_once 'db.php';

echo "<h2>Vérification des photos utilisateurs</h2>";

try {
    // Récupérer tous les utilisateurs
    $stmt = $pdo->query("SELECT id, nom, prenom, photo_profil FROM users");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p>Aucun utilisateur trouvé dans la base de données.</p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Nom</th><th>Prénom</th><th>Photo en DB</th><th>Fichier existe</th><th>Photo finale</th>";
    echo "</tr>";
    
    foreach ($users as $user) {
        $photo_db = $user['photo_profil'];
        $file_exists = 'N/A';
        
        if ($photo_db) {
            $file_exists = file_exists($photo_db) ? 'OUI' : 'NON';
        }
        
        // Logique de chargement de photo (même que dans user_home.php)
        $photo = 'https://ui-avatars.com/api/?name=' . urlencode($user['prenom'].' '.$user['nom']) . '&background=2563eb&color=fff&size=64';
        
        if ($photo_db) {
            if (strpos($photo_db, 'http') === 0) {
                $photo = $photo_db;
            } else {
                if (file_exists($photo_db)) {
                    $photo = $photo_db;
                }
            }
        }
        
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['nom']}</td>";
        echo "<td>{$user['prenom']}</td>";
        echo "<td>" . ($photo_db ?: 'NULL') . "</td>";
        echo "<td>{$file_exists}</td>";
        echo "<td><img src='{$photo}' alt='Photo' style='width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #ccc;'></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Contenu du dossier uploads/ :</h3>";
    if (is_dir('uploads/')) {
        $files = scandir('uploads/');
        if (count($files) <= 2) {
            echo "<p>Le dossier uploads/ est vide.</p>";
        } else {
            echo "<ul>";
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    echo "<li>{$file}</li>";
                }
            }
            echo "</ul>";
        }
    } else {
        echo "<p>Le dossier uploads/ n'existe pas.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur : " . $e->getMessage() . "</p>";
}
?> 