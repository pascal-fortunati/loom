<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps<{
  modelValue: string;
  placeholder?: string;
  minHeightClass?: string;
  onUploadImage?: (file: File) => Promise<string>;
}>();

const emit = defineEmits<{
  (event: "update:modelValue", value: string): void;
}>();

const editorRef = ref<HTMLDivElement | null>(null);
const imageInputRef = ref<HTMLInputElement | null>(null);
const isEditorEmpty = ref(true);
const uploadingImage = ref(false);
const selectedImageElement = ref<HTMLImageElement | null>(null);
const selectedImageFigureElement = ref<HTMLElement | null>(null);
const selectedImageWidthPercent = ref(100);
const selectedImageCaption = ref("");
const selectedImageWrapMode = ref<"block" | "left" | "right">("block");

/** État actif des commandes (gras, italique, etc.) mis à jour à chaque sélection. */
const activeCommands = ref<Set<string>>(new Set());

/**
 * Vérifie quelles commandes sont actives sur la sélection courante
 * et met à jour activeCommands en conséquence.
 */
function updateActiveStates(): void {
  const commands = ["bold", "italic", "underline", "insertUnorderedList", "insertOrderedList"];
  const selection = window.getSelection();
  const anchorNode = selection?.anchorNode ?? null;
  const insideEditor =
    !!editorRef.value &&
    !!anchorNode &&
    (editorRef.value === anchorNode || editorRef.value.contains(anchorNode));

  if (!insideEditor) {
    activeCommands.value = new Set();
    return;
  }

  const next = new Set<string>();
  for (const cmd of commands) {
    try {
      if (document.queryCommandState(cmd)) next.add(cmd);
    } catch {
      // Certains navigateurs lèvent une exception sur certaines commandes hors contexte.
    }
  }
  activeCommands.value = next;
}

/** Synchronise le contenu quand la valeur externe change. */
watch(
  () => props.modelValue,
  (nextValue) => {
    if (!editorRef.value) return;
    if (editorRef.value.innerHTML === nextValue) return;
    editorRef.value.innerHTML = nextValue || "";
    refreshEditorEmptyState();
  },
);

onMounted(() => {
  if (editorRef.value) {
    editorRef.value.innerHTML = props.modelValue || "";
  }
  refreshEditorEmptyState();
  document.addEventListener("selectionchange", updateActiveStates);
});

onBeforeUnmount(() => {
  document.removeEventListener("selectionchange", updateActiveStates);
});

/** Applique une commande execCommand et met à jour le v-model. */
function applyCommand(command: string, value?: string): void {
  editorRef.value?.focus();
  document.execCommand(command, false, value);
  handleInput();
  updateActiveStates();
}

/** Met à jour le v-model à chaque frappe. */
function handleInput(): void {
  refreshEditorEmptyState();
  if (selectedImageElement.value && !selectedImageElement.value.isConnected) {
    clearSelectedImageSelection();
  }
  emit("update:modelValue", editorRef.value?.innerHTML || "");
}

/** Insère un lien sur la sélection courante. */
function addLink(): void {
  const rawUrl = window.prompt("URL du lien (https://...)");
  if (!rawUrl) return;
  const sanitizedUrl = normalizeHttpUrl(rawUrl);
  if (!sanitizedUrl) {
    window.alert("Lien invalide: utilise une URL http/https.");
    return;
  }
  applyCommand("createLink", sanitizedUrl);
}

/** Vide tout le formatage sur la sélection. */
function clearFormat(): void {
  applyCommand("removeFormat");
  // Remet aussi les blocs en paragraphe normal
  applyCommand("formatBlock", "P");
}

/**
 * Ouvre la sélection de fichier pour insérer une image.
 */
function triggerImagePicker(): void {
  if (!props.onUploadImage) return;
  if (uploadingImage.value) return;
  imageInputRef.value?.click();
}

/**
 * Insère une image dans l'éditeur.
 * Si un uploader est fourni par le parent, l'image est uploadée avant insertion.
 */
async function handleImageSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  if (!file.type.startsWith("image/")) {
    window.alert("Le fichier sélectionné doit être une image.");
    input.value = "";
    return;
  }

  try {
    uploadingImage.value = true;
    let imageUrl = "";

    if (!props.onUploadImage) {
      throw new Error("Upload image non configuré");
    }
    imageUrl = await props.onUploadImage(file);

    insertImageHtml(imageUrl);
  } catch {
    window.alert("Insertion d'image impossible.");
  } finally {
    uploadingImage.value = false;
    input.value = "";
  }
}

/**
 * Vérifie si l'éditeur est réellement vide (même si le navigateur laisse des <br>).
 */
function refreshEditorEmptyState(): void {
  const html = editorRef.value?.innerHTML ?? "";
  const plainText = (editorRef.value?.textContent || "").replace(/\u00a0/g, " ").trim();
  const normalizedHtml = html
    .replace(/<br\s*\/?>/gi, "")
    .replace(/&nbsp;/gi, "")
    .replace(/\s+/g, "")
    .trim();
  isEditorEmpty.value = !plainText && !normalizedHtml;
}

/**
 * Accepte uniquement les URLs HTTP/HTTPS et normalise la valeur.
 */
function normalizeHttpUrl(value: string): string | null {
  const candidate = value.trim();
  if (!candidate) return null;
  try {
    const parsed = new URL(candidate);
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") return null;
    return parsed.toString();
  } catch {
    return null;
  }
}

/**
 * Insère une image avec une largeur par défaut adaptée à l'éditeur.
 */
function insertImageHtml(imageUrl: string): void {
  const escapedUrl = imageUrl.replace(/"/g, "&quot;");
  editorRef.value?.focus();
  document.execCommand(
    "insertHTML",
    false,
    `
<figure data-loom-figure="true" style="display:block;margin:0.6rem 0;">
  <img src="${escapedUrl}" alt="Image article" style="width:100%;height:auto;display:block;" />
  <figcaption data-loom-caption="true" style="margin-top:0.35rem;font-size:0.82rem;opacity:0.75;">
    Ajoute une légende...
  </figcaption>
</figure>`,
  );
  handleInput();
}

/**
 * Supprime visuellement la sélection d'image active.
 */
function clearSelectedImageSelection(): void {
  if (selectedImageElement.value) {
    selectedImageElement.value.removeAttribute("data-selected");
  }
  if (selectedImageFigureElement.value) {
    selectedImageFigureElement.value.removeAttribute("data-selected-figure");
  }
  selectedImageElement.value = null;
  selectedImageFigureElement.value = null;
  selectedImageCaption.value = "";
  selectedImageWrapMode.value = "block";
}

/**
 * Calcule une largeur en pourcentage (20-100) à partir de l'image sélectionnée.
 */
function getCurrentImageWidthPercent(imageElement: HTMLImageElement): number {
  const inlineWidth = imageElement.style.width?.trim() || "";
  if (inlineWidth.endsWith("%")) {
    const parsedPercent = Number.parseFloat(inlineWidth);
    if (Number.isFinite(parsedPercent)) {
      return Math.min(100, Math.max(20, Math.round(parsedPercent)));
    }
  }

  const editorWidth = editorRef.value?.clientWidth || 0;
  if (editorWidth > 0 && imageElement.clientWidth > 0) {
    const computedPercent = Math.round((imageElement.clientWidth / editorWidth) * 100);
    return Math.min(100, Math.max(20, computedPercent));
  }

  return 100;
}

/**
 * Sélectionne une image cliquée dans l'éditeur et active les contrôles de resize.
 */
function selectEditorImage(imageElement: HTMLImageElement): void {
  clearSelectedImageSelection();
  selectedImageElement.value = imageElement;
  selectedImageElement.value.setAttribute("data-selected", "true");
  const figureElement = imageElement.closest("figure");
  if (figureElement) {
    selectedImageFigureElement.value = figureElement as HTMLElement;
    selectedImageFigureElement.value.setAttribute("data-selected-figure", "true");
    const captionElement = selectedImageFigureElement.value.querySelector(
      "figcaption",
    ) as HTMLElement | null;
    selectedImageCaption.value = captionElement?.textContent?.trim() || "";
  }

  const floatValue =
    (selectedImageFigureElement.value?.style.float ||
      selectedImageElement.value.style.float ||
      "").toLowerCase();
  if (floatValue === "left" || floatValue === "right") {
    selectedImageWrapMode.value = floatValue;
  } else {
    selectedImageWrapMode.value = "block";
  }

  selectedImageWidthPercent.value = getCurrentImageWidthPercent(imageElement);
}

/**
 * Gère le clic dans l'éditeur pour sélectionner/désélectionner une image.
 */
function handleEditorClick(event: MouseEvent): void {
  const target = event.target;
  if (target instanceof HTMLImageElement && editorRef.value?.contains(target)) {
    selectEditorImage(target);
    return;
  }
  clearSelectedImageSelection();
}

/**
 * Applique une largeur à l'image sélectionnée (en pourcentage).
 */
function applySelectedImageWidth(nextPercent: number): void {
  if (!selectedImageElement.value) return;
  const safeValue = Math.min(100, Math.max(20, Math.round(nextPercent)));
  selectedImageWidthPercent.value = safeValue;
  selectedImageElement.value.style.width = `${safeValue}%`;
  selectedImageElement.value.style.height = "auto";
  handleInput();
}

/**
 * Change l'alignement de l'image sélectionnée (gauche, centre, droite).
 */
function alignSelectedImage(position: "left" | "center" | "right"): void {
  if (!selectedImageElement.value) return;
  selectedImageElement.value.style.display = "block";

  if (position === "left") {
    selectedImageElement.value.style.marginLeft = "0";
    selectedImageElement.value.style.marginRight = "auto";
  }
  if (position === "center") {
    selectedImageElement.value.style.marginLeft = "auto";
    selectedImageElement.value.style.marginRight = "auto";
  }
  if (position === "right") {
    selectedImageElement.value.style.marginLeft = "auto";
    selectedImageElement.value.style.marginRight = "0";
  }

  handleInput();
}

/**
 * Met à jour la légende de l'image sélectionnée.
 */
function updateSelectedImageCaption(nextCaption: string): void {
  selectedImageCaption.value = nextCaption;
  if (!selectedImageFigureElement.value) return;

  let captionElement = selectedImageFigureElement.value.querySelector(
    "figcaption",
  ) as HTMLElement | null;

  if (!captionElement) {
    captionElement = document.createElement("figcaption");
    captionElement.setAttribute("data-loom-caption", "true");
    selectedImageFigureElement.value.appendChild(captionElement);
  }

  captionElement.textContent = nextCaption.trim() || "Ajoute une légende...";
  handleInput();
}

/**
 * Applique l'habillage du texte autour de l'image sélectionnée.
 */
function applySelectedImageWrapMode(mode: "block" | "left" | "right"): void {
  const targetElement =
    selectedImageFigureElement.value || selectedImageElement.value;
  if (!targetElement) return;

  selectedImageWrapMode.value = mode;

  if (mode === "block") {
    targetElement.style.float = "none";
    targetElement.style.display = "block";
    targetElement.style.margin = "0.6rem 0";
    if (selectedImageElement.value) {
      selectedImageElement.value.style.marginLeft = "auto";
      selectedImageElement.value.style.marginRight = "auto";
    }
    handleInput();
    return;
  }

  targetElement.style.float = mode;
  targetElement.style.display = "block";
  targetElement.style.margin =
    mode === "left" ? "0.35rem 1rem 0.5rem 0" : "0.35rem 0 0.5rem 1rem";

  if (selectedImageElement.value) {
    selectedImageElement.value.style.marginLeft = "0";
    selectedImageElement.value.style.marginRight = "0";
  }

  handleInput();
}

/**
 * Supprime l'image actuellement sélectionnée.
 */
function deleteSelectedImage(): void {
  if (selectedImageFigureElement.value) {
    selectedImageFigureElement.value.remove();
    clearSelectedImageSelection();
    handleInput();
    return;
  }
  if (selectedImageElement.value) {
    selectedImageElement.value.remove();
    clearSelectedImageSelection();
    handleInput();
  }
}
</script>

<template>
  <!--
    RichTextEditor – Loom
    Stack : Vue 3 + TypeScript + DaisyUI v5
    Éditeur riche contenteditable avec barre d'outils groupée,
    icônes Material Symbols, indicateur d'état actif et undo/redo.
  -->
  <div class="overflow-hidden rounded-xl border border-base-300 bg-base-100 transition-colors focus-within:border-primary">

    <!-- ── Barre d'outils ── -->
    <div class="flex flex-wrap items-center gap-0.5 border-b border-base-300 bg-base-200 px-2 py-1.5">

      <!-- Groupe 1 : Historique -->
      <div class="flex items-center gap-0.5">
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Annuler (Ctrl+Z)"
          @mousedown.prevent
          @click="applyCommand('undo')"
        >
          <span class="material-symbols-outlined loom-editor-icon">undo</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Rétablir (Ctrl+Y)"
          @mousedown.prevent
          @click="applyCommand('redo')"
        >
          <span class="material-symbols-outlined loom-editor-icon">redo</span>
        </button>
      </div>

      <div class="w-px h-5 bg-base-300 mx-1"/>

      <!-- Groupe 2 : Formatage de caractères -->
      <div class="flex items-center gap-0.5">
        <button
          type="button"
          class="btn btn-xs btn-square"
          :class="activeCommands.has('bold') ? 'btn-primary' : 'btn-ghost'"
          title="Gras (Ctrl+B)"
          @mousedown.prevent
          @click="applyCommand('bold')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_bold</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-square"
          :class="activeCommands.has('italic') ? 'btn-primary' : 'btn-ghost'"
          title="Italique (Ctrl+I)"
          @mousedown.prevent
          @click="applyCommand('italic')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_italic</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-square"
          :class="activeCommands.has('underline') ? 'btn-primary' : 'btn-ghost'"
          title="Souligner (Ctrl+U)"
          @mousedown.prevent
          @click="applyCommand('underline')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_underlined</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Barré"
          @mousedown.prevent
          @click="applyCommand('strikeThrough')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_strikethrough</span>
        </button>
      </div>

      <div class="w-px h-5 bg-base-300 mx-1"/>

      <!-- Groupe 3 : Titres -->
      <div class="flex items-center gap-0.5">
        <button
          type="button"
          class="btn btn-xs btn-ghost font-bold px-2"
          title="Titre H2"
          @mousedown.prevent
          @click="applyCommand('formatBlock', 'H2')"
        >
          H2
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost font-bold px-2"
          title="Titre H3"
          @mousedown.prevent
          @click="applyCommand('formatBlock', 'H3')"
        >
          H3
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Paragraphe normal"
          @mousedown.prevent
          @click="applyCommand('formatBlock', 'P')"
        >
          <span class="material-symbols-outlined loom-editor-icon">segment</span>
        </button>
      </div>

      <div class="w-px h-5 bg-base-300 mx-1"/>

      <!-- Groupe 4 : Alignement -->
      <div class="flex items-center gap-0.5">
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Aligner à gauche"
          @mousedown.prevent
          @click="applyCommand('justifyLeft')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_align_left</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Centrer"
          @mousedown.prevent
          @click="applyCommand('justifyCenter')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_align_center</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Aligner à droite"
          @mousedown.prevent
          @click="applyCommand('justifyRight')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_align_right</span>
        </button>
      </div>

      <div class="w-px h-5 bg-base-300 mx-1"/>

      <!-- Groupe 5 : Listes -->
      <div class="flex items-center gap-0.5">
        <button
          type="button"
          class="btn btn-xs btn-square"
          :class="activeCommands.has('insertUnorderedList') ? 'btn-primary' : 'btn-ghost'"
          title="Liste à puces"
          @mousedown.prevent
          @click="applyCommand('insertUnorderedList')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_list_bulleted</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-square"
          :class="activeCommands.has('insertOrderedList') ? 'btn-primary' : 'btn-ghost'"
          title="Liste numérotée"
          @mousedown.prevent
          @click="applyCommand('insertOrderedList')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_list_numbered</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Citation"
          @mousedown.prevent
          @click="applyCommand('formatBlock', 'BLOCKQUOTE')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_quote</span>
        </button>
      </div>

      <div class="w-px h-5 bg-base-300 mx-1"/>

      <!-- Groupe 6 : Lien + nettoyer -->
      <div class="flex items-center gap-0.5">
        <button
          v-if="props.onUploadImage"
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Insérer une image"
          :disabled="uploadingImage"
          @mousedown.prevent
          @click="triggerImagePicker"
        >
          <span class="material-symbols-outlined loom-editor-icon">
            {{ uploadingImage ? "progress_activity" : "image" }}
          </span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Insérer un lien"
          @mousedown.prevent
          @click="addLink"
        >
          <span class="material-symbols-outlined loom-editor-icon">link</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Supprimer le lien"
          @mousedown.prevent
          @click="applyCommand('unlink')"
        >
          <span class="material-symbols-outlined loom-editor-icon">link_off</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Effacer le formatage"
          @mousedown.prevent
          @click="clearFormat"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_clear</span>
        </button>
      </div>

      <div
        v-if="selectedImageElement"
        class="ml-2 flex flex-wrap items-center gap-1 rounded-lg border border-primary/30 bg-primary/10 px-2 py-1"
      >
        <span class="material-symbols-outlined loom-editor-icon text-primary">photo_size_select_large</span>
        <button
          type="button"
          class="btn btn-xs btn-ghost px-2"
          @mousedown.prevent
          @click="applySelectedImageWidth(35)"
        >
          S
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost px-2"
          @mousedown.prevent
          @click="applySelectedImageWidth(60)"
        >
          M
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost px-2"
          @mousedown.prevent
          @click="applySelectedImageWidth(100)"
        >
          L
        </button>
        <input
          type="range"
          min="20"
          max="100"
          step="1"
          class="range range-primary range-xs w-24"
          :value="selectedImageWidthPercent"
          @input="
            applySelectedImageWidth(
              Number((($event.target as HTMLInputElement).value || '100')),
            )
          "
        />
        <div class="w-px h-5 bg-primary/25 mx-1"></div>
        <button
          type="button"
          class="btn btn-xs btn-ghost"
          :class="{ 'btn-primary text-primary-content': selectedImageWrapMode === 'block' }"
          title="Sans habillage (bloc)"
          @mousedown.prevent
          @click="applySelectedImageWrapMode('block')"
        >
          Bloc
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost"
          :class="{ 'btn-primary text-primary-content': selectedImageWrapMode === 'left' }"
          title="Habillage gauche"
          @mousedown.prevent
          @click="applySelectedImageWrapMode('left')"
        >
          Wrap G
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost"
          :class="{ 'btn-primary text-primary-content': selectedImageWrapMode === 'right' }"
          title="Habillage droite"
          @mousedown.prevent
          @click="applySelectedImageWrapMode('right')"
        >
          Wrap D
        </button>
        <div class="w-px h-5 bg-primary/25 mx-1"></div>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Aligner à gauche"
          @mousedown.prevent
          @click="alignSelectedImage('left')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_align_left</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Centrer l'image"
          @mousedown.prevent
          @click="alignSelectedImage('center')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_align_center</span>
        </button>
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square"
          title="Aligner à droite"
          @mousedown.prevent
          @click="alignSelectedImage('right')"
        >
          <span class="material-symbols-outlined loom-editor-icon">format_align_right</span>
        </button>
        <div class="w-px h-5 bg-primary/25 mx-1"></div>
        <input
          type="text"
          class="input input-bordered input-xs w-44"
          :value="selectedImageCaption"
          placeholder="Légende image"
          @input="
            updateSelectedImageCaption(
              (($event.target as HTMLInputElement).value || ''),
            )
          "
        />
        <button
          type="button"
          class="btn btn-xs btn-ghost btn-square text-error"
          title="Supprimer l'image sélectionnée"
          @mousedown.prevent
          @click="deleteSelectedImage"
        >
          <span class="material-symbols-outlined loom-editor-icon">delete</span>
        </button>
      </div>
    </div>

    <!-- ── Zone d'édition ── -->
    <div
      ref="editorRef"
      contenteditable="true"
      class="loom-rich-editor w-full p-4 text-sm outline-none"
      :class="props.minHeightClass || 'min-h-52'"
      :data-placeholder="props.placeholder || 'Écris ton article...'"
      :data-empty="isEditorEmpty ? 'true' : 'false'"
      @input="handleInput"
      @blur="handleInput"
      @click="handleEditorClick"
      @keyup="updateActiveStates"
      @mouseup="updateActiveStates"
    />

    <input
      ref="imageInputRef"
      type="file"
      class="hidden"
      accept="image/*"
      @change="handleImageSelected"
    />
  </div>
</template>

<style scoped>
/* Placeholder quand l'éditeur est vide */
.loom-rich-editor[data-empty="true"]::before {
  content: attr(data-placeholder);
  color: color-mix(in srgb, var(--color-base-content, currentColor) 40%, transparent);
  pointer-events: none;
}

.loom-editor-icon {
  font-size: 1.05rem;
  line-height: 1;
}

/* Styles du contenu rendu dans l'éditeur — cohérents avec la typographie DaisyUI */
.loom-rich-editor :deep(h2) {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0.75rem 0 0.35rem;
  color: var(--color-base-content);
}

.loom-rich-editor :deep(h3) {
  font-size: 1.05rem;
  font-weight: 600;
  margin: 0.6rem 0 0.3rem;
  color: var(--color-base-content);
}

.loom-rich-editor :deep(p) {
  margin: 0.3rem 0;
  line-height: 1.7;
}

.loom-rich-editor :deep(strong) { font-weight: 700; }
.loom-rich-editor :deep(em)     { font-style: italic; }
.loom-rich-editor :deep(u)      { text-decoration: underline; }
.loom-rich-editor :deep(s)      { text-decoration: line-through; }

.loom-rich-editor :deep(ul) {
  list-style: disc;
  padding-left: 1.4rem;
  margin: 0.4rem 0;
}

.loom-rich-editor :deep(ol) {
  list-style: decimal;
  padding-left: 1.4rem;
  margin: 0.4rem 0;
}

.loom-rich-editor :deep(li) {
  margin: 0.15rem 0;
  line-height: 1.6;
}

.loom-rich-editor :deep(blockquote) {
  border-left: 3px solid var(--color-primary);
  padding: 0.4rem 0.9rem;
  margin: 0.6rem 0;
  color: color-mix(in srgb, var(--color-base-content) 65%, transparent);
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
  border-radius: 0 0.4rem 0.4rem 0;
  font-style: italic;
}

.loom-rich-editor :deep(a) {
  color: var(--color-primary);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.loom-rich-editor :deep(a:hover) {
  opacity: 0.8;
}

.loom-rich-editor :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 0.6rem;
  margin: 0.5rem 0;
  cursor: pointer;
}

.loom-rich-editor :deep(img[data-selected="true"]) {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.loom-rich-editor :deep(figure[data-selected-figure="true"]) {
  border: 1px dashed color-mix(in srgb, var(--color-primary) 50%, transparent);
  border-radius: 0.5rem;
  padding: 0.35rem;
}
</style>
