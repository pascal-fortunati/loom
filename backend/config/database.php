<?php

// ========================================
// Chargement des variables d'environnement
// ========================================
// Petit chargeur .env "maison" (sans dépendance externe, esprit DWWM).
// Les secrets (mots de passe, clé JWT) sont stockés dans backend/.env,
// qui n'est PAS versionné (voir .gitignore). Des valeurs par défaut
// permettent au projet de fonctionner en dev même sans .env.

/**
 * Charge un fichier .env (KEY=VALUE par ligne) dans l'environnement PHP.
 */
function loomLoadEnv($path)
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignore les lignes vides et les commentaires (#)
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $position = strpos($line, '=');
        if ($position === false) {
            continue;
        }
        $key = trim(substr($line, 0, $position));
        $value = trim(substr($line, $position + 1));
        // Retire d'éventuels guillemets autour de la valeur
        $value = trim($value, "\"'");
        // N'écrase pas une variable déjà définie par le serveur
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Lit une variable d'environnement avec valeur par défaut.
 */
function loomEnv($key, $default = null)
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

// Charge backend/.env s'il existe
loomLoadEnv(__DIR__ . '/../.env');

// ========================================
// Paramètres de connexion MySQL
// ========================================

define('DB_HOST', loomEnv('DB_HOST', 'localhost'));
define('DB_USER', loomEnv('DB_USER', 'root'));
define('DB_PASS', loomEnv('DB_PASS', ''));
define('DB_NAME', loomEnv('DB_NAME', 'loom'));

// ========================================
// Paramètres d'application
// ========================================

define('APP_NAME', loomEnv('APP_NAME', 'Loom'));
define('APP_ENV', loomEnv('APP_ENV', 'development'));
define('APP_DEBUG', APP_ENV === 'development');

// ========================================
// Sécurité JWT
// ========================================

// Le secret de signature ne doit JAMAIS avoir de valeur de repli en production :
// un secret présent dans le dépôt permettrait à n'importe qui de forger un token
// valide. En développement, une valeur par défaut est tolérée pour le confort.
$loomJwtSecret = loomEnv('JWT_SECRET', '');

if ($loomJwtSecret === '') {
    if (APP_ENV === 'production') {
        http_response_code(500);
        error_log('[Config] JWT_SECRET absent : refus de démarrer en production.');
        die(json_encode([
            'success' => false,
            'message' => 'Configuration serveur incomplète'
        ]));
    }
    $loomJwtSecret = 'dev_secret_a_changer_en_production';
}

define('JWT_SECRET', $loomJwtSecret);
define('JWT_ALGORITHM', loomEnv('JWT_ALGORITHM', 'HS256'));
define('JWT_EXPIRATION', (int)loomEnv('JWT_EXPIRATION', 86400));

// ========================================
// CORS - Domaines autorisés
// ========================================

define('ALLOWED_ORIGINS', array_values(array_filter(array_map(
    'trim',
    explode(',', loomEnv('ALLOWED_ORIGINS', 'http://localhost:8080'))
))));

// Force UTF-8 pour les réponses et les connexions MySQL.
ini_set('default_charset', 'UTF-8');
