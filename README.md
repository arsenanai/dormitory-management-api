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

## Getting Started

### Prerequisites

- PHP 8.1+
- Composer
- MySQL or PostgreSQL
- Node.js & npm (for frontend, if needed)
- [Docker](https://www.docker.com/) (optional, for containerized setup)

---

### Installation

1. **Clone the repository:**
   ```sh
   git clone <your-repo-url>
   cd dormitory-management-api
   ```

2. **Install dependencies:**
   ```sh
   composer install
   ```

3. **Copy and configure your environment:**
   ```sh
   cp .env.example .env
   # Edit .env to set your DB and other settings
   ```

4. **Generate application key:**
   ```sh
   php artisan key:generate
   ```

5. **Run migrations and seeders:**
   ```sh
   php artisan migrate --seed
   ```

6. **(Optional) Link storage for file uploads:**
   ```sh
   php artisan storage:link
   ```

---

### Running the API Locally

Start the Laravel development server:

```sh
php artisan serve
```

The API will be available at [http://localhost:8000](http://localhost:8000).

---

### Running with Docker

You can use Docker Compose profiles for different environments:

```sh
docker-compose --profile dev up --build
docker-compose --profile prod up --build
docker-compose --profile test up -d --build
```

---

### Running Tests

```sh
php artisan test
```
or
```sh
vendor/bin/phpunit
```

---

## API Endpoints

- `/api/login` — User