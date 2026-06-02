<script setup lang="ts">
import { useRouter } from "vue-router";

/**
 * Page 404 affichée pour toute route inconnue (catch-all du routeur).
 */
const router = useRouter();
</script>

<template>
  <main
    class="loom-shell flex min-h-[60vh] flex-col items-center justify-center gap-4 py-16 text-center"
  >
    <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/10 text-primary">
      <span class="material-symbols-outlined text-5xl">travel_explore</span>
    </div>
    <p class="text-6xl font-extrabold leading-none text-primary">404</p>
    <h1 class="text-xl font-bold">Page introuvable</h1>
    <p class="max-w-md text-sm text-base-content/60">
      La page que tu cherches n'existe pas ou a été déplacée.
    </p>
    <div class="mt-2 flex flex-wrap justify-center gap-2">
      <button class="btn btn-primary gap-1.5" @click="router.push('/home')">
        <span class="material-symbols-outlined text-base">home</span>
        Retour à l'accueil
      </button>
      <button class="btn btn-ghost gap-1.5" @click="router.push('/explore')">
        <span class="material-symbols-outlined text-base">travel_explore</span>
        Explorer
      </button>
    </div>
  </main>
</template>
