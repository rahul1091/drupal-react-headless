# Drupal + React Headless CMS

A monorepo that combines a **Drupal 11 backend** with a **React 19 single-page application (SPA)** embedded in a custom Drupal theme. The project exposes custom REST resources for managing **Content** and **Project Trackers**, while Drupal remains the content, authentication, permissions, and API layer.

## Key Features

- Drupal 11 backend managed through Composer
- React 19 frontend located inside a custom Drupal theme
- Single-repository architecture for backend and frontend code
- Public read access for Project Tracker content
- Authenticated create, update, and delete operations
- Drupal session or HTTP Basic authentication support
- CSRF protection for mutating requests

## Architecture

Drupal acts as the CMS and API provider. React consumes Drupal's REST API endpoints and renders the user interface. Production frontend assets can be built and served through the custom Drupal theme, while the Vite development server can be used during frontend development.

## Technical Highlights

- **Headless Drupal Architecture**​: Decoupled Drupal and React architecture powered by RESTful integrations​
- **Custom REST API Development**​: Custom Drupal APIs for content and project tracker management​
- **Secure API Implementation**​: Authenticated API access with CSRF protection and security controls​
- **Custom CORS Management**​: Dedicated cross-origin request handling for secure frontend-backend communication​
- **Modern React Application Structure**​: Modular frontend design leveraging APIs, Hooks, Components, and Pages​
- **Mono-Repo Development Strategy**​: Drupal backend and React frontend are maintained within a single repository​
- **API-First Design Pattern**​: Business functionality exposed through reusable and scalable APIs​
- **Multilingual Support​**: Language-aware APIs and React internationalization​

## Functional Highlights​

- **Client Information System**​: Centralized management of client and project-related information​
- **Project Tracker Management System**​: A structured tracking and management of project activities and deliverables​
- **Project Task Status Tracking​**: Support for multiple task statuses throughout the project lifecycle​
- **Testimonials & Trending Topics Information**​: Administrative capability to manage client testimonials and trending content​
- **Full CRUD Operations​**: Users can Create, Read, Update, and Delete records using API-driven workflows​
- **Role-Based Access Control**​: Permission-based access for authenticated and anonymous users​
- **Public and Authenticated User Experience​**: The platform offers different experiences based on user authentication status​
- **Localized Experience**​: Translated content, localized UI and language switching​

## License

See [`LICENSE.txt`](LICENSE.txt) for license information.
