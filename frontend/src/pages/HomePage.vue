<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { isAuthenticated } from "../composables/useAuth";
import { fetchExplorePosts, type ApiPost } from "../services/feed.service";
import { fetchPublicPassions, type PassionPage } from "../services/passion.service";
import { fetchPublicStats, type PublicStats } from "../services/stats.service";

const router = useRouter();

interface FeedPreviewItem {
  passion: string;
  icon: string;
  author: string;
  time: string;
  content: string;
  likes: number;
}

interface PopularPassionItem {
  icon: string;
  label: string;
  count: string;
}

const fallbackFeedPreview: FeedPreviewItem[] = [
  {
    passion: "Gaming",
    icon: "sports_esports",
    author: "Marie D.",
    time: "5 min",
    content: "Enfin terminé Elden Ring DLC après 142h… une masterpiece absolue.",
    likes: 247,
  },
  {
    passion: "Cuisine",
    icon: "restaurant",
    author: "Thomas B.",
    time: "40 min",
    content: "Ramen tonkotsu maison : 18h de bouillon, résultat incroyable.",
    likes: 184,
  },
  {
    passion: "Sport",
    icon: "directions_run",
    author: "Sofia M.",
    time: "1h",
    content: "Premier semi-marathon en 1h52 — 6 mois d'entraînement payés !",
    likes: 312,
  },
];

const steps = [
  {
    icon: "person_add",
    number: "01",
    title: "Crée ton profil",
    text: "Un seul profil, autant de pages de passions que tu veux. Chaque univers a son espace dédié.",
    badge: "bg-primary text-primary-content",
    ghost: "text-primary/15",
  },
  {
    icon: "interests",
    number: "02",
    title: "Suis des passions, pas des gens",
    text: "Tu t'abonnes à la passion Cuisine de Thomas, pas à toute sa vie. Ton fil ne contient que ce qui t'intéresse.",
    badge: "bg-secondary text-secondary-content",
    ghost: "text-secondary/15",
  },
  {
    icon: "dynamic_feed",
    number: "03",
    title: "Profite d'un fil 100 % utile",
    text: "Fini le contenu parasite. Chaque publication correspond à une passion que tu as choisie de suivre.",
    badge: "bg-accent text-accent-content",
    ghost: "text-accent/15",
  },
];

const comparison = [
  { label: "Fil ciblé par passion",                          loom: true,  classic: false },
  { label: "Abonnement sélectif (pas toute une personne)",   loom: true,  classic: false },
  { label: "Zéro contenu hors-sujet",                        loom: true,  classic: false },
  { label: "Publication organisée par thématique",           loom: true,  classic: false },
  { label: "Exploration publique sans compte",               loom: true,  classic: true  },
  { label: "Likes & commentaires",                           loom: true,  classic: true  },
];

const fallbackPopularPassions: PopularPassionItem[] = [
  { icon: "sports_esports", label: "Gaming",     count: "4.8k posts" },
  { icon: "restaurant",     label: "Cuisine",    count: "3.1k posts" },
  { icon: "directions_run", label: "Sport",      count: "2.6k posts" },
  { icon: "photo_camera",   label: "Photo",      count: "2.2k posts" },
  { icon: "flight",         label: "Voyage",     count: "1.9k posts" },
  { icon: "music_note",     label: "Musique",    count: "1.7k posts" },
  { icon: "yard",           label: "Jardinage",  count: "980 posts"  },
  { icon: "mode_night",     label: "Astronomie", count: "760 posts"  },
];

const homePosts = ref<ApiPost[]>([]);
const homePublicPassions = ref<PassionPage[]>([]);
const homePublicStats = ref<PublicStats | null>(null);

/**
 * Formate un nombre en version compacte pour le hero.
 */
function formatCompactCount(value: number): string {
  if (value >= 10000) return `${Math.round(value / 1000)}k`;
  if (value >= 1000) return `${(value / 1000).toFixed(1)}k`;
  return String(value);
}

/**
 * Formate une date API en libellé court.
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
 * Tronque un texte sans couper l'UX de lecture.
 */
function truncateText(value: string, maxLength = 120): string {
  const normalized = value.trim();
  if (normalized.length <= maxLength) return normalized;
  return `${normalized.slice(0, maxLength).trimEnd()}...`;
}

/**
 * Extrait un texte lisible depuis le format de contenu stocké (photo, vidéo, article).
 */
function extractPostPreviewText(rawContent: string): string {
  if (!rawContent) return "Publication Loom";

  if (rawContent.startsWith("__LOOM_VIDEO__")) {
    try {
      const parsed = JSON.parse(rawContent.replace("__LOOM_VIDEO__", ""));
      return truncateText(String(parsed.text ?? "Publication vidéo"));
    } catch {
      return "Publication vidéo";
    }
  }

  if (rawContent.startsWith("__LOOM_ARTICLE__")) {
    try {
      const parsed = JSON.parse(rawContent.replace("__LOOM_ARTICLE__", ""));
      const articleTitle = String(parsed.title ?? "Article");
      const articleBody = String(parsed.body ?? "");
      const articleDocument = new DOMParser().parseFromString(articleBody, "text/html");
      const plainText = articleDocument.body.textContent || "";
      return truncateText(`${articleTitle} — ${plainText}`);
    } catch {
      return "Article Loom";
    }
  }

  return truncateText(rawContent);
}

/**
 * Associe une icône Material Symbol selon le nom de passion.
 */
function getPassionIcon(name: string): string {
  const normalizedName = name.toLowerCase();
  if (normalizedName.includes("game")) return "sports_esports";
  if (normalizedName.includes("cuisine") || normalizedName.includes("food")) return "restaurant";
  if (normalizedName.includes("sport") || normalizedName.includes("run")) return "directions_run";
  if (normalizedName.includes("photo")) return "photo_camera";
  if (normalizedName.includes("voyage") || normalizedName.includes("travel")) return "flight";
  if (normalizedName.includes("music")) return "music_note";
  return "interests";
}

/**
 * Palette de couleurs DaisyUI tournante pour donner de la vie aux passions.
 * On stocke des classes complètes (et non concaténées) pour rester compatible
 * avec le scan de classes de Tailwind v4. Toutes les couleurs sont des tokens
 * de thème, donc le rendu reste cohérent en clair comme en sombre.
 */
const passionPalette = [
  "border-primary/25 bg-primary/10 text-primary",
  "border-secondary/25 bg-secondary/10 text-secondary",
  "border-accent/25 bg-accent/10 text-accent",
  "border-info/25 bg-info/10 text-info",
  "border-success/25 bg-success/10 text-success",
  "border-warning/25 bg-warning/10 text-warning",
];

/**
 * Renvoie un jeu de classes de couleur stable selon la position de la passion.
 */
function passionColor(index: number): string {
  return passionPalette[index % passionPalette.length];
}

/**
 * Construit l'aperçu du feed sur Home depuis les vrais posts d'exploration.
 */
function buildFeedPreview(posts: ApiPost[]): FeedPreviewItem[] {
  return posts.slice(0, 3).map((post) => ({
    passion: post.passion_page_name || "Passion",
    icon: getPassionIcon(post.passion_page_name || ""),
    author: post.username || "Membre Loom",
    time: formatTimeLabel(post.created_at),
    content: extractPostPreviewText(post.content),
    likes: Number(post.likes_count) || 0,
  }));
}

/**
 * Construit les passions populaires à partir des données publiques API.
 */
function buildPopularPassions(passions: PassionPage[], posts: ApiPost[]): PopularPassionItem[] {
  const postsCountByPassionId = posts.reduce<Record<number, number>>((accumulator, post) => {
    const key = Number(post.passion_page_id);
    if (!Number.isFinite(key)) return accumulator;
    accumulator[key] = (accumulator[key] || 0) + 1;
    return accumulator;
  }, {});

  return passions
    .map((passion) => {
      const postCount = postsCountByPassionId[Number(passion.id)] || 0;
      return {
        icon: getPassionIcon(passion.name),
        label: passion.name,
        count: `${postCount} post${postCount > 1 ? "s" : ""}`,
        score: postCount,
      };
    })
    .sort((first, second) => second.score - first.score)
    .slice(0, 8)
    .map(({ icon, label, count }) => ({ icon, label, count }));
}

/**
 * Charge les données réelles utilisées par la Home.
 */
async function loadHomeData(): Promise<void> {
  try {
    const [posts, passions, stats] = await Promise.all([
      fetchExplorePosts(80, 0),
      fetchPublicPassions(80),
      fetchPublicStats(),
    ]);
    homePosts.value = posts;
    homePublicPassions.value = passions;
    homePublicStats.value = stats;
  } catch {
    // Fallback silencieux: la page conserve les données de secours déjà présentes.
  }
}

const feedPreview = computed<FeedPreviewItem[]>(() =>
  homePosts.value.length > 0 ? buildFeedPreview(homePosts.value) : fallbackFeedPreview,
);

const popularPassions = computed<PopularPassionItem[]>(() =>
  homePublicPassions.value.length > 0
    ? buildPopularPassions(homePublicPassions.value, homePosts.value)
    : fallbackPopularPassions,
);

const fallbackMembersCount = computed(() => {
  const usernames = new Set<string>();
  homePosts.value.forEach((post) => {
    if (post.username) usernames.add(post.username.toLowerCase());
  });
  homePublicPassions.value.forEach((passion) => {
    if (passion.username) usernames.add(passion.username.toLowerCase());
  });
  return formatCompactCount(usernames.size);
});

const fallbackPublicationsCount = computed(() =>
  formatCompactCount(homePosts.value.length),
);

const fallbackPassionsCount = computed(() =>
  formatCompactCount(homePublicPassions.value.length),
);

const membersCount = computed(() =>
  homePublicStats.value
    ? formatCompactCount(homePublicStats.value.membres_total)
    : fallbackMembersCount.value,
);

const publicationsCount = computed(() =>
  homePublicStats.value
    ? formatCompactCount(homePublicStats.value.publications_publiques_total)
    : fallbackPublicationsCount.value,
);

const passionsCount = computed(() =>
  homePublicStats.value
    ? formatCompactCount(homePublicStats.value.passions_publiques_total)
    : fallbackPassionsCount.value,
);

onMounted(() => {
  loadHomeData();
});
</script>

<template>
  <!--
    HomeView – Loom
    Stack : Vue 3 + TypeScript + DaisyUI v5 + Tailwind v4
    Le fond décoratif (grille de points + halos) est désormais global et géré
    dans App.vue, donc partagé par toutes les pages. 100 % tokens DaisyUI.
  -->
  <div class="relative overflow-hidden">

    <main class="loom-shell py-10 md:py-16 space-y-24 relative">

      <!-- ══════════════════════════════════════════════════════
           HERO
      ═══════════════════════════════════════════════════════ -->
      <section class="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-12 items-center">

        <div class="space-y-7">
          <!-- Logo Loom mis en évidence (teinté en couleur primary du thème) -->
          <div class="flex items-center gap-3">
            <span class="loom-logo-mask h-12 w-12 text-primary md:h-14 md:w-14" aria-hidden="true"></span>
            <span class="text-4xl font-extrabold tracking-tight text-primary md:text-5xl">Loom</span>
          </div>

          <div class="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5">
            <span class="material-symbols-outlined text-sm text-primary">favorite</span>
            <span class="text-xs font-semibold text-primary">Le réseau social des passions</span>
          </div>

          <h1 class="text-5xl md:text-6xl font-extrabold leading-[1.1] tracking-tight text-base-content">
            Arrête de suivre<br>des gens.<br>
            <span class="bg-gradient-to-r from-primary via-secondary to-accent bg-clip-text text-transparent">Suis des passions.</span>
          </h1>

          <p class="text-base text-base-content/65 leading-relaxed max-w-lg">
            Sur Loom, tu t'abonnes aux sujets qui t'intéressent, pas à toute la vie de quelqu'un.
            Ton fil d'actualité devient enfin un espace utile, ciblé, sans bruit.
          </p>

          <div class="flex flex-wrap gap-2">
            <span
              v-for="(p, index) in popularPassions.slice(0, 6)"
              :key="p.label"
              :class="[
                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-transform cursor-pointer hover:scale-105',
                passionColor(index),
              ]"
            >
              <span class="material-symbols-outlined text-sm">{{ p.icon }}</span>
              {{ p.label }}
            </span>
          </div>

          <div class="flex flex-wrap gap-3">
            <button
              class="btn btn-primary btn-md gap-2"
              @click="router.push(isAuthenticated ? '/feed' : '/register')"
            >
              <span class="material-symbols-outlined text-base">rocket_launch</span>
              {{ isAuthenticated ? "Ouvrir mon fil" : "Commencer gratuitement" }}
            </button>
            <button class="btn btn-outline btn-md gap-2" @click="router.push('/explore')">
              <span class="material-symbols-outlined text-base">travel_explore</span>
              Explorer sans compte
            </button>
          </div>

          <div class="flex items-center gap-8 pt-1">
            <div>
              <p class="text-2xl font-bold text-base-content">{{ membersCount }}</p>
              <p class="text-xs text-base-content/50">membres</p>
            </div>
            <div class="w-px h-10 bg-base-300"/>
            <div>
              <p class="text-2xl font-bold text-base-content">{{ publicationsCount }}</p>
              <p class="text-xs text-base-content/50">publications publiques</p>
            </div>
            <div class="w-px h-10 bg-base-300"/>
            <div>
              <p class="text-2xl font-bold text-base-content">{{ passionsCount }}</p>
              <p class="text-xs text-base-content/50">passions publiques</p>
            </div>
          </div>
        </div>

        <!-- Mock feed -->
        <div class="relative">
          <div class="absolute -inset-4 rounded-3xl bg-primary/8 blur-2xl"/>
          <div class="relative rounded-2xl border border-base-300 bg-base-100 shadow-lg overflow-hidden">
            <div class="flex items-center gap-2 border-b border-base-300 bg-base-200 px-4 py-3">
              <span class="material-symbols-outlined text-sm text-primary">dynamic_feed</span>
              <span class="text-xs font-semibold text-base-content/60 uppercase tracking-wider">Ton fil personnalisé</span>
            </div>
            <div class="p-4 space-y-3">
              <div
                v-for="(post, index) in feedPreview"
                :key="index"
                class="rounded-xl border border-base-300 bg-base-200 p-4 space-y-2 hover:border-primary/40 hover:bg-base-100 transition-all cursor-pointer"
              >
                <div class="flex items-center justify-between">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold',
                      passionColor(index),
                    ]"
                  >
                    <span class="material-symbols-outlined text-sm">{{ post.icon }}</span>
                    {{ post.passion }}
                  </span>
                  <span class="text-xs text-base-content/40">{{ post.author }} · {{ post.time }}</span>
                </div>
                <p class="text-sm text-base-content leading-snug">{{ post.content }}</p>
                <div class="flex items-center gap-1 text-xs text-base-content/40">
                  <span class="material-symbols-outlined text-sm">favorite</span>
                  {{ post.likes }}
                </div>
              </div>
              <p class="text-center text-xs text-base-content/35 py-1">
                + des centaines de publications t'attendent…
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- ══════════════════════════════════════════════════════
           PROBLÈME / SOLUTION
      ═══════════════════════════════════════════════════════ -->
      <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2">
          <div class="p-8 md:p-10 border-b md:border-b-0 md:border-r border-base-300 space-y-5">
            <div class="inline-flex items-center gap-2 rounded-full bg-error/10 border border-error/20 px-3 py-1.5">
              <span class="material-symbols-outlined text-sm text-error">sentiment_dissatisfied</span>
              <span class="text-xs font-semibold text-error uppercase tracking-wide">Le problème</span>
            </div>
            <h2 class="text-xl font-bold text-base-content">Les réseaux classiques t'imposent toute la vie d'une personne</h2>
            <ul class="space-y-3">
              <li v-for="pb in ['Tu suis quelqu\'un pour sa cuisine, tu reçois aussi ses opinions politiques','Ton fil est noyé de contenu hors-sujet','Tu dois faire le tri manuellement à chaque scroll','L\'algorithme décide à ta place ce que tu vois']" :key="pb" class="flex items-start gap-3 text-sm text-base-content/70">
                <span class="material-symbols-outlined text-base text-error mt-0.5 flex-shrink-0">cancel</span>
                {{ pb }}
              </li>
            </ul>
          </div>
          <div class="p-8 md:p-10 space-y-5">
            <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 border border-primary/20 px-3 py-1.5">
              <span class="material-symbols-outlined text-sm text-primary">favorite</span>
              <span class="text-xs font-semibold text-primary uppercase tracking-wide">La solution Loom</span>
            </div>
            <h2 class="text-xl font-bold text-base-content">Tu choisis exactement ce que tu veux voir, passion par passion</h2>
            <ul class="space-y-3">
              <li v-for="sol in ['Tu suis la passion Cuisine de Thomas — et rien d\'autre','Ton fil ne contient que tes sujets d\'intérêt','Chaque publication est catégorisée par thématique','Tu gardes le contrôle total de ton expérience']" :key="sol" class="flex items-start gap-3 text-sm text-base-content/70">
                <span class="material-symbols-outlined text-base text-primary mt-0.5 flex-shrink-0">check_circle</span>
                {{ sol }}
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- ══════════════════════════════════════════════════════
           COMMENT ÇA MARCHE
      ═══════════════════════════════════════════════════════ -->
      <section class="space-y-10">
        <div class="text-center space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/40">Comment ça marche</p>
          <h2 class="text-2xl md:text-3xl font-bold text-base-content">Simple comme bonjour</h2>
          <p class="text-sm text-base-content/55 max-w-md mx-auto">En trois étapes, tu passes d'un réseau bruyant à un fil qui te ressemble vraiment.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 relative">
          <div class="hidden md:block absolute top-[22px] left-[calc(33.33%+1.5rem)] right-[calc(33.33%+1.5rem)] h-px border-t-2 border-dashed border-base-300"/>
          <article
            v-for="step in steps"
            :key="step.number"
            class="relative rounded-2xl border border-base-300 bg-base-100 p-7 space-y-4 hover:border-primary/50 hover:shadow-md hover:-translate-y-1 transition-all duration-300 z-10"
          >
            <div class="flex items-start justify-between">
              <span
                :class="['flex h-11 w-11 items-center justify-center rounded-xl text-sm font-bold shadow-sm', step.badge]"
              >{{ step.number }}</span>
              <span class="material-symbols-outlined text-4xl" :class="step.ghost">{{ step.icon }}</span>
            </div>
            <h3 class="text-base font-bold text-base-content">{{ step.title }}</h3>
            <p class="text-sm text-base-content/60 leading-relaxed">{{ step.text }}</p>
          </article>
        </div>
      </section>

      <!-- ══════════════════════════════════════════════════════
           COMPARATIF
      ═══════════════════════════════════════════════════════ -->
      <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden shadow-sm">
        <div class="flex items-center justify-between p-6 md:p-8 border-b border-base-300 bg-base-200">
          <div>
            <h2 class="text-xl font-bold text-base-content">Loom vs les autres réseaux</h2>
            <p class="text-sm text-base-content/50 mt-0.5">Pourquoi Loom change vraiment la donne</p>
          </div>
          <span class="material-symbols-outlined text-2xl text-primary">compare_arrows</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-base-300">
                <th class="text-left text-xs font-semibold uppercase tracking-wide text-base-content/50 p-4">Fonctionnalité</th>
                <th class="text-center p-4 w-32 bg-primary/5">
                  <span class="inline-flex items-center justify-center gap-1 text-sm font-bold text-primary">
                    <span class="material-symbols-outlined text-base">favorite</span>Loom
                  </span>
                </th>
                <th class="text-center p-4 w-40">
                  <span class="text-sm font-semibold text-base-content/50">Réseaux classiques</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in comparison" :key="row.label" :class="i % 2 === 0 ? 'bg-base-200/40' : ''" class="border-b border-base-300/40 transition-colors hover:bg-base-200/70">
                <td class="text-sm text-base-content/75 p-4">{{ row.label }}</td>
                <td class="text-center p-4 bg-primary/5">
                  <span class="material-symbols-outlined text-lg" :class="row.loom ? 'text-primary' : 'text-error'">{{ row.loom ? "check_circle" : "cancel" }}</span>
                </td>
                <td class="text-center p-4">
                  <span class="material-symbols-outlined text-lg" :class="row.classic ? 'text-success' : 'text-error'">{{ row.classic ? "check_circle" : "cancel" }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ══════════════════════════════════════════════════════
           PASSIONS POPULAIRES
      ═══════════════════════════════════════════════════════ -->
      <section class="space-y-6">
        <div class="flex items-end justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/40 mb-1">Explorer</p>
            <h2 class="text-2xl font-bold text-base-content">Passions populaires en ce moment</h2>
          </div>
          <button class="btn btn-ghost btn-sm gap-1" @click="router.push('/explore')">
            Voir tout
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </button>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div
            v-for="(p, index) in popularPassions"
            :key="p.label"
            class="group rounded-2xl border border-base-300 bg-base-100 p-5 flex flex-col items-center gap-3 cursor-pointer hover:border-primary/50 hover:shadow-md hover:-translate-y-1 transition-all duration-300"
            @click="router.push('/explore')"
          >
            <div
              :class="['flex h-12 w-12 items-center justify-center rounded-xl border transition group-hover:scale-110', passionColor(index)]"
            >
              <span class="material-symbols-outlined text-xl">{{ p.icon }}</span>
            </div>
            <span class="text-sm font-semibold text-base-content">{{ p.label }}</span>
            <span class="text-xs text-base-content/45">{{ p.count }}</span>
          </div>
        </div>
      </section>

      <!-- ══════════════════════════════════════════════════════
           CTA FINAL
      ═══════════════════════════════════════════════════════ -->
      <section v-if="!isAuthenticated" class="relative rounded-2xl bg-primary overflow-hidden">
        <!-- SVG déco interne du CTA — formes géométriques simples en blanc -->
        <svg
          class="absolute inset-0 h-full w-full"
          viewBox="0 0 900 220"
          preserveAspectRatio="xMidYMid slice"
          xmlns="http://www.w3.org/2000/svg"
          aria-hidden="true"
        >
          <!-- Grands cercles blancs semi-transparents -->
          <circle cx="820" cy="110" r="160" fill="white" opacity="0.07"/>
          <circle cx="820" cy="110" r="100" fill="white" opacity="0.06"/>
          <circle cx="60"  cy="200" r="140" fill="white" opacity="0.05"/>
          <circle cx="400" cy="-30" r="90"  fill="white" opacity="0.04"/>
          <!-- Grille pointillée blanche -->
          <pattern id="cta-dots" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
            <circle cx="1" cy="1" r="1" fill="white" opacity="0.15"/>
          </pattern>
          <rect width="100%" height="100%" fill="url(#cta-dots)"/>
        </svg>
        <div class="relative flex flex-col md:flex-row items-center justify-between gap-6 p-10 md:p-14">
          <div class="space-y-2 text-primary-content text-center md:text-left">
            <h2 class="text-2xl md:text-3xl font-bold leading-snug">Prêt à suivre ce qui compte vraiment ?</h2>
            <p class="text-primary-content/75 text-sm max-w-md">Crée ton compte gratuitement et commence à construire ton fil de passions en quelques secondes.</p>
          </div>
          <div class="flex gap-3 flex-shrink-0">
            <button class="btn bg-base-100 text-primary hover:bg-base-200 border-0 btn-md gap-2 font-bold" @click="router.push('/register')">
              <span class="material-symbols-outlined text-base">person_add</span>
              Créer mon compte
            </button>
            <button class="btn btn-outline border-primary-content/40 text-primary-content hover:bg-primary-content/10 btn-md" @click="router.push('/login')">
              Se connecter
            </button>
          </div>
        </div>
      </section>

    </main>
  </div>
</template>
