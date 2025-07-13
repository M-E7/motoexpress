<?php
require_once 'db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, nom, prenom, latitude as lat, longitude as lon, note_moyenne as note FROM moto_taxis WHERE statut = 'actif' AND latitude IS NOT NULL AND longitude IS NOT NULL");
$moto_taxis = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'moto_taxis' => $moto_taxis
]); 