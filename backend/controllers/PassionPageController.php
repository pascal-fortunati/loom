<?php

// CONTRÔLEUR - PAGES DE PASSIONS
class PassionPageController
{
    private $passionPageModel;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->passionPageModel = new PassionPageModel();
    }

    /**
     * Endpoint : GET /passion-pages
     * Liste toutes les pages de passions publiques
     * 
     * Paramètres GET (optionnels) :
     * - limit : Nombre de résultats (défaut 20)
     * - offset : Pour pagination (défaut 0)
     */
    public function listAll()
    {
        global $queryParams;

        // Récupère les paramètres de pagination
        $limit = min((int)($queryParams['limit'] ?? 20), 100); // Max 100
        $offset = max(0, (int)($queryParams['offset'] ?? 0));

        // Récupère les pages publiques
        $pages = $this->passionPageModel->getAllPublic($limit, $offset);

        Response::success($pages, 'Pages de passions récupérées');
    }

    /**
     * Endpoint : GET /passion-pages/my
     * Liste les pages de passions de l'utilisateur connecté (privées et publiques)
     */
    public function getMyPages()
    {
        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Récupère toutes les pages (inclus privées)
        $pages = $this->passionPageModel->getByUserId($userId, true);

        Response::success($pages, 'Vos pages de passions');
    }

    /**
     * Endpoint : GET /passion-pages/{id}
     * Récupère les détails d'une page de passion
     * 
     * Paramètres URL :
     * - {id} : L'ID de la page
     */
    public function getDetail($pageId)
    {
        // Valide l'ID
        if (!is_numeric($pageId) || $pageId <= 0) {
            Response::error('ID de passion invalide', 400);
        }

        // Récupère la page
        $page = $this->passionPageModel->findById($pageId);

        if ($page === null) {
            Response::error('Page de passion non trouvée', 404);
        }

        // Vérifie si elle est publique ou si l'utilisateur est propriétaire
        $payload = AuthMiddleware::authenticateOptional();
        
        if ($page['is_public'] === 0) {
            // Page privée - vérifier que l'utilisateur est propriétaire
            if ($payload === null || (int)$payload['user_id'] !== (int)$page['user_id']) {
                Response::error('Vous n\'avez pas accès à cette page', 403);
            }
        }

        // Ajoute le nombre d'abonnés
        $page['subscriber_count'] = $this->passionPageModel->getSubscriberCount($pageId);

        Response::success($page, 'Page de passion récupérée');
    }

    /**
     * Endpoint : POST /passion-pages
     * Crée une nouvelle page de passion
     * 
     * Body :
     * {
     *   "name": "Gaming",
     *   "description": "Discussions sur les jeux vidéo",
     *   "is_public": true
     * }
     */
    public function create()
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide les données
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';
        $isPublic = $body['is_public'] ?? true;

        // Valide le nom
        if (!PassionPageModel::isValidName($name)) {
            Response::error('Le nom doit faire entre 2 et 100 caractères', 400, [
                'name' => 'Invalide'
            ]);
        }

        // Valide la description
        if (!PassionPageModel::isValidDescription($description)) {
            Response::error('La description ne peut pas dépasser 1000 caractères', 400, [
                'description' => 'Trop long'
            ]);
        }

        // Crée la page
        $pageId = $this->passionPageModel->create($userId, $name, $description, (bool)$isPublic);

        if ($pageId === false) {
            Response::error('Erreur lors de la création de la page', 500);
        }

        // Récupère et retourne la page créée
        $page = $this->passionPageModel->findById($pageId);

        Response::success($page, 'Page de passion créée avec succès', 201);
    }

    /**
     * Endpoint : PUT /passion-pages/{id}
     * Édite une page de passion
     * 
     * Paramètres URL :
     * - {id} : L'ID de la page
     * 
     * Body :
     * {
     *   "name": "Gaming et Esports",
     *   "description": "New description",
     *   "is_public": false
     * }
     */
    public function update($pageId)
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($pageId) || $pageId <= 0) {
            Response::error('ID de passion invalide', 400);
        }

        // Récupère la page
        $page = $this->passionPageModel->findById($pageId);

        if ($page === null) {
            Response::error('Page de passion non trouvée', 404);
        }

        // Vérifie que l'utilisateur est propriétaire
        if (!$this->passionPageModel->isOwner($pageId, $userId)) {
            Response::error('Vous ne pouvez éditer que vos propres pages', 403);
        }

        // Prépare les données à mettre à jour
        $updates = [];

        if (isset($body['name'])) {
            if (!PassionPageModel::isValidName($body['name'])) {
                Response::error('Le nom doit faire entre 2 et 100 caractères', 400);
            }
            $updates['name'] = $body['name'];
        }

        if (isset($body['description'])) {
            if (!PassionPageModel::isValidDescription($body['description'])) {
                Response::error('La description ne peut pas dépasser 1000 caractères', 400);
            }
            $updates['description'] = $body['description'];
        }

        if (isset($body['is_public'])) {
            $updates['is_public'] = (bool)$body['is_public'] ? 1 : 0;
        }

        if (empty($updates)) {
            Response::error('Aucun champ à mettre à jour', 400);
        }

        // Effectue la mise à jour
        if (!$this->passionPageModel->update($pageId, $updates)) {
            Response::error('Erreur lors de la mise à jour', 500);
        }

        // Récupère et retourne la page mise à jour
        $updatedPage = $this->passionPageModel->findById($pageId);

        Response::success($updatedPage, 'Page de passion mise à jour');
    }

    /**
     * Endpoint : DELETE /passion-pages/{id}
     * Supprime une page de passion
     * 
     * Paramètres URL :
     * - {id} : L'ID de la page
     */
    public function delete($pageId)
    {
        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($pageId) || $pageId <= 0) {
            Response::error('ID de passion invalide', 400);
        }

        // Récupère la page
        $page = $this->passionPageModel->findById($pageId);

        if ($page === null) {
            Response::error('Page de passion non trouvée', 404);
        }

        // Vérifie que l'utilisateur est propriétaire
        if (!$this->passionPageModel->isOwner($pageId, $userId)) {
            Response::error('Vous ne pouvez supprimer que vos propres pages', 403);
        }

        // Supprime la page
        if (!$this->passionPageModel->delete($pageId)) {
            Response::error('Erreur lors de la suppression', 500);
        }

        Response::success(['id' => $pageId], 'Page de passion supprimée');
    }
}
