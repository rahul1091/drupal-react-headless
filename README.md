# Drupal + React CMS

A monorepo: Drupal 10 backend with a React 18 SPA embedded inside the custom theme.  
Two content types are exposed via Drupal REST Resources: **Article** and **Project Tracker**.

---

## Repository Structure

```
drupal-react-cms/
├── composer.json
├── scripts/setup.sh                         ← one-command bootstrapper
├── config/sync/                             ← Drupal CMI exports
└── web/
    ├── modules/custom/content_api/          ← custom REST module
    │   ├── content_api.info.yml
    │   ├── content_api.install              ← creates project_tracker type + fields
    │   ├── content_api.routing.yml          ← per-method routes with access rules
    │   ├── content_api.services.yml
    │   ├── config/install/
    │   │   ├── rest.resource.content_api.articles.yml
    │   │   └── rest.resource.content_api.project_trackers.yml
    │   └── src/
    │       ├── Controller/OptionsController.php    ← CORS preflight handler
    │       ├── EventSubscriber/CorsSubscriber.php
    │       └── Plugin/rest/resource/
    │           ├── ArticlesResource.php            ← Article CRUD
    │           └── ProjectTrackersResource.php     ← ProjectTracker CRUD
    └── themes/custom/react_theme/
        └── react-app/src/
            ├── api/
            │   ├── client.js               ← Axios + _format=json + CSRF
            │   ├── articles.js
            │   └── projectTrackers.js      ← NEW
            ├── hooks/
            │   ├── useAuth.js
            │   ├── useArticles.js
            │   └── useProjectTrackers.js   ← NEW
            ├── components/
            │   ├── ProjectTrackerCard.jsx  ← NEW
            │   ├── ProjectTrackerForm.jsx  ← NEW
            │   └── … (shared: Modal, ConfirmDialog, Pagination, TopBar)
            └── pages/
                ├── ProjectTrackersPage.jsx     ← NEW (public)
                ├── ProjectTrackerDetailPage.jsx ← NEW (public)
                ├── ArticlesPage.jsx
                └── …
```

---

## REST API

All endpoints require `?_format=json`.  
Authentication via HTTP Basic Auth header or Drupal session cookie.  
Mutating requests require `X-CSRF-Token` header (token from `GET /session/token`).

### Articles

| Method | Path | Access |
|--------|------|--------|
| GET | `/api/articles?_format=json` | Public |
| GET | `/api/articles/{id}?_format=json` | Public |
| POST | `/api/articles?_format=json` | **Authenticated** |
| PATCH | `/api/articles/{id}?_format=json` | **Authenticated** |
| DELETE | `/api/articles/{id}?_format=json` | **Authenticated** |

Request body (POST/PATCH):
```json
{ "title": "string", "body": "string", "summary": "string", "status": 1 }
```

### Project Trackers

| Method | Path | Access |
|--------|------|--------|
| GET | `/api/project-trackers?_format=json` | **Public** |
| GET | `/api/project-trackers/{id}?_format=json` | **Public** |
| POST | `/api/project-trackers?_format=json` | **Authenticated** |
| PATCH | `/api/project-trackers/{id}?_format=json` | **Authenticated** |
| DELETE | `/api/project-trackers/{id}?_format=json` | **Authenticated** |

Request body (POST/PATCH):
```json
{
  "title":       "string (required on POST)",
  "description": "string",
  "status":      "open | in_progress | on_hold | completed | cancelled",
  "severity":    "low | medium | high | critical",
  "due_date":    "YYYY-MM-DD or null"
}
```

Collection query params: `page`, `limit`, `status`, `severity`.

---

## project_tracker Content Type

Created programmatically by `content_api_install()`.

| Field | Machine name | Drupal type | Values |
|-------|-------------|-------------|--------|
| Title | `title` | String | — |
| Description | `field_pt_description` | Text (long, formatted) | — |
| Status | `field_pt_status` | List (string) | open, in_progress, on_hold, completed, cancelled |
| Severity | `field_pt_severity` | List (string) | low, medium, high, critical |
| Due Date | `field_pt_due_date` | Datetime (date only) | YYYY-MM-DD |

**Permissions:** authenticated users can create/edit/delete their own; anonymous can view.

---

## Quick Start

```bash
chmod +x scripts/setup.sh
./scripts/setup.sh            # installs Drupal, enables module+theme, builds React

# React dev server
cd web/themes/custom/react_theme/react-app
cp .env.example .env          # set VITE_DRUPAL_BASE_URL=http://localhost:8888
npm run dev                   # http://localhost:5173
```

---

## React Routes

| Path | Access | Description |
|------|--------|-------------|
| `/project-trackers` | Public | List all trackers; auth users see create/edit/delete |
| `/project-trackers/:id` | Public | Tracker detail |
| `/articles` | Auth required | Article list (full CRUD) |
| `/articles/:id` | Auth required | Article detail |
| `/login` | Public | Drupal session login |
