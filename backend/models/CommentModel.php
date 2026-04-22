<?php

// MODÈLE - COMMENTAIRES
class CommentModel
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
     * Récupère un commentaire par ID
     * 
     * @param int $commentId L'ID du commentaire
     * @return array|null Les données du commentaire ou null
     */
    public function findById($commentId)
    {
        $sql = 'SELECT c.id, c.user_id, c.post_id, c.content, c.created_at,
                        u.username, u.avatar
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ?';
        return $this->db->fetchOne($sql, [$commentId]);
    }

    /**
     * Récupère les commentaires d'un post
     * 
     * @param int $postId L'ID du post
     * @param int $limit Nombre de résultats
     * @param int $offset Décalage
     * @return array Liste des commentaires
     */
    public function getByPost($postId, $limit = 50, $offset = 0)
    {
        $sql = 'SELECT c.id, c.user_id, c.post_id, c.content, c.created_at,
                        u.username, u.avatar
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ?
                ORDER BY c.created_at ASC
                LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$postId, (int)$limit, (int)$offset]);
    }

    /**
     * Crée un commentaire
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $postId L'ID du post
     * @param string $content Le contenu du commentaire
     * @return int|false L'ID du commentaire créé ou false
     */
    public function create($userId, $postId, $content)
    {
        // Vérifie que le post existe
        $sql = 'SELECT id FROM posts WHERE id = ?';
        if ($this->db->fetchOne($sql, [$postId]) === null) {
            return false;
        }

        $sql = 'INSERT INTO comments (user_id, post_id, content) VALUES (?, ?, ?)';
        
        try {
            $this->db->execute($sql, [$userId, $postId, $content]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[CommentModel] Erreur création commentaire : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour un commentaire
     * 
     * @param int $commentId L'ID du commentaire
     * @param string $content Le nouveau contenu
     * @return bool true si succès
     */
    public function update($commentId, $content)
    {
        $sql = 'UPDATE comments SET content = ? WHERE id = ?';
        
        try {
            $this->db->execute($sql, [$content, $commentId]);
            return true;
        } catch (PDOException $e) {
            error_log('[CommentModel] Erreur mise à jour : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un commentaire
     * 
     * @param int $commentId L'ID du commentaire
     * @return bool true si succès
     */
    public function delete($commentId)
    {
        $sql = 'DELETE FROM comments WHERE id = ?';
        
        try {
            $this->db->execute($sql, [$commentId]);
            return true;
        } catch (PDOException $e) {
            error_log('[CommentModel] Erreur suppression : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Compte les commentaires d'un post
     * 
     * @param int $postId L'ID du post
     * @return int Nombre de commentaires
     */
    public function getCommentCount($postId)
    {
        $sql = 'SELECT COUNT(*) as count FROM comments WHERE post_id = ?';
        $result = $this->db->fetchOne($sql, [$postId]);
        return $result['count'] ?? 0;
    }

    /**
     * Vérifie si un utilisateur peut éditer un commentaire
     * 
     * @param int $commentId L'ID du commentaire
     * @param int $userId L'ID de l'utilisateur
     * @return bool true si peut éditer
     */
    public function canEdit($commentId, $userId)
    {
        $sql = 'SELECT user_id FROM comments WHERE id = ?';
        $comment = $this->db->fetchOne($sql, [$commentId]);
        
        return $comment !== null && (int)$comment['user_id'] === (int)$userId;
    }

    /**
     * Valide le contenu d'un commentaire
     * 
     * @param string $content Le contenu
     * @return bool true si valide
     */
    public static function isValidContent($content)
    {
        return !empty($content) && strlen($content) >= 1 && strlen($content) <= 500;
    }
}
