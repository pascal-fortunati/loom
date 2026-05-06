<?php

// CONTRÔLEUR - FIL D'ACTUALITÉ
class FeedController
{
    private $subscriptionModel;
    private $db;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->subscriptionModel = new SubscriptionModel();
        $this->db = Database::getInstance();
    }

    /**
     * Endpoint : GET /feed
     * Fil principal: publications des passions suivies (abonnements uniquement).
     *
     * Paramètres GET (optionnels):
     * - limit : Nombre de posts (défaut 20)
     * - offset : Pour pagination (défaut 0)
     */
    public function getFeed()
    {
        global $queryParams;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Récupère les paramètres de pagination
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = max(0, (int)($queryParams['offset'] ?? 0));

        // Récupère uniquement les IDs des pages suivies.
        $sql = 'SELECT passion_page_id FROM subscriptions WHERE follower_id = ?';
        $subscriptions = $this->db->fetchAll($sql, [$userId]);
        $pageIds = array_values(array_unique(array_map('intval', array_column($subscriptions, 'passion_page_id'))));

        // Si aucun abonnement, retourne un fil vide.
        if (empty($pageIds)) {
            Response::success([], 'Fil d\'actualité vide');
        }

        // Crée les placeholders pour les paramètres
        $placeholders = implode(',', array_fill(0, count($pageIds), '?'));

        // Récupère les posts des pages auxquelles on est abonné
        // Ordonnés par date décroissante (les plus récents en premier)
        $sql = 'SELECT p.id, p.passion_page_id, p.content, p.image_url, p.created_at,
                        pp.user_id, pp.name as passion_page_name, u.username, u.avatar,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
                        EXISTS(
                            SELECT 1 FROM likes l
                            WHERE l.post_id = p.id AND l.user_id = ?
                        ) as is_liked
                FROM posts p
                JOIN passion_pages pp ON p.passion_page_id = pp.id
                JOIN users u ON pp.user_id = u.id
                WHERE p.passion_page_id IN (' . $placeholders . ')
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?';

        // Ordre important des paramètres:
        // 1) user_id pour le sous-select EXISTS (is_liked),
        // 2) IDs des pages suivies pour IN (...),
        // 3) limit puis offset.
        $params = array_merge([(int)$userId], $pageIds, [(int)$limit, (int)$offset]);

        $posts = $this->db->fetchAll($sql, $params);

        Response::success($posts, 'Fil d\'actualité récupéré');
    }

    /**
     * Endpoint : GET /feed/mine
     * Fil personnel: publications des passions créées par l'utilisateur connecté.
     */
    public function getMyPassionsFeed()
    {
        global $queryParams;

        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = max(0, (int)($queryParams['offset'] ?? 0));

        // Récupère mes pages de passion.
        $sql = 'SELECT id FROM passion_pages WHERE user_id = ?';
        $myPages = $this->db->fetchAll($sql, [$userId]);
        $pageIds = array_values(array_unique(array_map('intval', array_column($myPages, 'id'))));

        if (empty($pageIds)) {
            Response::success([], 'Aucune publication personnelle');
        }

        $placeholders = implode(',', array_fill(0, count($pageIds), '?'));

        $sql = 'SELECT p.id, p.passion_page_id, p.content, p.image_url, p.created_at,
                        pp.user_id, pp.name as passion_page_name, u.username, u.avatar,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
                        EXISTS(
                            SELECT 1 FROM likes l
                            WHERE l.post_id = p.id AND l.user_id = ?
                        ) as is_liked
                FROM posts p
                JOIN passion_pages pp ON p.passion_page_id = pp.id
                JOIN users u ON pp.user_id = u.id
                WHERE p.passion_page_id IN (' . $placeholders . ')
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?';

        $params = array_merge([(int)$userId], $pageIds, [(int)$limit, (int)$offset]);
        $posts = $this->db->fetchAll($sql, $params);

        Response::success($posts, 'Fil personnel récupéré');
    }

    /**
     * Endpoint : GET /feed/explore
     * Récupère les posts des pages publiques pour la section "Explorer"
     */

    public function explore()
    {
        global $queryParams;

        // Récupère les paramètres de pagination
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = max(0, (int)($queryParams['offset'] ?? 0));

        // Récupère les posts des pages publiques
        $sql = 'SELECT p.id, p.passion_page_id, p.content, p.image_url, p.created_at,
                        pp.user_id, pp.name as passion_page_name, u.username, u.avatar,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count,
                        0 as is_liked
                FROM posts p
                JOIN passion_pages pp ON p.passion_page_id = pp.id
                JOIN users u ON pp.user_id = u.id
                WHERE pp.is_public = 1
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?';

        $posts = $this->db->fetchAll($sql, [(int)$limit, (int)$offset]);

        Response::success($posts, 'Exploration récupérée');
    }
}
