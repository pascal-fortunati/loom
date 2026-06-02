<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import ArticleRichEditor from "../components/ArticleRichEditor.vue";
import FeedSidebar from "../components/FeedSidebar.vue";
import StatsWidget from "../components/StatsWidget.vue";
import {
  hydrateAuthUser,
  isAuthenticated,
  useAuthToken,
  useCurrentAvatar,
} from "../composables/useAuth";
import { fetchMe, getApiBaseUrl } from "../services/auth.service";
import { fetchMyPassionsFeed, type ApiPost } from "../services/feed.service";
import { fetchMyPassions, fetchPublicPassions, type PassionPage } from "../services/passion.service";
import {
  fetchMySubscriptions,
  subscribeToPassion,
  unsubscribeFromPassion,
  type SubscriptionItem,
} from "../services/subscription.service";
import { uploadAvatarImage } from "../services/upload.service";
import { fetchUserProfile, updateUserProfile, type UserProfile } from "../services/user.service";
import {
  fetchPrivateStats,
  fetchPrivateStatsDetails,
  type PrivateStats,
  type PrivateStatsDetails,
} from "../services/stats.service";

interface SidebarPassionItem extends PassionPage {
  colorClass: string;
  postCount: number;
}

const router = useRouter();
const token = useAuthToken();
const currentAuthAvatar = useCurrentAvatar();

const loading = ref(true);
const saving = ref(false);
const loadingDiscover = ref(false);
const loadingSubscribe = ref(false);
const uploadingAvatar = ref(false);
const showAvatarCropModal = ref(false);
const apiError = ref("");
const apiMessage = ref("");
const avatarPreviewUrl = ref("");
const avatarCropSourceUrl = ref("");
const avatarCropFileName = ref("avatar.jpg");
const avatarCropZoom = ref(1);
const avatarCropOffsetX = ref(0);
const avatarCropOffsetY = ref(0);
const avatarDragActive = ref(false);
const avatarDragLastX = ref(0);
const avatarDragLastY = ref(0);

const profile = ref<UserProfile | null>(null);
const myPassions = ref<PassionPage[]>([]);
const mySubscriptions = ref<SubscriptionItem[]>([]);
const discoverPassions = ref<PassionPage[]>([]);
const myPostsCount = ref(0);
const myPostCountsByPassionId = ref<Record<number, number>>({});
const privateStats = ref<PrivateStats | null>(null);
const privateStatsDetails = ref<PrivateStatsDetails | null>(null);

const form = ref({
  bio: "",
  avatar: currentAuthAvatar.value || "",
});

/**
 * Prépare un texte brut pour l'éditeur riche (retours à la ligne => <br>).
 */
function convertPlainTextToEditorHtml(value: string): string {
  const escaped = value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
  return escaped.replace(/\n/g, "<br>");
}

/**
 * Extrait un texte brut depuis du HTML pour stocker une bio propre.
 */
function extractPlainTextFromHtml(rawHtml: string): string {
  const parser = new DOMParser();
  const documentFragment = parser.parseFromString(rawHtml || "", "text/html");
  return (documentFragment.body.textContent || "").trim();
}

/**
 * Indicateur simple de complétion du profil public.
 */
const profileCompletion = computed(() => {
  let score = 35;
  if ((profile.value?.bio || "").trim().length >= 20) score += 35;
  if (avatarImageUrl.value) score += 30;
  return Math.min(score, 100);
});

/**
 * Construit les initiales pour l'avatar.
 */
const initials = computed(() =>
  (profile.value?.username || "U").trim().charAt(0).toUpperCase(),
);

/**
 * Résout l'URL d'avatar selon ce qui est stocké (URL absolue ou nom de fichier).
 */
function resolveAvatarUrl(avatar: string | null | undefined): string {
  if (!avatar) return "";
  if (avatar.startsWith("http://") || avatar.startsWith("https://")) return avatar;
  return `${getApiBaseUrl()}/uploads/avatars/${avatar}`;
}

/**
 * Image d'avatar à afficher dans l'interface (preview prioritaire).
 */
const avatarImageUrl = computed(() => {
  if (avatarPreviewUrl.value) return avatarPreviewUrl.value;
  return resolveAvatarUrl(form.value.avatar || profile.value?.avatar || currentAuthAvatar.value);
});

/**
 * Stats de la sidebar gauche.
 */
const myFeedStats = computed(() => ({
  passions: privateStats.value?.passions_total ?? myPassions.value.length,
  suivis: privateStats.value?.suivis_total ?? mySubscriptions.value.length,
  publications: privateStats.value?.publications_total ?? myPostsCount.value,
}));

/**
 * Couleur stable pour les puces de passion.
 */
function pickAvatarClass(index: number): string {
  const palette = ["bg-primary", "bg-secondary", "bg-accent", "bg-info", "bg-success"];
  return palette[index % palette.length];
}

/**
 * Retourne l'ID de passion d'un abonnement.
 */
function getSubscriptionPassionId(item: SubscriptionItem): number {
  return Number(item.passion_page_id ?? item.id);
}

/**
 * Liste des passions pour la sidebar gauche avec compteur de posts.
 */
const topPassions = computed<SidebarPassionItem[]>(() =>
  myPassions.value.map((passion, index) => ({
    ...passion,
    colorClass: pickAvatarClass(index),
    postCount: myPostCountsByPassionId.value[Number(passion.id)] || 0,
  })),
);

/**
 * Regroupe le nombre de publications par passion pour alimenter la sidebar.
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
 * Charge la liste "Passions à découvrir".
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
 * Charge toutes les données du profil et des sidebars.
 */
async function loadProfileData(): Promise<void> {
  if (!isAuthenticated.value || !token.value) {
    router.push("/login");
    return;
  }

  loading.value = true;
  apiError.value = "";
  apiMessage.value = "";

  try {
    const me = await fetchMe(token.value);
    const [
      profileData,
      passions,
      subscriptions,
      myFeedPosts,
      privateStatsData,
      privateStatsDetailsData,
    ] = await Promise.all([
      fetchUserProfile(me.id, token.value),
      fetchMyPassions(token.value),
      fetchMySubscriptions(token.value),
      fetchMyPassionsFeed(token.value, 100, 0),
      fetchPrivateStats(token.value),
      fetchPrivateStatsDetails(token.value),
    ]);

    profile.value = profileData;
    myPassions.value = passions;
    mySubscriptions.value = subscriptions;
    myPostsCount.value = myFeedPosts.length;
    myPostCountsByPassionId.value = buildPostCountsByPassion(myFeedPosts);
    privateStats.value = privateStatsData;
    privateStatsDetails.value = privateStatsDetailsData;
    form.value.bio = convertPlainTextToEditorHtml(profileData.bio || "");
    form.value.avatar = profileData.avatar || "";
    avatarPreviewUrl.value = "";
    await loadDiscoverPassions();
  } catch (error) {
    apiError.value =
      error instanceof Error ? error.message : "Chargement du profil impossible.";
  } finally {
    loading.value = false;
  }
}

/**
 * Enregistre les modifications du profil utilisateur.
 */
async function saveProfile(): Promise<void> {
  if (!token.value || !profile.value) return;

  const payload: { bio?: string; avatar?: string } = {};
  const cleanBio = extractPlainTextFromHtml(form.value.bio);
  const cleanAvatar = form.value.avatar.trim();

  payload.bio = cleanBio;
  if (cleanAvatar) payload.avatar = cleanAvatar;

  saving.value = true;
  apiError.value = "";
  apiMessage.value = "";
  try {
    const updated = await updateUserProfile(token.value, profile.value.id, payload);
    profile.value = updated;
    form.value.bio = convertPlainTextToEditorHtml(updated.bio || "");
    form.value.avatar = updated.avatar || "";
    await hydrateAuthUser();
    apiMessage.value = "Profil mis à jour avec succès.";
  } catch (error) {
    apiError.value =
      error instanceof Error ? error.message : "Mise à jour du profil impossible.";
  } finally {
    saving.value = false;
  }
}

/**
 * Lit un fichier local en Data URL pour l'aperçu dans la modal de recadrage.
 */
function readFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ""));
    reader.onerror = () => reject(new Error("Impossible de lire le fichier image."));
    reader.readAsDataURL(file);
  });
}

/**
 * Ouvre la modal de recadrage avec les réglages par défaut.
 */
async function handleAvatarFileSelection(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = "";
  if (!file) return;

  if (!file.type.startsWith("image/")) {
    apiError.value = "Le fichier sélectionné n'est pas une image.";
    return;
  }

  const maxBytes = 5 * 1024 * 1024;
  if (file.size > maxBytes) {
    apiError.value = "Image trop volumineuse (max 5 Mo).";
    return;
  }

  try {
    avatarCropSourceUrl.value = await readFileAsDataUrl(file);
    avatarCropFileName.value = file.name || "avatar.jpg";
    avatarCropZoom.value = 1;
    avatarCropOffsetX.value = 0;
    avatarCropOffsetY.value = 0;
    showAvatarCropModal.value = true;
    apiError.value = "";
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Préparation de l'image impossible.";
  }
}

/**
 * Ferme la modal de recadrage avatar et nettoie l'état temporaire.
 */
function closeAvatarCropModal(): void {
  showAvatarCropModal.value = false;
  avatarCropSourceUrl.value = "";
  avatarCropZoom.value = 1;
  avatarCropOffsetX.value = 0;
  avatarCropOffsetY.value = 0;
  avatarDragActive.value = false;
}

/**
 * Borne les offsets de recadrage pour rester dans une zone maîtrisée.
 */
function clampCropOffset(value: number): number {
  return Math.max(-80, Math.min(80, value));
}

/**
 * Démarre le déplacement de l'image dans l'aperçu de recadrage.
 */
function startAvatarDrag(event: PointerEvent): void {
  avatarDragActive.value = true;
  avatarDragLastX.value = event.clientX;
  avatarDragLastY.value = event.clientY;
}

/**
 * Déplace l'image recadrée en glisser-déposer.
 */
function moveAvatarDrag(event: PointerEvent): void {
  if (!avatarDragActive.value) return;
  const deltaX = event.clientX - avatarDragLastX.value;
  const deltaY = event.clientY - avatarDragLastY.value;
  avatarCropOffsetX.value = clampCropOffset(avatarCropOffsetX.value + deltaX);
  avatarCropOffsetY.value = clampCropOffset(avatarCropOffsetY.value + deltaY);
  avatarDragLastX.value = event.clientX;
  avatarDragLastY.value = event.clientY;
}

/**
 * Termine le glisser-déposer du recadrage.
 */
function stopAvatarDrag(): void {
  avatarDragActive.value = false;
}

/**
 * Construit un fichier avatar recadré au format carré (512x512).
 */
async function createCroppedAvatarFile(): Promise<File> {
  if (!avatarCropSourceUrl.value) {
    throw new Error("Aucune image à recadrer.");
  }

  const sourceImage = new Image();
  sourceImage.src = avatarCropSourceUrl.value;
  await new Promise<void>((resolve, reject) => {
    sourceImage.onload = () => resolve();
    sourceImage.onerror = () => reject(new Error("Chargement de l'image impossible."));
  });

  const outputSize = 512;
  const previewSize = 240;
  const canvas = document.createElement("canvas");
  canvas.width = outputSize;
  canvas.height = outputSize;
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    throw new Error("Canvas non disponible pour le recadrage.");
  }

  const baseScale = Math.max(outputSize / sourceImage.width, outputSize / sourceImage.height);
  const scale = baseScale * avatarCropZoom.value;
  const drawWidth = sourceImage.width * scale;
  const drawHeight = sourceImage.height * scale;
  const moveFactor = outputSize / previewSize;
  const drawX = (outputSize - drawWidth) / 2 + avatarCropOffsetX.value * moveFactor;
  const drawY = (outputSize - drawHeight) / 2 + avatarCropOffsetY.value * moveFactor;

  ctx.clearRect(0, 0, outputSize, outputSize);
  ctx.drawImage(sourceImage, drawX, drawY, drawWidth, drawHeight);

  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob((value) => resolve(value), "image/jpeg", 0.92);
  });
  if (!blob) {
    throw new Error("Export image impossible.");
  }

  const cleanBaseName = avatarCropFileName.value.replace(/\.[^/.]+$/, "") || "avatar";
  return new File([blob], `${cleanBaseName}_crop.jpg`, { type: "image/jpeg" });
}

/**
 * Applique le recadrage puis upload l'avatar et met à jour le profil.
 */
async function applyAvatarCropAndUpload(): Promise<void> {
  if (!token.value || !profile.value || uploadingAvatar.value) return;

  uploadingAvatar.value = true;
  apiError.value = "";
  apiMessage.value = "";
  try {
    const croppedFile = await createCroppedAvatarFile();
    const uploaded = await uploadAvatarImage(token.value, croppedFile);
    avatarPreviewUrl.value = uploaded.avatar_url;
    form.value.avatar = uploaded.avatar;

    const updatedProfile = await updateUserProfile(token.value, profile.value.id, {
      avatar: uploaded.avatar,
    });
    profile.value = updatedProfile;
    form.value.avatar = updatedProfile.avatar || uploaded.avatar;
    avatarPreviewUrl.value = resolveAvatarUrl(updatedProfile.avatar) || uploaded.avatar_url;
    await hydrateAuthUser();
    closeAvatarCropModal();
    apiMessage.value = "Avatar mis à jour avec succès.";
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Upload avatar impossible.";
  } finally {
    uploadingAvatar.value = false;
  }
}

/**
 * Ouvre la page "Mon fil" (zone centrale feed).
 */
function openMyFeed(): void {
  router.push({ path: "/feed", query: { mode: "mine" } });
}

/**
 * Ouvre une passion depuis la sidebar.
 */
function openPassionDetail(passionId: number): void {
  router.push(`/passion/${passionId}`);
}

/**
 * Ouvre la page paramètres.
 */
function openSettingsPage(): void {
  router.push("/settings");
}

/**
 * Action de suivi depuis la sidebar droite.
 */
async function followPassion(passion: PassionPage): Promise<void> {
  if (!token.value || loadingSubscribe.value) return;
  loadingSubscribe.value = true;
  try {
    await subscribeToPassion(token.value, Number(passion.id));
    await loadProfileData();
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Abonnement impossible.";
  } finally {
    loadingSubscribe.value = false;
  }
}

/**
 * Désabonne l'utilisateur d'une passion.
 */
async function removeSubscription(passionPageId: number): Promise<void> {
  if (!token.value || loadingSubscribe.value) return;
  loadingSubscribe.value = true;
  try {
    await unsubscribeFromPassion(token.value, passionPageId);
    await loadProfileData();
  } catch (error) {
    apiError.value = error instanceof Error ? error.message : "Désabonnement impossible.";
  } finally {
    loadingSubscribe.value = false;
  }
}

onMounted(() => {
  loadProfileData();
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
      :is-profile-mode="true"
      :is-settings-mode="false"
      :active-passion-id="null"
      @open-my-feed="openMyFeed"
      @open-profile="() => {}"
      @open-settings="openSettingsPage"
      @open-passion="openPassionDetail"
      @create-passion="openMyFeed"
    />

    <section class="min-w-0 space-y-3">
      <article class="loom-soft-card overflow-hidden">
        <div class="relative h-14 loom-brand-bg opacity-85">
          <div class="absolute inset-0 bg-gradient-to-r from-base-content/12 to-transparent"></div>
        </div>
        <div class="px-5 pb-5 pt-2">
          <div class="flex flex-wrap items-start gap-4">
            <div
              v-if="avatarImageUrl"
              class="h-14 w-14 overflow-hidden rounded-2xl border-2 border-base-100 bg-base-200 shadow-sm"
            >
              <img :src="avatarImageUrl" alt="Avatar utilisateur" class="h-full w-full object-cover" />
            </div>
            <div
              v-else-if="loading"
              class="h-14 w-14 animate-pulse rounded-2xl border-2 border-base-100 bg-base-300"
            ></div>
            <div
              v-else
              class="avatar-initials h-14 w-14 rounded-2xl border-2 border-base-100 bg-primary text-primary-content text-base"
            >
              {{ initials }}
            </div>
            <div class="flex-1">
              <h1 class="text-lg font-semibold">{{ profile?.username || "Mon profil" }}</h1>
              <p class="mt-1 text-sm text-base-content/70">{{ profile?.email || "" }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <span class="badge badge-outline">Passions: {{ myFeedStats.passions }}</span>
                <span class="badge badge-outline">Suivis: {{ myFeedStats.suivis }}</span>
                <span class="badge badge-outline">Profil: {{ profileCompletion }}%</span>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline" @click="openSettingsPage">
              <span class="material-symbols-outlined text-sm">settings</span>
              Paramètres compte
            </button>
          </div>
        </div>
      </article>

      <!-- Skeleton de chargement (cohérent avec le fil d'actualité) -->
      <div v-if="loading" class="space-y-3">
        <div class="loom-soft-card space-y-4 p-5">
          <div class="h-5 w-40 animate-pulse rounded-full bg-base-300"></div>
          <div class="h-3 w-2/3 animate-pulse rounded-full bg-base-300"></div>
          <div class="h-24 w-full animate-pulse rounded-xl bg-base-300/70"></div>
          <div class="h-10 w-32 animate-pulse rounded-lg bg-base-300"></div>
        </div>
        <div class="loom-soft-card space-y-3 p-5">
          <div class="h-4 w-48 animate-pulse rounded-full bg-base-300"></div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="h-28 animate-pulse rounded-xl bg-base-300/70"></div>
            <div class="h-28 animate-pulse rounded-xl bg-base-300/70"></div>
          </div>
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
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">Identité publique</h2>
            <p class="mt-1 text-sm text-base-content/70">
              Cette section correspond à ce que les autres utilisateurs voient sur ton profil.
            </p>
          </div>
          <div class="rounded-lg border border-base-300 bg-base-100 px-3 py-2 text-xs text-base-content/70">
            Compte et sécurité dans <span class="font-semibold">Paramètres</span>
          </div>
        </div>
        <p class="mt-1 text-sm text-base-content/70">
          Ajoute un avatar premium et personnalise ta bio.
        </p>

        <form class="mt-4 space-y-3" @submit.prevent="saveProfile">
          <div class="rounded-xl border border-base-300 bg-base-100 p-3">
            <p class="text-xs font-medium text-base-content/80">Avatar</p>
            <div class="mt-2 flex items-center gap-3">
              <div
                v-if="avatarImageUrl"
                class="h-14 w-14 overflow-hidden rounded-full border border-base-300 bg-base-200"
              >
                <img :src="avatarImageUrl" alt="Aperçu avatar" class="h-full w-full object-cover" />
              </div>
              <div
                v-else
                class="avatar-initials h-14 w-14 rounded-full border border-base-300 bg-primary text-primary-content"
              >
                {{ initials }}
              </div>
              <div class="space-y-1">
                <label class="btn btn-sm btn-outline">
                  <span class="material-symbols-outlined text-sm">upload</span>
                  {{ uploadingAvatar ? "Upload..." : "Changer mon avatar" }}
                  <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    class="hidden"
                    :disabled="uploadingAvatar"
                    @change="handleAvatarFileSelection"
                  />
                </label>
                <p class="text-xs text-base-content/60">PNG, JPG, WEBP, GIF - max 5 Mo</p>
              </div>
            </div>
          </div>

          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">Bio</span>
            <ArticleRichEditor
              v-model="form.bio"
              placeholder="Parle de tes passions en quelques lignes..."
              min-height-class="min-h-32"
            />
          </label>

          <div class="flex justify-end">
            <button :disabled="saving" class="btn btn-primary">
              <span class="material-symbols-outlined text-sm">save</span>
              {{ saving ? "Enregistrement..." : "Enregistrer" }}
            </button>
          </div>
        </form>
      </article>
    </section>

    <div class="modal" :class="{ 'modal-open': showAvatarCropModal }">
      <div class="modal-box max-w-xl">
        <h3 class="text-lg font-semibold">Recadrer mon avatar</h3>
        <p class="mt-1 text-sm text-base-content/70">
          Ajuste le cadrage puis valide pour enregistrer ton nouvel avatar.
        </p>

        <div class="mt-4 flex justify-center">
          <div
            class="h-60 w-60 overflow-hidden rounded-2xl border border-base-300 bg-base-200"
            :class="avatarDragActive ? 'cursor-grabbing' : 'cursor-grab'"
            @pointerdown="startAvatarDrag"
            @pointermove="moveAvatarDrag"
            @pointerup="stopAvatarDrag"
            @pointerleave="stopAvatarDrag"
            @pointercancel="stopAvatarDrag"
          >
            <img
              v-if="avatarCropSourceUrl"
              :src="avatarCropSourceUrl"
              alt="Prévisualisation recadrage"
              class="h-full w-full select-none object-cover transition-transform"
              draggable="false"
              :style="{
                transform: `translate(${avatarCropOffsetX}px, ${avatarCropOffsetY}px) scale(${avatarCropZoom})`,
              }"
            />
          </div>
        </div>

        <div class="mt-4 space-y-3">
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">Zoom</span>
            <input v-model.number="avatarCropZoom" type="range" min="1" max="2.8" step="0.01" class="range range-primary range-sm" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">Décalage horizontal</span>
            <input v-model.number="avatarCropOffsetX" type="range" min="-80" max="80" step="1" class="range range-sm" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80">Décalage vertical</span>
            <input v-model.number="avatarCropOffsetY" type="range" min="-80" max="80" step="1" class="range range-sm" />
          </label>
        </div>

        <div class="modal-action">
          <button type="button" class="btn btn-ghost" :disabled="uploadingAvatar" @click="closeAvatarCropModal">
            Annuler
          </button>
          <button type="button" class="btn btn-primary" :disabled="uploadingAvatar" @click="applyAvatarCropAndUpload">
            {{ uploadingAvatar ? "Upload..." : "Appliquer et enregistrer" }}
          </button>
        </div>
      </div>
      <div class="modal-backdrop" @click="closeAvatarCropModal"></div>
    </div>

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
          <button class="btn btn-xs rounded-full btn-outline" @click="followPassion(item)">
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
            class="btn btn-xs rounded-full btn-ghost"
            @click="removeSubscription(getSubscriptionPassionId(sub))"
          >
            Retirer
          </button>
        </div>
      </article>
    </aside>
  </main>
</template>
