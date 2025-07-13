-- Base de données pour l'application MotoExpress - Version complète

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telephone VARCHAR(20) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    photo_profil VARCHAR(255),
    solde DECIMAL(10,2) DEFAULT 0,
    points_fidelite INT DEFAULT 0,
    langue VARCHAR(20) DEFAULT 'fr',
    notifications BOOLEAN DEFAULT 1,
    mode_sombre BOOLEAN DEFAULT 0,
    deux_facteurs BOOLEAN DEFAULT 0,
    adresse_favorite TEXT,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME,
    statut ENUM('actif', 'suspendu', 'supprime') DEFAULT 'actif'
);

-- Table des moto-taxis
CREATE TABLE moto_taxis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    telephone VARCHAR(20) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    photo_profil VARCHAR(255),
    numero_permis VARCHAR(50) NOT NULL,
    numero_immatriculation VARCHAR(50) NOT NULL,
    marque_moto VARCHAR(100),
    modele_moto VARCHAR(100),
    annee_moto INT,
    couleur_moto VARCHAR(50),
    solde DECIMAL(10,2) DEFAULT 0,
    points_fidelite INT DEFAULT 0,
    latitude DOUBLE,
    longitude DOUBLE,
    derniere_position DATETIME,
    statut ENUM('en_attente', 'actif', 'suspendu', 'supprime') DEFAULT 'en_attente',
    note_moyenne DECIMAL(3,2) DEFAULT 5.0,
    nombre_livraisons INT DEFAULT 0,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME
);

-- Table des livraisons
CREATE TABLE livraisons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    moto_taxi_id INT,
    type_livraison ENUM('livraison', 'retrait') DEFAULT 'livraison',
    adresse_depart VARCHAR(255) NOT NULL,
    adresse_arrivee VARCHAR(255) NOT NULL,
    latitude_depart DOUBLE,
    longitude_depart DOUBLE,
    latitude_arrivee DOUBLE,
    longitude_arrivee DOUBLE,
    distance_km DOUBLE,
    montant DECIMAL(10,2) NOT NULL,
    urgence BOOLEAN DEFAULT 0,
    nuit BOOLEAN DEFAULT 0,
    week_end BOOLEAN DEFAULT 0,
    pluie BOOLEAN DEFAULT 0,
    heures_pointe BOOLEAN DEFAULT 0,
    fragile BOOLEAN DEFAULT 0,
    description_colis TEXT,
    statut ENUM('en_attente', 'en_cours', 'terminee', 'annulee') DEFAULT 'en_attente',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_acceptation DATETIME,
    date_debut_livraison DATETIME,
    date_fin_livraison DATETIME,
    temps_estime INT,
    temps_reel INT,
    photo_preuve VARCHAR(255),
    note INT CHECK (note BETWEEN 1 AND 5),
    commentaire_evaluation TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (moto_taxi_id) REFERENCES moto_taxis(id) ON DELETE SET NULL
);

-- Table des documents de livraison
CREATE TABLE documents_livraison (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livraison_id INT NOT NULL,
    type_document ENUM('facture', 'bon_livraison', 'preuve_retrait', 'autre') NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    taille_fichier INT,
    type_mime VARCHAR(100),
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE CASCADE
);

-- Table des documents moto-taxi
CREATE TABLE documents_moto_taxi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moto_taxi_id INT NOT NULL,
    type_document ENUM('permis_conduire', 'carte_grise', 'assurance', 'carte_identite', 'autre') NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    taille_fichier INT,
    type_mime VARCHAR(100),
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente', 'approuve', 'rejete') DEFAULT 'en_attente',
    FOREIGN KEY (moto_taxi_id) REFERENCES moto_taxis(id) ON DELETE CASCADE
);

-- Table des messages (chat utilisateur-mototaxi)
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livraison_id INT NOT NULL,
    expediteur_id INT NOT NULL,
    type_expediteur ENUM('user', 'moto_taxi') NOT NULL,
    type_message ENUM('texte', 'photo', 'audio', 'gps', 'statut', 'predefini', 'fichier') DEFAULT 'texte',
    contenu TEXT,
    fichier VARCHAR(255),
    coordonnees_gps VARCHAR(100),
    statut_livraison VARCHAR(50),
    message_predefini VARCHAR(100),
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    lu BOOLEAN DEFAULT 0,
    FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE CASCADE
);

-- Table des évaluations
CREATE TABLE evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livraison_id INT NOT NULL,
    evaluateur_id INT NOT NULL,
    type_evaluateur ENUM('user', 'moto_taxi') NOT NULL,
    ponctualite INT CHECK (ponctualite BETWEEN 1 AND 5),
    etat_colis INT CHECK (etat_colis BETWEEN 1 AND 5),
    politesse INT CHECK (politesse BETWEEN 1 AND 5),
    securite INT CHECK (securite BETWEEN 1 AND 5),
    note_generale INT CHECK (note_generale BETWEEN 1 AND 5),
    commentaire TEXT,
    date_evaluation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE CASCADE
);

-- Table des notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type_user ENUM('user', 'moto_taxi', 'admin') NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT,
    type_notification ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    lu BOOLEAN DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_lecture DATETIME
);

-- Table de l'historique des connexions
CREATE TABLE historique_connexions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type_user ENUM('user', 'moto_taxi', 'admin') NOT NULL,
    date_connexion DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    action ENUM('connexion', 'deconnexion') DEFAULT 'connexion'
);

-- Table des transactions de solde (utilisateurs)
CREATE TABLE transactions_solde (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('recharge', 'paiement', 'remboursement', 'commission') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    solde_avant DECIMAL(10,2) NOT NULL,
    solde_apres DECIMAL(10,2) NOT NULL,
    moyen_paiement VARCHAR(50),
    reference_transaction VARCHAR(100),
    description TEXT,
    statut ENUM('en_attente', 'terminee', 'annulee', 'echouee') DEFAULT 'en_attente',
    date_transaction DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des transactions de solde (moto-taxis)
CREATE TABLE transactions_moto_taxi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moto_taxi_id INT NOT NULL,
    type ENUM('gain', 'retrait', 'commission', 'bonus') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    solde_avant DECIMAL(10,2) NOT NULL,
    solde_apres DECIMAL(10,2) NOT NULL,
    livraison_id INT,
    description TEXT,
    statut ENUM('en_attente', 'terminee', 'annulee') DEFAULT 'terminee',
    date_transaction DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moto_taxi_id) REFERENCES moto_taxis(id) ON DELETE CASCADE,
    FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE SET NULL
);

-- Table des administrateurs
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    email VARCHAR(150),
    telephone VARCHAR(20),
    role ENUM('super_admin', 'admin', 'moderateur') DEFAULT 'admin',
    permissions JSON,
    derniere_connexion DATETIME,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif', 'inactif') DEFAULT 'actif'
);

-- Table des paramètres de tarification
CREATE TABLE parametres_tarification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_parametre VARCHAR(100) NOT NULL UNIQUE,
    valeur DECIMAL(10,2) NOT NULL,
    description TEXT,
    date_maj DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des zones de couverture
CREATE TABLE zones_couverture (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_zone VARCHAR(100) NOT NULL,
    latitude_centre DOUBLE NOT NULL,
    longitude_centre DOUBLE NOT NULL,
    rayon_km DOUBLE NOT NULL,
    actif BOOLEAN DEFAULT 1,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des horaires de service
CREATE TABLE horaires_service (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jour_semaine ENUM('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    actif BOOLEAN DEFAULT 1,
    majoration_nuit DECIMAL(5,2) DEFAULT 0.30,
    majoration_weekend DECIMAL(5,2) DEFAULT 0.20
);

-- Table des messages prédéfinis
CREATE TABLE messages_predefinis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie ENUM('user', 'moto_taxi') NOT NULL,
    message VARCHAR(255) NOT NULL,
    actif BOOLEAN DEFAULT 1,
    ordre_affichage INT DEFAULT 0
);

-- Table des statistiques
CREATE TABLE statistiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_statistique DATE NOT NULL,
    type_statistique VARCHAR(50) NOT NULL,
    valeur INT NOT NULL,
    details JSON,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_stat (date_statistique, type_statistique)
);

-- Insertion des paramètres de tarification par défaut
INSERT INTO parametres_tarification (nom_parametre, valeur, description) VALUES
('tarif_base', 500.00, 'Tarif de base en FCFA'),
('cout_par_km', 200.00, 'Coût par kilomètre en FCFA'),
('majoration_urgence', 0.50, 'Majoration pour livraison urgente (50%)'),
('majoration_nuit', 0.30, 'Majoration pour livraison de nuit (30%)'),
('majoration_weekend', 0.20, 'Majoration pour livraison le weekend (20%)'),
('majoration_pluie', 0.20, 'Majoration par temps de pluie (20%)'),
('majoration_heures_pointe', 0.40, 'Majoration aux heures de pointe (40%)'),
('commission_plateforme', 0.15, 'Commission de la plateforme (15%)');

-- Insertion des horaires de service par défaut
INSERT INTO horaires_service (jour_semaine, heure_debut, heure_fin) VALUES
('lundi', '06:00:00', '22:00:00'),
('mardi', '06:00:00', '22:00:00'),
('mercredi', '06:00:00', '22:00:00'),
('jeudi', '06:00:00', '22:00:00'),
('vendredi', '06:00:00', '22:00:00'),
('samedi', '07:00:00', '21:00:00'),
('dimanche', '08:00:00', '20:00:00');

-- Insertion des messages prédéfinis
INSERT INTO messages_predefinis (categorie, message, ordre_affichage) VALUES
('user', 'Je suis arrivé au point de départ', 1),
('user', 'Où êtes-vous exactement ?', 2),
('user', 'J\'ai un problème, je vais être en retard', 3),
('user', 'Merci pour la livraison !', 4),
('moto_taxi', 'Je suis en route vers vous', 1),
('moto_taxi', 'Je suis arrivé à destination', 2),
('moto_taxi', 'Livraison effectuée avec succès', 3),
('moto_taxi', 'Avez-vous des questions ?', 4);

-- Insertion des zones de couverture par défaut (Douala)
INSERT INTO zones_couverture (nom_zone, latitude_centre, longitude_centre, rayon_km) VALUES
('Douala Centre', 4.0511, 9.7679, 10.0),
('Douala Akwa', 4.0511, 9.7679, 5.0),
('Douala Deido', 4.0511, 9.7679, 5.0),
('Douala Bali', 4.0511, 9.7679, 5.0);

-- Création des index pour optimiser les performances
CREATE INDEX idx_livraisons_user_id ON livraisons(user_id);
CREATE INDEX idx_livraisons_moto_taxi_id ON livraisons(moto_taxi_id);
CREATE INDEX idx_livraisons_statut ON livraisons(statut);
CREATE INDEX idx_livraisons_date_creation ON livraisons(date_creation);
CREATE INDEX idx_messages_livraison_id ON messages(livraison_id);
CREATE INDEX idx_messages_expediteur ON messages(expediteur_id, type_expediteur);
CREATE INDEX idx_notifications_user_id ON notifications(user_id, type_user);
CREATE INDEX idx_notifications_lu ON notifications(lu);
CREATE INDEX idx_moto_taxis_statut ON moto_taxis(statut);
CREATE INDEX idx_moto_taxis_position ON moto_taxis(latitude, longitude);
CREATE INDEX idx_transactions_user_id ON transactions_solde(user_id);
CREATE INDEX idx_transactions_moto_taxi_id ON transactions_moto_taxi(moto_taxi_id);
CREATE INDEX idx_historique_connexions_user_id ON historique_connexions(user_id, type_user);

-- Insertion d'un administrateur par défaut (mot de passe: admin123)
INSERT INTO admins (nom, prenom, login, mot_de_passe, email, role) VALUES 
('Admin', 'Principal', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@motoexpress.com', 'super_admin'); 