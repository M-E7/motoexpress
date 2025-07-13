<?php
session_start();
require_once 'db.php';

// Simuler une session utilisateur pour les tests
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ID utilisateur de test
}

echo "<h1>Insertion de données de test</h1>";

$user_id = $_SESSION['user_id'];

try {
    // 1. Insérer des moto-taxis de test
    echo "<h2>1. Insertion des moto-taxis</h2>";
    
    $moto_taxis = [
        ['Amadou', 'Dupont', 'amadou@test.com', '237612345678', 'password123', 4.8, 1, 'uploads/mototaxis/amadou.jpg'],
        ['Marie', 'Martin', 'marie@test.com', '237612345679', 'password123', 4.7, 1, 'uploads/mototaxis/marie.jpg'],
        ['Ibrahim', 'Diallo', 'ibrahim@test.com', '237612345680', 'password123', 4.9, 1, 'uploads/mototaxis/ibrahim.jpg']
    ];
    
    foreach ($moto_taxis as $moto) {
        $stmt = $pdo->prepare("
            INSERT INTO moto_taxis (nom, prenom, email, telephone, mot_de_passe, note_moyenne, statut, photo_profil)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($moto);
        echo "<p>✅ Moto-taxi {$moto[0]} {$moto[1]} ajouté</p>";
    }
    
    // 2. Insérer des livraisons de test
    echo "<h2>2. Insertion des livraisons</h2>";
    
    $livraisons = [
        [$user_id, 1, 'livraison', 'Douala, Akwa', 'Douala, Deido', 4.0511, 9.7085, 4.0611, 9.7185, 2.5, 1500, 0, 'Documents importants', 'terminee', '2024-05-01 14:20:00'],
        [$user_id, 2, 'retrait', 'Douala, Akwa', 'Douala, Bonamoussadi', 4.0511, 9.7085, 4.0711, 9.7285, 3.2, 1800, 1, 'Colis fragile', 'en_cours', '2024-05-02 09:10:00'],
        [$user_id, 3, 'livraison', 'Douala, Deido', 'Douala, Akwa', 4.0611, 9.7185, 4.0511, 9.7085, 1.8, 1200, 0, 'Cadeau', 'en_attente', '2024-05-03 17:00:00'],
        [$user_id, 1, 'livraison', 'Douala, Bonamoussadi', 'Douala, Deido', 4.0711, 9.7285, 4.0611, 9.7185, 2.1, 1400, 0, 'Documents', 'terminee', '2024-04-30 10:30:00'],
        [$user_id, 2, 'retrait', 'Douala, Akwa', 'Douala, Bonamoussadi', 4.0511, 9.7085, 4.0711, 9.7285, 3.0, 1700, 1, 'Colis urgent', 'terminee', '2024-04-29 16:45:00']
    ];
    
    foreach ($livraisons as $liv) {
        $stmt = $pdo->prepare("
            INSERT INTO livraisons (
                user_id, moto_taxi_id, type_livraison, adresse_depart, adresse_arrivee,
                latitude_depart, longitude_depart, latitude_arrivee, longitude_arrivee,
                distance, prix, fragile, description, statut, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($liv);
        echo "<p>✅ Livraison #{$liv[0]} ajoutée</p>";
    }
    
    // 3. Insérer des évaluations de test
    echo "<h2>3. Insertion des évaluations</h2>";
    
    $evaluations = [
        [1, 5, 5, 5, 'Excellent service, très ponctuel', '2024-05-01 15:30:00'],
        [4, 4, 5, 4, 'Bon service, colis bien protégé', '2024-04-30 11:30:00'],
        [5, 5, 4, 5, 'Très satisfait, recommande', '2024-04-29 17:30:00']
    ];
    
    foreach ($evaluations as $eval) {
        $stmt = $pdo->prepare("
            INSERT INTO evaluations (livraison_id, ponctualite, etat_colis, politesse, commentaire, date_evaluation)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($eval);
        echo "<p>✅ Évaluation pour livraison #{$eval[0]} ajoutée</p>";
    }
    
    // 4. Insérer des transactions de test
    echo "<h2>4. Insertion des transactions</h2>";
    
    $transactions = [
        [$user_id, 'credit', 5000, 0, 5000, 'Recharge solde via Orange Money', '2024-05-01 10:00:00'],
        [$user_id, 'debit', 1500, 5000, 3500, 'Paiement livraison #1 - livraison', '2024-05-01 14:20:00'],
        [$user_id, 'credit', 3000, 3500, 6500, 'Recharge solde via MTN', '2024-05-02 08:00:00'],
        [$user_id, 'debit', 1800, 6500, 4700, 'Paiement livraison #2 - retrait', '2024-05-02 09:10:00'],
        [$user_id, 'debit', 1200, 4700, 3500, 'Paiement livraison #3 - livraison', '2024-05-03 17:00:00']
    ];
    
    foreach ($transactions as $trans) {
        $stmt = $pdo->prepare("
            INSERT INTO transactions_solde (user_id, type_transaction, montant, solde_avant, solde_apres, description, date_transaction)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($trans);
        echo "<p>✅ Transaction {$trans[1]} de {$trans[2]} FCFA ajoutée</p>";
    }
    
    // 5. Insérer des notifications de test
    echo "<h2>5. Insertion des notifications</h2>";
    
    $notifications = [
        [$user_id, 'livraison_acceptee', 'Livraison acceptée', 'Votre livraison #1 a été acceptée par Amadou', '{"livraison_id": 1, "mototaxi_nom": "Amadou"}', '2024-05-01 14:25:00'],
        [$user_id, 'livraison_terminee', 'Livraison terminée', 'Votre livraison #1 a été livrée avec succès', '{"livraison_id": 1}', '2024-05-01 15:00:00'],
        [$user_id, 'paiement_reussi', 'Paiement réussi', 'Paiement de 5 000 FCFA réussi', '{"montant": 5000, "type_paiement": "Orange Money"}', '2024-05-01 10:05:00'],
        [$user_id, 'recharge_solde', 'Solde rechargé', 'Votre solde a été rechargé de 5 000 FCFA. Nouveau solde: 5 000 FCFA', '{"montant": 5000, "nouveau_solde": 5000}', '2024-05-01 10:05:00'],
        [$user_id, 'promotion', 'Promotion disponible', 'Réduction 20% : 20% de réduction sur votre prochaine livraison', '{"titre_promo": "Réduction 20%", "description": "20% de réduction sur votre prochaine livraison"}', '2024-05-02 12:00:00']
    ];
    
    foreach ($notifications as $notif) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type_notification, titre, message, data_extra, date_creation)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($notif);
        echo "<p>✅ Notification '{$notif[2]}' ajoutée</p>";
    }
    
    // 6. Mettre à jour le solde de l'utilisateur
    echo "<h2>6. Mise à jour du solde utilisateur</h2>";
    
    $stmt = $pdo->prepare("UPDATE users SET solde = 3500, points_fidelite = 150 WHERE id = ?");
    $stmt->execute([$user_id]);
    echo "<p>✅ Solde utilisateur mis à jour (3500 FCFA, 150 points)</p>";
    
    // 7. Insérer des documents de test pour les demandes de retrait
    echo "<h2>7. Insertion des documents de test</h2>";
    
    $documents = [
        [2, 'doc1.pdf', 'document1.pdf', 'application/pdf', 1024000, 'uploads/documents/test1.pdf', '2024-05-02 09:15:00'],
        [2, 'doc2.jpg', 'justificatif.jpg', 'image/jpeg', 512000, 'uploads/documents/test2.jpg', '2024-05-02 09:16:00'],
        [5, 'doc3.pdf', 'facture.pdf', 'application/pdf', 2048000, 'uploads/documents/test3.pdf', '2024-04-29 16:50:00']
    ];
    
    foreach ($documents as $doc) {
        $stmt = $pdo->prepare("
            INSERT INTO documents_livraison (livraison_id, nom_fichier, nom_original, type_fichier, taille_fichier, chemin_fichier, date_upload)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($doc);
        echo "<p>✅ Document '{$doc[2]}' ajouté pour livraison #{$doc[0]}</p>";
    }
    
    echo "<h2>✅ Données de test insérées avec succès !</h2>";
    echo "<p><a href='user_home.php' target='_blank'>Accéder à user_home.php</a></p>";
    echo "<p><a href='test_data_loading.php' target='_blank'>Vérifier les données</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur: " . $e->getMessage() . "</p>";
    
    // Afficher les détails de l'erreur pour le débogage
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo "<p>⚠️ Certaines données existent déjà. C'est normal si vous avez déjà exécuté ce script.</p>";
    }
}
?> 