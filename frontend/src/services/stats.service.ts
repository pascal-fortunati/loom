import { apiRequest } from "./api.service";

/**
 * Structure des statistiques publiques utilisées sur la Home.
 */
export interface PublicStats {
  membres_total: number;
  publications_publiques_total: number;
  passions_publiques_total: number;
}

/**
 * Structure des statistiques privées d'un utilisateur connecté.
 */
export interface PrivateStats {
  passions_total: number;
  suivis_total: number;
  publications_total: number;
}

/**
 * Structure des statistiques privées détaillées (premium UI).
 */
export interface PrivateStatsDetails {
  likes_recus_total: number;
  commentaires_recus_total: number;
  abonnes_total: number;
  passions_publiques_total: number;
  passions_privees_total: number;
}

/**
 * Récupère les statistiques globales de la plateforme.
 */
export async function fetchPublicStats(): Promise<PublicStats> {
  return apiRequest<PublicStats>("/stats/public", { method: "GET" });
}

/**
 * Récupère les statistiques privées du compte connecté.
 */
export async function fetchPrivateStats(token: string): Promise<PrivateStats> {
  return apiRequest<PrivateStats>("/stats/private", { method: "GET" }, token);
}

/**
 * Récupère les statistiques privées détaillées du compte connecté.
 */
export async function fetchPrivateStatsDetails(
  token: string,
): Promise<PrivateStatsDetails> {
  return apiRequest<PrivateStatsDetails>(
    "/stats/private/details",
    { method: "GET" },
    token,
  );
}
