<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { login } from "../composables/useAuth";

const router = useRouter();
const loading = ref(false);
const errorMessage = ref("");
const email = ref("");
const password = ref("");

/**
 * Soumet la connexion puis redirige vers le feed.
 */
async function submit(): Promise<void> {
  if (!email.value || !password.value) {
    errorMessage.value = "Merci de remplir tous les champs.";
    return;
  }

  loading.value = true;
  errorMessage.value = "";
  try {
    await login(email.value, password.value);
    router.push("/feed");
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Connexion impossible";
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="loom-shell py-10 md:py-14">
    <div class="mx-auto grid w-full max-w-5xl gap-6 lg:grid-cols-[1.08fr_1fr]">
      <section class="loom-soft-card hidden p-8 lg:block">
        <p
          class="text-xs font-semibold uppercase tracking-wider text-base-content/60"
        >
          Loom
        </p>
        <h1 class="mt-2 text-4xl font-bold leading-tight text-base-content">
          Retrouve ton fil de passions
        </h1>
        <p class="mt-3 max-w-md text-sm leading-relaxed text-base-content/70">
          Connecte-toi pour suivre des passions ciblées et personnaliser ton
          expérience.
        </p>

        <div class="mt-5 flex flex-wrap gap-2">
          <span class="rounded-full border border-base-300 px-3 py-1 text-xs"
            >Gaming</span
          >
          <span class="rounded-full border border-base-300 px-3 py-1 text-xs"
            >Cuisine</span
          >
          <span class="rounded-full border border-base-300 px-3 py-1 text-xs"
            >Sport</span
          >
          <span class="rounded-full border border-base-300 px-3 py-1 text-xs"
            >Photo</span
          >
        </div>
      </section>

      <section class="loom-soft-card p-6 md:p-8">
        <h2 class="text-3xl font-bold text-base-content">Connexion</h2>
        <p class="mt-1 text-sm text-base-content/70">Bienvenue sur Loom</p>

        <div
          v-if="errorMessage"
          class="mt-4 rounded-lg border border-error/40 bg-error/10 px-3 py-2 text-sm text-error"
        >
          {{ errorMessage }}
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80"
              >Email</span
            >
            <input
              v-model="email"
              type="email"
              class="input input-bordered w-full"
              placeholder="alice@example.com"
            />
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80"
              >Mot de passe</span
            >
            <input
              v-model="password"
              type="password"
              class="input input-bordered w-full"
              placeholder="********"
            />
          </label>

          <button
            :disabled="loading"
            class="btn btn-primary mt-1 flex h-11 w-full items-center justify-center gap-2 rounded-lg border-0 text-sm font-semibold"
          >
            <span class="material-symbols-outlined text-base">login</span>
            {{ loading ? "Connexion..." : "Se connecter" }}
          </button>
        </form>

        <p class="mt-5 text-center text-sm text-base-content/75">
          Pas encore inscrit ?
          <button
            class="font-medium text-primary hover:underline"
            @click="router.push('/register')"
          >
            Créer un compte
          </button>
        </p>
      </section>
    </div>
  </main>
</template>
