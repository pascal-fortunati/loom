import { apiRequest } from "./api.service";

/**
 * Service API des likes.
 * Il permet d'ajouter ou retirer un like sur un post.
 */

/**
 * Ajoute un like sur un post.
 */
export async function likePost(token: string, postId: number): Promise<void> {
  await apiRequest(
    "/likes",
    {
      method: "POST",
      body: JSON.stringify({ post_id: postId }),
    },
    token,
  );
}

/**
 * Retire un like d'un post.
 */
export async function unlikePost(token: string, postId: number): Promise<void> {
  await apiRequest(`/likes/${postId}`, { method: "DELETE" }, token);
}
