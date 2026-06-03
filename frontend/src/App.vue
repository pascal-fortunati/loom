<script setup lang="ts">
import { computed, onMounted } from "vue";
import { RouterView, useRoute } from "vue-router";
import AppNavbar from "./components/AppNavbar.vue";
import AppFooter from "./components/AppFooter.vue";
import { hydrateAuthUser, isAuthenticated } from "./composables/useAuth";

const route = useRoute();
// La page Messages gère sa propre hauteur : pas de padding bas global pour elle.
const needsBottomPadding = computed(() => isAuthenticated.value && route.path !== "/messages");
// Le pied de page (liens légaux) est masqué sur la messagerie (pleine hauteur).
const showFooter = computed(() => route.path !== "/messages");

/**
 * Initialise le thème et l'état utilisateur au chargement de l'application.
 */
onMounted(() => {
  const savedTheme = localStorage.getItem("loom-theme") || "light";
  document.documentElement.setAttribute("data-theme", savedTheme);
  hydrateAuthUser();
});
</script>

<template>
  <!--
    Pas de couleur de fond opaque ici : le fond décoratif global (motif de
    points + halos colorés) est porté par le <body> dans style.css. C'est plus
    robuste qu'une couche en z-index négatif, qui se faisait masquer.
  -->
  <!-- pb-20 sur mobile pour laisser la place à la bottom-nav (masquée dès md) -->
  <div
    class="min-h-screen text-base-content transition-colors"
    :class="{ 'pb-20 md:pb-0': needsBottomPadding }"
  >
    <!-- Lien d'évitement (accessibilité) : visible uniquement au focus clavier. -->
    <a
      href="#main"
      class="sr-only rounded-lg bg-primary px-4 py-2 font-semibold text-primary-content focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-[100]"
    >
      Aller au contenu
    </a>
    <AppNavbar />
    <div id="main" tabindex="-1" class="outline-none">
      <RouterView />
    </div>
    <AppFooter v-if="showFooter" />
  </div>
</template>
