<?php

// Contrôleur d'authentification - Gère l'inscription, la connexion et la récupération des infos utilisateur
class AuthController
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
     * Endpoint : POST /auth/register
     * Inscrit un nouvel utilisateur
     * 
     * Body attendu :
     * {
     *   "username": "alice_gaming",
     *   "email": "alice@example.com",
     *   "password": "motdepasse123"
     * }
     */
    public function register()
    {
        global $body;

        // Récupère les données
        $username = $body['username'] ?? '';
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        // Vérifie que tous les champs sont remplis
        if (empty($username) || empty($email) || empty($password)) {
            Response::error('Username, email et password sont requis', 400, [
                'username' => empty($username) ? 'Requis' : null,
                'email' => empty($email) ? 'Requis' : null,
                'password' => empty($password) ? 'Requis' : null
            ]);
        }

        // Valide le format du username
        if (!Validator::isValidUsername($username)) {
            Response::error('Username invalide (3-50 caractères, alphanumériques + tiret/tiret bas)', 400);
        }

        // Valide le format de l'email
        if (!Validator::isValidEmail($email)) {
            Response::error('Email invalide', 400);
        }

        // Valide la force du mot de passe
        if (!Validator::isValidPassword($password)) {
            Response::error('Mot de passe trop faible (minimum 6 caractères)', 400);
        }

        // Vérifie que le username n'existe pas
        if ($this->userModel->usernameExists($username)) {
            Response::error('Ce username existe déjà', 400, ['username' => 'Indisponible']);
        }

        // Vérifie que l'email n'existe pas
        if ($this->userModel->emailExists($email)) {
            Response::error('Cet email est déjà utilisé', 400, ['email' => 'Déjà inscrit']);
        }

        // Crée le compte utilisateur
        $passwordHash = PasswordHelper::hash($password);
        $userId = $this->userModel->create($username, $email, $passwordHash);

        if ($userId === false) {
            Response::error('Erreur lors de la création du compte', 500);
        }

        // Génère un JWT pour l'utilisateur
        $token = JWT::encode(['user_id' => $userId, 'username' => $username]);

        Response::success([
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'token' => $token
        ], 'Inscription réussie', 201);
    }

    /**
     * Endpoint : POST /auth/login
     * Connecte un utilisateur et retourne un JWT
     * 
     * Body attendu :
     * {
     *   "email": "alice@example.com",
     *   "password": "motdepasse123"
     * }
     */
    public function login()
    {
        global $body;

        // Anti-bruteforce : limite les tentatives de connexion par IP
        // (5 essais maximum sur une fenêtre de 5 minutes).
        // IP du vrai client, et non celle de nginx (voir ClientIp).
        $clientIp = ClientIp::get();
        $rateKey = 'login:' . $clientIp;
        if (!RateLimiter::attempt($rateKey, 5, 300)) {
            Response::error(
                'Trop de tentatives de connexion. Réessaie dans quelques minutes.',
                429
            );
        }

        // Récupère les données
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        // Vérifie que tous les champs sont remplis
        if (empty($email) || empty($password)) {
            Response::error('Email et password requis', 400);
        }

       
        // Recherche l'utilisateur par email
        $user = $this->userModel->findByEmail($email);

        if ($user === null) {
            // Ne pas révéler si l'email existe ou pas (sécurité)
            Response::error('Email ou mot de passe incorrect', 401);
        }

        // Vérifie le mot de passe
        if (!PasswordHelper::verify($password, $user['password_hash'])) {
            Response::error('Email ou mot de passe incorrect', 401);
        }

        // Connexion réussie : on réinitialise le compteur anti-bruteforce
        RateLimiter::reset($rateKey);

        // Génère un JWT pour l'utilisateur
        $token = JWT::encode([
            'user_id' => $user['id'],
            'username' => $user['username']
        ]);

        // Retourne les données sans le hash du mot de passe
        Response::success([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'avatar' => $user['avatar'],
            'bio' => $user['bio'],
            'token' => $token
        ], 'Connexion réussie');
    }

    /**
     * Endpoint : GET /auth/me
     * Retourne les infos de l'utilisateur connecté (protégé par JWT)
     */
    public function getMe()
    {
        // Vérifie l'authentification
        $payload = AuthMiddleware::authenticate();

        // Récupère les données utilisateur
        $user = $this->userModel->findById($payload['user_id']);

        if ($user === null) {
            Response::error('Utilisateur non trouvé', 404);
        }

        Response::success($user, 'Utilisateur récupéré');
    }
}