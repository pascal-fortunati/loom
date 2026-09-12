<?php
class CommentController
{
    private $commentModel;
    private $postModel;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->commentModel = new CommentModel();
        $this->postModel = new PostModel();
    }

    /**
     * Endpoint : GET /posts/{postId}/comments
     * Liste les commentaires d'un post
     * 
     * Paramètres URL :
     * - {postId} : L'ID du post
     * 
     * Paramètres GET (optionnels) :
     * - limit : Nombre de commentaires (défaut 50)
     * - offset : Pour pagination (défaut 0)
     */
    public function listByPost($postId)
    {
        global $queryParams;

        // Valide l'ID
        if (!is_numeric($postId) || $postId <= 0) {
            Response::error('ID du post invalide', 400);
        }

        // Vérifie que le post existe
        $post = $this->postModel->findById($postId);
        if ($post === null) {
            Response::error('Post non trouvé', 404);
        }

        // Applique la confidentialité de la passion parente : les commentaires
        // d'une publication appartenant à une passion privée ne sont visibles
        // que par son propriétaire.
        if ((int)$post['is_public'] === 0) {
            $payload = AuthMiddleware::authenticateOptional();
            if ($payload === null || (int)$payload['user_id'] !== (int)$post['user_id']) {
                Response::error('Vous n\'avez pas accès à cette page', 403);
            }
        }

        // Récupère les paramètres de pagination
        $limit = min((int)($queryParams['limit'] ?? 50), 200);
        $offset = max(0, (int)($queryParams['offset'] ?? 0));

        // Récupère les commentaires
        $comments = $this->commentModel->getByPost($postId, $limit, $offset);

        Response::success($comments, 'Commentaires récupérés');
    }

    /**
     * Endpoint : POST /comments
     * Crée un commentaire
     * 
     * Body :
     * {
     *   "post_id": 1,
     *   "content": "Super post !"
     * }
     */
    public function create()
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide les données
        $postId = $body['post_id'] ?? null;
        $content = $body['content'] ?? '';

        if (!is_numeric($postId) || $postId <= 0) {
            Response::error('ID du post invalide', 400);
        }

        // Vérifie que le post existe
        $post = $this->postModel->findById($postId);
        if ($post === null) {
            Response::error('Post non trouvé', 404);
        }

        // Valide le contenu
        if (!CommentModel::isValidContent($content)) {
            Response::error('Le commentaire doit faire entre 1 et 500 caractères', 400, [
                'content' => 'Invalide'
            ]);
        }

        // Crée le commentaire
        $commentId = $this->commentModel->create($userId, $postId, $content);

        if ($commentId === false) {
            Response::error('Erreur lors de la création du commentaire', 500);
        }

        // Notifie le propriétaire de la publication (sauf s'il commente la sienne)
        $postOwnerId = (int)$post['user_id'];
        if ($postOwnerId !== (int)$userId) {
            $notificationModel = new NotificationModel();
            $notificationModel->create($postOwnerId, (int)$userId, 'comment', (int)$postId, (int)$commentId);
        }

        // Récupère et retourne le commentaire
        $comment = $this->commentModel->findById($commentId);

        Response::success($comment, 'Commentaire créé avec succès', 201);
    }

    /**
     * Endpoint : PUT /comments/{id}
     * Édite son propre commentaire
     * 
     * Paramètres URL :
     * - {id} : L'ID du commentaire
     * 
     * Body :
     * {
     *   "content": "Nouveau contenu"
     * }
     */
    public function update($commentId)
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($commentId) || $commentId <= 0) {
            Response::error('ID du commentaire invalide', 400);
        }

        // Récupère le commentaire
        $comment = $this->commentModel->findById($commentId);
        if ($comment === null) {
            Response::error('Commentaire non trouvé', 404);
        }

        // Vérifie que l'utilisateur est propriétaire
        if (!$this->commentModel->canEdit($commentId, $userId)) {
            Response::error('Vous ne pouvez éditer que vos propres commentaires', 403);
        }

        // Valide le contenu
        $content = $body['content'] ?? '';

        if (!CommentModel::isValidContent($content)) {
            Response::error('Le commentaire doit faire entre 1 et 500 caractères', 400);
        }

        // Effectue la mise à jour
        if (!$this->commentModel->update($commentId, $content)) {
            Response::error('Erreur lors de la mise à jour', 500);
        }

        // Récupère et retourne le commentaire mis à jour
        $updatedComment = $this->commentModel->findById($commentId);

        Response::success($updatedComment, 'Commentaire mis à jour');
    }

    /**
     * Endpoint : DELETE /comments/{id}
     * Supprime son propre commentaire
     * 
     * Paramètres URL :
     * - {id} : L'ID du commentaire
     */
    public function delete($commentId)
    {
        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($commentId) || $commentId <= 0) {
            Response::error('ID du commentaire invalide', 400);
        }

        // Récupère le commentaire
        $comment = $this->commentModel->findById($commentId);
        if ($comment === null) {
            Response::error('Commentaire non trouvé', 404);
        }

        // Vérifie que l'utilisateur est propriétaire
        if (!$this->commentModel->canEdit($commentId, $userId)) {
            Response::error('Vous ne pouvez supprimer que vos propres commentaires', 403);
        }

        // Supprime le commentaire
        if (!$this->commentModel->delete($commentId)) {
            Response::error('Erreur lors de la suppression', 500);
        }

        Response::success(['id' => $commentId], 'Commentaire supprimé');
    }
}