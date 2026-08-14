# Drupal + React Headless CMS

A monorepo: a Drupal 11 backend exposing custom REST endpoints, consumed by a
React 19 SPA that is embedded inside a custom Drupal theme (`react_theme`).

> **Note on this README:** an earlier version of this file described a
> different, more ambitious architecture (a `content_api` module with full
> Articles + Project Tracker CRUD, a CORS event subscriber, exported
> `config/sync/` permissions, etc.). None of that existed in the actual
> codebase — the real module is `apiservices`, and it implements a smaller,
> concrete set of endpoints. This version documents what is actually here.

---

## Repository Structure

```
drupal-react-headless/
├── composer.json
├── scripts/setup.sh                          ← bootstrapper (Drupal install + npm build)
└── web/
    ├── modules/custom/apiservices/            ← custom REST module
    │   ├── apiservices.info.yml
    │   └── src/Plugin/rest/resource/
    │       ├── TopicList.php                  ← GET  /api/topiclist        (landing_page nodes)
    │       ├── TaskList.php                    ← GET  /api/task-list,
    │       │                                     POST /api/add-task        (project_tracker nodes)
    │       ├── UserLogin.php                   ← POST /api/user-login
    │       └── UserRegistration.php            ← POST /api/user-registration
    └── themes/custom/react_theme/
        ├── react_theme.info.yml
        ├── react_theme.libraries.yml           ← attaches compiled js/app.js + css/app.css
        ├── react_theme.theme                    ← injects drupalSettings.reactApp (baseUrl, csrfToken, currentUser)
        ├── templates/page.html.twig             ← renders <div id="react-root">, React mounts there
        ├── css/theme.css                        ← static theme chrome (header/footer)
        └── react-headless/                      ← the React app's source (Vite + TS)
            └── src/
                ├── api/client.js                ← single Axios client: auth + topics + tasks
                ├── hooks/useAuth.jsx
                ├── components/ (TopBar, TopicList, TaskList, CreateTask)
                └── pages/ (HomePage, LoginPage, RegisterPage, DashboardPage)
```

There is no `config/sync/` in this repo — REST resource permissions, CORS,
and the `project_tracker` / `landing_page` content types and their fields
are assumed to already exist on the target Drupal site (created manually or
via a config export that isn't checked in yet). See **Known Gaps** below.

---

## Content Types Used

| Content type | Fields consumed by the API | Used by |
|---|---|---|
| `landing_page` | `field_sub_heading`, `field_description`, `field_trending` | `TopicList.php` → `/api/topiclist` |
| `project_tracker` | `field_description`, `field_due_date`, `field_severity`, `field_status` | `TaskList.php` → `/api/task-list`, `/api/add-task` |

---

## REST API

All endpoints return JSON in the shape `{ status, message?, result }`.

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/api/topiclist?_format=json` | Public | Landing page "topics" |
| GET | `/api/task-list?_format=json` | Public | Project tracker tasks |
| POST | `/api/add-task?_format=json` | **Authenticated** (enforced in code) | `{ title, description, due_date, severity, status }` |
| POST | `/api/user-login?_format=json` | Public | `{ email, password }` → returns `current_user`, `csrf_token`, `logout_token` |
| POST | `/api/user-registration?_format=json` | Public | `{ firstname, lastname, email, password }` |

Mutating requests (`POST` here) must send the `X-CSRF-Token` header, using
the token returned by login (or `GET /session/token`). The SPA's Axios
client (`src/api/client.js`) attaches this automatically once a token is in
`sessionStorage` or `drupalSettings`.

---

## Quick Start

```bash
chmod +x scripts/setup.sh
./scripts/setup.sh                 # composer install, Drupal install, enable module+theme

# Build the React app into the theme (production):
cd web/themes/custom/react_theme/react-headless
npm install
npm run build                      # outputs js/app.js + css/app.css into ../{js,css}
                                    # (see vite.config.ts) — required before
                                    # react_theme is usable, since the theme
                                    # does not ship pre-built assets.

# OR, for frontend development with hot reload against a running Drupal:
cp .env.example .env               # set VITE_DRUPAL_API_URL to your Drupal base URL
npm run dev                        # http://localhost:5173
```

The standalone dev server (`npm run dev`) and the theme-embedded build read
the API base URL differently: the embedded build reads
`window.drupalSettings.reactApp.baseUrl` (same-origin, injected by
`react_theme.theme`); the standalone dev server falls back to
`VITE_DRUPAL_API_URL` from `.env`, and needs CORS enabled on Drupal since
it's cross-origin (see **Known Gaps**).

---

## Known Gaps / Recommended Next Steps

This is an honest list of things that are *not* handled by the current
code, so they aren't discovered by surprise in review or production:

1. **CORS is not configured anywhere in this repo.** Running the SPA via
   `npm run dev` (a different origin/port from Drupal) will fail on
   mutating requests until CORS is enabled — e.g. via `cors.config` in
   `services.yml`, which isn't checked in here (correctly — it belongs in
   an untracked, environment-specific `sites/*/services.yml`, not in git).
   Document your CORS settings for your environment rather than assuming
   the defaults work.
2. **REST resource permissions aren't exported.** Which roles can call
   which endpoint depends on the site's REST/RESTUI config, which isn't in
   `config/sync/` here. `TaskList::post()` now enforces authentication in
   code as a defense-in-depth measure, but the other endpoints rely
   entirely on whatever permissions exist on the target site — export and
   commit that config once it's finalized.
3. **No automated tests** (PHPUnit for the module, or a JS test runner for
   the SPA). Given the auth and content-creation flows involved, this is
   the highest-leverage next addition.
4. **Password policy is duplicated** (regex in `UserRegistration.php` and
   again in `RegisterPage.jsx`). Fine for now, but drifting the two apart
   silently is an easy future bug — consider having the frontend surface
   the *backend's* rejection message rather than re-implementing the rule.
