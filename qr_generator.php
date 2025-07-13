<?php
/**
 * Générateur de QR codes pour les livraisons
 * Nécessite l'extension PHP QR Code ou une bibliothèque alternative
 */

require_once 'db.php';

/**
 * Génère un QR code pour une livraison
 * 
 * @param int $livraison_id ID de la livraison
 * @param int $moto_taxi_id ID du moto-taxi
 * @return array Résultat de la génération
 */
function genererQRCodeLivraison($livraison_id, $moto_taxi_id) {
    global $pdo;
    
    try {
        // Vérifier que la livraison existe et appartient au moto-taxi
        $stmt = $pdo->prepare("
            SELECT l.*, u.nom as user_nom, u.prenom as user_prenom, u.telephone as user_telephone,
                   mt.nom as moto_nom, mt.prenom as moto_prenom, mt.telephone as moto_telephone
            FROM livraisons l
            JOIN users u ON l.user_id = u.id
            JOIN moto_taxis mt ON l.moto_taxi_id = mt.id
            WHERE l.id = ? AND l.moto_taxi_id = ?
        ");
        $stmt->execute([$livraison_id, $moto_taxi_id]);
        $livraison = $stmt->fetch();
        
        if (!$livraison) {
            return ['success' => false, 'message' => 'Livraison non trouvée'];
        }
        
        // Créer les données du QR code
        $qr_data = [
            'livraison_id' => $livraison_id,
            'moto_taxi_id' => $moto_taxi_id,
            'user_id' => $livraison['user_id'],
            'timestamp' => time(),
            'type' => 'livraison_confirmation'
        ];
        
        $qr_content = json_encode($qr_data);
        
        // Générer un nom de fichier unique
        $qr_filename = 'qr_livraison_' . $livraison_id . '_' . time() . '.png';
        $qr_path = 'uploads/qr_codes/' . $qr_filename;
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir('uploads/qr_codes/')) {
            mkdir('uploads/qr_codes/', 0777, true);
        }
        
        // Générer le QR code (utiliser une bibliothèque QR)
        $qr_generated = genererQRCodeImage($qr_content, $qr_path);
        
        if (!$qr_generated) {
            return ['success' => false, 'message' => 'Erreur lors de la génération du QR code'];
        }
        
        // Enregistrer les informations du QR code dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes_livraison (
                livraison_id, moto_taxi_id, user_id, qr_content, qr_filename, 
                qr_path, date_creation, statut
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'actif')
        ");
        $stmt->execute([
            $livraison_id, 
            $moto_taxi_id, 
            $livraison['user_id'],
            $qr_content,
            $qr_filename,
            $qr_path
        ]);
        
        return [
            'success' => true,
            'qr_path' => $qr_path,
            'qr_filename' => $qr_filename,
            'qr_content' => $qr_content,
            'livraison' => $livraison
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Génère une image QR code
 * 
 * @param string $content Contenu à encoder
 * @param string $filepath Chemin du fichier de sortie
 * @return bool Succès de la génération
 */
function genererQRCodeImage($content, $filepath) {
    // Méthode 1: Utiliser l'API Google Charts (solution simple)
    $qr_url = 'https://chart.googleapis.com/chart?chs=300x300&chld=M|0&cht=qr&chl=' . urlencode($content);
    
    $image_content = file_get_contents($qr_url);
    
    if ($image_content !== false) {
        return file_put_contents($filepath, $image_content) !== false;
    }
    
    // Méthode 2: Utiliser une bibliothèque PHP QR Code si disponible
    if (class_exists('QRcode')) {
        QRcode::png($content, $filepath, QR_ECLEVEL_L, 10);
        return file_exists($filepath);
    }
    
    // Méthode 3: Utiliser l'API QR Server
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($content);
    
    $image_content = file_get_contents($qr_url);
    
    if ($image_content !== false) {
        return file_put_contents($filepath, $image_content) !== false;
    }
    
    return false;
}

/**
 * Récupère le QR code d'une livraison
 * 
 * @param int $livraison_id ID de la livraison
 * @return array Informations du QR code
 */
function getQRCodeLivraison($livraison_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT qr.*, l.montant, l.adresse_depart, l.adresse_arrivee,
                   u.nom as user_nom, u.prenom as user_prenom,
                   mt.nom as moto_nom, mt.prenom as moto_prenom
            FROM qr_codes_livraison qr
            JOIN livraisons l ON qr.livraison_id = l.id
            JOIN users u ON qr.user_id = u.id
            JOIN moto_taxis mt ON qr.moto_taxi_id = mt.id
            WHERE qr.livraison_id = ? AND qr.statut = 'actif'
            ORDER BY qr.date_creation DESC
            LIMIT 1
        ");
        $stmt->execute([$livraison_id]);
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Valide un QR code scanné
 * 
 * @param string $qr_content Contenu du QR code scanné
 * @return array Résultat de la validation
 */
function validerQRCode($qr_content) {
    global $pdo;
    
    try {
        $qr_data = json_decode($qr_content, true);
        
        if (!$qr_data || !isset($qr_data['livraison_id']) || !isset($qr_data['type'])) {
            return ['success' => false, 'message' => 'QR code invalide'];
        }
        
        if ($qr_data['type'] !== 'livraison_confirmation') {
            return ['success' => false, 'message' => 'Type de QR code non reconnu'];
        }
        
        // Vérifier que la livraison existe et est en cours
        $stmt = $pdo->prepare("
            SELECT l.*, qr.id as qr_id
            FROM livraisons l
            LEFT JOIN qr_codes_livraison qr ON l.id = qr.livraison_id
            WHERE l.id = ? AND l.statut = 'en_cours'
        ");
        $stmt->execute([$qr_data['livraison_id']]);
        $livraison = $stmt->fetch();
        
        if (!$livraison) {
            return ['success' => false, 'message' => 'Livraison non trouvée ou déjà terminée'];
        }
        
        // Vérifier que le QR code n'a pas expiré (24h)
        $qr_timestamp = $qr_data['timestamp'];
        if (time() - $qr_timestamp > 86400) { // 24 heures
            return ['success' => false, 'message' => 'QR code expiré'];
        }
        
        return [
            'success' => true,
            'livraison' => $livraison,
            'qr_data' => $qr_data
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Marque un QR code comme utilisé
 * 
 * @param int $livraison_id ID de la livraison
 * @return bool Succès de l'opération
 */
function marquerQRCodeUtilise($livraison_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE qr_codes_livraison 
            SET statut = 'utilise', date_utilisation = NOW() 
            WHERE livraison_id = ? AND statut = 'actif'
        ");
        return $stmt->execute([$livraison_id]);
        
    } catch (Exception $e) {
        return false;
    }
}
?> 