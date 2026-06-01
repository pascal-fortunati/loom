<?php

// Routes messagerie privée

return [
    // Liste des conversations de l'utilisateur connecté
    [
        'method' => 'GET',
        'pattern' => '/^GET \/messages\/conversations$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new MessageController();
            $controller->getConversations();
        }
    ],

    // Nombre de messages non lus (pour la pastille navbar / polling)
    [
        'method' => 'GET',
        'pattern' => '/^GET \/messages\/unread-count$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new MessageController();
            $controller->getUnreadCount();
        }
    ],

    // Conversation avec un utilisateur précis
    [
        'method' => 'GET',
        'pattern' => '/^GET \/messages\/with\/(\d+)$/',
        'handler' => function($otherId) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new MessageController();
            $controller->getConversation($otherId);
        }
    ],

    // Envoi d'un message
    [
        'method' => 'POST',
        'pattern' => '/^POST \/messages$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new MessageController();
            $controller->send();
        }
    ],
];
