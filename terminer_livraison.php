<?php
session_start();
require_once 'db.php';
require_once 'paiement_automatique.php';

if (!isset($_SESSION['moto_taxi_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$livraison_id = $input['livraison_id'] ?? 0;

if (!$livraison_id) {
    echo json_encode(['success' => false, 'message' => 'ID de livraison manquant']);
    exit;
}

try {
    // Vérifier que la livraison appartient au moto-taxi et est en cours
    $stmt = $pdo->prepare("SELECT * FROM livraisons WHERE id = ? AND moto_taxi_id = ? AND statut = 'en_cours'");
    $stmt->execute([$livraison_id, $_SESSION['moto_taxi_id']]);
    $livraison = $stmt->fetch();
    
    if (!$livraison) {
        throw new Exception('Livraison non trouvée ou déjà terminée.');
    }
    
    // Effectuer le paiement automatique (inclut la mise à jour du statut)
    $resultat_paiement = effectuerPaiementAutomatique($livraison_id, $_SESSION['moto_taxi_id'], 0.05, 'moto_taxi');
    
    if (!$resultat_paiement['success']) {
        throw new Exception($resultat_paiement['message']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Livraison terminée avec succès',
        'gain' => $resultat_paiement['gain'],
        'commission' => $resultat_paiement['commission'],
        'nouveau_solde' => $resultat_paiement['nouveau_solde']
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 