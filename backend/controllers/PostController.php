<?php

// CONTRÔLEUR - POSTS
class PostController
{
    private $postModel;
    private $passionPageModel;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->postModel = new PostModel();
        $this->passionPageModel = new PassionPageModel();
    }

    /**
     * Endpoint : GET /posts/{passionPageId}
     * Liste les posts d'une page de passion
     * 
     * Paramètres URL :
     * - {passionPageId} : L'ID de la page
     * 
     * Paramètres GET (optionnels) :
     * - limit : Nombre de posts (défaut 20)
     * - offset : Pour pagination (défaut 0)
     */
    public function listByPassion($passionPageId)
    {
        global $queryParams;

        // Valide l'ID
        if (!is_numeric($passionPageId) || $passionPageId <= 0) {
            Response::error('ID de passion invalide', 400);
        }

        // Vérifie que la page existe
        $page = $this->passionPageModel->findById($passionPageId);
        if ($page === null) {
            Response::error('Page de passion non trouvée', 404);
        }

        // Applique la confidentialité de la page : une passion privée ne doit
        // livrer ses publications qu'à son propriétaire. Sans ce contrôle, la
        // route de détail était protégée mais celle des publications ne l'était
        // pas (fuite de données).
        if ((int)$page['is_public'] === 0) {
            $payload = AuthMiddleware::authenticateOptional();
            if ($payload === null || (int)$payload['user_id'] !== (int)$page['user_id']) {
                Response::error('Vous n\'avez pas accès à cette page', 403);
            }
        }

        // Récupère les paramètres de pagination
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = max(0, (int)($queryParams['offset'] ?? 0));

        // Récupère les posts
        $posts = $this->postModel->getByPassionPage($passionPageId, $limit, $offset);

        Response::success($posts, 'Posts récupérés');
    }

    /**
     * Endpoint : POST /posts
     * Crée un nouveau post
     * 
     * Body :
     * {
     *   "passion_page_id": 1,
     *   "content": "Mon premier post sur le gaming !",
     *   "image_url": "image.jpg" (optionnel)
     * }
     */
    public function create()
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide les données
        $passionPageId = $body['passion_page_id'] ?? null;
        $content = $body['content'] ?? '';
        $imageUrl = $body['image_url'] ?? null;

        // Vérifie que la page existe
        if (!is_numeric($passionPageId) || $passionPageId <= 0) {
            Response::error('ID de passion invalide', 400);
        }

        $page = $this->passionPageModel->findById($passionPageId);
        if ($page === null) {
            Response::error('Page de passion non trouvée', 404);
        }

        // Vérifie que l'utilisateur est propriétaire de la page
        if ((int)$page['user_id'] !== $userId) {
            Response::error('Vous ne pouvez poster que sur vos propres pages', 403);
        }

        // Valide le contenu
        if (!PostModel::isValidContent($content)) {
            Response::error('Le contenu doit faire entre 1 et 2000 caractères', 400, [
                'content' => 'Invalide'
            ]);
        }

        // Nettoie le HTML des articles côté serveur (anti-XSS, défense en profondeur)
        $content = HtmlSanitizer::sanitizePostContent($content);

        // Crée le post
        $postId = $this->postModel->create($passionPageId, $content, $imageUrl);

        if ($postId === false) {
            Response::error('Erreur lors de la création du post', 500);
        }

        // Récupère et retourne le post créé
        $post = $this->postModel->findById($postId);

        Response::success($post, 'Post créé avec succès', 201);
    }

    /**
     * Endpoint : PUT /posts/{id}
     * Édite un post
     * 
     * Paramètres URL :
     * - {id} : L'ID du post
     * 
     * Body :
     * {
     *   "content": "Contenu modifié",
     *   "image_url": "new_image.jpg"
     * }
     */
    public function update($postId)
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($postId) || $postId <= 0) {
            Response::error('ID du post invalide', 400);
        }

        // Récupère le post
        $post = $this->postModel->findById($postId);
        if ($post === null) {
            Response::error('Post non trouvé', 404);
        }

        // Vérifie que l'utilisateur est propriétaire
        if (!$this->postModel->canEditPost($postId, $userId)) {
            Response::error('Vous ne pouvez éditer que vos propres posts', 403);
        }

        // Prépare les données à mettre à jour
        $updates = [];

        if (isset($body['content'])) {
            if (!PostModel::isValidContent($body['content'])) {
                Response::error('Le contenu doit faire entre 1 et 2000 caractères', 400);
            }
            // Nettoie le HTML des articles côté serveur (anti-XSS)
            $updates['content'] = HtmlSanitizer::sanitizePostContent($body['content']);
        }

        if (isset($body['image_url'])) {
            $updates['image_url'] = $body['image_url'];
        }

        if (empty($updates)) {
            Response::error('Aucun champ à mettre à jour', 400);
        }

        // Effectue la mise à jour
        if (!$this->postModel->update($postId, $updates)) {
            Response::error('Erreur lors de la mise à jour', 500);
        }

        // Récupère et retourne le post mis à jour
        $updatedPost = $this->postModel->findById($postId);

        Response::success($updatedPost, 'Post mis à jour');
    }

    /**
     * Endpoint : DELETE /posts/{id}
     * Supprime un post
     * 
     * Paramètres URL :
     * - {id} : L'ID du post
     */
    public function delete($postId)
    {
        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($postId) || $postId <= 0) {
            Response::error('ID du post invalide', 400);
        }

        // Récupère le post
        $post = $this->postModel->findById($postId);
        if ($post === null) {
            Response::error('Post non trouvé', 404);
        }

        // Vérifie que l'utilisateur est propriétaire
        if (!$this->postModel->canEditPost($postId, $userId)) {
            Response::error('Vous ne pouvez supprimer que vos propres posts', 403);
        }

        // Supprime le post
        if (!$this->postModel->delete($postId)) {
            Response::error('Erreur lors de la suppression', 500);
        }

        Response::success(['id' => $postId], 'Post supprimé');
    }
}
