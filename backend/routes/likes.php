<?php

// Routes likes

return [
    // Liker un post
    [
        'method' => 'POST',
        'pattern' => '/^POST \/likes$/',
        'handler' => function() {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new LikeController();
            $controller->like();
        }
    ],
    
    // Unlike un post
    [
        'method' => 'DELETE',
        'pattern' => '/^DELETE \/likes\/(\d+)$/',
        'handler' => function($id) {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new LikeController();
            $controller->unlike($id);
        }
    ],
];
