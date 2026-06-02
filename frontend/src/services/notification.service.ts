import { apiRequest } from "./api.service";

/**
 * Service des notifications (ex: commentaire reçu sur une de tes publications).
 * Toutes les requêtes passent par l'API PHP et sont authentifiées par le JWT.
 */

export interface NotificationActor {
  id: number;
  username: string;
  avatar: string | null;
}

export interface AppNotification {
  id: number;
  type: string; // 'comment', ... (extensible)
  post_id: number | null;
  is_read: boolean;
  created_at: string;
  actor: NotificationActor;
}

/** Liste les notifications de l'utilisateur connecté. */
export function fetchNotifications(token: string): Promise<AppNotification[]> {
  return apiRequest<AppNotification[]>("/notifications", { method: "GET" }, token);
}

/** Nombre de notifications non lues (pastille de la cloche). */
export function fetchNotificationUnreadCount(
  token: string,
): Promise<{ count: number }> {
  return apiRequest<{ count: number }>(
    "/notifications/unread-count",
    { method: "GET" },
    token,
  );
}

/** Marque toutes les notifications comme lues. */
export function markAllNotificationsRead(token: string): Promise<unknown> {
  return apiRequest<unknown>("/notifications/read", { method: "POST" }, token);
}

/** Supprime une notification. */
export function deleteNotification(
  token: string,
  id: number,
): Promise<unknown> {
  return apiRequest<unknown>(`/notifications/${id}`, { method: "DELETE" }, token);
}
