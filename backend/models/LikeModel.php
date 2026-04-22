<?php

// MODÈLE - LIKES
class LikeModel
{
    private $db;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère un like par ID
     * 
     * @param int $likeId L'ID du like
     * @return array|null Les données du like ou null
     */
    public function findById($likeId)
    {
        $sql = 'SELECT id, user_id, post_id, created_at FROM likes WHERE id = ?';
        return $this->db->fetchOne($sql, [$likeId]);
    }

    /**
     * Vérifie si un utilisateur a liké un post
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $postId L'ID du post
     * @return int|null L'ID du like ou null
     */
    public function getLike($userId, $postId)
    {
        $sql = 'SELECT id FROM likes WHERE user_id = ? AND post_id = ?';
        $result = $this->db->fetchOne($sql, [$userId, $postId]);
        return $result['id'] ?? null;
    }

    /**
     * Ajoute un like
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $postId L'ID du post
     * @return int|false L'ID du like créé ou false
     */
    public function like($userId, $postId)
    {
        // Vérifie que le post existe
        $sql = 'SELECT id FROM posts WHERE id = ?';
        if ($this->db->fetchOne($sql, [$postId]) === null) {
            return false;
        }

        // Vérifie que le like n'existe pas déjà
        if ($this->getLike($userId, $postId) !== null) {
            return false;
        }

        $sql = 'INSERT INTO likes (user_id, post_id) VALUES (?, ?)';
        
        try {
            $this->db->execute($sql, [$userId, $postId]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[LikeModel] Erreur création like : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un like
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $postId L'ID du post
     * @return bool true si succès
     */
    public function unlike($userId, $postId)
    {
        $sql = 'DELETE FROM likes WHERE user_id = ? AND post_id = ?';
        
        try {
            $this->db->execute($sql, [$userId, $postId]);
            return true;
        } catch (PDOException $e) {
            error_log('[LikeModel] Erreur suppression like : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Compte les likes d'un post
     * 
     * @param int $postId L'ID du post
     * @return int Nombre de likes
     */
    public function getLikeCount($postId)
    {
        $sql = 'SELECT COUNT(*) as count FROM likes WHERE post_id = ?';
        $result = $this->db->fetchOne($sql, [$postId]);
        return $result['count'] ?? 0;
    }
}
