# Drupal + React Headless CMS

A monorepo that combines a **Drupal 10/11 backend** with a **React 18 single-page application (SPA)** embedded in a custom Drupal theme. The project exposes custom REST resources for managing **Articles** and **Project Trackers**, while Drupal remains the content, authentication, permissions, and API layer.

## Key Features

- Drupal 11 backend managed through Composer
- React 19 frontend located inside a custom Drupal theme
- Single-repository architecture for backend and frontend code
- Custom REST API module for Content and Project Trackers
- Public read access for Project Tracker content
- Authenticated create, update, and delete operations
- Drupal session or HTTP Basic authentication support
- CSRF protection for mutating requests
- Filtering and pagination for Project Tracker collections

## Architecture

Drupal acts as the CMS and API provider. React consumes Drupal's REST API endpoints and renders the user interface. Production frontend assets can be built and served through the custom Drupal theme, while the Vite development server can be used during frontend development.

## License

See [`LICENSE.txt`](LICENSE.txt) for license information.
