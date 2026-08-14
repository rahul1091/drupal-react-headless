import axios from "axios";

// Helper function to resolve dynamic base URL
function getBaseUrl() {
  if (
    typeof window !== "undefined" &&
    window.drupalSettings?.reactApp?.baseUrl
  ) {
    return window.drupalSettings.reactApp.baseUrl;
  }
  return import.meta.env.VITE_DRUPAL_API_URL || "";
}

// Instantiate Axios client instance
const API_URL = axios.create({
  baseURL: getBaseUrl(),
  headers: {
    "Content-Type": "application/json",
  },
  withCredentials: true, // Required to send/receive cookies cross-origin
});

/** Attach CSRF token to every mutating request */
API_URL.interceptors.request.use((config) => {
  const method = config.method?.toUpperCase();
  if (["POST", "PATCH", "PUT", "DELETE"].includes(method)) {
    const drupalToken = window.drupalSettings?.reactApp?.csrfToken;
    const localToken = sessionStorage.getItem("csrf_token");
    const token = drupalToken || localToken;
    if (token) config.headers["X-CSRF-Token"] = token;
  }
  return config;
});

/** Unwrap Drupal REST error shapes into plain Error objects */
API_URL.interceptors.response.use(
  (res) => res,
  (err) => {
    const body = err.response?.data;
    const message =
      (typeof body === "string" ? body : null) ||
      body?.message ||
      body?.error ||
      err.message ||
      "An unexpected error occurred.";
    return Promise.reject(new Error(message));
  },
);

// ---------------------------------------------------------------------------
// Exported API Methods
// ---------------------------------------------------------------------------

export const drupalLogin = async (email, password) => {
  // Using custom UserLogin endpoint payload
  const response = await API_URL.post("/api/user-login", {
    email,
    password,
  });

  const result = response.data?.result || response.data;

  // Store both CSRF and Logout tokens returned by Drupal
  if (result?.csrf_token) {
    sessionStorage.setItem("csrf_token", result.csrf_token);
  }
  if (result?.logout_token) {
    sessionStorage.setItem("logout_token", result.logout_token);
  }

  return result?.current_user || result;
};

export const drupalLogout = async (token) => {
  // Prefer logout_token, fall back to passed token or csrf_token
  const logoutToken =
    token ||
    sessionStorage.getItem("logout_token") ||
    sessionStorage.getItem("csrf_token") ||
    "";

  try {
    if (logoutToken) {
      await API_URL.post(
        `/user/logout?_format=json&token=${encodeURIComponent(logoutToken)}`,
      );
    }
  } catch (error) {
    console.warn(
      "Server logout request failed, clearing local session.",
      error,
    );
  } finally {
    // Always purge local storage items on logout
    sessionStorage.removeItem("csrf_token");
    sessionStorage.removeItem("logout_token");
    sessionStorage.removeItem("user");
  }
};

export default API_URL;
