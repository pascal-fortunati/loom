<script setup lang="ts">
import { computed } from "vue";
import type { PrivateStatsDetails } from "../services/stats.service";

/**
 * Widget "Statistiques" réutilisable.
 *
 * Affiche les statistiques privées de l'utilisateur connecté sous forme de
 * tuiles colorées. Utilisé à l'identique par la page Profil et la page Fil,
 * d'où sa factorisation ici (un seul endroit à maintenir).
 */
const props = defineProps<{
  /** Statistiques détaillées (likes reçus, abonnés, etc.) ou null pendant le chargement. */
  details: PrivateStatsDetails | null;
  /** Nombre de publications de l'utilisateur (vient d'une autre source que `details`). */
  publications: number;
  /** Affiche les squelettes de chargement quand vrai. */
  loading: boolean;
}>();

/**
 * Tuiles à afficher : libellé, valeur, icône et teinte de la pastille.
 * Classes de couleur écrites en entier pour rester compatibles avec le scan
 * de Tailwind v4. Toutes les couleurs sont des tokens de thème DaisyUI.
 */
const statTiles = computed(() => [
  { label: "Likes", value: props.details?.likes_recus_total ?? 0, icon: "favorite", badge: "bg-error/10 text-error" },
  { label: "Commentaires", value: props.details?.commentaires_recus_total ?? 0, icon: "chat_bubble", badge: "bg-info/10 text-info" },
  { label: "Abonnés", value: props.details?.abonnes_total ?? 0, icon: "group", badge: "bg-success/10 text-success" },
  { label: "Public", value: props.details?.passions_publiques_total ?? 0, icon: "public", badge: "bg-primary/10 text-primary" },
  { label: "Privé", value: props.details?.passions_privees_total ?? 0, icon: "lock", badge: "bg-warning/10 text-warning" },
  { label: "Publier", value: props.publications, icon: "article", badge: "bg-secondary/10 text-secondary" },
]);
</script>

<template>
  <article class="loom-soft-card overflow-hidden">
    <!-- Bandeau coloré pleine largeur, collé aux bords (comme les couvertures de profil) -->
    <div class="relative flex h-14 items-center gap-1.5 loom-brand-bg px-4 opacity-90">
      <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-base-content/12 to-transparent"></div>
      <span class="material-symbols-outlined relative text-[15px] text-secondary-content">insights</span>
      <p class="relative text-[10px] font-semibold uppercase tracking-wider text-secondary-content">
        Statistiques
      </p>
    </div>

    <div class="p-3">
      <!-- Squelettes pendant le chargement -->
      <div v-if="loading" class="grid grid-cols-3 gap-2">
        <div v-for="n in 6" :key="n" class="h-14 animate-pulse rounded-lg bg-base-300/70"></div>
      </div>

      <!-- Tuiles de statistiques -->
      <div v-else class="grid grid-cols-3 gap-2">
        <div
          v-for="tile in statTiles"
          :key="tile.label"
          class="flex flex-col items-center gap-1 rounded-xl border border-base-300/70 bg-base-100 p-2.5 text-center transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-sm"
        >
          <span :class="['flex h-8 w-8 items-center justify-center rounded-lg', tile.badge]">
            <span class="material-symbols-outlined text-[18px]">{{ tile.icon }}</span>
          </span>
          <span class="text-lg font-extrabold leading-none">{{ tile.value }}</span>
          <span class="w-full truncate text-[11px] font-medium text-base-content/60">
            {{ tile.label }}
          </span>
        </div>
      </div>
    </div>
  </article>
</template>
