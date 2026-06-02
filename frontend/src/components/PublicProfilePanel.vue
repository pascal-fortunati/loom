<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { isAuthenticated, useAuthToken } from "../composables/useAuth";
import { getApiBaseUrl } from "../services/auth.service";
import type { ApiPost } from "../services/feed.service";
import { fetchPostsByPassion } from "../services/post.service";
import type { PassionPage } from "../services/passion.service";
import {
  fetchMySubscriptions,
  subscribeToPassion,
  unsubscribeFromPassion,
} from "../services/subscription.service";
import {
  fetchUserProfile,
  fetchUserPublicPassions,
  type UserProfile,
} from "../services/user.service";

interface PassionWithRecentPosts extends PassionPage {
  recentPosts: ApiPost[];
}

const props = defineProps<{
  userId: number;
}>();

const emit = defineEmits<{
  (event: "close"): void;
  (event: "open-passion", passionId: number): void;
  (event: "open-private-profile"): void;
  (event: "open-settings"): void;
}>();

const token = useAuthToken();
const router = useRouter();

const loading = ref(false);
const loadingSubscriptions = ref(false);
const loadingPassionActionId = ref<number | null>(null);
const apiError = ref("");
const apiMessage = ref("");

const profile = ref<UserProfile | null>(null);
const passions = ref<PassionWithRecentPosts[]>([]);
const subscribedPassionIds = ref<number[]>([]);
const activePassion = ref<PassionWithRecentPosts | null>(null);

/**
 * Résout une valeur d'avatar en URL HTTP exploitable.
 */
function resolveAvatarUrl(avatar: string | null | undefined): string {
  if (!avatar) return "";
  if (avatar.startsWith("http://") || avatar.startsWith("https://")) return avatar;
  return `${getApiBaseUrl()}/uploads/avatars/${avatar}`;
}

/**
 * Construit les initiales fallback pour l'avatar.
 */
const profileInitials = computed(() =>
  (profile.value?.username || "U").trim().charAt(0).toUpperCase(),
);

/**
 * Retourne l'URL avatar du profil consulté.
 */
const profileAvatarUrl = computed(() => resolveAvatarUrl(profile.value?.avatar));

/**
 * Étiquette de visibilité lisible dans l'UI.
 */
const visibilityLabel = computed(() => {
  if (profile.value?.profile_visibility === "followers") return "Abonnés";
  if (profile.value?.profile_visibility === "private") return "Privé";
  return "Public";
});

/**
 * Indique si le profil est verrouillé pour le visiteur.
 */
const isProfileLocked = computed(() => Boolean(profile.value?.is_profile_locked));

/**
 * Indique si le profil consulté est celui du compte connecté.
 */
const isOwner = computed(() => Boolean(profile.value?.is_owner));

/**
 * Extrait un texte brut depuis un HTML pour les aperçus article.
 */
function extractPlainTextFromHtml(rawHtml: string): string {
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(rawHtml || "", "text/html");
  return (documentFragment.body.textContent || "").trim();
}

/**
 * Décode le contenu encodé d'une publication.
 */
function decodePostContent(rawContent: string): {
  postType: "photo" | "video" | "article";
  content: string;
  articleTitle?: string;
} {
  if (rawContent.startsWith("__LOOM_VIDEO__")) {
    try {
      const parsed = JSON.parse(rawContent.replace("__LOOM_VIDEO__", ""));
      return {
        postType: "video",
        content: String(parsed.text ?? ""),
      };
    } catch {
      return { postType: "video", content: rawContent.replace("__LOOM_VIDEO__", "") };
    }
  }

  if (rawContent.startsWith("__LOOM_ARTICLE__")) {
    try {
      const parsed = JSON.parse(rawContent.replace("__LOOM_ARTICLE__", ""));
      return {
        postType: "article",
        articleTitle: String(parsed.title ?? "Article"),
        content: extractPlainTextFromHtml(String(parsed.body ?? "")),
      };
    } catch {
      return {
        postType: "article",
        content: extractPlainTextFromHtml(rawContent.replace("__LOOM_ARTICLE__", "")),
      };
    }
  }

  return { postType: "photo", content: rawContent };
}

/**
 * Construit un aperçu texte lisible pour une publication.
 */
function buildPostPreview(post: ApiPost): string {
  const decoded = decodePostContent(post.content || "");
  const baseText = decoded.content || "";
  if (decoded.postType === "article" && decoded.articleTitle) {
    return `${decoded.articleTitle} · ${baseText.slice(0, 110)}`.trim();
  }
  if (baseText.length <= 140) return baseText;
  return `${baseText.slice(0, 140).trimEnd()}...`;
}

/**
 * Formate une date en libellé court pour les cartes.
 */
function formatTimeLabel(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Maintenant";
  const diffMin = Math.floor((Date.now() - date.getTime()) / 60000);
  if (diffMin < 60) return `${Math.max(1, diffMin)} min`;
  const diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) return `${diffHour} h`;
  return `${Math.floor(diffHour / 24)} j`;
}

/**
 * Vérifie si la passion est déjà suivie.
 */
function isPassionFollowed(passionId: number): boolean {
  return subscribedPassionIds.value.includes(Number(passionId));
}

/**
 * Charge les abonnements du compte connecté pour gérer les CTA Suivre.
 */
async function loadMySubscriptions(): Promise<void> {
  if (!token.value || !isAuthenticated.value) {
    subscribedPassionIds.value = [];
    return;
  }

  loadingSubscriptions.value = true;
  try {
    const subscriptions = await fetchMySubscriptions(token.value);
    subscribedPassionIds.value = subscriptions
      .map((item) => Number(item.passion_page_id ?? item.id))
      .filter((value) => Number.isFinite(value));
  } finally {
    loadingSubscriptions.value = false;
  }
}

/**
 * Recharge complètement les données du profil public affiché.
 */
async function loadPublicProfilePanel(): Promise<void> {
  if (!props.userId) return;

  loading.value = true;
  apiError.value = "";
  apiMessage.value = "";
  passions.value = [];
  activePassion.value = null;

  try {
    const profileData = await fetchUserProfile(props.userId, token.value || undefined);
    profile.value = profileData;

    await loadMySubscriptions();

    if (profileData.is_profile_locked) {
      apiMessage.value =
        "Ce profil est protégé. Connecte-toi avec un compte autorisé pour voir le contenu.";
      return;
    }

    const publicPassions = await fetchUserPublicPassions(props.userId, token.value || undefined);
    const passionsWithPosts = await Promise.all(
      publicPassions.map(async (item) => {
        const recentPosts = await fetchPostsByPassion(item.id, 3, 0, token.value || undefined);
        return {
          ...item,
          recentPosts,
        } as PassionWithRecentPosts;
      }),
    );

    passions.value = passionsWithPosts;
    if (passionsWithPosts.length > 0) {
      activePassion.value = passionsWithPosts[0];
    }
  } catch (error) {
    apiError.value =
      error instanceof Error ? error.message : "Chargement du profil public impossible.";
  } finally {
    loading.value = false;
  }
}

/**
 * Suivre / ne plus suivre une passion depuis le panneau profil.
 */
async function toggleFollowPassion(passionId: number): Promise<void> {
  if (!token.value || !isAuthenticated.value) {
    apiError.value = "Connecte-toi pour suivre une passion.";
    return;
  }

  loadingPassionActionId.value = passionId;
  apiError.value = "";
  try {
    if (isPassionFollowed(passionId)) {
      await unsubscribeFromPassion(token.value, passionId);
      subscribedPassionIds.value = subscribedPassionIds.value.filter((id) => id !== passionId);
      apiMessage.value = "Abonnement retiré.";
    } else {
      await subscribeToPassion(token.value, passionId);
      subscribedPassionIds.value.push(passionId);
      apiMessage.value = "Passion suivie avec succès.";
    }
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Action abonnement impossible.";
  } finally {
    loadingPassionActionId.value = null;
  }
}

/**
 * Active une passion dans la colonne de gauche.
 */
function selectPassion(passion: PassionWithRecentPosts): void {
  activePassion.value = passion;
}

onMounted(() => {
  loadPublicProfilePanel();
});

watch(
  () => props.userId,
  () => {
    loadPublicProfilePanel();
  },
);
</script>

<template>
  <div class="space-y-3">
    <article class="loom-soft-card overflow-hidden">
      <div class="relative h-14 loom-brand-bg opacity-85">
        <div class="absolute inset-0 bg-gradient-to-r from-base-content/12 to-transparent"></div>
      </div>
      <div class="px-5 pb-5 pt-2">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex min-w-0 items-start gap-3">
            <div
              v-if="profileAvatarUrl"
              class="h-16 w-16 overflow-hidden rounded-2xl border-2 border-base-100 bg-base-200 shadow-sm"
            >
              <img :src="profileAvatarUrl" alt="Avatar profil public" class="h-full w-full object-cover" />
            </div>
            <div
              v-else
              class="avatar-initials h-16 w-16 rounded-2xl border-2 border-base-100 bg-primary text-primary-content shadow-sm"
            >
              {{ profileInitials }}
            </div>
            <div class="min-w-0">
              <h2 class="truncate text-xl font-semibold">
                {{ profile?.username || "Profil public" }}
              </h2>
              <p class="text-sm text-base-content/60">
                @{{ profile?.username || "utilisateur" }}
              </p>
              <div class="mt-2 flex flex-wrap gap-2">
                <span class="badge badge-outline badge-primary">
                  Visibilité: {{ visibilityLabel }}
                </span>
                <span v-if="profile?.is_follower" class="badge badge-outline">
                  Abonné
                </span>
                <span v-if="profile?.is_owner" class="badge badge-outline">
                  Mon compte
                </span>
              </div>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="btn btn-sm btn-outline"
              @click="emit('close')"
            >
              <span class="material-symbols-outlined text-sm">arrow_back</span>
              Retour fil
            </button>
            <button
              v-if="isAuthenticated && !isOwner"
              type="button"
              class="btn btn-sm btn-primary"
              @click="router.push(`/messages?to=${props.userId}`)"
            >
              <span class="material-symbols-outlined text-sm">chat</span>
              Message
            </button>
            <button
              v-if="isOwner"
              type="button"
              class="btn btn-sm btn-outline"
              @click="emit('open-private-profile')"
            >
              <span class="material-symbols-outlined text-sm">person</span>
              Mon profil
            </button>
            <button
              v-if="isOwner"
              type="button"
              class="btn btn-sm btn-outline"
              @click="emit('open-settings')"
            >
              <span class="material-symbols-outlined text-sm">settings</span>
              Paramètres
            </button>
          </div>
        </div>
        <p v-if="!isProfileLocked" class="mt-3 rounded-xl border border-base-300 bg-base-100 px-3 py-3 text-sm text-base-content/80">
          {{ profile?.bio || "Aucune bio publique pour le moment." }}
        </p>
        <p
          v-else
          class="mt-3 rounded-xl border border-warning/40 bg-warning/10 px-3 py-3 text-sm text-base-content/80"
        >
          Ce profil est verrouillé pour ton niveau d'accès.
        </p>
      </div>
    </article>

    <!-- Skeleton de chargement (cohérent avec le reste de l'app) -->
    <div v-if="loading" class="grid grid-cols-1 gap-3 lg:grid-cols-[250px_minmax(0,1fr)]">
      <div class="loom-soft-card space-y-2 p-3">
        <div class="h-3 w-24 animate-pulse rounded-full bg-base-300"></div>
        <div class="h-12 w-full animate-pulse rounded-lg bg-base-300/70"></div>
        <div class="h-12 w-full animate-pulse rounded-lg bg-base-300/70"></div>
      </div>
      <div class="loom-soft-card space-y-3 p-4">
        <div class="h-4 w-40 animate-pulse rounded-full bg-base-300"></div>
        <div class="h-20 w-full animate-pulse rounded-xl bg-base-300/70"></div>
        <div class="h-20 w-full animate-pulse rounded-xl bg-base-300/70"></div>
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

    <div v-if="!loading && !isProfileLocked" class="grid grid-cols-1 gap-3 lg:grid-cols-[250px_minmax(0,1fr)]">
      <article class="loom-soft-card p-3">
        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-base-content/55">
          Passions publiques
        </p>
        <div v-if="passions.length === 0" class="rounded-lg border border-base-300 bg-base-100 p-3 text-sm text-base-content/70">
          Aucune passion publique.
        </div>
        <div v-else class="space-y-2">
          <button
            v-for="item in passions"
            :key="item.id"
            type="button"
            class="flex w-full items-center gap-2 rounded-lg border px-2 py-2 text-left transition"
            :class="
              activePassion?.id === item.id
                ? 'border-primary/40 bg-primary/10'
                : 'border-base-300 bg-base-100 hover:border-primary/30 hover:bg-base-200'
            "
            @click="selectPassion(item)"
          >
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-base-200 text-base-content/60">
              <span class="material-symbols-outlined text-sm">interests</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold">{{ item.name }}</p>
              <p class="truncate text-xs text-base-content/55">{{ item.recentPosts.length }} post(s)</p>
            </div>
          </button>
        </div>
      </article>

      <article v-if="activePassion" class="loom-soft-card p-4">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
          <div>
            <h3 class="text-base font-semibold">{{ activePassion.name }}</h3>
            <p class="text-xs text-base-content/65">
              {{ activePassion.description || "Sans description." }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="!isOwner"
              type="button"
              class="btn btn-xs rounded-full"
              :class="isPassionFollowed(activePassion.id) ? 'btn-outline' : 'btn-primary'"
              :disabled="loadingPassionActionId === activePassion.id || loadingSubscriptions"
              @click="toggleFollowPassion(activePassion.id)"
            >
              {{
                loadingPassionActionId === activePassion.id
                  ? "..."
                  : isPassionFollowed(activePassion.id)
                    ? "Suivi"
                    : "Suivre"
              }}
            </button>
            <button
              type="button"
              class="btn btn-xs btn-ghost"
              @click="emit('open-passion', activePassion.id)"
            >
              Voir tout
            </button>
          </div>
        </div>

        <div v-if="activePassion.recentPosts.length === 0" class="rounded-lg border border-base-300 bg-base-100 p-3 text-sm text-base-content/70">
          Aucune publication récente.
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="post in activePassion.recentPosts"
            :key="post.id"
            class="rounded-lg border border-base-300 bg-base-100 p-2"
          >
            <p class="text-sm text-base-content/80">
              {{ buildPostPreview(post) }}
            </p>
            <p class="mt-1 text-xs text-base-content/55">
              {{ formatTimeLabel(post.created_at) }}
            </p>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>