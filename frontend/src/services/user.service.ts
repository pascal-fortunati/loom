import { apiRequest } from "./api.service";
import type { PassionPage } from "./passion.service";

/**
 * Structure de profil utilisateur.
 */
export interface UserProfile {
  id: number;
  username: string;
  email?: string;
  avatar: string | null;
  bio: string | null;
  profile_visibility?: "public" | "followers" | "private";
  is_profile_locked?: boolean;
  is_owner?: boolean;
  is_follower?: boolean;
  created_at?: string;
}

/**
 * Payload de mise à jour du profil.
 */
export interface UpdateProfilePayload {
  bio?: string;
  avatar?: string;
  profile_visibility?: "public" | "followers" | "private";
}

/**
 * Payload de changement de mot de passe.
 */
export interface UpdatePasswordPayload {
  current_password: string;
  new_password: string;
}

/**
 * Récupère le profil d'un utilisateur via son ID.
 */
export async function fetchUserProfile(
  userId: number,
  token?: string,
): Promise<UserProfile> {
  return apiRequest<UserProfile>(`/users/${userId}`, { method: "GET" }, token);
}

/**
 * Met à jour le profil de l'utilisateur connecté.
 */
export async function updateUserProfile(
  token: string,
  userId: number,
  payload: UpdateProfilePayload,
): Promise<UserProfile> {
  return apiRequest<UserProfile>(
    `/users/${userId}`,
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
    token,
  );
}

/**
 * Met à jour le mot de passe de l'utilisateur connecté.
 */
export async function updateUserPassword(
  token: string,
  userId: number,
  payload: UpdatePasswordPayload,
): Promise<void> {
  await apiRequest(
    `/users/${userId}/password`,
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
    token,
  );
}

/**
 * Supprime définitivement le compte utilisateur connecté.
 */
export async function deleteUserAccount(
  token: string,
  userId: number,
  password: string,
): Promise<void> {
  await apiRequest(
    `/users/${userId}`,
    {
      method: "DELETE",
      body: JSON.stringify({ password }),
    },
    token,
  );
}

/**
 * Récupère les passions publiques d'un utilisateur.
 */
export async function fetchUserPublicPassions(
  userId: number,
  token?: string,
): Promise<PassionPage[]> {
  const ts = Date.now();
  return apiRequest<PassionPage[]>(
    `/users/${userId}/passion-pages?_ts=${ts}`,
    { method: "GET" },
    token,
  );
}
