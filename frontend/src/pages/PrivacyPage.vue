<script setup lang="ts">
/**
 * Politique de confidentialité (page publique, RGPD).
 * Décrit les données collectées, la finalité, la base légale, la durée de
 * conservation et les droits des personnes.
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

      <h1 class="text-2xl font-bold text-base-content">Politique de confidentialité</h1>
      <p class="mt-1 text-sm text-base-content/60">Dernière mise à jour : juin 2026</p>

      <p class="mt-4 text-sm leading-relaxed text-base-content/80">
        Loom accorde de l'importance à la protection de tes données personnelles, conformément au
        Règlement Général sur la Protection des Données (RGPD). Cette page explique quelles données
        sont collectées, pourquoi, combien de temps elles sont conservées et quels sont tes droits.
      </p>

      <section class="mt-6 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Données collectées</h2>
        <ul class="list-disc space-y-1 pl-5 text-sm leading-relaxed text-base-content/80">
          <li>Données de compte : pseudo, adresse e-mail, mot de passe (stocké uniquement sous forme hachée avec bcrypt).</li>
          <li>Données de profil : avatar et biographie (facultatifs).</li>
          <li>Contenus créés : pages de passions, publications, commentaires, messages privés.</li>
          <li>Données techniques minimales : jeton d'authentification et préférence de thème, stockés sur ton appareil (localStorage).</li>
        </ul>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Finalité et base légale</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Ces données servent uniquement à fournir le service (création de compte, publication,
          interactions, messagerie). La base légale est l'exécution du service auquel tu souscris en
          créant un compte. Aucune donnée n'est vendue ni utilisée à des fins publicitaires.
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Cookies et traceurs</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Loom n'utilise aucun cookie publicitaire ni traceur tiers. Seul un stockage technique local
          (localStorage) est employé pour te garder connecté et mémoriser ton thème : il est strictement
          nécessaire au fonctionnement et ne requiert donc pas de consentement préalable.
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Durée de conservation</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Tes données sont conservées tant que ton compte est actif. À la suppression du compte, ton
          profil, tes passions, tes publications, tes messages et tes notifications sont supprimés
          (suppression en cascade).
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Sécurité</h2>
        <p class="text-sm leading-relaxed text-base-content/80">
          Les mots de passe sont hachés (bcrypt), les accès à la base utilisent des requêtes préparées,
          l'authentification repose sur un jeton signé et les contenus sont nettoyés contre les injections
          (XSS). Les identifiants et secrets ne figurent jamais dans le code.
        </p>
      </section>

      <section class="mt-5 space-y-2">
        <h2 class="text-lg font-semibold text-base-content">Tes droits</h2>
        <ul class="list-disc space-y-1 pl-5 text-sm leading-relaxed text-base-content/80">
          <li><strong>Droit d'accès et de portabilité</strong> : tu peux télécharger tes données depuis la page Paramètres (export JSON).</li>
          <li><strong>Droit de rectification</strong> : tu peux modifier ton profil à tout moment.</li>
          <li><strong>Droit à l'effacement</strong> : tu peux supprimer ton compte depuis la page Paramètres.</li>
          <li>Pour toute question, contacte [adresse e-mail de contact].</li>
        </ul>
      </section>
    </article>
  </main>
</template>
