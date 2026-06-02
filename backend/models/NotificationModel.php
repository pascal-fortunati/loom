<?php

// MODÈLE - NOTIFICATIONS
class NotificationModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une notification pour un utilisateur.
     *
     * @param int $userId Destinataire
     * @param int $actorId Auteur de l'action
     * @param string $type Type d'événement (ex: 'comment')
     * @param int|null $postId Publication concernée
     * @param int|null $commentId Commentaire concerné
     * @return int|false L'ID créé ou false
     */
    public function create($userId, $actorId, $type, $postId = null, $commentId = null)
    {
        try {
            $this->db->execute(
                'INSERT INTO notifications (user_id, actor_id, type, post_id, comment_id)
                 VALUES (?, ?, ?, ?, ?)',
                [(int)$userId, (int)$actorId, $type, $postId, $commentId]
            );
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[NotificationModel] Erreur création : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Liste les notifications d'un utilisateur (avec infos sur l'auteur).
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getForUser($userId, $limit = 30)
    {
        $sql = 'SELECT n.id, n.type, n.post_id, n.comment_id, n.is_read, n.created_at,
                        actor.id AS actor_id, actor.username AS actor_username, actor.avatar AS actor_avatar
                FROM notifications n
                JOIN users actor ON actor.id = n.actor_id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT ?';
        return $this->db->fetchAll($sql, [(int)$userId, (int)$limit]);
    }

    /**
     * Nombre de notifications non lues (pour la pastille de la cloche).
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount($userId)
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0',
            [(int)$userId]
        );
        return (int)($row['count'] ?? 0);
    }

    /**
     * Marque toutes les notifications de l'utilisateur comme lues.
     *
     * @param int $userId
     */
    public function markAllRead($userId)
    {
        $this->db->execute(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
            [(int)$userId]
        );
    }

    /**
     * Supprime une notification appartenant à l'utilisateur.
     * Le filtre sur user_id garantit qu'on ne supprime que les siennes.
     *
     * @param int $id Identifiant de la notification
     * @param int $userId Propriétaire attendu
     */
    public function delete($id, $userId)
    {
        $this->db->execute(
            'DELETE FROM notifications WHERE id = ? AND user_id = ?',
            [(int)$id, (int)$userId]
        );
    }
}
