import { ref } from "vue";
import { fetchUnreadCount } from "../services/message.service";
import { useAuthToken } from "./useAuth";

/**
 * État global du nombre de messages non lus.
 * Partagé entre la navbar et la sidebar du fil (pastille de notification),
 * et rafraîchi périodiquement (polling léger).
 */
const unreadCount = ref(0);
let intervalId: number | null = null;

/**
 * Expose le compteur réactif de non-lus (lecture seule à l'usage).
 */
export function useUnreadMessages() {
  return unreadCount;
}

/**
 * Interroge l'API pour mettre à jour le compteur. Silencieux en cas d'erreur.
 */
export async function refreshUnreadCount(): Promise<void> {
  const token = useAuthToken().value;
  if (!token) {
    unreadCount.value = 0;
    return;
  }
  try {
    const result = await fetchUnreadCount(token);
    unreadCount.value = result.count;
  } catch {
    // On n'interrompt pas l'UI si le compteur échoue (réseau, etc.)
  }
}

/**
 * Démarre le polling du compteur (toutes les 5 s). Idempotent.
 */
export function startUnreadPolling(): void {
  if (intervalId !== null) return;
  refreshUnreadCount();
  intervalId = window.setInterval(refreshUnreadCount, 5000);
}

/**
 * Arrête le polling et remet le compteur à zéro.
 */
export function stopUnreadPolling(): void {
  if (intervalId !== null) {
    clearInterval(intervalId);
    intervalId = null;
  }
  unreadCount.value = 0;
}
