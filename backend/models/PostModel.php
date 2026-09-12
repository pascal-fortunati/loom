<?php

// MODÈLE - POSTS
class PostModel
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
     * Récupère un post par ID
     * 
     * @param int $postId L'ID du post
     * @return array|null Les données du post ou null
     */
    public function findById($postId)
    {
        // pp.is_public est remonté pour permettre aux contrôleurs d'appliquer la
        // confidentialité de la passion parente (ex: commentaires d'une page privée).
        $sql = 'SELECT p.id, p.passion_page_id, p.content, p.image_url, p.created_at,
                        pp.user_id, pp.is_public, u.username, u.avatar,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
                FROM posts p
                JOIN passion_pages pp ON p.passion_page_id = pp.id
                JOIN users u ON pp.user_id = u.id
                WHERE p.id = ?';
        return $this->db->fetchOne($sql, [$postId]);
    }

    /**
     * Récupère les posts d'une page de passion
     * 
     * @param int $passionPageId L'ID de la page
     * @param int $limit Nombre de résultats
     * @param int $offset Décalage
     * @return array Liste des posts
     */
    public function getByPassionPage($passionPageId, $limit = 20, $offset = 0)
    {
        $sql = 'SELECT p.id, p.passion_page_id, p.content, p.image_url, p.created_at,
                        pp.user_id, u.username, u.avatar,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
                FROM posts p
                JOIN passion_pages pp ON p.passion_page_id = pp.id
                JOIN users u ON pp.user_id = u.id
                WHERE p.passion_page_id = ?
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$passionPageId, (int)$limit, (int)$offset]);
    }

    /**
     * Crée un nouveau post
     * 
     * @param int $passionPageId L'ID de la page de passion
     * @param string $content Le contenu du post
     * @param string $imageUrl URL de l'image (optionnel)
     * @return int|false L'ID créé ou false
     */
    public function create($passionPageId, $content, $imageUrl = null)
    {
        $sql = 'INSERT INTO posts (passion_page_id, content, image_url) 
                VALUES (?, ?, ?)';
        
        try {
            $this->db->execute($sql, [$passionPageId, $content, $imageUrl]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[PostModel] Erreur création post : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour un post
     * 
     * @param int $postId L'ID du post
     * @param array $data Données à mettre à jour
     * @return bool true si succès
     */
    public function update($postId, $data)
    {
        $updates = [];
        $params = [];

        // Champs autorisés à mettre à jour
        $allowedFields = ['content', 'image_url'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $postId;
        $sql = 'UPDATE posts SET ' . implode(', ', $updates) . ' WHERE id = ?';

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (PDOException $e) {
            error_log('[PostModel] Erreur mise à jour : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un post (et ses likes/commentaires via CASCADE)
     * 
     * @param int $postId L'ID du post
     * @return bool true si succès
     */
    public function delete($postId)
    {
        $sql = 'DELETE FROM posts WHERE id = ?';
        
        try {
            $this->db->execute($sql, [$postId]);
            return true;
        } catch (PDOException $e) {
            error_log('[PostModel] Erreur suppression : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un post appartient à une page appartenant à un utilisateur
     * 
     * @param int $postId L'ID du post
     * @param int $userId L'ID de l'utilisateur
     * @return bool true si l'utilisateur peut éditer ce post
     */
    public function canEditPost($postId, $userId)
    {
        $sql = 'SELECT pp.user_id FROM posts p
                JOIN passion_pages pp ON p.passion_page_id = pp.id
                WHERE p.id = ?';
        $post = $this->db->fetchOne($sql, [$postId]);
        
        return $post !== null && (int)$post['user_id'] === (int)$userId;
    }

    /**
     * Valide le contenu d'un post
     * 
     * @param string $content Le contenu
     * @return bool true si valide
     */
    public static function isValidContent($content)
    {
        return !empty($content) && strlen($content) >= 1 && strlen($content) <= 2000;
    }
}