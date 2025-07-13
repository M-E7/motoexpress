-- Création de la table messages pour la messagerie
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livraison_id INT NOT NULL,
    expediteur ENUM('user', 'mototaxi') NOT NULL,
    contenu TEXT NOT NULL,
    type ENUM('texte', 'photo', 'audio', 'gps', 'statut', 'predefini') DEFAULT 'texte',
    fichier VARCHAR(255) NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    lu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (livraison_id) REFERENCES livraisons(id) ON DELETE CASCADE
);

-- Index pour améliorer les performances
CREATE INDEX idx_messages_livraison ON messages(livraison_id);
CREATE INDEX idx_messages_date ON messages(date_envoi);
CREATE INDEX idx_messages_lu ON messages(lu); 