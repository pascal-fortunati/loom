<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
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

const route  = useRoute();
const router = useRouter();
const token  = useAuthToken();

const loading                = ref(true);
const loadingSubscriptions   = ref(false);
const loadingPassionActionId = ref<number | null>(null);
const apiError               = ref("");
const apiMessage             = ref("");

const profile              = ref<UserProfile | null>(null);
const passions             = ref<PassionWithRecentPosts[]>([]);
const subscribedPassionIds = ref<number[]>([]);

/** Passion dont le détail est ouvert dans le drawer latéral. */
const activePassion = ref<PassionWithRecentPosts | null>(null);

const profileUserId = computed(() => {
  const rawId = Number(route.params.id);
  return Number.isFinite(rawId) && rawId > 0 ? rawId : 0;
});

function resolveAvatarUrl(avatar: string | null | undefined): string {
  if (!avatar) return "";
  if (avatar.startsWith("http://") || avatar.startsWith("https://")) return avatar;
  return `${getApiBaseUrl()}/uploads/avatars/${avatar}`;
}

const profileInitials  = computed(() => (profile.value?.username || "U").trim().charAt(0).toUpperCase());
const profileAvatarUrl = computed(() => resolveAvatarUrl(profile.value?.avatar));

const visibilityLabel = computed(() => {
  if (profile.value?.profile_visibility === "followers") return "Abonnés";
  if (profile.value?.profile_visibility === "private")   return "Privé";
  return "Public";
});
const visibilityIcon = computed(() => {
  if (profile.value?.profile_visibility === "followers") return "group";
  if (profile.value?.profile_visibility === "private")   return "lock";
  return "public";
});

const isProfileLocked = computed(() => Boolean(profile.value?.is_profile_locked));
const isOwner         = computed(() => Boolean(profile.value?.is_owner));

/** Nombre total de posts récents sur toutes les passions. */
const totalRecentPosts = computed(() =>
  passions.value.reduce((acc, p) => acc + p.recentPosts.length, 0),
);

function extractPlainTextFromHtml(rawHtml: string): string {
  const doc = new DOMParser().parseFromString(rawHtml || "", "text/html");
  return (doc.body.textContent || "").trim();
}

function decodePostContent(rawContent: string): {
  postType: "photo" | "video" | "article";
  content: string;
  articleTitle?: string;
} {
  if (rawContent.startsWith("__LOOM_VIDEO__")) {
    try { const p = JSON.parse(rawContent.replace("__LOOM_VIDEO__", "")); return { postType: "video", content: String(p.text ?? "") }; }
    catch { return { postType: "video", content: rawContent.replace("__LOOM_VIDEO__", "") }; }
  }
  if (rawContent.startsWith("__LOOM_ARTICLE__")) {
    try { const p = JSON.parse(rawContent.replace("__LOOM_ARTICLE__", "")); return { postType: "article", articleTitle: String(p.title ?? "Article"), content: extractPlainTextFromHtml(String(p.body ?? "")) }; }
    catch { return { postType: "article", content: extractPlainTextFromHtml(rawContent.replace("__LOOM_ARTICLE__", "")) }; }
  }
  return { postType: "photo", content: rawContent };
}

function buildPostPreview(post: ApiPost): string {
  const decoded  = decodePostContent(post.content || "");
  const base     = decoded.content || "";
  if (decoded.postType === "article" && decoded.articleTitle) return `${decoded.articleTitle} · ${base.slice(0, 80)}`.trim();
  return base.length <= 120 ? base : `${base.slice(0, 120).trimEnd()}...`;
}

function getPostTypeIcon(post: ApiPost): string {
  if (post.content?.startsWith("__LOOM_VIDEO__"))   return "videocam";
  if (post.content?.startsWith("__LOOM_ARTICLE__")) return "article";
  if (post.image_url)                               return "image";
  return "chat_bubble_outline";
}

function formatTimeLabel(value: string): string {
  const date     = new Date(value);
  if (Number.isNaN(date.getTime())) return "Maintenant";
  const diffMin  = Math.floor((Date.now() - date.getTime()) / 60000);
  if (diffMin < 60)  return `${Math.max(1, diffMin)} min`;
  const diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) return `${diffHour} h`;
  return `${Math.floor(diffHour / 24)} j`;
}

function isPassionFollowed(passionId: number): boolean {
  return subscribedPassionIds.value.includes(Number(passionId));
}

async function loadPublicProfilePage(): Promise<void> {
  if (!profileUserId.value) { apiError.value = "Identifiant utilisateur invalide."; return; }
  loading.value = true; apiError.value = ""; apiMessage.value = ""; passions.value = []; activePassion.value = null;
  try {
    const profileData = await fetchUserProfile(profileUserId.value, token.value || undefined);
    profile.value = profileData;
    if (isAuthenticated.value && token.value) await loadMySubscriptions();
    else subscribedPassionIds.value = [];
    if (profileData.is_profile_locked) { apiMessage.value = "Ce profil est protégé. Connecte-toi avec un compte autorisé pour voir le contenu."; return; }
    const publicPassions    = await fetchUserPublicPassions(profileUserId.value, token.value || undefined);
    const passionsWithPosts = await Promise.all(publicPassions.map(async (item) => {
      const recentPosts = await fetchPostsByPassion(item.id, 3, 0, token.value || undefined);
      return { ...item, recentPosts } as PassionWithRecentPosts;
    }));
    passions.value = passionsWithPosts;
    if (passionsWithPosts.length > 0) activePassion.value = passionsWithPosts[0];
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Chargement du profil public impossible.";
  } finally { loading.value = false; }
}

async function loadMySubscriptions(): Promise<void> {
  if (!token.value) return;
  loadingSubscriptions.value = true;
  try {
    const subs             = await fetchMySubscriptions(token.value);
    subscribedPassionIds.value = subs.map((item) => Number(item.passion_page_id ?? item.id)).filter((v) => Number.isFinite(v));
  } finally { loadingSubscriptions.value = false; }
}

async function toggleFollowPassion(passionId: number): Promise<void> {
  if (!isAuthenticated.value || !token.value) { router.push("/login"); return; }
  loadingPassionActionId.value = passionId; apiError.value = "";
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
  } finally { loadingPassionActionId.value = null; }
}

function openPassionDetail(passionId: number): void { router.push(`/passion/${passionId}`); }
function openMyPrivateProfile(): void { router.push("/profile"); }
function openMySettings(): void { router.push("/settings"); }
function selectPassion(passion: PassionWithRecentPosts): void { activePassion.value = passion; }

onMounted(() => { loadPublicProfilePage(); });
watch(() => route.params.id, () => { loadPublicProfilePage(); });
</script>

<template>
  <!--
    PublicProfileView – Loom
    Stack : Vue 3 + TypeScript + DaisyUI v5 + Tailwind v4
    Logique 100 % identique. Template refondu : avatar centré au-dessus de la cover,
    layout sidebar gauche (passions) + colonne droite (posts), composants DaisyUI natifs.
  -->
  <main class="loom-shell py-6 space-y-5">

    <!-- ══════════════════════════════════════
         SKELETON
    ═══════════════════════════════════════ -->
    <div v-if="loading" class="space-y-5">
      <div class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
        <div class="h-40 w-full animate-pulse bg-base-300" />
        <div class="flex flex-col items-center -mt-12 pb-6 space-y-3">
          <div class="h-24 w-24 animate-pulse rounded-full bg-base-300 border-4 border-base-100" />
          <div class="h-4 w-36 animate-pulse rounded-full bg-base-300" />
          <div class="h-3 w-24 animate-pulse rounded-full bg-base-300" />
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-5">
        <div class="space-y-3">
          <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-base-300" />
        </div>
        <div class="space-y-3">
          <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-xl bg-base-300" />
        </div>
      </div>
    </div>

    <template v-else>

      <!-- ══════════════════════════════════════
           CARD PROFIL — cover + avatar centré
      ═══════════════════════════════════════ -->
      <div class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">

        <!-- Cover -->
        <div class="relative h-40 bg-primary overflow-hidden">
          <svg class="absolute inset-0 h-full w-full" viewBox="0 0 900 160" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <pattern id="pp-dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
              <circle cx="1" cy="1" r="1" fill="white" opacity="0.12"/>
            </pattern>
            <rect width="100%" height="100%" fill="url(#pp-dots)"/>
            <circle cx="820" cy="80" r="140" fill="white" opacity="0.06"/>
            <circle cx="820" cy="80" r="80"  fill="white" opacity="0.05"/>
            <circle cx="50"  cy="140" r="100" fill="white" opacity="0.04"/>
            <circle cx="400" cy="-10" r="70"  fill="white" opacity="0.04"/>
          </svg>

          <!-- Actions owner (coin haut-droit) -->
          <div v-if="isOwner" class="absolute top-3 right-3 flex gap-2 z-10">
            <button type="button" class="btn btn-xs bg-primary-content/15 hover:bg-primary-content/25 text-primary-content border-0 gap-1 backdrop-blur-sm" @click="openMyPrivateProfile">
              <span class="material-symbols-outlined text-sm">person</span>
              Mon profil
            </button>
            <button type="button" class="btn btn-xs bg-primary-content/15 hover:bg-primary-content/25 text-primary-content border-0 gap-1 backdrop-blur-sm" @click="openMySettings">
              <span class="material-symbols-outlined text-sm">settings</span>
            </button>
          </div>
        </div>

        <!-- Avatar centré qui chevauche la cover — z-index élevé + bg opaque -->
        <div class="flex flex-col items-center -mt-12 pb-5 px-5 relative z-10">

          <!-- Avatar DaisyUI -->
          <div class="avatar mb-3">
            <div
              v-if="profileAvatarUrl"
              class="w-24 rounded-full ring ring-base-100 ring-offset-0 shadow-lg"
            >
              <img :src="profileAvatarUrl" alt="Avatar" />
            </div>
            <div
              v-else
              class="avatar placeholder"
            >
              <div class="w-24 rounded-full ring ring-base-100 shadow-lg bg-primary text-primary-content">
                <span class="text-3xl font-bold">{{ profileInitials }}</span>
              </div>
            </div>
          </div>

          <!-- Nom + handle -->
          <h1 class="text-xl font-bold text-base-content text-center leading-tight">
            {{ profile?.username || "Profil public" }}
          </h1>
          <p class="text-sm text-base-content/50 mt-0.5 text-center">@{{ profile?.username || "utilisateur" }}</p>

          <!-- Badges -->
          <div class="flex flex-wrap justify-center gap-2 mt-3">
            <div class="badge badge-outline gap-1 text-xs">
              <span class="material-symbols-outlined text-xs">{{ visibilityIcon }}</span>
              {{ visibilityLabel }}
            </div>
            <div v-if="profile?.is_follower" class="badge badge-primary badge-outline gap-1 text-xs">
              <span class="material-symbols-outlined text-xs">check_circle</span>
              Abonné
            </div>
            <div v-if="isOwner" class="badge badge-secondary badge-outline gap-1 text-xs">
              <span class="material-symbols-outlined text-xs">manage_accounts</span>
              Mon compte
            </div>
          </div>

          <!-- Envoyer un message (si connecté et que ce n'est pas son propre profil) -->
          <button
            v-if="isAuthenticated && !isOwner && profileUserId"
            type="button"
            class="btn btn-primary btn-sm gap-1.5 mt-4"
            @click="router.push(`/messages?to=${profileUserId}`)"
          >
            <span class="material-symbols-outlined text-base">chat</span>
            Envoyer un message
          </button>

          <!-- Bio -->
          <div class="mt-4 w-full max-w-lg text-center">
            <div v-if="isProfileLocked" class="alert alert-warning text-sm py-2 px-4">
              <span class="material-symbols-outlined text-base">lock</span>
              Ce profil est verrouillé pour ton niveau d'accès.
            </div>
            <p v-else class="text-sm text-base-content/65 leading-relaxed">
              {{ profile?.bio || "Aucune bio publique pour le moment." }}
            </p>
          </div>

          <!-- Stats -->
          <div v-if="!isProfileLocked" class="stats stats-horizontal shadow-none border border-base-300 rounded-2xl mt-5 bg-base-200 text-center">
            <div class="stat px-6 py-3">
              <div class="stat-title text-xs">Passions</div>
              <div class="stat-value text-lg text-primary">{{ passions.length }}</div>
            </div>
            <div class="stat px-6 py-3">
              <div class="stat-title text-xs">Publications</div>
              <div class="stat-value text-lg">{{ totalRecentPosts }}+</div>
            </div>
            <div class="stat px-6 py-3">
              <div class="stat-title text-xs">Abonnés</div>
              <div class="stat-value text-lg">–</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Alertes -->
      <div v-if="apiMessage" class="alert alert-info text-sm py-2.5 px-4 rounded-xl">
        <span class="material-symbols-outlined text-base">info</span>
        {{ apiMessage }}
      </div>
      <div v-if="apiError" class="alert alert-error text-sm py-2.5 px-4 rounded-xl">
        <span class="material-symbols-outlined text-base">error</span>
        {{ apiError }}
      </div>

      <!-- ══════════════════════════════════════
           LAYOUT PRINCIPAL — sidebar passions + posts
      ═══════════════════════════════════════ -->
      <div v-if="!isProfileLocked" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-5 items-start">

        <!-- ── Colonne gauche : liste des passions ── -->
        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wider text-base-content/40 px-1 mb-3">
            {{ passions.length }} passion{{ passions.length !== 1 ? 's' : '' }} publique{{ passions.length !== 1 ? 's' : '' }}
          </p>

          <div v-if="passions.length === 0" class="rounded-xl border border-base-300 bg-base-100 p-6 text-center space-y-2">
            <span class="material-symbols-outlined text-3xl text-base-content/20">interests</span>
            <p class="text-sm text-base-content/50">Aucune passion publique.</p>
          </div>

          <button
            v-for="item in passions"
            :key="item.id"
            type="button"
            class="w-full flex items-center gap-3 rounded-xl border px-3 py-3 text-left transition-all"
            :class="activePassion?.id === item.id
              ? 'bg-primary/10 border-primary/40 shadow-sm'
              : 'border-base-300 bg-base-100 hover:border-primary/30 hover:bg-base-200'"
            @click="selectPassion(item)"
          >
            <!-- Icône passion -->
            <div
              class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border transition-colors"
              :class="activePassion?.id === item.id ? 'bg-primary/20 border-primary/30' : 'bg-base-200 border-base-300'"
            >
              <span class="material-symbols-outlined text-base" :class="activePassion?.id === item.id ? 'text-primary' : 'text-base-content/50'">interests</span>
            </div>

            <!-- Nom + description -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold truncate" :class="activePassion?.id === item.id ? 'text-primary' : 'text-base-content'">
                {{ item.name }}
              </p>
              <p class="text-xs text-base-content/45 truncate">
                {{ item.recentPosts.length }} post{{ item.recentPosts.length !== 1 ? 's' : '' }}
              </p>
            </div>

            <!-- Badge suivi -->
            <div v-if="!isOwner" class="flex-shrink-0">
              <div v-if="isPassionFollowed(item.id)" class="badge badge-primary badge-sm gap-1">
                <span class="material-symbols-outlined text-[10px]">check</span>
                Suivi
              </div>
            </div>

            <!-- Chevron actif -->
            <span v-if="activePassion?.id === item.id" class="material-symbols-outlined text-sm text-primary flex-shrink-0">chevron_right</span>
          </button>
        </div>

        <!-- ── Colonne droite : détail de la passion active ── -->
        <div v-if="activePassion" class="space-y-4">

          <!-- Header de la passion sélectionnée -->
          <div class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
            <div class="flex items-start justify-between gap-4 p-5">
              <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10 border border-primary/20">
                  <span class="material-symbols-outlined text-xl text-primary">interests</span>
                </div>
                <div class="min-w-0">
                  <h2 class="text-base font-bold text-base-content truncate">{{ activePassion.name }}</h2>
                  <p class="text-sm text-base-content/55 mt-0.5 line-clamp-2">
                    {{ activePassion.description || "Sans description." }}
                  </p>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex flex-col items-end gap-2 flex-shrink-0">
                <button
                  v-if="!isOwner"
                  type="button"
                  class="btn btn-sm rounded-full gap-1.5 transition-all"
                  :class="isPassionFollowed(activePassion.id) ? 'btn-outline' : 'btn-primary'"
                  :disabled="loadingPassionActionId === activePassion.id || loadingSubscriptions"
                  @click="toggleFollowPassion(activePassion.id)"
                >
                  <span v-if="loadingPassionActionId === activePassion.id" class="loading loading-spinner loading-xs" />
                  <span v-else class="material-symbols-outlined text-sm">
                    {{ isPassionFollowed(activePassion.id) ? 'check' : 'add' }}
                  </span>
                  {{ loadingPassionActionId === activePassion.id ? '...' : isPassionFollowed(activePassion.id) ? 'Suivi' : 'Suivre' }}
                </button>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs gap-1 text-primary"
                  @click="openPassionDetail(activePassion.id)"
                >
                  Voir tout
                  <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Publications récentes -->
          <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-base-content/40 px-1">
              Publications récentes
            </p>

            <div v-if="activePassion.recentPosts.length === 0" class="rounded-2xl border border-base-300 bg-base-100 p-8 text-center space-y-2">
              <span class="material-symbols-outlined text-3xl text-base-content/20">inbox</span>
              <p class="text-sm text-base-content/50">Aucune publication pour l'instant.</p>
            </div>

            <div
              v-for="post in activePassion.recentPosts"
              :key="post.id"
              class="rounded-2xl border border-base-300 bg-base-100 p-4 hover:border-primary/30 hover:shadow-sm transition-all cursor-pointer"
              @click="openPassionDetail(activePassion.id)"
            >
              <div class="flex items-start gap-3">
                <!-- Icône type -->
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-base-200 border border-base-300 mt-0.5">
                  <span class="material-symbols-outlined text-sm text-base-content/50">{{ getPostTypeIcon(post) }}</span>
                </div>

                <!-- Contenu -->
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-base-content/85 leading-relaxed line-clamp-3">
                    {{ buildPostPreview(post) }}
                  </p>

                  <!-- Image aperçu si photo -->
                  <img
                    v-if="post.image_url && !post.content?.startsWith('__LOOM_')"
                    :src="post.image_url"
                    alt="Aperçu image"
                    class="mt-2 h-32 w-full object-cover rounded-lg border border-base-300"
                  />

                  <div class="flex items-center gap-3 mt-2">
                    <span class="text-xs text-base-content/40">{{ formatTimeLabel(post.created_at) }}</span>
                    <span class="badge badge-ghost badge-sm gap-1">
                      <span class="material-symbols-outlined text-[10px]">{{ getPostTypeIcon(post) }}</span>
                      {{ post.content?.startsWith('__LOOM_VIDEO__') ? 'Vidéo' : post.content?.startsWith('__LOOM_ARTICLE__') ? 'Article' : 'Photo' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Voir plus -->
            <button
              type="button"
              class="w-full flex items-center justify-center gap-2 rounded-2xl border border-dashed border-base-300 py-4 text-sm font-medium text-primary hover:bg-primary/5 hover:border-primary/40 transition-all"
              @click="openPassionDetail(activePassion.id)"
            >
              <span class="material-symbols-outlined text-base">expand_circle_down</span>
              Voir toutes les publications de {{ activePassion.name }}
            </button>
          </div>
        </div>

        <!-- Aucune passion sélectionnée -->
        <div v-else-if="passions.length === 0" class="rounded-2xl border border-dashed border-base-300 bg-base-100 p-12 flex flex-col items-center gap-3 text-center">
          <span class="material-symbols-outlined text-4xl text-base-content/20">interests</span>
          <p class="text-sm text-base-content/50">Aucune passion publique à afficher.</p>
        </div>
      </div>

      <!-- ══════════════════════════════════════
           CTA visiteur non connecté
      ═══════════════════════════════════════ -->
      <div v-if="!isAuthenticated && !isProfileLocked" class="relative rounded-2xl bg-primary overflow-hidden">
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 900 120" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <pattern id="cta-dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
            <circle cx="1" cy="1" r="1" fill="white" opacity="0.12"/>
          </pattern>
          <rect width="100%" height="100%" fill="url(#cta-dots)"/>
          <circle cx="820" cy="60" r="100" fill="white" opacity="0.06"/>
          <circle cx="60"  cy="110" r="80"  fill="white" opacity="0.05"/>
        </svg>
        <div class="relative flex flex-col sm:flex-row items-center justify-between gap-4 px-8 py-7">
          <div class="text-primary-content text-center sm:text-left">
            <p class="text-base font-bold">Envie de suivre ces passions ?</p>
            <p class="text-sm text-primary-content/75 mt-0.5">Crée un compte gratuit pour t'abonner et personnaliser ton fil.</p>
          </div>
          <div class="flex gap-3 flex-shrink-0">
            <button class="btn bg-base-100 text-primary hover:bg-base-200 border-0 btn-sm gap-1.5 font-bold" @click="router.push('/register')">
              <span class="material-symbols-outlined text-sm">person_add</span>
              Créer un compte
            </button>
            <button class="btn btn-outline border-primary-content/40 text-primary-content hover:bg-primary-content/10 btn-sm" @click="router.push('/login')">
              Se connecter
            </button>
          </div>
        </div>
      </div>

    </template>
  </main>
</template>