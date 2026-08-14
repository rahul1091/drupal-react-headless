import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

// https://vite.dev/config/
//
// This app is compiled straight into the parent Drupal theme (react_theme)
// rather than served by its own web server. Two things follow from that:
//
// 1. The output must land where react_theme.libraries.yml expects it
//    (../js and ../css, i.e. web/themes/custom/react_theme/{js,css}),
//    with STABLE filenames - Vite's default content-hashed names
//    (index-a1b2c3.js) can't be referenced from a static .libraries.yml.
// 2. `emptyOutDir: true` only clears ../js, so it never touches this
//    project's own source tree.
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: "../js",
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: "app.js",
        chunkFileNames: "app-[name].js",
        assetFileNames: (assetInfo) =>
          assetInfo.name?.endsWith(".css") ? "../css/app.css" : "assets/[name][extname]",
      },
    },
    cssCodeSplit: false,
  },
});
