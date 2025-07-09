# Dormitory Management API

This is a Laravel-based API for managing dormitories, users, rooms, room types, beds, and related entities. It supports role-based access (admin, sudo), file uploads, and CRUD operations for all main resources.

---

## Features

- User authentication (with Laravel Sanctum)
- Role-based access control (admin, sudo, etc.)
- CRUD for Dormitories, Users, Room Types, Rooms, Beds, Payments, Guests, etc.
- File upload support (e.g., minimap images for room types)
- JSON API responses
- Fully tested with PHPUnit

---

### Running with Docker

You can use Docker Compose for different environments and tasks:

**Install Composer dependencies:**
```sh
docker compose run --rm composer
```

**Run migrations:**
```sh
docker compose run --rm migrate
```

**Run seeders:**
```sh
docker compose run --rm seed
```

**Generate application key (run once if .env does not have APP_KEY):**
```sh
docker compose run --rm app php artisan key:generate
```

**Run tests (SQLite in-memory):**
```sh
docker compose run --rm test
```

**Start the development server:**
```sh
docker compose up dev db redis
```

**Start the production server (with Nginx, PHP-FPM, and SSL via nginx-proxy):**
```sh
docker compose up -d app nginx-proxy letsencrypt db redis
```

---

### Running Tests

```sh
docker compose run --rm test
```
or
```sh
vendor/bin/phpunit
```

---

## API Endpoints

- `/api/login` — User