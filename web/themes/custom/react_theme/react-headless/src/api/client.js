import axios from "axios";

// ---------------------------------------------------------------------------
// Single Axios client for all Drupal REST calls made by this SPA.
//
// This file used to be split across `api/client.js` and `api/drupalService.js`,
// each with its own Axios instance, its own CSRF interceptor, and its own
// (slightly different) idea of where the session/CSRF tokens were stored.
// That duplication was a bug risk (e.g. a token refreshed via one instance
// was invisible to the other). Everything now lives here.
// ---------------------------------------------------------------------------

/** Resolve the Drupal base URL: prefer drupalSettings (theme-embedded mode),
 *  fall back to the Vite env var (standalone dev-server mode). */
function getBaseUrl() {
  if (
    typeof window !== "undefined" &&
    window.drupalSettings?.reactApp?.baseUrl
  ) {
    return window.drupalSettings.reactApp.baseUrl;
  }
  return import.meta.env.VITE_DRUPAL_API_URL || "";
}

const API_URL = axios.create({
  baseURL: getBaseUrl(),
  headers: {
    "Content-Type": "application/json",
  },
  withCredentials: true, // send/receive the Drupal session cookie cross-origin
});

/** Attach the CSRF token to every mutating request. */
API_URL.interceptors.request.use((config) => {
	// config.params = {
	// 	...config.params,
	// 	langcode: localStorage.getItem("langcode") || "en",
	// };
  const method = config.method?.toUpperCase();
  if (["POST", "PATCH", "PUT", "DELETE"].includes(method)) {
    const drupalToken = window.drupalSettings?.reactApp?.csrfToken;
    const localToken = sessionStorage.getItem("csrf_token");
    const token = drupalToken || localToken;
    if (token) config.headers["X-CSRF-Token"] = token;
  }
  return config;
});

/** Unwrap Drupal's REST error shapes into plain Error objects so callers
 *  can just read `err.message`. */
API_URL.interceptors.response.use(
  (res) => res,
  (err) => {
    const body = err.response?.data;
    const message =
      (typeof body === "string" ? body : null) ||
      body?.result ||
      body?.message ||
      body?.error ||
      err.message ||
      "An unexpected error occurred.";
    return Promise.reject(new Error(message));
  },
);

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------

/** Logs in via the custom /api/user-login resource and persists the CSRF /
 *  logout tokens Drupal returns so subsequent mutating requests can use them. */
export const loginUser = async (email, password) => {
  const response = await API_URL.post("/api/user-login?_format=json", {
    email,
    password,
  });

  const result = response.data?.result || response.data;

  if (result?.csrf_token)
    sessionStorage.setItem("csrf_token", result.csrf_token);
  if (result?.logout_token)
    sessionStorage.setItem("logout_token", result.logout_token);

  return result?.current_user || result;
};

/** Logs out both server-side (invalidate the Drupal session) and client-side
 *  (clear any cached tokens), even if the network call fails. */
export const logoutUser = async (token) => {
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
      "Server logout request failed, clearing local session anyway.",
      error,
    );
  } finally {
    sessionStorage.removeItem("csrf_token");
    sessionStorage.removeItem("logout_token");
    sessionStorage.removeItem("user");
  }
};

/** Registers a new Drupal user via /api/user-registration. */
export const registerUser = async (userData) => {
  const payload = {
    firstname: userData.firstname,
    lastname: userData.lastname,
    email: userData.email,
    password: userData.password,
  };
  return API_URL.post("/api/user-registration?_format=json", payload);
};

// ---------------------------------------------------------------------------
// Fetch Languages (for language switcher in TopBar)
// ---------------------------------------------------------------------------

export const getLanguages = async () => API_URL.get("/api/language-list?_format=json");

// ---------------------------------------------------------------------------
// Topics (landing_page content)
// ---------------------------------------------------------------------------

export const getTopics = async (langcode = "en") =>
  API_URL.get("/api/topiclist", {
    params: {
      _format: "json",
      langcode,
    },
  });

export const addTopic = async (topicData) => {
  const payload = {
    title: topicData.title,
    subheading: topicData.subheading,
    description: topicData.description,
    trending: topicData.trending,
  };
  return API_URL.post("/api/add-topic?_format=json", payload);
};

// ---------------------------------------------------------------------------
// Users (for task assignment)
// ---------------------------------------------------------------------------

export const getUsers = async () => API_URL.get("/api/user-list?_format=json");

// ---------------------------------------------------------------------------
// Project Tracker tasks
// ---------------------------------------------------------------------------

export const getTasks = async () => API_URL.get("/api/task-list?_format=json");

export const getTaskById = async (id) =>
  API_URL.get(`/api/task/${id}?_format=json`);

export const updateTask = async (id, taskData) => {
  const payload = {
    title: taskData.title,
    description: taskData.description,
    due_date: taskData.due_date,
    severity: taskData.severity,
    status: taskData.status,
  };
  return API_URL.post(`/api/task/${id}/update?_format=json`, payload);
};

export const addTask = async (taskData) => {
  const payload = {
    title: taskData.title,
    description: taskData.description,
    due_date: taskData.due_date,
    severity: taskData.severity,
    status: taskData.status,
    assigned_to: taskData.assigned_to,
  };
  return API_URL.post("/api/add-task?_format=json", payload);
};

export default API_URL;
