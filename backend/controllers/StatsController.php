<?php

// CONTRÔLEUR - STATISTIQUES PUBLIQUES
class StatsController
{
    private $statsModel;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->statsModel = new StatsModel();
    }

    /**
     * Endpoint : GET /stats/public
     * Retourne des chiffres globaux pour la Home publique.
     */
    public function getPublicStats()
    {
        $stats = $this->statsModel->getPublicStats();
        Response::success($stats, 'Statistiques publiques récupérées');
    }

    /**
     * Endpoint : GET /stats/private
     * Retourne les statistiques privées du compte connecté.
     */
    public function getPrivateStats()
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];
        $stats = $this->statsModel->getPrivateStats($userId);
        Response::success($stats, 'Statistiques privées récupérées');
    }

    /**
     * Endpoint : GET /stats/private/details
     * Retourne des statistiques privées détaillées du compte connecté.
     */
    public function getPrivateStatsDetails()
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];
        $stats = $this->statsModel->getPrivateStatsDetails($userId);
        Response::success($stats, 'Statistiques privées détaillées récupérées');
    }
}
