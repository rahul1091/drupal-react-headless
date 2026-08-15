# Drupal + React Headless CMS

A monorepo: a Drupal 11 backend exposing custom REST endpoints, consumed by a
React 19 SPA that is embedded inside a custom Drupal theme (`react_theme`).

> **Note on this README:** an earlier version of this file described a
> different, more ambitious architecture (a `content_api` module with full
> Articles + Project Tracker CRUD, a CORS event subscriber, exported
> `config/sync/` permissions, etc.). None of that existed in the actual
> codebase — the real module is `apiservices`. This version documents what
> is actually here, including task assignment/editing and admin-only topic
> creation.

---

## Repository Structure

```
drupal-react-headless/
├── composer.json                              ← pins Drupal core to ^11.4
├── scripts/setup.sh                          ← bootstrapper (Drupal install + npm build)
└── web/
    ├── modules/custom/apiservices/            ← custom REST module (declares core_version_requirement: ^10 || ^11,
    │   │                                        though composer.json above fixes the actual site to 11)
    │   ├── apiservices.info.yml
    │   ├── apiservices.install                 ← creates field_assigned_to via update hook
    │   ├── apiservices.services.yml            ← registers CrossOriginSessionCookieSubscriber
    │   ├── src/EventSubscriber/
    │   │   └── CrossOriginSessionCookieSubscriber.php  ← makes the session cookie work cross-origin (see Known Gaps)
    │   └── src/Plugin/rest/resource/
    │       ├── TopicList.php                  ← GET  /api/topiclist        (landing_page nodes),
    │       │                                     POST /api/add-topic       (admin-only)
    │       ├── TaskList.php                    ← GET  /api/task-list       (only tasks assigned to the caller),
    │       │                                     POST /api/add-task        (project_tracker nodes)
    │       ├── TaskDetail.php                  ← GET/POST /api/task/{id}   (view/edit a task - assignee-only)
    │       ├── UserList.php                    ← GET  /api/user-list       (for the Assign To dropdown)
    │       ├── UserLogin.php                   ← POST /api/user-login      (also returns isAdmin)
    │       └── UserRegistration.php            ← POST /api/user-registration
    └── themes/custom/react_theme/
        ├── react_theme.info.yml
        ├── react_theme.libraries.yml           ← attaches compiled js/app.js + css/app.css
        ├── react_theme.theme                    ← injects drupalSettings.reactApp (baseUrl, csrfToken, currentUser)
        ├── templates/page.html.twig             ← renders <div id="react-root">, React mounts there
        ├── css/theme.css                        ← static theme chrome (header/footer)
        └── react-headless/                      ← the React app's source (Vite + TS)
            └── src/
                ├── api/client.js                ← single Axios client: auth + topics + tasks + users
                ├── hooks/useAuth.jsx             ← also exposes user.isAdmin
                ├── components/
                │   ├── TopBar, TopicList
                │   ├── TaskList                  ← "my assigned tasks" grid, with an Edit button per card
                │   ├── CreateTask, EditTask       ← create vs. edit a task (edit has no reassignment field)
                │   └── AddTopic                  ← admin-only topic creation
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
| `landing_page` | `field_sub_heading`, `field_description`, `field_trending` (stores the lowercase string `"yes"`/`"no"`) | `TopicList.php` → `/api/topiclist`, `/api/add-topic` |
| `project_tracker` | `field_description`, `field_due_date`, `field_severity`, `field_status`, `field_assigned_to` (entity reference → user, added by `apiservices_update_9001()`) | `TaskList.php`, `TaskDetail.php` → `/api/task-list`, `/api/add-task`, `/api/task/{id}` |

---

## REST API

All endpoints return JSON in the shape `{ status, message?, result }`.

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/api/topiclist?_format=json` | Public | Landing page "topics" |
| POST | `/api/add-topic?_format=json` | **Admin only** (`administrator` role) | `{ title, subheading, description, trending }` — `trending` is normalized to lowercase `"yes"`/`"no"` before saving into `field_trending`; the Add Topic form's dropdown shows capitalized "Yes"/"No" labels to the user but sends the lowercase value |
| GET | `/api/task-list?_format=json` | **Authenticated** | Only tasks **assigned to the caller** (`field_assigned_to`), not all tasks |
| POST | `/api/add-task?_format=json` | **Authenticated** | `{ title, description, due_date, severity, status, assigned_to }` — `assigned_to` (uid) is required and validated against an active user; the creator is recorded automatically as the node's author |
| GET | `/api/task/{id}?_format=json` | **Authenticated, assignee only** | Fetch a single task, for the edit page |
| POST | `/api/task/{id}?_format=json` | **Authenticated, assignee only** | `{ title, description, due_date, severity, status }` — updates a task; reassignment isn't allowed through this endpoint (uses POST rather than PATCH — see comment in `TaskDetail.php`) |
| GET | `/api/user-list?_format=json` | **Authenticated** | `{ uid, name, email }[]` — used to populate the "Assign To" dropdown |
| POST | `/api/user-login?_format=json` | Public | `{ email, password }` → returns `current_user` (includes `isAdmin: boolean`, checked against the `administrator` role machine name), `csrf_token`, `logout_token` |
| POST | `/api/user-registration?_format=json` | Public | `{ firstname, lastname, email, password }` |

Mutating requests (`POST` here) must send the `X-CSRF-Token` header, using
the token returned by login (or `GET /session/token`). The SPA's Axios
client (`src/api/client.js`) attaches this automatically once a token is in
`sessionStorage` or `drupalSettings`.

---

## Frontend Routes

| Route | Access | Page |
|---|---|---|
| `/` | Public | Home |
| `/login`, `/register` | Public | Auth |
| `/dashboard` | Authenticated | User info, trending topics, "My Tasks" |
| `/create-task` | Authenticated | Create a task and assign it to any user |
| `/edit-task/:id` | Authenticated, assignee only (enforced server-side) | Edit a task assigned to you |
| `/add-topic` | **Admin only** (`AdminRoute` redirects non-admins to `/dashboard`) | Create a landing-page topic |

---

## Quick Start

```bash
chmod +x scripts/setup.sh
./scripts/setup.sh                 # composer install, Drupal install, enable module+theme

# Run pending updates (needed for field_assigned_to - see apiservices.install):
drush updb

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
it's cross-origin (see **Known Gaps**). **It also needs an `https://` Drupal
URL** — see the cross-origin session cookie note below, and the `.env.example`
drift flagged in Known Gaps.

---

## Known Gaps / Recommended Next Steps

This is an honest list of things that are *not* handled by the current
code, so they aren't discovered by surprise in review or production:

1. **`.env.example` no longer matches what the backend requires.** It
   currently points at a plain `http://drupal-headless.site` placeholder
   with no explanation. `CrossOriginSessionCookieSubscriber` (registered in
   `apiservices.services.yml`) only rewrites the session cookie to
   `SameSite=None; Secure` over HTTPS — pointing the dev server at an
   `http://` Drupal URL will let login succeed but silently make every
   later authenticated call (`/api/task-list`, `/api/user-list`,
   `/api/add-task`, `/api/task/{id}`) look anonymous. Point
   `VITE_DRUPAL_API_URL` at your Drupal site's `https://` URL instead.
2. **`.gitignore` no longer excludes build artifacts or the DB dump.** It
   doesn't currently ignore `web/themes/custom/react_theme/{js,css}`
   (the compiled output of `npm run build`) or `db_backup/` (which
   contains a raw database dump with real user data/password hashes and
   shouldn't be committed). Worth re-adding both entries.
3. **CORS is not configured anywhere in this repo.** Running the SPA via
   `npm run dev` (a different origin/port from Drupal) will fail on
   mutating requests until CORS is enabled — e.g. via `cors.config` in
   `services.yml`, which isn't checked in here (correctly — it belongs in
   an untracked, environment-specific `sites/*/services.yml`, not in git).
4. **REST resource permissions aren't exported.** Which roles can call
   which endpoint depends on the site's REST/RESTUI config, which isn't in
   `config/sync/` here. Several endpoints enforce authentication/role
   checks in code as defense-in-depth, but that's not a substitute for
   exporting and committing the actual permissions config.
5. **No automated tests** (PHPUnit for the module, or a JS test runner for
   the SPA). Given the auth, assignment, and content-creation flows
   involved, this is the highest-leverage next addition.
6. **Password policy is duplicated** (regex in `UserRegistration.php` and
   again in `RegisterPage.jsx`). Fine for now, but drifting the two apart
   silently is an easy future bug — consider having the frontend surface
   the *backend's* rejection message rather than re-implementing the rule.
