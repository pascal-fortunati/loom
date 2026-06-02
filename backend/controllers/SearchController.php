<?php

// CONTRÔLEUR - RECHERCHE
class SearchController
{
    private $searchModel;

    public function __construct()
    {
        $this->searchModel = new SearchModel();
    }

    /**
     * Endpoint : GET /search?q=...
     * Recherche publique d'utilisateurs et de passions.
     */
    public function search()
    {
        global $queryParams;

        $query = trim((string)($queryParams['q'] ?? ''));

        // On évite de spammer la base pour 1 caractère
        if (mb_strlen($query) < 2) {
            Response::success(
                ['users' => [], 'passions' => []],
                'Requête trop courte (2 caractères minimum)'
            );
        }

        $users = $this->searchModel->searchUsers($query, 8);
        $passions = $this->searchModel->searchPassions($query, 8);

        Response::success([
            'users' => array_map(function ($user) {
                return [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                ];
            }, $users),
            'passions' => array_map(function ($passion) {
                return [
                    'id' => (int)$passion['id'],
                    'name' => $passion['name'],
                    'username' => $passion['username'],
                    'avatar' => $passion['avatar'],
                ];
            }, $passions),
        ], 'Résultats de recherche');
    }
}
