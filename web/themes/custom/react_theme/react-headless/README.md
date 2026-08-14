# react-headless

React 19 + TypeScript + Vite SPA, embedded inside the `react_theme` Drupal
theme. See the [repository root README](../../../../../../README.md) for
the overall architecture, API endpoints, and setup instructions.

## Local commands

```bash
npm install
npm run dev      # standalone dev server at http://localhost:5173, reads
                 # VITE_DRUPAL_API_URL from .env (copy from .env.example)
npm run build    # production build → ../js/app.js + ../css/app.css,
                 # picked up by react_theme.libraries.yml
npm run lint
```

## Structure

- `src/api/client.js` — the single Axios client for all Drupal REST calls
  (auth, topics, tasks). Handles CSRF token attachment and error unwrapping.
- `src/hooks/useAuth.jsx` — auth context; bootstraps from
  `window.drupalSettings.reactApp.currentUser` when embedded in Drupal, or
  from a fresh login otherwise.
- `src/pages/`, `src/components/` — routed pages and the widgets they use.
