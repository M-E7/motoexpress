-- Requête SQL pour créer un super administrateur
-- Identifiants de connexion :
-- Login: superadmin
-- Mot de passe: SuperAdmin2024!

INSERT INTO admins (
    nom, 
    prenom, 
    login, 
    mot_de_passe, 
    email, 
    telephone, 
    role, 
    permissions, 
    statut, 
    date_creation
) VALUES (
    'Super',
    'Administrateur',
    'superadmin',
    '$2y$10$YourHashHereForSuperAdmin2024!', -- Mot de passe: SuperAdmin2024!
    'superadmin@motoexpress.com',
    '+237 6XX XXX XXX',
    'super_admin',
    '{"all_permissions": true, "manage_users": true, "manage_drivers": true, "manage_admins": true, "view_reports": true, "manage_settings": true, "manage_transactions": true}',
    'actif',
    NOW()
);

-- Alternative avec un mot de passe plus simple pour les tests
-- Mot de passe: admin123
INSERT INTO admins (
    nom, 
    prenom, 
    login, 
    mot_de_passe, 
    email, 
    telephone, 
    role, 
    permissions, 
    statut, 
    date_creation
) VALUES (
    'Admin',
    'Test',
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Mot de passe: admin123
    'admin@motoexpress.com',
    '+237 6XX XXX XXX',
    'super_admin',
    '{"all_permissions": true, "manage_users": true, "manage_drivers": true, "manage_admins": true, "view_reports": true, "manage_settings": true, "manage_transactions": true}',
    'actif',
    NOW()
);

-- Vérification que l'admin a été créé
SELECT id, nom, prenom, login, email, role, statut, date_creation FROM admins WHERE login IN ('superadmin', 'admin'); 