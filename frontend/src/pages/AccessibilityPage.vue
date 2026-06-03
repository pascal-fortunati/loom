<script setup lang="ts">
/**
 * Déclaration d'accessibilité (page publique, démarche RGAA).
 * Décrit l'état de conformité, les mesures prises et les axes d'amélioration.
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

      <h1 class="text-2xl font-bold text-base-content">Déclaration d'accessibilité</h1>
      <p class="mt-1 text-sm text-base-content/60">Dernière mise à jour : juin 2026</p>

      <p class="mt-4 text-sm leading-relaxed text-base-content/80">
        L'accessibilité a été prise en compte dès la conception de Loom, en s'inspirant du Référentiel
        Général d'Amélioration de l'Accessibilité (RGAA). Cette page présente l'état de conformité et la
        démarche suivie.
      </p>

      <section class="mt-6 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">État de conformité</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Loom est <strong>partiellement conforme</strong> au RGAA : un audit complet n'a pas encore été
          réalisé, mais plusieurs bonnes pratiques sont déjà en place.
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Mesures mises en œuvre</h2>
        <ul class="list-disc space-y-1 pl-5 text-sm leading-relaxed text-base-content/80">
          <li>Langue de la page déclarée (<code>lang="fr"</code>).</li>
          <li>Lien d'évitement « Aller au contenu » en début de page.</li>
          <li>Titre de page dynamique adapté à chaque écran.</li>
          <li>Libellés alternatifs (<code>aria-label</code>) sur les boutons icônes et textes alternatifs sur les images.</li>
          <li>Navigation au clavier et indicateur de focus visible.</li>
          <li>Respect de la préférence système « réduire les animations » (<code>prefers-reduced-motion</code>).</li>
          <li>Contrastes assurés par les thèmes (couleurs de texte et de fond cohérentes).</li>
        </ul>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Axes d'amélioration</h2>
        <ul class="list-disc space-y-1 pl-5 text-sm leading-relaxed text-base-content/80">
          <li>Réaliser un audit RGAA complet et publier le taux de conformité.</li>
          <li>Vérifier systématiquement la hiérarchie des titres sur chaque page.</li>
          <li>Tester avec un lecteur d'écran (NVDA / VoiceOver).</li>
        </ul>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Retour d'information</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Si tu rencontres un défaut d'accessibilité, tu peux le signaler à [adresse e-mail de contact].
        </p>
      </section>
    </article>
  </main>
</template>
