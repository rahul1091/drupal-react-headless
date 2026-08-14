import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./css/index.css";
import App from "./App.js";

createRoot(document.getElementById("react-root")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
