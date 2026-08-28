/**
 * Service d'authentification Loom.
 * Il centralise login/register/me et la normalisation des réponses backend PHP.
 */

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
}

export interface AuthPayload {
  user_id: number;
  username: string;
  email: string;
  avatar?: string | null;
  bio?: string | null;
  token: string;
}

export interface AuthUser {
  id: number;
  username: string;
  email: string;
  avatar?: string | null;
  bio?: string | null;
  created_at?: string;
}

/**
 * Détermine l'URL de base de l'API selon l'hôte qui sert le front.
 * - Sur le PC (loom.dev / localhost) : on garde https://loom.dev/backend.
 * - Depuis le réseau local (téléphone via l'IP, ex http://192.168.x.x:5173) :
 *   l'API est servie par nginx sur le port 80 de la même IP → http://IP/backend.
 *   (loom.dev ne résout pas sur le téléphone, d'où le « Failed to fetch ».)
 */
function computeApiBaseUrl(): string {
  if (typeof window === "undefined") return "/backend";
  // Le frontend Nginx reverse-proxy /backend vers le service PHP. Une URL
  // relative fonctionne en local, dans Docker et derrière un domaine HTTPS.
  return "/backend";
}

const API_BASE_URL = computeApiBaseUrl();

/**
 * Construit des headers JSON standard.
 */
function buildHeaders(token?: string): HeadersInit {
  return {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

/**
 * Connecte un utilisateur via email/mot de passe.
 */
export async function loginUser(
  email: string,
  password: string,
): Promise<AuthPayload> {
  const response = await fetch(`${API_BASE_URL}/auth/login`, {
    method: "POST",
    headers: buildHeaders(),
    body: JSON.stringify({ email, password }),
  });

  const payload: ApiResponse<AuthPayload> = await parseJsonResponse(response);
  if (!response.ok || !payload.success || !payload.data) {
    throw new Error(payload.message || "Connexion impossible");
  }
  return payload.data;
}

/**
 * Crée un nouveau compte utilisateur.
 */
export async function registerUser(
  username: string,
  email: string,
  password: string,
): Promise<AuthPayload> {
  const response = await fetch(`${API_BASE_URL}/auth/register`, {
    method: "POST",
    headers: buildHeaders(),
    body: JSON.stringify({ username, email, password }),
  });

  const payload: ApiResponse<AuthPayload> = await parseJsonResponse(response);
  if (!response.ok || !payload.success || !payload.data) {
    throw new Error(payload.message || "Inscription impossible");
  }
  return payload.data;
}

/**
 * Récupère le profil de l'utilisateur connecté depuis son token JWT.
 */
export async function fetchMe(token: string): Promise<AuthUser> {
  const response = await fetch(`${API_BASE_URL}/auth/me`, {
    method: "GET",
    headers: buildHeaders(token),
  });

  if (response.status === 401) {
    throw new Error("Token invalide ou expiré");
  }

  const payload: ApiResponse<AuthUser> = await parseJsonResponse(response);
  if (!response.ok || !payload.success || !payload.data) {
    throw new Error(payload.message || "Impossible de récupérer le profil");
  }
  return payload.data;
}

/**
 * Expose l'URL API pour les autres services.
 */
async function parseJsonResponse<T>(response: Response): Promise<ApiResponse<T>> {
  const text = await response.text();
  try {
    return JSON.parse(text) as ApiResponse<T>;
  } catch {
    throw new Error("Le serveur a renvoyé une réponse invalide. Consulte les logs backend.");
  }
}

export function getApiBaseUrl(): string {
  return API_BASE_URL;
}

/**
 * Réécrit les URLs absolues vers la base d'API courante.
 */
export function rebaseApiUrl(value: string): string {
  if (!value) return value;
  return value.replace(/https?:\/\/[^/"'\s]+\/backend/g, API_BASE_URL);
}
