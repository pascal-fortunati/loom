<?php

// Routes pages de passions

return [
    // Lister toutes les pages passions publiques
    [
        'method' => 'GET',
        'pattern' => '/^GET \/passion-pages(\?.*)?$/',
        'handler' => function() {
            $controller = new PassionPageController();
            $controller->listAll();
        }
    ],
    
    // Récupérer mes pages passions
    [
        'method' => 'GET',
        'pattern' => '/^GET \/passion-pages\/my$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new PassionPageController();
            $controller->getMyPages();
        }
    ],
    
    // Détail d'une page passion
    [
        'method' => 'GET',
        'pattern' => '/^GET \/passion-pages\/(\d+)$/',
        'handler' => function($id) {
            $controller = new PassionPageController();
            $controller->getDetail($id);
        }
    ],
    
    // Créer une page passion
    [
        'method' => 'POST',
        'pattern' => '/^POST \/passion-pages$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new PassionPageController();
            $controller->create();
        }
    ],
    
    // Éditer une page passion
    [
        'method' => 'PUT',
        'pattern' => '/^PUT \/passion-pages\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new PassionPageController();
            $controller->update($id);
        }
    ],
    
    // Supprimer une page passion
    [
        'method' => 'DELETE',
        'pattern' => '/^DELETE \/passion-pages\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new PassionPageController();
            $controller->delete($id);
        }
    ],
];
