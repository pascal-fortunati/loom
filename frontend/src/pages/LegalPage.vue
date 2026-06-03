<script setup lang="ts">
/**
 * Mentions légales (page publique, RGPD / LCEN).
 * Les éléments entre crochets sont à compléter par l'éditeur du site.
 */
import { useRouter } from "vue-router";

const router = useRouter();
</script>

<template>
  <main class="loom-shell py-8 md:py-12">
    <article class="loom-soft-card mx-auto w-full max-w-3xl p-6 md:p-8">
      <button
        type="button"
        class="mb-4 inline-flex items-center gap-1 text-sm text-primary hover:underline"
        @click="router.push('/home')"
      >
        <span class="material-symbols-outlined text-base">arrow_back</span>
        Retour à l'accueil
      </button>

      <h1 class="text-2xl font-bold text-base-content">Mentions légales</h1>
      <p class="mt-1 text-sm text-base-content/60">Dernière mise à jour : juin 2026</p>

      <section class="mt-6 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Éditeur du site</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Le site Loom est édité par [Nom Prénom de l'éditeur], dans le cadre d'un projet de formation
          (Titre Professionnel Développeur Web et Web Mobile).<br />
          Contact : [adresse e-mail de contact].
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Hébergement</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Le site est hébergé par [Nom de l'hébergeur], [adresse de l'hébergeur].
          En environnement de formation, l'application est exécutée localement (Laragon : PHP, MySQL, nginx).
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Propriété intellectuelle</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Le nom « Loom », le logo et l'interface du site sont la propriété de leur auteur. Les contenus
          publiés par les membres restent la propriété de leurs auteurs respectifs.
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Données personnelles</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Le traitement des données personnelles est détaillé dans la
          <button class="font-medium text-primary hover:underline" @click="router.push('/privacy')">
            politique de confidentialité
          </button>.
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Accessibilité</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          L'état de conformité est décrit dans la
          <button class="font-medium text-primary hover:underline" @click="router.push('/accessibilite')">
            déclaration d'accessibilité
          </button>.
        </p>
      </section>
    </article>
  </main>
</template>
