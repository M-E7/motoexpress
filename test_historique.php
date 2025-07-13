<?php
session_start();
require_once 'db.php';

echo "<h1>Test de la page historique</h1>";

// Vérifier la session
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>Aucune session utilisateur active.</p>";
    echo "<p><a href='login.php'>Se connecter</a></p>";
    exit;
}

echo "<p><strong>Session utilisateur ID :</strong> {$_SESSION['user_id']}</p>";

// Récupérer les données utilisateur
$stmt = $pdo->prepare("SELECT nom, prenom, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    echo "<p style='color: red;'>Utilisateur non trouvé.</p>";
    exit;
}

echo "<p><strong>Utilisateur :</strong> {$user['prenom']} {$user['nom']} ({$user['email']})</p>";

// Vérifier les livraisons
$stmt = $pdo->prepare("
    SELECT l.*, 
           m.nom as mototaxi_nom,
           m.telephone as mototaxi_telephone,
           e.ponctualite, e.etat_colis, e.politesse, e.commentaire,
           (e.ponctualite + e.etat_colis + e.politesse) / 3 as note_moyenne
    FROM livraisons l
    LEFT JOIN moto_taxis m ON l.moto_taxi_id = m.id
    LEFT JOIN evaluations e ON l.id = e.livraison_id
    WHERE l.user_id = ?
    ORDER BY l.date_demande DESC
");
$stmt->execute([$_SESSION['user_id']]);
$livraisons = $stmt->fetchAll();

echo "<h2>Livraisons trouvées : " . count($livraisons) . "</h2>";

if (empty($livraisons)) {
    echo "<p style='color: orange;'>Aucune livraison trouvée pour cet utilisateur.</p>";
    echo "<p><a href='insert_test_data.php'>Insérer des données de test</a></p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Date</th><th>Départ</th><th>Arrivée</th><th>Statut</th><th>Prix</th><th>Moto-taxi</th><th>Note</th>";
    echo "</tr>";
    
    foreach ($livraisons as $livraison) {
        echo "<tr>";
        echo "<td>{$livraison['id']}</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($livraison['date_demande'])) . "</td>";
        echo "<td>" . htmlspecialchars(substr($livraison['adresse_depart'], 0, 30)) . "...</td>";
        echo "<td>" . htmlspecialchars(substr($livraison['adresse_arrivee'], 0, 30)) . "...</td>";
        echo "<td>{$livraison['statut']}</td>";
        echo "<td>" . number_format($livraison['prix_total'], 0, ',', ' ') . " FCFA</td>";
        echo "<td>" . ($livraison['mototaxi_nom'] ?: 'Non assigné') . "</td>";
        echo "<td>" . ($livraison['note_moyenne'] ? number_format($livraison['note_moyenne'], 1) : 'Non évalué') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<h2>Actions :</h2>";
echo "<p><a href='historique.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voir la page historique</a></p>";
echo "<p><a href='user_home.php' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Retour à l'accueil</a></p>";
echo "<p><a href='insert_test_data.php' style='background: #ea580c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Insérer des données de test</a></p>";
?> 