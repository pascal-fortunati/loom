import { apiRequest } from "./api.service";

/**
 * Service API du fil d'actualité.
 * Il expose le feed connecté et le feed d'exploration.
 */

export interface ApiPost {
  id: number;
  passion_page_id: number;
  user_id?: number;
  content: string;
  image_url: string | null;
  created_at: string;
  passion_page_name: string;
  username: string;
  avatar?: string | null;
  likes_count: number;
  comments_count: number;
  is_liked?: number | boolean;
}

/**
 * Récupère les posts publics du endpoint `/feed/explore`.
 * On utilise un endpoint public pour afficher du contenu même sans connexion.
 */
export async function fetchExplorePosts(
  limit = 20,
  offset = 0,
): Promise<ApiPost[]> {
  return apiRequest<ApiPost[]>(
    `/feed/explore?limit=${limit}&offset=${offset}`,
    {
      method: "GET",
    },
  );
}

/**
 * Récupère le fil personnalisé de l'utilisateur authentifié.
 */
export async function fetchMyFeed(
  token: string,
  limit = 30,
  offset = 0,
): Promise<ApiPost[]> {
  const ts = Date.now();
  return apiRequest<ApiPost[]>(
    `/feed?limit=${limit}&offset=${offset}&_ts=${ts}`,
    { method: "GET" },
    token,
  );
}

/**
 * Récupère le fil personnel de l'utilisateur connecté.
 * Ce flux contient uniquement les publications créées dans ses propres passions.
 */
export async function fetchMyPassionsFeed(
  token: string,
  limit = 30,
  offset = 0,
): Promise<ApiPost[]> {
  const ts = Date.now();
  return apiRequest<ApiPost[]>(
    `/feed/mine?limit=${limit}&offset=${offset}&_ts=${ts}`,
    { method: "GET" },
    token,
  );
}
