<?php
// Fichier d'entrée de l'API - index.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/PDODatabase.php';
require_once __DIR__ . '/utils/helpers.php';
require_once __DIR__ . '/middlewares/AuthMiddleware.php';

// Modèles
require_once __DIR__ . '/models/UserModel.php';
require_once __DIR__ . '/models/PassionPageModel.php';
require_once __DIR__ . '/models/PostModel.php';
require_once __DIR__ . '/models/SubscriptionModel.php';
require_once __DIR__ . '/models/LikeModel.php';
require_once __DIR__ . '/models/CommentModel.php';
require_once __DIR__ . '/models/StatsModel.php';
require_once __DIR__ . '/models/MessageModel.php';
require_once __DIR__ . '/models/NotificationModel.php';
require_once __DIR__ . '/models/SearchModel.php';

// Contrôleurs
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/PassionPageController.php';
require_once __DIR__ . '/controllers/PostController.php';
require_once __DIR__ . '/controllers/SubscriptionController.php';
require_once __DIR__ . '/controllers/LikeController.php';
require_once __DIR__ . '/controllers/CommentController.php';
require_once __DIR__ . '/controllers/FeedController.php';
require_once __DIR__ . '/controllers/UploadController.php';
require_once __DIR__ . '/controllers/StatsController.php';
require_once __DIR__ . '/controllers/MessageController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/SearchController.php';

// Active CORS pour les frontends autorisés
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originAllowed = in_array($origin, ALLOWED_ORIGINS);

// En développement, on autorise aussi les origines du réseau local
// (localhost et IP privées 10.x / 192.168.x / 172.16-31.x), afin de pouvoir
// tester l'application depuis un téléphone sur le même réseau Wi-Fi.
if (!$originAllowed && APP_DEBUG && $origin !== '') {
    if (preg_match(
        '#^https?://(localhost|127\.0\.0\.1|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(?:1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3})(:\d+)?$#',
        $origin
    )) {
        $originAllowed = true;
    }
}

if ($originAllowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');

// Gère les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Teste la connexion à la base de données
try {
    $db = Database::getInstance();
    if (APP_DEBUG) {
        error_log('[API] Base de données connectée');
    }
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données'
    ]));
}


// Récupère la méthode HTTP et l'URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/backend', '', $uri); // Enlève le préfixe /backend si présent

// Récupère les paramètres GET
$queryParams = $_GET;

// Récupère le corps de la requête (pour POST, PUT)
$body = json_decode(file_get_contents('php://input'), true) ?? [];


// Charge toutes les routes depuis le dossier routes/
$routes = [];

$routeFiles = [
    'auth',
    'users',
    'passion-pages',
    'posts',
    'subscriptions',
    'likes',
    'comments',
    'feed',
    'uploads',
    'stats',
    'messages',
    'notifications',
    'search'
];

foreach ($routeFiles as $routeFile) {
    $routePath = __DIR__ . '/routes/' . $routeFile . '.php';
    if (file_exists($routePath)) {
        $fileRoutes = require_once $routePath;
        $routes = array_merge($routes, $fileRoutes);
    }
}

// Route de test - affiche la liste des endpoints
if ($uri === '/' && $method === 'GET') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'API Loom - Fonctionnelle ✓',
        'version' => '1.0.0',
        'endpoints' => [
            'Auth' => ['POST /auth/register', 'POST /auth/login', 'GET /auth/me'],
            'Users' => ['GET /users/{id}', 'PUT /users/{id}', 'PUT /users/{id}/password', 'DELETE /users/{id}', 'GET /users/{id}/passion-pages'],
            'Passions' => ['GET /passion-pages', 'GET /passion-pages/my', 'GET /passion-pages/{id}', 'POST /passion-pages', 'PUT /passion-pages/{id}', 'DELETE /passion-pages/{id}'],
            'Posts' => ['GET /posts/{passionPageId}', 'POST /posts', 'PUT /posts/{id}', 'DELETE /posts/{id}'],
            'Subscriptions' => ['GET /subscriptions/my', 'POST /subscriptions', 'DELETE /subscriptions/{passionPageId}'],
            'Likes' => ['POST /likes', 'DELETE /likes/{postId}'],
            'Comments' => ['GET /posts/{postId}/comments', 'POST /comments', 'PUT /comments/{id}', 'DELETE /comments/{id}'],
            'Feed' => ['GET /feed', 'GET /feed/mine', 'GET /feed/explore'],
            'Uploads' => ['POST /uploads/image', 'POST /uploads/avatar'],
            'Stats' => ['GET /stats/public', 'GET /stats/private', 'GET /stats/private/details'],
            'Messages' => ['GET /messages/conversations', 'GET /messages/unread-count', 'GET /messages/with/{userId}', 'POST /messages'],
            'Notifications' => ['GET /notifications', 'GET /notifications/unread-count', 'POST /notifications/read', 'DELETE /notifications/{id}'],
            'Search' => ['GET /search?q=...']
        ]
    ]);
    exit();
}

// Parcourt toutes les routes pour trouver une correspondance
$routeFound = false;
foreach ($routes as $route) {
    if (preg_match($route['pattern'], $method . ' ' . $uri, $matches)) {
        $routeFound = true;
        array_shift($matches); // Enlève le match complet
        call_user_func_array($route['handler'], $matches);
        break;
    }
}

// Si aucune route ne correspond, retourne une erreur 404
if (!$routeFound) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Route non trouvée : ' . $method . ' ' . $uri,
        'hint' => 'Consultez GET / pour la liste des endpoints disponibles'
    ]);
}
