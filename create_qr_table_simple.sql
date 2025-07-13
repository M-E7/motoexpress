-- Script SQL simplifié pour créer la table QR codes
-- Sans contraintes de clés étrangères pour éviter les erreurs

-- Vérifier si la table existe déjà
DROP TABLE IF EXISTS `qr_codes_livraison`;

-- Créer la table QR codes
CREATE TABLE `qr_codes_livraison` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `livraison_id` int(11) NOT NULL,
  `moto_taxi_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `qr_content` text NOT NULL,
  `qr_filename` varchar(255) NOT NULL,
  `qr_path` varchar(500) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_utilisation` datetime NULL,
  `statut` enum('actif','utilise','expire') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `idx_livraison_id` (`livraison_id`),
  KEY `idx_moto_taxi_id` (`moto_taxi_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Afficher un message de confirmation
SELECT 'Table qr_codes_livraison créée avec succès!' as message; 