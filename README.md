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

Edit `.env` if you need to change the default credentials:

```env
APP_NAME="Mona Lisa Insurance"
APP_URL=http://localhost:8000

DB_DATABASE=mona_lisa_insurance
DB_USERNAME=sail
DB_PASSWORD=password
DB_ROOT_PASSWORD=rootpassword
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

### 4. Build frontend assets

```bash
make build-assets   # compiles React + Tailwind via Vite
```

> Run this once after first install, and again whenever you want a production build.
> During active development use `make dev` instead (see below).

### 5. Seed the database

```bash
make seed
```

This creates the default admin account (see [User Roles](#user-roles) below).

---

## User Roles

The application supports two roles: **admin** and **manager**.

| Role      | Description                              |
|-----------|------------------------------------------|
| `admin`   | Full access to all features              |
| `manager` | Standard access for day-to-day operations |

### Default Accounts

| Role    | Email                   | Password   |
|---------|-------------------------|------------|
| Admin   | `admin@monalisa.com`    | `MNL452$$` |

Run `make seed` to create the accounts. The seeder uses `firstOrCreate` — safe to run multiple times without creating duplicates.

> **Important:** Change default passwords after first login in a production environment.

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
│   │   │   └── CognitoController.php   # Dashboard + form details pages
│   │   ├── Middleware/
│   │   │   ├── HandleInertiaRequests.php
│   │   │   └── RoleMiddleware.php
│   │   └── Traits/
│   │       └── PaginatesArray.php      # Reusable search/sort/pagination for arrays
│   ├── Models/
│   │   └── User.php                    # isAdmin() / isManager() helpers
│   └── Services/
│       ├── CognitoFormsService.php     # Cognito Forms REST API client
│       ├── NowCertsService.php         # NowCerts REST API client
│       └── NowCertsFieldMapper.php     # Maps Cognito form fields → NowCerts fields
├── bootstrap/                          # Laravel bootstrap & middleware registration
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
│   │   ├── app.jsx                     # Inertia + React bootstrap
│   │   ├── Layouts/
│   │   │   └── AuthenticatedLayout.jsx # Sidebar + header layout
│   │   ├── Components/
│   │   │   ├── Pagination.jsx          # Reusable pagination component
│   │   │   ├── SchemaField.jsx         # Renders a single form schema field row
│   │   │   ├── SearchInput.jsx         # Reusable search input with icon
│   │   │   ├── SortableHeader.jsx      # Sortable table header with direction arrows
│   │   │   └── StatusBadge.jsx         # Active/Inactive status badge
│   │   ├── constants/
│   │   │   └── statusOptions.js        # STATUS_OPTIONS array (all/active/inactive)
│   │   └── Pages/
│   │       ├── Auth/
│   │       │   └── Login.jsx           # Login page (home)
│   │       ├── Cognito/
│   │       │   └── FormDetails.jsx     # Form details + schema with search & pagination
│   │       └── Dashboard.jsx           # Cognito Forms listing
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

Insurance management system. The service maps Cognito form submissions to NowCerts records.

| Variable              | Description                         |
|-----------------------|-------------------------------------|
| `NOWCERTS_USERNAME`   | NowCerts account username (email)   |
| `NOWCERTS_PASSWORD`   | NowCerts account password           |
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
```

#### Field Mapping

`NowCertsFieldMapper` translates Cognito form entry fields into NowCerts API payloads:

```php
$mapper = new NowCertsFieldMapper();

$insuredPayload = $mapper->mapInsured($cognitoEntry);
$policyPayload  = $mapper->mapPolicy($cognitoEntry);
$driverPayload  = $mapper->mapDriver($cognitoEntry);
$vehiclePayload = $mapper->mapVehicle($cognitoEntry);
```

Custom field mappings can be passed per form:

```php
$mapper = new NowCertsFieldMapper([
    'insured' => [
        'My_Custom_Field' => 'CommercialName',
        'Business_Email'  => 'EMail',
    ],
    'policy' => [
        'Pol_Number' => 'Number',
    ],
]);
```

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
