# Drupal + React Headless CMS

A monorepo that combines a **Drupal 10/11 backend** with a **React 18 single-page application (SPA)** embedded in a custom Drupal theme. The project exposes custom REST resources for managing **Articles** and **Project Trackers**, while Drupal remains the content, authentication, permissions, and API layer.

## Key Features

- Drupal 10 backend managed through Composer
- React 18 frontend located inside a custom Drupal theme
- Single-repository architecture for backend and frontend code
- Custom REST API module for Articles and Project Trackers
- Public read access for Project Tracker content
- Authenticated create, update, and delete operations
- Drupal session or HTTP Basic authentication support
- CSRF protection for mutating requests
- Filtering and pagination for Project Tracker collections
- Automated local bootstrap through `scripts/setup.sh`

## Architecture

```text
Browser
|
v
React 18 SPA
|
| Axios / JSON / Drupal session
v
Custom Drupal REST resources
|
v
Drupal entities, permissions, and database
```

Drupal acts as the CMS and API provider. React consumes Drupal's JSON endpoints and renders the user interface. Production frontend assets can be built and served through the custom Drupal theme, while the Vite development server can be used during frontend development.

## Repository Structure

```text
drupal-react-headless/
├── composer.json
├── composer.lock
├── db_backup/
├── scripts/
│ └── setup.sh
└── web/
├── modules/custom/content_api/
│ ├── content_api.info.yml
│ ├── content_api.install
│ ├── content_api.routing.yml
│ ├── content_api.services.yml
│ ├── config/install/
│ │ ├── rest.resource.content_api.articles.yml
│ │ └── rest.resource.content_api.project_trackers.yml
│ └── src/
│ ├── Controller/OptionsController.php
│ ├── EventSubscriber/CorsSubscriber.php
│ └── Plugin/rest/resource/
│ ├── ArticlesResource.php
│ └── ProjectTrackersResource.php
└── themes/custom/react_theme/
└── react-app/
└── src/
├── api/
│ ├── client.js
│ ├── articles.js
│ └── projectTrackers.js
├── hooks/
│ ├── useAuth.js
│ ├── useArticles.js
│ └── useProjectTrackers.js
├── components/
│ ├── ProjectTrackerCard.jsx
│ ├── ProjectTrackerForm.jsx
│ ├── Modal.jsx
│ ├── ConfirmDialog.jsx
│ ├── Pagination.jsx
│ └── TopBar.jsx
└── pages/
├── ArticlesPage.jsx
├── ProjectTrackersPage.jsx
└── ProjectTrackerDetailPage.jsx
```

## Prerequisites

Install the following tools before setting up the project:

- PHP and required Drupal PHP extensions
- Composer 2
- A Drupal-compatible MySQL or MariaDB database
- Node.js and npm
- A local web server or Drupal-compatible local development environment
- Git

> Exact runtime versions should be validated against `composer.json`, `composer.lock`, and the frontend `package.json` before installation.

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/rahulk1011/drupal-react-headless.git
cd drupal-react-headless
```

### 2. Run the setup script

```bash
chmod +x scripts/setup.sh
./scripts/setup.sh
```

The setup script is intended to install Drupal dependencies, configure the application, enable the custom module and theme, and build the React application.

### 3. Start frontend development

```bash
cd web/themes/custom/react_theme/react-app
cp .env.example .env
npm install
npm run dev
```

Set the Drupal base URL in `.env`:

```dotenv
VITE_DRUPAL_BASE_URL=http://localhost:8888
```

The Vite development server is normally available at:

```text
http://localhost:5173
```

## Frontend Build

Create a production frontend build with:

```bash
cd web/themes/custom/react_theme/react-app
npm install
npm run build
```

After building, clear Drupal caches so that updated frontend assets are discovered:

```bash
vendor/bin/drush cr
```

## REST API

All API requests require the Drupal JSON format query parameter:

```text
?_format=json
```

### Authentication

The API supports either:

- Drupal session-cookie authentication
- HTTP Basic authentication

Requests that modify content also require an `X-CSRF-Token` header. Retrieve a token from:

```http
GET /session/token
```

### Article Endpoints

| Method   | Endpoint                          | Access        |
| -------- | --------------------------------- | ------------- |
| `GET`    | `/api/articles?_format=json`      | Public        |
| `GET`    | `/api/articles/{id}?_format=json` | Public        |
| `POST`   | `/api/articles?_format=json`      | Authenticated |
| `PATCH`  | `/api/articles/{id}?_format=json` | Authenticated |
| `DELETE` | `/api/articles/{id}?_format=json` | Authenticated |

Example Article payload:

```json
{
  "title": "Headless Drupal with React",
  "body": "Article body content",
  "summary": "A short article summary",
  "status": 1
}
```

### Project Tracker Endpoints

| Method   | Endpoint                                  | Access        |
| -------- | ----------------------------------------- | ------------- |
| `GET`    | `/api/project-trackers?_format=json`      | Public        |
| `GET`    | `/api/project-trackers/{id}?_format=json` | Public        |
| `POST`   | `/api/project-trackers?_format=json`      | Authenticated |
| `PATCH`  | `/api/project-trackers/{id}?_format=json` | Authenticated |
| `DELETE` | `/api/project-trackers/{id}?_format=json` | Authenticated |

Supported collection parameters:

- `page`
- `limit`
- `status`
- `severity`

Example filtered request:

```http
GET /api/project-trackers?_format=json&page=1&limit=10&status=open&severity=high
```

Example Project Tracker payload:

```json
{
  "title": "Complete API documentation",
  "description": "Document all custom REST endpoints and payloads.",
  "status": "in_progress",
  "severity": "high",
  "due_date": "2026-08-31"
}
```

Supported status values:

- `open`
- `in_progress`
- `on_hold`
- `completed`
- `cancelled`

Supported severity values:

- `low`
- `medium`
- `high`
- `critical`

The `due_date` value must use `YYYY-MM-DD` or be `null`.

## Project Tracker Content Type

The `project_tracker` content type is created programmatically when the `content_api` module is installed.

| Label       | Machine name           | Drupal field type      | Allowed values                                             |
| ----------- | ---------------------- | ---------------------- | ---------------------------------------------------------- |
| Title       | `title`                | String                 | Required title value                                       |
| Description | `field_pt_description` | Text (long, formatted) | Free-form text                                             |
| Status      | `field_pt_status`      | List (string)          | `open`, `in_progress`, `on_hold`, `completed`, `cancelled` |
| Severity    | `field_pt_severity`    | List (string)          | `low`, `medium`, `high`, `critical`                        |
| Due Date    | `field_pt_due_date`    | Datetime (date only)   | `YYYY-MM-DD`                                               |

Anonymous users can view Project Tracker content. Authenticated users can create content and edit or delete content according to the Drupal permissions configured by the module/site.

## React Routes

| Route                   | Access        | Description                                                             |
| ----------------------- | ------------- | ----------------------------------------------------------------------- |
| `/project-trackers`     | Public        | Lists Project Trackers; authenticated users receive management controls |
| `/project-trackers/:id` | Public        | Displays one Project Tracker                                            |
| `/articles`             | Authenticated | Lists and manages Articles                                              |
| `/articles/:id`         | Authenticated | Displays one Article                                                    |
| `/login`                | Public        | Provides Drupal session login                                           |

## API Examples

### Retrieve Project Trackers

```bash
curl "http://localhost:8888/api/project-trackers?_format=json&page=1&limit=10"
```

### Retrieve a CSRF token

```bash
curl \
--cookie-jar cookies.txt \
--cookie cookies.txt \
"http://localhost:8888/session/token"
```

### Create a Project Tracker

```bash
curl --request POST \
--url "http://localhost:8888/api/project-trackers?_format=json" \
--header "Content-Type: application/json" \
--header "X-CSRF-Token: YOUR_CSRF_TOKEN" \
--cookie cookies.txt \
--data '{
"title": "Prepare release",
"description": "Complete testing and deployment checks.",
"status": "open",
"severity": "high",
"due_date": "2026-08-31"
}'
```

## Development Workflow

### Drupal development

```bash
composer install
vendor/bin/drush cr
```

After changing module installation logic, test it against a clean environment or reinstall the module only when it is safe to remove module-owned configuration and data.

### React development

```bash
cd web/themes/custom/react_theme/react-app
npm install
npm run dev
```

Use the Vite development server for local frontend work and `npm run build` before testing the theme-integrated production assets.

## Security Notes

- Do not commit `.env` files, database credentials, session cookies, or API credentials.
- Keep Drupal core, contributed packages, npm dependencies, and Composer dependencies updated.
- Use HTTPS outside local development.
- Restrict write operations through Drupal permissions rather than relying only on frontend route protection.
- Validate and sanitize all API input on the server.
- Keep CORS origins limited to trusted frontend hosts.
- Do not use administrator credentials in frontend source code.
- Database backups may contain sensitive information and should not be committed to a public repository unless thoroughly sanitized.

## Troubleshooting

### API returns `403 Access Denied`

- Confirm that the user is authenticated.
- Verify the user's Drupal permissions.
- Include a valid `X-CSRF-Token` for `POST`, `PATCH`, and `DELETE` requests.
- Confirm that the browser or API client sends the Drupal session cookie.

### Browser reports a CORS error

- Confirm that the React origin is allowed by the Drupal CORS configuration.
- Verify that preflight `OPTIONS` requests return the required headers.
- Ensure that the frontend base URL points to the correct Drupal host.

### API returns an unsupported-format error

Append the required format parameter:

```text
?_format=json
```

### React changes are not visible in Drupal

```bash
cd web/themes/custom/react_theme/react-app
npm run build
cd ../../../../../..
vendor/bin/drush cr
```

Also confirm that the Drupal theme library references the generated build files.

## Recommended Improvements

- Add automated PHPUnit or Drupal Kernel tests for REST resources.
- Add React component and API-client tests.
- Add CI checks for Composer validation, Drupal coding standards, frontend linting, and production builds.
- Replace or sanitize committed database backups and document a safe local data-import process.
- Document tested PHP, database, Node.js, and npm versions.
- Add an API schema such as OpenAPI for easier integration and testing.
- Add environment-specific CORS guidance and sample configuration.
- Add screenshots or a short demo GIF of the application.

## Contributing

1. Create a feature branch from `main`.
2. Make focused changes with clear commit messages.
3. Run backend and frontend quality checks.
4. Build the React application and test the Drupal-integrated output.
5. Open a pull request describing the change and its testing evidence.

## License

See [`LICENSE.txt`](LICENSE.txt) for license information.
