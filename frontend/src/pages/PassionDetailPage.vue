<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import FeedSidebar from "../components/FeedSidebar.vue";
import MobileSidebarDrawer from "../components/MobileSidebarDrawer.vue";
import { isAuthenticated, useAuthToken, useCurrentAvatar } from "../composables/useAuth";
import { fetchMe, getApiBaseUrl, rebaseApiUrl } from "../services/auth.service";
import { fetchMyPassionsFeed, type ApiPost } from "../services/feed.service";
import {
  fetchMyPassions,
  fetchPassionById,
  fetchPublicPassions,
  type PassionPage,
} from "../services/passion.service";
import { fetchPostsByPassion } from "../services/post.service";
import {
  fetchMySubscriptions,
  subscribeToPassion,
  unsubscribeFromPassion,
  type SubscriptionItem,
} from "../services/subscription.service";

type PostType = "photo" | "video" | "article";

interface PassionPostItem {
  id: number;
  authorId?: number;
  author: string;
  initials: string;
  avatarClass: string;
  avatarUrl?: string | null;
  time: string;
  content: string;
  imageUrl?: string | null;
  videoUrl?: string;
  articleTitle?: string;
  postType: PostType;
  likes: number;
  comments: number;
}

interface SidebarPassionItem extends PassionPage {
  colorClass: string;
  postCount: number;
}

const route = useRoute();
const router = useRouter();
const token = useAuthToken();
const currentAuthAvatar = useCurrentAvatar();

const loading = ref(false);
const loadingMore = ref(false);
const loadingSubscription = ref(false);
const errorMessage = ref("");
const infoMessage = ref("");

const passion = ref<PassionPage | null>(null);
const posts = ref<PassionPostItem[]>([]);
const currentUserId = ref<number | null>(null);
const currentUsername = ref("Utilisateur");
const subscribedPassionIds = ref<number[]>([]);
const myPassions = ref<PassionPage[]>([]);
const mySubscriptions = ref<SubscriptionItem[]>([]);
const discoverPassions = ref<PassionPage[]>([]);
const myPostsCount = ref(0);
const myPostCountsByPassionId = ref<Record<number, number>>({});
const loadingDiscover = ref(false);
const loadingSubscribe = ref(false);
const subscriptionActionPulse = ref(false);
const expandedPostContent = ref<Record<number, boolean>>({});
const offset = ref(0);
const limit = 12;
const hasMore = ref(true);

/**
 * Résout une valeur avatar en URL complète.
 */
function resolveAvatarUrl(avatarValue: string | null | undefined): string {
  if (!avatarValue) return "";
  if (avatarValue.startsWith("http://") || avatarValue.startsWith("https://")) return avatarValue;
  return `${getApiBaseUrl()}/uploads/avatars/${avatarValue}`;
}

/**
 * Stats affichées dans la sidebar gauche.
 */
const myFeedStats = computed(() => ({
  passions: myPassions.value.length,
  suivis: mySubscriptions.value.length,
  publications: myPostsCount.value,
}));

const currentUserAvatarUrl = computed(() => resolveAvatarUrl(currentAuthAvatar.value));

/**
 * Lit l'ID de passion dans l'URL et valide qu'il est exploitable.
 */
const passionId = computed(() => {
  const rawId = Number(route.params.id);
  return Number.isFinite(rawId) && rawId > 0 ? rawId : 0;
});

/**
 * Indique si l'utilisateur connecté est propriétaire de la passion affichée.
 */
const isOwner = computed(() => {
  if (!passion.value || !currentUserId.value) return false;
  return Number(passion.value.user_id) === Number(currentUserId.value);
});

/**
 * Indique si l'utilisateur connecté suit déjà la passion affichée.
 */
const isSubscribed = computed(() => {
  if (!passion.value) return false;
  return subscribedPassionIds.value.includes(Number(passion.value.id));
});

/**
 * Calcule les initiales d'un pseudo.
 */
function getInitials(name: string): string {
  return (name || "U").trim().charAt(0).toUpperCase();
}

/**
 * Formate la date en libellé court.
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
 * Donne une couleur d'avatar stable selon l'index.
 */
function pickAvatarClass(index: number): string {
  const palette = ["bg-primary", "bg-secondary", "bg-accent", "bg-info"];
  return palette[index % palette.length];
}

/**
 * Retourne l'ID de passion d'un abonnement.
 */
function getSubscriptionPassionId(item: SubscriptionItem): number {
  return Number(item.passion_page_id ?? item.id);
}

/**
 * Transforme les passions de l'utilisateur pour la sidebar.
 */
const topPassions = computed<SidebarPassionItem[]>(() =>
  myPassions.value.map((item, index) => ({
    ...item,
    colorClass: pickAvatarClass(index),
    postCount: myPostCountsByPassionId.value[Number(item.id)] || 0,
  })),
);

/**
 * Construit un index "passion_id -> nombre de posts" pour la sidebar.
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
 * Limite d'affichage des contenus longs avant "Voir plus".
 */
const postPreviewMaxLength = 220;
const articlePreviewMaxLength = 280;

/**
 * Indique si le contenu d'un post doit être tronqué.
 */
function shouldTruncatePostContent(post: PassionPostItem): boolean {
  if (post.postType === "article") {
    const articleText = extractPlainTextFromHtml(post.content);
    return articleText.length > postPreviewMaxLength;
  }
  return post.content.length > postPreviewMaxLength;
}

/**
 * Vérifie si le post est actuellement déplié.
 */
function isPostContentExpanded(postId: number): boolean {
  return Boolean(expandedPostContent.value[postId]);
}

/**
 * Retourne le contenu affiché selon l'état "Voir plus / Voir moins".
 */
function getDisplayedPostContent(post: PassionPostItem): string {
  if (post.postType === "article") {
    const articleText = extractPlainTextFromHtml(post.content);
    if (isPostContentExpanded(post.id) || articleText.length <= postPreviewMaxLength) {
      return articleText;
    }
    return `${articleText.slice(0, postPreviewMaxLength).trimEnd()}...`;
  }

  if (isPostContentExpanded(post.id) || !shouldTruncatePostContent(post)) {
    return post.content;
  }
  return `${post.content.slice(0, postPreviewMaxLength).trimEnd()}...`;
}

/**
 * Alterne l'état de dépliage du contenu d'un post.
 */
function togglePostContent(postId: number): void {
  expandedPostContent.value = {
    ...expandedPostContent.value,
    [postId]: !isPostContentExpanded(postId),
  };
}

/**
 * Déclenche une animation courte sur les actions d'abonnement.
 */
function triggerSubscriptionPulse(): void {
  subscriptionActionPulse.value = false;
  requestAnimationFrame(() => {
    subscriptionActionPulse.value = true;
    setTimeout(() => {
      subscriptionActionPulse.value = false;
    }, 240);
  });
}

/**
 * Supprime les balises/attributs HTML risqués pour l'affichage des articles.
 */
function sanitizeArticleHtml(rawHtml: string): string {
  if (!rawHtml) return "";
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(rawHtml, "text/html");

  documentFragment
    .querySelectorAll("script, style, iframe, object, embed")
    .forEach((node) => node.remove());

  documentFragment.querySelectorAll("*").forEach((element) => {
    Array.from(element.attributes).forEach((attribute) => {
      const attributeName = attribute.name.toLowerCase();
      const attributeValue = attribute.value.toLowerCase();
      if (attributeName.startsWith("on")) {
        element.removeAttribute(attribute.name);
      }
      if (
        (attributeName === "href" || attributeName === "src") &&
        attributeValue.startsWith("javascript:")
      ) {
        element.removeAttribute(attribute.name);
      }
    });
  });

  return rebaseApiUrl(documentFragment.body.innerHTML);
}

/**
 * Extrait le texte brut d'un contenu HTML.
 */
function extractPlainTextFromHtml(rawHtml: string): string {
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(rawHtml || "", "text/html");
  return (documentFragment.body.textContent || "").trim();
}

/**
 * Retourne le HTML article prêt à afficher dans la carte.
 */
function getSafeArticleHtml(post: PassionPostItem): string {
  return sanitizeArticleHtml(post.content);
}

/**
 * Extrait la première image d'un article pour l'aperçu visuel.
 */
function getArticlePreviewImageUrl(post: PassionPostItem): string {
  if (post.postType !== "article") return "";
  const sanitizedHtml = sanitizeArticleHtml(post.content);
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(sanitizedHtml, "text/html");
  const firstImage = documentFragment.querySelector("img");
  return rebaseApiUrl(firstImage?.getAttribute("src") || "");
}

/**
 * Construit un extrait texte court d'article pour le feed passion.
 */
function getArticlePreviewText(post: PassionPostItem): string {
  if (post.postType !== "article") return "";
  const plainText = extractPlainTextFromHtml(post.content);
  if (plainText.length <= articlePreviewMaxLength) return plainText;
  return `${plainText.slice(0, articlePreviewMaxLength).trimEnd()}...`;
}

/**
 * Décode les types de publication (photo, vidéo, article) stockés dans content.
 */
function decodePostContent(rawContent: string): {
  postType: PostType;
  content: string;
  articleTitle?: string;
  videoUrl?: string;
} {
  if (rawContent.startsWith("__LOOM_VIDEO__")) {
    try {
      const parsed = JSON.parse(rawContent.replace("__LOOM_VIDEO__", ""));
      return {
        postType: "video",
        content: String(parsed.text ?? ""),
        videoUrl: String(parsed.url ?? ""),
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
        content: String(parsed.body ?? ""),
      };
    } catch {
      return { postType: "article", content: rawContent.replace("__LOOM_ARTICLE__", "") };
    }
  }

  return { postType: "photo", content: rawContent };
}

/**
 * Convertit les posts API en format d'affichage.
 */
function mapPosts(apiPosts: ApiPost[], startIndex = 0): PassionPostItem[] {
  return apiPosts.map((item, index) => {
    const decoded = decodePostContent(item.content);
    return {
      id: item.id,
      authorId: Number(item.user_id),
      author: item.username,
      initials: getInitials(item.username),
      avatarClass: pickAvatarClass(startIndex + index),
      avatarUrl: resolveAvatarUrl(item.avatar),
      time: formatTimeLabel(item.created_at),
      content: decoded.content,
      imageUrl: item.image_url,
      videoUrl: decoded.videoUrl,
      articleTitle: decoded.articleTitle,
      postType: decoded.postType,
      likes: Number(item.likes_count) || 0,
      comments: Number(item.comments_count) || 0,
    };
  });
}

/**
 * Retourne l'avatar à afficher pour un post.
 * Si l'auteur est l'utilisateur connecté, on applique son avatar courant.
 */
function getPostAvatarUrl(post: PassionPostItem): string {
  if (post.author.trim().toLowerCase() === currentUsername.value.trim().toLowerCase()) {
    return currentUserAvatarUrl.value;
  }
  return post.avatarUrl || "";
}

/**
 * Charge la liste d'abonnements de l'utilisateur connecté pour connaître l'état "suivi".
 */
async function loadSubscriptionsState(): Promise<void> {
  if (!token.value || !isAuthenticated.value) {
    subscribedPassionIds.value = [];
    currentUserId.value = null;
    return;
  }

  const [me, subscriptions, passions, myFeedPosts] = await Promise.all([
    fetchMe(token.value),
    fetchMySubscriptions(token.value),
    fetchMyPassions(token.value),
    fetchMyPassionsFeed(token.value, 100, 0),
  ]);

  currentUserId.value = me.id;
  currentUsername.value = me.username || "Utilisateur";
  mySubscriptions.value = subscriptions;
  myPassions.value = passions;
  myPostsCount.value = myFeedPosts.length;
  myPostCountsByPassionId.value = buildPostCountsByPassion(myFeedPosts);
  subscribedPassionIds.value = mySubscriptions.value
    .map((item) => Number(item.passion_page_id ?? item.id))
    .filter((value) => Number.isFinite(value));
  await loadDiscoverPassions();
}

/**
 * Charge la liste des passions à découvrir pour la sidebar droite.
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
 * Recharge complètement la page passion (détail + premiers posts).
 */
async function loadPassionPage(): Promise<void> {
  if (!passionId.value) {
    errorMessage.value = "Identifiant de passion invalide.";
    return;
  }

  loading.value = true;
  errorMessage.value = "";
  infoMessage.value = "";
  offset.value = 0;
  hasMore.value = true;

  try {
    const [passionData, firstPosts] = await Promise.all([
      fetchPassionById(passionId.value, token.value || undefined),
      fetchPostsByPassion(passionId.value, limit, 0, token.value || undefined),
    ]);
    passion.value = passionData;
    posts.value = mapPosts(firstPosts);
    offset.value = firstPosts.length;
    hasMore.value = firstPosts.length === limit;
    await loadSubscriptionsState();
  } catch (error) {
    errorMessage.value =
      error instanceof Error
        ? error.message
        : "Chargement de la passion impossible.";
  } finally {
    loading.value = false;
  }
}

/**
 * Charge la page suivante des posts de la passion.
 */
async function loadMorePosts(): Promise<void> {
  if (!passion.value || loadingMore.value || !hasMore.value) return;
  loadingMore.value = true;
  try {
    const nextPosts = await fetchPostsByPassion(
      passion.value.id,
      limit,
      offset.value,
      token.value || undefined,
    );
    posts.value.push(...mapPosts(nextPosts, posts.value.length));
    offset.value += nextPosts.length;
    hasMore.value = nextPosts.length === limit;
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Pagination impossible.";
  } finally {
    loadingMore.value = false;
  }
}

/**
 * Suit ou retire la passion affichée.
 */
async function toggleSubscription(): Promise<void> {
  if (!passion.value) return;
  if (!isAuthenticated.value || !token.value) {
    router.push("/login");
    return;
  }

  loadingSubscription.value = true;
  errorMessage.value = "";
  infoMessage.value = "";
  const targetId = Number(passion.value.id);

  try {
    if (isSubscribed.value) {
      await unsubscribeFromPassion(token.value, targetId);
      subscribedPassionIds.value = subscribedPassionIds.value.filter(
        (id) => id !== targetId,
      );
      infoMessage.value = "Abonnement retiré.";
      triggerSubscriptionPulse();
    } else {
      await subscribeToPassion(token.value, targetId);
      if (!subscribedPassionIds.value.includes(targetId)) {
        subscribedPassionIds.value.push(targetId);
      }
      infoMessage.value = "Passion suivie avec succès.";
      triggerSubscriptionPulse();
    }
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Action d'abonnement impossible.";
  } finally {
    loadingSubscription.value = false;
  }
}

/**
 * Ouvre le mode "Mon fil" dans la page feed.
 */
function openMyFeed(): void {
  router.push({ path: "/feed", query: { mode: "mine" } });
}

/**
 * Ouvre une passion via la sidebar.
 */
function openPassionDetail(passionPageId: number): void {
  router.push(`/passion/${passionPageId}`);
}

/**
 * Ouvre la page profil.
 */
function openProfilePage(): void {
  router.push("/profile");
}

/**
 * Ouvre un profil public via son identifiant.
 */
function openPublicProfileById(userId: number | null | undefined): void {
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

/**
 * Ouvre le profil public de l'auteur d'une publication.
 */
function openPostAuthorProfile(post: PassionPostItem): void {
  openPublicProfileById(post.authorId);
}

/**
 * Ouvre la page paramètres.
 */
function openSettingsPage(): void {
  router.push("/settings");
}

/**
 * Suit une passion depuis la sidebar droite.
 */
async function followPassion(passionItem: PassionPage): Promise<void> {
  if (!token.value || loadingSubscribe.value) return;
  loadingSubscribe.value = true;
  try {
    await subscribeToPassion(token.value, Number(passionItem.id));
    await loadSubscriptionsState();
    triggerSubscriptionPulse();
    if (passion.value && Number(passion.value.id) === Number(passionItem.id)) {
      infoMessage.value = "Passion suivie avec succès.";
    }
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Abonnement impossible.";
  } finally {
    loadingSubscribe.value = false;
  }
}

/**
 * Retire un abonnement depuis la sidebar droite.
 */
async function removeSubscription(passionPageId: number): Promise<void> {
  if (!token.value || loadingSubscribe.value) return;
  loadingSubscribe.value = true;
  try {
    await unsubscribeFromPassion(token.value, passionPageId);
    await loadSubscriptionsState();
    triggerSubscriptionPulse();
    if (passion.value && Number(passion.value.id) === Number(passionPageId)) {
      infoMessage.value = "Abonnement retiré.";
    }
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Désabonnement impossible.";
  } finally {
    loadingSubscribe.value = false;
  }
}

onMounted(() => {
  loadPassionPage();
});

watch(
  () => route.params.id,
  () => {
    loadPassionPage();
  },
);
</script>

<template>
  <main class="loom-shell grid gap-4 py-4 lg:grid-cols-[220px_minmax(0,1fr)_260px]">
    <FeedSidebar
      :username="currentUsername"
      :user-initials="currentUsername.charAt(0).toUpperCase()"
      :user-avatar-url="currentUserAvatarUrl"
      :stats="myFeedStats"
      :passions="topPassions"
      :is-my-feed-mode="false"
      :is-profile-mode="false"
      :is-settings-mode="false"
      :active-passion-id="passionId"
      @open-my-feed="openMyFeed"
      @open-profile="openProfilePage"
      @open-settings="openSettingsPage"
      @open-passion="openPassionDetail"
      @create-passion="openMyFeed"
    />

    <section class="min-w-0 space-y-3">
      <!-- Tiroir mobile : sidebars (profil, passions, à découvrir, abonnements). -->
      <MobileSidebarDrawer label="Mon espace">
        <FeedSidebar
          force-visible
          :username="currentUsername"
          :user-initials="currentUsername.charAt(0).toUpperCase()"
          :user-avatar-url="currentUserAvatarUrl"
          :stats="myFeedStats"
          :passions="topPassions"
          :is-my-feed-mode="false"
          :is-profile-mode="false"
          :is-settings-mode="false"
          :active-passion-id="passionId"
          @open-my-feed="openMyFeed"
          @open-profile="openProfilePage"
          @open-settings="openSettingsPage"
          @open-passion="openPassionDetail"
          @create-passion="openMyFeed"
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
            :key="`drawer-discover-${item.id}`"
            class="mb-1 flex items-center gap-2 rounded-lg p-1 hover:bg-primary/10"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary">
              <span class="material-symbols-outlined text-base">interests</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold">{{ item.name }}</p>
              <p class="truncate text-xs text-base-content/50">
                <button
                  type="button"
                  class="cursor-pointer transition hover:text-primary"
                  @click="openPublicProfileById(item.user_id)"
                >
                  @{{ item.username || "createur" }}
                </button>
              </p>
            </div>
            <button
              class="btn btn-xs shrink-0 cursor-pointer rounded-full btn-outline"
              @click="followPassion(item)"
            >
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
            :key="`drawer-sub-${sub.id}`"
            class="mb-1 flex items-center gap-2 rounded-lg p-1 hover:bg-primary/10"
          >
            <div class="avatar-initials h-8 w-8 shrink-0 rounded-full bg-base-300 text-xs text-base-content">
              {{ (sub.name || "P").charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold">{{ sub.name }}</p>
              <p class="truncate text-xs text-base-content/50">@{{ sub.username }}</p>
            </div>
            <button
              class="btn btn-xs shrink-0 cursor-pointer rounded-full btn-ghost"
              @click="removeSubscription(getSubscriptionPassionId(sub))"
            >
              Retirer
            </button>
          </div>
        </article>
      </MobileSidebarDrawer>

      <article class="loom-soft-card overflow-hidden">
        <div class="h-14 loom-brand-bg opacity-85"></div>
        <div class="px-4 pb-3 pt-2">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h1 class="text-lg font-semibold">
                {{ passion?.name || "Passion" }}
              </h1>
              <p class="mt-1 text-sm text-base-content/70">
                {{ passion?.description || "Aucune description pour le moment." }}
              </p>
              <p class="mt-1 text-xs text-base-content/50">
                Créée par
                <button
                  type="button"
                  class="cursor-pointer font-medium hover:text-primary"
                  @click="openPublicProfileById(passion?.user_id)"
                >
                  @{{ passion?.username || "utilisateur" }}
                </button>
              </p>
            </div>
            <button
              v-if="passion && !isOwner"
              class="btn btn-sm transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0"
              :class="[
                isSubscribed ? 'btn-outline' : 'btn-primary',
                { 'loom-like-pop': subscriptionActionPulse },
              ]"
              :disabled="loadingSubscription"
              @click="toggleSubscription"
            >
              {{
                loadingSubscription
                  ? "Traitement..."
                  : isSubscribed
                    ? "Ne plus suivre"
                    : "Suivre"
              }}
            </button>
          </div>
        </div>
      </article>

      <div
        v-if="errorMessage"
        class="rounded-lg border border-error/40 bg-error/10 px-3 py-2 text-xs text-error"
      >
        {{ errorMessage }}
      </div>
      <div
        v-if="infoMessage"
        class="rounded-lg border border-base-300 bg-base-100 px-3 py-2 text-xs text-base-content/70"
      >
        {{ infoMessage }}
      </div>

      <Transition name="loom-fade" mode="out-in">
        <div v-if="loading" key="passion-loading" class="space-y-3">
          <article
            v-for="item in 3"
            :key="`passion-post-skeleton-${item}`"
            class="loom-soft-card overflow-hidden p-4"
          >
            <div class="mb-3 flex items-start gap-3">
              <div class="h-10 w-10 animate-pulse rounded-full bg-base-300"></div>
              <div class="flex-1 space-y-2">
                <div class="h-3 w-32 animate-pulse rounded bg-base-300"></div>
                <div class="h-3 w-20 animate-pulse rounded bg-base-300"></div>
              </div>
            </div>
            <div class="space-y-2">
              <div class="h-3 w-full animate-pulse rounded bg-base-300"></div>
              <div class="h-3 w-10/12 animate-pulse rounded bg-base-300"></div>
            </div>
            <div class="mt-4 h-44 w-full animate-pulse rounded-lg bg-base-300/80"></div>
            <div class="mt-3 flex items-center gap-2">
              <div class="h-8 w-14 animate-pulse rounded bg-base-300"></div>
              <div class="h-8 w-14 animate-pulse rounded bg-base-300"></div>
            </div>
          </article>
        </div>
        <div v-else key="passion-content" class="space-y-3">
          <article
            v-for="post in posts"
            :key="post.id"
            class="loom-soft-card overflow-hidden"
          >
        <div class="p-4">
          <div class="mb-2 flex items-start gap-3">
            <button
              v-if="getPostAvatarUrl(post)"
              type="button"
              class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-200 transition hover:scale-[1.03]"
              @click="openPostAuthorProfile(post)"
            >
              <img :src="getPostAvatarUrl(post)" alt="Avatar auteur" class="h-full w-full object-cover" />
            </button>
            <button
              v-else
              type="button"
              class="avatar-initials h-10 w-10 shrink-0 rounded-full text-white"
              :class="post.avatarClass"
              @click="openPostAuthorProfile(post)"
            >
              {{ post.initials }}
            </button>
            <div class="min-w-0 flex-1">
              <div class="mt-0.5 flex min-w-0 flex-wrap items-center gap-1.5 text-xs">
                <button
                  type="button"
                  class="min-w-0 max-w-full truncate text-sm font-semibold transition hover:text-primary"
                  @click="openPostAuthorProfile(post)"
                >
                  {{ post.author }}
                </button>
                <button
                  type="button"
                  class="badge badge-sm badge-outline badge-primary cursor-pointer hover:opacity-80"
                  @click="openPassionDetail(passionId)"
                >
                  <span class="material-symbols-outlined loom-badge-icon">interests</span>
                  {{ passion?.name || "Passion" }}
                </button>
                <span
                  v-if="post.postType === 'article'"
                  class="badge badge-sm badge-outline badge-primary"
                >
                  <span class="material-symbols-outlined loom-badge-icon">article</span>
                  Article
                </span>
                <span class="whitespace-nowrap text-base-content/50">{{ post.time }}</span>
              </div>
            </div>
          </div>
          <p
            v-if="post.postType === 'article'"
            class="mb-3 mt-5 text-base font-semibold leading-tight"
          >
            {{ post.articleTitle || "Article" }}
          </p>
          <p
            v-if="post.postType !== 'article'"
            class="text-sm leading-relaxed whitespace-pre-line break-words"
          >
            {{ getDisplayedPostContent(post) }}
          </p>
          <button
            v-if="post.postType !== 'article' && shouldTruncatePostContent(post)"
            type="button"
            class="mt-2 text-xs font-semibold text-primary hover:opacity-80"
            @click="togglePostContent(post.id)"
          >
            {{ isPostContentExpanded(post.id) ? "Voir moins" : "Voir plus" }}
          </button>
        </div>

        <img
          v-if="post.postType === 'photo' && post.imageUrl"
          :src="post.imageUrl"
          alt="Image publication"
          class="h-64 w-full object-cover"
        />

        <div
          v-if="post.postType === 'video' && post.videoUrl"
          class="mx-4 mb-4 rounded-lg border border-base-300 bg-base-200 p-3"
        >
          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-base-content/60">
            Vidéo
          </p>
          <a
            :href="post.videoUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="link link-primary break-all text-sm"
          >
            {{ post.videoUrl }}
          </a>
        </div>

        <div
          v-if="post.postType === 'article'"
          class="mx-4 mb-4 overflow-hidden rounded-xl border border-base-300 bg-base-100"
        >
          <div
            v-if="!isPostContentExpanded(post.id)"
            class="space-y-0"
          >
            <img
              v-if="getArticlePreviewImageUrl(post)"
              :src="getArticlePreviewImageUrl(post)"
              alt="Couverture article"
              class="h-52 w-full object-cover"
            />
            <div class="space-y-2 p-4">
              <p class="text-sm leading-relaxed text-base-content/75 break-words">
                {{ getArticlePreviewText(post) }}
              </p>
              <button
                type="button"
                class="btn btn-ghost btn-xs inline-flex items-center gap-1 px-0 text-primary hover:bg-transparent"
                @click="togglePostContent(post.id)"
              >
                <span class="material-symbols-outlined text-sm">menu_book</span>
                Lire l'article
              </button>
            </div>
          </div>
          <div
            v-else
            class="space-y-2 p-4"
          >
            <div
              class="prose prose-sm max-w-none text-base-content/85 break-words"
              v-html="getSafeArticleHtml(post)"
            ></div>
            <button
              type="button"
              class="btn btn-ghost btn-xs inline-flex items-center gap-1 px-0 text-primary hover:bg-transparent"
              @click="togglePostContent(post.id)"
            >
              <span class="material-symbols-outlined text-sm">unfold_less</span>
              Réduire
            </button>
          </div>
        </div>

        <div class="flex items-center gap-4 border-t border-base-300 p-2 text-sm">
          <span class="inline-flex items-center gap-1 text-base-content/70 transition-transform duration-150 hover:scale-105">
            <span class="material-symbols-outlined text-base">favorite</span>
            {{ post.likes }}
          </span>
          <span class="inline-flex items-center gap-1 text-base-content/70 transition-transform duration-150 hover:scale-105">
            <span class="material-symbols-outlined text-base">chat_bubble</span>
            {{ post.comments }}
          </span>
        </div>
          </article>

          <div v-if="posts.length === 0" class="loom-soft-card p-4 text-sm text-base-content/70">
            Aucune publication dans cette passion pour le moment.
          </div>
        </div>
      </Transition>

      <div class="flex justify-center pt-2">
        <button
          v-if="hasMore && !loading"
          class="btn btn-outline btn-sm"
          :disabled="loadingMore"
          @click="loadMorePosts"
        >
          {{ loadingMore ? "Chargement..." : "Charger plus" }}
        </button>
      </div>
    </section>

    <aside class="hidden space-y-3 lg:block">
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
            <p class="text-xs text-base-content/50">
              <button
                type="button"
                class="cursor-pointer transition hover:text-primary"
                @click="openPublicProfileById(item.user_id)"
              >
                @{{ item.username || "createur" }}
              </button>
            </p>
          </div>
          <button
            class="btn btn-xs cursor-pointer rounded-full btn-outline transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0"
            :class="{ 'loom-like-pop': subscriptionActionPulse }"
            @click="followPassion(item)"
          >
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

<style scoped>
/* Uniformise la taille/optique des Material Symbols dans les badges. */
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

