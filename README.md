# Creative Connects

Creative Connects brings customer messaging, AI assistance, automation, CRM, campaigns, voice and commerce into one business workspace. Teams can manage WhatsApp and Instagram conversations, organize leads, configure automated journeys and continue conversations personally when needed.

The public website introduces the application through interactive AI demonstrations and industry use cases. The authenticated application provides company workspaces and a separate superadmin area for platform management.

## Features

| Area | Capabilities |
| --- | --- |
| Messaging | Shared WhatsApp and Instagram inbox, conversation history, assignments, attachments and message delivery status |
| AI and automation | Business-knowledge AI replies, chatbots, keyword workflows, chat menus, guided conversations and human handoff |
| Contacts and CRM | Contacts, tags, phonebooks, CSV and Excel imports, exports, lead stages and deal tracking |
| Campaigns | Phonebook and tag audiences, message templates, scheduled campaigns and delivery reporting |
| Voice | AI voice agents, call flows, WhatsApp calling and conversation follow-up through configured providers |
| Commerce | Branches, product catalog, inventory, orders, delivery settings, payment configuration and reports |
| Analytics | Conversation activity, campaign outcomes, pipeline and commerce reporting |
| Team administration | Company settings, team members, roles, permissions and channel connections |
| Superadmin | Company and user management, account status and suspension, platform usage and activity history; platform management only, without a subscription or billing administration module |
| Public website | AI-first landing page, interactive workflow graphics, industry pages, privacy policy and terms |

Features depend on the user's role, enabled settings and connected services. Public demonstrations use illustrative sample data; they do not send messages, place calls or confirm bookings.

### Industries

The landing page and `/industries` directory link to fifteen sector pages:

- Education & Edtech
- Healthcare
- Restaurants
- Real estate
- Spas & Salons
- Gyms
- Ecommerce
- Events & Webinar
- IT Services & Internet
- HR & Recruitment
- Banking & Fintech
- Automobile
- Travel & Tourism
- Government
- Marketing Agencies

Each page includes sample conversations, configurable workflows, setup guidance and capability boundaries. These examples describe communication use cases rather than promising built-in sector systems such as medical records, travel reservations or government case management.

## Technology

| Layer | Implementation |
| --- | --- |
| Frontend | React 19, TypeScript, React Router 8 in SPA mode, Vite 8, Material UI |
| Backend | Laravel 13, PHP 8.3+, Sanctum authentication, Spatie roles and permissions |
| Database | MySQL 8.4 in Docker Compose; SQLite is available for local development and tests |
| Realtime | Laravel Reverb, Laravel Echo and Pusher-compatible WebSockets |
| Background work | Laravel queues and scheduler |
| Voice sidecar | Python 3.11, FastAPI and aiortc |
| Infrastructure | Docker Compose, Nginx, coturn and AWS Lightsail |
| Tests | Vitest and Testing Library for the frontend; Pest/PHPUnit for the backend |

## Repository layout

```text
app/                       React pages, components, hooks and API client
app/components/public/     Shared public navigation and workflow graphics
app/data/industries.ts     Sector content and legacy route aliases
backend/                   Laravel API, services, jobs, migrations and tests
voice-sidecar/             Python voice and WebRTC service
public/                    Static assets, sitemap and built chatbot widget
.github/workflows/         Deployment automation
docker-compose.yml         Production service definitions
Dockerfile                 Frontend image
backend/Dockerfile         API and Reverb image
nginx.conf                 HTTPS reverse-proxy configuration
PRODUCT.md                 Product scope and constraints
DESIGN.md                  Existing theme and design conventions
```

## Local development

Install Node.js 22.12+ (or a newer supported version), npm, PHP 8.3+ and Composer 2. Enable the PHP extensions required by Composer and SQLite support for the local database. The Docker images use Node.js 22, PHP 8.4 and Python 3.11.

### 1. Prepare the backend

From the repository root:

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

In PowerShell, `Copy-Item .env.example .env` can be used instead of `cp`.

Set these values in `backend/.env`, retaining the generated `APP_KEY`:

```dotenv
APP_NAME="Creative Connects"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local-app
REVERB_APP_KEY=local-reverb-key
REVERB_APP_SECRET=replace-with-a-local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

The Reverb values above are development examples. Use distinct credentials for production. For MySQL development, configure `DB_CONNECTION=mysql` and the corresponding database host, port, name and credentials instead.

Run migrations and the setup seeders from `backend/`:

```sh
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=BusinessTypeSeeder
php artisan storage:link
```

The full `DatabaseSeeder` creates demonstration accounts and sample records; it is not the production account-provisioning procedure.

### 2. Prepare the frontend

From the repository root, install the locked dependencies:

```sh
npm ci
```

Create a root `.env` file with explicit local endpoints:

```dotenv
VITE_API_URL=http://localhost:8000
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
VITE_REVERB_APP_KEY=local-reverb-key
VITE_REVERB_PATH=
```

`VITE_API_URL` is the backend origin, without `/api/v1`; the API client adds endpoint paths. The Reverb public key must match the backend configuration. `VITE_*` values are embedded in the frontend build, so rebuild after changing them. Never put private API tokens or server secrets in frontend variables.

### 3. Start the services

Run each process in its own terminal:

| Working directory | Command | Purpose |
| --- | --- | --- |
| Repository root | `npm run dev -- --host localhost --port 5173` | Frontend at `http://localhost:5173` |
| `backend/` | `php artisan serve --host=localhost --port=8000` | Laravel API |
| `backend/` | `php artisan queue:work --tries=3` | Queued jobs |
| `backend/` | `php artisan schedule:work` | Scheduled campaigns, daily metrics and no-reply automation checks |
| `backend/` | `php artisan reverb:start --host=0.0.0.0 --port=8080` | Realtime transport |

On a fresh database without a superadmin, open `/setup` to create the initial platform administrator. Use `/login` for subsequent access. Superadmins manage companies and users through `/superadmin`; company users work in routes such as `/dashboard`, `/inbox` and `/campaigns` according to their permissions.

### Optional integrations

Configure integrations through the application's settings and the corresponding backend environment variables. WhatsApp and Instagram need the relevant Meta business setup, credentials and webhook configuration. AI and voice workflows require their provider configuration. Payments, storage and browser push notifications also depend on the selected service settings.

The voice sidecar is separate from the frontend and API. Its dependencies are in `voice-sidecar/requirements.txt`, and its environment template is `voice-sidecar/.env.example`. Voice/WebRTC deployment also uses coturn and matching shared secrets between Laravel and the sidecar. The Compose configuration uses host networking for these services and is intended for the Linux deployment host.

## Validation commands

From the repository root:

```sh
npm test -- --maxWorkers=1
npm run typecheck
npm run build
```

`npm run build` builds the embeddable chatbot widget and then the frontend application. `npm run start` serves the production frontend on port 3000 after a successful build.

From `backend/`:

```sh
composer test
```

Backend tests use an in-memory SQLite database through `backend/phpunit.xml`. Install development dependencies and the SQLite PHP extension before running them. Production backend images omit development test dependencies.

## AWS Lightsail deployment

Production runs on AWS Lightsail using the services in `docker-compose.yml`:

- `frontend`: serves the built application on port 3000.
- `backend`: serves Laravel on port 8080 and starts migrations, role/pipeline provisioning, a queue worker and the scheduler.
- `mysql`: stores application data in the persistent `mysql_data` volume.
- `reverb`: runs the WebSocket server on host port 8081.
- `voice-sidecar` and `coturn`: provide configured voice/WebRTC services.

The backend's persistent files use the `backend_storage` volume. Runtime Laravel settings are supplied through `backend.env`; Compose variables and frontend build arguments come from the root `.env`. These files and SSH keys are excluded from Git.

For a new deployment, configure database credentials, `APP_KEY`, application URLs, allowed frontend origin, Reverb credentials and internal publishing endpoints, and any required provider and voice settings before starting services. Within Compose, the database hostname is `mysql`; Reverb publishing can use the internal `reverb:8080` endpoint through `REVERB_SERVER_PUBLISH_HOST`, `REVERB_SERVER_PUBLISH_PORT` and `REVERB_SERVER_PUBLISH_SCHEME`. Keep the public browser-facing Reverb endpoint distinct from the internal publishing address.

The committed `nginx.conf` routes HTTPS traffic to the frontend, API, authentication, storage and `/reverb/` endpoints. The host needs the referenced TLS certificates. For the same-origin production setup, frontend build variables use the public HTTPS origin and Reverb uses port 443, scheme `https` and path `/reverb`.

### Automated releases

Pushing to `master` triggers [the deployment workflow](.github/workflows/deploy.yml). It connects over SSH using the GitHub Actions secrets `DEPLOY_HOST`, `DEPLOY_USER` and `DEPLOY_SSH_KEY`, then:

1. Updates `/home/ubuntu/whatsapp-crm` to `origin/master`.
2. Builds the backend, Reverb, frontend and voice-sidecar images.
3. Runs `docker compose up -d`.
4. Caches Laravel configuration and restarts queue workers.

The workflow resets the server checkout to the pushed revision. Keep server-specific configuration in the ignored environment files, rather than editing tracked files on the host. Back up the database and persistent storage before releases involving schema or data changes.

To inspect a running deployment from its repository directory on the host:

```sh
sudo docker compose ps
sudo docker compose logs --tail=100 backend reverb frontend
```

Successful container startup should be followed by application checks: sign-in, company access, inbox updates, campaign processing and any configured voice or commerce integrations.
