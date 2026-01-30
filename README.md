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

## 🐳 Docker Deployment

### Quick Start with Docker

```bash
# Start all services
docker-compose up -d

# Fix storage permissions (IMPORTANT!)
docker exec -it crm-api chown -R www-data:www-data /var/www/html/storage

# Check container status
docker-compose ps
```

### Local Development Setup

For local development, the system works with:
- **`dorm.lcl`** → Frontend (Vue.js application)
- **`dorm.lcl:8000`** → Backend API (Laravel)

**Setup local domain:**
```bash
# Add to /etc/hosts
echo "127.0.0.1 dorm.lcl" | sudo tee -a /etc/hosts
```

**Access points:**
- Frontend: `https://dorm.lcl` (port 443)
- Backend API: `https://dorm.lcl:8000` (port 8000)
- API Endpoints: `https://dorm.lcl:8000/api/*`

### Domain Architecture

The system works with a single domain:
- **`dorm.sdu.edu.kz`** → Frontend (Vue.js application) + Backend API (on port 8000)

**API Endpoints**: All API calls should be made to `https://dorm.sdu.edu.kz:8000/api/*`

**Frontend**: The Vue.js application is served from the same domain.

### Troubleshooting

**Permission Denied Error**: If you see `file_put_contents(): Failed to open stream: Permission denied`:
```bash
# Fix storage directory permissions
docker exec -it crm-api chown -R www-data:www-data /var/www/html/storage
docker exec -it crm-api chmod -R 775 /var/www/html/storage
```

**Port Access**: The backend should NOT be directly accessible on port 8000 in production. Use the reverse proxy configuration instead.

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

## 📧 Mail notifications and queue

The application sends emails for several events. All mailables are queued; a queue worker must be running for emails to be sent.

### Mail events

| Event | When | Recipients |
|-------|------|------------|
| **user.registered** | User created (public registration or admin-created student/guest) | The new user |
| **payment.status_changed** | Payment status updated to **completed** only (no email for pending → processing) | The payment’s user |
| **user.status_changed** | User status updated (e.g. approve, suspend, cron sets pending) | The user |
| **message.sent** | Admin sends a message to a scope (all / dormitory / room / individual) | All students and guests in that scope |

### Configuration

- **Mail**: Set `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, and transport-specific vars in `.env`. For local testing, `MAIL_MAILER=log` writes to `storage/logs/laravel.log`; use MailHog or similar for real SMTP.
- **Queue**: Default `QUEUE_CONNECTION` is `database`. The `jobs` table is created by migrations. Use `sync` to run jobs immediately in the same process (no worker needed, but not recommended for production).

### Running the queue worker

**Required for queued mail** (unless `QUEUE_CONNECTION=sync`):

```bash
# Process jobs using the database queue
php artisan queue:work

# Or specify connection and queue explicitly
php artisan queue:work database --queue=default
```

For production, run the worker via a process manager (e.g. systemd, supervisor) so it restarts on failure. Example systemd unit:

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/path/to/your/crm-back
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
```

### Optional: Redis queue

For better performance, use Redis as the queue driver:

1. Install and run Redis.
2. In `.env`: `QUEUE_CONNECTION=redis` and Redis connection vars (e.g. `REDIS_HOST`, `REDIS_PASSWORD`).
3. Run the worker: `php artisan queue:work redis`.

### Adding new mail events

1. Register the event in `config/mail_events.php` (mailable class and recipient type).
2. Create a mailable in `app/Mail/` and a Blade view in `resources/views/emails/`.
3. Emit `MailEventOccurred` where the event occurs: `event(new \App\Events\MailEventOccurred('your.event', [ ... ]));`
4. The `ProcessMailEvent` listener will build and queue the mailable.

### Manual QA: mail notifications

**Step-by-step triggering guide:** see **[docs/MAIL_QA_TRIGGERING_GUIDE.md](../docs/MAIL_QA_TRIGGERING_GUIDE.md)** for exact flows (prerequisites, queue/mail setup, UI steps, cron commands, edge cases, and a checklist).

Summary:

- **Queue:** Use `QUEUE_CONNECTION=sync` (no worker) or `database` + `php artisan queue:work`. **Mail:** `MAIL_MAILER=log` (check `storage/logs/laravel.log`) or MailHog.
- **user.registered:** Public student/guest registration **or** admin-created student/guest → “Registration Complete” to new user.
- **payment.status_changed:** Admin edits payment, changes status (e.g. pending → completed) → “Payment Status Update” to payment’s user.
- **user.status_changed:** Approve student, toggle access, update guest/user status, or run `students:check-payment-status` / `guests:check-payment-status` (users moved to pending) → “Account Status Update” to that user.
- **message.sent:** Admin sends message to scope (all / dormitory / room / individual) → “New Message” to each recipient in scope.
- **Edge cases:** No/invalid email → no send, log “Mail skipped” / “Mail send failed”. Failed SMTP → log “Mail send failed”, optional `failed_jobs`.

## 🐳 Docker Setup

This project uses environment-based Docker builds controlled by the `APP_ENV` variable in your `.env` file. **Only one `docker-compose.yml` file is needed** - the environment is automatically detected from your `.env` file.

### Prerequisites
- Docker and Docker Compose installed
- Git for cloning the repository

### Quick Start

#### Local Development
```bash
# Copy environment file for local development
cp env.example .env

# Edit .env file and set:
APP_ENV=local
APP_DEBUG=true
DB_PASSWORD=password

# Build and start containers
docker-compose up -d

# Run migrations and seed database
docker-compose exec api php artisan migrate
docker-compose exec api php artisan db:seed

# Access the application
# API: http://localhost:8000
# MailHog: http://localhost:8025
# Database: localhost:3306
# Redis: localhost:6379
```

#### Production
```bash
# Copy environment file for production
cp env.production .env

# Edit .env file and set:
APP_ENV=production
APP_DEBUG=false
DB_PASSWORD=your_secure_password

# Build and start containers
docker-compose up -d

# Run migrations
docker-compose exec api php artisan migrate --force

# Access the application
# API: http://localhost:8000
```

### Environment Configuration

The system automatically detects your environment from the `APP_ENV` variable in your `.env` file:

#### Local Development (`APP_ENV=local`)
- **Container Target**: `local` (development-friendly)
- **Debugging**: Full debugging enabled with Xdebug
- **Hot Reload**: Code changes reflect immediately
- **Volume Mounts**: Source code mounted for live editing
- **Development Tools**: vim, htop, mysql-client included
- **Port**: 8000
- **Caching**: File-based caching
- **Sessions**: File-based sessions

#### Production (`APP_ENV=production`)
- **Container Target**: `production` (optimized)
- **Security**: Enhanced security headers and CSRF protection
- **Performance**: Redis-based caching and session storage
- **Optimization**: OPcache enabled, compressed responses
- **Port**: 80
- **Caching**: Redis-based caching
- **Sessions**: Redis-based sessions

### Docker Commands

```bash
# Start services
docker-compose up -d

# View logs
docker-compose logs -f api

# Access container shell
docker-compose exec api sh

# Run migrations
docker-compose exec api php artisan migrate

# Run tests
docker-compose exec api php artisan test

# Clear caches
docker-compose exec api php artisan config:clear
docker-compose exec api php artisan cache:clear

# Stop services
docker-compose down

# Rebuild containers
docker-compose up --build -d
```

### Environment Variables

#### Required Variables
```env
APP_ENV=local                    # Environment: local or production
APP_DEBUG=true                   # Debug mode (true for local, false for production)
DB_PASSWORD=password             # Database password
```

#### Docker-Specific Variables
```env
API_PORT=8000                    # API port (8000 for local, 80 for production)
VOLUME_MOUNT=.:/var/www/html     # Volume mount (.:/var/www/html for local, /dev/null:/dev/null for production)
CACHE_STORE=file                 # Cache store (file for local, redis for production)
SESSION_DRIVER=file              # Session driver (file for local, redis for production)
QUEUE_CONNECTION=sync            # Queue connection (sync for local, redis for production)
```

### Services

- **api**: Laravel application (environment-based build)
- **db**: MySQL 8.0 database
- **redis**: Redis cache and session storage
- **mailhog**: Email testing (development only)

### Troubleshooting

#### Common Issues
1. **Port conflicts**: Ensure ports 8000, 3306, 6379 are available
2. **Permission errors**: Run `chmod -R 755 storage bootstrap/cache` in container
3. **Database connection**: Check DB_HOST and credentials in .env
4. **Build failures**: Clear Docker cache with `docker system prune -a`

#### Debug Commands
```bash
# Check container status
docker-compose ps

# View detailed logs
docker-compose logs -f

# Rebuild containers
docker-compose up --build -d

# Clean up
docker-compose down -v
docker system prune -f
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

## ⏰ Laravel Scheduler Setup (Ubuntu Server)

The application uses Laravel's task scheduler to run scheduled commands. You need to set up a cron job on your Ubuntu server to run the scheduler.

### Scheduled Commands

The following commands are scheduled to run automatically:
- `payments:generate` - Runs monthly on the 1st at 00:00
- `students:check-payment-status` - Runs daily
- `guests:check-payment-status` - Runs daily

### Setting Up the Cron Job

1. **Edit the crontab** for the user that runs your Laravel application (usually `www-data` or your application user):

```bash
sudo crontab -u www-data -e
```

Or if running as a specific user:
```bash
crontab -e
```

2. **Add the following line** to run the Laravel scheduler every minute:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**Important**: Replace `/path/to/your/project` with the actual absolute path to your Laravel project directory (e.g., `/var/www/dormitory-api` or `/home/user/crm-back`).

3. **Verify the cron job is set up correctly**:

```bash
# Check if cron job is added
sudo crontab -u www-data -l

# Or for your user
crontab -l
```

4. **Test the scheduler manually** (optional):

```bash
cd /path/to/your/project
php artisan schedule:run
```

5. **View scheduled tasks**:

```bash
php artisan schedule:list
```

### Example Setup

For a production server where the application is in `/var/www/dormitory-api`:

```bash
# Edit crontab
sudo crontab -u www-data -e

# Add this line:
* * * * * cd /var/www/dormitory-api && php artisan schedule:run >> /dev/null 2>&1
```

### Troubleshooting the Scheduler

1. **Check if cron is running**:
```bash
sudo systemctl status cron
```

2. **Check cron logs** (Ubuntu):
```bash
sudo tail -f /var/log/syslog | grep CRON
```

3. **Test individual scheduled commands manually**:
```bash
# Test student payment check
php artisan students:check-payment-status

# Test guest payment check
php artisan guests:check-payment-status

# Test payment generation
php artisan payments:generate
```

4. **Verify scheduler is working**:
```bash
# This should show your scheduled tasks
php artisan schedule:list

# Run scheduler manually to see output
php artisan schedule:run -v
```

### Important Notes

- The cron job runs the scheduler every minute, but Laravel's scheduler determines when each command actually executes based on the schedule defined in `routes/console.php`
- Ensure the user running the cron job has proper permissions to execute PHP and access the Laravel application files
- For Docker deployments, you may need to run the cron job inside the container or use a separate cron container
- Always use absolute paths in cron jobs, never relative paths

## 🆘 Troubleshooting

### Common Issues

1. **Database Connection**: Check `.env` file for database configuration
2. **Permission Errors**: Ensure proper file permissions
3. **CORS Issues**: Configure CORS middleware properly
4. **Authentication Errors**: Check Sanctum configuration
5. **Scheduler Not Running**: Verify cron job is set up correctly (see Laravel Scheduler Setup section above)

### Development Tips

- Use `php artisan tinker` for interactive debugging
- Check Laravel logs in `storage/logs/`
- Use `php artisan route:list` to see all routes
- Use `php artisan config:cache` for production optimization
- Use `php artisan schedule:list` to view scheduled tasks
- Use `php artisan schedule:run -v` to test scheduler with verbose output
