import { createApp } from "vue";
import "./style.css";
import App from "./App.vue";
import { router } from "./router";

/**
 * Initialise le thème DaisyUI enregistré localement.
 * Cette logique garantit la persistance du choix utilisateur entre deux sessions.
 */
const savedTheme = localStorage.getItem("loom-theme");
document.documentElement.setAttribute("data-theme", savedTheme || "light");

createApp(App).use(router).mount("#app");
