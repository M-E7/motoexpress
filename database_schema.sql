-- Table pour les documents de livraison (pour les demandes de retrait)
CREATE TABLE documents_livraison (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livraison_id INT NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    nom_original VARCHAR(255) NOT NULL,
    type_fichier VARCHAR(100) NOT NULL,
    taille_fichier INT NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    date_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE CASCADE
);

-- Table pour les notifications utilisateur
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type_notification VARCHAR(50) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data_extra JSON,
    lu BOOLEAN DEFAULT FALSE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_lu (user_id, lu),
    INDEX idx_date_creation (date_creation)
); 