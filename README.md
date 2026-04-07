# Mona Lisa Insurance

A web application built with **Laravel 13**, **Inertia.js**, **React**, and **Tailwind CSS**, backed by **MySQL** and containerised with **Docker**.

---

## Tech Stack

| Layer       | Technology                      |
|-------------|---------------------------------|
| Backend     | Laravel 13 (PHP 8.4)            |
| Frontend    | React 19 + Inertia.js v3        |
| Styling     | Tailwind CSS v4                 |
| Build tool  | Vite                            |
| Database    | MySQL 8.0                       |
| Runtime     | Docker + Docker Compose         |

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
git clone <repository-url> mona-lisa-insurance
cd mona-lisa-insurance
```

### 2. Run the one-command setup

```bash
make install
```

This single command will:

1. Copy `.env.example` → `.env` (if `.env` does not exist)
2. Build Docker images
3. Start all containers
4. Generate the application key
5. Run all database migrations and seeders

The app will be available at **http://localhost:8000**.

### 3. (Optional) Customise environment variables

Edit `.env` before running `make install` if you need different credentials:

```env
DB_DATABASE=mona_lisa_insurance
DB_USERNAME=sail
DB_PASSWORD=password
DB_ROOT_PASSWORD=rootpassword
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
make dev       # starts the Vite HMR dev server inside the container
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
make shell     # bash inside the app container
make tinker    # Laravel Tinker REPL
make mysql     # MySQL CLI connected to the app database
```

---

## Database

```bash
make migrate               # run pending migrations
make rollback              # roll back the last batch
make migrate-fresh         # drop all tables and re-run migrations
make migrate-fresh-seed    # drop → migrate → seed (resets dev data)
make seed                  # run seeders only
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
make make-model      NAME=Policy          # model + migration
make make-controller NAME=PolicyController
make make-migration  NAME=create_policies_table
make make-seeder     NAME=PolicySeeder
```

---

## Production Build

Build optimised frontend assets and cache Laravel config/routes/views:

```bash
make build-assets   # npm run build inside the container
make cache          # php artisan optimize
```

To rebuild Docker images from scratch (e.g. after a Dockerfile change):

```bash
make build
```

---

## All Available Commands

Run `make help` at any time to see the full list:

```
make help
```

---

## Project Structure

```
mona-lisa-insurance/
├── app/                    # PHP application (controllers, models, etc.)
├── bootstrap/              # Laravel bootstrap & middleware registration
├── config/                 # Laravel configuration files
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/            # Database seeders
├── resources/
│   ├── css/app.css         # Tailwind CSS entry point
│   ├── js/
│   │   ├── app.jsx         # Inertia + React bootstrap
│   │   └── Pages/          # React page components (one per route)
│   └── views/app.blade.php # Single Blade template (Inertia root)
├── routes/
│   └── web.php             # Web routes (return Inertia::render(...))
├── tests/                  # PHPUnit feature & unit tests
├── Dockerfile
├── docker-compose.yml
├── Makefile
└── vite.config.js
```

---

## Ports

| Service | Host port |
|---------|-----------|
| Laravel app | `8000` |
| MySQL | `3306` |
| Vite HMR | `5173` |

---

## Stopping & Cleanup

```bash
make down       # stop containers, keep DB volume
make destroy    # stop containers AND delete DB volume (all data lost)
```
