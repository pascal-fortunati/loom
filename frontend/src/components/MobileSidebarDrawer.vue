<script setup lang="ts">
/**
 * Tiroir latéral mobile (off-canvas).
 * Affiche, sous le point de rupture lg, un bouton « Mon espace » qui ouvre un
 * panneau glissant depuis la gauche. Le contenu (sidebars desktop) est passé
 * via le slot par défaut. Au-delà de lg, le composant est masqué car les
 * sidebars reprennent leur place dans la grille.
 */
import { onBeforeUnmount, ref, watch } from "vue";
import { useRoute } from "vue-router";

const props = defineProps<{ label?: string }>();
const open = ref(false);
const route = useRoute();

/** Ouvre le tiroir. */
function openDrawer(): void {
  open.value = true;
}

/** Ferme le tiroir. */
function closeDrawer(): void {
  open.value = false;
}

// Verrouille le défilement de l'arrière-plan quand le tiroir est ouvert.
watch(open, (isOpen) => {
  document.body.style.overflow = isOpen ? "hidden" : "";
});

// Ferme automatiquement le tiroir lors d'un changement de page.
watch(
  () => route.fullPath,
  () => {
    open.value = false;
  },
);

onBeforeUnmount(() => {
  document.body.style.overflow = "";
});
</script>

<template>
  <div class="lg:hidden">
    <button
      type="button"
      class="loom-soft-card flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left transition active:scale-[0.99]"
      @click="openDrawer"
    >
      <span class="flex items-center gap-2 text-sm font-semibold">
        <span class="material-symbols-outlined text-base text-primary">dashboard</span>
        {{ props.label || "Mon espace" }}
      </span>
      <span class="flex items-center gap-1 text-xs text-base-content/50">
        Ouvrir
        <span class="material-symbols-outlined text-base">chevron_right</span>
      </span>
    </button>

    <Teleport to="body">
      <!--
        Le tiroir reste monté en permanence : on pilote l'ouverture par des classes
        (translate / opacity) plutôt que par v-if + <Transition>. Ainsi la position
        finale est toujours correcte (translate-x-0) même si les transitions sont
        gelées, tout en restant fluide sur un vrai appareil.
      -->
      <div
        class="fixed inset-0 z-[70] lg:hidden"
        :class="open ? '' : 'pointer-events-none'"
        :aria-hidden="open ? 'false' : 'true'"
      >
        <div
          class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity duration-200"
          :class="open ? 'opacity-100' : 'opacity-0'"
          aria-hidden="true"
          @click="closeDrawer"
        ></div>
        <aside
          class="loom-drawer-panel absolute inset-y-0 left-0 flex w-[86%] max-w-xs flex-col gap-3 overflow-y-auto bg-base-100 p-3 shadow-2xl"
          :style="{
            transform: open ? 'translateX(0)' : 'translateX(-100%)',
            transition: 'transform 0.3s cubic-bezier(0.22, 1, 0.36, 1)',
          }"
        >
          <div class="flex items-center justify-between px-1">
            <span class="flex items-center gap-2 text-sm font-bold">
              <span class="material-symbols-outlined text-base text-primary">dashboard</span>
              {{ props.label || "Mon espace" }}
            </span>
            <button
              type="button"
              class="btn btn-ghost btn-sm btn-square"
              aria-label="Fermer"
              @click="closeDrawer"
            >
              <span class="material-symbols-outlined text-base">close</span>
            </button>
          </div>
          <div class="space-y-3 pb-4">
            <slot />
          </div>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
