<script setup lang="ts">
/**
 * Pied de page global : liens légaux (RGPD) et déclaration d'accessibilité (RGAA).
 */
import { useRouter } from "vue-router";

const router = useRouter();
const year = new Date().getFullYear();
</script>

<template>
  <footer class="mt-8 border-t border-base-300/70 py-6 text-base-content/70">
    <div class="loom-shell flex flex-col items-center gap-3 text-center sm:flex-row sm:justify-between sm:text-left">
      <p class="flex items-center gap-1.5 text-xs">
        <span class="loom-logo-mask h-4 w-4 text-primary" aria-hidden="true"></span>
        © {{ year }} Loom — Le réseau social des passions
      </p>
      <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs" aria-label="Liens légaux">
        <button class="hover:text-primary hover:underline" @click="router.push('/legal')">
          Mentions légales
        </button>
        <button class="hover:text-primary hover:underline" @click="router.push('/privacy')">
          Confidentialité
        </button>
        <button class="hover:text-primary hover:underline" @click="router.push('/accessibilite')">
          Accessibilité
        </button>
      </nav>
    </div>
  </footer>
</template>
