<?php

// Routes utilisateurs

return [
    // Récupérer profil utilisateur
    [
        'method' => 'GET',
        'pattern' => '/^GET \/users\/(\d+)$/',
        'handler' => function($id) {
            $controller = new UserController();
            $controller->getProfile($id);
        }
    ],
    
    // Éditer profil utilisateur
    [
        'method' => 'PUT',
        'pattern' => '/^PUT \/users\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new UserController();
            $controller->updateProfile($id);
        }
    ],
    
    // Récupérer pages passions d'un utilisateur
    [
        'method' => 'GET',
        'pattern' => '/^GET \/users\/(\d+)\/passion-pages$/',
        'handler' => function($id) {
            $controller = new UserController();
            $controller->getUserPassionPages($id);
        }
    ],

    // Modifier le mot de passe de l'utilisateur connecté
    [
        'method' => 'PUT',
        'pattern' => '/^PUT \/users\/(\d+)\/password$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new UserController();
            $controller->updatePassword($id);
        }
    ],

    // Supprimer le compte de l'utilisateur connecté
    [
        'method' => 'DELETE',
        'pattern' => '/^DELETE \/users\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new UserController();
            $controller->deleteAccount($id);
        }
    ],
];
