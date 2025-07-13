<?php
session_start();
require_once 'db.php';
require_once 'create_notification.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $montant = (int)($_POST['montant'] ?? 0);
    $methode_paiement = $_POST['methode_paiement'] ?? '';
    
    // Validation des données
    if ($montant <= 0) {
        throw new Exception('Montant invalide');
    }
    
    if (empty($methode_paiement)) {
        throw new Exception('Méthode de paiement requise');
    }
    
    // Simuler le processus de paiement (dans un vrai système, on appellerait l'API de paiement)
    $paiement_reussi = simulerPaiement($montant, $methode_paiement);
    
    if (!$paiement_reussi) {
        // Créer une notification d'échec de paiement
        notifyPaiementEchec($user_id, $montant, 'Erreur lors du traitement du paiement');
        
        throw new Exception('Échec du paiement. Veuillez réessayer.');
    }
    
    // Démarrer une transaction
    $pdo->beginTransaction();
    
    // Récupérer le solde actuel
    $stmt = $pdo->prepare("SELECT solde FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $solde_avant = $user['solde'];
    
    // Mettre à jour le solde
    $nouveau_solde = $solde_avant + $montant;
    $stmt = $pdo->prepare("UPDATE users SET solde = ? WHERE id = ?");
    $stmt->execute([$nouveau_solde, $user_id]);
    
    // Enregistrer la transaction
    $stmt = $pdo->prepare("
        INSERT INTO transactions_solde (
            user_id, type_transaction, montant, solde_avant, solde_apres,
            description, date_transaction
        ) VALUES (?, 'credit', ?, ?, ?, ?, NOW())
    ");
    
    $description_transaction = "Recharge solde via $methode_paiement";
    $stmt->execute([$user_id, $montant, $solde_avant, $nouveau_solde, $description_transaction]);
    
    // Valider la transaction
    $pdo->commit();
    
    // Créer une notification de recharge réussie
    notifyRechargeSolde($user_id, $montant, $nouveau_solde);
    
    // Créer une notification de paiement réussi
    notifyPaiementReussi($user_id, $montant, $methode_paiement);
    
    echo json_encode([
        'success' => true,
        'message' => 'Recharge effectuée avec succès',
        'nouveau_solde' => $nouveau_solde,
        'montant_recharge' => $montant
    ]);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Simule un processus de paiement
 * Dans un vrai système, on appellerait l'API de paiement (Orange Money, MTN, etc.)
 */
function simulerPaiement($montant, $methode_paiement) {
    // Simulation d'un délai de traitement
    usleep(500000); // 0.5 seconde
    
    // Simulation d'un taux de succès de 95%
    $succes = (rand(1, 100) <= 95);
    
    // Log de la simulation
    error_log("Simulation paiement: $montant FCFA via $methode_paiement - " . ($succes ? 'SUCCÈS' : 'ÉCHEC'));
    
    return $succes;
}
?> 