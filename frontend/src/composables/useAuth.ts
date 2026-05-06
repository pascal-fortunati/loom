import { computed, ref } from "vue";
import { fetchMe, loginUser, registerUser } from "../services/auth.service";

/**
 * État global minimal d'authentification pour Loom.
 * Ce module évite de dupliquer la logique entre navbar et pages auth.
 */
const token = ref<string | null>(localStorage.getItem("loom-token"));
const username = ref<string>(localStorage.getItem("loom-username") || "Invite");
const avatar = ref<string>(localStorage.getItem("loom-avatar") || "");

export const isAuthenticated = computed(() => Boolean(token.value));

/**
 * Connecte un utilisateur et persiste sa session locale.
 */
export async function login(email: string, password: string): Promise<void> {
  const data = await loginUser(email, password);
  token.value = data.token;
  username.value = data.username;
  avatar.value = data.avatar || "";
  localStorage.setItem("loom-token", data.token);
  localStorage.setItem("loom-username", data.username);
  localStorage.setItem("loom-avatar", avatar.value);
}

/**
 * Inscrit un utilisateur puis ouvre sa session.
 */
export async function register(
  userName: string,
  email: string,
  password: string,
): Promise<void> {
  const data = await registerUser(userName, email, password);
  token.value = data.token;
  username.value = data.username;
  avatar.value = data.avatar || "";
  localStorage.setItem("loom-token", data.token);
  localStorage.setItem("loom-username", data.username);
  localStorage.setItem("loom-avatar", avatar.value);
}

/**
 * Déconnecte l'utilisateur localement.
 */
export function logout(): void {
  token.value = null;
  username.value = "Invite";
  avatar.value = "";
  localStorage.removeItem("loom-token");
  localStorage.removeItem("loom-username");
  localStorage.removeItem("loom-avatar");
}

/**
 * Expose le nom utilisateur courant.
 */
export function useCurrentUsername() {
  return username;
}

/**
 * Expose l'avatar courant de l'utilisateur connecté.
 */
export function useCurrentAvatar() {
  return avatar;
}

/**
 * Expose le token courant (lecture seule).
 */
export function useAuthToken() {
  return token;
}

/**
 * Recharge le profil depuis l'API pour fiabiliser l'état local.
 */
export async function hydrateAuthUser(): Promise<void> {
  if (!token.value) return;
  try {
    const me = await fetchMe(token.value);
    username.value = me.username;
    avatar.value = me.avatar || "";
    localStorage.setItem("loom-username", me.username);
    localStorage.setItem("loom-avatar", avatar.value);
  } catch {
    logout();
  }
}
