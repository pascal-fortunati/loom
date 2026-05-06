import { apiRequest } from "./api.service";

/**
 * Structure d'une page de passion côté API.
 */
export interface PassionPage {
  id: number;
  user_id: number;
  name: string;
  description: string | null;
  cover_image: string | null;
  is_public: number | boolean;
  created_at: string;
  username?: string;
  avatar?: string | null;
}

/**
 * Payload utilisé pour créer une page de passion.
 */
export interface CreatePassionPayload {
  name: string;
  description: string;
  is_public: boolean;
}

/**
 * Payload utilisé pour mettre à jour une page de passion.
 */
export interface UpdatePassionPayload {
  name?: string;
  description?: string;
  is_public?: boolean;
}

/**
 * Récupère les passions de l'utilisateur connecté.
 */
export async function fetchMyPassions(token: string): Promise<PassionPage[]> {
  return apiRequest<PassionPage[]>(
    "/passion-pages/my",
    { method: "GET" },
    token,
  );
}

/**
 * Récupère des passions publiques pour la découverte.
 */
export async function fetchPublicPassions(limit = 8): Promise<PassionPage[]> {
  const ts = Date.now();
  return apiRequest<PassionPage[]>(
    `/passion-pages?limit=${limit}&offset=0&_ts=${ts}`,
    { method: "GET" },
  );
}

/**
 * Crée une nouvelle passion pour l'utilisateur connecté.
 */
export async function createPassion(
  token: string,
  payload: CreatePassionPayload,
): Promise<PassionPage> {
  return apiRequest<PassionPage>(
    "/passion-pages",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
    token,
  );
}

/**
 * Met à jour une passion existante appartenant à l'utilisateur connecté.
 */
export async function updatePassion(
  token: string,
  passionId: number,
  payload: UpdatePassionPayload,
): Promise<PassionPage> {
  return apiRequest<PassionPage>(
    `/passion-pages/${passionId}`,
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
    token,
  );
}

/**
 * Supprime une passion appartenant à l'utilisateur connecté.
 */
export async function deletePassion(
  token: string,
  passionId: number,
): Promise<void> {
  await apiRequest(`/passion-pages/${passionId}`, { method: "DELETE" }, token);
}

/**
 * Récupère le détail d'une passion par son ID.
 * Le token est optionnel: utile pour l'accès public ou connecté.
 */
export async function fetchPassionById(
  passionId: number,
  token?: string,
): Promise<PassionPage> {
  const ts = Date.now();
  return apiRequest<PassionPage>(
    `/passion-pages/${passionId}?_ts=${ts}`,
    { method: "GET" },
    token,
  );
}
