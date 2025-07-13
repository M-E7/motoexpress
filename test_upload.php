<?php
session_start();
require_once 'db.php';

// Simuler une session utilisateur pour les tests
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ID utilisateur de test
}

echo "<h1>Test d'upload de documents</h1>";

// Vérifier que le dossier uploads existe
if (!is_dir('uploads/documents')) {
    echo "<p style='color: red;'>❌ Le dossier uploads/documents n'existe pas</p>";
} else {
    echo "<p style='color: green;'>✅ Le dossier uploads/documents existe</p>";
}

// Vérifier les permissions du dossier
if (is_writable('uploads/documents')) {
    echo "<p style='color: green;'>✅ Le dossier uploads/documents est accessible en écriture</p>";
} else {
    echo "<p style='color: red;'>❌ Le dossier uploads/documents n'est pas accessible en écriture</p>";
}

// Vérifier la connexion à la base de données
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p style='color: green;'>✅ Connexion à la base de données OK (${result['count']} utilisateurs)</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</p>";
}

// Vérifier que la table documents_livraison existe
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM documents_livraison");
    $result = $stmt->fetch();
    echo "<p style='color: green;'>✅ Table documents_livraison OK (${result['count']} documents)</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Table documents_livraison manquante: " . $e->getMessage() . "</p>";
}

// Formulaire de test simple
echo "<h2>Formulaire de test</h2>";
echo "<form action='create_livraison.php' method='post' enctype='multipart/form-data'>";
echo "<input type='hidden' name='type_livraison' value='retrait'>";
echo "<input type='hidden' name='adresse_depart' value='Test départ'>";
echo "<input type='hidden' name='adresse_arrivee' value='Test arrivée'>";
echo "<input type='hidden' name='latitude_depart' value='6.5244'>";
echo "<input type='hidden' name='longitude_depart' value='3.3792'>";
echo "<input type='hidden' name='latitude_arrivee' value='6.5244'>";
echo "<input type='hidden' name='longitude_arrivee' value='3.3792'>";
echo "<input type='hidden' name='moto_taxi_id' value='1'>";
echo "<input type='hidden' name='description' value='Test de livraison'>";
echo "<p><label>Document de test: <input type='file' name='documents[]' accept='.pdf,image/*'></label></p>";
echo "<p><button type='submit'>Tester l'upload</button></p>";
echo "</form>";

// Afficher les documents existants
echo "<h2>Documents existants</h2>";
try {
    $stmt = $pdo->query("
        SELECT dl.*, l.type_livraison, l.adresse_depart 
        FROM documents_livraison dl 
        JOIN livraisons l ON dl.livraison_id = l.id 
        ORDER BY dl.date_upload DESC 
        LIMIT 10
    ");
    $documents = $stmt->fetchAll();
    
    if (empty($documents)) {
        echo "<p>Aucun document trouvé</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Livraison</th><th>Type</th><th>Fichier</th><th>Taille</th><th>Date</th><th>Action</th></tr>";
        
        foreach ($documents as $doc) {
            $file_size = number_format($doc['taille_fichier'] / 1024, 2) . ' KB';
            echo "<tr>";
            echo "<td>{$doc['id']}</td>";
            echo "<td>{$doc['livraison_id']} ({$doc['type_livraison']})</td>";
            echo "<td>{$doc['type_fichier']}</td>";
            echo "<td>{$doc['nom_original']}</td>";
            echo "<td>{$file_size}</td>";
            echo "<td>{$doc['date_upload']}</td>";
            echo "<td><a href='download_document.php?document_id={$doc['id']}' target='_blank'>Télécharger</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Erreur lors de la récupération des documents: " . $e->getMessage() . "</p>";
}
?> 