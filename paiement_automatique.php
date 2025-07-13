<?php
/**
 * Fonction utilitaire pour le paiement automatique des moto-taxis
 * Gère la mise à jour du solde et l'enregistrement des transactions
 */

require_once 'db.php';

/**
 * Effectue le paiement automatique d'une livraison terminée
 * 
 * @param int $livraison_id ID de la livraison
 * @param int $moto_taxi_id ID du moto-taxi
 * @param float $commission_rate Taux de commission (défaut: 0.05 = 5%)
 * @param string $source Source du paiement ('moto_taxi', 'admin', etc.)
 * @return array Résultat de l'opération
 */
function effectuerPaiementAutomatique($livraison_id, $moto_taxi_id, $commission_rate = 0.05, $source = 'moto_taxi') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer les informations de la livraison
        $stmt = $pdo->prepare("SELECT * FROM livraisons WHERE id = ? AND moto_taxi_id = ? AND statut = 'en_cours'");
        $stmt->execute([$livraison_id, $moto_taxi_id]);
        $livraison = $stmt->fetch();
        
        if (!$livraison) {
            throw new Exception('Livraison non trouvée, ne correspond pas au moto-taxi ou n\'est pas en cours.');
        }
        
        // Vérifier que la livraison n'a pas déjà été payée
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions_moto_taxi WHERE livraison_id = ? AND type = 'gain'");
        $stmt->execute([$livraison_id]);
        $deja_payee = $stmt->fetchColumn() > 0;
        
        if ($deja_payee) {
            throw new Exception('Cette livraison a déjà été payée.');
        }
        
        // Calculer le gain du moto-taxi
        $montant_livraison = $livraison['montant'];
        $commission = $montant_livraison * $commission_rate;
        $gain_moto_taxi = $montant_livraison - $commission;
        
        // Récupérer le solde actuel du moto-taxi
        $stmt = $pdo->prepare("SELECT solde FROM moto_taxis WHERE id = ?");
        $stmt->execute([$moto_taxi_id]);
        $solde_actuel = $stmt->fetchColumn();
        
        // Calculer le nouveau solde
        $nouveau_solde = $solde_actuel + $gain_moto_taxi;
        
        // Mettre à jour le statut de la livraison
        $stmt = $pdo->prepare("UPDATE livraisons SET statut = 'terminee', date_fin_livraison = NOW() WHERE id = ?");
        $stmt->execute([$livraison_id]);
        
        // Mettre à jour le solde du moto-taxi
        $stmt = $pdo->prepare("UPDATE moto_taxis SET solde = ? WHERE id = ?");
        $stmt->execute([$nouveau_solde, $moto_taxi_id]);
        
        // Enregistrer la transaction de gain pour le moto-taxi
        $stmt = $pdo->prepare("
            INSERT INTO transactions_moto_taxi (
                moto_taxi_id, type, montant, solde_avant, solde_apres, 
                livraison_id, description, statut, date_transaction
            ) VALUES (?, 'gain', ?, ?, ?, ?, ?, 'terminee', NOW())
        ");
        $description_gain = "Gain livraison #$livraison_id ($source) - " . number_format($gain_moto_taxi, 0, ',', ' ') . " FCFA (Commission: " . number_format($commission, 0, ',', ' ') . " FCFA)";
        $stmt->execute([$moto_taxi_id, $gain_moto_taxi, $solde_actuel, $nouveau_solde, $livraison_id, $description_gain]);
        
        // Enregistrer la transaction de commission pour la plateforme
        $stmt = $pdo->prepare("
            INSERT INTO transactions_moto_taxi (
                moto_taxi_id, type, montant, solde_avant, solde_apres, 
                livraison_id, description, statut, date_transaction
            ) VALUES (?, 'commission', ?, ?, ?, ?, ?, 'terminee', NOW())
        ");
        $description_commission = "Commission plateforme livraison #$livraison_id ($source) - " . number_format($commission, 0, ',', ' ') . " FCFA";
        $stmt->execute([$moto_taxi_id, $commission, $nouveau_solde, $nouveau_solde, $livraison_id, $description_commission]);
        
        // Mettre à jour les statistiques du moto-taxi
        $stmt = $pdo->prepare("
            UPDATE moto_taxis SET 
            nombre_livraisons = nombre_livraisons + 1,
            points_fidelite = points_fidelite + 1
            WHERE id = ?
        ");
        $stmt->execute([$moto_taxi_id]);
        
        // Créer une notification pour le moto-taxi
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type_user, titre, message, date_creation) 
            VALUES (?, 'moto_taxi', 'Livraison terminée', 'Livraison terminée avec succès. Gain: " . number_format($gain_moto_taxi, 0, ',', ' ') . " FCFA', NOW())
        ");
        $stmt->execute([$moto_taxi_id]);
        
        // Créer une notification pour l'utilisateur
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type_user, titre, message, date_creation) 
            VALUES (?, 'user', 'Livraison terminée', 'Votre livraison a été marquée comme terminée.', NOW())
        ");
        $stmt->execute([$livraison['user_id']]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'gain' => $gain_moto_taxi,
            'commission' => $commission,
            'nouveau_solde' => $nouveau_solde,
            'message' => 'Paiement effectué avec succès'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Vérifie si une livraison a déjà été payée
 * 
 * @param int $livraison_id ID de la livraison
 * @return bool True si déjà payée, False sinon
 */
function livraisonDejaPayee($livraison_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions_moto_taxi WHERE livraison_id = ? AND type = 'gain'");
    $stmt->execute([$livraison_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Calcule le gain et la commission pour une livraison
 * 
 * @param float $montant_livraison Montant de la livraison
 * @param float $commission_rate Taux de commission (défaut: 0.05 = 5%)
 * @return array ['gain' => float, 'commission' => float]
 */
function calculerGainEtCommission($montant_livraison, $commission_rate = 0.05) {
    $commission = $montant_livraison * $commission_rate;
    $gain = $montant_livraison - $commission;
    
    return [
        'gain' => $gain,
        'commission' => $commission
    ];
}

/**
 * Met à jour les statistiques d'un moto-taxi après une livraison
 * 
 * @param int $moto_taxi_id ID du moto-taxi
 * @return bool Succès de l'opération
 */
function mettreAJourStatistiquesMotoTaxi($moto_taxi_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE moto_taxis SET 
            nombre_livraisons = nombre_livraisons + 1,
            points_fidelite = points_fidelite + 1
            WHERE id = ?
        ");
        $stmt->execute([$moto_taxi_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?> 