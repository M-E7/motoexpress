<?php
require_once 'db.php';
header('Content-Type: application/json');
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;
if ($lat === null || $lon === null) {
    echo json_encode([]); exit;
}
// Haversine SQL
$sql = "SELECT id, nom, note_moyenne, latitude, longitude, temps_reponse_moyen, photo_profil,
    (6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS(latitude - ?)/2),2) + COS(RADIANS(?)) * COS(RADIANS(latitude)) * POWER(SIN(RADIANS(longitude - ?)/2),2)))) AS distance
    FROM moto_taxis
    WHERE disponibilite = 'en_ligne'
    HAVING distance <= 5
    ORDER BY distance ASC, note_moyenne DESC, temps_reponse_moyen ASC
    LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$lat, $lat, $lon]);
$motos = $stmt->fetchAll();
foreach ($motos as &$m) {
    if (!$m['photo_profil']) {
        $m['photo_profil'] = 'https://ui-avatars.com/api/?name=' . urlencode($m['nom']) . '&background=16a34a&color=fff&size=48';
    }
}
echo json_encode($motos); 