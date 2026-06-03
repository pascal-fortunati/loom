<?php

// Routes statistiques publiques
return [
    // Récupérer les statistiques publiques globales (homepage)
    [
        'method' => 'GET',
        'pattern' => '/^GET \/stats\/public(\?.*)?$/',
        'handler' => function() {
            $controller = new StatsController();
            $controller->getPublicStats();
        }
    ],
    // Récupérer les statistiques privées de l'utilisateur connecté
    [
        'method' => 'GET',
        'pattern' => '/^GET \/stats\/private(\?.*)?$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new StatsController();
            $controller->getPrivateStats();
        }
    ],
    // Récupérer les statistiques privées détaillées de l'utilisateur connecté
    [
        'method' => 'GET',
        'pattern' => '/^GET \/stats\/private\/details(\?.*)?$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new StatsController();
            $controller->getPrivateStatsDetails();
        }
    ],
];
