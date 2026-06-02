import { apiRequest } from "./api.service";

/**
 * Service de recherche (utilisateurs + passions), interrogé côté serveur.
 */

export interface SearchUserResult {
  id: number;
  username: string;
  avatar: string | null;
}

export interface SearchPassionResult {
  id: number;
  name: string;
  username: string;
  avatar: string | null;
}

export interface SearchResults {
  users: SearchUserResult[];
  passions: SearchPassionResult[];
}

/**
 * Recherche publique d'utilisateurs et de passions.
 */
export function searchAll(query: string): Promise<SearchResults> {
  return apiRequest<SearchResults>(
    `/search?q=${encodeURIComponent(query)}`,
    { method: "GET" },
  );
}
