<?php

// CONTRÔLEUR - UTILISATEURS
class UserController
{
    private $userModel;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Endpoint : GET /users/{id}
     * Récupère le profil public d'un utilisateur
     * 
     * Paramètres URL :
     * - {id} : L'ID de l'utilisateur
     * 
     * Réponse :
     * {
     *   "id": 1,
     *   "username": "alice_gaming",
     *   "avatar": "avatar1.jpg",
     *   "bio": "Passionnée de jeux vidéo"
     * }
     */
    public function getProfile($userId)
    {
        // Valide que c'est un nombre entier
        if (!is_numeric($userId) || $userId <= 0) {
            Response::error('ID utilisateur invalide', 400);
        }

        // Récupère les infos publiques
        $user = $this->userModel->findById($userId);

        if ($user === null) {
            Response::error('Utilisateur non trouvé', 404);
        }

        // Vérifie l'auth optionnelle pour personnaliser la visibilité sans bloquer la route publique.
        $payload = AuthMiddleware::authenticateOptional();
        $viewerUserId = isset($payload['user_id']) ? (int)$payload['user_id'] : null;

        // Détermine les droits de visibilité et construit une réponse sécurisée.
        $visibilityContext = $this->buildVisibilityContext($user, $viewerUserId);
        if (!$visibilityContext['can_view_profile']) {
            $lockedProfile = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'avatar' => $user['avatar'],
                'bio' => null,
                'profile_visibility' => $user['profile_visibility'] ?? 'public',
                'is_profile_locked' => true,
                'is_owner' => $visibilityContext['is_owner'],
                'is_follower' => $visibilityContext['is_follower'],
            ];
            Response::success($lockedProfile, 'Ce profil est privé pour votre niveau d\'accès');
        }

        $profile = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'avatar' => $user['avatar'],
            'bio' => $user['bio'],
            'created_at' => $user['created_at'],
            'profile_visibility' => $user['profile_visibility'] ?? 'public',
            'is_profile_locked' => false,
            'is_owner' => $visibilityContext['is_owner'],
            'is_follower' => $visibilityContext['is_follower'],
        ];

        // L'email reste privé sauf pour le propriétaire connecté.
        if ($visibilityContext['is_owner']) {
            $profile['email'] = $user['email'];
        }

        Response::success($profile, 'Profil utilisateur récupéré');
    }

    /**
     * Endpoint : PUT /users/{id}
     * Édite le profil de l'utilisateur connecté
     * 
     * Paramètres URL :
     * - {id} : L'ID de l'utilisateur (doit correspondre au JWT)
     * 
     * Body :
     * {
     *   "bio": "Nouvelle bio",
     *   "avatar": "new_avatar.jpg"
     * }
     */
    public function updateProfile($userId)
    {
        global $body;

        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();

        // Vérifie que l'utilisateur édite son propre profil (sécurité)
        if ((int)$userId !== (int)$payload['user_id']) {
            Response::error('Vous ne pouvez éditer que votre propre profil', 403);
        }

        // Valide l'ID
        if (!is_numeric($userId) || $userId <= 0) {
            Response::error('ID utilisateur invalide', 400);
        }

        // Vérifie que l'utilisateur existe
        $user = $this->userModel->findById($userId);
        if ($user === null) {
            Response::error('Utilisateur non trouvé', 404);
        }

        // Construit les données à mettre à jour
        $updates = [];

        // Valide et ajoute la bio si fournie
        if (isset($body['bio'])) {
            if (strlen($body['bio']) > 500) {
                Response::error('La bio ne peut pas dépasser 500 caractères', 400);
            }
            $updates['bio'] = $body['bio'];
        }

        // Valide et ajoute l'avatar si fourni (juste le nom du fichier)
        if (isset($body['avatar'])) {
            if (strlen($body['avatar']) > 255 || !preg_match('/^[\w\-\.]+(\.jpg|\.png|\.gif)$/i', $body['avatar'])) {
                Response::error('Nom d\'avatar invalide', 400);
            }
            $updates['avatar'] = $body['avatar'];
        }

        // Valide la visibilité de profil si fournie
        if (isset($body['profile_visibility'])) {
            $allowedValues = ['public', 'followers', 'private'];
            if (!in_array($body['profile_visibility'], $allowedValues, true)) {
                Response::error('Visibilité de profil invalide (public, followers, private)', 400);
            }
            $updates['profile_visibility'] = $body['profile_visibility'];
        }

        // Si rien à mettre à jour
        if (empty($updates)) {
            Response::error('Aucun champ à mettre à jour', 400);
        }

        // Effectue la mise à jour
        if (!$this->userModel->update($userId, $updates)) {
            Response::error('Erreur lors de la mise à jour du profil', 500);
        }

        // Récupère et retourne le profil mis à jour
        $updatedUser = $this->userModel->findById($userId);
        Response::success($updatedUser, 'Profil mis à jour avec succès');
    }

    /**
     * Endpoint : GET /users/{id}/passion-pages
     * Récupère les pages de passions publiques d'un utilisateur
     * 
     * Paramètres URL :
     * - {id} : L'ID de l'utilisateur
     * 
     * Réponse :
     * [
     *   {
     *     "id": 1,
     *     "name": "Gaming",
     *     "description": "Discussions sur les jeux vidéo",
     *     "is_public": true,
     *     "created_at": "2026-04-22..."
     *   }
     * ]
     */
    public function getUserPassionPages($userId)
    {
        // Valide l'ID
        if (!is_numeric($userId) || $userId <= 0) {
            Response::error('ID utilisateur invalide', 400);
        }

        // Vérifie que l'utilisateur existe
        $user = $this->userModel->findById($userId);
        if ($user === null) {
            Response::error('Utilisateur non trouvé', 404);
        }

        // Applique la confidentialité profil avant de révéler les passions publiques.
        $payload = AuthMiddleware::authenticateOptional();
        $viewerUserId = isset($payload['user_id']) ? (int)$payload['user_id'] : null;
        $visibilityContext = $this->buildVisibilityContext($user, $viewerUserId);
        if (!$visibilityContext['can_view_profile']) {
            Response::error('Ce profil est privé, passions non accessibles', 403);
        }

        // Récupère les pages de passions publiques
        $passionPages = $this->userModel->getPassionPages($userId);

        // Filtre pour n'afficher que les pages publiques
        $publicPages = array_filter($passionPages, function ($page) {
            return $page['is_public'] === 1;
        });

        Response::success(array_values($publicPages), 'Pages de passions récupérées');
    }

    /**
     * Calcule le contexte de visibilité d'un profil selon:
     * - propriétaire connecté
     * - follower (abonné à au moins une passion du profil)
     * - règle de confidentialité configurée sur le profil cible
     *
     * @param array $targetUser Profil cible (incluant profile_visibility)
     * @param int|null $viewerUserId Utilisateur connecté qui consulte
     * @return array{
     *   is_owner: bool,
     *   is_follower: bool,
     *   can_view_profile: bool
     * }
     */
    private function buildVisibilityContext($targetUser, $viewerUserId)
    {
        $targetUserId = (int)$targetUser['id'];
        $visibility = $targetUser['profile_visibility'] ?? 'public';
        $isOwner = $viewerUserId !== null && $viewerUserId === $targetUserId;
        $isFollower = false;

        if (!$isOwner && $viewerUserId !== null) {
            $isFollower = $this->userModel->isFollowingUserByPassion($viewerUserId, $targetUserId);
        }

        $canView = false;
        if ($isOwner) {
            $canView = true;
        } elseif ($visibility === 'public') {
            $canView = true;
        } elseif ($visibility === 'followers') {
            $canView = $isFollower;
        } elseif ($visibility === 'private') {
            $canView = false;
        }

        return [
            'is_owner' => $isOwner,
            'is_follower' => $isFollower,
            'can_view_profile' => $canView,
        ];
    }

    /**
     * Endpoint : PUT /users/{id}/password
     * Permet à l'utilisateur connecté de modifier son mot de passe.
     *
     * Body :
     * {
     *   "current_password": "ancienMotDePasse",
     *   "new_password": "nouveauMotDePasse"
     * }
     */
    public function updatePassword($userId)
    {
        global $body;

        $payload = AuthMiddleware::authenticate();

        if ((int)$userId !== (int)$payload['user_id']) {
            Response::error('Vous ne pouvez modifier que votre propre mot de passe', 403);
        }

        if (!is_numeric($userId) || $userId <= 0) {
            Response::error('ID utilisateur invalide', 400);
        }

        $currentPassword = trim($body['current_password'] ?? '');
        $newPassword = trim($body['new_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            Response::error('Les champs current_password et new_password sont requis', 400);
        }

        if (!Validator::isValidPassword($newPassword)) {
            Response::error('Nouveau mot de passe trop faible (minimum 6 caractères)', 400);
        }

        $user = $this->userModel->findByIdWithPassword($userId);
        if ($user === null) {
            Response::error('Utilisateur non trouvé', 404);
        }

        if (!PasswordHelper::verify($currentPassword, $user['password_hash'])) {
            Response::error('Mot de passe actuel incorrect', 401);
        }

        if (PasswordHelper::verify($newPassword, $user['password_hash'])) {
            Response::error('Le nouveau mot de passe doit être différent de l\'ancien', 400);
        }

        $newPasswordHash = PasswordHelper::hash($newPassword);
        if (!$this->userModel->updatePassword($userId, $newPasswordHash)) {
            Response::error('Erreur lors de la mise à jour du mot de passe', 500);
        }

        Response::success([], 'Mot de passe mis à jour avec succès');
    }

    /**
     * Endpoint : DELETE /users/{id}
     * Supprime définitivement le compte de l'utilisateur connecté.
     *
     * Body :
     * {
     *   "password": "motDePasseCourant"
     * }
     */
    public function deleteAccount($userId)
    {
        global $body;

        $payload = AuthMiddleware::authenticate();

        if ((int)$userId !== (int)$payload['user_id']) {
            Response::error('Vous ne pouvez supprimer que votre propre compte', 403);
        }

        if (!is_numeric($userId) || $userId <= 0) {
            Response::error('ID utilisateur invalide', 400);
        }

        $password = trim($body['password'] ?? '');
        if ($password === '') {
            Response::error('Le mot de passe est requis pour confirmer la suppression', 400);
        }

        $user = $this->userModel->findByIdWithPassword($userId);
        if ($user === null) {
            Response::error('Utilisateur non trouvé', 404);
        }

        if (!PasswordHelper::verify($password, $user['password_hash'])) {
            Response::error('Mot de passe incorrect', 401);
        }

        if (!$this->userModel->deleteById($userId)) {
            Response::error('Suppression du compte impossible', 500);
        }

        Response::success([], 'Compte supprimé avec succès');
    }
}
