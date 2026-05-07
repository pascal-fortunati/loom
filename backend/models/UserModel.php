<?php

// MODÈLE - UTILISATEURS
class UserModel
{
    private $db;

    /**
     * Constructeur - Initialise la connexion à la BDD
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère un utilisateur par ID
     * 
     * @param int $userId L'ID utilisateur
     * @return array|null Les données utilisateur ou null
     */
    public function findById($userId)
    {
        $sql = 'SELECT id, username, email, avatar, bio, profile_visibility, created_at FROM users WHERE id = ?';
        return $this->db->fetchOne($sql, [$userId]);
    }

    /**
     * Récupère un utilisateur par email
     * Utilisé pour la connexion
     * 
     * @param string $email L'email de l'utilisateur
     * @return array|null Les données utilisateur avec hash ou null
     */
    public function findByEmail($email)
    {
        $sql = 'SELECT id, username, email, password_hash, avatar, bio, profile_visibility, created_at FROM users WHERE email = ?';
        return $this->db->fetchOne($sql, [$email]);
    }

    /**
     * Récupère un utilisateur par username
     * Utilisé pour vérifier l'unicité
     * 
     * @param string $username Le username
     * @return array|null Les données utilisateur ou null
     */
    public function findByUsername($username)
    {
        $sql = 'SELECT id, username, email, avatar, profile_visibility FROM users WHERE username = ?';
        return $this->db->fetchOne($sql, [$username]);
    }

    /**
     * Crée un nouvel utilisateur
     * 
     * @param string $username Le nom d'utilisateur
     * @param string $email L'adresse email
     * @param string $passwordHash Le hash du mot de passe
     * @return int|false L'ID du nouvel utilisateur ou false
     */
    public function create($username, $email, $passwordHash)
    {
        $sql = 'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)';
        
        try {
            $this->db->execute($sql, [$username, $email, $passwordHash]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[UserModel] Erreur création utilisateur : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour le profil utilisateur
     * 
     * @param int $userId L'ID utilisateur
     * @param array $data Les données à mettre à jour ['bio' => '...', 'avatar' => '...']
     * @return bool true si succès
     */
    public function update($userId, $data)
    {
        $updates = [];
        $params = [];

        // Construit la requête UPDATE dynamiquement
        foreach ($data as $key => $value) {
            if (in_array($key, ['bio', 'avatar', 'username', 'profile_visibility'])) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $userId; // Pour la clause WHERE

        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (PDOException $e) {
            error_log('[UserModel] Erreur mise à jour utilisateur : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un email existe déjà
     * 
     * @param string $email L'email à vérifier
     * @return bool true si existe
     */
    public function emailExists($email)
    {
        return $this->findByEmail($email) !== null;
    }

    /**
     * Vérifie si un username existe déjà
     * 
     * @param string $username Le username à vérifier
     * @return bool true si existe
     */
    public function usernameExists($username)
    {
        return $this->findByUsername($username) !== null;
    }

    /**
     * Retourne les pages de passions d'un utilisateur
     * 
     * @param int $userId L'ID utilisateur
     * @return array Liste des pages de passions
     */
    public function getPassionPages($userId)
    {
        $sql = 'SELECT id, name, description, cover_image, is_public, created_at 
                FROM passion_pages 
                WHERE user_id = ? 
                ORDER BY created_at DESC';
        return $this->db->fetchAll($sql, [$userId]);
    }

    /**
     * Récupère un utilisateur par ID avec le hash du mot de passe.
     * Utilisé uniquement pour les opérations de sécurité du compte.
     *
     * @param int $userId L'ID utilisateur
     * @return array|null Les données utilisateur avec password_hash ou null
     */
    public function findByIdWithPassword($userId)
    {
        $sql = 'SELECT id, username, email, password_hash, avatar, bio, profile_visibility, created_at FROM users WHERE id = ?';
        return $this->db->fetchOne($sql, [$userId]);
    }

    /**
     * Vérifie si un utilisateur suit au moins une passion d'un autre utilisateur.
     * Cette méthode permet de déterminer le statut "follower" pour la confidentialité.
     *
     * @param int $viewerUserId L'ID de l'utilisateur qui consulte
     * @param int $targetUserId L'ID du propriétaire du profil
     * @return bool true si le viewer suit au moins une passion du target
     */
    public function isFollowingUserByPassion($viewerUserId, $targetUserId)
    {
        $sql = 'SELECT s.id
                FROM subscriptions s
                INNER JOIN passion_pages pp ON pp.id = s.passion_page_id
                WHERE s.follower_id = ? AND pp.user_id = ?
                LIMIT 1';
        return $this->db->fetchOne($sql, [$viewerUserId, $targetUserId]) !== null;
    }

    /**
     * Met à jour le mot de passe d'un utilisateur.
     *
     * @param int $userId L'ID utilisateur
     * @param string $passwordHash Le nouveau hash du mot de passe
     * @return bool true si succès
     */
    public function updatePassword($userId, $passwordHash)
    {
        $sql = 'UPDATE users SET password_hash = ? WHERE id = ?';
        try {
            $this->db->execute($sql, [$passwordHash, $userId]);
            return true;
        } catch (PDOException $e) {
            error_log('[UserModel] Erreur mise à jour mot de passe : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime définitivement un utilisateur.
     * Les données liées sont supprimées automatiquement via ON DELETE CASCADE.
     *
     * @param int $userId L'ID utilisateur
     * @return bool true si succès
     */
    public function deleteById($userId)
    {
        $sql = 'DELETE FROM users WHERE id = ?';
        try {
            $this->db->execute($sql, [$userId]);
            return true;
        } catch (PDOException $e) {
            error_log('[UserModel] Erreur suppression utilisateur : ' . $e->getMessage());
            return false;
        }
    }
}
