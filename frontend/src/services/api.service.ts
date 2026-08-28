import { getApiBaseUrl, type ApiResponse } from "./auth.service";

/**
 * Client HTTP minimal pour l'API Loom.
 * Il gère les headers communs et les erreurs applicatives JSON.
 */
export async function apiRequest<T>(
  endpoint: string,
  options: RequestInit = {},
  token?: string,
): Promise<T> {
  const response = await fetch(`${getApiBaseUrl()}${endpoint}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
  });

  const responseText = await response.text();
  let payload: ApiResponse<T>;
  try {
    payload = JSON.parse(responseText) as ApiResponse<T>;
  } catch {
    throw new Error("Le serveur a renvoyé une réponse invalide. Consulte les logs backend.");
  }
  if (!response.ok || !payload.success) {
    throw new Error(payload.message || `Erreur HTTP ${response.status}`);
  }
  return payload.data;
}
