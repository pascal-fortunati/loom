<?php

// CONTRÔLEUR - UPLOADS
class UploadController
{
    /**
     * Endpoint : POST /uploads/image
     * Upload une image pour un post et retourne son URL publique.
     *
     * Requête attendue (multipart/form-data):
     * - image: fichier image (jpeg, png, webp, gif), max 5 Mo
     */
    public function uploadPostImage()
    {
        // Vérifie l'authentification pour éviter les uploads anonymes.
        AuthMiddleware::authenticate();

        if (!isset($_FILES['image'])) {
            Response::error('Aucun fichier image reçu', 400);
        }

        $file = $_FILES['image'];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Erreur lors de l\'upload du fichier', 400);
        }

        // Limite volontairement simple et pédagogique (niveau DWWM).
        $maxBytes = 5 * 1024 * 1024; // 5 Mo
        if (($file['size'] ?? 0) > $maxBytes) {
            Response::error('Image trop volumineuse (max 5 Mo)', 400);
        }

        $allowedMimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $mimeType = mime_content_type($file['tmp_name']);
        if (!isset($allowedMimeToExtension[$mimeType])) {
            Response::error('Format image non supporté (jpeg, png, webp, gif)', 400);
        }

        $uploadDir = __DIR__ . '/../uploads/posts';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            Response::error('Impossible de créer le dossier d\'upload', 500);
        }

        $fileName = 'post_' . bin2hex(random_bytes(10)) . '.' . $allowedMimeToExtension[$mimeType];
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::error('Impossible d\'enregistrer le fichier', 500);
        }

        $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
            ? $_SERVER['HTTP_X_FORWARDED_PROTO']
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'] ?? 'loom.dev';
        $publicUrl = $scheme . '://' . $host . '/backend/uploads/posts/' . $fileName;

        Response::success(
            ['image_url' => $publicUrl],
            'Image uploadée avec succès',
            201
        );
    }

    /**
     * Endpoint : POST /uploads/avatar
     * Upload un avatar utilisateur et retourne le nom du fichier + URL publique.
     *
     * Requête attendue (multipart/form-data):
     * - image: fichier image (jpeg, png, webp, gif), max 5 Mo
     */
    public function uploadAvatarImage()
    {
        // L'upload d'avatar nécessite un utilisateur connecté.
        AuthMiddleware::authenticate();

        if (!isset($_FILES['image'])) {
            Response::error('Aucun fichier image reçu', 400);
        }

        $file = $_FILES['image'];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Erreur lors de l\'upload du fichier', 400);
        }

        $maxBytes = 5 * 1024 * 1024; // 5 Mo
        if (($file['size'] ?? 0) > $maxBytes) {
            Response::error('Image trop volumineuse (max 5 Mo)', 400);
        }

        $allowedMimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $mimeType = mime_content_type($file['tmp_name']);
        if (!isset($allowedMimeToExtension[$mimeType])) {
            Response::error('Format image non supporté (jpeg, png, webp, gif)', 400);
        }

        $uploadDir = __DIR__ . '/../uploads/avatars';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            Response::error('Impossible de créer le dossier d\'upload avatar', 500);
        }

        // On conserve un format simple compatible avec la validation UserController.
        $fileName = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $allowedMimeToExtension[$mimeType];
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::error('Impossible d\'enregistrer l\'avatar', 500);
        }

        $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
            ? $_SERVER['HTTP_X_FORWARDED_PROTO']
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'] ?? 'loom.dev';
        $publicUrl = $scheme . '://' . $host . '/backend/uploads/avatars/' . $fileName;

        Response::success(
            [
                'avatar' => $fileName,
                'avatar_url' => $publicUrl,
            ],
            'Avatar uploadé avec succès',
            201
        );
    }
}
