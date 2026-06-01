-- =====================================
-- MIGRATION : Notifications
-- =====================================
-- Notifications reçues par un utilisateur (ex: un commentaire sur sa publication).
-- Conçu pour être extensible (type = 'comment' aujourd'hui, 'like'/'subscribe' demain).

CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,              -- destinataire (propriétaire de la publication)
    actor_id INT NOT NULL,             -- auteur de l'action (ex: le commentateur)
    type VARCHAR(30) NOT NULL,         -- type d'événement : 'comment', ...
    post_id INT DEFAULT NULL,          -- publication concernée
    comment_id INT DEFAULT NULL,       -- commentaire concerné (si applicable)
    is_read BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
