import { createRouter, createWebHistory } from "vue-router";
import FeedPage from "../pages/FeedPage.vue";
import HomePage from "../pages/HomePage.vue";
import ExplorePage from "../pages/ExplorePage.vue";
import PassionDetailPage from "../pages/PassionDetailPage.vue";
import ProfilePage from "../pages/ProfilePage.vue";
import SettingsPage from "../pages/SettingsPage.vue";
import PublicProfilePage from "../pages/PublicProfilePage.vue";
import MessagesPage from "../pages/MessagesPage.vue";
import NotFoundPage from "../pages/NotFoundPage.vue";
import LoginPage from "../pages/LoginPage.vue";
import RegisterPage from "../pages/RegisterPage.vue";
import LegalPage from "../pages/LegalPage.vue";
import PrivacyPage from "../pages/PrivacyPage.vue";
import AccessibilityPage from "../pages/AccessibilityPage.vue";
import { isAuthenticated } from "../composables/useAuth";

/**
 * Routeur principal de Loom.
 * Chaque route porte un titre (meta.title) utilisé pour mettre à jour le titre
 * de l'onglet (accessibilité : titre de page pertinent et unique).
 */
export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/", redirect: "/home" },
    { path: "/home", component: HomePage, meta: { title: "Accueil" } },
    { path: "/explore", component: ExplorePage, meta: { title: "Explorer" } },
    { path: "/passion/:id", component: PassionDetailPage, meta: { title: "Passion" } },
    { path: "/u/:id", component: PublicProfilePage, meta: { title: "Profil" } },
    { path: "/profile", component: ProfilePage, meta: { requiresAuth: true, title: "Mon profil" } },
    { path: "/settings", component: SettingsPage, meta: { requiresAuth: true, title: "Paramètres" } },
    { path: "/feed", component: FeedPage, meta: { requiresAuth: true, title: "Mon fil" } },
    { path: "/messages", component: MessagesPage, meta: { requiresAuth: true, title: "Messages" } },
    { path: "/login", component: LoginPage, meta: { title: "Connexion" } },
    { path: "/register", component: RegisterPage, meta: { title: "Inscription" } },
    { path: "/legal", component: LegalPage, meta: { title: "Mentions légales" } },
    { path: "/privacy", component: PrivacyPage, meta: { title: "Politique de confidentialité" } },
    { path: "/accessibilite", component: AccessibilityPage, meta: { title: "Déclaration d'accessibilité" } },
    // Catch-all : toute route inconnue affiche la page 404
    { path: "/:pathMatch(.*)*", component: NotFoundPage, meta: { title: "Page introuvable" } },
  ],
});

/**
 * Garde de navigation: protège les routes qui demandent une session active.
 */
router.beforeEach((to) => {
  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return "/home";
  }

  if (
    (to.path === "/login" || to.path === "/register") &&
    isAuthenticated.value
  ) {
    return "/feed";
  }

  return true;
});

/**
 * Met à jour le titre du document à chaque navigation (accessibilité).
 */
router.afterEach((to) => {
  const pageTitle = (to.meta.title as string | undefined) || "";
  document.title = pageTitle ? `${pageTitle} · Loom` : "Loom";
});
