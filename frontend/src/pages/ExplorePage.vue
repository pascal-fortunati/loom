<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { isAuthenticated } from "../composables/useAuth";
import { getApiBaseUrl, rebaseApiUrl } from "../services/auth.service";
import { fetchExplorePosts, type ApiPost } from "../services/feed.service";

interface ExplorePostItem {
  id: number;
  passionPageId: number;
  authorId?: number;
  author: string;
  initials: string;
  avatarClass: string;
  badgeClass: string;
  accentClass: string;
  barClass: string;
  avatarUrl?: string | null;
  tag: string;
  time: string;
  content: string;
  imageUrl?: string | null;
  likes: number;
  comments: number;
}

const loading   = ref(false);
const apiError  = ref("");
const posts     = ref<ExplorePostItem[]>([]);
const activeFilter = ref("Tous");
const router    = useRouter();

function getInitials(name: string): string {
  return (name || "U").trim().charAt(0).toUpperCase();
}

function pickAvatarClass(index: number): string {
  const palette = ["bg-primary","bg-secondary","bg-accent","bg-info","bg-success"];
  return palette[index % palette.length];
}

/**
 * Couleur de badge stable selon l'identifiant de la passion : une même passion
 * garde toujours la même couleur (repère visuel façon réseau social).
 * Classes complètes pour le scan de Tailwind v4, basées sur les tokens DaisyUI.
 */
function pickPassionBadgeClass(passionPageId: number): string {
  const palette = [
    "border-primary/30 bg-primary/10 text-primary",
    "border-secondary/30 bg-secondary/10 text-secondary",
    "border-accent/30 bg-accent/10 text-accent",
    "border-info/30 bg-info/10 text-info",
    "border-success/30 bg-success/10 text-success",
    "border-warning/30 bg-warning/10 text-warning",
  ];
  return palette[Math.abs(passionPageId) % palette.length];
}

/**
 * Accent coloré (fond doux + texte) stable par passion, pour les placeholders
 * d'image et les icônes. Aligné sur la même palette que les badges.
 */
function pickPassionAccentClass(passionPageId: number): string {
  const palette = [
    "bg-primary/10 text-primary",
    "bg-secondary/10 text-secondary",
    "bg-accent/10 text-accent",
    "bg-info/10 text-info",
    "bg-success/10 text-success",
    "bg-warning/10 text-warning",
  ];
  return palette[Math.abs(passionPageId) % palette.length];
}

/**
 * Couleur pleine (bande d'accent en haut de carte) stable par passion.
 */
function pickPassionBarClass(passionPageId: number): string {
  const palette = [
    "bg-primary",
    "bg-secondary",
    "bg-accent",
    "bg-info",
    "bg-success",
    "bg-warning",
  ];
  return palette[Math.abs(passionPageId) % palette.length];
}

/**
 * Résout un avatar vers une URL exploitable.
 */
function resolveAvatarUrl(value: string | null | undefined): string {
  if (!value) return "";
  if (value.startsWith("http://") || value.startsWith("https://")) return value;
  return `${getApiBaseUrl()}/uploads/avatars/${value}`;
}

/**
 * Résout une image de post vers une URL exploitable.
 */
function resolvePostImageUrl(value: string | null | undefined): string {
  if (!value) return "";
  if (value.startsWith("http://") || value.startsWith("https://")) return rebaseApiUrl(value);
  if (value.startsWith("/")) return `${getApiBaseUrl()}${value}`;
  return `${getApiBaseUrl()}/uploads/posts/${value}`;
}

function formatTimeLabel(value: string): string {
  const date    = new Date(value);
  if (Number.isNaN(date.getTime())) return "Maintenant";
  const diffMin = Math.floor((Date.now() - date.getTime()) / 60000);
  if (diffMin < 60)  return `${Math.max(1, diffMin)} min`;
  const diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) return `${diffHour} h`;
  return `${Math.floor(diffHour / 24)} j`;
}

/**
 * Décode le format de publication encodé dans la BDD.
 */
function decodeContent(rawContent: string): { type: "photo" | "video" | "article"; text: string; articleTitle?: string } {
  if (rawContent.startsWith("__LOOM_VIDEO__")) {
    try { const p = JSON.parse(rawContent.replace("__LOOM_VIDEO__", "")); return { type: "video", text: String(p.text ?? "") }; }
    catch { return { type: "video", text: "" }; }
  }
  if (rawContent.startsWith("__LOOM_ARTICLE__")) {
    try {
      const p   = JSON.parse(rawContent.replace("__LOOM_ARTICLE__", ""));
      const doc = new DOMParser().parseFromString(String(p.body ?? ""), "text/html");
      return { type: "article", articleTitle: String(p.title ?? "Article"), text: (doc.body.textContent || "").trim() };
    }
    catch { return { type: "article", text: "" }; }
  }
  return { type: "photo", text: rawContent };
}

function getPostDisplayText(raw: string, max = 160): string {
  const decoded = decodeContent(raw);
  const base    = decoded.type === "article" && decoded.articleTitle
    ? `${decoded.articleTitle} — ${decoded.text}`
    : decoded.text;
  return base.length <= max ? base : `${base.slice(0, max).trimEnd()}…`;
}

function getPostTypeIcon(raw: string): string {
  if (raw.startsWith("__LOOM_VIDEO__"))   return "videocam";
  if (raw.startsWith("__LOOM_ARTICLE__")) return "article";
  return "photo_camera";
}

function getPostTypeLabel(raw: string): string {
  if (raw.startsWith("__LOOM_VIDEO__"))   return "Vidéo";
  if (raw.startsWith("__LOOM_ARTICLE__")) return "Article";
  return "Photo";
}

/**
 * Retourne la première image trouvée dans un article encodé.
 */
function getArticlePreviewImageFromRaw(rawContent: string): string {
  if (!rawContent.startsWith("__LOOM_ARTICLE__")) return "";
  try {
    const parsed = JSON.parse(rawContent.replace("__LOOM_ARTICLE__", ""));
    const bodyHtml = String(parsed.body ?? "");
    const documentFragment = new DOMParser().parseFromString(bodyHtml, "text/html");
    const firstImage = documentFragment.querySelector("img");
    return rebaseApiUrl(firstImage?.getAttribute("src") || "");
  } catch {
    return "";
  }
}

/**
 * Retourne l'image d'aperçu d'une publication (photo ou article).
 */
function getPostPreviewImage(post: ExplorePostItem): string {
  const fromImageField = resolvePostImageUrl(post.imageUrl);
  if (fromImageField) return fromImageField;
  const fromArticleBody = getArticlePreviewImageFromRaw(post.content);
  return fromArticleBody;
}

function mapPosts(apiPosts: ApiPost[]): ExplorePostItem[] {
  return apiPosts.map((item, index) => ({
    id:            item.id,
    passionPageId: item.passion_page_id,
    authorId:      Number(item.user_id),
    author:        item.username,
    initials:      getInitials(item.username),
    avatarClass:   pickAvatarClass(index),
    badgeClass:    pickPassionBadgeClass(item.passion_page_id),
    accentClass:   pickPassionAccentClass(item.passion_page_id),
    barClass:      pickPassionBarClass(item.passion_page_id),
    avatarUrl:     resolveAvatarUrl(item.avatar),
    tag:           item.passion_page_name || "Général",
    time:          formatTimeLabel(item.created_at),
    content:       item.content,
    imageUrl:      item.image_url,
    likes:         Number(item.likes_count) || 0,
    comments:      Number(item.comments_count) || 0,
  }));
}

function openPassionDetail(passionId: number): void  { router.push(`/passion/${passionId}`); }
function openPublicProfile(userId: number | null | undefined): void {
  if (!userId || !Number.isFinite(Number(userId))) return;
  if (isAuthenticated.value) {
    router.push({
      path: "/feed",
      query: {
        mode: "subscriptions",
        view: "profile",
        user: String(Number(userId)),
      },
    });
    return;
  }
  router.push(`/u/${Number(userId)}`);
}

const availableFilters = computed(() => {
  const tags = Array.from(new Set(posts.value.map((p) => p.tag)));
  return ["Tous", ...tags];
});

const filteredPosts = computed(() =>
  activeFilter.value === "Tous"
    ? posts.value
    : posts.value.filter((p) => p.tag === activeFilter.value),
);

/** Posts mis en avant dans le hero (les 3 premiers). */
const heroPost  = computed(() => filteredPosts.value[0] ?? null);
const sidePosts = computed(() => filteredPosts.value.slice(1, 3));

/** Reste des posts affichés dans la grille principale. */
const gridPosts = computed(() => filteredPosts.value.slice(3));

async function loadExploreData(): Promise<void> {
  loading.value  = true;
  apiError.value = "";
  try {
    const apiPosts  = await fetchExplorePosts(50, 0);
    posts.value     = mapPosts(apiPosts);
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Erreur de chargement";
  } finally {
    loading.value = false;
  }
}

onMounted(() => { loadExploreData(); });
</script>

<template>
  <!--
    ExploreView – Loom
    Stack : Vue 3 + TypeScript + DaisyUI v5 + Tailwind v4
    Logique 100 % identique à l'original. Template refondu pour visiteurs non inscrits.
    100 % tokens DaisyUI. Icônes : material-symbols-outlined.
  -->
  <div class="relative overflow-hidden">

    <main class="loom-shell py-8 space-y-10 relative">

      <!-- ══════════════════════════════════════════════════
           HERO BANNER — accroche visiteur
      ═══════════════════════════════════════════════════ -->
      <section class="rounded-2xl bg-primary overflow-hidden relative">
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 1000 200" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <pattern id="hero-dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
            <circle cx="1" cy="1" r="1" fill="white" opacity="0.12"/>
          </pattern>
          <rect width="100%" height="100%" fill="url(#hero-dots)"/>
          <circle cx="900" cy="100" r="160" fill="white" opacity="0.06"/>
          <circle cx="900" cy="100" r="90"  fill="white" opacity="0.05"/>
          <circle cx="50"  cy="180" r="120" fill="white" opacity="0.04"/>
          <circle cx="400" cy="-20" r="80"  fill="white" opacity="0.04"/>
        </svg>
        <div class="relative flex flex-col md:flex-row items-center justify-between gap-6 px-8 py-10">
          <div class="text-primary-content text-center md:text-left space-y-2 max-w-xl">
            <div class="inline-flex items-center gap-1.5 rounded-full bg-primary-content/15 border border-primary-content/20 px-3 py-1.5 text-xs font-semibold text-primary-content/90 mb-1">
              <span class="material-symbols-outlined text-sm">travel_explore</span>
              Exploration publique
            </div>
            <h1 class="text-2xl md:text-3xl font-bold leading-snug">
              Découvre ce que les gens<br class="hidden md:block"> partagent avec passion
            </h1>
            <p class="text-sm text-primary-content/75 leading-relaxed">
              {{
                isAuthenticated
                  ? "Parcours les publications publiques et découvre de nouvelles passions."
                  : "Parcours librement les publications publiques. Crée un compte pour interagir et construire ton propre fil de passions."
              }}
            </p>
          </div>
          <div v-if="!isAuthenticated" class="flex flex-col gap-3 flex-shrink-0 items-center">
            <button class="btn bg-base-100 text-primary hover:bg-base-200 border-0 btn-md gap-2 font-bold w-44" @click="router.push('/register')">
              <span class="material-symbols-outlined text-base">person_add</span>
              Créer un compte
            </button>
            <button class="btn btn-outline border-primary-content/40 text-primary-content hover:bg-primary-content/10 btn-sm w-44" @click="router.push('/login')">
              J'ai déjà un compte
            </button>
          </div>
        </div>
      </section>

      <!-- ══════════════════════════════════════════════════
           FILTRES PAR PASSION
      ═══════════════════════════════════════════════════ -->
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold text-base-content/40 uppercase tracking-wider mr-1">Filtrer :</span>
        <button
          v-for="filter in availableFilters"
          :key="filter"
          class="btn btn-xs rounded-full border transition-all"
          :class="activeFilter === filter
            ? 'btn-primary border-primary'
            : 'border-base-300 bg-base-100 hover:border-primary/40 hover:text-primary'"
          @click="activeFilter = filter"
        >
          {{ filter }}
        </button>
      </div>

      <!-- Erreur -->
      <div v-if="apiError" class="alert alert-error text-sm py-2.5 px-4 rounded-xl">
        <span class="material-symbols-outlined text-base">error</span>
        {{ apiError }}
      </div>

      <!-- ══════════════════════════════════════════════════
           SKELETON CHARGEMENT
      ═══════════════════════════════════════════════════ -->
      <div v-if="loading" class="space-y-6">
        <!-- Skeleton hero post -->
        <div class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
          <div class="h-52 w-full animate-pulse bg-base-300"/>
          <div class="p-5 space-y-3">
            <div class="flex gap-3 items-center">
              <div class="h-10 w-10 rounded-full animate-pulse bg-base-300 flex-shrink-0"/>
              <div class="space-y-2 flex-1">
                <div class="h-3 w-32 animate-pulse rounded-full bg-base-300"/>
                <div class="h-3 w-20 animate-pulse rounded-full bg-base-300"/>
              </div>
            </div>
            <div class="h-3 w-full animate-pulse rounded-full bg-base-300"/>
            <div class="h-3 w-4/5 animate-pulse rounded-full bg-base-300"/>
          </div>
        </div>
        <!-- Skeleton grille -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="i in 6" :key="i" class="rounded-2xl border border-base-300 bg-base-100 p-4 space-y-3">
            <div class="flex gap-3 items-center">
              <div class="h-9 w-9 rounded-full animate-pulse bg-base-300 flex-shrink-0"/>
              <div class="space-y-2 flex-1">
                <div class="h-3 w-24 animate-pulse rounded-full bg-base-300"/>
                <div class="h-3 w-16 animate-pulse rounded-full bg-base-300"/>
              </div>
            </div>
            <div class="h-3 w-full animate-pulse rounded-full bg-base-300"/>
            <div class="h-3 w-3/4 animate-pulse rounded-full bg-base-300"/>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════
           CONTENU — posts explorables
      ═══════════════════════════════════════════════════ -->
      <div v-else-if="filteredPosts.length === 0" class="rounded-2xl border border-dashed border-base-300 bg-base-100 py-16 flex flex-col items-center gap-3 text-center">
        <span class="material-symbols-outlined text-4xl text-base-content/20">travel_explore</span>
        <p class="text-sm text-base-content/50">Aucune publication publique disponible pour le moment.</p>
      </div>

      <div v-else class="space-y-8">

        <!-- ── POST VEDETTE (le premier) ── -->
        <div v-if="heroPost" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden hover:border-primary/40 hover:shadow-md hover:-translate-y-1 transition-all duration-300">
          <div class="relative">
            <img
              v-if="getPostPreviewImage(heroPost)"
              :src="getPostPreviewImage(heroPost)"
              alt="Aperçu publication vedette"
              class="h-64 w-full object-cover md:h-72"
            />
            <div
              v-else
              :class="['flex h-64 w-full items-center justify-center md:h-72', heroPost.accentClass]"
            >
              <span class="material-symbols-outlined text-6xl">
                {{ getPostTypeIcon(heroPost.content) }}
              </span>
            </div>
            <div class="absolute left-4 top-4 badge badge-outline badge-primary inline-flex items-center gap-1 bg-base-100/90">
              <span class="material-symbols-outlined loom-badge-icon">star</span>
              Publication vedette
            </div>
          </div>

          <div class="flex flex-col gap-4 p-5 md:p-6">
            <div class="flex items-center gap-3">
              <button
                v-if="heroPost.avatarUrl"
                type="button"
                class="h-10 w-10 overflow-hidden rounded-full border border-base-300 bg-base-200"
                @click="openPublicProfile(heroPost.authorId)"
              >
                <img :src="heroPost.avatarUrl" alt="Avatar auteur" class="h-full w-full object-cover" />
              </button>
              <button
                v-else
                type="button"
                class="avatar-initials h-10 w-10 rounded-full text-sm font-semibold text-white"
                :class="heroPost.avatarClass"
                @click="openPublicProfile(heroPost.authorId)"
              >
                {{ heroPost.initials }}
              </button>
              <div class="min-w-0">
                <div class="mt-0.5 flex min-w-0 flex-nowrap items-center gap-1.5">
                  <button
                    type="button"
                    class="truncate text-sm font-semibold text-base-content transition-colors hover:text-primary"
                    @click="openPublicProfile(heroPost.authorId)"
                  >
                    {{ heroPost.author }}
                  </button>
                  <button
                    :class="[
                      'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold cursor-pointer transition hover:brightness-110',
                      heroPost.badgeClass,
                    ]"
                    @click="openPassionDetail(heroPost.passionPageId)"
                  >
                    <span class="material-symbols-outlined loom-badge-icon">interests</span>
                    {{ heroPost.tag }}
                  </button>
                  <span class="whitespace-nowrap text-xs text-base-content/50">{{ heroPost.time }}</span>
                </div>
              </div>
            </div>

            <p class="text-sm leading-relaxed text-base-content/85">
              {{ getPostDisplayText(heroPost.content, 260) }}
            </p>

            <div class="flex items-center gap-3 border-t border-base-300 pt-3">
              <span class="badge badge-outline inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-xs">{{ getPostTypeIcon(heroPost.content) }}</span>
                {{ getPostTypeLabel(heroPost.content) }}
              </span>
              <div class="ml-auto flex items-center gap-3 text-xs text-base-content/50">
                <span class="inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-sm">favorite</span>
                  {{ heroPost.likes }}
                </span>
                <span class="inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-sm">chat_bubble_outline</span>
                  {{ heroPost.comments }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── 2 POSTS SECONDAIRES côte à côte ── -->
        <div v-if="sidePosts.length" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div
            v-for="post in sidePosts"
            :key="post.id"
            class="rounded-2xl border border-base-300 bg-base-100 p-5 flex flex-col gap-4 hover:border-primary/40 hover:shadow-md hover:-translate-y-1 transition-all duration-300"
          >
            <div class="flex items-center gap-3">
              <button
                v-if="post.avatarUrl"
                type="button"
                class="h-9 w-9 overflow-hidden rounded-full border border-base-300 bg-base-200"
                @click="openPublicProfile(post.authorId)"
              >
                <img :src="post.avatarUrl" alt="Avatar auteur" class="h-full w-full object-cover" />
              </button>
              <button
                v-else
                type="button"
                class="avatar-initials h-9 w-9 rounded-full text-white font-semibold flex-shrink-0 flex items-center justify-center text-xs"
                :class="post.avatarClass"
                @click="openPublicProfile(post.authorId)"
              >
                {{ post.initials }}
              </button>
              <div class="min-w-0">
                <div class="mt-0.5 flex min-w-0 flex-nowrap items-center gap-1.5">
                  <button type="button" class="truncate text-sm font-semibold text-base-content transition-colors hover:text-primary" @click="openPublicProfile(post.authorId)">
                    {{ post.author }}
                  </button>
                  <button :class="['inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold cursor-pointer transition hover:brightness-110', post.badgeClass]" @click="openPassionDetail(post.passionPageId)">
                    <span class="material-symbols-outlined loom-badge-icon">interests</span>
                    {{ post.tag }}
                  </button>
                  <span class="whitespace-nowrap text-xs text-base-content/40">{{ post.time }}</span>
                </div>
              </div>
            </div>
            <p class="text-sm text-base-content/80 leading-relaxed flex-1">
              {{ getPostDisplayText(post.content) }}
            </p>
            <img
              v-if="getPostPreviewImage(post)"
              :src="getPostPreviewImage(post)"
              alt="Aperçu publication"
              class="h-40 w-full rounded-xl border border-base-300 object-cover"
            />
            <div
              v-else
              :class="['flex h-40 w-full items-center justify-center rounded-xl', post.accentClass]"
            >
              <span class="material-symbols-outlined text-6xl opacity-80">
                {{ getPostTypeIcon(post.content) }}
              </span>
            </div>
            <div class="flex items-center gap-2 border-t border-base-300 pt-3">
              <span class="badge badge-outline inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-xs">{{ getPostTypeIcon(post.content) }}</span>
                {{ getPostTypeLabel(post.content) }}
              </span>
              <div class="flex items-center gap-3 ml-auto text-xs text-base-content/45">
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">favorite</span>{{ post.likes }}</span>
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">chat_bubble_outline</span>{{ post.comments }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── BANNIÈRE INTERMÉDIAIRE — incitation ── -->
        <div
          v-if="!isAuthenticated"
          class="rounded-2xl border border-primary/25 bg-primary/8 flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-5"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15 border border-primary/20 flex-shrink-0">
              <span class="material-symbols-outlined text-lg text-primary">lock_open</span>
            </div>
            <div>
              <p class="text-sm font-semibold text-base-content">Tu veux interagir ?</p>
              <p class="text-xs text-base-content/55">Inscris-toi gratuitement pour liker, commenter et suivre des passions.</p>
            </div>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button class="btn btn-primary btn-sm rounded-full gap-1.5" @click="router.push('/register')">
              <span class="material-symbols-outlined text-sm">person_add</span>
              Créer un compte
            </button>
            <button class="btn btn-ghost btn-sm rounded-full" @click="router.push('/login')">
              Se connecter
            </button>
          </div>
        </div>

        <!-- ── GRILLE PRINCIPALE — reste des posts ── -->
        <div v-if="gridPosts.length" class="space-y-4">
          <div class="flex items-center gap-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-base-content/40">Toutes les publications</p>
            <div class="flex-1 h-px bg-base-300"/>
            <span class="text-xs text-base-content/40">{{ filteredPosts.length }} au total</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="post in gridPosts"
              :key="post.id"
              class="group rounded-2xl border border-base-300 bg-base-100 overflow-hidden hover:border-primary/40 hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col"
            >
              <!-- Bande d'accent colorée (couleur de la passion) -->
              <div :class="['h-1 w-full', post.barClass]"></div>

              <!-- Header -->
              <div class="flex items-center gap-3 p-4 pb-3">
                <button
                  v-if="post.avatarUrl"
                  type="button"
                  class="h-8 w-8 overflow-hidden rounded-full border border-base-300 bg-base-200"
                  @click="openPublicProfile(post.authorId)"
                >
                  <img :src="post.avatarUrl" alt="Avatar auteur" class="h-full w-full object-cover" />
                </button>
                <button
                  v-else
                  type="button"
                  class="avatar-initials h-8 w-8 rounded-full text-white text-xs font-semibold flex-shrink-0 flex items-center justify-center"
                  :class="post.avatarClass"
                  @click="openPublicProfile(post.authorId)"
                >
                  {{ post.initials }}
                </button>
                <div class="flex-1 min-w-0">
                  <div class="mt-0.5 flex min-w-0 items-center gap-1.5">
                    <button
                      type="button"
                      class="truncate text-xs font-semibold text-base-content transition-colors hover:text-primary"
                      @click="openPublicProfile(post.authorId)"
                    >
                      {{ post.author }}
                    </button>
                    <button :class="['inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold cursor-pointer transition hover:brightness-110', post.badgeClass]" @click="openPassionDetail(post.passionPageId)">
                      <span class="material-symbols-outlined loom-badge-icon">interests</span>
                      {{ post.tag }}
                    </button>
                    <span class="whitespace-nowrap text-[10px] text-base-content/40">{{ post.time }}</span>
                  </div>
                </div>
                <div
                  :class="['flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg transition group-hover:scale-110', post.accentClass]"
                >
                  <span class="material-symbols-outlined text-sm">{{ getPostTypeIcon(post.content) }}</span>
                </div>
              </div>

              <!-- Visuel : image si présente, sinon bloc coloré décoratif (plus de carte vide) -->
              <img
                v-if="getPostPreviewImage(post)"
                :src="getPostPreviewImage(post)"
                alt="Aperçu publication"
                class="mx-4 h-36 w-[calc(100%-2rem)] rounded-xl border border-base-300 object-cover"
              />
              <div
                v-else
                :class="['mx-4 flex h-28 items-center justify-center rounded-xl', post.accentClass]"
              >
                <span class="material-symbols-outlined text-5xl opacity-80">
                  {{ getPostTypeIcon(post.content) }}
                </span>
              </div>

              <!-- Texte -->
              <div class="px-4 pb-3 pt-3 flex-1">
                <p class="text-xs text-base-content/75 leading-relaxed line-clamp-3">
                  {{ getPostDisplayText(post.content, 160) }}
                </p>
              </div>

              <!-- Footer -->
              <div class="flex items-center gap-2 border-t border-base-300 px-4 py-2.5 bg-base-200/50">
                <span class="text-xs text-base-content/40 flex items-center gap-1">
                  <span class="material-symbols-outlined text-sm">favorite</span>{{ post.likes }}
                </span>
                <span class="text-xs text-base-content/40 flex items-center gap-1">
                  <span class="material-symbols-outlined text-sm">chat_bubble_outline</span>{{ post.comments }}
                </span>
                <button
                  v-if="!isAuthenticated"
                  class="btn btn-ghost btn-xs rounded-full ml-auto gap-1 text-[10px] text-primary opacity-0 group-hover:opacity-100 transition-opacity"
                  @click="router.push('/register')"
                >
                  <span class="material-symbols-outlined text-xs">login</span>
                  Rejoindre
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════
             CTA FINAL
        ═══════════════════════════════════════════════════ -->
        <div v-if="!isAuthenticated" class="relative rounded-2xl bg-primary overflow-hidden">
          <svg class="absolute inset-0 h-full w-full" viewBox="0 0 900 180" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <pattern id="cta-exp-dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
              <circle cx="1" cy="1" r="1" fill="white" opacity="0.12"/>
            </pattern>
            <rect width="100%" height="100%" fill="url(#cta-exp-dots)"/>
            <circle cx="830" cy="90"  r="150" fill="white" opacity="0.07"/>
            <circle cx="830" cy="90"  r="90"  fill="white" opacity="0.05"/>
            <circle cx="60"  cy="160" r="110" fill="white" opacity="0.04"/>
            <circle cx="420" cy="-10" r="80"  fill="white" opacity="0.04"/>
          </svg>
          <div class="relative flex flex-col md:flex-row items-center justify-between gap-6 px-10 py-12">
            <div class="text-primary-content text-center md:text-left space-y-2 max-w-lg">
              <h2 class="text-2xl font-bold leading-snug">
                Prêt à créer ton propre fil de passions ?
              </h2>
              <p class="text-sm text-primary-content/75 leading-relaxed">
                Rejoins Loom gratuitement. Crée tes pages de passions, abonne-toi à celles qui t'inspirent et profite d'un fil 100 % personnalisé.
              </p>
              <div class="flex flex-wrap justify-center md:justify-start gap-2 pt-2">
                <div v-for="p in ['Gaming','Cuisine','Sport','Photo','Voyage','Musique']" :key="p" class="badge bg-primary-content/15 border-primary-content/20 text-primary-content text-xs gap-1">
                  <span class="material-symbols-outlined text-xs">interests</span>
                  {{ p }}
                </div>
              </div>
            </div>
            <div class="flex flex-col gap-3 flex-shrink-0 items-center">
              <button class="btn bg-base-100 text-primary hover:bg-base-200 border-0 btn-md gap-2 font-bold w-48" @click="router.push('/register')">
                <span class="material-symbols-outlined text-base">rocket_launch</span>
                Commencer maintenant
              </button>
              <button class="btn btn-outline border-primary-content/40 text-primary-content hover:bg-primary-content/10 btn-sm w-48" @click="router.push('/login')">
                Déjà un compte ? Se connecter
              </button>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>
</template>

<style scoped>
/* Ajuste le rendu des Material Symbols dans les badges (taille + alignement optique). */
.loom-badge-icon {
  font-size: 11px;
  line-height: 1;
  font-variation-settings:
    "FILL" 0,
    "wght" 400,
    "GRAD" 0,
    "opsz" 20;
}
</style>
