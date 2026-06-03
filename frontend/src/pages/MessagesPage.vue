<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import FeedSidebar from "../components/FeedSidebar.vue";
import StatsWidget from "../components/StatsWidget.vue";
import {
  isAuthenticated,
  useAuthToken,
  useCurrentAvatar,
  useCurrentUsername,
} from "../composables/useAuth";
import { refreshUnreadCount } from "../composables/useUnreadMessages";
import { fetchMe, getApiBaseUrl } from "../services/auth.service";
import {
  fetchExplorePosts,
  fetchMyPassionsFeed,
  type ApiPost,
} from "../services/feed.service";
import {
  fetchMyPassions,
  fetchPublicPassions,
  type PassionPage,
} from "../services/passion.service";
import {
  fetchMySubscriptions,
  subscribeToPassion,
  type SubscriptionItem,
} from "../services/subscription.service";
import {
  fetchPrivateStats,
  fetchPrivateStatsDetails,
  type PrivateStats,
  type PrivateStatsDetails,
} from "../services/stats.service";
import {
  fetchConversation,
  fetchConversations,
  sendMessage,
  type ChatMessage,
  type Conversation,
} from "../services/message.service";

const route = useRoute();
const router = useRouter();
const token = useAuthToken();
const currentUsername = useCurrentUsername();
const currentAvatar = useCurrentAvatar();

interface SidebarPassionItem extends PassionPage {
  colorClass: string;
  postCount: number;
}

// --- Données des sidebars (gauche + droite), comme sur le Feed / Profil ---
const loadingSidebar = ref(true);
const myPassions = ref<PassionPage[]>([]);
const mySubscriptions = ref<SubscriptionItem[]>([]);
const discoverPassions = ref<PassionPage[]>([]);
const loadingDiscover = ref(false);
const myPostsCount = ref(0);
const myPostCountsByPassionId = ref<Record<number, number>>({});
const privateStats = ref<PrivateStats | null>(null);
const privateStatsDetails = ref<PrivateStatsDetails | null>(null);

// --- Données de la messagerie ---
const conversations = ref<Conversation[]>([]);
const loadingConversations = ref(true);
const activeUserId = ref<number | null>(null);
const activeUser = ref<{ id: number; username: string; avatar: string | null } | null>(null);
const messages = ref<ChatMessage[]>([]);
const canMessage = ref(true);
// Existe-t-il un abonnement commun ? (pour la note informative)
const hasLink = ref(true);
const loadingThread = ref(false);
const draft = ref("");
const sending = ref(false);
const sendError = ref("");
const threadRef = ref<HTMLElement | null>(null);
// Suivi du défilement : l'utilisateur est-il en bas + y a-t-il des messages non vus ?
const isNearBottom = ref(true);
const showNewMessagesPill = ref(false);

// --- Sélecteur "Nouveau message" ---
const myUserId = ref<number | null>(null);
const showCompose = ref(false);
const composeQuery = ref("");
const candidates = ref<{ id: number; username: string; avatar: string | null }[]>([]);
const loadingCandidates = ref(false);
const candidatesLoaded = ref(false);

let pollId: number | null = null;
const POLL_INTERVAL = 2500;

/* ============================ Helpers communs ============================ */

/** Résout l'URL d'un avatar (URL absolue ou nom de fichier). */
function resolveAvatarUrl(avatar: string | null | undefined): string {
  if (!avatar) return "";
  if (avatar.startsWith("http://") || avatar.startsWith("https://")) return avatar;
  return `${getApiBaseUrl()}/uploads/avatars/${avatar}`;
}

/** Couleur stable selon un index (puces de passion, avatars de secours). */
function pickAvatarClass(index: number): string {
  const palette = ["bg-primary", "bg-secondary", "bg-accent", "bg-info", "bg-success"];
  return palette[Math.abs(index) % palette.length];
}

/* ====================== Sidebars (données + actions) ===================== */

const initials = computed(() =>
  (currentUsername.value || "U").trim().charAt(0).toUpperCase(),
);
const avatarImageUrl = computed(() => resolveAvatarUrl(currentAvatar.value));

const myFeedStats = computed(() => ({
  passions: privateStats.value?.passions_total ?? myPassions.value.length,
  suivis: privateStats.value?.suivis_total ?? mySubscriptions.value.length,
  publications: privateStats.value?.publications_total ?? myPostsCount.value,
}));

const topPassions = computed<SidebarPassionItem[]>(() =>
  myPassions.value.map((passion, index) => ({
    ...passion,
    colorClass: pickAvatarClass(index),
    postCount: myPostCountsByPassionId.value[Number(passion.id)] || 0,
  })),
);

function getSubscriptionPassionId(item: SubscriptionItem): number {
  return Number(item.passion_page_id ?? item.id);
}

/** Regroupe le nombre de publications par passion (pour la sidebar). */
function buildPostCountsByPassion(apiPosts: ApiPost[]): Record<number, number> {
  return apiPosts.reduce<Record<number, number>>((accumulator, post) => {
    const passionId = Number(post.passion_page_id);
    if (!Number.isFinite(passionId)) return accumulator;
    accumulator[passionId] = (accumulator[passionId] || 0) + 1;
    return accumulator;
  }, {});
}

/** Charge la liste "Passions à découvrir". */
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

/** S'abonner à une passion proposée dans "à découvrir". */
async function followPassion(item: PassionPage): Promise<void> {
  if (!token.value) return;
  try {
    await subscribeToPassion(token.value, Number(item.id));
    discoverPassions.value = discoverPassions.value.filter((p) => p.id !== item.id);
  } catch {
    // silencieux
  }
}

/** Charge les données nécessaires aux sidebars. */
async function loadSidebarData(): Promise<void> {
  if (!token.value) return;
  loadingSidebar.value = true;
  try {
    const me = await fetchMe(token.value); // valide la session + récupère mon id
    myUserId.value = me.id;
    const [passions, subscriptions, myFeedPosts, stats, statsDetails] =
      await Promise.all([
        fetchMyPassions(token.value),
        fetchMySubscriptions(token.value),
        fetchMyPassionsFeed(token.value, 100, 0),
        fetchPrivateStats(token.value),
        fetchPrivateStatsDetails(token.value),
      ]);
    myPassions.value = passions;
    mySubscriptions.value = subscriptions;
    myPostsCount.value = myFeedPosts.length;
    myPostCountsByPassionId.value = buildPostCountsByPassion(myFeedPosts);
    privateStats.value = stats;
    privateStatsDetails.value = statsDetails;
    await loadDiscoverPassions();
  } catch {
    // silencieux : la messagerie reste utilisable même si une sidebar échoue
  } finally {
    loadingSidebar.value = false;
  }
}

// Navigation depuis la sidebar gauche (cohérente avec le reste de l'app)
function openMyFeed(): void {
  router.push({ path: "/feed", query: { mode: "subscriptions" } });
}
function openProfile(): void {
  router.push("/profile");
}
function openSettings(): void {
  router.push("/settings");
}
function openPassionDetail(passionId: number): void {
  router.push(`/passion/${passionId}`);
}

/* ============================== Messagerie ============================== */

function avatarUrl(avatar: string | null | undefined): string {
  return resolveAvatarUrl(avatar);
}
function initial(username: string | undefined): string {
  return (username || "?").charAt(0).toUpperCase();
}
function avatarColor(id: number): string {
  return pickAvatarClass(id);
}
function formatTime(value: string): string {
  const date = new Date(value.replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
}

async function scrollToBottom(): Promise<void> {
  await nextTick();
  if (threadRef.value) threadRef.value.scrollTop = threadRef.value.scrollHeight;
  isNearBottom.value = true;
  showNewMessagesPill.value = false;
}

/**
 * Détecte si l'utilisateur est (presque) en bas du fil pour décider d'auto-scroller
 * ou d'afficher la pastille "nouveaux messages".
 */
function onThreadScroll(): void {
  const el = threadRef.value;
  if (!el) return;
  isNearBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
  if (isNearBottom.value) showNewMessagesPill.value = false;
}

async function loadConversations(): Promise<void> {
  if (!token.value) return;
  try {
    conversations.value = await fetchConversations(token.value);
  } catch {
    // silencieux
  } finally {
    loadingConversations.value = false;
  }
}

async function openConversation(userId: number): Promise<void> {
  if (!token.value) return;
  activeUserId.value = userId;
  loadingThread.value = true;
  sendError.value = "";
  try {
    const thread = await fetchConversation(token.value, userId, 0);
    activeUser.value = thread.user;
    messages.value = thread.messages;
    canMessage.value = thread.can_message;
    hasLink.value = thread.has_link;
    await scrollToBottom();
    await Promise.all([loadConversations(), refreshUnreadCount()]);
  } catch {
    sendError.value = "Impossible de charger la conversation.";
  } finally {
    loadingThread.value = false;
  }
}

async function poll(): Promise<void> {
  if (!token.value) return;
  if (activeUserId.value !== null) {
    const lastId = messages.value.length ? messages.value[messages.value.length - 1].id : 0;
    try {
      const thread = await fetchConversation(token.value, activeUserId.value, lastId);
      if (thread.messages.length > 0) {
        const onlyMine = thread.messages.every((m) => m.from_me);
        messages.value.push(...thread.messages);
        // On auto-scrolle si l'utilisateur lit déjà le bas (ou si ce sont ses
        // propres messages) ; sinon on signale les nouveaux messages.
        if (isNearBottom.value || onlyMine) {
          await scrollToBottom();
        } else {
          showNewMessagesPill.value = true;
        }
      }
      canMessage.value = thread.can_message;
      hasLink.value = thread.has_link;
    } catch {
      // silencieux
    }
  }
  await loadConversations();
  await refreshUnreadCount();
}

async function send(): Promise<void> {
  const content = draft.value.trim();
  if (!content || !token.value || activeUserId.value === null || sending.value) return;
  sending.value = true;
  sendError.value = "";
  try {
    const message = await sendMessage(token.value, activeUserId.value, content);
    messages.value.push(message);
    draft.value = "";
    await scrollToBottom();
    await loadConversations();
  } catch (error) {
    sendError.value = error instanceof Error ? error.message : "Échec de l'envoi du message.";
  } finally {
    sending.value = false;
  }
}

function onComposerKeydown(event: KeyboardEvent): void {
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    send();
  }
}

function backToList(): void {
  activeUserId.value = null;
  activeUser.value = null;
  messages.value = [];
}

/**
 * Charge (une seule fois) la liste des personnes à qui écrire.
 * Source : auteurs des publications publiques (Explorer), dédoublonnés.
 */
async function loadCandidates(): Promise<void> {
  if (candidatesLoaded.value || loadingCandidates.value) return;
  loadingCandidates.value = true;
  try {
    const posts = await fetchExplorePosts(120, 0);
    const byId = new Map<number, { id: number; username: string; avatar: string | null }>();
    posts.forEach((post) => {
      const id = Number(post.user_id);
      if (!Number.isFinite(id) || id <= 0) return;
      if (id === myUserId.value) return; // pas soi-même
      if (!byId.has(id)) {
        byId.set(id, { id, username: post.username, avatar: post.avatar ?? null });
      }
    });
    candidates.value = Array.from(byId.values());
    candidatesLoaded.value = true;
  } finally {
    loadingCandidates.value = false;
  }
}

/** Ouvre le sélecteur "Nouveau message". */
function openCompose(): void {
  showCompose.value = true;
  composeQuery.value = "";
  loadCandidates();
}

/** Démarre une conversation avec la personne choisie. */
function startConversation(userId: number): void {
  showCompose.value = false;
  openConversation(userId);
}

/** Candidats filtrés par la recherche. */
const filteredCandidates = computed(() => {
  const query = composeQuery.value.trim().toLowerCase();
  if (!query) return candidates.value;
  return candidates.value.filter((user) =>
    user.username.toLowerCase().includes(query),
  );
});

// Ouverture via ?to=ID (depuis un profil)
watch(
  () => route.query.to,
  (to) => {
    const id = Number(to);
    if (Number.isFinite(id) && id > 0) openConversation(id);
  },
);

onMounted(async () => {
  if (!isAuthenticated.value || !token.value) {
    router.push("/login");
    return;
  }
  await Promise.all([loadConversations(), loadSidebarData()]);
  const to = Number(route.query.to);
  if (Number.isFinite(to) && to > 0) await openConversation(to);
  pollId = window.setInterval(poll, POLL_INTERVAL);
});

onBeforeUnmount(() => {
  if (pollId !== null) clearInterval(pollId);
});

// En dessous de xl, on n'affiche qu'un volet à la fois (liste OU fil)
const showList = computed(() => activeUserId.value === null);
</script>

<template>
  <main class="loom-shell grid gap-4 py-4 lg:grid-cols-[220px_minmax(0,1fr)_260px]">
    <!-- ===================== Sidebar gauche (navigation) ===================== -->
    <FeedSidebar
      :username="currentUsername"
      :user-initials="initials"
      :user-avatar-url="avatarImageUrl"
      :stats="myFeedStats"
      :passions="topPassions"
      :is-my-feed-mode="false"
      :is-profile-mode="false"
      :is-settings-mode="false"
      :active-passion-id="null"
      @open-my-feed="openMyFeed"
      @open-profile="openProfile"
      @open-settings="openSettings"
      @open-passion="openPassionDetail"
      @create-passion="openMyFeed"
    />

    <!-- ===================== Centre : messagerie ===================== -->
    <section class="min-w-0">
      <div
        class="loom-soft-card grid h-[calc(100vh-9.5rem)] grid-cols-1 overflow-hidden md:h-[calc(100vh-6.5rem)] xl:grid-cols-[280px_1fr]"
      >
        <!-- Liste des conversations -->
        <div
          class="flex flex-col border-base-300 xl:border-r"
          :class="showList ? 'flex' : 'hidden xl:flex'"
        >
          <div class="flex items-center gap-2 border-b border-base-300 px-4 py-3">
            <span class="material-symbols-outlined text-primary">chat</span>
            <h1 class="text-base font-bold">Messages</h1>
            <button
              type="button"
              class="btn btn-primary btn-xs ml-auto gap-1"
              @click="openCompose"
            >
              <span class="material-symbols-outlined text-sm">edit_square</span>
              Nouveau
            </button>
          </div>

          <div class="flex-1 overflow-y-auto">
            <div v-if="loadingConversations" class="space-y-2 p-3">
              <div v-for="n in 5" :key="n" class="h-14 animate-pulse rounded-xl bg-base-200"></div>
            </div>

            <div
              v-else-if="conversations.length === 0"
              class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-base-content/60"
            >
              <span class="material-symbols-outlined text-4xl">forum</span>
              <p class="text-sm">Aucune conversation pour l'instant.</p>
              <button
                type="button"
                class="btn btn-primary btn-sm mt-2 gap-1"
                @click="openCompose"
              >
                <span class="material-symbols-outlined text-base">edit_square</span>
                Nouveau message
              </button>
            </div>

            <button
              v-for="conv in conversations"
              :key="conv.user_id"
              type="button"
              class="flex w-full items-center gap-3 border-b border-base-200 px-3 py-3 text-left transition hover:bg-base-200"
              :class="{ 'bg-primary/10': conv.user_id === activeUserId }"
              @click="openConversation(conv.user_id)"
            >
              <div class="relative shrink-0">
                <img
                  v-if="avatarUrl(conv.avatar)"
                  :src="avatarUrl(conv.avatar)"
                  :alt="conv.username"
                  class="h-11 w-11 rounded-full object-cover"
                />
                <div
                  v-else
                  class="avatar-initials h-11 w-11 rounded-full text-white"
                  :class="avatarColor(conv.user_id)"
                >
                  {{ initial(conv.username) }}
                </div>
                <span
                  v-if="conv.unread_count > 0"
                  class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-bold text-error-content"
                >
                  {{ conv.unread_count }}
                </span>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <span class="truncate text-sm font-semibold">{{ conv.username }}</span>
                  <span class="shrink-0 text-[11px] text-base-content/50">{{ formatTime(conv.last_time) }}</span>
                </div>
                <p
                  class="truncate text-xs"
                  :class="conv.unread_count > 0 ? 'font-semibold text-base-content/80' : 'text-base-content/55'"
                >
                  <span v-if="conv.last_from_me" class="text-base-content/40">Vous : </span>{{ conv.last_content }}
                </p>
              </div>
            </button>
          </div>
        </div>

        <!-- Fil de discussion -->
        <section class="relative flex flex-col" :class="showList ? 'hidden xl:flex' : 'flex'">
          <div
            v-if="activeUserId === null"
            class="flex h-full flex-col items-center justify-center gap-3 p-8 text-center text-base-content/55"
          >
            <span class="material-symbols-outlined text-5xl text-primary/60">forum</span>
            <p class="text-sm">Sélectionne une conversation pour commencer à discuter.</p>
          </div>

          <template v-else>
            <div class="flex items-center gap-3 border-b border-base-300 px-4 py-3">
              <button
                type="button"
                class="btn btn-ghost btn-sm btn-square xl:hidden"
                aria-label="Retour"
                @click="backToList"
              >
                <span class="material-symbols-outlined">arrow_back</span>
              </button>
              <button
                v-if="activeUser"
                type="button"
                class="flex items-center gap-3"
                @click="router.push(`/u/${activeUser.id}`)"
              >
                <img
                  v-if="avatarUrl(activeUser.avatar)"
                  :src="avatarUrl(activeUser.avatar)"
                  :alt="activeUser.username"
                  class="h-9 w-9 rounded-full object-cover"
                />
                <div
                  v-else
                  class="avatar-initials h-9 w-9 rounded-full text-white"
                  :class="avatarColor(activeUser.id)"
                >
                  {{ initial(activeUser.username) }}
                </div>
                <span class="font-semibold transition hover:text-primary">{{ activeUser?.username }}</span>
              </button>

              <!-- Quitter la conversation (revient à la liste) -->
              <button
                type="button"
                class="btn btn-ghost btn-sm btn-square ml-auto"
                aria-label="Quitter la conversation"
                title="Quitter la conversation"
                @click="backToList"
              >
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>

            <!-- Note informative : aucun abonnement commun (mais l'envoi reste possible) -->
            <div
              v-if="canMessage && !hasLink"
              class="flex items-center gap-2 border-b border-base-300 bg-base-200/60 px-4 py-2 text-xs text-base-content/70"
            >
              <span class="material-symbols-outlined text-sm text-primary">info</span>
              Vous n'avez pas encore d'abonnement commun avec cette personne.
            </div>

            <div
              ref="threadRef"
              class="flex-1 space-y-2 overflow-y-auto p-4"
              @scroll="onThreadScroll"
            >
              <div v-if="loadingThread" class="flex justify-center py-6">
                <span class="loading loading-spinner text-primary"></span>
              </div>

              <div
                v-else-if="messages.length === 0"
                class="flex h-full items-center justify-center text-center text-sm text-base-content/50"
              >
                Aucun message. Écris le premier !
              </div>

              <div
                v-for="message in messages"
                :key="message.id"
                class="flex"
                :class="message.from_me ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm leading-snug shadow-sm"
                  :class="
                    message.from_me
                      ? 'rounded-br-md bg-primary text-primary-content'
                      : 'rounded-bl-md bg-base-200 text-base-content'
                  "
                >
                  <p class="whitespace-pre-wrap break-words">{{ message.content }}</p>
                  <p
                    class="mt-1 text-[10px]"
                    :class="message.from_me ? 'text-primary-content/70' : 'text-base-content/45'"
                  >
                    {{ formatTime(message.created_at) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Pastille "nouveaux messages" : visible quand on n'est pas en bas -->
            <Transition name="loom-fade">
              <button
                v-if="showNewMessagesPill"
                type="button"
                class="btn btn-primary btn-sm absolute bottom-20 left-1/2 z-10 -translate-x-1/2 gap-1 rounded-full shadow-lg"
                @click="scrollToBottom"
              >
                <span class="material-symbols-outlined text-base">arrow_downward</span>
                Nouveaux messages
              </button>
            </Transition>

            <div class="border-t border-base-300 p-3">
              <p v-if="sendError" class="mb-2 text-xs text-error">{{ sendError }}</p>
              <div
                v-if="!canMessage"
                class="rounded-xl bg-base-200 px-3 py-2 text-center text-xs text-base-content/60"
              >
                Tu ne peux pas écrire à cette personne (profil privé et aucun abonnement commun).
              </div>
              <div v-else class="flex items-end gap-2">
                <textarea
                  v-model="draft"
                  rows="1"
                  placeholder="Écris un message..."
                  class="textarea textarea-bordered max-h-32 min-h-[2.75rem] flex-1 resize-none rounded-xl"
                  @keydown="onComposerKeydown"
                ></textarea>
                <button
                  type="button"
                  class="btn btn-primary btn-square"
                  :disabled="!draft.trim() || sending"
                  aria-label="Envoyer"
                  @click="send"
                >
                  <span v-if="sending" class="loading loading-spinner loading-sm"></span>
                  <span v-else class="material-symbols-outlined">send</span>
                </button>
              </div>
            </div>
          </template>
        </section>
      </div>
    </section>

    <!-- ===================== Sidebar droite (stats + découverte) ===================== -->
    <aside class="hidden space-y-3 lg:block">
      <StatsWidget
        :details="privateStatsDetails"
        :publications="myFeedStats.publications"
        :loading="loadingSidebar"
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
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold">{{ item.name }}</p>
            <p class="truncate text-xs text-base-content/50">@{{ item.username || "createur" }}</p>
          </div>
          <button class="btn btn-xs rounded-full btn-outline" @click="followPassion(item)">
            Suivre
          </button>
        </div>
        <div
          v-if="!loadingDiscover && discoverPassions.length === 0"
          class="py-2 text-xs text-base-content/60"
        >
          Rien de nouveau à découvrir.
        </div>
      </article>
    </aside>

    <!-- ===================== Sélecteur "Nouveau message" ===================== -->
    <div
      v-if="showCompose"
      class="fixed inset-0 z-[60] flex items-start justify-center bg-black/40 p-4 pt-24"
      @click.self="showCompose = false"
    >
      <div class="loom-soft-card w-full max-w-md overflow-hidden">
        <div class="flex items-center gap-2 border-b border-base-300 px-4 py-3">
          <span class="material-symbols-outlined text-primary">edit_square</span>
          <h2 class="text-sm font-bold">Nouveau message</h2>
          <button
            type="button"
            class="btn btn-ghost btn-xs btn-square ml-auto"
            aria-label="Fermer"
            @click="showCompose = false"
          >
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="p-3">
          <input
            v-model="composeQuery"
            type="text"
            placeholder="Rechercher une personne..."
            class="input input-bordered w-full rounded-xl"
          />
        </div>
        <div class="max-h-80 overflow-y-auto px-2 pb-3">
          <div v-if="loadingCandidates" class="p-4 text-center text-sm text-base-content/60">
            Chargement...
          </div>
          <div
            v-else-if="filteredCandidates.length === 0"
            class="p-4 text-center text-sm text-base-content/60"
          >
            Aucune personne trouvée.
          </div>
          <button
            v-for="user in filteredCandidates"
            :key="user.id"
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-base-200"
            @click="startConversation(user.id)"
          >
            <img
              v-if="avatarUrl(user.avatar)"
              :src="avatarUrl(user.avatar)"
              :alt="user.username"
              class="h-9 w-9 rounded-full object-cover"
            />
            <div
              v-else
              class="avatar-initials h-9 w-9 rounded-full text-white"
              :class="avatarColor(user.id)"
            >
              {{ initial(user.username) }}
            </div>
            <span class="text-sm font-semibold">{{ user.username }}</span>
            <span class="material-symbols-outlined ml-auto text-base text-primary">chat</span>
          </button>
        </div>
      </div>
    </div>
  </main>
</template>
