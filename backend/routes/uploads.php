<?php

// Routes upload
return [
    // Upload d'image de post
    [
        'method' => 'POST',
        'pattern' => '/^POST \/uploads\/image$/',
        'handler' => function () {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new UploadController();
            $controller->uploadPostImage();
        }
    ],
    // Upload d'avatar utilisateur
    [
        'method' => 'POST',
        'pattern' => '/^POST \/uploads\/avatar$/',
        'handler' => function () {
            $middleware = new AuthMiddleware();
            $middleware->authenticate();
            $controller = new UploadController();
            $controller->uploadAvatarImage();
        }
    ],
];
