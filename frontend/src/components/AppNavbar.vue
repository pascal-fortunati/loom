<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  isAuthenticated,
  logout,
  useCurrentUsername,
} from "../composables/useAuth";
import {
  startUnreadPolling,
  stopUnreadPolling,
  useUnreadMessages,
} from "../composables/useUnreadMessages";
import {
  markNotificationsRead,
  removeNotification,
  startNotificationsPolling,
  stopNotificationsPolling,
  useNotificationUnreadCount,
  useNotifications,
} from "../composables/useNotifications";
import type { AppNotification } from "../services/notification.service";
import { getApiBaseUrl } from "../services/auth.service";
import { searchAll } from "../services/search.service";

const router = useRouter();
const route = useRoute();
const currentUsername = useCurrentUsername();

/**
 * Liste de thèmes DaisyUI proposés dans la navbar
 */
const availableThemes = [
  "light",
  "dark",
  "cupcake",
  "bumblebee",
  "emerald",
  "corporate",
  "synthwave",
  "retro",
  "cyberpunk",
  "forest",
  "aqua",
  "pastel",
  "dracula",
  "nord",
  "sunset",
];

/**
 * Thème actif DaisyUI (persisté en localStorage).
 */
const selectedTheme = ref(localStorage.getItem("loom-theme") || "light");
const showSearchPanel = ref(false);
const searchQuery = ref("");
const searchInputRef = ref<HTMLInputElement | null>(null);
const searchLoading = ref(false);
const searchLoadError = ref("");

interface SearchSuggestion {
  type: "user" | "passion";
  id: number;
  title: string;
  subtitle: string;
  avatarUrl?: string;
  icon: string;
}

const searchUsers = ref<SearchSuggestion[]>([]);
const searchPassions = ref<SearchSuggestion[]>([]);
const isNavbarScrolled = ref(false);

const isHomeRoute = computed(() => route.path === "/home");
const isFeedRoute = computed(() => route.path === "/feed");
const isExploreRoute = computed(() => route.path === "/explore");
const isMessagesRoute = computed(() => route.path === "/messages");
const isLoginRoute = computed(() => route.path === "/login");
const isRegisterRoute = computed(() => route.path === "/register");

// Compteur global de messages non lus (pastille du bouton Messages)
const unreadMessages = useUnreadMessages();

// Notifications (cloche) : liste + compteur de non-lus
const notifications = useNotifications();
const notifUnread = useNotificationUnreadCount();

/**
 * Texte lisible d'une notification.
 */
function notificationLabel(actorUsername: string, type: string): string {
  if (type === "comment") return `${actorUsername} a commenté ta publication`;
  return `${actorUsername} a interagi avec ton contenu`;
}

/**
 * Libellé court "il y a X" pour une date de notification.
 */
function notificationTime(value: string): string {
  const date = new Date(value.replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return "";
  const diffMin = Math.floor((Date.now() - date.getTime()) / 60000);
  if (diffMin < 1) return "à l'instant";
  if (diffMin < 60) return `il y a ${diffMin} min`;
  const diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) return `il y a ${diffHour} h`;
  return `il y a ${Math.floor(diffHour / 24)} j`;
}

/**
 * Ouvre le menu des notifications et les marque comme lues.
 */
function openNotifications(): void {
  markNotificationsRead();
}

/**
 * Clic sur une notification : va à la publication concernée (fil "mine",
 * commentaires ouverts + post mis en évidence) puis ferme le menu.
 */
function goToNotification(notification: AppNotification): void {
  markNotificationsRead();
  if (notification.post_id) {
    router.push({
      path: "/feed",
      query: { mode: "mine", post: String(notification.post_id) },
    });
  } else {
    router.push({ path: "/feed", query: { mode: "mine" } });
  }
  // Ferme le menu déroulant (DaisyUI se ferme à la perte de focus)
  (document.activeElement as HTMLElement | null)?.blur();
}

/**
 * Classes globales de la navbar selon l'état de scroll.
 */
const navbarHeaderClass = computed(() =>
  isNavbarScrolled.value
    ? "sticky top-0 z-50 loom-navbar loom-navbar--scrolled bg-primary/90 text-primary-content backdrop-blur-xl"
    : "sticky top-0 z-50 loom-navbar bg-base-100/70 text-base-content backdrop-blur-xl",
);

/**
 * Couleur du brand dans la barre.
 */
const navbarBrandClass = computed(() =>
  // En haut : marque en couleur primary. Une fois la navbar scrollée (fond
  // coloré) : marque en noir. Le logo (masque) hérite de cette couleur.
  isNavbarScrolled.value ? "text-black" : "text-primary",
);

/**
 * Retourne les classes des boutons centraux (Fil/Explorer/Accueil).
 */
function getCenterNavButtonClass(isActive: boolean): string {
  if (isNavbarScrolled.value) {
    return isActive
      ? "!border !border-primary-content/50 !bg-primary-content/25 !text-primary-content"
      : "!border !border-transparent !text-primary-content hover:!border-primary-content/45 hover:!bg-primary-content/20 hover:!text-primary-content";
  }
  return isActive
    ? "bg-primary/15 text-primary"
    : "text-base-content hover:bg-base-200";
}

/**
 * Retourne les classes des boutons ghost en zone droite.
 */
function getRightGhostButtonClass(isActive = false): string {
  if (isNavbarScrolled.value) {
    return isActive
      ? "!border !border-primary-content/50 !bg-primary-content/25 !text-primary-content shadow-sm"
      : "!border !border-transparent !text-primary-content hover:!border-primary-content/45 hover:!bg-primary-content/20 hover:!text-primary-content";
  }
  return isActive
    ? "bg-primary/15 text-primary"
    : "text-base-content hover:bg-base-200";
}

/**
 * Retourne les classes du bouton inscription.
 */
const registerButtonClass = computed(() =>
  isNavbarScrolled.value
    ? "btn btn-sm border border-primary-content/35 bg-primary-content text-primary hover:border-primary-content hover:bg-primary-content/90"
    : "btn btn-primary btn-sm",
);

/**
 * Met à jour l'état "navbar scrollée".
 */
function updateNavbarScrolledState(): void {
  isNavbarScrolled.value = window.scrollY > 8;
}

/**
 * Ouvre le fil principal des abonnements.
 * On force `mode=subscriptions` pour éviter toute ambiguïté.
 */
function openSubscriptionsFeed(): void {
  router.push({ path: "/feed", query: { mode: "subscriptions" } });
}

/**
 * Applique et sauvegarde le thème.
 */
function changeTheme(theme: string): void {
  selectedTheme.value = theme;
  document.documentElement.setAttribute("data-theme", theme);
  localStorage.setItem("loom-theme", theme);
}

/**
 * Indique si un thème est actuellement actif.
 */
function isThemeActive(theme: string): boolean {
  return selectedTheme.value === theme;
}

/**
 * Déconnecte puis renvoie vers la connexion.
 */
function handleLogout(): void {
  logout();
  router.push("/home");
}

/**
 * Ouvre la page profil de l'utilisateur connecté.
 */
function openProfile(): void {
  router.push("/profile");
}

/**
 * Ouvre/ferme le panneau de recherche sous la navbar.
 */
async function toggleSearchPanel(): Promise<void> {
  showSearchPanel.value = !showSearchPanel.value;
  if (showSearchPanel.value) {
    await nextTick();
    searchInputRef.value?.focus();
  }
}

/**
 * Ferme le panneau de recherche.
 */
function closeSearchPanel(): void {
  showSearchPanel.value = false;
}

/**
 * Vide la saisie courante sans fermer le panneau.
 */
function clearSearchQuery(): void {
  searchQuery.value = "";
  nextTick(() => {
    searchInputRef.value?.focus();
  });
}

/**
 * Résout une URL d'avatar utilisateur.
 */
function resolveAvatarUrl(avatar: string | null | undefined): string {
  if (!avatar) return "";
  if (avatar.startsWith("http://") || avatar.startsWith("https://")) return avatar;
  return `${getApiBaseUrl()}/uploads/avatars/${avatar}`;
}

/**
 * Interroge l'API de recherche (utilisateurs + passions) côté serveur.
 */
async function runSearch(query: string): Promise<void> {
  searchLoading.value = true;
  searchLoadError.value = "";
  try {
    const results = await searchAll(query);
    searchUsers.value = results.users.map((user) => ({
      type: "user",
      id: user.id,
      title: user.username,
      subtitle: "Profil utilisateur",
      avatarUrl: resolveAvatarUrl(user.avatar),
      icon: "person",
    }));
    searchPassions.value = results.passions.map((passion) => ({
      type: "passion",
      id: passion.id,
      title: passion.name,
      subtitle: `@${passion.username || "createur"}`,
      avatarUrl: resolveAvatarUrl(passion.avatar),
      icon: "interests",
    }));
  } catch (error) {
    searchLoadError.value =
      error instanceof Error ? error.message : "Recherche impossible.";
  } finally {
    searchLoading.value = false;
  }
}

// Recherche serveur déclenchée à la frappe, avec un petit délai (debounce)
// pour ne pas envoyer une requête à chaque caractère.
let searchDebounceId: number | null = null;
watch(searchQuery, (value) => {
  const query = value.trim();
  if (searchDebounceId !== null) clearTimeout(searchDebounceId);
  if (query.length < 2) {
    searchUsers.value = [];
    searchPassions.value = [];
    searchLoading.value = false;
    searchLoadError.value = "";
    return;
  }
  searchLoading.value = true;
  searchDebounceId = window.setTimeout(() => runSearch(query), 250);
});

// Les résultats viennent déjà filtrés du serveur : on fusionne utilisateurs + passions.
const searchSuggestions = computed<SearchSuggestion[]>(() =>
  [...searchUsers.value, ...searchPassions.value].slice(0, 12),
);

/**
 * Ouvre un résultat de recherche (profil public ou page passion).
 */
function openSearchSuggestion(item: SearchSuggestion): void {
  if (item.type === "passion") {
    router.push(`/passion/${item.id}`);
    closeSearchPanel();
    return;
  }

  if (isAuthenticated.value) {
    router.push({
      path: "/feed",
      query: {
        mode: "subscriptions",
        view: "profile",
        user: String(item.id),
      },
    });
  } else {
    router.push(`/u/${item.id}`);
  }
  closeSearchPanel();
}

/**
 * Soumet la recherche avec la première suggestion disponible.
 */
function submitSearch(): void {
  const firstResult = searchSuggestions.value[0];
  if (!firstResult) return;
  openSearchSuggestion(firstResult);
}

watch(
  () => route.fullPath,
  () => {
    closeSearchPanel();
  },
);

onMounted(() => {
  updateNavbarScrolledState();
  window.addEventListener("scroll", updateNavbarScrolledState, { passive: true });
  // Démarre le suivi des messages + notifications si l'utilisateur est connecté
  if (isAuthenticated.value) {
    startUnreadPolling();
    startNotificationsPolling();
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("scroll", updateNavbarScrolledState);
  stopUnreadPolling();
  stopNotificationsPolling();
});

// Démarre/arrête le polling selon l'état de connexion
watch(isAuthenticated, (connected) => {
  if (connected) {
    startUnreadPolling();
    startNotificationsPolling();
  } else {
    stopUnreadPolling();
    stopNotificationsPolling();
  }
});

/**
 * Raccourcis fixes de la barre de navigation mobile (2 à gauche, 2 à droite).
 */
const bottomLinks = computed(() => [
  { key: "home", label: "Accueil", icon: "home", path: "/home", go: () => router.push("/home") },
  { key: "feed", label: "Fil", icon: "grid_view", path: "/feed", go: openSubscriptionsFeed },
  { key: "explore", label: "Explorer", icon: "travel_explore", path: "/explore", go: () => router.push("/explore") },
  { key: "messages", label: "Messages", icon: "chat", path: "/messages", go: () => router.push("/messages") },
]);

/**
 * Métadonnées de la page courante, affichées dans le bouton rond central
 * (indique toujours « où on se trouve », y compris Profil/Paramètres/Messages).
 */
const currentPageMeta = computed(() => {
  const path = route.path;
  if (path === "/home") return { icon: "home", label: "Accueil" };
  if (path === "/feed") return { icon: "grid_view", label: "Fil" };
  if (path === "/explore") return { icon: "travel_explore", label: "Explorer" };
  if (path === "/messages") return { icon: "chat", label: "Messages" };
  if (path === "/profile") return { icon: "person", label: "Profil" };
  if (path === "/settings") return { icon: "settings", label: "Réglages" };
  if (path.startsWith("/passion/")) return { icon: "interests", label: "Passion" };
  if (path.startsWith("/u/")) return { icon: "account_circle", label: "Profil" };
  return { icon: "favorite", label: "Loom" };
});

/** Indique si un raccourci correspond à la page courante (mise en avant). */
function isBottomLinkActive(path: string): boolean {
  return route.path === path;
}

/** Remonte en haut de la page (clic sur le bouton central). */
function scrollToTop(): void {
  window.scrollTo({ top: 0, behavior: "smooth" });
}
</script>

<template>
  <header :class="navbarHeaderClass">
    <div class="loom-shell flex h-14 items-center gap-3">
      <div
        class="flex flex-1 items-center text-[17px] font-semibold tracking-tight"
        :class="navbarBrandClass"
      >
        <button
          class="flex items-center gap-1.5"
          aria-label="Accueil Loom"
          @click="router.push('/home')"
        >
          <span class="loom-logo-mask h-6 w-6" aria-hidden="true"></span>
          Loom
        </button>
      </div>

      <nav class="hidden flex-1 items-center justify-center gap-1 md:flex">
        <button
          v-if="isAuthenticated"
          class="btn btn-sm gap-1 rounded-lg border-0 bg-transparent text-xs"
          :class="getCenterNavButtonClass(isHomeRoute)"
          @click="router.push('/home')"
        >
          <span class="material-symbols-outlined text-base">home</span>
          Accueil
        </button>
        <button
          v-if="isAuthenticated"
          class="btn btn-sm gap-1 rounded-lg border-0 bg-transparent text-xs"
          :class="getCenterNavButtonClass(isFeedRoute)"
          @click="openSubscriptionsFeed"
        >
          <span class="material-symbols-outlined text-base">grid_view</span>
          Fil
        </button>
        <button
          class="btn btn-sm gap-1 rounded-lg border-0 bg-transparent text-xs"
          :class="getCenterNavButtonClass(isExploreRoute)"
          @click="router.push('/explore')"
        >
          <span class="material-symbols-outlined text-base"
            >travel_explore</span
          >
          Explorer
        </button>
      </nav>

      <div class="flex flex-1 items-center justify-end gap-2">
        <button
          v-if="isAuthenticated"
          class="btn btn-square btn-ghost btn-sm"
          :class="getRightGhostButtonClass(showSearchPanel)"
          aria-label="Ouvrir la recherche"
          @click="toggleSearchPanel"
        >
          <span class="material-symbols-outlined text-base">search</span>
        </button>

        <div title="Changer de thème" class="dropdown dropdown-end">
          <div
            tabindex="0"
            role="button"
            class="btn btn-sm gap-1.5 px-1.5 btn-ghost"
            :class="getRightGhostButtonClass()"
            aria-label="Changer de thème"
          >
            <div
              class="bg-base-100 border-base-content/10 grid shrink-0 grid-cols-2 gap-0.5 rounded-md border p-1"
            >
              <div class="bg-base-content size-1 rounded-full"></div>
              <div class="bg-primary size-1 rounded-full"></div>
              <div class="bg-secondary size-1 rounded-full"></div>
              <div class="bg-accent size-1 rounded-full"></div>
            </div>
            <span class="hidden text-xs md:inline">{{ selectedTheme }}</span>
            <svg
              width="12"
              height="12"
              class="hidden size-2 fill-current opacity-60 sm:inline-block"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 2048 2048"
            >
              <path d="M1799 349l242 241-1017 1017L7 590l242-241 775 775 775-775z"></path>
            </svg>
          </div>

          <div
            tabindex="0"
            class="dropdown-content z-50 bg-base-200 text-base-content rounded-box mt-3 max-h-[70vh] w-60 overflow-y-auto border border-white/5 p-1 shadow-2xl max-sm:fixed max-sm:inset-x-2 max-sm:top-14 max-sm:mt-0 max-sm:w-auto"
          >
            <ul class="menu w-full">
              <li class="menu-title text-xs">Thème</li>
              <li v-for="theme in availableThemes" :key="theme">
                <button class="gap-3 px-2" @click="changeTheme(theme)">
                  <div
                    :data-theme="theme"
                    class="bg-base-100 grid shrink-0 grid-cols-2 gap-0.5 rounded-md p-1 shadow-sm"
                  >
                    <div class="bg-base-content size-1 rounded-full"></div>
                    <div class="bg-primary size-1 rounded-full"></div>
                    <div class="bg-secondary size-1 rounded-full"></div>
                    <div class="bg-accent size-1 rounded-full"></div>
                  </div>
                  <div class="w-28 truncate text-left">{{ theme }}</div>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="h-3 w-3 shrink-0"
                    :class="isThemeActive(theme) ? 'visible' : 'invisible'"
                  >
                    <path d="M20.285 2l-11.285 11.567-5.286-5.011-3.714 3.716 9 8.728 15-15.285z"></path>
                  </svg>
                </button>
              </li>
            </ul>
          </div>
        </div>

        <div v-if="isAuthenticated" class="dropdown dropdown-end">
          <button
            tabindex="0"
            class="btn btn-square btn-ghost btn-sm relative"
            :class="getRightGhostButtonClass()"
            aria-label="Notifications"
            @click="openNotifications"
          >
            <span class="material-symbols-outlined text-base">notifications</span>
            <span
              v-if="notifUnread > 0"
              class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-error-content"
            >
              {{ notifUnread > 9 ? "9+" : notifUnread }}
            </span>
          </button>
          <div
            tabindex="0"
            class="dropdown-content z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-base-300 bg-base-100 text-base-content shadow-lg max-sm:fixed max-sm:inset-x-2 max-sm:top-14 max-sm:mt-0 max-sm:w-auto"
          >
            <div class="flex items-center gap-2 border-b border-base-300 px-4 py-2.5">
              <span class="material-symbols-outlined text-primary text-base">notifications</span>
              <span class="text-sm font-bold">Notifications</span>
            </div>
            <div class="max-h-96 overflow-y-auto">
              <div
                v-if="notifications.length === 0"
                class="flex flex-col items-center gap-1 px-4 py-8 text-center text-base-content/55"
              >
                <span class="material-symbols-outlined text-3xl">notifications_off</span>
                <p class="text-sm">Aucune notification.</p>
              </div>
              <div
                v-for="notif in notifications"
                :key="notif.id"
                class="group/notif relative flex items-start gap-3 border-b border-base-200 px-3 py-2.5 transition last:border-b-0 hover:bg-base-200"
                :class="{ 'bg-primary/5': !notif.is_read }"
              >
                <button
                  type="button"
                  class="flex min-w-0 flex-1 items-start gap-3 text-left"
                  @click="goToNotification(notif)"
                >
                  <img
                    v-if="resolveAvatarUrl(notif.actor.avatar)"
                    :src="resolveAvatarUrl(notif.actor.avatar)"
                    :alt="notif.actor.username"
                    class="h-9 w-9 shrink-0 rounded-full object-cover"
                  />
                  <div
                    v-else
                    class="avatar-initials h-9 w-9 shrink-0 rounded-full bg-primary text-primary-content"
                  >
                    {{ (notif.actor.username || "?").charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm leading-snug">
                      {{ notificationLabel(notif.actor.username, notif.type) }}
                    </p>
                    <p class="text-[11px] text-base-content/50">{{ notificationTime(notif.created_at) }}</p>
                  </div>
                </button>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs btn-square shrink-0 text-base-content/40 opacity-0 transition hover:text-error group-hover/notif:opacity-100"
                  aria-label="Supprimer la notification"
                  @click.stop="removeNotification(notif.id)"
                >
                  <span class="material-symbols-outlined text-sm">close</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        <button
          v-if="isAuthenticated"
          class="btn btn-square btn-ghost btn-sm relative hidden md:inline-flex"
          :class="getRightGhostButtonClass(isMessagesRoute)"
          aria-label="Messages"
          @click="router.push('/messages')"
        >
          <span class="material-symbols-outlined text-base">chat</span>
          <span
            v-if="unreadMessages > 0"
            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-error-content"
          >
            {{ unreadMessages > 9 ? "9+" : unreadMessages }}
          </span>
        </button>

        <button
          v-if="!isAuthenticated"
          class="btn btn-ghost btn-sm"
          :class="[
            getRightGhostButtonClass(),
            { 'underline underline-offset-4': isLoginRoute },
          ]"
          @click="router.push('/login')"
        >
          Connexion
        </button>
        <button
          v-if="!isAuthenticated"
          :class="[
            registerButtonClass,
            { 'ring ring-primary/30': isRegisterRoute && !isNavbarScrolled },
          ]"
          @click="router.push('/register')"
        >
          Inscription
        </button>
        <!-- Menu compte compact (responsive : contient la navigation sur mobile) -->
        <div v-if="isAuthenticated" class="dropdown dropdown-end">
          <button
            tabindex="0"
            class="btn btn-sm gap-1 px-2"
            :class="getRightGhostButtonClass()"
            aria-label="Menu du compte"
          >
            <span class="material-symbols-outlined text-base">account_circle</span>
            <span class="hidden max-w-[140px] truncate sm:inline">{{ currentUsername }}</span>
            <span class="material-symbols-outlined text-sm">expand_more</span>
          </button>
          <ul
            tabindex="0"
            class="dropdown-content z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-base-300 bg-base-100 p-1.5 text-base-content shadow-lg"
          >
            <li class="mb-1 border-b border-base-300 px-3 py-1.5 text-xs text-base-content/55">
              Connecté : <span class="font-semibold">{{ currentUsername }}</span>
            </li>
            <li>
              <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-base-200" @click="openProfile">
                <span class="material-symbols-outlined text-base">person</span> Mon profil
              </button>
            </li>
            <li>
              <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-base-200" @click="router.push('/settings')">
                <span class="material-symbols-outlined text-base">settings</span> Paramètres
              </button>
            </li>
            <li>
              <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-error hover:bg-error/10" @click="handleLogout">
                <span class="material-symbols-outlined text-base">logout</span> Déconnexion
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <Transition name="loom-fade">
      <div
        v-if="showSearchPanel && (isAuthenticated)"
        class="fixed inset-0 top-14 z-40"
      >
        <button
          type="button"
          class="absolute inset-0 bg-black/15"
          aria-label="Fermer la recherche"
          @click="closeSearchPanel"
        ></button>
        <div class="relative bg-base-100/95 backdrop-blur">
          <div class="loom-shell py-3">
            <form class="mx-auto w-full max-w-3xl space-y-2" @submit.prevent="submitSearch">
              <label class="input input-bordered input-sm flex w-full items-center gap-2">
                <span class="material-symbols-outlined text-base text-base-content/50">search</span>
                <input
                  ref="searchInputRef"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Rechercher un utilisateur, une passion..."
                  @keydown.esc.prevent="closeSearchPanel"
                />
                <button
                  v-if="searchQuery.trim().length > 0"
                  type="button"
                  class="btn btn-ghost btn-xs btn-circle"
                  aria-label="Effacer la recherche"
                  @click="clearSearchQuery"
                >
                  <span class="material-symbols-outlined text-sm">close</span>
                </button>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs btn-circle"
                  aria-label="Fermer la recherche"
                  @click="closeSearchPanel"
                >
                  <span class="material-symbols-outlined text-sm">close</span>
                </button>
              </label>

              <div
                v-if="searchQuery.trim().length > 0"
                class="rounded-xl border border-base-300 bg-base-100 shadow-sm"
              >
                <div v-if="searchLoading" class="px-3 py-2 text-xs text-base-content/60">
                  Chargement des suggestions...
                </div>
                <div v-else-if="searchLoadError" class="px-3 py-2 text-xs text-error">
                  {{ searchLoadError }}
                </div>
                <div
                  v-else-if="searchQuery.trim().length < 2"
                  class="px-3 py-2 text-xs text-base-content/60"
                >
                  Saisis au moins 2 caractères.
                </div>
                <div
                  v-else-if="searchSuggestions.length === 0"
                  class="px-3 py-2 text-xs text-base-content/60"
                >
                  Aucun résultat.
                </div>
                <button
                  v-for="item in searchSuggestions"
                  :key="`${item.type}-${item.id}`"
                  type="button"
                  class="flex w-full items-center gap-2 border-t border-base-300/70 px-3 py-2 text-left first:border-t-0 hover:bg-base-200/70"
                  @click="openSearchSuggestion(item)"
                >
                  <div
                    v-if="item.avatarUrl"
                    class="h-7 w-7 overflow-hidden rounded-full border border-base-300 bg-base-200"
                  >
                    <img :src="item.avatarUrl" alt="Avatar suggestion" class="h-full w-full object-cover" />
                  </div>
                  <div
                    v-else
                    class="flex h-7 w-7 items-center justify-center rounded-full border border-base-300 bg-base-200 text-base-content/60"
                  >
                    <span class="material-symbols-outlined text-sm">{{ item.icon }}</span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-base-content">{{ item.title }}</p>
                    <p class="truncate text-[11px] text-base-content/60">{{ item.subtitle }}</p>
                  </div>
                  <span
                    class="badge badge-outline badge-sm"
                    :class="item.type === 'user' ? 'badge-primary' : ''"
                  >
                    {{ item.type === "user" ? "Profil" : "Passion" }}
                  </span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </header>

  <!-- ===================== Bottom navigation (mobile) ===================== -->
  <nav
    v-if="isAuthenticated"
    class="loom-navbar fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100/90 backdrop-blur-lg md:hidden"
    aria-label="Navigation mobile"
  >
    <div class="relative mx-auto flex h-16 max-w-sm items-center justify-around px-2">
      <!-- 2 raccourcis à gauche -->
      <button
        v-for="n in bottomLinks.slice(0, 2)"
        :key="n.key"
        type="button"
        class="flex flex-1 flex-col items-center gap-0.5 transition"
        :class="isBottomLinkActive(n.path) ? 'text-primary' : 'text-base-content/55 hover:text-primary'"
        @click="n.go()"
      >
        <span class="material-symbols-outlined text-[22px]">{{ n.icon }}</span>
        <span class="text-[10px] font-medium">{{ n.label }}</span>
      </button>

      <!-- Bouton rond central : page courante (toujours affichée) -->
      <button
        type="button"
        class="-mt-9 flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-full border-4 border-base-100 bg-primary text-primary-content shadow-lg transition active:scale-95"
        :aria-label="currentPageMeta.label"
        @click="scrollToTop"
      >
        <span class="material-symbols-outlined loom-symbol-fill text-[24px]">{{ currentPageMeta.icon }}</span>
        <span class="text-[9px] font-semibold leading-none">{{ currentPageMeta.label }}</span>
      </button>

      <!-- 2 raccourcis à droite -->
      <button
        v-for="n in bottomLinks.slice(2, 4)"
        :key="n.key"
        type="button"
        class="flex flex-1 flex-col items-center gap-0.5 transition"
        :class="isBottomLinkActive(n.path) ? 'text-primary' : 'text-base-content/55 hover:text-primary'"
        @click="n.go()"
      >
        <span class="relative">
          <span class="material-symbols-outlined text-[22px]">{{ n.icon }}</span>
          <span
            v-if="n.key === 'messages' && unreadMessages > 0"
            class="absolute -right-2 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[9px] font-bold text-error-content"
          >
            {{ unreadMessages > 9 ? "9+" : unreadMessages }}
          </span>
        </span>
        <span class="text-[10px] font-medium">{{ n.label }}</span>
      </button>
    </div>
  </nav>
</template>
