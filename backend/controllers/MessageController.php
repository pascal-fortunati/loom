<?php

// CONTRÔLEUR - MESSAGES PRIVÉS
class MessageController
{
    private $messageModel;

    public function __construct()
    {
        $this->messageModel = new MessageModel();
    }

    /**
     * Endpoint : GET /messages/conversations
     * Liste les conversations de l'utilisateur connecté (dernier message + non lus).
     */
    public function getConversations()
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];

        $conversations = $this->messageModel->getConversations($userId);

        // Normalise les types pour le front
        $conversations = array_map(function ($conv) use ($userId) {
            return [
                'user_id' => (int)$conv['user_id'],
                'username' => $conv['username'],
                'avatar' => $conv['avatar'],
                'last_content' => $conv['last_content'],
                'last_time' => $conv['last_time'],
                'last_from_me' => (int)$conv['last_sender_id'] === $userId,
                'unread_count' => (int)$conv['unread_count'],
            ];
        }, $conversations);

        Response::success($conversations, 'Conversations récupérées');
    }

    /**
     * Endpoint : GET /messages/with/{userId}
     * Messages échangés avec un interlocuteur. Les messages reçus sont
     * marqués comme lus. Paramètre GET optionnel `after` pour le polling.
     */
    public function getConversation($otherId)
    {
        global $queryParams;

        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];
        $otherId = (int)$otherId;

        if ($otherId <= 0 || $otherId === $userId) {
            Response::error('Interlocuteur invalide', 400);
        }

        $other = $this->messageModel->getUserInfo($otherId);
        if ($other === null) {
            Response::error('Utilisateur introuvable', 404);
        }

        $afterId = max(0, (int)($queryParams['after'] ?? 0));

        // Récupère les messages puis marque comme lus ceux reçus de l'interlocuteur
        $messages = $this->messageModel->getConversationWith($userId, $otherId, $afterId);
        $this->messageModel->markConversationRead($userId, $otherId);

        // Formate les messages pour le front
        $messages = array_map(function ($message) use ($userId) {
            return [
                'id' => (int)$message['id'],
                'content' => $message['content'],
                'created_at' => $message['created_at'],
                'from_me' => (int)$message['sender_id'] === $userId,
            ];
        }, $messages);

        Response::success([
            'user' => [
                'id' => (int)$other['id'],
                'username' => $other['username'],
                'avatar' => $other['avatar'],
            ],
            'messages' => $messages,
            // Le front sait s'il peut (encore) écrire à cette personne
            'can_message' => $this->messageModel->canMessage($userId, $otherId),
            // Existe-t-il un abonnement commun ? (pour une note informative)
            'has_link' => $this->messageModel->hasSubscriptionLink($userId, $otherId),
        ], 'Conversation récupérée');
    }

    /**
     * Endpoint : POST /messages
     * Envoie un message privé.
     * Body : { "recipient_id": 2, "content": "Salut !" }
     */
    public function send()
    {
        global $body;

        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];

        $recipientId = isset($body['recipient_id']) ? (int)$body['recipient_id'] : 0;
        $content = isset($body['content']) ? trim((string)$body['content']) : '';

        // Validation des données côté serveur
        if ($recipientId <= 0) {
            Response::error('Destinataire invalide', 400);
        }
        if ($recipientId === $userId) {
            Response::error('Impossible de s\'envoyer un message à soi-même', 400);
        }
        if ($content === '') {
            Response::error('Le message ne peut pas être vide', 400);
        }
        if (mb_strlen($content) > 2000) {
            Response::error('Message trop long (2000 caractères maximum)', 400);
        }

        // Vérifie l'autorisation (profil public OU lien d'abonnement)
        if (!$this->messageModel->canMessage($userId, $recipientId)) {
            Response::error('Vous ne pouvez pas écrire à cet utilisateur', 403);
        }

        $message = $this->messageModel->send($userId, $recipientId, $content);

        Response::success([
            'id' => (int)$message['id'],
            'content' => $message['content'],
            'created_at' => $message['created_at'],
            'from_me' => true,
        ], 'Message envoyé', 201);
    }

    /**
     * Endpoint : GET /messages/unread-count
     * Nombre total de messages non lus (pour la pastille de la navbar).
     */
    public function getUnreadCount()
    {
        $payload = AuthMiddleware::authenticate();
        $userId = (int)$payload['user_id'];

        $count = $this->messageModel->getUnreadCount($userId);

        Response::success(['count' => $count], 'Nombre de non-lus récupéré');
    }
}
