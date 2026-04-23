<?php

// Routes d'authentification

return [
    // Inscription
    [
        'method' => 'POST',
        'pattern' => '/^POST \/auth\/register$/',
        'handler' => function() {
            $controller = new AuthController();
            $controller->register();
        }
    ],
    
    // Connexion
    [
        'method' => 'POST',
        'pattern' => '/^POST \/auth\/login$/',
        'handler' => function() {
            $controller = new AuthController();
            $controller->login();
        }
    ],
    
    // Récupérer profil authentifié
    [
        'method' => 'GET',
        'pattern' => '/^GET \/auth\/me$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new AuthController();
            $controller->getMe();
        }
    ],
];
