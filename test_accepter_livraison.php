<?php
// Test simple pour vérifier accepter_livraison.php
session_start();

// Simuler une session moto-taxi
$_SESSION['moto_taxi_id'] = 1;

// Inclure le fichier à tester
ob_start();
include 'accepter_livraison.php';
$output = ob_get_clean();

echo "=== TEST accepter_livraison.php ===\n";
echo "Headers envoyés:\n";
foreach (headers_list() as $header) {
    echo "- $header\n";
}
echo "\nContenu de la réponse:\n";
echo $output;
echo "\n=== FIN DU TEST ===\n";
?> 