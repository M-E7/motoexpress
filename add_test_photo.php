<?php
require_once 'db.php';

echo "<h2>Ajout d'une photo de test</h2>";

try {
    // Vérifier s'il y a des utilisateurs
    $stmt = $pdo->query("SELECT id, nom, prenom, photo_profil FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<p>Aucun utilisateur trouvé. Créez d'abord un utilisateur via register.php</p>";
        exit;
    }
    
    echo "<p>Utilisateur trouvé : {$user['prenom']} {$user['nom']} (ID: {$user['id']})</p>";
    
    if ($user['photo_profil']) {
        echo "<p>L'utilisateur a déjà une photo : {$user['photo_profil']}</p>";
    } else {
        echo "<p>L'utilisateur n'a pas de photo. Création d'une photo de test...</p>";
        
        // Créer une image de test simple
        $image = imagecreate(100, 100);
        $bg_color = imagecolorallocate($image, 37, 99, 235); // Bleu
        $text_color = imagecolorallocate($image, 255, 255, 255); // Blanc
        
        // Ajouter du texte
        $text = substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1);
        $font_size = 5;
        $text_width = imagefontwidth($font_size) * strlen($text);
        $text_height = imagefontheight($font_size);
        $x = (100 - $text_width) / 2;
        $y = (100 - $text_height) / 2;
        
        imagestring($image, $font_size, $x, $y, $text, $text_color);
        
        // Sauvegarder l'image
        $filename = 'uploads/test_photo_' . $user['id'] . '.png';
        imagepng($image, $filename);
        imagedestroy($image);
        
        // Mettre à jour la base de données
        $stmt = $pdo->prepare("UPDATE users SET photo_profil = ? WHERE id = ?");
        $stmt->execute([$filename, $user['id']]);
        
        echo "<p>Photo de test créée : {$filename}</p>";
        echo "<img src='{$filename}' alt='Photo de test' style='width:100px;height:100px;border:2px solid #ccc;'>";
    }
    
    // Afficher l'état final
    $stmt = $pdo->prepare("SELECT photo_profil FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $updated_user = $stmt->fetch();
    
    echo "<h3>État final :</h3>";
    echo "<p>Photo en base : " . ($updated_user['photo_profil'] ?: 'NULL') . "</p>";
    if ($updated_user['photo_profil']) {
        echo "<p>Fichier existe : " . (file_exists($updated_user['photo_profil']) ? 'OUI' : 'NON') . "</p>";
        if (file_exists($updated_user['photo_profil'])) {
            echo "<img src='{$updated_user['photo_profil']}' alt='Photo finale' style='width:100px;height:100px;border:2px solid #ccc;'>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur : " . $e->getMessage() . "</p>";
}
?> 