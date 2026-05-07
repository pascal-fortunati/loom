import { apiRequest } from "./api.service";
import type { ApiPost } from "./feed.service";

/**
 * Payload de création d'un post.
 */
export interface CreatePostPayload {
  passion_page_id: number;
  content: string;
  image_url?: string | null;
}

/**
 * Payload de mise à jour d'un post.
 */
export interface UpdatePostPayload {
  content?: string;
  image_url?: string | null;
}

/**
 * Crée un post sur une passion appartenant à l'utilisateur connecté.
 */
export async function createPost(
  token: string,
  payload: CreatePostPayload,
): Promise<ApiPost> {
  return apiRequest<ApiPost>(
    "/posts",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
    token,
  );
}

/**
 * Récupère les posts d'une passion donnée.
 * Le token est optionnel car la route peut être publique.
 */
export async function fetchPostsByPassion(
  passionPageId: number,
  limit = 20,
  offset = 0,
  token?: string,
): Promise<ApiPost[]> {
  const ts = Date.now();
  return apiRequest<ApiPost[]>(
    `/posts/${passionPageId}?limit=${limit}&offset=${offset}&_ts=${ts}`,
    { method: "GET" },
    token,
  );
}

/**
 * Met à jour un post existant appartenant à l'utilisateur connecté.
 */
export async function updatePost(
  token: string,
  postId: number,
  payload: UpdatePostPayload,
): Promise<ApiPost> {
  return apiRequest<ApiPost>(
    `/posts/${postId}`,
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
    token,
  );
}

/**
 * Supprime un post existant appartenant à l'utilisateur connecté.
 */
export async function deletePost(token: string, postId: number): Promise<{ id: number }> {
  return apiRequest<{ id: number }>(
    `/posts/${postId}`,
    {
      method: "DELETE",
    },
    token,
  );
}
