<?php

// Routes notifications

return [
    // Liste des notifications de l'utilisateur connecté
    [
        'method' => 'GET',
        'pattern' => '/^GET \/notifications$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new NotificationController();
            $controller->getNotifications();
        }
    ],

    // Nombre de notifications non lues (pastille cloche / polling)
    [
        'method' => 'GET',
        'pattern' => '/^GET \/notifications\/unread-count$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new NotificationController();
            $controller->getUnreadCount();
        }
    ],

    // Marquer toutes les notifications comme lues
    [
        'method' => 'POST',
        'pattern' => '/^POST \/notifications\/read$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new NotificationController();
            $controller->markAllRead();
        }
    ],

    // Supprimer une notification
    [
        'method' => 'DELETE',
        'pattern' => '/^DELETE \/notifications\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new NotificationController();
            $controller->delete($id);
        }
    ],
];
