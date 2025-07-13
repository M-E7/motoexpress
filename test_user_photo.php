<?php
session_start();
require_once 'db.php';

echo "<h1>Test complet du chargement des photos utilisateur</h1>";

// Simuler une session utilisateur pour tester
if (!isset($_SESSION['user_id'])) {
    // Récupérer le premier utilisateur pour le test
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        echo "<p>Session simulée pour l'utilisateur ID: {$user['id']}</p>";
    } else {
        echo "<p style='color: red;'>Aucun utilisateur trouvé dans la base de données.</p>";
        exit;
    }
}

// Récupérer les données utilisateur (même logique que user_home.php)
$stmt = $pdo->prepare("SELECT nom, prenom, solde, email, telephone, photo_profil, points_fidelite FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    echo "<p style='color: red;'>Utilisateur non trouvé.</p>";
    exit;
}

echo "<h2>Données utilisateur :</h2>";
echo "<p><strong>Nom :</strong> {$user['nom']}</p>";
echo "<p><strong>Prénom :</strong> {$user['prenom']}</p>";
echo "<p><strong>Email :</strong> {$user['email']}</p>";
echo "<p><strong>Photo en DB :</strong> " . ($user['photo_profil'] ?: 'NULL') . "</p>";

// Points de fidélité depuis la table users
$points = $user['points_fidelite'] ?? 0;

// Photo de profil - chargement depuis la base de données avec fallback
$photo = 'https://ui-avatars.com/api/?name=' . urlencode($user['prenom'].' '.$user['nom']) . '&background=2563eb&color=fff&size=64';

if ($user['photo_profil']) {
    // Si la photo est stockée en base de données
    if (strpos($user['photo_profil'], 'http') === 0) {
        // Si c'est une URL externe (comme un avatar généré)
        $photo = $user['photo_profil'];
        echo "<p><strong>Type :</strong> URL externe</p>";
    } else {
        // Si c'est un fichier local
        $photo_path = $user['photo_profil'];
        echo "<p><strong>Type :</strong> Fichier local</p>";
        echo "<p><strong>Chemin :</strong> {$photo_path}</p>";
        // Vérifier si le fichier existe (chemin relatif depuis le répertoire courant)
        if (file_exists($photo_path)) {
            $photo = $photo_path;
            echo "<p><strong>Fichier existe :</strong> OUI</p>";
        } else {
            echo "<p><strong>Fichier existe :</strong> NON (utilisation de l'avatar par défaut)</p>";
        }
    }
} else {
    echo "<p><strong>Type :</strong> Aucune photo en DB (utilisation de l'avatar par défaut)</p>";
}

echo "<h2>Photo finale :</h2>";
echo "<img src='{$photo}' alt='Photo de profil' style='width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #2563eb;'>";
echo "<p><strong>URL finale :</strong> {$photo}</p>";

echo "<h2>Test de l'interface utilisateur :</h2>";
echo "<div style='background: #f4f4f4; padding: 20px; border-radius: 8px;'>";
echo "<div style='display: flex; align-items: center; gap: 1rem;'>";
echo "<img src='{$photo}' alt='Photo de profil' style='width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #2563eb;'>";
echo "<div style='font-size: 1.3rem; font-weight: 500;'>Bienvenue, {$user['prenom']} 👋</div>";
echo "</div>";
echo "</div>";

echo "<h2>Actions :</h2>";
echo "<p><a href='add_test_photo.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ajouter une photo de test</a></p>";
echo "<p><a href='check_user_photos.php' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Vérifier toutes les photos</a></p>";
echo "<p><a href='user_home.php' style='background: #ea580c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Retour à l'accueil</a></p>";
?> 