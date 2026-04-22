<?php

// CONTRÔLEUR - LIKES
class LikeController
{
    private $likeModel;
    private $postModel;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->likeModel = new LikeModel();
        $this->postModel = new PostModel();
    }

    /**
     * Endpoint : POST /likes
     * Liker un post
     * 
     * Body :
     * {
     *   "post_id": 1
     * }
     */
    public function like()
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide les données
        $postId = $body['post_id'] ?? null;

        if (!is_numeric($postId) || $postId <= 0) {
            Response::error('ID du post invalide', 400);
        }

        // Vérifie que le post existe
        $post = $this->postModel->findById($postId);
        if ($post === null) {
            Response::error('Post non trouvé', 404);
        }

        // Crée le like
        $likeId = $this->likeModel->like($userId, $postId);

        if ($likeId === false) {
            Response::error('Vous avez déjà liké ce post, ou erreur', 400);
        }

        Response::success([
            'id' => $likeId,
            'post_id' => $postId
        ], 'Like ajouté', 201);
    }

    /**
     * Endpoint : DELETE /likes/{postId}
     * Retirer un like d'un post
     * 
     * Paramètres URL :
     * - {postId} : L'ID du post
     */
    public function unlike($postId)
    {
        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];

        // Valide l'ID
        if (!is_numeric($postId) || $postId <= 0) {
            Response::error('ID du post invalide', 400);
        }

        // Vérifie que le post existe
        $post = $this->postModel->findById($postId);
        if ($post === null) {
            Response::error('Post non trouvé', 404);
        }

        // Supprime le like
        if (!$this->likeModel->unlike($userId, $postId)) {
            Response::error('Vous n\'avez pas liké ce post', 400);
        }

        Response::success(['post_id' => $postId], 'Like retiré');
    }
}
