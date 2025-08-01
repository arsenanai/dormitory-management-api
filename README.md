# SDU Dormitory Management API

This is a Laravel-based REST API for managing dormitories, users, rooms, room types, beds, payments, and related entities. It supports role-based access control, comprehensive business rules, file uploads, and CRUD operations for all main resources.

## 🚀 Features

### Core Management
- **Authentication & Authorization**: Laravel Sanctum with role-based access control
- **User Management**: Admin, student, and guest profiles with role-specific data
- **Dormitory Management**: Complete dormitory lifecycle management
- **Room & Bed Management**: Room types, allocation, and staff reservations
- **Payment System**: Semester payment tracking and financial management
- **Messaging System**: Internal communication platform
- **Configuration Management**: System settings and integrations

### Technical Features
- **RESTful API**: JSON-based API with proper HTTP status codes
- **Validation**: Comprehensive request validation with custom rules
- **File Uploads**: Secure file handling for documents and images
- **Database**: MySQL for production, SQLite for testing
- **Testing**: Comprehensive PHPUnit test coverage
- **Documentation**: PHPDoc comments and API documentation

## 📁 Project Structure

```
app/
├── Http/
│   ├── Controllers/     # API controllers with business logic
│   └── Middleware/      # Custom middleware (RoleMiddleware)
├── Models/              # Eloquent models with relationships
├── Services/            # Business logic services
├── Mail/                # Email templates and classes
└── Providers/           # Service providers
database/
├── migrations/          # Database schema migrations
├── seeders/             # Database seeders for test data
└── factories/           # Model factories for testing
tests/
├── Feature/             # Feature tests for API endpoints
└── Unit/                # Unit tests for services and models
```

## 🛠️ Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- MySQL (development) / SQLite (testing)
- Docker (optional)

### Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env file
# Run migrations
php artisan migrate

# Seed database with test data
php artisan db:seed
```

### Development Server

```bash
# Start development server
php artisan serve
```

The API will be available at [http://localhost:8000](http://localhost:8000).

## 🧪 Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run tests with coverage
php artisan test --coverage

# Run tests in parallel
php artisan test --parallel
```

### Test Coverage

- **Feature Tests**: API endpoint testing with authentication
- **Unit Tests**: Service and model testing
- **Database Tests**: Migration and seeder testing
- **Integration Tests**: Full workflow testing

## 🐳 Docker Installation & Usage

### Prerequisites

- [Docker](https://www.docker.com/get-started) and [Docker Compose](https://docs.docker.com/compose/install/) installed
- Git for cloning the repository

### Development Environment Setup

#### 1. Clone and Setup Project

```bash
# Clone the repository
git clone <repository-url>
cd crm-back

# Copy environment file
cp .env.example .env
```

#### 2. Configure Environment

Edit the `.env` file with your database settings:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=dormitory_management
DB_USERNAME=root
DB_PASSWORD=password
CACHE_STORE=file
```

#### 3. Start Development Environment

```bash
# Start all development services
docker compose --profile dev up -d

# Generate application key
docker compose exec dev php artisan key:generate

# Run migrations
docker compose run --rm migrate

# Seed database with test data
docker compose run --rm seed

# Verify setup
docker compose exec dev php artisan migrate:status
```

#### 4. Access Development Environment

- **API**: http://localhost:8000
- **MailHog (Email Testing)**: http://localhost:8025
- **Database**: localhost:3306
- **Redis**: localhost:6379

#### 5. Development Commands

```bash
# View logs
docker compose logs dev

# Run tests
docker compose run --rm test

# Access container shell
docker compose exec dev bash

# Clear cache
docker compose exec dev php artisan config:clear

# Restart services
docker compose restart dev
```

### Production Environment Setup

#### 1. Clone and Setup Project

```bash
# Clone the repository
git clone <repository-url>
cd crm-back

# Copy environment file
cp .env.example .env
```

#### 2. Configure Production Environment

Edit the `.env` file for production:

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=dormitory_management
DB_USERNAME=root
DB_PASSWORD=your_secure_password
CACHE_STORE=file
```

#### 3. Start Production Environment

```bash
# Start production services
docker compose --profile production up -d

# Generate application key
docker compose exec app php artisan key:generate

# Run migrations
docker compose run --rm migrate

# Seed database (if needed)
docker compose run --rm seed

# Set proper permissions
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

#### 4. Production Access

- **Web Application**: http://your-domain.com
- **API**: http://your-domain.com/api
- **Database**: Internal network only
- **Redis**: Internal network only

#### 5. Production Maintenance

```bash
# View logs
docker compose logs app

# Update application
git pull
docker compose --profile production up -d --build

# Backup database
docker compose exec db mysqldump -u root -p dormitory_management > backup.sql

# Monitor services
docker compose ps
```

### Service Profiles

- **Development**: `docker compose --profile dev up -d`
  - Includes: dev, db, redis, mailhog, server-base
- **Production**: `docker compose --profile production up -d`
  - Includes: app, nginx, db, redis, server-base
- **Testing**: `docker compose --profile test up -d`
  - Includes: test, server-base

### Troubleshooting

#### Common Issues

1. **Port conflicts**: Ensure ports 8000, 3306, 6379 are available
2. **Permission errors**: Run `chmod -R 755 storage bootstrap/cache` in container
3. **Database connection**: Check `.env` file and ensure database container is running
4. **Memory issues**: Increase Docker memory allocation for large applications

#### Useful Commands

```bash
# Clean up containers and volumes
docker compose down -v

# Rebuild services
docker compose --profile dev up -d --build

# View service status
docker compose ps

# Check service logs
docker compose logs [service-name]

# Access database
docker compose exec db mysql -u root -p dormitory_management
```

## 📚 API Endpoints

### Authentication

```http
POST /api/login
Content-Type: application/json

{
  "email": "admin@sdu.edu.kz",
  "password": "password"
}
```

```http
POST /api/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password",
  "password_confirmation": "password",
  "role": "student"
}
```

```http
POST /api/password/reset-link
Content-Type: application/json

{
  "email": "user@example.com"
}
```

### User Management

```http
GET /api/users
Authorization: Bearer {token}

GET /api/users/{id}
Authorization: Bearer {token}

POST /api/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "role_id": 1
}
```

### Student Management

```http
GET /api/students?faculty=engineering&status=active
Authorization: Bearer {token}

POST /api/students
Authorization: Bearer {token}
Content-Type: application/json

{
  "iin": "123456789012",
  "name": "John Doe",
  "email": "john@example.com",
  "faculty": "engineering",
  "specialist": "computer_sciences",
  "enrollment_year": 2024,
  "gender": "male",
  "password": "password"
}
```

### Dormitory Management

```http
GET /api/dormitories
Authorization: Bearer {token}

POST /api/dormitories
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "A-BLOCK",
  "capacity": 300,
  "gender": "female",
  "admin": "admin1"
}
```

### Room Management

```http
GET /api/rooms
Authorization: Bearer {token}

POST /api/rooms
Authorization: Bearer {token}
Content-Type: application/json

{
  "number": "A210",
  "floor": 2,
  "dormitory_id": 1,
  "room_type_id": 1
}
```

### Payment Management

```http
GET /api/payments?status=completed&date_from=2024-01-01
Authorization: Bearer {token}

POST /api/payments
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 1,
  "amount": 150000,
  "contract_number": "CONTRACT123",
  "contract_date": "2024-01-15",
  "payment_date": "2024-01-15",
  "payment_method": "bank_transfer",
  "semester": "fall",
  "year": 2024,
  "semester_type": "fall"
}
```

### Messaging System

```http
GET /api/messages
Authorization: Bearer {token}

POST /api/messages
Authorization: Bearer {token}
Content-Type: application/json

{
  "receiver_id": 1,
  "title": "Important Announcement",
  "content": "Please read this important message",
  "type": "announcement"
}
```

## 🔒 Business Rules

### Payment & Access Control

1. **Semester Payments**: Students must pay for each semester
2. **Dormitory Access**: Only students with current semester payments can access dormitories
3. **Payment Verification**: System tracks payment status for access control
4. **Staff Reservations**: Admin can reserve beds for staff members

### Room & Bed Management

1. **Capacity Limits**: Rooms have maximum capacity based on room type
2. **Gender Separation**: Dormitories are gender-specific
3. **Staff Reservations**: Staff-reserved beds cannot be assigned to students
4. **Availability Tracking**: System tracks room and bed availability

### User Management

1. **Role-based Access**: Different permissions for admin, student, guest
2. **Profile Separation**: Role-specific data stored in separate profile tables
3. **Data Validation**: Comprehensive validation for all user data
4. **Soft Deletes**: Users are soft-deleted for data integrity

## 🔧 Development Guidelines

### Code Style

```bash
# Code formatting
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Code quality
./vendor/bin/phpcs
```

### Database Migrations

```bash
# Create migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Refresh migrations
php artisan migrate:fresh --seed
```

### Model Development

1. **Use Eloquent Relationships**: Define proper relationships between models
2. **Implement Soft Deletes**: Use soft deletes for data integrity
3. **Add Validation Rules**: Define validation rules in models
4. **Use Accessors/Mutators**: For data transformation
5. **Add PHPDoc Comments**: Document all public methods

### Service Layer

1. **Business Logic**: Keep business logic in service classes
2. **Dependency Injection**: Use Laravel's DI container
3. **Error Handling**: Proper exception handling
4. **Validation**: Service-level validation
5. **Testing**: Comprehensive unit tests

### API Development

1. **RESTful Design**: Follow REST conventions
2. **Status Codes**: Use appropriate HTTP status codes
3. **Validation**: Request validation with custom rules
4. **Authentication**: Proper authentication middleware
5. **Documentation**: PHPDoc comments for all endpoints

## 📝 PHPDoc Examples

### Controller Methods

```php
/**
 * Display a listing of students with filters
 * 
 * @param Request $request The HTTP request containing filters
 * @return JsonResponse JSON response with students data
 * 
 * @throws ValidationException When validation fails
 * @throws AuthorizationException When user lacks permission
 */
public function index(Request $request): JsonResponse
{
    // Implementation
}
```

### Service Methods

```php
/**
 * Create a new student with profile data
 * 
 * @param array $data Validated student data
 * @return User The created user with student profile
 * 
 * @throws DatabaseException When database operation fails
 * @throws ValidationException When data validation fails
 */
public function createStudent(array $data): User
{
    // Implementation
}
```

### Model Methods

```php
/**
 * Get the student's dormitory access status
 * 
 * @return bool True if student has access, false otherwise
 */
public function hasDormitoryAccess(): bool
{
    // Implementation
}
```

## 🔒 Security Features

- **Authentication**: Laravel Sanctum for API authentication
- **Authorization**: Role-based access control middleware
- **Validation**: Comprehensive input validation
- **CSRF Protection**: Built-in CSRF protection
- **Rate Limiting**: API rate limiting for abuse prevention
- **SQL Injection Protection**: Eloquent ORM protection
- **XSS Protection**: Output escaping and sanitization

## 🚀 Performance Optimization

- **Database Indexing**: Proper database indexes
- **Query Optimization**: Efficient Eloquent queries
- **Caching**: Redis caching for frequently accessed data
- **Eager Loading**: Prevent N+1 query problems
- **Pagination**: API response pagination
- **Compression**: Response compression

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/YourFeature`)
3. Write tests first (TDD approach)
4. Implement the feature
5. Ensure all tests pass
6. Add PHPDoc comments
7. Commit your changes (`git commit -am 'Add some feature'`)
8. Push to the branch (`git push origin feature/YourFeature`)
9. Create a new Pull Request

### Code Review Checklist

- [ ] Does this follow Laravel conventions?
- [ ] Are all business rules enforced and tested?
- [ ] Is the code properly documented with PHPDoc?
- [ ] Are there comprehensive tests (unit + feature)?
- [ ] Does it handle errors gracefully?
- [ ] Is it secure and validated?

## 📄 License

This project is private and for SDU internal use only.

---

**SDU Dormitory Management API**  
Contact: [info@sdu.edu.kz](mailto:info@sdu.edu.kz)

## 🆘 Troubleshooting

### Common Issues

1. **Database Connection**: Check `.env` file for database configuration
2. **Permission Errors**: Ensure proper file permissions
3. **CORS Issues**: Configure CORS middleware properly
4. **Authentication Errors**: Check Sanctum configuration

### Development Tips

- Use `php artisan tinker` for interactive debugging
- Check Laravel logs in `storage/logs/`
- Use `php artisan route:list` to see all routes
- Use `php artisan config:cache` for production optimization
