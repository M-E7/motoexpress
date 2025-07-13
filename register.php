<?php
require_once 'db.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $mot_de_passe2 = $_POST['mot_de_passe2'] ?? '';
    $photo_profil = null;

    // Vérification des champs obligatoires
    if (!$nom || !$prenom || !$email || !$telephone || !$mot_de_passe || !$mot_de_passe2) {
        $message = "Tous les champs sont obligatoires.";
    } elseif ($mot_de_passe !== $mot_de_passe2) {
        $message = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier unicité email/téléphone
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR telephone = ?");
        $stmt->execute([$email, $telephone]);
        if ($stmt->fetch()) {
            $message = "Email ou téléphone déjà utilisé.";
        } else {
            // Gestion de la photo de profil (optionnelle)
            if (!empty($_FILES['photo_profil']['name'])) {
                $target_dir = 'uploads/';
                if (!is_dir($target_dir)) mkdir($target_dir);
                $ext = pathinfo($_FILES['photo_profil']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('user_') . '.' . $ext;
                $target_file = $target_dir . $filename;
                if (move_uploaded_file($_FILES['photo_profil']['tmp_name'], $target_file)) {
                    $photo_profil = $target_file;
                }
            }
            // Hash du mot de passe
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            // Insertion
            $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, telephone, mot_de_passe, photo_profil) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $prenom, $email, $telephone, $hash, $photo_profil]);
            $message = "Inscription réussie. <a href='login.php'>Connectez-vous</a>.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription Utilisateur</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 400px; margin: 40px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px #ccc; }
        input, label { display: block; width: 100%; margin-bottom: 12px; }
        input[type="submit"] { width: auto; background: #007bff; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        .msg { color: red; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Inscription Utilisateur</h2>
    <?php if ($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label>Nom
            <input type="text" name="nom" required>
        </label>
        <label>Prénom
            <input type="text" name="prenom" required>
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Téléphone
            <input type="text" name="telephone" required>
        </label>
        <label>Mot de passe
            <input type="password" name="mot_de_passe" required>
        </label>
        <label>Confirmer le mot de passe
            <input type="password" name="mot_de_passe2" required>
        </label>
        <label>Photo de profil (optionnelle)
            <input type="file" name="photo_profil" accept="image/*">
        </label>
        <input type="submit" value="S'inscrire">
    </form>
    <p>Déjà inscrit ? <a href="login.php">Connectez-vous</a></p>
</div>
</body>
</html> 