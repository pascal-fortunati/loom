<?php

// UTILS - FONCTIONS GÉNÉRALES
class JWT
{
    /**
     * Crée un JWT signé
     * 
     * @param array $payload Les données à encoder (ex: ['user_id' => 1])
     * @param int $expiryTime Durée de validité en secondes
     * @return string Le token JWT
     */
    public static function encode($payload, $expiryTime = null)
    {
        if ($expiryTime === null) {
            // Utilise la constante de configuration centrale pour la durée de vie du token.
            $expiryTime = JWT_EXPIRATION;
        }

        // En-tête du JWT
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        // Ajoute le timestamp d'expiration
        $payload['exp'] = time() + $expiryTime;

        // Encode en Base64URL
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        // Crée la signature
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        // Retourne le JWT complet
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Valide et décode un JWT
     * 
     * @param string $token Le token JWT
     * @return array|null Le payload si valide, null sinon
     */
    public static function decode($token)
    {
        // Divise le token en 3 parties
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        // Vérifie la signature
        $expectedSignature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, JWT_SECRET, true);
        $expectedSignatureEncoded = self::base64UrlEncode($expectedSignature);

        if ($signatureEncoded !== $expectedSignatureEncoded) {
            return null;
        }

        // Décode le payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        // Vérifie l'expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Encode une chaîne en Base64URL (sécurisée pour URLs)
     * 
     * @param string $data Les données à encoder
     * @return string La chaîne encodée
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Décode une chaîne Base64URL
     * 
     * @param string $data Les données à décoder
     * @return string La chaîne décodée
     */
    private static function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

/**
 * UTILITAIRES POUR MOTS DE PASSE
 * 
 * Hachage sécurisé avec bcrypt (password_hash)
 */
class PasswordHelper
{
    /**
     * Hache un mot de passe avec bcrypt
     * 
     * @param string $password Le mot de passe en clair
     * @return string Le hash du mot de passe
     */
    public static function hash($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Vérifie qu'un mot de passe correspond à son hash
     * 
     * @param string $password Le mot de passe en clair
     * @param string $hash Le hash stocké en BDD
     * @return bool true si le mot de passe est correct
     */
    public static function verify($password, $hash)
    {
        return password_verify($password, $hash);
    }
}

/**
 * UTILITAIRES DE VALIDATION
 */
class Validator
{
    /**
     * Valide une adresse email
     * 
     * @param string $email L'email à valider
     * @return bool true si valide
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valide la force d'un mot de passe
     * Au minimum : 6 caractères
     * 
     * @param string $password Le mot de passe
     * @return bool true si suffisamment fort
     */
    public static function isValidPassword($password)
    {
        return strlen($password) >= 6;
    }

    /**
     * Valide l'utilisateur (username)
     * Entre 3 et 50 caractères, alphanumériques + tiret bas/tiret
     * 
     * @param string $username Le username
     * @return bool true si valide
     */
    public static function isValidUsername($username)
    {
        return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username) === 1;
    }
}

/**
 * CLASSE POUR LES RÉPONSES API
 * 
 * Standardise le format JSON retourné par l'API
 */
class Response
{
    /**
     * Retourne une réponse de succès
     * 
     * @param array $data Les données à retourner
     * @param string $message Message de succès
     * @param int $httpCode Code HTTP (défaut 200)
     */
    public static function success($data = [], $message = 'Succès', $httpCode = 200)
    {
        http_response_code($httpCode);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message
        ]);
        exit();
    }

    /**
     * Retourne une réponse d'erreur
     * 
     * @param string $message Message d'erreur
     * @param int $httpCode Code HTTP (défaut 400)
     * @param array $errors Détails supplémentaires
     */
    public static function error($message = 'Erreur', $httpCode = 400, $errors = [])
    {
        http_response_code($httpCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ]);
        exit();
    }
}

/**
 * NETTOYAGE HTML (anti-XSS) côté serveur.
 *
 * Défense en profondeur : même si le front nettoie déjà le HTML des articles,
 * on re-nettoie au moment de l'enregistrement pour que la base ne contienne
 * jamais de HTML dangereux (script, gestionnaires onclick, javascript:, etc.).
 */
class HtmlSanitizer
{
    // Balises retirées entièrement
    private static $forbiddenTags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'];

    /**
     * Nettoie une chaîne HTML et retourne une version sûre.
     */
    public static function clean($html)
    {
        if ($html === null || $html === '') {
            return '';
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // ignore les avertissements sur HTML non strict
        // On encapsule dans un <div> wrapper et on force l'UTF-8
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="loom-sanitize-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        // 1) Supprime les balises interdites
        foreach (self::$forbiddenTags as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            // Parcours à l'envers car la NodeList est "vivante"
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // 2) Supprime les attributs dangereux (on*, href/src en javascript:/data:)
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//*') as $element) {
            if (!$element->attributes) {
                continue;
            }
            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $attribute = $element->attributes->item($i);
                $name = strtolower($attribute->name);
                $value = strtolower(trim($attribute->value));
                if (strpos($name, 'on') === 0) {
                    $element->removeAttribute($attribute->name);
                    continue;
                }
                if (
                    ($name === 'href' || $name === 'src') &&
                    (strpos($value, 'javascript:') === 0 || strpos($value, 'data:') === 0)
                ) {
                    $element->removeAttribute($attribute->name);
                }
            }
        }

        // 3) Récupère le HTML interne du wrapper
        $root = $dom->getElementById('loom-sanitize-root');
        if ($root === null) {
            return '';
        }
        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $dom->saveHTML($child);
        }
        return $clean;
    }

    /**
     * Nettoie le contenu d'une publication.
     * Seuls les articles contiennent du HTML (rendu via v-html côté front) ;
     * les autres types (texte, vidéo) sont affichés en texte brut, donc sûrs.
     */
    public static function sanitizePostContent($content)
    {
        $marker = '__LOOM_ARTICLE__';
        if (strpos($content, $marker) !== 0) {
            return $content;
        }
        $json = substr($content, strlen($marker));
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['body'])) {
            $data['body'] = self::clean((string)$data['body']);
            return $marker . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $content;
    }
}

/**
 * LIMITEUR DE DÉBIT (anti-bruteforce) basé sur des fichiers.
 *
 * Simple et sans dépendance : compte les tentatives par clé (ex: IP) sur une
 * fenêtre de temps. Utilisé pour protéger la connexion contre le bruteforce.
 */
class RateLimiter
{
    /**
     * Enregistre une tentative et indique si elle est autorisée.
     *
     * @param string $key Identifiant (ex: 'login:<ip>')
     * @param int $maxAttempts Nombre max de tentatives sur la fenêtre
     * @param int $windowSeconds Durée de la fenêtre en secondes
     * @return bool true si autorisé, false si la limite est dépassée
     */
    public static function attempt($key, $maxAttempts = 5, $windowSeconds = 300)
    {
        $file = self::filePath($key);
        $now = time();
        $data = ['count' => 0, 'start' => $now];

        if (is_file($file)) {
            $decoded = json_decode(file_get_contents($file), true);
            if (is_array($decoded) && isset($decoded['count'], $decoded['start'])) {
                $data = $decoded;
            }
        }

        // Réinitialise la fenêtre si elle est expirée
        if ($now - (int)$data['start'] > $windowSeconds) {
            $data = ['count' => 0, 'start' => $now];
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return $data['count'] <= $maxAttempts;
    }

    /**
     * Réinitialise le compteur d'une clé (ex: après une connexion réussie).
     */
    public static function reset($key)
    {
        $file = self::filePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Construit le chemin du fichier de compteur (crée le dossier si besoin).
     */
    private static function filePath($key)
    {
        $dir = __DIR__ . '/../tmp/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . md5($key) . '.json';
    }
}
