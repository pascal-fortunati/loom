<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import FeedSidebar from "../components/FeedSidebar.vue";
import StatsWidget from "../components/StatsWidget.vue";
import { isAuthenticated, logout, useAuthToken } from "../composables/useAuth";
import { fetchMe, getApiBaseUrl } from "../services/auth.service";
import {
  fetchPrivateStatsDetails,
  type PrivateStatsDetails,
} from "../services/stats.service";
import { fetchMyPassionsFeed, type ApiPost } from "../services/feed.service";
import { fetchMyPassions, fetchPublicPassions, type PassionPage } from "../services/passion.service";
import {
  fetchMySubscriptions,
  subscribeToPassion,
  unsubscribeFromPassion,
  type SubscriptionItem,
} from "../services/subscription.service";
import {
  deleteUserAccount,
  fetchUserProfile,
  updateUserProfile,
  updateUserPassword,
  type UserProfile,
} from "../services/user.service";

interface SidebarPassionItem extends PassionPage {
  colorClass: string;
  postCount: number;
}

const router = useRouter();
const token = useAuthToken();

const loading = ref(true);
const privateStatsDetails = ref<PrivateStatsDetails | null>(null);
const savingPreferences = ref(false);
const savingSecurity = ref(false);
const deletingAccount = ref(false);
const exportingData = ref(false);
const loadingDiscover = ref(false);
const loadingSubscribe = ref(false);
const apiError = ref("");
const apiMessage = ref("");

const profile = ref<UserProfile | null>(null);
const myPassions = ref<PassionPage[]>([]);
const mySubscriptions = ref<SubscriptionItem[]>([]);
const discoverPassions = ref<PassionPage[]>([]);
const myPostsCount = ref(0);
const myPostCountsByPassionId = ref<Record<number, number>>({});

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

const preferencesForm = ref({
  theme: localStorage.getItem("loom-theme") || "light",
  emailNotifications: localStorage.getItem("loom-pref-email-notifications") !== "0",
  pushNotifications: localStorage.getItem("loom-pref-push-notifications") === "1",
  profileVisibility: localStorage.getItem("loom-pref-profile-visibility") || "public",
});

const securityForm = ref({
  currentPassword: "",
  newPassword: "",
  confirmNewPassword: "",
});

const dangerForm = ref({
  password: "",
  confirmText: "",
});

/**
 * Initiales du compte pour l'avatar fallback.
 */
const initials = computed(() =>
  (profile.value?.username || "U").trim().charAt(0).toUpperCase(),
);

/**
 * Convertit une valeur avatar en URL directement exploitable.
 */
function resolveAvatarUrl(avatar: string | null | undefined): string {
  if (!avatar) return "";
  if (avatar.startsWith("http://") || avatar.startsWith("https://")) return avatar;
  return `${getApiBaseUrl()}/uploads/avatars/${avatar}`;
}

/**
 * Avatar affiché dans la page (preview prioritaire après upload).
 */
const avatarImageUrl = computed(() => resolveAvatarUrl(profile.value?.avatar));

/**
 * Libellé lisible de la visibilité choisie.
 */
const visibilityLabel = computed(() => {
  if (preferencesForm.value.profileVisibility === "followers") return "Abonnés uniquement";
  if (preferencesForm.value.profileVisibility === "private") return "Privé";
  return "Public";
});

/**
 * Stats globales affichées dans la sidebar gauche.
 */
const myFeedStats = computed(() => ({
  passions: myPassions.value.length,
  suivis: mySubscriptions.value.length,
  publications: myPostsCount.value,
}));

/**
 * Retourne une couleur stable pour les puces des passions.
 */
function pickAvatarClass(index: number): string {
  const palette = ["bg-primary", "bg-secondary", "bg-accent", "bg-info", "bg-success"];
  return palette[index % palette.length];
}

/**
 * Normalise l'identifiant de passion dans un abonnement.
 */
function getSubscriptionPassionId(item: SubscriptionItem): number {
  return Number(item.passion_page_id ?? item.id);
}

/**
 * Liste des passions utilisateur avec compteur de publications.
 */
const topPassions = computed<SidebarPassionItem[]>(() =>
  myPassions.value.map((item, index) => ({
    ...item,
    colorClass: pickAvatarClass(index),
    postCount: myPostCountsByPassionId.value[Number(item.id)] || 0,
  })),
);

/**
 * Agrège les posts par passion pour alimenter la sidebar.
 */
function buildPostCountsByPassion(apiPosts: ApiPost[]): Record<number, number> {
  return apiPosts.reduce<Record<number, number>>((accumulator, post) => {
    const passionId = Number(post.passion_page_id);
    if (!Number.isFinite(passionId)) return accumulator;
    accumulator[passionId] = (accumulator[passionId] || 0) + 1;
    return accumulator;
  }, {});
}

/**
 * Charge les passions "à découvrir" dans la colonne droite.
 */
async function loadDiscoverPassions(): Promise<void> {
  loadingDiscover.value = true;
  try {
    const publicPassions = await fetchPublicPassions(10);
    const myPassionIds = new Set(myPassions.value.map((item) => Number(item.id)));
    const followedIds = new Set(mySubscriptions.value.map((item) => getSubscriptionPassionId(item)));
    discoverPassions.value = publicPassions
      .filter((item) => !myPassionIds.has(Number(item.id)))
      .filter((item) => !followedIds.has(Number(item.id)))
      .slice(0, 6);
  } finally {
    loadingDiscover.value = false;
  }
}

/**
 * Charge toutes les données nécessaires à la page paramètres.
 */
async function loadSettingsData(): Promise<void> {
  if (!isAuthenticated.value || !token.value) {
    router.push("/login");
    return;
  }

  loading.value = true;
  apiError.value = "";
  apiMessage.value = "";

  try {
    const me = await fetchMe(token.value);
    const [profileData, passions, subscriptions, myFeedPosts, statsDetails] = await Promise.all([
      fetchUserProfile(me.id, token.value),
      fetchMyPassions(token.value),
      fetchMySubscriptions(token.value),
      fetchMyPassionsFeed(token.value, 100, 0),
      fetchPrivateStatsDetails(token.value),
    ]);

    profile.value = profileData;
    myPassions.value = passions;
    mySubscriptions.value = subscriptions;
    myPostsCount.value = myFeedPosts.length;
    privateStatsDetails.value = statsDetails;
    myPostCountsByPassionId.value = buildPostCountsByPassion(myFeedPosts);
    preferencesForm.value.profileVisibility =
      profileData.profile_visibility || preferencesForm.value.profileVisibility;
    await loadDiscoverPassions();
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Chargement des paramètres impossible.";
  } finally {
    loading.value = false;
  }
}

/**
 * Applique le thème choisi et le persiste localement.
 */
function applyTheme(theme: string): void {
  document.documentElement.setAttribute("data-theme", theme);
  localStorage.setItem("loom-theme", theme);
}

/**
 * Navigation rapide vers la page profil pour modifier l'avatar et la bio.
 */
function goToProfileEdition(): void {
  router.push("/profile");
}

/**
 * Sauvegarde les préférences de compte (hors identité publique).
 */
async function savePreferences(): Promise<void> {
  if (!profile.value || !token.value) return;

  savingPreferences.value = true;
  apiError.value = "";
  apiMessage.value = "";

  try {
    const updatedProfile = await updateUserProfile(token.value, profile.value.id, {
      profile_visibility: preferencesForm.value.profileVisibility as
        | "public"
        | "followers"
        | "private",
    });
    profile.value = updatedProfile;
    preferencesForm.value.profileVisibility =
      updatedProfile.profile_visibility || preferencesForm.value.profileVisibility;
    applyTheme(preferencesForm.value.theme);
    localStorage.setItem(
      "loom-pref-email-notifications",
      preferencesForm.value.emailNotifications ? "1" : "0",
    );
    localStorage.setItem(
      "loom-pref-push-notifications",
      preferencesForm.value.pushNotifications ? "1" : "0",
    );
    // Si le push est activé, on demande l'autorisation du navigateur (popups de bureau)
    if (
      preferencesForm.value.pushNotifications &&
      typeof Notification !== "undefined" &&
      Notification.permission === "default"
    ) {
      Notification.requestPermission();
    }
    localStorage.setItem(
      "loom-pref-profile-visibility",
      preferencesForm.value.profileVisibility,
    );
    apiMessage.value = "Préférences enregistrées avec succès.";
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Sauvegarde des préférences impossible.";
  } finally {
    savingPreferences.value = false;
  }
}

/**
 * Met à jour le mot de passe du compte utilisateur connecté.
 */
async function saveSecuritySettings(): Promise<void> {
  if (!token.value || !profile.value) return;

  if (!securityForm.value.currentPassword || !securityForm.value.newPassword) {
    apiError.value = "Renseigne le mot de passe actuel et le nouveau mot de passe.";
    return;
  }
  if (securityForm.value.newPassword.length < 6) {
    apiError.value = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    return;
  }
  if (securityForm.value.newPassword !== securityForm.value.confirmNewPassword) {
    apiError.value = "La confirmation du nouveau mot de passe ne correspond pas.";
    return;
  }

  savingSecurity.value = true;
  apiError.value = "";
  apiMessage.value = "";
  try {
    await updateUserPassword(token.value, profile.value.id, {
      current_password: securityForm.value.currentPassword,
      new_password: securityForm.value.newPassword,
    });
    securityForm.value.currentPassword = "";
    securityForm.value.newPassword = "";
    securityForm.value.confirmNewPassword = "";
    apiMessage.value = "Mot de passe modifié avec succès.";
  } catch (error) {
    apiError.value =
      error instanceof Error ? error.message : "Mise à jour du mot de passe impossible.";
  } finally {
    savingSecurity.value = false;
  }
}

/**
 * Supprime définitivement le compte après double confirmation utilisateur.
 */
async function deleteMyAccount(): Promise<void> {
  if (!token.value || !profile.value) return;

  if (dangerForm.value.confirmText.trim().toUpperCase() !== "SUPPRIMER") {
    apiError.value = "Tape SUPPRIMER pour confirmer la suppression du compte.";
    return;
  }
  if (!dangerForm.value.password) {
    apiError.value = "Renseigne ton mot de passe pour confirmer la suppression.";
    return;
  }

  deletingAccount.value = true;
  apiError.value = "";
  apiMessage.value = "";
  try {
    await deleteUserAccount(token.value, profile.value.id, dangerForm.value.password);
    logout();
    router.push("/");
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Suppression du compte impossible.";
  } finally {
    deletingAccount.value = false;
  }
}

/**
 * Exporte les données personnelles de l'utilisateur au format JSON
 * (droit d'accès et de portabilité — RGPD). Le fichier est téléchargé localement.
 */
async function exportMyData(): Promise<void> {
  if (!token.value) return;
  exportingData.value = true;
  apiError.value = "";
  apiMessage.value = "";
  try {
    const [me, passions, publications, subscriptions] = await Promise.all([
      fetchMe(token.value),
      fetchMyPassions(token.value),
      fetchMyPassionsFeed(token.value, 1000, 0),
      fetchMySubscriptions(token.value),
    ]);

    const exportPayload = {
      export_metadata: {
        application: "Loom",
        type: "Export des données personnelles (RGPD)",
        generated_at: new Date().toISOString(),
      },
      profile: me,
      passions,
      publications,
      subscriptions,
    };

    const blob = new Blob([JSON.stringify(exportPayload, null, 2)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `loom-mes-donnees-${me.username || "compte"}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    apiMessage.value = "Tes données ont été exportées (fichier JSON téléchargé).";
  } catch (error) {
    apiError.value =
      error instanceof Error ? error.message : "Export des données impossible.";
  } finally {
    exportingData.value = false;
  }
}

/**
 * Navigation rapide vers le fil personnel.
 */
function openMyFeed(): void {
  router.push({ path: "/feed", query: { mode: "mine" } });
}

/**
 * Navigation vers la page profil.
 */
function openProfilePage(): void {
  router.push("/profile");
}

/**
 * Navigation vers une passion ciblée.
 */
function openPassionDetail(passionId: number): void {
  router.push(`/passion/${passionId}`);
}

/**
 * Suit une passion depuis la colonne droite.
 */
async function followPassion(passion: PassionPage): Promise<void> {
  if (!token.value || loadingSubscribe.value) return;
  loadingSubscribe.value = true;
  try {
    await subscribeToPassion(token.value, Number(passion.id));
    await loadSettingsData();
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Abonnement impossible.";
  } finally {
    loadingSubscribe.value = false;
  }
}

/**
 * Retire un abonnement depuis la colonne droite.
 */
async function removeSubscription(passionPageId: number): Promise<void> {
  if (!token.value || loadingSubscribe.value) return;
  loadingSubscribe.value = true;
  try {
    await unsubscribeFromPassion(token.value, passionPageId);
    await loadSettingsData();
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Désabonnement impossible.";
  } finally {
    loadingSubscribe.value = false;
  }
}

onMounted(() => {
  applyTheme(preferencesForm.value.theme);
  loadSettingsData();
});
</script>

<template>
  <main class="loom-shell grid gap-4 py-4 lg:grid-cols-[220px_minmax(0,1fr)_260px]">
    <FeedSidebar
      :username="profile?.username || 'Utilisateur'"
      :user-initials="initials"
      :user-avatar-url="avatarImageUrl"
      :stats="myFeedStats"
      :passions="topPassions"
      :is-my-feed-mode="false"
      :is-profile-mode="false"
      :is-settings-mode="true"
      :active-passion-id="null"
      @open-my-feed="openMyFeed"
      @open-profile="openProfilePage"
      @open-settings="() => {}"
      @open-passion="openPassionDetail"
      @create-passion="openMyFeed"
    />

    <section class="min-w-0 space-y-3">
      <article class="loom-soft-card overflow-hidden">
        <div class="h-14 loom-brand-bg opacity-85"></div>
        <div class="px-5 pb-4 pt-2">
          <h1 class="text-lg font-semibold">Paramètres</h1>
          <p class="mt-1 text-sm text-base-content/70">
            Compte, sécurité et préférences. La page profil reste dédiée à ton identité publique.
          </p>
        </div>
      </article>

      <!-- Skeleton de chargement (cohérent avec le reste de l'app) -->
      <div v-if="loading" class="space-y-3">
        <div class="loom-soft-card space-y-4 p-5">
          <div class="h-5 w-44 animate-pulse rounded-full bg-base-300"></div>
          <div class="h-3 w-2/3 animate-pulse rounded-full bg-base-300"></div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="h-11 animate-pulse rounded-lg bg-base-300/70"></div>
            <div class="h-11 animate-pulse rounded-lg bg-base-300/70"></div>
          </div>
          <div class="h-11 w-full animate-pulse rounded-lg bg-base-300/70"></div>
        </div>
        <div class="loom-soft-card space-y-3 p-5">
          <div class="h-4 w-40 animate-pulse rounded-full bg-base-300"></div>
          <div class="h-11 w-full animate-pulse rounded-lg bg-base-300/70"></div>
          <div class="h-11 w-full animate-pulse rounded-lg bg-base-300/70"></div>
        </div>
      </div>

      <div
        v-if="apiMessage"
        class="rounded-lg border border-base-300 bg-base-100 px-3 py-2 text-xs text-base-content/70"
      >
        {{ apiMessage }}
      </div>
      <div
        v-if="apiError"
        class="rounded-lg border border-error/40 bg-error/10 px-3 py-2 text-xs text-error"
      >
        {{ apiError }}
      </div>

      <article v-if="!loading && profile" class="loom-soft-card p-5">
        <form class="space-y-4" @submit.prevent="savePreferences">
          <section class="rounded-xl border border-base-300 bg-gradient-to-br from-base-100 to-base-200/60 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="flex min-w-0 items-center gap-3">
                <div
                  v-if="avatarImageUrl"
                  class="h-14 w-14 overflow-hidden rounded-2xl border border-base-300 bg-base-200 shadow-sm"
                >
                  <img :src="avatarImageUrl" alt="Avatar du compte" class="h-full w-full object-cover" />
                </div>
                <div
                  v-else
                  class="avatar-initials h-14 w-14 rounded-2xl border border-base-300 bg-primary text-primary-content shadow-sm"
                >
                  {{ initials }}
                </div>
                <div class="min-w-0">
                  <h2 class="truncate text-base font-semibold">Compte connecté</h2>
                  <p class="truncate text-xs text-base-content/70">{{ profile.email }}</p>
                  <p class="mt-1 text-xs text-base-content/60">
                    Profil public: <span class="font-medium">{{ visibilityLabel }}</span>
                  </p>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline" @click="goToProfileEdition">
                <span class="material-symbols-outlined text-sm">person_edit</span>
                Modifier avatar / bio
              </button>
            </div>
          </section>

          <section class="space-y-3 rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm font-semibold">Préférences d'interface</h2>
              <span class="badge badge-outline badge-sm">Paramètres locaux</span>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/80">Thème DaisyUI</span>
                <select v-model="preferencesForm.theme" class="select select-bordered w-full">
                  <option v-for="theme in availableThemes" :key="theme" :value="theme">
                    {{ theme }}
                  </option>
                </select>
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/80">Visibilité du profil</span>
                <select v-model="preferencesForm.profileVisibility" class="select select-bordered w-full">
                  <option value="public">Public</option>
                  <option value="followers">Abonnés uniquement</option>
                  <option value="private">Privé</option>
                </select>
              </label>
              <div class="flex items-center gap-6 rounded-lg border border-base-300 bg-base-100 p-3">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    v-model="preferencesForm.emailNotifications"
                    type="checkbox"
                    class="toggle toggle-primary toggle-sm"
                  />
                  <span class="text-sm">Notifications e-mail</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    v-model="preferencesForm.pushNotifications"
                    type="checkbox"
                    class="toggle toggle-primary toggle-sm"
                  />
                  <span class="text-sm">Notifications push</span>
                </label>
              </div>
            </div>
            <div class="flex justify-end">
              <button :disabled="savingPreferences" class="btn btn-primary">
                <span class="material-symbols-outlined text-sm">save</span>
                {{ savingPreferences ? "Enregistrement..." : "Enregistrer les préférences" }}
              </button>
            </div>
          </section>

          <section class="space-y-2 rounded-xl border border-base-300 bg-base-100 p-4">
            <h2 class="text-sm font-semibold">Informations du compte</h2>
            <div class="grid gap-2 text-sm md:grid-cols-2">
              <p><span class="font-medium">Pseudo:</span> {{ profile.username }}</p>
              <p><span class="font-medium">Email:</span> {{ profile.email }}</p>
            </div>
          </section>

          <section class="space-y-3 rounded-xl border border-warning/40 bg-warning/5 p-4">
            <h2 class="text-sm font-semibold">Sécurité du compte</h2>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/80">Mot de passe actuel</span>
                <input
                  v-model="securityForm.currentPassword"
                  type="password"
                  class="input input-bordered w-full"
                  autocomplete="current-password"
                  placeholder="********"
                />
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/80">Nouveau mot de passe</span>
                <input
                  v-model="securityForm.newPassword"
                  type="password"
                  class="input input-bordered w-full"
                  autocomplete="new-password"
                  placeholder="Minimum 6 caractères"
                />
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/80">Confirmation</span>
                <input
                  v-model="securityForm.confirmNewPassword"
                  type="password"
                  class="input input-bordered w-full"
                  autocomplete="new-password"
                  placeholder="Répéter le mot de passe"
                />
              </label>
            </div>
            <div class="flex justify-end">
              <button
                type="button"
                class="btn btn-warning btn-outline"
                :disabled="savingSecurity"
                @click="saveSecuritySettings"
              >
                <span class="material-symbols-outlined text-sm">shield_lock</span>
                {{ savingSecurity ? "Mise à jour..." : "Changer le mot de passe" }}
              </button>
            </div>
          </section>

          <section class="space-y-3 rounded-xl border border-base-300 bg-base-100 p-4">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-base-content">
              <span class="material-symbols-outlined text-base text-primary">download</span>
              Mes données (RGPD)
            </h2>
            <p class="text-xs text-base-content/70">
              Tu peux télécharger l'ensemble de tes données personnelles (profil, passions,
              publications, abonnements) dans un fichier JSON — droit d'accès et de portabilité.
            </p>
            <div class="flex justify-end">
              <button
                type="button"
                class="btn btn-outline btn-primary"
                :disabled="exportingData"
                @click="exportMyData"
              >
                <span class="material-symbols-outlined text-sm">download</span>
                {{ exportingData ? "Préparation..." : "Télécharger mes données (JSON)" }}
              </button>
            </div>
          </section>

          <section class="space-y-3 rounded-xl border border-error/40 bg-error/5 p-4">
            <h2 class="text-sm font-semibold text-error">Zone danger</h2>
            <p class="text-xs text-base-content/70">
              Cette action est irréversible: ton profil, tes passions et tes publications seront supprimés.
            </p>
            <div class="grid gap-3 md:grid-cols-2">
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/80">Mot de passe actuel</span>
                <input
                  v-model="dangerForm.password"
                  type="password"
                  class="input input-bordered w-full"
                  autocomplete="current-password"
                  placeholder="Mot de passe de confirmation"
                />
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-base-content/80">
                  Confirmation (tape SUPPRIMER)
                </span>
                <input
                  v-model="dangerForm.confirmText"
                  type="text"
                  class="input input-bordered w-full"
                  placeholder="SUPPRIMER"
                />
              </label>
            </div>
            <div class="flex justify-end">
              <button
                type="button"
                class="btn btn-error"
                :disabled="deletingAccount"
                @click="deleteMyAccount"
              >
                <span class="material-symbols-outlined text-sm">delete_forever</span>
                {{ deletingAccount ? "Suppression..." : "Supprimer mon compte" }}
              </button>
            </div>
          </section>
        </form>
      </article>
    </section>

    <aside class="hidden space-y-3 lg:block">
      <StatsWidget
        :details="privateStatsDetails"
        :publications="myFeedStats.publications"
        :loading="loading"
      />

      <article class="loom-soft-card p-3">
        <div class="relative mb-2 overflow-hidden rounded-md border border-base-300/80 bg-base-200/70 px-2 py-1">
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/8 to-transparent"></div>
          <p class="relative text-[10px] font-semibold uppercase tracking-wider text-base-content/65">
            Passions à découvrir
          </p>
        </div>
        <div v-if="loadingDiscover" class="py-2 text-xs text-base-content/60">Chargement...</div>
        <div
          v-for="item in discoverPassions"
          :key="item.id"
          class="mb-1 flex items-center gap-2 rounded-lg p-1 hover:bg-primary/10"
        >
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/15 text-primary">
            <span class="material-symbols-outlined text-base">interests</span>
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold">{{ item.name }}</p>
            <p class="text-xs text-base-content/50">@{{ item.username || "createur" }}</p>
          </div>
          <button class="btn btn-xs cursor-pointer rounded-full btn-outline" @click="followPassion(item)">
            Suivre
          </button>
        </div>
      </article>

      <article class="loom-soft-card p-3">
        <div class="relative mb-2 overflow-hidden rounded-md border border-base-300/80 bg-base-200/70 px-2 py-1">
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/8 to-transparent"></div>
          <p class="relative text-[10px] font-semibold uppercase tracking-wider text-base-content/65">
            Mes abonnements
          </p>
        </div>
        <div v-if="mySubscriptions.length === 0" class="text-xs text-base-content/60">
          Aucun abonnement pour le moment.
        </div>
        <div
          v-for="sub in mySubscriptions.slice(0, 6)"
          :key="`sub-${sub.id}`"
          class="mb-1 flex items-center gap-2 rounded-lg p-1 hover:bg-primary/10"
        >
          <div class="avatar-initials h-8 w-8 rounded-full bg-base-300 text-xs text-base-content">
            {{ (sub.name || "P").charAt(0).toUpperCase() }}
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold">{{ sub.name }}</p>
            <p class="text-xs text-base-content/50">@{{ sub.username }}</p>
          </div>
          <button
            class="btn btn-xs cursor-pointer rounded-full btn-ghost"
            @click="removeSubscription(getSubscriptionPassionId(sub))"
          >
            Retirer
          </button>
        </div>
      </article>
    </aside>
  </main>
</template>
