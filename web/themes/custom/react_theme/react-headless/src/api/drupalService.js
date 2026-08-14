import axios from "axios";

const API_URL = axios.create({
  baseURL: import.meta.env.VITE_DRUPAL_API_URL,
  headers: {
    "Content-Type": "application/json",
  },
  withCredentials: true, // Required to send/receive session cookies cross-origin
});

/** Request Interceptor to dynamically attach CSRF token */
API_URL.interceptors.request.use((config) => {
  const method = config.method?.toUpperCase();
  if (["POST", "PATCH", "PUT", "DELETE"].includes(method)) {
    const drupalToken = window.drupalSettings?.reactApp?.csrfToken;
    const localToken = sessionStorage.getItem("csrf_token");
    const token = drupalToken || localToken;
    if (token) {
      config.headers["X-CSRF-Token"] = token;
    }
  }
  return config;
});

/** User Registration API */
export const registerUser = async (userData) => {
  const payload = {
    firstname: userData.firstname,
    lastname: userData.lastname,
    email: userData.email,
    password: userData.password,
  };
  return API_URL.post("/api/user-registration?_format=json", payload);
};

/** User Login API */
export const userLogin = async (email, password) => {
  const response = await API_URL.post("/api/user-login?_format=json", {
    email,
    password,
  });
  if (response.data?.result?.csrf_token) {
    sessionStorage.setItem("csrf_token", response.data.result.csrf_token);
  }
  return response.data;
};

/** Fetch Topics List */
export const getTopics = async () => {
  return API_URL.get("/api/topiclist?_format=json");
};

/** Fetch Task List */
export const getTasks = async () => {
  return API_URL.get("/api/task-list?_format=json");
};

/** Add New Task */
export const addTask = async (taskData) => {
  const token = sessionStorage.getItem("csrf_token");
  const payload = {
    title: taskData.title,
    description: taskData.description,
    due_date: taskData.due_date,
    severity: taskData.severity,
    status: taskData.status,
  };
  return API_URL.post("/api/add-task?_format=json", payload);
};

export default API_URL;
