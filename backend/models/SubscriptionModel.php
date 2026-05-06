<?php

// MODÈLE - ABONNEMENTS
class SubscriptionModel
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
     * Récupère un abonnement par ID
     * 
     * @param int $subscriptionId L'ID de l'abonnement
     * @return array|null Les données de l'abonnement ou null
     */
    public function findById($subscriptionId)
    {
        $sql = 'SELECT id, follower_id, passion_page_id, created_at 
                FROM subscriptions 
                WHERE id = ?';
        return $this->db->fetchOne($sql, [$subscriptionId]);
    }

    /**
     * Récupère les abonnements d'un utilisateur
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $limit Nombre de résultats
     * @param int $offset Décalage
     * @return array Liste des pages suivies
     */
    public function getUserSubscriptions($userId, $limit = 50, $offset = 0)
    {
        $sql = 'SELECT s.id AS subscription_id, s.passion_page_id,
                        pp.id, pp.name, pp.description, pp.cover_image, pp.is_public, pp.created_at,
                        u.username, u.avatar
                FROM subscriptions s
                JOIN passion_pages pp ON s.passion_page_id = pp.id
                JOIN users u ON pp.user_id = u.id
                WHERE s.follower_id = ?
                ORDER BY s.created_at DESC
                LIMIT ? OFFSET ?';
        return $this->db->fetchAll($sql, [$userId, (int)$limit, (int)$offset]);
    }

    /**
     * Vérifie si un utilisateur est abonné à une page
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $passionPageId L'ID de la page
     * @return bool true si abonné
     */
    public function isSubscribed($userId, $passionPageId)
    {
        $sql = 'SELECT id FROM subscriptions 
                WHERE follower_id = ? AND passion_page_id = ?';
        return $this->db->fetchOne($sql, [$userId, $passionPageId]) !== null;
    }

    /**
     * Crée un abonnement
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $passionPageId L'ID de la page
     * @return int|false L'ID créé ou false
     */
    public function subscribe($userId, $passionPageId)
    {
        // Vérifie que la page existe
        $sql = 'SELECT id FROM passion_pages WHERE id = ?';
        if ($this->db->fetchOne($sql, [$passionPageId]) === null) {
            return false;
        }

        // Vérifie que l'utilisateur n'est pas déjà abonné
        if ($this->isSubscribed($userId, $passionPageId)) {
            return false;
        }

        $sql = 'INSERT INTO subscriptions (follower_id, passion_page_id) 
                VALUES (?, ?)';
        
        try {
            $this->db->execute($sql, [$userId, $passionPageId]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[SubscriptionModel] Erreur création abonnement : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un abonnement
     * 
     * @param int $userId L'ID de l'utilisateur
     * @param int $passionPageId L'ID de la page
     * @return bool true si succès
     */
    public function unsubscribe($userId, $passionPageId)
    {
        $sql = 'DELETE FROM subscriptions 
                WHERE follower_id = ? AND passion_page_id = ?';
        
        try {
            $this->db->execute($sql, [$userId, $passionPageId]);
            return true;
        } catch (PDOException $e) {
            error_log('[SubscriptionModel] Erreur suppression abonnement : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les abonnements d'une page (qui la suit)
     * 
     * @param int $passionPageId L'ID de la page
     * @return int Nombre d'abonnés
     */
    public function getSubscriberCount($passionPageId)
    {
        $sql = 'SELECT COUNT(*) as count FROM subscriptions WHERE passion_page_id = ?';
        $result = $this->db->fetchOne($sql, [$passionPageId]);
        return $result['count'] ?? 0;
    }
}
