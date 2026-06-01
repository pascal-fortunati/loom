<?php

// MODÈLE - STATISTIQUES PUBLIQUES
class StatsModel
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
     * Récupère les statistiques publiques globales de la plateforme.
     *
     * Remarque:
     * - membres_total: tous les comptes utilisateurs
     * - publications_publiques_total: posts attachés à des passions publiques
     * - passions_publiques_total: pages de passion visibles publiquement
     *
     * @return array Statistiques globales
     */
    public function getPublicStats()
    {
        $sql = 'SELECT
                    (SELECT COUNT(*) FROM users) AS membres_total,
                    (SELECT COUNT(*)
                     FROM posts p
                     INNER JOIN passion_pages pp ON pp.id = p.passion_page_id
                     WHERE pp.is_public = 1) AS publications_publiques_total,
                    (SELECT COUNT(*) FROM passion_pages WHERE is_public = 1) AS passions_publiques_total';

        $stats = $this->db->fetchOne($sql);

        return [
            'membres_total' => (int)($stats['membres_total'] ?? 0),
            'publications_publiques_total' => (int)($stats['publications_publiques_total'] ?? 0),
            'passions_publiques_total' => (int)($stats['passions_publiques_total'] ?? 0),
        ];
    }

    /**
     * Récupère les statistiques privées de l'utilisateur connecté.
     *
     * Règles métier:
     * - passions_total: pages créées par l'utilisateur
     * - publications_total: posts publiés dans les passions de l'utilisateur
     * - suivis_total: abonnements vers des passions d'autres utilisateurs
     *
     * @param int $userId ID utilisateur connecté
     * @return array Statistiques privées
     */
    public function getPrivateStats($userId)
    {
        $sql = 'SELECT
                    (SELECT COUNT(*) FROM passion_pages WHERE user_id = ?) AS passions_total,
                    (SELECT COUNT(*)
                     FROM posts p
                     INNER JOIN passion_pages pp ON pp.id = p.passion_page_id
                     WHERE pp.user_id = ?) AS publications_total,
                    (SELECT COUNT(*)
                     FROM subscriptions s
                     INNER JOIN passion_pages followed_pp ON followed_pp.id = s.passion_page_id
                     WHERE s.follower_id = ? AND followed_pp.user_id <> ?) AS suivis_total';

        $stats = $this->db->fetchOne($sql, [(int)$userId, (int)$userId, (int)$userId, (int)$userId]);

        return [
            'passions_total' => (int)($stats['passions_total'] ?? 0),
            'publications_total' => (int)($stats['publications_total'] ?? 0),
            'suivis_total' => (int)($stats['suivis_total'] ?? 0),
        ];
    }

    /**
     * Récupère les statistiques privées détaillées de l'utilisateur connecté.
     *
     * Règles métier (tout est rapporté aux passions créées par l'utilisateur):
     * - likes_recus_total: likes reçus sur les publications de ses passions
     * - commentaires_recus_total: commentaires reçus sur ces mêmes publications
     * - abonnes_total: utilisateurs (autres que lui-même) abonnés à ses passions
     * - passions_publiques_total: ses pages de passion visibles publiquement
     * - passions_privees_total: ses pages de passion privées
     *
     * @param int $userId ID utilisateur connecté
     * @return array Statistiques privées détaillées
     */
    public function getPrivateStatsDetails($userId)
    {
        $sql = 'SELECT
                    (SELECT COUNT(*)
                     FROM likes l
                     INNER JOIN posts p ON p.id = l.post_id
                     INNER JOIN passion_pages pp ON pp.id = p.passion_page_id
                     WHERE pp.user_id = ?) AS likes_recus_total,
                    (SELECT COUNT(*)
                     FROM comments c
                     INNER JOIN posts p ON p.id = c.post_id
                     INNER JOIN passion_pages pp ON pp.id = p.passion_page_id
                     WHERE pp.user_id = ?) AS commentaires_recus_total,
                    (SELECT COUNT(*)
                     FROM subscriptions s
                     INNER JOIN passion_pages pp ON pp.id = s.passion_page_id
                     WHERE pp.user_id = ? AND s.follower_id <> pp.user_id) AS abonnes_total,
                    (SELECT COUNT(*) FROM passion_pages WHERE user_id = ? AND is_public = 1) AS passions_publiques_total,
                    (SELECT COUNT(*) FROM passion_pages WHERE user_id = ? AND is_public = 0) AS passions_privees_total';

        $stats = $this->db->fetchOne($sql, [(int)$userId, (int)$userId, (int)$userId, (int)$userId, (int)$userId]);

        return [
            'likes_recus_total' => (int)($stats['likes_recus_total'] ?? 0),
            'commentaires_recus_total' => (int)($stats['commentaires_recus_total'] ?? 0),
            'abonnes_total' => (int)($stats['abonnes_total'] ?? 0),
            'passions_publiques_total' => (int)($stats['passions_publiques_total'] ?? 0),
            'passions_privees_total' => (int)($stats['passions_privees_total'] ?? 0),
        ];
    }
}
