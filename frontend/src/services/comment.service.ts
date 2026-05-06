import { apiRequest } from "./api.service";

/**
 * Structure d'un commentaire côté API.
 */
export interface ApiComment {
  id: number;
  user_id: number;
  post_id: number;
  content: string;
  created_at: string;
  username: string;
  avatar?: string | null;
}

/**
 * Récupère les commentaires d'un post.
 */
export async function fetchPostComments(postId: number): Promise<ApiComment[]> {
  return apiRequest<ApiComment[]>(
    `/posts/${postId}/comments?limit=100&offset=0`,
    { method: "GET" },
  );
}

/**
 * Crée un commentaire sur un post.
 */
export async function createComment(
  token: string,
  postId: number,
  content: string,
): Promise<ApiComment> {
  return apiRequest<ApiComment>(
    "/comments",
    {
      method: "POST",
      body: JSON.stringify({ post_id: postId, content }),
    },
    token,
  );
}

/**
 * Met à jour un commentaire existant.
 */
export async function updateComment(
  token: string,
  commentId: number,
  content: string,
): Promise<ApiComment> {
  return apiRequest<ApiComment>(
    `/comments/${commentId}`,
    {
      method: "PUT",
      body: JSON.stringify({ content }),
    },
    token,
  );
}

/**
 * Supprime un commentaire existant.
 */
export async function deleteComment(
  token: string,
  commentId: number,
): Promise<void> {
  await apiRequest<void>(`/comments/${commentId}`, { method: "DELETE" }, token);
}
