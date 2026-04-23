<?php

// Routes abonnements

return [
    // Récupérer mes abonnements
    [
        'method' => 'GET',
        'pattern' => '/^GET \/subscriptions\/my(\?.*)?$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new SubscriptionController();
            $controller->getMySubscriptions();
        }
    ],
    
    // S'abonner à une page passion
    [
        'method' => 'POST',
        'pattern' => '/^POST \/subscriptions$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new SubscriptionController();
            $controller->subscribe();
        }
    ],
    
    // Se désabonner d'une page passion
    [
        'method' => 'DELETE',
        'pattern' => '/^DELETE \/subscriptions\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new SubscriptionController();
            $controller->unsubscribe($id);
        }
    ],
];
