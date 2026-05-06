import { apiRequest } from "./api.service";

/**
 * Structure d'un abonnement utilisateur.
 */
export interface SubscriptionItem {
  subscription_id?: number;
  passion_page_id?: number;
  id: number;
  name: string;
  description: string | null;
  cover_image: string | null;
  is_public: number | boolean;
  created_at: string;
  username: string;
  avatar: string | null;
}

/**
 * Récupère les abonnements de l'utilisateur connecté.
 */
export async function fetchMySubscriptions(token: string): Promise<SubscriptionItem[]> {
  const ts = Date.now();
  return apiRequest<SubscriptionItem[]>(
    `/subscriptions/my?limit=20&offset=0&_ts=${ts}`,
    { method: "GET" },
    token,
  );
}

/**
 * Abonne l'utilisateur à une passion.
 */
export async function subscribeToPassion(token: string, passionPageId: number): Promise<void> {
  await apiRequest("/subscriptions", {
    method: "POST",
    body: JSON.stringify({ passion_page_id: passionPageId }),
  }, token);
}

/**
 * Désabonne l'utilisateur d'une passion.
 */
export async function unsubscribeFromPassion(token: string, passionPageId: number): Promise<void> {
  await apiRequest(`/subscriptions/${passionPageId}`, { method: "DELETE" }, token);
}
