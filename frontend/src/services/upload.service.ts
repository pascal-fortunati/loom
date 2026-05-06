import { getApiBaseUrl } from "./auth.service";

/**
 * Réponse de l'API pour l'upload d'image.
 */
interface UploadImageResponse {
  image_url: string;
}

/**
 * Réponse de l'API pour l'upload d'avatar.
 */
interface UploadAvatarResponse {
  avatar: string;
  avatar_url: string;
}

interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
}

/**
 * Upload une image de post (multipart/form-data) et retourne son URL publique.
 */
export async function uploadPostImage(
  token: string,
  file: File,
): Promise<string> {
  const formData = new FormData();
  formData.append("image", file);

  const response = await fetch(`${getApiBaseUrl()}/uploads/image`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: formData,
  });

  const payload = (await response.json()) as ApiResponse<UploadImageResponse>;
  if (!response.ok || !payload.success || !payload.data?.image_url) {
    throw new Error(payload.message || "Upload image impossible");
  }

  return payload.data.image_url;
}

/**
 * Upload un avatar utilisateur et retourne le nom du fichier + son URL publique.
 */
export async function uploadAvatarImage(
  token: string,
  file: File,
): Promise<UploadAvatarResponse> {
  const formData = new FormData();
  formData.append("image", file);

  const response = await fetch(`${getApiBaseUrl()}/uploads/avatar`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: formData,
  });

  const payload = (await response.json()) as ApiResponse<UploadAvatarResponse>;
  if (!response.ok || !payload.success || !payload.data?.avatar) {
    throw new Error(payload.message || "Upload avatar impossible");
  }

  return payload.data;
}
