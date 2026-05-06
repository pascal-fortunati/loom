<?php

// CONTRÔLEUR - ABONNEMENTS
class SubscriptionController
{
    private $subscriptionModel;
    private $passionPageModel;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->subscriptionModel = new SubscriptionModel();
        $this->passionPageModel = new PassionPageModel();
    }

    /**
     * Endpoint : GET /subscriptions/my
     * Liste les abonnements de l'utilisateur connecté
     * 
     * Paramètres GET (optionnels) :
     * - limit : Nombre de résultats (défaut 50)
     * - offset : Pour pagination (défaut 0)
     */
    public function getMySubscriptions()
    {
        global $queryParams;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Récupère les paramètres de pagination
        $limit = min((int)($queryParams['limit'] ?? 50), 200);
        $offset = max(0, (int)($queryParams['offset'] ?? 0));

        // Récupère les abonnements
        $subscriptions = $this->subscriptionModel->getUserSubscriptions($userId, $limit, $offset);

        Response::success($subscriptions, 'Abonnements récupérés');
    }

    /**
     * Endpoint : POST /subscriptions
     * S'abonner à une page de passion
     * 
     * Body :
     * {
     *   "passion_page_id": 1
     * }
     */
    public function subscribe()
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide les données
        $passionPageId = isset($body['passion_page_id']) ? (int)$body['passion_page_id'] : null;

        if (!is_numeric($passionPageId) || $passionPageId <= 0) {
            Response::error('ID de passion invalide', 400);
        }

        // Vérifie que la page existe et est publique
        $page = $this->passionPageModel->findById($passionPageId);
        if ($page === null || $page['is_public'] === 0) {
            Response::error('Page de passion non trouvée ou privée', 404);
        }

        // Crée l'abonnement
        $subscriptionId = $this->subscriptionModel->subscribe($userId, $passionPageId);

        if ($subscriptionId === false) {
            Response::error('Vous êtes déjà abonné à cette page, ou erreur lors de l\'abonnement', 400);
        }

        $subscription = $this->subscriptionModel->findById($subscriptionId);
        if ($subscription === null || (int)$subscription['passion_page_id'] !== (int)$passionPageId) {
            Response::error('Abonnement incohérent détecté', 500);
        }

        Response::success([
            'id' => $subscriptionId,
            'passion_page_id' => $passionPageId
        ], 'Abonnement créé avec succès', 201);
    }

    /**
     * Endpoint : DELETE /subscriptions/{passionPageId}
     * Se désabonner d'une page de passion
     * 
     * Paramètres URL :
     * - {passionPageId} : L'ID de la page
     */
    public function unsubscribe($passionPageId)
    {
        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($passionPageId) || $passionPageId <= 0) {
            Response::error('ID de passion invalide', 400);
        }

        // Vérifie que la page existe
        $page = $this->passionPageModel->findById($passionPageId);
        if ($page === null) {
            Response::error('Page de passion non trouvée', 404);
        }

        // Supprime l'abonnement
        if (!$this->subscriptionModel->unsubscribe($userId, $passionPageId)) {
            Response::error('Vous n\'êtes pas abonné à cette page', 400);
        }

        Response::success([
            'passion_page_id' => $passionPageId
        ], 'Abonnement supprimé');
    }
}
