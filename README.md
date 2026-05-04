# Mona Lisa Insurance

A web application built with **Laravel 11**, **Inertia.js**, **React**, and **Tailwind CSS**, backed by **MySQL** and containerised with **Docker**.

---

## Tech Stack

| Layer      | Technology               |
|------------|--------------------------|
| Backend    | Laravel 11 (PHP 8.3)     |
| Frontend   | React 19 + Inertia.js v3 |
| Styling    | Tailwind CSS v4          |
| Build tool | Vite                     |
| Database   | MySQL 8.0                |
| Runtime    | Docker + Docker Compose  |

---

## Requirements

- [Docker](https://docs.docker.com/get-docker/) >= 24
- [Docker Compose](https://docs.docker.com/compose/) >= 2
- [Node.js](https://nodejs.org/) >= 20 (for local frontend development)
- `make`

> **Note:** Node.js is required locally for frontend development. The Docker container only runs PHP/Laravel backend.

---

## Installation

### Quick Start

For first-time setup, run the automated installer:

```bash
git clone https://github.com/ethelynmatias/mona-lisa-insurance-app.git
cd mona-lisa-insurance-app
make install
```

This will:
1. Copy `.env.example` to `.env` if needed
2. Install Node.js dependencies locally
3. Build Docker containers
4. Start all services
5. Generate Laravel application key
6. Run database migrations and seeders

The app will be available at **http://localhost:8000**.

### Manual Installation

If you prefer step-by-step setup:

#### 1. Clone and configure

```bash
git clone https://github.com/ethelynmatias/mona-lisa-insurance-app.git
cd mona-lisa-insurance-app
cp .env.example .env
```

#### 2. Edit environment variables

Update `.env` with your credentials:

```env
APP_NAME="Mona Lisa Insurance"
APP_URL=http://localhost:8000

DB_DATABASE=mona_lisa_insurance
DB_USERNAME=sail
DB_PASSWORD=password
DB_ROOT_PASSWORD=rootpassword

# Cognito Forms
COGNITO_API_KEY=your_cognito_api_key

# NowCerts
NOWCERTS_USERNAME=your_nowcerts_email
NOWCERTS_PASSWORD=your_nowcerts_password

# Docker Hub (for builds)
DOCKERHUB_USERNAME=your_dockerhub_username
```

#### 3. Install dependencies and build

```bash
make npm-install    # Install Node.js dependencies locally
make build          # Build Docker images from scratch
make up             # Start all containers
```

#### 4. Setup Laravel application

```bash
make migrate        # Run database migrations
make seed           # Create default admin account
```

### After Installation

- **Laravel backend:** http://localhost:8000
- **Frontend development:** Run `make dev` to start Vite dev server
- **Default admin:** `admin@monalisa.com` / `MNL452$$`

---

## User Roles

The application supports two roles: **admin** and **manager**.

| Role      | Description                               |
|-----------|-------------------------------------------|
| `admin`   | Full access including user management     |
| `manager` | Standard access for day-to-day operations |

Both roles require the account to be **active** (`is_active = true`) to log in.

### Default Accounts

| Role  | Email                 | Password   |
|-------|-----------------------|------------|
| Admin | `admin@monalisa.com`  | `MNL452$$` |

Run `make seed` to create the accounts. The seeder uses `firstOrCreate` — safe to run multiple times without creating duplicates.

> **Important:** Change default passwords after first login in a production environment.

### Changing a user's role via Tinker

```bash
make tinker
```

```php
User::where('email', 'admin@monalisa.com')->update(['role' => 'admin']);
```

### Protecting Routes by Role

```php
// Admin only
Route::middleware(['auth', 'role:admin'])->group(function () { ... });

// Admin or manager
Route::middleware(['auth', 'role:admin,manager'])->group(function () { ... });
```

### Checking Role in PHP

```php
$user->isAdmin();    // true if role === 'admin'
$user->isManager();  // true if role === 'manager'
```

### Checking Role in React

The authenticated user's role is available on every page via Inertia shared props:

```jsx
import { usePage } from '@inertiajs/react';

const { auth } = usePage().props;

if (auth.user.role === 'admin') {
    // show admin-only UI
}
```

---

## Settings

The **Settings** page (`/settings`) is accessible from the sidebar and the user dropdown menu. The **Profile** and **Change Password** panels are displayed side-by-side on wider screens and stack vertically on mobile.

### Profile & Password

Every logged-in user can:
- Update their **name** and **email address** (duplicate emails are blocked)
- Change their **password** (requires current password, enforces strong password rules)

Password fields on both the Login page and the Settings page include an **eye icon** to toggle password visibility.

### User Management (Admin only)

Admins can:
- **Create** new users with a name, email, password, and role
- **View** all users in a table with role badges, status badges, and creation dates
- **Activate / Deactivate** any user except their own account
- **Delete** any user except their own account

#### User Status (Active / Inactive)

Users have an `is_active` flag (default `true`). Inactive users are blocked at login with the message:

> _Your account has been deactivated. Please contact an administrator._

Admins can toggle a user's status from the **All Users** table on the Settings page (Activate / Deactivate buttons). Admins cannot deactivate their own account.

---

## Daily Development

### Starting Development

**Recommended workflow for daily development:**

```bash
# Start backend services
make up

# In a separate terminal, start frontend development server
make dev
```

This gives you:
- **Laravel backend** at http://localhost:8000
- **Vite dev server** with hot module replacement (HMR)
- **Live reloading** for React components and Tailwind CSS

> **Pro tip:** Use `make dev-start` to start both backend and frontend in one command.

### Quick Commands

```bash
# Development lifecycle
make dev-start     # Start both backend and frontend for development
make dev-stop      # Stop all development services
make up            # Start containers in the background
make down          # Stop and remove containers
make restart       # Stop then start
make ps            # Show running containers

# Frontend development
make dev           # Start Vite HMR dev server locally
make build-assets  # Build production assets
make npm-install   # Install/update Node dependencies

# Code quality
make test          # Run PHPUnit test suite
make lint          # Fix code style with Laravel Pint
make lint-dry      # Check style without writing changes
```

### Common Development Tasks

#### Working with dependencies
```bash
# Add new PHP package
make shell
composer require package/name

# Add new Node package
npm install package-name
```

#### Database operations
```bash
make migrate               # Run pending migrations
make migrate-fresh-seed    # Reset database with fresh data
make seed                  # Run seeders only
```

#### Debugging and logs
```bash
make logs        # Tail all containers
make logs-app    # Tail the Laravel app only
make logs-db     # Tail MySQL only
make shell       # Bash shell inside the app container
make tinker      # Laravel Tinker REPL
make mysql       # MySQL CLI connected to the database
```

### Development Tips

1. **Keep `make dev` running** in a dedicated terminal for instant feedback
2. **Use Laravel Pint** (`make lint`) to maintain consistent code style
3. **Run tests** (`make test`) before committing changes
4. **Check logs** (`make logs-app`) if you encounter issues
5. **Use Tinker** (`make tinker`) for quick database queries and testing

---

## Database

```bash
make migrate               # run pending migrations
make rollback              # roll back the last migration batch
make migrate-fresh         # drop all tables and re-run migrations
make migrate-fresh-seed    # drop → migrate → seed (resets dev data)
make seed                  # run seeders only
```

> Migrations also run automatically every time the container starts via `php artisan migrate --force`.

To reset all data and re-seed from scratch:

```bash
make migrate-fresh-seed
```

---

## Environment Encryption

Laravel's built-in `.env` encryption keeps secrets out of plain-text files.

```bash
# Encrypt .env → generates .env.encrypted + prints the key
php artisan env:encrypt

# Decrypt on another machine (store the key as a secret in CI/CD)
php artisan env:decrypt --key=base64:...

# Force overwrite an existing .env
php artisan env:decrypt --key=base64:... --force
```

Commit `.env.encrypted` to the repository. Keep `.env` in `.gitignore` (default).

---

## Cache & Optimisation

```bash
make optimize      # php artisan optimize  — cache config, routes, views
make cache-clear   # php artisan optimize:clear — clear all caches
```

---

## Testing & Code Quality

```bash
make test                          # run the full PHPUnit test suite
make test-filter FILTER=LoginTest  # run tests matching a name
make lint                          # fix code style with Laravel Pint
make lint-dry                      # check style without writing changes
```

---

## Generating Code

```bash
make make-model      NAME=Policy              # model + migration
make make-controller NAME=PolicyController    # controller
make make-migration  NAME=create_policies_table
make make-seeder     NAME=PolicySeeder
```

---

## Rebuilding Docker Images

Required after changes to `Dockerfile`, `docker-compose.yml`, or PHP/Node dependencies:

```bash
make down
make build    # rebuild images from scratch (no cache)
make up
make npm-install
make build-assets
```

---

## All Available Commands

```bash
make help
```

---

## Project Structure

```
mona-lisa-insurance/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthenticatedSessionController.php
│   │   │   ├── Webhook/
│   │   │   │   └── CognitoWebhookController.php  # Receives webhooks, syncs to NowCerts, manages history
│   │   │   ├── CognitoController.php   # Dashboard + form details pages
│   │   │   └── SettingsController.php  # Profile, password, user management
│   │   ├── Middleware/
│   │   │   ├── HandleInertiaRequests.php  # Shares auth, flash, ziggy props
│   │   │   └── RoleMiddleware.php
│   │   ├── Requests/
│   │   │   └── SaveMappingsRequest.php    # Validates field mapping save payload
│   │   └── Traits/
│   │       └── PaginatesArray.php      # Reusable search/sort/pagination for arrays
│   ├── Models/
│   │   ├── FormFieldMapping.php        # Persisted Cognito → NowCerts field mappings
│   │   ├── WebhookDiscoveredField.php  # Discovered field keys per form (persisted independently)
│   │   ├── WebhookLog.php              # Incoming webhook event log
│   │   └── User.php                    # isAdmin() / isManager() / isActive() helpers
│   ├── Repositories/
│   │   ├── Contracts/
│   │   │   ├── FormFieldMappingRepositoryInterface.php
│   │   │   └── WebhookLogRepositoryInterface.php
│   │   ├── FormFieldMappingRepository.php
│   │   └── WebhookLogRepository.php
│   ├── Enums/
│   │   └── NowCertsEntity.php          # NowCerts entity enum (Insured, Policy, Driver, Vehicle, Property, Contact)
│   └── Services/
│       ├── CognitoFormsService.php     # Cognito Forms REST API client
│       ├── NowCertsService.php         # NowCerts REST API client + file upload + Contact entity support
│       └── NowCertsFieldMapper.php     # DB-driven field mapper with Contact mapping + flatten/extract helpers
├── bootstrap/
│   └── providers.php                   # Registers ZiggyServiceProvider
├── config/
│   ├── cognito.php                     # Cognito Forms API config
│   └── nowcerts.php                    # NowCerts API config
├── database/
│   ├── migrations/                     # Database migrations
│   └── seeders/
│       ├── AdminSeeder.php             # Seeds default admin account
│       └── DatabaseSeeder.php
├── resources/
│   ├── css/app.css                     # Tailwind CSS entry point
│   ├── js/
│   │   ├── app.jsx                     # Inertia + React bootstrap + Ziggy route()
│   │   ├── Layouts/
│   │   │   └── AuthenticatedLayout.jsx # Sidebar + header layout
│   │   ├── Components/
│   │   │   ├── Pagination.jsx          # Reusable pagination component
│   │   │   ├── SchemaField.jsx         # Schema field row with unified searchable NowCerts dropdown
│   │   │   ├── SearchInput.jsx         # Reusable search input with icon
│   │   │   ├── SortableHeader.jsx      # Sortable table header with direction arrows
│   │   │   ├── StatusBadge.jsx         # Active/Inactive status badge
│   │   │   └── WebhookHistoryPanel.jsx # Webhook event log table
│   │   ├── constants/
│   │   │   ├── nowcerts.js             # NOWCERTS_ENTITY_COLORS
│   │   │   └── statusOptions.js        # STATUS_OPTIONS array
│   │   └── Pages/
│   │       ├── Auth/
│   │       │   └── Login.jsx
│   │       ├── Cognito/
│   │       │   └── FormDetails.jsx     # Form details + field mapping + webhook instructions
│   │       ├── Dashboard.jsx           # Cognito Forms listing
│   │       └── Settings.jsx            # Profile, password, user management
│   └── views/app.blade.php             # Single Blade template (Inertia root)
├── routes/
│   └── web.php                         # Web routes
├── tests/                              # PHPUnit feature & unit tests
├── Dockerfile
├── docker-compose.yml
├── Makefile
└── vite.config.js
```

---

## Per-Page Filter

The **Dashboard** listing and the **Form Schema** panel on the Form Details page both support a configurable number of records per page.

| Location          | Options       | Default |
|-------------------|---------------|---------|
| Dashboard         | 20 / 50 / 100 | 20      |
| Form Schema panel | 20 / 50 / 100 | 20      |

The selected value is preserved in the URL query string (`per_page`) so it survives page navigation and browser refreshes.

---

## Page Titles & Favicon

Every page sets a descriptive `<title>` in the format `{Page} — Mona Lisa Insurance` using Inertia's `<Head>` component.

The favicon is derived from the company logo (`/images/logo.png`) and is declared in `resources/views/app.blade.php` as 16×16, 32×32, and Apple-touch-icon variants.

---

## Third-Party Integrations

### Cognito Forms

Used to list forms and trigger webhook-based syncs to NowCerts.

| Variable            | Description                                          |
|---------------------|------------------------------------------------------|
| `COGNITO_API_KEY`   | Bearer token from Organization Settings → API Keys  |
| `COGNITO_BASE_URL`  | Default: `https://www.cognitoforms.com/api/`         |

**Service:** `app/Services/CognitoFormsService.php`

Key methods:

```php
$cognito->getForms();                        // list all forms
$cognito->getFormFields($formId);            // get field schema for a form
$cognito->getEntries($formId, $params);      // list entries
$cognito->createEntry($formId, $data);       // create entry
$cognito->updateEntry($formId, $id, $data);  // update entry
$cognito->deleteEntry($formId, $id);         // delete entry
```

---

### NowCerts

Insurance management system. Cognito form submissions are automatically synced to NowCerts via webhook using the field mappings configured on each form's details page.

| Variable              | Description                              |
|-----------------------|------------------------------------------|
| `NOWCERTS_USERNAME`   | NowCerts account username (email)        |
| `NOWCERTS_PASSWORD`   | NowCerts account password                |
| `NOWCERTS_BASE_URL`   | Default: `https://api.nowcerts.com/api/` |

**Service:** `app/Services/NowCertsService.php`
**Field Mapper:** `app/Services/NowCertsFieldMapper.php`

Authentication is handled automatically — the service obtains a Bearer token via OAuth2 password grant and caches it for ~1 hour, refreshing on expiry.

Key methods:

```php
// Insureds
$nowcerts->syncInsured($data);               // find-or-create/update insured
$nowcerts->findInsureds(['Email' => '...']);

// Policies
$nowcerts->upsertPolicy($data);              // find-or-create/update policy by Number
$nowcerts->findPolicies(['policyNumber' => 'POL-001']);

// Property
$nowcerts->insertOrUpdateProperty($data);    // create or update a property record

// File uploads
$nowcerts->uploadDocument($insuredId, $fileUrl, $fileName, $contentType, $label);
// Uploads to: PUT Insured/UploadInsuredFile (visible in insured Files tab)

// Available NowCerts fields for mapping UI
$nowcerts->getAvailableFields();             // ['Insured' => [...], 'Policy' => [...], ...]
$nowcerts->getPropertyFields();
$nowcerts->getPropertyAdditionalFields();
```

#### Field Mapping UI

On the **Form Details** page each discovered Cognito field has a unified searchable dropdown:

| Column                    | Entities available                                    |
|---------------------------|-------------------------------------------------------|
| Set NowCerts Fields       | Insured, Policy, Driver, Vehicle, Property, Contact  |

The dropdown includes search functionality to quickly find specific fields across all NowCerts entities. Vehicle and driver information is automatically extracted on the backend, so manual mapping is only needed to override auto-detection.

Mappings are stored in the `form_field_mappings` table (`form_id`, `cognito_field`, `nowcerts_entity`, `nowcerts_field`).

#### Webhook-Discovered Fields

Field keys discovered from real webhook payloads are persisted to the `webhook_discovered_fields` table (one row per `form_id`, JSON `fields` column). This table is never cleared when webhook history is deleted, so saved field mappings remain intact.

Dot-notation keys (e.g. `NameOfInsured.First`) are grouped into collapsible sections in the mapping UI. Only new keys trigger a DB write — repeat webhooks with unchanged fields are a no-op.

---

### Ziggy (Laravel Routes in JavaScript)

Ziggy exposes Laravel named routes to the frontend via the `route()` helper.

The route list is injected into every page via the `@routes` Blade directive and shared as an Inertia prop. No additional setup is needed — `route()` is available globally in all React components.

```jsx
router.post(route('forms.mappings.save', { formId }), payload);
```

---

## Webhook Integration

### Connecting Cognito Forms

The **Form Details** page shows step-by-step instructions with pre-filled, copyable URLs. The general setup is:

1. Open your form in Cognito Forms and click the **Build** tab
2. Scroll down to **Post JSON Data** and enable it
3. Add the **Submit** endpoint:
   ```
   POST https://YOUR_DOMAIN/webhook/cognito?form_id=FORM_ID&event=entry.submitted
   ```
4. Add the **Update** endpoint:
   ```
   POST https://YOUR_DOMAIN/webhook/cognito?form_id=FORM_ID&event=entry.updated
   ```
5. Save and submit a test entry — it appears in **Webhook History** and syncs to NowCerts automatically

> **Local development with ngrok:**
> ```bash
> ngrok http 8000
> ```
> Run on your host machine (not inside Docker). Use the generated `https://xxxx.ngrok-free.dev` as `YOUR_DOMAIN`.

### Receiving webhooks

The public endpoint accepts `POST` requests — no authentication or CSRF token required:

```
POST /webhook/cognito?form_id={formId}&event={eventType}
```

Supported `event` values:

| Value              | Action                              |
|--------------------|-------------------------------------|
| `entry.submitted`  | Full sync to NowCerts + file upload |
| `entry.updated`    | Full sync to NowCerts (no duplicate file uploads) |
| `entry.deleted`    | Logged as skipped — no NowCerts action |

### NowCerts sync flow

1. Payload logged with `sync_status = pending`
2. Discovered field keys saved to `webhook_discovered_fields` (new keys only)
3. `entry.submitted` / `entry.updated`:
   - `NowCertsFieldMapper` loads DB-saved mappings for the form
   - Flattens Cognito payload to dot-notation keys
   - **Insured** synced first — `findExistingInsured()` looks up by email then name; passes `DatabaseId` in body to update if found
   - **Policy** synced — looks up by `Number` to inject `policyDatabaseId` for update
   - **Contact** synced for Form 13 — mapped Contact entity fields sent to InsertPrincipal API
   - **Drivers** & **Vehicles** — auto-extracted from numbered fields (Name2+, Vehicle2+) plus UI-mapped Driver/Vehicle entity fields
   - **Property** synced after Insured — `InsuredDatabaseId` injected from Insured response
   - **File uploads** — Cognito file attachments uploaded to `Insured/UploadInsuredFile` (visible in NowCerts Files tab); Cognito file IDs tracked in `uploaded_file_ids` to prevent re-uploading unchanged files on updates
   - Each entity is attempted independently — one failure does not block others
4. Log updated with `sync_status`, `synced_entities`, `sync_error`, `synced_at`

### Sync status values

| Status    | Meaning                                               | Badge   |
|-----------|-------------------------------------------------------|---------|
| `pending` | Received but not yet processed                        | Yellow  |
| `synced`  | All mapped entities pushed to NowCerts successfully   | Green   |
| `failed`  | One or more NowCerts API calls returned an error      | Red     |
| `skipped` | No mappings configured, or delete event               | Gray    |

### Rerunning a failed sync

Each row in the Webhook History panel has a **Rerun** button that replays the sync using the stored payload — useful after fixing a misconfigured field mapping.

### Webhook History panels

| Location     | Scope                           | Per-page options |
|--------------|---------------------------------|-----------------|
| Dashboard    | All forms — up to 500 events    | 20 / 50 / 100   |
| Form Details | Current form — up to 500 events | 20 / 50 / 100   |

Both panels have **Clear History** (deletes logs but not discovered fields) and a **View** button per row to inspect the raw JSON payload.

### Database tables

#### `webhook_logs`

| Column              | Type      | Description                                         |
|---------------------|-----------|-----------------------------------------------------|
| `form_id`           | string    | Cognito form ID                                     |
| `form_name`         | string    | Human-readable form name                            |
| `event_type`        | string    | `entry.submitted`, `entry.updated`, `entry.deleted` |
| `entry_id`          | string    | Cognito entry ID                                    |
| `status`            | string    | Always `received` on ingest                         |
| `payload`           | json      | Full raw request body                               |
| `sync_status`       | enum      | `pending`, `synced`, `failed`, `skipped`            |
| `sync_error`        | text      | Error message(s) if sync failed                     |
| `synced_entities`   | json      | e.g. `["Insured","Policy","Property"]`              |
| `uploaded_file_ids` | json      | Cognito file IDs already uploaded (dedup)           |
| `synced_at`         | timestamp | When the NowCerts sync completed                    |

#### `webhook_discovered_fields`

| Column    | Type   | Description                                  |
|-----------|--------|----------------------------------------------|
| `form_id` | string | Cognito form ID (unique)                     |
| `fields`  | json   | Array of discovered flattened field key names |

#### `form_field_mappings`

| Column           | Type   | Description                                                    |
|------------------|--------|----------------------------------------------------------------|
| `form_id`        | string | Cognito form ID                                                |
| `cognito_field`  | string | Flattened field key (e.g. `NameOfInsured.First`); property mappings use `__property` suffix |
| `nowcerts_entity`| string | e.g. `Insured`, `Policy`, `Property`, `Additional`             |
| `nowcerts_field` | string | e.g. `FirstName`, `AddressLine1`, `YearBuilt`                  |

---

## Ports

| Service     | Host port |
|-------------|-----------|
| Laravel app | `8000`    |
| MySQL       | `3306`    |
| Vite HMR    | `5173`    |

---

## Production Deployment (Digital Ocean)

### First Time Server Setup

**1. Clone the repo:**
```bash
cd /var/www
git clone https://github.com/ethelynmatias/mona-lisa-insurance-app.git mona-lisa-insurance-app
cd mona-lisa-insurance-app
```

**2. Create `.env` file:**
```bash
nano .env
```

Fill in all values including:
```env
APP_KEY=                        # generate after first docker compose up
DOCKERHUB_USERNAME=ethelyn1hubstart
```

**3. Login to Docker Hub:**
```bash
docker login -u ethelyn1hubstart
# Use your Personal Access Token as the password
```

**4. Pull image and start containers:**
```bash
docker compose pull
docker compose up -d
```

**5. Generate APP_KEY:**
```bash
docker compose exec app php artisan key:generate
```

**6. Run migrations and seed:**
```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed
```

### Auto Deploy (GitHub Actions)

Every push to `main` automatically:
1. Builds frontend assets (`npm run build`)
2. Builds and pushes Docker image to Docker Hub
3. SSHs into server → `git pull` → `docker compose pull` → `docker compose up -d`
4. Runs migrations, clears caches, and runs `php artisan optimize`

### Required GitHub Secrets

| Secret | Description |
|--------|-------------|
| `DOCKERHUB_USERNAME` | Docker Hub username |
| `DOCKERHUB_TOKEN` | Docker Hub Personal Access Token |
| `SSH_HOST` | Server IP address |
| `SSH_USER` | SSH username |
| `SSH_KEY` | SSH private key |

### Manual Deploy

```bash
cd /var/www/mona-lisa-insurance-app
git pull origin main
docker compose pull
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

### Useful Server Commands

```bash
# View logs
docker logs mona-lisa-app --tail 50

# Enter container
docker exec -it mona-lisa-app bash

# Check running containers
docker ps

# Stop containers (keeps DB data)
docker compose down

# View database backups
ls -lh /var/www/mona-lisa-insurance-app/backup_*.sql
```

---

## Stopping & Cleanup

```bash
make down       # stop containers, keep DB volume intact
make destroy    # stop containers AND delete the DB volume (all data lost)
```
