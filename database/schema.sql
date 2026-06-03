-- =====================================
-- LOOM - Schéma de Base de Données
-- =====================================
-- Créé pour le projet DWWM (Développeur Web et Web Mobile)
-- Structure simple et pédagogique avec normalisaiton

-- =====================================
-- 1. TABLE UTILISATEURS
-- =====================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    profile_visibility ENUM('public', 'followers', 'private') NOT NULL DEFAULT 'public',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- 2. TABLE PAGES DE PASSIONS
-- =====================================
CREATE TABLE passion_pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- 3. TABLE ABONNEMENTS (Suivre une passion)
-- =====================================
CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    follower_id INT NOT NULL,
    passion_page_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subscription (follower_id, passion_page_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (passion_page_id) REFERENCES passion_pages(id) ON DELETE CASCADE,
    INDEX idx_follower_id (follower_id),
    INDEX idx_passion_page_id (passion_page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- 4. TABLE PUBLICATIONS
-- =====================================
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    passion_page_id INT NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (passion_page_id) REFERENCES passion_pages(id) ON DELETE CASCADE,
    INDEX idx_passion_page_id (passion_page_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- 5. TABLE LIKES
-- =====================================
CREATE TABLE likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- 6. TABLE COMMENTAIRES
-- =====================================
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- DONNÉES DE TEST
-- =====================================

-- Utilisateurs de test
-- Mot de passe en clair pour les 3 comptes : password123
-- (hash bcrypt généré avec password_hash(..., PASSWORD_BCRYPT))
INSERT INTO users (username, email, password_hash, avatar, bio) VALUES
('alice_gaming', 'alice@example.com', '$2y$10$QmBe7Cr0cW1ugL1IyN8O/eXcRV4OqZ0gguk4gDj8KVBz0b9jaYzym', 'avatar1.jpg', 'Passionnée de jeux vidéo et esports'),
('bob_cuisine', 'bob@example.com', '$2y$10$QmBe7Cr0cW1ugL1IyN8O/eXcRV4OqZ0gguk4gDj8KVBz0b9jaYzym', 'avatar2.jpg', 'Chef amateur qui adore cuisiner'),
('carol_sport', 'carol@example.com', '$2y$10$QmBe7Cr0cW1ugL1IyN8O/eXcRV4OqZ0gguk4gDj8KVBz0b9jaYzym', 'avatar3.jpg', 'Athlète et fan de fitness');

-- Pages de passions pour Alice
INSERT INTO passion_pages (user_id, name, description, is_public) VALUES
(1, 'Gaming', 'Discussions sur les jeux vidéo, esports et actualités gaming', TRUE),
(1, 'Anime', 'Partage de mes animes préférées et recommandations', TRUE);

-- Pages de passions pour Bob
INSERT INTO passion_pages (user_id, name, description, is_public) VALUES
(2, 'Cuisine', 'Recettes, astuces culinaires et découvertes gastronomiques', TRUE),
(2, 'Pâtisserie', 'Focus sur les desserts et pâtisseries', TRUE);

-- Pages de passions pour Carol
INSERT INTO passion_pages (user_id, name, description, is_public) VALUES
(3, 'Fitness', 'Entraînement, nutrition et conseils sportifs', TRUE),
(3, 'Running', 'Trail, marathons et courses à pied', TRUE);

-- Abonnements (Alice suit Gaming et Anime de Bob, etc.)
INSERT INTO subscriptions (follower_id, passion_page_id) VALUES
(1, 4), -- Alice suit Pâtisserie de Bob
(2, 1), -- Bob suit Gaming d'Alice
(3, 1), -- Carol suit Gaming d'Alice
(3, 4), -- Carol suit Pâtisserie de Bob
(1, 6); -- Alice suit Running de Carol

-- Publications de test
INSERT INTO posts (passion_page_id, content) VALUES
(1, 'Elden Ring DLC - première impression après 20h de jeu ! Les boss sont incroyablement difficiles mais tellement bien designés.'),
(1, 'Qui regarde l\'EVO 2026 ? Hype pour les matchs de Street Fighter !'),
(2, 'Vient de finir Attack on Titan. J\'en suis sans voix... quelle fin incroyable !'),
(4, 'Recette de croissants faits maison - conseil : utilisez du beurre de qualité !'),
(6, '10 km ce matin en 50 minutes. Personnellement meilleur record ! 🏃'),
(5, 'Guide complet des étirements post-entraînement');

-- Likes de test
INSERT INTO likes (user_id, post_id) VALUES
(2, 1), -- Bob aime le post d'Alice sur Elden Ring
(3, 1), -- Carol aime le post d'Alice sur Elden Ring
(1, 4), -- Alice aime le post de Bob sur les croissants
(1, 6); -- Alice aime le post de Carol sur le running

-- Commentaires de test
INSERT INTO comments (user_id, post_id, content) VALUES
(2, 1, 'Je suis d\'accord ! Le DLC est difficile mais très bon. T\'as battu Maliketh ?'),
(3, 4, 'Les croissants maison c\'est le meilleur ! Je vais tester ta recette.'),
(1, 6, 'Bravo Carol ! C\'est un super temps. Tu prépares un marathon ?');
