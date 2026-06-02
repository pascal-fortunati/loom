<?php

// MODÈLE - RECHERCHE (utilisateurs + passions publiques)
class SearchModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche d'utilisateurs par pseudo (LIKE).
     *
     * @param string $query Terme recherché
     * @param int $limit Nombre max de résultats
     * @return array
     */
    public function searchUsers($query, $limit = 8)
    {
        $sql = 'SELECT id, username, avatar
                FROM users
                WHERE username LIKE ?
                ORDER BY username ASC
                LIMIT ?';
        // Le % est volontaire (joker LIKE) ; PDO échappe la valeur (anti-injection).
        return $this->db->fetchAll($sql, ['%' . $query . '%', (int)$limit]);
    }

    /**
     * Recherche de pages de passion publiques par nom (LIKE).
     *
     * @param string $query Terme recherché
     * @param int $limit Nombre max de résultats
     * @return array
     */
    public function searchPassions($query, $limit = 8)
    {
        $sql = 'SELECT pp.id, pp.name, pp.cover_image, u.username, u.avatar
                FROM passion_pages pp
                JOIN users u ON u.id = pp.user_id
                WHERE pp.is_public = 1 AND pp.name LIKE ?
                ORDER BY pp.name ASC
                LIMIT ?';
        return $this->db->fetchAll($sql, ['%' . $query . '%', (int)$limit]);
    }
}
