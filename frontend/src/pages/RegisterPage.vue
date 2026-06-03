<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { register } from "../composables/useAuth";

const router = useRouter();
const loading = ref(false);
const errorMessage = ref("");
const username = ref("");
const email = ref("");
const password = ref("");
const confirmPassword = ref("");
const consent = ref(false);

/**
 * Soumet l'inscription puis redirige vers le feed.
 */
async function submit(): Promise<void> {
  if (!username.value || !email.value || !password.value) {
    errorMessage.value = "Tous les champs sont obligatoires.";
    return;
  }
  if (password.value !== confirmPassword.value) {
    errorMessage.value = "Les mots de passe ne correspondent pas.";
    return;
  }
  if (!consent.value) {
    errorMessage.value = "Tu dois accepter la politique de confidentialité.";
    return;
  }

  loading.value = true;
  errorMessage.value = "";
  try {
    await register(username.value, email.value, password.value);
    router.push("/feed");
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Inscription impossible";
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
          Crée ton espace passions
        </h1>
        <p class="mt-3 max-w-md text-sm leading-relaxed text-base-content/70">
          Rejoins Loom pour publier sur tes passions et construire un fil ciblé.
        </p>

        <ul class="mt-5 space-y-2 text-sm text-base-content/85">
          <li class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-primary"
              >check_circle</span
            >
            Profil personnalisable
          </li>
          <li class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-primary"
              >check_circle</span
            >
            Pages de passions dédiées
          </li>
          <li class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-primary"
              >check_circle</span
            >
            Fil d'actualité pertinent
          </li>
        </ul>
      </section>

      <section class="loom-soft-card p-6 md:p-8">
        <h2 class="text-3xl font-bold text-base-content">Inscription</h2>
        <p class="mt-1 text-sm text-base-content/70">Crée ton compte Loom</p>

        <div
          v-if="errorMessage"
          class="mt-4 rounded-lg border border-error/40 bg-error/10 px-3 py-2 text-sm text-error"
        >
          {{ errorMessage }}
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80"
              >Nom d'utilisateur</span
            >
            <input
              v-model="username"
              type="text"
              class="input input-bordered w-full"
              placeholder="alex_loom"
            />
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80"
              >Email</span
            >
            <input
              v-model="email"
              type="email"
              class="input input-bordered w-full"
              placeholder="alex@example.com"
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

          <label class="block">
            <span class="mb-1 block text-xs font-medium text-base-content/80"
              >Confirmer le mot de passe</span
            >
            <input
              v-model="confirmPassword"
              type="password"
              class="input input-bordered w-full"
              placeholder="********"
            />
          </label>

          <label class="flex items-start gap-2 text-xs text-base-content/80">
            <input
              v-model="consent"
              type="checkbox"
              class="checkbox checkbox-primary checkbox-sm mt-0.5"
            />
            <span>
              J'ai lu et j'accepte la
              <button
                type="button"
                class="font-medium text-primary hover:underline"
                @click="router.push('/privacy')"
              >
                politique de confidentialité
              </button>.
            </span>
          </label>

          <button
            :disabled="loading || !consent"
            class="btn btn-primary mt-1 flex h-11 w-full items-center justify-center gap-2 rounded-lg border-0 text-sm font-semibold"
          >
            <span class="material-symbols-outlined text-base">person_add</span>
            {{ loading ? "Inscription..." : "Créer mon compte" }}
          </button>
        </form>

        <p class="mt-5 text-center text-sm text-base-content/75">
          Déjà inscrit ?
          <button
            class="font-medium text-primary hover:underline"
            @click="router.push('/login')"
          >
            Se connecter
          </button>
        </p>
      </section>
    </div>
  </main>
</template>
