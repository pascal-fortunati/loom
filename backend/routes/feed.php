<?php

// Routes fil d'actualité

return [
    // Récupérer fil personnalisé
    [
        'method' => 'GET',
        'pattern' => '/^GET \/feed(\?.*)?$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new FeedController();
            $controller->getFeed();
        }
    ],

    // Récupérer le fil personnel (mes passions)
    [
        'method' => 'GET',
        'pattern' => '/^GET \/feed\/mine(\?.*)?$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new FeedController();
            $controller->getMyPassionsFeed();
        }
    ],
    
    // Récupérer fil d'exploration publique
    [
        'method' => 'GET',
        'pattern' => '/^GET \/feed\/explore(\?.*)?$/',
        'handler' => function() {
            $controller = new FeedController();
            $controller->explore();
        }
    ],
];
