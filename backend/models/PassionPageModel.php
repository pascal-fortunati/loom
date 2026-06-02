<?php

// MODÈLE - PASSION PAGES
class PassionPageModel
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
     * Récupère une page de passion par ID
     * 
     * @param int $pageId L'ID de la page
     * @return array|null Les données de la page ou null
     */
    public function findById($pageId)
    {
        // On joint users pour exposer le pseudo + avatar du créateur
        // (utile pour afficher "Créée par @pseudo" sur la page de détail).
        $sql = 'SELECT p.id, p.user_id, p.name, p.description, p.cover_image, p.is_public, p.created_at,
                        u.username, u.avatar
                FROM passion_pages p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = ?';
        return $this->db->fetchOne($sql, [$pageId]);
    }

    /**
     * Récupère toutes les pages de passions publiques
     * Avec pagination pour les performances
     * 
     * @param int $limit Nombre de résultats (défaut 20)
     * @param int $offset Décalage pour la pagination
     * @return array Liste des pages publiques
     */
    public function getAllPublic($limit = 20, $offset = 0)
    {
        $sql = 'SELECT p.id, p.user_id, p.name, p.description, p.cover_image, p.created_at,
                        u.username, u.avatar
                FROM passion_pages p
                JOIN users u ON p.user_id = u.id
                WHERE p.is_public = 1
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?';
        
        return $this->db->fetchAll($sql, [(int)$limit, (int)$offset]);
    }

    /**
     * Récupère les pages de passions d'un utilisateur
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param bool $includePrivate Inclure les pages privées
     * @return array Liste des pages
     */
    public function getByUserId($userId, $includePrivate = false)
    {
        $sql = 'SELECT id, user_id, name, description, cover_image, is_public, created_at 
                FROM passion_pages 
                WHERE user_id = ?';
        
        if (!$includePrivate) {
            $sql .= ' AND is_public = 1';
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        return $this->db->fetchAll($sql, [$userId]);
    }

    /**
     * Crée une nouvelle page de passion
     * 
     * @param int $userId L'ID du créateur
     * @param string $name Le nom de la passion
     * @param string $description La description
     * @param bool $isPublic Visible publiquement
     * @param string $coverImage Fichier image de couverture (optionnel)
     * @return int|false L'ID créé ou false
     */
    public function create($userId, $name, $description, $isPublic = true, $coverImage = null)
    {
        $sql = 'INSERT INTO passion_pages (user_id, name, description, cover_image, is_public) 
                VALUES (?, ?, ?, ?, ?)';
        
        try {
            $this->db->execute($sql, [
                $userId,
                $name,
                $description,
                $coverImage,
                $isPublic ? 1 : 0
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[PassionPageModel] Erreur création passion : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour une page de passion
     * 
     * @param int $pageId L'ID de la page
     * @param array $data Données à mettre à jour
     * @return bool true si succès
     */
    public function update($pageId, $data)
    {
        $updates = [];
        $params = [];

        // Champs autorisés à mettre à jour
        $allowedFields = ['name', 'description', 'cover_image', 'is_public'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $pageId;
        $sql = 'UPDATE passion_pages SET ' . implode(', ', $updates) . ' WHERE id = ?';

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (PDOException $e) {
            error_log('[PassionPageModel] Erreur mise à jour : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime une page de passion
     * Supprime aussi les abonnements, posts, likes et commentaires associés
     * 
     * @param int $pageId L'ID de la page
     * @return bool true si succès
     */
    public function delete($pageId)
    {
        $sql = 'DELETE FROM passion_pages WHERE id = ?';
        
        try {
            $this->db->execute($sql, [$pageId]);
            return true;
        } catch (PDOException $e) {
            error_log('[PassionPageModel] Erreur suppression : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Compte le nombre d'abonnés à une page de passion
     * 
     * @param int $pageId L'ID de la page
     * @return int Nombre d'abonnés
     */
    public function getSubscriberCount($pageId)
    {
        $sql = 'SELECT COUNT(*) as count FROM subscriptions WHERE passion_page_id = ?';
        $result = $this->db->fetchOne($sql, [$pageId]);
        return $result['count'] ?? 0;
    }

    /**
     * Vérifie si une page appartient à un utilisateur
     * 
     * @param int $pageId L'ID de la page
     * @param int $userId L'ID de l'utilisateur
     * @return bool true si propriétaire
     */
    public function isOwner($pageId, $userId)
    {
        $sql = 'SELECT user_id FROM passion_pages WHERE id = ?';
        $page = $this->db->fetchOne($sql, [$pageId]);
        
        return $page !== null && (int)$page['user_id'] === (int)$userId;
    }

    /**
     * Valide le nom d'une passion
     * 
     * @param string $name Le nom
     * @return bool true si valide
     */
    public static function isValidName($name)
    {
        return !empty($name) && strlen($name) >= 2 && strlen($name) <= 100;
    }

    /**
     * Valide la description
     * 
     * @param string $description La description
     * @return bool true si valide
     */
    public static function isValidDescription($description)
    {
        return strlen($description) <= 1000;
    }
}
