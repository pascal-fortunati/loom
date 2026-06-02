import { ref } from "vue";
import {
  deleteNotification,
  fetchNotifications,
  markAllNotificationsRead,
  type AppNotification,
} from "../services/notification.service";
import { useAuthToken } from "./useAuth";

/**
 * État global des notifications + polling.
 * Gère aussi les vraies notifications "push" du navigateur (API Notification),
 * activables depuis les Paramètres. Pas de service worker : les popups
 * n'apparaissent que tant qu'un onglet de Loom est ouvert (limite assumée,
 * cohérente avec une archi tout-PHP côté serveur).
 */
const notifications = ref<AppNotification[]>([]);
const unreadCount = ref(0);

let intervalId: number | null = null;
// Plus grand identifiant déjà "poppé" en notification de bureau (anti-doublon)
let lastNotifiedId = Number(localStorage.getItem("loom-last-notified-id") || 0);
// Au premier chargement, on n'affiche pas de popup pour l'historique existant
let primed = false;

export function useNotifications() {
  return notifications;
}

export function useNotificationUnreadCount() {
  return unreadCount;
}

/** Le "push" navigateur est-il activé par l'utilisateur (Paramètres) ? */
function isPushEnabled(): boolean {
  return localStorage.getItem("loom-pref-push-notifications") === "1";
}

/** Texte lisible d'une notification. */
function notificationLabel(notification: AppNotification): string {
  if (notification.type === "comment") {
    return `${notification.actor.username} a commenté ta publication`;
  }
  return `${notification.actor.username} a interagi avec ton contenu`;
}

/** Affiche une notification de bureau si autorisé. */
function showDesktopNotification(notification: AppNotification): void {
  if (
    typeof Notification === "undefined" ||
    Notification.permission !== "granted" ||
    !isPushEnabled()
  ) {
    return;
  }
  new Notification("Loom", {
    body: notificationLabel(notification),
    icon: "/favicon.svg",
  });
}

/** Met à jour le dernier id "poppé" et le persiste. */
function rememberLastNotified(list: AppNotification[]): void {
  const maxId = list.reduce((max, n) => Math.max(max, n.id), lastNotifiedId);
  lastNotifiedId = maxId;
  localStorage.setItem("loom-last-notified-id", String(maxId));
}

/** Recharge les notifications + compteur, et déclenche les popups éventuels. */
export async function refreshNotifications(): Promise<void> {
  const token = useAuthToken().value;
  if (!token) {
    notifications.value = [];
    unreadCount.value = 0;
    return;
  }
  try {
    const list = await fetchNotifications(token);
    notifications.value = list;
    unreadCount.value = list.filter((n) => !n.is_read).length;

    if (!primed) {
      // Premier passage : on mémorise sans spammer l'historique
      primed = true;
      rememberLastNotified(list);
      return;
    }

    // Popups de bureau pour les nouvelles notifications non lues
    const fresh = list
      .filter((n) => n.id > lastNotifiedId && !n.is_read)
      .sort((a, b) => a.id - b.id);
    fresh.forEach(showDesktopNotification);
    rememberLastNotified(list);
  } catch {
    // silencieux
  }
}

/** Marque toutes les notifications comme lues (local + serveur). */
export async function markNotificationsRead(): Promise<void> {
  const token = useAuthToken().value;
  if (!token || unreadCount.value === 0) return;
  unreadCount.value = 0;
  notifications.value = notifications.value.map((n) => ({ ...n, is_read: true }));
  try {
    await markAllNotificationsRead(token);
  } catch {
    // silencieux
  }
}

/** Supprime une notification (local + serveur). */
export async function removeNotification(id: number): Promise<void> {
  const token = useAuthToken().value;
  if (!token) return;
  const target = notifications.value.find((n) => n.id === id);
  notifications.value = notifications.value.filter((n) => n.id !== id);
  if (target && !target.is_read && unreadCount.value > 0) {
    unreadCount.value -= 1;
  }
  try {
    await deleteNotification(token, id);
  } catch {
    // silencieux
  }
}

/** Démarre le polling des notifications (toutes les 8 s). Idempotent. */
export function startNotificationsPolling(): void {
  if (intervalId !== null) return;
  refreshNotifications();
  intervalId = window.setInterval(refreshNotifications, 8000);
}

/** Arrête le polling et réinitialise l'état. */
export function stopNotificationsPolling(): void {
  if (intervalId !== null) {
    clearInterval(intervalId);
    intervalId = null;
  }
  notifications.value = [];
  unreadCount.value = 0;
  primed = false;
}
