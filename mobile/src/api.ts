import * as SecureStore from "expo-secure-store";
const base = process.env.EXPO_PUBLIC_API_URL;
let token: string | null = null;
export async function restoreToken() {
  token = await SecureStore.getItemAsync("quiz_token");
  return !!token;
}
export async function setToken(value: string | null) {
  if (value) await SecureStore.setItemAsync("quiz_token", value);
  else await SecureStore.deleteItemAsync("quiz_token");
  token = value;
}
export async function api<T = any>(
  path: string,
  method = "GET",
  data?: unknown,
): Promise<T> {
  if (!base)
    throw new Error("Set EXPO_PUBLIC_API_URL before launching the app.");
  if (!__DEV__ && !base.startsWith("https://"))
    throw new Error("HTTPS is required.");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(`${base.replace(/\/$/, "")}/${path}`, {
      method,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: data ? JSON.stringify(data) : undefined,
      signal: controller.signal,
    });
    const text = await response.text();
    const result = text ? JSON.parse(text) : null;
    if (!response.ok)
      throw new Error(
        Object.values(result?.errors ?? {})
          .flat()
          .join("\n") ||
          result?.message ||
          `Request failed (${response.status})`,
      );
    return result as T;
  } finally {
    clearTimeout(timeout);
  }
}
export type Category = {
  id: number;
  name: string;
  daily_limit: number;
  icon?: string;
};
export type Assignment = {
  id: number;
  local_date: string;
  question: {
    text: string;
    category_id: number;
    type: string;
    image_path?: string | null;
    options: { id: number; text: string }[];
  };
  result: null | { answer: string; correct: boolean; coins_awarded: number };
};

export function imageUrl(path: string) {
  return base
    ? `${base.replace(/\/api\/v1\/?$/, "")}/storage/${path.split("/").map(encodeURIComponent).join("/")}`
    : undefined;
}
