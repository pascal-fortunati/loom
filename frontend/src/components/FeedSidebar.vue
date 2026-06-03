<script setup lang="ts">
/**
 * Sidebar gauche du feed.
 * Version compacte: garde les proportions actuelles et ajoute les blocs manquants.
 */
import { useRouter } from "vue-router";
import { useUnreadMessages } from "../composables/useUnreadMessages";

const router = useRouter();
// Vrai compteur de messages non lus (remplace l'ancien badge "3" en dur)
const unreadMessages = useUnreadMessages();

interface FeedStats {
  passions: number;
  suivis: number;
  publications: number;
}

interface SidebarPassion {
  id: number;
  name: string;
  colorClass: string;
  postCount: number;
}

const props = defineProps<{
  username: string;
  userInitials: string;
  userAvatarUrl?: string;
  stats: FeedStats;
  passions: SidebarPassion[];
  isMyFeedMode: boolean;
  isProfileMode?: boolean;
  isSettingsMode?: boolean;
  activePassionId?: number | null;
  /** Force l'affichage (utilisé dans le tiroir mobile, sinon masqué < lg). */
  forceVisible?: boolean;
}>();

const emit = defineEmits<{
  (event: "create-passion"): void;
  (event: "open-my-feed"): void;
  (event: "open-profile"): void;
  (event: "open-settings"): void;
  (event: "open-passion", passionId: number): void;
}>();
</script>

<template>
  <aside :class="props.forceVisible ? 'space-y-3' : 'hidden space-y-3 lg:block'">
    <article class="loom-soft-card overflow-hidden">
      <div class="relative">
        <div class="h-14 loom-brand-bg"></div>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/10 to-transparent"></div>
        <div
          v-if="props.userAvatarUrl"
          class="absolute left-1/2 top-full z-10 h-14 w-14 -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-full border-4 border-base-100 bg-base-200"
        >
          <img
            :src="props.userAvatarUrl"
            alt="Avatar utilisateur"
            class="h-full w-full object-cover"
          />
        </div>
        <div
          v-else
          class="avatar-initials absolute left-1/2 top-full z-10 h-14 w-14 -translate-x-1/2 -translate-y-1/2 rounded-full border-4 border-base-100 bg-primary text-primary-content"
        >
          {{ props.userInitials }}
        </div>
      </div>
      <div class="px-4 pb-4 pt-8 text-center">
        <h2 class="mt-2 text-sm font-semibold">{{ props.username }}</h2>
        <p class="text-xs text-base-content/50">@{{ props.username }}</p>
        <div class="mt-3 grid grid-cols-3 gap-1 text-center text-[10px]">
          <div class="rounded bg-base-200 p-2">
            <p class="text-sm font-semibold text-primary">
              {{ props.stats.passions }}
            </p>
            <p class="text-base-content/60 text-[8px]">passions</p>
          </div>
          <div class="rounded bg-base-200 p-2">
            <p class="text-sm font-semibold text-primary">
              {{ props.stats.suivis }}
            </p>
            <p class="text-base-content/60 text-[8px]">suivis</p>
          </div>
          <div class="rounded bg-base-200 p-2">
            <p class="text-sm font-semibold text-primary">
              {{ props.stats.publications }}
            </p>
            <p class="text-base-content/60 text-[8px]">publications</p>
          </div>
        </div>
      </div>
    </article>

    <article class="loom-soft-card p-3">
      <div class="relative mb-2 overflow-hidden rounded-md border border-base-300/80 bg-base-200/70 px-2 py-1">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/8 to-transparent"></div>
        <p class="relative text-[10px] font-semibold uppercase tracking-wider text-base-content/65">
          Menu
        </p>
      </div>
      <div class="space-y-1">
        <button
          class="btn btn-sm w-full cursor-pointer justify-start border-0 text-xs"
          :class="
            props.isMyFeedMode
              ? 'bg-primary/15 text-primary'
              : 'btn-ghost text-base-content/80'
          "
          @click="emit('open-my-feed')"
        >
          <span class="material-symbols-outlined text-base">grid_view</span>
          Mon fil
        </button>
        <button
          class="btn btn-sm w-full cursor-pointer justify-start border-0 text-xs"
          :class="
            props.isProfileMode
              ? 'bg-primary/15 text-primary'
              : 'btn-ghost text-base-content/80'
          "
          @click="emit('open-profile')"
        >
          <span class="material-symbols-outlined text-base">person</span>
          Mon profil
        </button>
        <button
          class="btn btn-ghost btn-sm w-full cursor-pointer justify-start text-base-content/80"
          @click="router.push('/messages')"
        >
          <span class="material-symbols-outlined text-base">mail</span>
          Messages
          <span
            v-if="unreadMessages > 0"
            class="badge badge-error badge-xs ml-auto text-error-content"
          >
            {{ unreadMessages > 9 ? "9+" : unreadMessages }}
          </span>
        </button>
        <button
          class="btn btn-sm w-full cursor-pointer justify-start border-0 text-xs"
          :class="
            props.isSettingsMode
              ? 'bg-primary/15 text-primary'
              : 'btn-ghost text-base-content/80'
          "
          @click="emit('open-settings')"
        >
          <span class="material-symbols-outlined text-base">settings</span>
          Paramètres
        </button>
      </div>
    </article>

    <article class="loom-soft-card p-3">
      <div class="relative mb-2 overflow-hidden rounded-md border border-base-300/80 bg-base-200/70 px-2 py-1">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/8 to-transparent"></div>
        <p class="relative text-[10px] font-semibold uppercase tracking-wider text-base-content/65">
          Mes passions
        </p>
      </div>
      <div
        v-if="props.passions.length === 0"
        class="text-xs text-base-content/60"
      >
        Aucune passion créée pour l'instant.
      </div>

      <div
        v-for="passion in props.passions"
        :key="passion.id"
        class="mb-1 rounded-lg"
      >
        <button
          class="btn btn-sm w-full cursor-pointer justify-start border-0 text-left text-xs"
          :class="
            Number(props.activePassionId) === Number(passion.id)
              ? 'bg-primary/15 text-primary'
              : 'btn-ghost text-base-content/80'
          "
          @click="emit('open-passion', passion.id)"
        >
          <div class="flex min-w-0 items-center gap-2">
            <span
              class="h-2 w-2 rounded-full"
              :class="passion.colorClass"
            ></span>
            <span class="truncate text-sm">{{ passion.name }}</span>
          </div>
          <span class="ml-auto text-xs opacity-70">{{
            passion.postCount
          }}</span>
        </button>
      </div>

      <button
        class="mt-2 flex w-full cursor-pointer items-center justify-center gap-1 rounded-lg border border-primary/30 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/15"
        @click="emit('create-passion')"
      >
        <span class="material-symbols-outlined text-base">add</span>
        Créer une passion
      </button>
    </article>
  </aside>
</template>
