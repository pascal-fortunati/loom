<?php

// Routes publications

return [
    // Lister posts d'une page passion
    [
        'method' => 'GET',
        'pattern' => '/^GET \/posts\/(\d+)(\?.*)?$/',
        'handler' => function($id) {
            $controller = new PostController();
            $controller->listByPassion($id);
        }
    ],
    
    // Créer un post
    [
        'method' => 'POST',
        'pattern' => '/^POST \/posts$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new PostController();
            $controller->create();
        }
    ],
    
    // Éditer un post
    [
        'method' => 'PUT',
        'pattern' => '/^PUT \/posts\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new PostController();
            $controller->update($id);
        }
    ],
    
    // Supprimer un post
    [
        'method' => 'DELETE',
        'pattern' => '/^DELETE \/posts\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new PostController();
            $controller->delete($id);
        }
    ],
];
