-- =====================================
-- MIGRATION : Messagerie privée
-- =====================================
-- Ajoute la table des messages privés entre deux utilisateurs.
-- Une "conversation" n'a pas de table dédiée : c'est simplement
-- l'ensemble des messages échangés entre deux utilisateurs (sender/recipient).

CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,            -- expéditeur
    recipient_id INT NOT NULL,         -- destinataire
    content TEXT NOT NULL,             -- contenu du message
    is_read BOOLEAN NOT NULL DEFAULT 0,-- lu par le destinataire ?
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    -- Index pour retrouver vite une conversation entre 2 personnes
    INDEX idx_pair (sender_id, recipient_id),
    -- Index pour compter vite les messages non lus d'un destinataire
    INDEX idx_recipient_read (recipient_id, is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
