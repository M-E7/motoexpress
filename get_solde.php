<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['solde'=>0]); exit; }
$stmt = $pdo->prepare('SELECT solde FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$solde = $stmt->fetchColumn();
echo json_encode(['solde'=>(float)$solde]); 