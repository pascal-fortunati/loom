<?php

// MIDDLEWARE - AUTHENTIFICATION
class AuthMiddleware
{
    /**
     * Vérifie le JWT dans les headers Authorization
     * Retourne l'utilisateur connecté ou null
     * 
     * @return array|null Le payload du JWT ou null si non valide
     */
    public static function authenticate()
    {
        // Récupère l'en-tête Authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader)) {
            Response::error('Authorization header manquant', 401);
        }

        // Extrait le token après "Bearer "
        if (strpos($authHeader, 'Bearer ') !== 0) {
            Response::error('Format Authorization invalide. Utilisez "Bearer <token>"', 401);
        }

        $token = substr($authHeader, 7);

        // Décode et valide le JWT
        $payload = JWT::decode($token);

        if ($payload === null) {
            Response::error('Token invalide ou expiré', 401);
        }

        return $payload;
    }

    /**
     * Extrait le JWT sans forcer l'authentification (optionnel)
     * Utile pour les routes publiques avec contenu personnalisé
     * 
     * @return array|null Le payload du JWT ou null
     */
    public static function authenticateOptional()
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
            return null;
        }

        $token = substr($authHeader, 7);
        return JWT::decode($token);
    }
}
