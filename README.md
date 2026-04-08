# Mona Lisa Insurance

A web application built with **Laravel 13**, **Inertia.js**, **React**, and **Tailwind CSS**, backed by **MySQL** and containerised with **Docker**.

---

## Tech Stack

| Layer      | Technology               |
|------------|--------------------------|
| Backend    | Laravel 13 (PHP 8.4)     |
| Frontend   | React 19 + Inertia.js v3 |
| Styling    | Tailwind CSS v4          |
| Build tool | Vite                     |
| Database   | MySQL 8.0                |
| Runtime    | Docker + Docker Compose  |

---

## Requirements

- [Docker](https://docs.docker.com/get-docker/) >= 24
- [Docker Compose](https://docs.docker.com/compose/) >= 2
- `make`

> For local development without Docker you also need PHP 8.4, Composer 2, and Node 20+.

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/ethelynmatias/mona-lisa-insurance.git
cd mona-lisa-insurance
```

### 2. Configure environment variables

```bash
cp .env.example .env
```

Edit `.env` with your credentials:

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
```

### 3. Build and start

```bash
make build    # build Docker images from scratch
make up       # start all containers in the background
```

On first boot the container automatically:
- Fixes storage directory permissions
- Regenerates the package manifest (`package:discover`)
- Runs all pending database migrations (`migrate --force`)

The app will be available at **http://localhost:8000**.

### 4. Install Node dependencies inside the container

```bash
make npm-install
```

> Required after cloning or whenever new npm packages are added (e.g. `ziggy-js`).

### 5. Build frontend assets

```bash
make build-assets   # compiles React + Tailwind via Vite
```

> Run this once after first install, and again whenever you want a production build.
> During active development use `make dev` instead (see below).

### 6. Seed the database

```bash
make seed
```

This creates the default admin account (see [User Roles](#user-roles) below).

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

### Start / stop

```bash
make up        # start containers in the background
make down      # stop and remove containers
make restart   # stop then start
make ps        # show running containers
```

### Frontend — hot module replacement

```bash
make dev       # start the Vite HMR dev server inside the container
```

Keep this running in a dedicated terminal while developing. Changes to React
components and CSS are reflected in the browser instantly without a page reload.

### Logs

```bash
make logs        # tail all containers
make logs-app    # tail the Laravel app only
make logs-db     # tail MySQL only
```

### Shell access

```bash
make shell     # bash shell inside the app container
make tinker    # Laravel Tinker REPL
make mysql     # MySQL CLI connected to the app database
```

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
│   │   │   │   └── CognitoWebhookController.php  # Receives Cognito Forms webhook events
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
│   │   ├── WebhookLog.php              # Incoming webhook event log
│   │   └── User.php                    # isAdmin() / isManager() / isActive() helpers
│   └── Services/
│       ├── CognitoFormsService.php     # Cognito Forms REST API client
│       ├── NowCertsService.php         # NowCerts REST API client + dynamic field schema
│       └── NowCertsFieldMapper.php     # DB + API driven field mapper (no hardcoded maps)
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
│   │   │   ├── SchemaField.jsx         # Schema field table row with NowCerts dropdown
│   │   │   ├── SearchInput.jsx         # Reusable search input with icon
│   │   │   ├── SortableHeader.jsx      # Sortable table header with direction arrows
│   │   │   ├── StatusBadge.jsx         # Active/Inactive status badge
│   │   │   └── WebhookHistoryPanel.jsx # Webhook event log table (used on Dashboard + FormDetails)
│   │   ├── constants/
│   │   │   ├── nowcerts.js             # NOWCERTS_ENTITY_COLORS
│   │   │   └── statusOptions.js        # STATUS_OPTIONS array (all/active/inactive)
│   │   └── Pages/
│   │       ├── Auth/
│   │       │   └── Login.jsx           # Login page (home)
│   │       ├── Cognito/
│   │       │   └── FormDetails.jsx     # Form details + schema with mapping dropdowns
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

| Location         | Options          | Default |
|------------------|------------------|---------|
| Dashboard        | 20 / 50 / 100    | 20      |
| Form Schema panel| 20 / 50 / 100    | 20      |

The selected value is preserved in the URL query string (`per_page`) so it survives page navigation and browser refreshes.

---

## Page Titles & Favicon

Every page sets a descriptive `<title>` in the format `{Page} — Mona Lisa Insurance` using Inertia's `<Head>` component.

The favicon is derived from the company logo (`/images/logo.png`) and is declared in `resources/views/app.blade.php` as 16×16, 32×32, and Apple-touch-icon variants.

---

## Third-Party Integrations

### Cognito Forms

Used to list forms and display their field schema on the dashboard.

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

Insurance management system. Cognito form field schemas can be mapped to NowCerts API fields directly from the Form Details page.

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
$nowcerts->getInsureds($params);
$nowcerts->findInsureds(['Name' => 'Smith', 'Email' => 'smith@example.com']);
$nowcerts->upsertInsured($data);
$nowcerts->upsertInsuredWithPolicies($data);

// Policies
$nowcerts->getPolicies($params);
$nowcerts->findPolicies(['Number' => 'POL-001']);
$nowcerts->upsertPolicy($data);
$nowcerts->patchPolicy($data);

// Drivers & Vehicles
$nowcerts->insertDriver($data);
$nowcerts->bulkInsertDrivers($drivers);
$nowcerts->insertVehicle($data);
$nowcerts->bulkInsertVehicles($vehicles);

// Claims, Notes, Tasks
$nowcerts->insertClaim($data);
$nowcerts->insertNote($data);
$nowcerts->upsertTask($data);

// Opportunities
$nowcerts->upsertOpportunity($data);

// Lookup data
$nowcerts->getAgents();
$nowcerts->getCarriers();
$nowcerts->getLinesOfBusiness();

// Dynamic field schema (cached 24h, derived from live API records)
$nowcerts->getAvailableFields();        // ['Insured' => [...], 'Policy' => [...], ...]
$nowcerts->clearAvailableFieldsCache(); // force refresh
```

#### Field Mapping UI

On the **Form Details** page, each Cognito form schema field has a dropdown to select the corresponding NowCerts entity and field. Click **Save Mappings** to persist to the database.

- Available NowCerts fields are fetched **live from the API** (cached 24 hours) — no hardcoded field lists
- If the API is unreachable or returns no records, an amber warning is shown and the dropdowns are disabled
- To force a field list refresh: `php artisan cache:forget nowcerts_available_fields`

Mappings are stored in the `form_field_mappings` table (`form_id`, `cognito_field`, `nowcerts_entity`, `nowcerts_field`).

#### Programmatic Field Mapping

`NowCertsFieldMapper` is fully DB + API driven — no hardcoded maps. It requires a `$formId` and the `NowCertsService` instance.

```php
$mapper = new NowCertsFieldMapper($formId, $nowcerts);

// Map a Cognito entry to NowCerts payloads using saved DB mappings
$insuredPayload = $mapper->mapInsured($cognitoEntry);
$policyPayload  = $mapper->mapPolicy($cognitoEntry);
$driverPayload  = $mapper->mapDriver($cognitoEntry);
$vehiclePayload = $mapper->mapVehicle($cognitoEntry);

// Get the lookup for the frontend (DB-saved mappings only)
$lookup = $mapper->getLookup();

// Auto-suggest mappings for fields not yet saved in DB
// Uses normalised name-matching against live NowCerts API fields
$suggestions = $mapper->getSuggestions($cognitoSchemaFields);
```

#### Validation

Field mapping saves are validated via `app/Http/Requests/SaveMappingsRequest.php`.

---

### Ziggy (Laravel Routes in JavaScript)

Ziggy exposes Laravel named routes to the frontend via the `route()` helper.

The route list is injected into every page via the `@routes` Blade directive and shared as an Inertia prop. No additional setup is needed — `route()` is available globally in all React components.

```jsx
// Example usage
router.post(route('forms.mappings.save', { formId }), payload);
```

---

## Webhook History

Incoming Cognito Forms webhook events are logged to the `webhook_logs` table and displayed in a **Webhook History** panel on both the Dashboard and the Form Details page.

### Receiving webhooks

The public endpoint accepts `POST` requests — no authentication or CSRF token required:

```
POST /webhook/cognito
```

Configure this URL in your Cognito Forms form settings under **"Post JSON Data to a Website"**. Cognito Forms allows one URL per trigger, so add all three separately:

| Trigger         | Webhook URL                                                                 |
|-----------------|-----------------------------------------------------------------------------|
| New Entry       | `https://YOUR_DOMAIN/webhook/cognito?form_id=YOUR_FORM_ID&event=entry.submitted` |
| Updated Entry   | `https://YOUR_DOMAIN/webhook/cognito?form_id=YOUR_FORM_ID&event=entry.updated`   |
| Deleted Entry   | `https://YOUR_DOMAIN/webhook/cognito?form_id=YOUR_FORM_ID&event=entry.deleted`   |

Replace `YOUR_DOMAIN` with your public URL (e.g. an ngrok tunnel like `https://xxxx.ngrok-free.dev` for local testing, or your production domain).

> **Local development with ngrok:**
> ```bash
> ngrok http 8000
> ```
> Run this on your host machine (not inside Docker). ngrok tunnels to `localhost:8000` which Docker already maps to the app container. Use the generated `https://xxxx.ngrok-free.dev` URL as your domain above.

Supported `event` values:

| Value              | Badge colour |
|--------------------|--------------|
| `entry.submitted`  | Blue         |
| `entry.updated`    | Amber        |
| `entry.deleted`    | Red          |

The controller (`app/Http/Controllers/Webhook/CognitoWebhookController.php`) also reads `FormId`, `FormName`, `EventType`, and `Id` directly from the JSON payload body if query params are not present.

### Webhook History panels

| Location       | Scope                              |
|----------------|------------------------------------|
| Dashboard      | All forms — most recent 50 events  |
| Form Details   | Current form — most recent 50 events |

The Dashboard panel includes a **Form** column showing the form name and ID. The Form Details panel omits it since it is already scoped to one form.

### Database table

| Column       | Type    | Description                        |
|--------------|---------|------------------------------------|
| `form_id`    | string  | Cognito form ID                    |
| `form_name`  | string  | Human-readable form name (optional)|
| `event_type` | string  | e.g. `entry.submitted`             |
| `entry_id`   | string  | Cognito entry ID (optional)        |
| `status`     | string  | Always `received` on ingest        |
| `payload`    | json    | Full raw request body              |

---

## Ports

| Service     | Host port |
|-------------|-----------|
| Laravel app | `8000`    |
| MySQL       | `3306`    |
| Vite HMR    | `5173`    |

---

## Stopping & Cleanup

```bash
make down       # stop containers, keep DB volume intact
make destroy    # stop containers AND delete the DB volume (all data lost)
```
