import { apiRequest } from "./api.service";

/**
 * Service de messagerie privée.
 * Toutes les requêtes passent par l'API PHP (loom.dev/backend) et sont
 * authentifiées par le token JWT de l'utilisateur connecté.
 */

/** Une conversation dans la liste (un interlocuteur + dernier message). */
export interface Conversation {
  user_id: number;
  username: string;
  avatar: string | null;
  last_content: string;
  last_time: string;
  last_from_me: boolean;
  unread_count: number;
}

/** Un message dans un fil de discussion. */
export interface ChatMessage {
  id: number;
  content: string;
  created_at: string;
  from_me: boolean;
}

/** Le fil complet avec un interlocuteur. */
export interface ConversationThread {
  user: { id: number; username: string; avatar: string | null };
  messages: ChatMessage[];
  can_message: boolean;
  /** Existe-t-il un abonnement commun entre les deux utilisateurs ? */
  has_link: boolean;
}

/**
 * Récupère la liste des conversations de l'utilisateur connecté.
 */
export function fetchConversations(token: string): Promise<Conversation[]> {
  return apiRequest<Conversation[]>(
    "/messages/conversations",
    { method: "GET" },
    token,
  );
}

/**
 * Récupère le fil de discussion avec un interlocuteur.
 * `afterId` permet de ne demander que les messages plus récents (polling).
 */
export function fetchConversation(
  token: string,
  otherId: number,
  afterId = 0,
): Promise<ConversationThread> {
  const query = afterId > 0 ? `?after=${afterId}` : "";
  return apiRequest<ConversationThread>(
    `/messages/with/${otherId}${query}`,
    { method: "GET" },
    token,
  );
}

/**
 * Envoie un message privé à un destinataire.
 */
export function sendMessage(
  token: string,
  recipientId: number,
  content: string,
): Promise<ChatMessage> {
  return apiRequest<ChatMessage>(
    "/messages",
    {
      method: "POST",
      body: JSON.stringify({ recipient_id: recipientId, content }),
    },
    token,
  );
}

/**
 * Récupère le nombre total de messages non lus (pastille navbar).
 */
export function fetchUnreadCount(token: string): Promise<{ count: number }> {
  return apiRequest<{ count: number }>(
    "/messages/unread-count",
    { method: "GET" },
    token,
  );
}
