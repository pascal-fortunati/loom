<?php

// CONTRÔLEUR - NOTIFICATIONS
class NotificationController
{
    private $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
    }

    /**
     * Endpoint : GET /notifications
     * Liste les notifications de l'utilisateur connecté.
     */
    public function getNotifications()
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];

        $notifications = $this->notificationModel->getForUser($userId, 30);

        // Normalise les types pour le front
        $notifications = array_map(function ($notification) {
            return [
                'id' => (int)$notification['id'],
                'type' => $notification['type'],
                'post_id' => $notification['post_id'] !== null ? (int)$notification['post_id'] : null,
                'is_read' => (int)$notification['is_read'] === 1,
                'created_at' => $notification['created_at'],
                'actor' => [
                    'id' => (int)$notification['actor_id'],
                    'username' => $notification['actor_username'],
                    'avatar' => $notification['actor_avatar'],
                ],
            ];
        }, $notifications);

        Response::success($notifications, 'Notifications récupérées');
    }

    /**
     * Endpoint : GET /notifications/unread-count
     * Nombre de notifications non lues (pastille de la cloche).
     */
    public function getUnreadCount()
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];

        $count = $this->notificationModel->getUnreadCount($userId);

        Response::success(['count' => $count], 'Nombre de notifications non lues récupéré');
    }

    /**
     * Endpoint : POST /notifications/read
     * Marque toutes les notifications comme lues.
     */
    public function markAllRead()
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];

        $this->notificationModel->markAllRead($userId);

        Response::success([], 'Notifications marquées comme lues');
    }

    /**
     * Endpoint : DELETE /notifications/{id}
     * Supprime une notification de l'utilisateur connecté.
     */
    public function delete($id)
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];
        $id = (int)$id;

        if ($id <= 0) {
            Response::error('Identifiant de notification invalide', 400);
        }

        $this->notificationModel->delete($id, $userId);

        Response::success(['id' => $id], 'Notification supprimée');
    }
}
