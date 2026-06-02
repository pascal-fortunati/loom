<?php

// Route recherche (publique : accessible aussi aux visiteurs non connectés)

return [
    [
        'method' => 'GET',
        'pattern' => '/^GET \/search$/',
        'handler' => function() {
            $controller = new SearchController();
            $controller->search();
        }
    ],
];
