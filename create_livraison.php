<?php
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
    
    // Récupérer les données du formulaire
    $type_livraison = $_POST['type_livraison'] ?? '';
    $adresse_depart = $_POST['adresse_depart'] ?? '';
    $adresse_arrivee = $_POST['adresse_arrivee'] ?? '';
    $latitude_depart = $_POST['latitude_depart'] ?? '';
    $longitude_depart = $_POST['longitude_depart'] ?? '';
    $latitude_arrivee = $_POST['latitude_arrivee'] ?? '';
    $longitude_arrivee = $_POST['longitude_arrivee'] ?? '';
    $moto_taxi_id = $_POST['moto_taxi_id'] ?? null;
    $fragile = isset($_POST['fragile']) ? 1 : 0;
    $description = $_POST['description'] ?? '';
    
    // Validation des données - Seuls les champs vraiment nécessaires
    if (empty($type_livraison)) {
        throw new Exception('Le type de livraison est obligatoire');
    }
    
    if (empty($adresse_depart) || empty($adresse_arrivee)) {
        throw new Exception('Les adresses de départ et d\'arrivée sont obligatoires');
    }
    
    if (empty($moto_taxi_id)) {
        throw new Exception('La sélection d\'un moto-taxi est obligatoire');
    }
    
    // Valeurs par défaut pour les champs optionnels
    $latitude_depart = $latitude_depart ?: 0;
    $longitude_depart = $longitude_depart ?: 0;
    $latitude_arrivee = $latitude_arrivee ?: 0;
    $longitude_arrivee = $longitude_arrivee ?: 0;
    $description = $description ?: 'Livraison standard';
    
    // Calculer la distance en km (si coordonnées disponibles)
    $distance = 0;
    if ($latitude_depart && $longitude_depart && $latitude_arrivee && $longitude_arrivee) {
        $distance = calculateDistance(
            $latitude_depart, $longitude_depart,
            $latitude_arrivee, $longitude_arrivee
        );
    }
    
    // Calculer le prix (prix minimum si pas de distance)
    $prix = calculatePrice($distance, $fragile, $type_livraison);
    
    // Vérifier le solde de l'utilisateur
    $stmt = $pdo->prepare("SELECT solde FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user['solde'] < $prix) {
        throw new Exception('Solde insuffisant. Solde actuel: ' . number_format($user['solde'], 0, ',', ' ') . ' FCFA. Prix: ' . number_format($prix, 0, ',', ' ') . ' FCFA');
    }
    
    // Démarrer une transaction
    $pdo->beginTransaction();
    
    // Insérer la livraison
    $stmt = $pdo->prepare("
        INSERT INTO livraisons (
            user_id, moto_taxi_id, type_livraison, adresse_depart, adresse_arrivee,
            latitude_depart, longitude_depart, latitude_arrivee, longitude_arrivee,
            distance_km, montant, fragile, description_colis, statut, date_creation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
    ");
    
    $stmt->execute([
        $user_id, $moto_taxi_id, $type_livraison, $adresse_depart, $adresse_arrivee,
        $latitude_depart, $longitude_depart, $latitude_arrivee, $longitude_arrivee,
        $distance, $prix, $fragile, $description
    ]);
    
    $livraison_id = $pdo->lastInsertId();
    
    // Gérer l'upload des documents pour les demandes de retrait
    $documents_uploaded = [];
    if ($type_livraison === 'retrait' && isset($_FILES['documents'])) {
        $upload_dir = 'uploads/documents/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Traiter chaque fichier uploadé
        foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['documents']['name'][$key];
                $file_size = $_FILES['documents']['size'][$key];
                $file_type = $_FILES['documents']['type'][$key];
                
                // Vérifier le type de fichier
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception('Type de fichier non autorisé: ' . $file_name);
                }
                
                // Vérifier la taille
                if ($file_size > $max_size) {
                    throw new Exception('Fichier trop volumineux: ' . $file_name);
                }
                
                // Générer un nom unique
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $unique_name;
                
                // Déplacer le fichier
                if (move_uploaded_file($tmp_name, $file_path)) {
                    // Insérer dans la base de données
                    $stmt = $pdo->prepare("
                        INSERT INTO documents_livraison (
                            livraison_id, nom_fichier, chemin_fichier, taille_fichier, type_mime, date_upload
                        ) VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $livraison_id, $unique_name, $file_path, $file_size, $file_type
                    ]);
                    
                    $documents_uploaded[] = $file_name;
                } else {
                    throw new Exception('Erreur lors de l\'upload du fichier: ' . $file_name);
                }
            }
        }
    }
    
    // Débiter le solde de l'utilisateur
    $nouveau_solde = $user['solde'] - $prix;
    $stmt = $pdo->prepare("UPDATE users SET solde = ? WHERE id = ?");
    $stmt->execute([$nouveau_solde, $user_id]);
    
    // Enregistrer la transaction
    $stmt = $pdo->prepare("
        INSERT INTO transactions_solde (
            user_id, type_transaction, montant, solde_avant, solde_apres,
            description, date_transaction
        ) VALUES (?, 'debit', ?, ?, ?, ?, NOW())
    ");
    
    $description_transaction = "Paiement livraison #$livraison_id - $type_livraison";
    $stmt->execute([$user_id, $prix, $user['solde'], $nouveau_solde, $description_transaction]);
    
    // Valider la transaction
    $pdo->commit();
    
    // Créer une notification de livraison créée
    notifySysteme($user_id, "Nouvelle livraison", "Votre livraison #$livraison_id a été créée avec succès", [
        'livraison_id' => $livraison_id,
        'prix' => $prix,
        'type_livraison' => $type_livraison
    ]);
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'message' => 'Livraison créée avec succès',
        'livraison_id' => $livraison_id,
        'prix' => $prix,
        'distance' => $distance,
        'nouveau_solde' => $nouveau_solde,
        'documents_uploaded' => $documents_uploaded
    ];
    
    // Si c'est une demande de retrait avec documents, ajouter un message spécial
    if ($type_livraison === 'retrait' && !empty($documents_uploaded)) {
        $response['message'] .= '. ' . count($documents_uploaded) . ' document(s) uploadé(s) avec succès.';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Calcule la distance entre deux points en km
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Rayon de la Terre en km
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat/2) * sin($delta_lat/2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($delta_lon/2) * sin($delta_lon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

/**
 * Calcule le prix de la livraison
 */
function calculatePrice($distance, $fragile, $type_livraison) {
    // Prix de base
    $prix_base = 500; // 500 FCFA
    
    // Prix par km
    $prix_par_km = 200; // 200 FCFA par km
    
    // Calcul du prix de base
    $prix = $prix_base + ($distance * $prix_par_km);
    
    // Majoration pour colis fragile
    if ($fragile) {
        $prix += 300; // +300 FCFA
    }
    
    // Majoration pour retrait (plus complexe)
    if ($type_livraison === 'retrait') {
        $prix += 200; // +200 FCFA
    }
    
    // Majoration pour urgence (si demandé)
    // Ici on pourrait ajouter une logique pour l'urgence
    
    // Majoration pour livraison de nuit (entre 22h et 6h)
    $heure_actuelle = (int)date('H');
    if ($heure_actuelle >= 22 || $heure_actuelle <= 6) {
        $prix += 500; // +500 FCFA pour livraison de nuit
    }
    
    return round($prix);
}
?> 