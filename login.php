<?php
session_start();
require_once 'db.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (!$identifiant || !$mot_de_passe) {
        $message = "Tous les champs sont obligatoires.";
    } else {
        // Recherche par email ou téléphone
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR telephone = ?");
        $stmt->execute([$identifiant, $identifiant]);
        $user = $stmt->fetch();
        if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
            // Connexion OK
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            header('Location: user_home.php');
            exit;
        } else {
            $message = "Identifiants invalides.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Utilisateur</title>
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
    <h2>Connexion Utilisateur</h2>
    <?php if ($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
    <form method="post">
        <label>Email ou Téléphone
            <input type="text" name="identifiant" required>
        </label>
        <label>Mot de passe
            <input type="password" name="mot_de_passe" required>
        </label>
        <input type="submit" value="Se connecter">
    </form>
    <p>Pas encore de compte ? <a href="register.php">Inscrivez-vous</a></p>
</div>
</body>
</html>
