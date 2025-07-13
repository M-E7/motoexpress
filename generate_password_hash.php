<?php
// Script pour générer les hashes des mots de passe pour les administrateurs

echo "=== Génération des hashes de mots de passe ===\n\n";

// Mot de passe 1: SuperAdmin2024!
$password1 = 'SuperAdmin2024!';
$hash1 = password_hash($password1, PASSWORD_DEFAULT);

// Mot de passe 2: admin123
$password2 = 'admin123';
$hash2 = password_hash($password2, PASSWORD_DEFAULT);

echo "Mot de passe: '$password1'\n";
echo "Hash: $hash1\n\n";

echo "Mot de passe: '$password2'\n";
echo "Hash: $hash2\n\n";

echo "=== Requête SQL mise à jour ===\n\n";

echo "-- Requête SQL pour créer un super administrateur\n";
echo "-- Identifiants de connexion :\n";
echo "-- Login: superadmin\n";
echo "-- Mot de passe: SuperAdmin2024!\n\n";

echo "INSERT INTO admins (\n";
echo "    nom, \n";
echo "    prenom, \n";
echo "    login, \n";
echo "    mot_de_passe, \n";
echo "    email, \n";
echo "    telephone, \n";
echo "    role, \n";
echo "    permissions, \n";
echo "    statut, \n";
echo "    date_creation\n";
echo ") VALUES (\n";
echo "    'Super',\n";
echo "    'Administrateur',\n";
echo "    'superadmin',\n";
echo "    '$hash1', -- Mot de passe: SuperAdmin2024!\n";
echo "    'superadmin@motoexpress.com',\n";
echo "    '+237 6XX XXX XXX',\n";
echo "    'super_admin',\n";
echo "    '{\"all_permissions\": true, \"manage_users\": true, \"manage_drivers\": true, \"manage_admins\": true, \"view_reports\": true, \"manage_settings\": true, \"manage_transactions\": true}',\n";
echo "    'actif',\n";
echo "    NOW()\n";
echo ");\n\n";

echo "-- Alternative avec un mot de passe plus simple pour les tests\n";
echo "-- Mot de passe: admin123\n";
echo "INSERT INTO admins (\n";
echo "    nom, \n";
echo "    prenom, \n";
echo "    login, \n";
echo "    mot_de_passe, \n";
echo "    email, \n";
echo "    telephone, \n";
echo "    role, \n";
echo "    permissions, \n";
echo "    statut, \n";
echo "    date_creation\n";
echo ") VALUES (\n";
echo "    'Admin',\n";
echo "    'Test',\n";
echo "    'admin',\n";
echo "    '$hash2', -- Mot de passe: admin123\n";
echo "    'admin@motoexpress.com',\n";
echo "    '+237 6XX XXX XXX',\n";
echo "    'super_admin',\n";
echo "    '{\"all_permissions\": true, \"manage_users\": true, \"manage_drivers\": true, \"manage_admins\": true, \"view_reports\": true, \"manage_settings\": true, \"manage_transactions\": true}',\n";
echo "    'actif',\n";
echo "    NOW()\n";
echo ");\n\n";

echo "-- Vérification que l'admin a été créé\n";
echo "SELECT id, nom, prenom, login, email, role, statut, date_creation FROM admins WHERE login IN ('superadmin', 'admin');\n";
?> 