<?php

// MODÈLE - MESSAGES PRIVÉS
class MessageModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Indique si $senderId a le droit d'écrire à $recipientId.
     *
     * Règle métier (choisie pour Loom) : on peut écrire à quelqu'un si
     *  - son profil est public, OU
     *  - il existe un lien d'abonnement entre les deux (l'un suit une passion
     *    de l'autre, dans un sens ou dans l'autre).
     *
     * @return bool true si l'envoi est autorisé
     */
    public function canMessage($senderId, $recipientId)
    {
        // On ne s'écrit pas à soi-même
        if ((int)$senderId === (int)$recipientId) {
            return false;
        }

        // 1) Le destinataire a-t-il un profil public ?
        $recipient = $this->db->fetchOne(
            'SELECT profile_visibility FROM users WHERE id = ?',
            [(int)$recipientId]
        );
        if ($recipient === null) {
            return false; // destinataire inexistant
        }
        if ($recipient['profile_visibility'] === 'public') {
            return true;
        }

        // 2) Une conversation existe-t-elle déjà entre les deux ?
        //    Si oui, on autorise toujours : on doit pouvoir RÉPONDRE à
        //    quelqu'un qui nous a écrit, quelle que soit notre visibilité.
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM messages
             WHERE (sender_id = ? AND recipient_id = ?)
                OR (sender_id = ? AND recipient_id = ?)
             LIMIT 1',
            [
                (int)$senderId, (int)$recipientId,
                (int)$recipientId, (int)$senderId,
            ]
        );
        if ($existing !== null) {
            return true;
        }

        // 3) Sinon, existe-t-il un lien d'abonnement entre les deux ?
        return $this->hasSubscriptionLink($senderId, $recipientId);
    }

    /**
     * Indique s'il existe un lien d'abonnement entre deux utilisateurs :
     * l'un suit une passion de l'autre, dans un sens ou dans l'autre.
     *
     * @return bool
     */
    public function hasSubscriptionLink($userAId, $userBId)
    {
        $sql = 'SELECT 1
                FROM subscriptions s
                JOIN passion_pages pp ON pp.id = s.passion_page_id
                WHERE (s.follower_id = ? AND pp.user_id = ?)
                   OR (s.follower_id = ? AND pp.user_id = ?)
                LIMIT 1';
        $link = $this->db->fetchOne($sql, [
            (int)$userAId, (int)$userBId,
            (int)$userBId, (int)$userAId,
        ]);

        return $link !== null;
    }

    /**
     * Enregistre un message et retourne la ligne créée.
     *
     * @return array Le message inséré (id, expéditeur, contenu, date...)
     */
    public function send($senderId, $recipientId, $content)
    {
        $this->db->execute(
            'INSERT INTO messages (sender_id, recipient_id, content) VALUES (?, ?, ?)',
            [(int)$senderId, (int)$recipientId, $content]
        );
        $id = (int)$this->db->lastInsertId();

        return $this->db->fetchOne(
            'SELECT id, sender_id, recipient_id, content, is_read, created_at
             FROM messages WHERE id = ?',
            [$id]
        );
    }

    /**
     * Liste les conversations de l'utilisateur : pour chaque interlocuteur,
     * le dernier message échangé et le nombre de messages non lus.
     *
     * @return array Conversations triées par date du dernier message
     */
    public function getConversations($userId)
    {
        $sql = 'SELECT
                    other.id AS user_id,
                    other.username,
                    other.avatar,
                    lm.content AS last_content,
                    lm.created_at AS last_time,
                    lm.sender_id AS last_sender_id,
                    (SELECT COUNT(*) FROM messages m2
                       WHERE m2.recipient_id = ?
                         AND m2.sender_id = other.id
                         AND m2.is_read = 0) AS unread_count
                FROM (
                    -- Identifiant de l\'interlocuteur + id du dernier message échangé
                    SELECT
                        CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS partner_id,
                        MAX(id) AS last_id
                    FROM messages
                    WHERE sender_id = ? OR recipient_id = ?
                    GROUP BY partner_id
                ) conv
                JOIN messages lm ON lm.id = conv.last_id
                JOIN users other ON other.id = conv.partner_id
                ORDER BY lm.created_at DESC';

        return $this->db->fetchAll($sql, [
            (int)$userId, (int)$userId, (int)$userId, (int)$userId,
        ]);
    }

    /**
     * Récupère les messages échangés entre l'utilisateur et un interlocuteur.
     * Si $afterId est fourni, ne renvoie que les messages plus récents
     * (utile pour le polling : on ne recharge que le nouveau).
     *
     * @return array Messages triés du plus ancien au plus récent
     */
    public function getConversationWith($userId, $otherId, $afterId = 0)
    {
        $sql = 'SELECT id, sender_id, recipient_id, content, is_read, created_at
                FROM messages
                WHERE ((sender_id = ? AND recipient_id = ?)
                    OR (sender_id = ? AND recipient_id = ?))
                  AND id > ?
                ORDER BY id ASC
                LIMIT 300';

        return $this->db->fetchAll($sql, [
            (int)$userId, (int)$otherId,
            (int)$otherId, (int)$userId,
            (int)$afterId,
        ]);
    }

    /**
     * Marque comme lus les messages reçus de $otherId par $userId.
     */
    public function markConversationRead($userId, $otherId)
    {
        $this->db->execute(
            'UPDATE messages SET is_read = 1
             WHERE recipient_id = ? AND sender_id = ? AND is_read = 0',
            [(int)$userId, (int)$otherId]
        );
    }

    /**
     * Nombre total de messages non lus de l'utilisateur (pour la pastille navbar).
     *
     * @return int
     */
    public function getUnreadCount($userId)
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS count FROM messages WHERE recipient_id = ? AND is_read = 0',
            [(int)$userId]
        );
        return (int)($row['count'] ?? 0);
    }

    /**
     * Infos publiques d'un utilisateur (en-tête de conversation).
     *
     * @return array|null
     */
    public function getUserInfo($userId)
    {
        return $this->db->fetchOne(
            'SELECT id, username, avatar, profile_visibility FROM users WHERE id = ?',
            [(int)$userId]
        );
    }
}
