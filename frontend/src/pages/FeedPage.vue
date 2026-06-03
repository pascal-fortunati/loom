<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import ArticleRichEditor from "../components/ArticleRichEditor.vue";
import FeedSidebar from "../components/FeedSidebar.vue";
import MobileSidebarDrawer from "../components/MobileSidebarDrawer.vue";
import PublicProfilePanel from "../components/PublicProfilePanel.vue";
import StatsWidget from "../components/StatsWidget.vue";
import { useAuthToken, useCurrentAvatar } from "../composables/useAuth";
import { fetchMe, getApiBaseUrl, rebaseApiUrl, type AuthUser } from "../services/auth.service";
import {
  fetchMyFeed,
  fetchMyPassionsFeed,
  type ApiPost,
} from "../services/feed.service";
import {
  createComment,
  deleteComment,
  fetchPostComments,
  updateComment,
} from "../services/comment.service";
import { likePost, unlikePost } from "../services/like.service";
import {
  createPassion,
  fetchMyPassions,
  fetchPublicPassions,
  type PassionPage,
  updatePassion,
} from "../services/passion.service";
import {
  fetchMySubscriptions,
  subscribeToPassion,
  unsubscribeFromPassion,
  type SubscriptionItem,
} from "../services/subscription.service";
import {
  fetchPrivateStats,
  fetchPrivateStatsDetails,
  type PrivateStats,
  type PrivateStatsDetails,
} from "../services/stats.service";
import { createPost, deletePost, updatePost } from "../services/post.service";
import { uploadPostImage } from "../services/upload.service";

type FeedFilter = string;
type FeedMode = "subscriptions" | "mine";
type ComposerMode = "photo" | "video" | "article";

interface PostItem {
  id: number;
  passionPageId: number;
  authorId?: number;
  author: string;
  initials: string;
  avatarClass: string;
  badgeClass: string;
  tag: FeedFilter;
  time: string;
  content: string;
  postType: ComposerMode;
  articleTitle?: string;
  videoUrl?: string;
  imageUrl?: string | null;
  avatarUrl?: string | null;
  likes: number;
  comments: number;
  liked: boolean;
}

interface CommentItem {
  id: number;
  userId: number;
  username: string;
  initials: string;
  content: string;
  time: string;
}

interface ToastItem {
  id: number;
  message: string;
  type: "success" | "error" | "info";
}

const token = useAuthToken();
const currentAuthAvatar = useCurrentAvatar();
const route = useRoute();
const router = useRouter();

const loadingPage = ref(false);
const loadingDiscover = ref(false);
const savingPassion = ref(false);
const creatingPost = ref(false);
const apiMessage = ref("");
const apiError = ref("");
const activeFilter = ref<FeedFilter>("Tous");
const activeFeedMode = ref<FeedMode>("subscriptions");

/**
 * Mode d'affichage central: feed par défaut, ou profil public.
 */
const activeCenterView = computed(() =>
  String(route.query.view || "").toLowerCase(),
);

/**
 * Indique si la colonne centrale affiche un profil public.
 */
const isPublicProfileMode = computed(() => activeCenterView.value === "profile");

/**
 * ID utilisateur ciblé pour le profil public embarqué dans le feed.
 */
const publicProfileUserId = computed<number | null>(() => {
  const parsed = Number(route.query.user);
  if (!Number.isFinite(parsed) || parsed <= 0) return null;
  return parsed;
});

const currentUser = ref<AuthUser | null>(null);
const posts = ref<PostItem[]>([]);
const myPosts = ref<PostItem[]>([]);
const myPassions = ref<PassionPage[]>([]);
const mySubscriptions = ref<SubscriptionItem[]>([]);
const privateStats = ref<PrivateStats | null>(null);
const privateStatsDetails = ref<PrivateStatsDetails | null>(null);
const discoverPassions = ref<PassionPage[]>([]);
const subscribingPassionIds = ref<number[]>([]);
const editingPassionId = ref<number | null>(null);
const showPassionModal = ref(false);
const openCommentsPostId = ref<number | null>(null);
const loadingCommentsPostId = ref<number | null>(null);
// Post à mettre en évidence (ex: arrivée depuis une notification de commentaire)
const highlightedPostId = ref<number | null>(null);
const commentsByPost = ref<Record<number, CommentItem[]>>({});
const commentDraftByPost = ref<Record<number, string>>({});
const editingCommentByPost = ref<Record<number, number | null>>({});
const editCommentDraftById = ref<Record<number, string>>({});
const selectedPostImage = ref<File | null>(null);
const selectedPostImagePreview = ref<string>("");
const fileInputRef = ref<HTMLInputElement | null>(null);
const toasts = ref<ToastItem[]>([]);
const expandedPostContent = ref<Record<number, boolean>>({});
const likePopByPost = ref<Record<number, boolean>>({});
const composerMode = ref<ComposerMode>("photo");
const showEditPostModal = ref(false);
const editingPost = ref<PostItem | null>(null);
const postForm = ref({
  passionPageId: 0,
  content: "",
});
const videoForm = ref({
  url: "",
});
const articleForm = ref({
  title: "",
  body: "",
});
const postEditForm = ref({
  content: "",
  articleTitle: "",
  videoUrl: "",
  articleBody: "",
});
const passionForm = ref({
  name: "",
  description: "",
});

/**
 * Calcule les initiales d'un nom utilisateur.
 */
function getInitials(name: string): string {
  return (name || "U").trim().charAt(0).toUpperCase();
}

/**
 * Renvoie une couleur stable d'avatar selon l'index.
 */
function pickAvatarClass(index: number): string {
  const palette = [
    "bg-primary",
    "bg-secondary",
    "bg-accent",
    "bg-info",
    "bg-success",
  ];
  return palette[index % palette.length];
}

/**
 * Renvoie un jeu de classes de couleur pour le badge d'une passion.
 * La couleur est déterminée par l'identifiant de la passion : ainsi une même
 * passion garde toujours la même couleur dans tout le fil (repère visuel,
 * façon réseau social). Classes complètes pour le scan de Tailwind v4.
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
 * Résout une valeur avatar en URL exploitable (URL absolue ou fichier local uploadé).
 */
function resolveAvatarUrl(avatarValue: string | null | undefined): string {
  if (!avatarValue) return "";
  if (avatarValue.startsWith("http://") || avatarValue.startsWith("https://")) return avatarValue;
  return `${getApiBaseUrl()}/uploads/avatars/${avatarValue}`;
}

/**
 * Formate un timestamp SQL en libellé court.
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
 * Affiche un toast temporaire DaisyUI.
 */
function showToast(message: string, type: ToastItem["type"] = "info"): void {
  const toastId = Date.now() + Math.floor(Math.random() * 1000);
  toasts.value.push({ id: toastId, message, type });
  setTimeout(() => {
    toasts.value = toasts.value.filter((item) => item.id !== toastId);
  }, 3500);
}

/**
 * Ouvre la sélection de fichier image pour le post.
 */
function triggerImagePicker(): void {
  fileInputRef.value?.click();
}

/**
 * Gère la sélection d'une image locale et génère un aperçu.
 */
function handlePostImageChange(event: Event): void {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;
  if (!file) return;

  const isImage = file.type.startsWith("image/");
  if (!isImage) {
    showToast("Le fichier sélectionné doit être une image.", "error");
    input.value = "";
    return;
  }

  const maxBytes = 5 * 1024 * 1024;
  if (file.size > maxBytes) {
    showToast("Image trop lourde (max 5 Mo).", "error");
    input.value = "";
    return;
  }

  selectedPostImage.value = file;
  selectedPostImagePreview.value = URL.createObjectURL(file);
}

/**
 * Réinitialise l'image sélectionnée dans le formulaire de publication.
 */
function clearSelectedPostImage(): void {
  if (selectedPostImagePreview.value) {
    URL.revokeObjectURL(selectedPostImagePreview.value);
  }
  selectedPostImage.value = null;
  selectedPostImagePreview.value = "";
  if (fileInputRef.value) {
    fileInputRef.value.value = "";
  }
}

/**
 * Upload une image pour l'insérer directement dans le contenu d'un article.
 */
async function uploadInlineArticleImage(file: File): Promise<string> {
  if (!token.value) {
    throw new Error("Connexion requise pour insérer une image.");
  }

  if (!file.type.startsWith("image/")) {
    throw new Error("Le fichier sélectionné doit être une image.");
  }

  const maxBytes = 5 * 1024 * 1024;
  if (file.size > maxBytes) {
    throw new Error("Image trop lourde (max 5 Mo).");
  }

  return uploadPostImage(token.value, file);
}

/**
 * Vérifie une URL HTTP/HTTPS simple.
 */
function isValidHttpUrl(value: string): boolean {
  try {
    const parsed = new URL(value);
    return parsed.protocol === "http:" || parsed.protocol === "https:";
  } catch {
    return false;
  }
}

/**
 * Encode un post vidéo dans le champ content sans changer la BDD.
 */
function buildVideoContent(url: string, text: string): string {
  return `__LOOM_VIDEO__${JSON.stringify({ url, text })}`;
}

/**
 * Encode un post article dans le champ content sans changer la BDD.
 */
function buildArticleContent(title: string, body: string): string {
  return `__LOOM_ARTICLE__${JSON.stringify({ title, body })}`;
}

/**
 * Supprime les balises dangereuses et nettoie les attributs HTML risqués.
 */
function sanitizeArticleHtml(rawHtml: string): string {
  if (!rawHtml) return "";
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(rawHtml, "text/html");

  documentFragment.querySelectorAll("script, style, iframe, object, embed").forEach((node) => {
    node.remove();
  });

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
 * Extrait un texte brut depuis du HTML pour vérifier le contenu réel.
 */
function extractPlainTextFromHtml(rawHtml: string): string {
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(rawHtml || "", "text/html");
  return (documentFragment.body.textContent || "").trim();
}

/**
 * Décode un post encodé (vidéo/article) en mode d'affichage.
 */
function decodePostContent(rawContent: string): {
  postType: ComposerMode;
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
 * Transforme les posts API vers le format d'affichage.
 */
function mapPosts(apiPosts: ApiPost[]): PostItem[] {
  return apiPosts.map((item, index) => {
    const decoded = decodePostContent(item.content);
    return {
      id: item.id,
      passionPageId: item.passion_page_id,
      authorId: Number(item.user_id),
      author: item.username,
      initials: getInitials(item.username),
      avatarClass: pickAvatarClass(index),
      badgeClass: pickPassionBadgeClass(item.passion_page_id),
      tag: item.passion_page_name || "Général",
      time: formatTimeLabel(item.created_at),
      content: decoded.content,
      postType: decoded.postType,
      articleTitle: decoded.articleTitle,
      videoUrl: decoded.videoUrl,
      imageUrl: item.image_url,
      avatarUrl: resolveAvatarUrl(item.avatar),
      likes: Number(item.likes_count) || 0,
      comments: Number(item.comments_count) || 0,
      liked: Boolean(Number(item.is_liked ?? 0)),
    };
  });
}

/**
 * Longueur max affichée par défaut avant "Voir plus".
 */
const postPreviewMaxLength = 220;
const articlePreviewMaxLength = 280;

/**
 * Indique si le texte d'un post doit être tronqué.
 */
function shouldTruncatePostContent(post: PostItem): boolean {
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
 * Retourne le texte à afficher (tronqué ou complet).
 */
function getDisplayedPostContent(post: PostItem): string {
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
 * Prépare un contenu HTML sûr pour l'éditeur à partir d'un texte brut.
 */
function convertPlainTextToEditorHtml(value: string): string {
  const escaped = value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
  return escaped.replace(/\n/g, "<br>");
}

/**
 * Alterne l'état "Voir plus / Voir moins" d'un post.
 */
function togglePostContent(postId: number): void {
  expandedPostContent.value = {
    ...expandedPostContent.value,
    [postId]: !isPostContentExpanded(postId),
  };
}

/**
 * Vérifie si le post affiché appartient à l'utilisateur connecté.
 */
function canManagePost(post: PostItem): boolean {
  const current = (currentUser.value?.username || "").trim().toLowerCase();
  const author = post.author.trim().toLowerCase();
  return Boolean(current) && current === author;
}

/**
 * Ouvre la modal d'édition en pré-remplissant les champs selon le type de post.
 */
function openEditPostModal(post: PostItem): void {
  editingPost.value = post;
  postEditForm.value.content = post.content;
  postEditForm.value.articleTitle = post.articleTitle || "";
  postEditForm.value.videoUrl = post.videoUrl || "";
  postEditForm.value.articleBody =
    post.postType === "article"
      ? post.content || ""
      : convertPlainTextToEditorHtml(post.content || "");
  showEditPostModal.value = true;
}

/**
 * Ferme la modal d'édition de post et nettoie le brouillon.
 */
function closeEditPostModal(): void {
  showEditPostModal.value = false;
  editingPost.value = null;
  postEditForm.value = {
    content: "",
    articleTitle: "",
    videoUrl: "",
    articleBody: "",
  };
}

/**
 * Construit le contenu final à envoyer à l'API pendant l'édition.
 */
function buildEditedContent(post: PostItem): string {
  const richHtml = sanitizeArticleHtml(postEditForm.value.articleBody || "");
  const plainContent = extractPlainTextFromHtml(richHtml);

  if (post.postType === "video") {
    const videoUrl = postEditForm.value.videoUrl.trim();
    if (!isValidHttpUrl(videoUrl)) {
      throw new Error("Ajoute une URL vidéo valide (http/https).");
    }
    return buildVideoContent(videoUrl, plainContent);
  }

  if (post.postType === "article") {
    const title = postEditForm.value.articleTitle.trim();
    if (!title || !plainContent) {
      throw new Error("Un article doit contenir un titre et un contenu.");
    }
    return buildArticleContent(title, richHtml);
  }

  if (!plainContent) {
    throw new Error("Le contenu du post ne peut pas être vide.");
  }
  return plainContent;
}

/**
 * Retourne le HTML article prêt à afficher dans la carte.
 */
function getSafeArticleHtml(post: PostItem): string {
  return sanitizeArticleHtml(post.content);
}

/**
 * Extrait la première image d'un article pour créer une couverture visuelle.
 */
function getArticlePreviewImageUrl(post: PostItem): string {
  if (post.postType !== "article") return "";
  const sanitizedHtml = sanitizeArticleHtml(post.content);
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(sanitizedHtml, "text/html");
  const firstImage = documentFragment.querySelector("img");
  return rebaseApiUrl(firstImage?.getAttribute("src") || "");
}

/**
 * Construit un extrait texte court d'article pour l'aperçu du fil.
 */
function getArticlePreviewText(post: PostItem): string {
  if (post.postType !== "article") return "";
  const plainText = extractPlainTextFromHtml(post.content);
  if (plainText.length <= articlePreviewMaxLength) return plainText;
  return `${plainText.slice(0, articlePreviewMaxLength).trimEnd()}...`;
}

/**
 * Enregistre les modifications d'un post.
 */
async function submitEditPost(): Promise<void> {
  if (!token.value || !editingPost.value) return;

  const targetPost = editingPost.value;
  try {
    const contentToSend = buildEditedContent(targetPost);
    await updatePost(token.value, targetPost.id, { content: contentToSend });
    showToast("Post mis à jour avec succès.", "success");
    closeEditPostModal();
    await loadPageData();
  } catch (error) {
    const message = error instanceof Error ? error.message : "Mise à jour du post impossible";
    showToast(message, "error");
  }
}

/**
 * Supprime un post avec confirmation utilisateur.
 */
async function removePost(postId: number): Promise<void> {
  if (!token.value) return;
  const confirmed = window.confirm("Supprimer ce post ?");
  if (!confirmed) return;

  try {
    await deletePost(token.value, postId);
    showToast("Post supprimé.", "success");
    await loadPageData();
  } catch (error) {
    const message = error instanceof Error ? error.message : "Suppression du post impossible";
    showToast(message, "error");
  }
}

/**
 * Déclenche une animation courte de "pop" sur le compteur de likes.
 */
function triggerLikePop(postId: number): void {
  likePopByPost.value = {
    ...likePopByPost.value,
    [postId]: false,
  };

  requestAnimationFrame(() => {
    likePopByPost.value = {
      ...likePopByPost.value,
      [postId]: true,
    };
    setTimeout(() => {
      likePopByPost.value = {
        ...likePopByPost.value,
        [postId]: false,
      };
    }, 240);
  });
}

/**
 * Avatar courant de l'utilisateur connecté.
 * Priorité à l'état auth global pour refléter immédiatement un changement de profil.
 */
const currentUserAvatarUrl = computed(() =>
  resolveAvatarUrl(currentAuthAvatar.value || currentUser.value?.avatar),
);

/**
 * Retourne l'avatar à afficher pour un post.
 * Si le post est de l'utilisateur connecté, on force son avatar actuel.
 */
function getPostAvatarUrl(post: PostItem): string {
  const normalizedAuthor = post.author.trim().toLowerCase();
  const normalizedCurrent = (currentUser.value?.username || "").trim().toLowerCase();
  if (normalizedAuthor && normalizedAuthor === normalizedCurrent) {
    return currentUserAvatarUrl.value;
  }
  return post.avatarUrl || "";
}

/**
 * Retourne l'identifiant de passion lié à un abonnement.
 * Compatibilité: certains endpoints renvoient `id`, d'autres `passion_page_id`.
 */
function getSubscriptionPassionId(item: SubscriptionItem): number {
  return Number(item.passion_page_id ?? item.id);
}

/**
 * Exclut les abonnements qui pointent vers mes propres passions.
 * Ainsi le bloc "suivis" ne compte que les passions d'autres utilisateurs.
 */
function filterExternalSubscriptions(
  subscriptions: SubscriptionItem[],
  passions: PassionPage[],
  currentUsername: string,
): SubscriptionItem[] {
  const myPassionIds = new Set(passions.map((passion) => Number(passion.id)));
  const normalizedCurrentUsername = currentUsername.trim().toLowerCase();
  return subscriptions.filter(
    (item) =>
      !myPassionIds.has(getSubscriptionPassionId(item)) &&
      item.username.trim().toLowerCase() !== normalizedCurrentUsername,
  );
}

/**
 * Normalise le mode de feed venant de l'URL.
 */
function normalizeFeedMode(rawMode: unknown): FeedMode {
  return rawMode === "mine" ? "mine" : "subscriptions";
}

/**
 * Charge les données principales authentifiées (profil, feed, sidebars).
 */
async function loadPageData(): Promise<void> {
  if (!token.value) return;
  loadingPage.value = true;
  apiError.value = "";
  apiMessage.value = "";

  try {
    // On charge toujours les 2 flux:
    // - flux abonnements (navbar "Fil")
    // - flux personnel (menu gauche "Mon fil")
    // Cela permet d'afficher des compteurs stables dans la sidebar.
    const subscriptionsFeedPromise = fetchMyFeed(token.value, 50, 0);
    const myFeedPromise = fetchMyPassionsFeed(token.value, 50, 0);

    const [
      me,
      subscriptionsFeedData,
      myFeedData,
      passions,
      subscriptions,
      privateStatsData,
      privateStatsDetailsData,
    ] =
      await Promise.all([
        fetchMe(token.value),
        subscriptionsFeedPromise,
        myFeedPromise,
        fetchMyPassions(token.value),
        fetchMySubscriptions(token.value),
        fetchPrivateStats(token.value),
        fetchPrivateStatsDetails(token.value),
      ]);

    currentUser.value = me;
    myPosts.value = mapPosts(myFeedData);
    posts.value =
      activeFeedMode.value === "mine"
        ? myPosts.value
        : mapPosts(subscriptionsFeedData);
    myPassions.value = passions;
    mySubscriptions.value = filterExternalSubscriptions(
      subscriptions,
      passions,
      me.username,
    );
    privateStats.value = privateStatsData;
    privateStatsDetails.value = privateStatsDetailsData;
    if (myPassions.value.length > 0) {
      const selectedIsStillValid = myPassions.value.some(
        (item) => item.id === postForm.value.passionPageId,
      );
      if (!selectedIsStillValid) {
        postForm.value.passionPageId = myPassions.value[0].id;
      }
    } else {
      postForm.value.passionPageId = 0;
    }

    if (!availableFilters.value.includes(activeFilter.value)) {
      activeFilter.value = "Tous";
    }
  } catch (error) {
    apiError.value =
      error instanceof Error ? error.message : "Erreur de chargement";
  } finally {
    loadingPage.value = false;
  }
}

/**
 * Charge la liste de passions publiques à découvrir.
 */
async function loadDiscoverPassions(): Promise<void> {
  loadingDiscover.value = true;
  try {
    const publicPassions = await fetchPublicPassions(12);
    const myPassionIds = new Set(myPassions.value.map((passion) => passion.id));
    const followedIds = new Set(
      mySubscriptions.value.map((item) => getSubscriptionPassionId(item)),
    );
    discoverPassions.value = publicPassions
      .filter((item) => !myPassionIds.has(item.id))
      .map((item) => ({ ...item, is_public: true }))
      .slice(0, 6);

    // Retire les passions déjà suivies de la découverte pour éviter la confusion.
    discoverPassions.value = discoverPassions.value.filter(
      (item) => !followedIds.has(item.id),
    );
  } finally {
    loadingDiscover.value = false;
  }
}

const availableFilters = computed<FeedFilter[]>(() => {
  const tags = Array.from(new Set(posts.value.map((post) => post.tag)));
  return ["Tous", ...tags];
});

const filteredPosts = computed(() => {
  if (activeFilter.value === "Tous") return posts.value;
  return posts.value.filter((post) => post.tag === activeFilter.value);
});

const userInitials = computed(() =>
  getInitials(currentUser.value?.username || "U"),
);

const myFeedStats = computed(() => ({
  passions: privateStats.value?.passions_total ?? myPassions.value.length,
  suivis: privateStats.value?.suivis_total ?? mySubscriptions.value.length,
  publications: privateStats.value?.publications_total ?? myPosts.value.length,
}));

/**
 * Index des publications personnelles par passion pour la sidebar gauche.
 */
const myPostCountsByPassionId = computed<Record<number, number>>(() =>
  myPosts.value.reduce<Record<number, number>>((accumulator, post) => {
    const passionId = Number(post.passionPageId);
    if (!Number.isFinite(passionId)) return accumulator;
    accumulator[passionId] = (accumulator[passionId] || 0) + 1;
    return accumulator;
  }, {}),
);

const topPassions = computed(() =>
  myPassions.value.map((passion, index) => ({
    ...passion,
    colorClass: pickAvatarClass(index),
    postCount: myPostCountsByPassionId.value[Number(passion.id)] || 0,
  })),
);

const feedModeLabel = computed(() =>
  activeFeedMode.value === "mine" ? "Mon Fil" : "Fil des abonnements",
);

async function toggleSubscribe(passion: PassionPage): Promise<void> {
  if (!token.value) return;
  const passionPageId = Number(passion.id);
  if (!passionPageId) {
    apiError.value = "Impossible de suivre cette passion (ID invalide).";
    return;
  }
  if (subscribingPassionIds.value.includes(passionPageId)) {
    return;
  }
  try {
    subscribingPassionIds.value.push(passionPageId);
    await subscribeToPassion(token.value, passionPageId);
    showToast(`Tu suis maintenant : ${passion.name}`, "success");
    // Retrait immédiat de la carte suivie pour éviter le double-clic et clarifier l'UX.
    discoverPassions.value = discoverPassions.value.filter(
      (item) => Number(item.id) !== passionPageId,
    );
    // Après un nouvel abonnement, on remet "Tous" pour afficher immédiatement les nouveaux posts.
    activeFilter.value = "Tous";

    // Le bouton "Suivre" alimente le flux abonnements (navbar "Fil"), donc on bascule dessus.
    if (activeFeedMode.value !== "subscriptions") {
      await router.push({ path: "/feed", query: { mode: "subscriptions" } });
    } else {
      await loadPageData();
      await loadDiscoverPassions();
    }
  } catch (error) {
    const message =
      error instanceof Error ? error.message : "Abonnement impossible";
    apiError.value = message;
    showToast(message, "error");
  } finally {
    subscribingPassionIds.value = subscribingPassionIds.value.filter(
      (id) => id !== passionPageId,
    );
  }
}

async function removeSubscription(passionPageId: number): Promise<void> {
  if (!token.value) return;
  try {
    await unsubscribeFromPassion(token.value, passionPageId);
    await loadPageData();
    await loadDiscoverPassions();
    showToast("Abonnement retiré.", "success");
  } catch (error) {
    const message =
      error instanceof Error ? error.message : "Désabonnement impossible";
    apiError.value = message;
    showToast(message, "error");
  }
}

/**
 * Publie un nouveau post sur une passion de l'utilisateur connecté.
 */
async function submitPost(): Promise<void> {
  if (!token.value) return;
  if (!postForm.value.passionPageId) {
    apiError.value = "Crée d'abord une passion avant de publier.";
    return;
  }

  let contentToSend = "";
  let imageUrlToSend: string | null = null;
  const baseContent = postForm.value.content.trim();
  const videoUrl = videoForm.value.url.trim();
  const articleTitle = articleForm.value.title.trim();
  const articleBodyHtml = sanitizeArticleHtml(articleForm.value.body);
  const articleBodyText = extractPlainTextFromHtml(articleBodyHtml);

  if (composerMode.value === "photo") {
    if (!baseContent && !selectedPostImage.value) {
      apiError.value = "Ajoute du texte ou une image pour publier.";
      return;
    }
    contentToSend = baseContent || "Publication photo";
  }

  if (composerMode.value === "video") {
    if (!isValidHttpUrl(videoUrl)) {
      apiError.value = "Ajoute une URL vidéo valide (http/https).";
      return;
    }
    contentToSend = buildVideoContent(videoUrl, baseContent);
    clearSelectedPostImage();
  }

  if (composerMode.value === "article") {
    if (!articleTitle || !articleBodyText) {
      apiError.value = "Un article doit contenir un titre et un contenu.";
      return;
    }
    contentToSend = buildArticleContent(articleTitle, articleBodyHtml);
    clearSelectedPostImage();
  }

  creatingPost.value = true;
  apiError.value = "";
  apiMessage.value = "";

  try {
    if (composerMode.value === "photo" && selectedPostImage.value) {
      imageUrlToSend = await uploadPostImage(token.value, selectedPostImage.value);
    }

    await createPost(token.value, {
      passion_page_id: postForm.value.passionPageId,
      content: contentToSend,
      image_url: imageUrlToSend,
    });
    postForm.value.content = "";
    videoForm.value.url = "";
    articleForm.value.title = "";
    articleForm.value.body = "";
    clearSelectedPostImage();
    showToast("Publication ajoutée avec succès.", "success");
    await loadPageData();
  } catch (error) {
    const message =
      error instanceof Error
        ? error.message
        : "Création de publication impossible";
    apiError.value = message;
    showToast(message, "error");
  } finally {
    creatingPost.value = false;
  }
}

/**
 * Ouvre le formulaire en mode création.
 */
function openCreatePassionForm(): void {
  editingPassionId.value = null;
  passionForm.value = {
    name: "",
    description: "",
  };
  showPassionModal.value = true;
}

/**
 * Ferme le formulaire de passion et nettoie l'état local.
 */
function cancelPassionForm(): void {
  showPassionModal.value = false;
  editingPassionId.value = null;
}

/**
 * Crée ou met à jour une passion selon le mode actif du formulaire.
 */
async function submitPassionForm(): Promise<void> {
  if (!token.value) return;

  const name = passionForm.value.name.trim();
  const description = passionForm.value.description.trim();
  if (name.length < 2) {
    apiError.value =
      "Le nom de la passion doit contenir au moins 2 caractères.";
    return;
  }

  savingPassion.value = true;
  apiError.value = "";
  apiMessage.value = "";

  try {
    if (editingPassionId.value) {
      await updatePassion(token.value, editingPassionId.value, {
        name,
        description,
        is_public: true,
      });
      showToast("Passion mise à jour avec succès.", "success");
    } else {
      await createPassion(token.value, {
        name,
        description,
        is_public: true,
      });
      showToast("Passion créée avec succès.", "success");
    }

    cancelPassionForm();
    await loadPageData();
    await loadDiscoverPassions();
  } catch (error) {
    const message =
      error instanceof Error
        ? error.message
        : "Opération sur la passion impossible";
    apiError.value = message;
    showToast(message, "error");
  } finally {
    savingPassion.value = false;
  }
}

async function toggleLike(postId: number): Promise<void> {
  if (!token.value) return;
  const targetPost = posts.value.find((post) => post.id === postId);
  if (!targetPost) return;

  apiError.value = "";
  try {
    if (targetPost.liked) {
      await unlikePost(token.value, postId);
      targetPost.liked = false;
      targetPost.likes = Math.max(0, targetPost.likes - 1);
      triggerLikePop(postId);
    } else {
      await likePost(token.value, postId);
      targetPost.liked = true;
      targetPost.likes += 1;
      triggerLikePop(postId);
    }
  } catch (error) {
    apiError.value =
      error instanceof Error ? error.message : "Action de like impossible";
  }
}

/**
 * Charge les commentaires d'un post.
 */
async function loadComments(postId: number): Promise<void> {
  loadingCommentsPostId.value = postId;
  try {
    const apiComments = await fetchPostComments(postId);
    commentsByPost.value[postId] = apiComments.map((item) => ({
      id: item.id,
      userId: item.user_id,
      username: item.username,
      initials: getInitials(item.username),
      content: item.content,
      time: formatTimeLabel(item.created_at),
    }));
  } catch (error) {
    apiError.value =
      error instanceof Error
        ? error.message
        : "Chargement des commentaires impossible";
  } finally {
    loadingCommentsPostId.value = null;
  }
}

/**
 * Ouvre/ferme les commentaires d'un post.
 */
async function toggleComments(postId: number): Promise<void> {
  if (openCommentsPostId.value === postId) {
    openCommentsPostId.value = null;
    return;
  }

  openCommentsPostId.value = postId;
  if (!commentsByPost.value[postId]) {
    await loadComments(postId);
  }
}

/**
 * Cible un post passé en paramètre d'URL (?post=ID), par exemple en arrivant
 * depuis une notification de commentaire : ouvre ses commentaires, fait défiler
 * jusqu'à lui et le met brièvement en évidence.
 */
async function focusTargetPostFromQuery(): Promise<void> {
  const targetId = Number(route.query.post);
  if (!Number.isFinite(targetId) || targetId <= 0) return;

  await nextTick();
  const exists = filteredPosts.value.some((post) => post.id === targetId);
  if (!exists) return; // le post n'est pas dans la liste chargée

  // Ouvre les commentaires du post ciblé
  if (openCommentsPostId.value !== targetId) {
    await toggleComments(targetId);
  }

  await nextTick();
  const element = document.getElementById(`post-${targetId}`);
  if (element) {
    element.scrollIntoView({ behavior: "smooth", block: "center" });
    highlightedPostId.value = targetId;
    window.setTimeout(() => {
      if (highlightedPostId.value === targetId) highlightedPostId.value = null;
    }, 2200);
  }
}

/**
 * Ajoute un commentaire sur un post.
 */
async function submitComment(postId: number): Promise<void> {
  if (!token.value) return;
  const content = (commentDraftByPost.value[postId] || "").trim();
  if (!content) return;

  try {
    const created = await createComment(token.value, postId, content);
    if (!commentsByPost.value[postId]) commentsByPost.value[postId] = [];
    commentsByPost.value[postId].push({
      id: created.id,
      userId: created.user_id,
      username: created.username,
      initials: getInitials(created.username),
      content: created.content,
      time: formatTimeLabel(created.created_at),
    });
    commentDraftByPost.value[postId] = "";
    showToast("Commentaire publié.", "success");

    const targetPost = posts.value.find((post) => post.id === postId);
    if (targetPost) targetPost.comments += 1;
  } catch (error) {
    apiError.value =
      error instanceof Error
        ? error.message
        : "Création du commentaire impossible";
    showToast(apiError.value, "error");
  }
}

/**
 * Vérifie si un commentaire appartient à l'utilisateur connecté.
 */
function canManageComment(comment: CommentItem): boolean {
  if (!currentUser.value) return false;
  return Number(comment.userId) === Number(currentUser.value.id);
}

/**
 * Active l'édition d'un commentaire.
 */
function startEditComment(postId: number, comment: CommentItem): void {
  editingCommentByPost.value[postId] = comment.id;
  editCommentDraftById.value[comment.id] = comment.content;
}

/**
 * Annule l'édition d'un commentaire.
 */
function cancelEditComment(postId: number): void {
  editingCommentByPost.value[postId] = null;
}

/**
 * Sauvegarde un commentaire édité.
 */
async function saveEditedComment(postId: number, commentId: number): Promise<void> {
  if (!token.value) return;
  const content = (editCommentDraftById.value[commentId] || "").trim();
  if (!content) {
    showToast("Le commentaire ne peut pas être vide.", "error");
    return;
  }

  try {
    const updated = await updateComment(token.value, commentId, content);
    commentsByPost.value[postId] = (commentsByPost.value[postId] || []).map(
      (item) =>
        item.id === commentId
          ? { ...item, content: updated.content, time: formatTimeLabel(updated.created_at) }
          : item,
    );
    editingCommentByPost.value[postId] = null;
    showToast("Commentaire mis à jour.", "success");
  } catch (error) {
    const message =
      error instanceof Error
        ? error.message
        : "Mise à jour du commentaire impossible";
    showToast(message, "error");
  }
}

/**
 * Supprime un commentaire.
 */
async function removeComment(postId: number, commentId: number): Promise<void> {
  if (!token.value) return;
  const confirmed = window.confirm("Supprimer ce commentaire ?");
  if (!confirmed) return;

  try {
    await deleteComment(token.value, commentId);
    const before = commentsByPost.value[postId] || [];
    commentsByPost.value[postId] = before.filter((item) => item.id !== commentId);
    const targetPost = posts.value.find((post) => post.id === postId);
    if (targetPost) {
      targetPost.comments = Math.max(0, targetPost.comments - 1);
    }
    showToast("Commentaire supprimé.", "success");
  } catch (error) {
    const message =
      error instanceof Error
        ? error.message
        : "Suppression du commentaire impossible";
    showToast(message, "error");
  }
}

/**
 * Active le mode "Mon Fil" depuis le menu de la sidebar.
 */
async function openMyFeedMode(): Promise<void> {
  await router.push({ path: "/feed", query: { mode: "mine" } });
}

/**
 * Ouvre la page dédiée d'une passion.
 */
function openPassionDetail(passionId: number): void {
  router.push(`/passion/${passionId}`);
}

/**
 * Ouvre la page profil du compte connecté.
 */
function openProfilePage(): void {
  router.push("/profile");
}

/**
 * Ouvre un profil public via l'identifiant utilisateur.
 */
function openPublicProfileById(userId: number | null | undefined): void {
  if (!userId || !Number.isFinite(Number(userId))) return;
  router.push({
    path: "/feed",
    query: {
      mode: activeFeedMode.value,
      view: "profile",
      user: String(Number(userId)),
    },
  });
}

/**
 * Ouvre le profil public de l'auteur du post.
 */
function openPostAuthorProfile(post: PostItem): void {
  openPublicProfileById(post.authorId);
}

/**
 * Ouvre la page paramètres.
 */
function openSettingsPage(): void {
  router.push("/settings");
}

/**
 * Ferme le panneau profil public et revient à l'affichage normal du feed.
 */
function closePublicProfileMode(): void {
  router.push({
    path: "/feed",
    query: { mode: activeFeedMode.value },
  });
}

watch(
  () => route.query.mode,
  async (mode) => {
    activeFeedMode.value = normalizeFeedMode(mode);
    await loadPageData();
    await loadDiscoverPassions();
    await focusTargetPostFromQuery();
  },
  { immediate: true },
);

// Si seul ?post=ID change (déjà sur le feed), on cible sans tout recharger
watch(
  () => route.query.post,
  () => {
    focusTargetPostFromQuery();
  },
);
</script>

<template>
  <main
    class="loom-shell grid gap-4 py-4 lg:grid-cols-[220px_minmax(0,1fr)_260px]"
  >
    <FeedSidebar
      :username="currentUser?.username || 'Utilisateur'"
      :user-initials="userInitials"
      :user-avatar-url="currentUserAvatarUrl"
      :stats="myFeedStats"
      :passions="topPassions"
      :is-my-feed-mode="activeFeedMode === 'mine'"
      :is-profile-mode="false"
      :is-settings-mode="false"
      :active-passion-id="null"
      @create-passion="openCreatePassionForm"
      @open-my-feed="openMyFeedMode"
      @open-profile="openProfilePage"
      @open-settings="openSettingsPage"
      @open-passion="openPassionDetail"
    />

    <section class="min-w-0 space-y-3">
      <!-- Tiroir mobile : regroupe les sidebars (profil, stats, passions, abonnements). -->
      <MobileSidebarDrawer label="Mon espace">
        <FeedSidebar
          force-visible
          :username="currentUser?.username || 'Utilisateur'"
          :user-initials="userInitials"
          :user-avatar-url="currentUserAvatarUrl"
          :stats="myFeedStats"
          :passions="topPassions"
          :is-my-feed-mode="activeFeedMode === 'mine'"
          :is-profile-mode="false"
          :is-settings-mode="false"
          :active-passion-id="null"
          @create-passion="openCreatePassionForm"
          @open-my-feed="openMyFeedMode"
          @open-profile="openProfilePage"
          @open-settings="openSettingsPage"
          @open-passion="openPassionDetail"
        />

        <StatsWidget
          :details="privateStatsDetails"
          :publications="myFeedStats.publications"
          :loading="loadingPage"
        />

        <article class="loom-soft-card p-3">
          <div class="relative mb-2 overflow-hidden rounded-md border border-base-300/80 bg-base-200/70 px-2 py-1">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/8 to-transparent"></div>
            <p class="relative text-[10px] font-semibold uppercase tracking-wider text-base-content/65">
              Passions à découvrir
            </p>
          </div>
          <div v-if="loadingDiscover" class="py-2 text-xs text-base-content/60">
            Chargement...
          </div>
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
              :disabled="subscribingPassionIds.includes(Number(item.id))"
              @click="toggleSubscribe(item)"
            >
              {{ subscribingPassionIds.includes(Number(item.id)) ? "Suivi..." : "Suivre" }}
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
              {{ getInitials(sub.name) }}
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

      <PublicProfilePanel
        v-if="isPublicProfileMode && publicProfileUserId"
        :user-id="publicProfileUserId"
        @close="closePublicProfileMode"
        @open-passion="openPassionDetail"
        @open-private-profile="openProfilePage"
        @open-settings="openSettingsPage"
      />

      <template v-else>
      <article class="loom-soft-card p-3">
        <form class="space-y-2" @submit.prevent="submitPost">
          <div class="flex items-center gap-2">
            <div
              v-if="currentUserAvatarUrl"
              class="h-9 w-9 overflow-hidden rounded-full border border-base-300 bg-base-200"
            >
              <img :src="currentUserAvatarUrl" alt="Avatar utilisateur" class="h-full w-full object-cover" />
            </div>
            <div
              v-else
              class="avatar-initials h-9 w-9 rounded-full bg-primary text-primary-content"
            >
              {{ userInitials }}
            </div>
            <input
              v-model="postForm.content"
              type="text"
              class="input input-bordered h-10 w-full"
              :disabled="myPassions.length === 0"
              placeholder="Quoi de neuf dans tes passions ?"
            />
          </div>

          <div class="flex flex-col gap-2 pt-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 flex-wrap items-center gap-4 text-sm text-base-content/80">
              <button
                type="button"
                class="inline-flex items-center gap-1"
                :class="
                  composerMode === 'photo'
                    ? 'text-primary font-semibold'
                    : 'hover:text-primary'
                "
                @click="composerMode = 'photo'"
              >
                <span class="material-symbols-outlined text-base">image</span>
                Photo
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1"
                :class="
                  composerMode === 'video'
                    ? 'text-primary font-semibold'
                    : 'hover:text-primary'
                "
                @click="composerMode = 'video'"
              >
                <span class="material-symbols-outlined text-base">videocam</span>
                Vidéo
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1"
                :class="
                  composerMode === 'article'
                    ? 'text-primary font-semibold'
                    : 'hover:text-primary'
                "
                @click="composerMode = 'article'"
              >
                <span class="material-symbols-outlined text-base">article</span>
                Article
              </button>
              <button
                v-if="composerMode === 'photo'"
                type="button"
                class="inline-flex items-center gap-1 hover:text-primary"
                @click="triggerImagePicker"
              >
                <span class="material-symbols-outlined text-base">upload</span>
                Ajouter image
              </button>
            </div>
            <div class="flex items-center justify-between gap-2 sm:justify-end">
              <select
                v-model.number="postForm.passionPageId"
                class="select select-bordered select-sm w-40 max-w-[55%] sm:max-w-none"
                :disabled="myPassions.length === 0"
              >
                <option :value="0" disabled>
                  {{ myPassions.length === 0 ? "Aucune passion" : "Passion" }}
                </option>
                <option
                  v-for="passion in myPassions"
                  :key="passion.id"
                  :value="passion.id"
                >
                  {{ passion.name }}
                </option>
              </select>

              <button
                :disabled="creatingPost || myPassions.length === 0"
                class="btn btn-primary btn-sm min-w-24"
              >
                {{ creatingPost ? "Publication..." : "Publier" }}
              </button>
            </div>
          </div>

          <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handlePostImageChange"
          />

          <div
            v-if="composerMode === 'photo' && selectedPostImagePreview"
            class="relative overflow-hidden rounded-lg border border-base-300"
          >
            <img
              :src="selectedPostImagePreview"
              alt="Aperçu image publication"
              class="h-48 w-full object-cover"
            />
            <button
              type="button"
              class="btn btn-xs btn-circle absolute right-2 top-2"
              @click="clearSelectedPostImage"
            >
              ✕
            </button>
          </div>

          <div v-if="composerMode === 'video'" class="space-y-2">
            <input
              v-model="videoForm.url"
              type="url"
              class="input input-bordered w-full"
              placeholder="URL vidéo (YouTube, Vimeo, etc.)"
            />
            <p class="text-xs text-base-content/60">
              Astuce: ajoute un texte dans le champ principal pour décrire la vidéo.
            </p>
          </div>

          <div v-if="composerMode === 'article'" class="space-y-2">
            <input
              v-model="articleForm.title"
              type="text"
              class="input input-bordered w-full"
              placeholder="Titre de l'article"
            />
            <ArticleRichEditor
              v-model="articleForm.body"
              placeholder="Contenu de l'article"
              min-height-class="min-h-52"
              :on-upload-image="uploadInlineArticleImage"
            />
          </div>
        </form>
      </article>

      <div class="flex min-w-0 items-center justify-between gap-2">
        <div class="badge badge-outline badge-primary shrink-0">
          {{ feedModeLabel }}
        </div>
        <div class="min-w-0 flex flex-1 gap-2 overflow-x-auto pb-1">
          <button
            v-for="filter in availableFilters"
            :key="filter"
            class="btn btn-xs shrink-0 rounded-full border border-base-300 bg-base-100 text-xs"
            :class="{
              'btn-primary border-primary text-primary-content':
                activeFilter === filter,
            }"
            @click="activeFilter = filter"
          >
            {{ filter }}
          </button>
        </div>
      </div>

      <Transition name="loom-fade" mode="out-in">
        <div v-if="loadingPage" key="feed-loading" class="space-y-3">
          <article
            v-for="item in 3"
            :key="`feed-skeleton-${item}`"
            class="loom-soft-card overflow-hidden p-4"
          >
            <div class="mb-3 flex items-start gap-3">
              <div class="h-10 w-10 animate-pulse rounded-full bg-base-300"></div>
              <div class="flex-1 space-y-2">
                <div class="h-3 w-32 animate-pulse rounded bg-base-300"></div>
                <div class="h-3 w-24 animate-pulse rounded bg-base-300"></div>
              </div>
            </div>
            <div class="space-y-2">
              <div class="h-3 w-full animate-pulse rounded bg-base-300"></div>
              <div class="h-3 w-11/12 animate-pulse rounded bg-base-300"></div>
            </div>
            <div class="mt-4 h-44 w-full animate-pulse rounded-lg bg-base-300/80"></div>
            <div class="mt-3 flex items-center gap-2">
              <div class="h-8 w-16 animate-pulse rounded bg-base-300"></div>
              <div class="h-8 w-16 animate-pulse rounded bg-base-300"></div>
            </div>
          </article>
        </div>
        <div v-else key="feed-content" class="space-y-3">
          <div
            v-if="filteredPosts.length === 0"
            class="loom-soft-card p-4 text-sm text-base-content/70"
          >
            {{
              activeFeedMode === "mine"
                ? "Ton fil personnel est vide. Publie dans une de tes passions."
                : "Ton fil d'abonnements est vide. Abonne-toi à des passions pour voir des publications."
            }}
          </div>

          <article
            v-for="post in filteredPosts"
            :id="`post-${post.id}`"
            :key="post.id"
            class="loom-soft-card overflow-hidden transition-shadow"
            :class="{ 'loom-highlight': highlightedPostId === post.id }"
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
              class="avatar-initials h-10 w-10 shrink-0 rounded-full text-white"
              :class="post.avatarClass"
              @click="openPostAuthorProfile(post)"
            >
              {{ post.initials }}
            </button>
            <div class="min-w-0 flex-1">
              <button
                type="button"
                class="block max-w-full truncate text-sm font-semibold transition hover:text-primary"
                @click="openPostAuthorProfile(post)"
              >
                {{ post.author }}
              </button>
              <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                <button
                  :class="[
                    'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold transition hover:brightness-110',
                    post.badgeClass,
                  ]"
                  @click="openPassionDetail(post.passionPageId)"
                >
                  <span class="material-symbols-outlined loom-badge-icon">interests</span>
                  {{ post.tag }}
                </button>
                <span
                  v-if="post.postType === 'article'"
                  class="inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-200 px-2 py-0.5 text-xs font-semibold text-base-content/70"
                >
                  <span class="material-symbols-outlined loom-badge-icon">article</span>
                  Article
                </span>
                <span class="text-base-content/50">· {{ post.time }}</span>
              </div>
            </div>
            <div v-if="canManagePost(post)" class="inline-flex items-center gap-1">
              <button
                type="button"
                class="btn btn-ghost btn-xs transition-transform duration-150 hover:scale-105"
                @click="openEditPostModal(post)"
              >
                <span class="material-symbols-outlined text-sm">edit</span>
              </button>
              <button
                type="button"
                class="btn btn-ghost btn-xs text-error transition-transform duration-150 hover:scale-105"
                @click="removePost(post.id)"
              >
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
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
            class="text-sm leading-relaxed break-words"
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
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/60">
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
        <div class="flex items-center gap-1 border-t border-base-300 p-2">
          <button
            class="btn btn-ghost btn-sm gap-1.5 transition-transform duration-150 hover:scale-105 hover:text-error active:scale-95"
            :class="{ 'text-error': post.liked }"
            @click="toggleLike(post.id)"
          >
            <span
              class="material-symbols-outlined text-base"
              :class="[
                { 'loom-symbol-fill': post.liked },
                { 'loom-like-pop': likePopByPost[post.id] },
              ]"
              >favorite</span
            >
            <span :class="{ 'loom-like-pop': likePopByPost[post.id] }">{{ post.likes }}</span>
          </button>
          <button
            class="btn btn-ghost btn-sm transition-transform duration-150 hover:scale-105 active:scale-95"
            :class="{
              'text-primary bg-primary/10': openCommentsPostId === post.id,
            }"
            @click="toggleComments(post.id)"
          >
            <span class="material-symbols-outlined text-base">chat_bubble</span>
            {{ post.comments }}
          </button>
        </div>
        <div
          v-if="openCommentsPostId === post.id"
          class="space-y-2 border-t border-base-300 bg-base-100 p-3"
        >
          <div
            v-if="loadingCommentsPostId === post.id"
            class="text-xs text-base-content/60"
          >
            Chargement des commentaires...
          </div>
          <div
            v-else-if="(commentsByPost[post.id] || []).length === 0"
            class="text-xs text-base-content/60"
          >
            Aucun commentaire pour le moment.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="comment in commentsByPost[post.id]"
              :key="comment.id"
              class="rounded-lg bg-base-200 p-2"
            >
              <div class="mb-1 flex items-center gap-2 text-xs">
                <span class="avatar-initials h-5 w-5 rounded-full bg-base-300">
                  {{ comment.initials }}
                </span>
                <span class="font-semibold">{{ comment.username }}</span>
                <span class="text-base-content/50">· {{ comment.time }}</span>
                <div
                  v-if="canManageComment(comment)"
                  class="ml-auto inline-flex items-center gap-1"
                >
                  <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    @click="startEditComment(post.id, comment)"
                  >
                    <span class="material-symbols-outlined text-sm">edit</span>
                  </button>
                  <button
                    type="button"
                    class="btn btn-ghost btn-xs text-error"
                    @click="removeComment(post.id, comment.id)"
                  >
                    <span class="material-symbols-outlined text-sm">delete</span>
                  </button>
                </div>
              </div>
              <div v-if="editingCommentByPost[post.id] === comment.id" class="space-y-2">
                <textarea
                  v-model="editCommentDraftById[comment.id]"
                  class="textarea textarea-bordered w-full text-sm"
                  rows="2"
                ></textarea>
                <div class="flex justify-end gap-2">
                  <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    @click="cancelEditComment(post.id)"
                  >
                    Annuler
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary btn-xs"
                    @click="saveEditedComment(post.id, comment.id)"
                  >
                    Enregistrer
                  </button>
                </div>
              </div>
              <p v-else class="text-sm text-base-content/85">{{ comment.content }}</p>
            </div>
          </div>
          <form
            class="flex items-center gap-2 pt-1"
            @submit.prevent="submitComment(post.id)"
          >
            <input
              v-model="commentDraftByPost[post.id]"
              type="text"
              class="input input-bordered input-sm w-full"
              placeholder="Écrire un commentaire..."
            />
            <button class="btn btn-primary btn-sm">Envoyer</button>
          </form>
        </div>
          </article>
        </div>
      </Transition>
      </template>
    </section>

    <aside class="hidden space-y-3 lg:block">
      <StatsWidget
        :details="privateStatsDetails"
        :publications="myFeedStats.publications"
        :loading="loadingPage"
      />

      <article class="loom-soft-card p-3">
        <div class="relative mb-2 overflow-hidden rounded-md border border-base-300/80 bg-base-200/70 px-2 py-1">
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/8 to-transparent"></div>
          <p class="relative text-[10px] font-semibold uppercase tracking-wider text-base-content/65">
            Passions à découvrir
          </p>
        </div>
        <div v-if="loadingDiscover" class="py-2 text-xs text-base-content/60">
          Chargement...
        </div>
        <div
          v-for="item in discoverPassions"
          :key="item.id"
          class="mb-1 flex items-center gap-2 rounded-lg p-1 hover:bg-primary/10"
        >
          <div
            class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/15 text-primary"
          >
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
            :disabled="subscribingPassionIds.includes(Number(item.id))"
            @click="toggleSubscribe(item)"
          >
            {{
              subscribingPassionIds.includes(Number(item.id))
                ? "Suivi..."
                : "Suivre"
            }}
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
        <div
          v-if="mySubscriptions.length === 0"
          class="text-xs text-base-content/60"
        >
          Aucun abonnement pour le moment.
        </div>
        <div
          v-for="sub in mySubscriptions.slice(0, 6)"
          :key="`sub-${sub.id}`"
          class="mb-1 flex items-center gap-2 rounded-lg p-1 hover:bg-primary/10"
        >
          <div
            class="avatar-initials h-8 w-8 rounded-full bg-base-300 text-xs text-base-content"
          >
            {{ getInitials(sub.name) }}
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

    <div class="modal" :class="{ 'modal-open': showPassionModal }">
      <div class="modal-box">
        <h3 class="text-lg font-semibold text-base-content">
          {{ editingPassionId ? "Modifier la passion" : "Créer une passion" }}
        </h3>
        <p class="mt-1 text-sm text-base-content/65">
          Renseigne les informations de ta page de passion.
        </p>

        <form class="mt-4 space-y-3" @submit.prevent="submitPassionForm">
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">
              Nom
            </span>
            <input
              v-model="passionForm.name"
              type="text"
              class="input input-bordered w-full"
              placeholder="Ex: Gaming"
            />
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">
              Description
            </span>
            <textarea
              v-model="passionForm.description"
              class="textarea textarea-bordered w-full"
              rows="3"
              placeholder="Décris le contenu de ta passion"
            ></textarea>
          </label>

          <div class="modal-action mt-4">
            <button
              type="button"
              class="btn btn-ghost"
              @click="cancelPassionForm"
            >
              Annuler
            </button>
            <button :disabled="savingPassion" class="btn btn-primary">
              {{
                savingPassion
                  ? "Enregistrement..."
                  : editingPassionId
                    ? "Enregistrer"
                    : "Créer"
              }}
            </button>
          </div>
        </form>
      </div>
      <div class="modal-backdrop" @click="cancelPassionForm"></div>
    </div>

    <div class="modal" :class="{ 'modal-open': showEditPostModal }">
      <div class="modal-box w-11/12 max-w-5xl">
        <h3 class="text-lg font-semibold">Modifier la publication</h3>
        <p class="mt-1 text-sm text-base-content/65">
          Mets à jour le contenu de ton post puis enregistre.
        </p>

        <form class="mt-4 space-y-3" @submit.prevent="submitEditPost">
          <label v-if="editingPost?.postType === 'video'" class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">URL vidéo</span>
            <input
              v-model="postEditForm.videoUrl"
              type="url"
              class="input input-bordered w-full"
              placeholder="https://..."
            />
          </label>

          <label v-if="editingPost?.postType === 'article'" class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">Titre article</span>
            <input
              v-model="postEditForm.articleTitle"
              type="text"
              class="input input-bordered w-full"
              placeholder="Titre"
            />
          </label>

          <div class="space-y-2">
            <span class="block text-xs font-medium text-base-content/80">
              {{
                editingPost?.postType === "article"
                  ? "Contenu de l'article"
                  : "Contenu"
              }}
            </span>
            <ArticleRichEditor
              v-model="postEditForm.articleBody"
              placeholder="Écris le contenu de l'article..."
              min-height-class="min-h-52"
              :on-upload-image="
                editingPost?.postType === 'article'
                  ? uploadInlineArticleImage
                  : undefined
              "
            />
          </div>

          <div class="modal-action mt-4">
            <button type="button" class="btn btn-ghost" @click="closeEditPostModal">
              Annuler
            </button>
            <button class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
      <div class="modal-backdrop" @click="closeEditPostModal"></div>
    </div>

    <div class="toast toast-top toast-end z-50">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="alert"
        :class="{
          'alert-success': toast.type === 'success',
          'alert-error': toast.type === 'error',
          'alert-info': toast.type === 'info',
        }"
      >
        <span>{{ toast.message }}</span>
      </div>
    </div>
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
