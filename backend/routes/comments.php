<?php

// Routes commentaires

return [
    // Lister commentaires d'un post
    [
        'method' => 'GET',
        'pattern' => '/^GET \/posts\/(\d+)\/comments(\?.*)?$/',
        'handler' => function($id) {
            $controller = new CommentController();
            $controller->listByPost($id);
        }
    ],
    
    // Créer un commentaire
    [
        'method' => 'POST',
        'pattern' => '/^POST \/comments$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new CommentController();
            $controller->create();
        }
    ],
    
    // Éditer un commentaire
    [
        'method' => 'PUT',
        'pattern' => '/^PUT \/comments\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new CommentController();
            $controller->update($id);
        }
    ],
    
    // Supprimer un commentaire
    [
        'method' => 'DELETE',
        'pattern' => '/^DELETE \/comments\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new CommentController();
            $controller->delete($id);
        }
    ],
];
