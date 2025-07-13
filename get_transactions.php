<?php
session_start();
require_once 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;
    $type = $_GET['type'] ?? ''; // debit, credit, ou vide pour tous
    
    // Construire la requête SQL
    $sql = "
        SELECT 
            id,
            type_transaction,
            montant,
            solde_avant,
            solde_apres,
            description,
            date_transaction,
            statut
        FROM transactions_solde 
        WHERE user_id = ?
    ";
    
    $params = [$user_id];
    
    // Ajouter le filtre par type si spécifié
    if (!empty($type)) {
        $sql .= " AND type_transaction = ?";
        $params[] = $type;
    }
    
    // Ajouter l'ordre et la pagination
    $sql .= " ORDER BY date_transaction DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les transactions
    foreach ($transactions as &$trans) {
        $trans['montant_formate'] = number_format($trans['montant'], 0, ',', ' ') . ' FCFA';
        $trans['solde_avant_formate'] = number_format($trans['solde_avant'], 0, ',', ' ') . ' FCFA';
        $trans['solde_apres_formate'] = number_format($trans['solde_apres'], 0, ',', ' ') . ' FCFA';
        $trans['date_formatee'] = date('d/m/Y H:i', strtotime($trans['date_transaction']));
        $trans['date_relative'] = formatRelativeTime($trans['date_transaction']);
        $trans['icone'] = getTransactionIcon($trans['type_transaction']);
        $trans['couleur'] = getTransactionColor($trans['type_transaction']);
        $trans['type_label'] = getTransactionTypeLabel($trans['type_transaction']);
    }
    
    // Compter le total pour la pagination
    $count_sql = "SELECT COUNT(*) as total FROM transactions_solde WHERE user_id = ?";
    $count_params = [$user_id];
    
    if (!empty($type)) {
        $count_sql .= " AND type_transaction = ?";
        $count_params[] = $type;
    }
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculer les statistiques des transactions
    $stats_sql = "
        SELECT 
            SUM(CASE WHEN type_transaction = 'credit' THEN montant ELSE 0 END) as total_credits,
            SUM(CASE WHEN type_transaction = 'debit' THEN montant ELSE 0 END) as total_debits,
            COUNT(*) as total_transactions
        FROM transactions_solde 
        WHERE user_id = ?
    ";
    
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'total' => (int)$total,
        'has_more' => ($offset + $limit) < $total,
        'stats' => [
            'total_credits' => (int)($stats['total_credits'] ?? 0),
            'total_debits' => (int)($stats['total_debits'] ?? 0),
            'total_transactions' => (int)($stats['total_transactions'] ?? 0)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Formate une date en temps relatif
 */
function formatRelativeTime($date_string) {
    $date = new DateTime($date_string);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->y > 0) {
        return "Il y a " . $diff->y . " an" . ($diff->y > 1 ? "s" : "");
    } elseif ($diff->m > 0) {
        return "Il y a " . $diff->m . " mois";
    } elseif ($diff->d > 0) {
        return "Il y a " . $diff->d . " jour" . ($diff->d > 1 ? "s" : "");
    } elseif ($diff->h > 0) {
        return "Il y a " . $diff->h . " heure" . ($diff->h > 1 ? "s" : "");
    } elseif ($diff->i > 0) {
        return "Il y a " . $diff->i . " minute" . ($diff->i > 1 ? "s" : "");
    } else {
        return "À l'instant";
    }
}

/**
 * Retourne l'icône appropriée selon le type de transaction
 */
function getTransactionIcon($type) {
    switch ($type) {
        case 'credit':
            return 'fas fa-plus-circle';
        case 'debit':
            return 'fas fa-minus-circle';
        case 'remboursement':
            return 'fas fa-undo';
        case 'bonus':
            return 'fas fa-gift';
        default:
            return 'fas fa-exchange-alt';
    }
}

/**
 * Retourne la couleur appropriée selon le type de transaction
 */
function getTransactionColor($type) {
    switch ($type) {
        case 'credit':
        case 'remboursement':
        case 'bonus':
            return 'success';
        case 'debit':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Retourne le label du type de transaction
 */
function getTransactionTypeLabel($type) {
    switch ($type) {
        case 'credit':
            return 'Crédit';
        case 'debit':
            return 'Débit';
        case 'remboursement':
            return 'Remboursement';
        case 'bonus':
            return 'Bonus';
        default:
            return ucfirst($type);
    }
}
?> 