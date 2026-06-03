-- =====================================
-- Migration - Visibilité de profil utilisateur
-- =====================================
-- Contexte:
-- Cette migration ajoute un champ de confidentialité centralisé
-- pour contrôler côté API qui peut consulter un profil utilisateur.

ALTER TABLE users
ADD COLUMN profile_visibility ENUM('public', 'followers', 'private')
NOT NULL DEFAULT 'public'
AFTER bio;

