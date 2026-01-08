# Mini SaaS POS Backend System

A robust, API-first Multi-Tenant POS/Inventory Management Backend System built with Laravel 12. This system provides secure tenant data isolation, efficient performance, and modern development practices.

## Table of Contents

- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Architecture Overview](#architecture-overview)
- [Multi-Tenancy Strategy](#multi-tenancy-strategy)
- [Authentication & Authorization](#authentication--authorization)
- [API Documentation](#api-documentation)
- [Database Schema](#database-schema)
- [Performance Considerations](#performance-considerations)
- [Testing](#testing)
- [Docker Setup](#docker-setup)

## Features

- **Multi-Tenancy**: Complete tenant data isolation via `X-Tenant-ID` header
- **Authentication**: Laravel Sanctum token-based API authentication
- **Role-Based Access Control**: Owner and Staff roles with policy-based authorization
- **Inventory Management**: Products with SKU (unique per tenant), stock tracking, low stock alerts
- **Order Processing**: Transaction-based order creation with stock deduction and cancellation with stock restoration
- **Reporting**: Daily sales summary, top selling products, low stock reports
- **API Standards**: RESTful API with Laravel Resources, pagination, and rate limiting

## System Requirements

- PHP >= 8.2
- MySQL >= 8.0
- Composer >= 2.0
- Node.js >= 18.0 (for asset compilation)
- Redis (optional, for production rate limiting)

## Installation

### Standard Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/mini-saas-pos-backend.git
   cd mini-saas-pos-backend
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database in `.env`**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=mini_saas_pos
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Start the development server**
   ```bash
   php artisan serve
   ```

### Docker Setup

1. **Build and start containers**
   ```bash
   docker-compose up -d --build
   ```

2. **Install dependencies inside container**
   ```bash
   docker-compose exec app composer install
   docker-compose exec app php artisan key:generate
   docker-compose exec app php artisan migrate
   ```

3. **Access the application at** `http://localhost:8000`

## Architecture Overview

```
app/
├── Enums/              # PHP Enums for type-safe status values
│   ├── OrderStatus.php
│   └── UserRole.php
├── Exceptions/         # Custom exception classes
│   └── InsufficientStockException.php
├── Http/
│   ├── Controllers/Api/  # API controllers
│   ├── Middleware/       # Custom middleware (ResolveTenantByHeader)
│   ├── Requests/Api/     # Form Request validation classes
│   └── Resources/Api/    # API Resource transformers
├── Models/             # Eloquent models with BelongsToTenant trait
├── Policies/           # Authorization policies
├── Services/           # Business logic services
│   ├── CurrentTenant.php
│   ├── OrderService.php
│   └── ReportService.php
└── Traits/             # Reusable traits
    └── BelongsToTenant.php
```

## Multi-Tenancy Strategy

### Overview

The system implements a **single-database, shared-schema** multi-tenancy approach where all tenants share the same database tables, but data is isolated using a `tenant_id` foreign key.

### Tenant Resolution Flow

1. **HTTP Request** arrives with `X-Tenant-ID` header
2. **ResolveTenantByHeader Middleware** validates and resolves the tenant
3. **CurrentTenant Service** (singleton) stores the tenant context
4. **TenantScope** (global scope) automatically filters all queries by `tenant_id`
5. **BelongsToTenant Trait** automatically sets `tenant_id` on model creation

### Implementation Details

```php
// Middleware resolves tenant from header
$tenantId = $request->header('X-Tenant-ID');
$tenant = Tenant::find($tenantId);
app(CurrentTenant::class)->set($tenant);

// TenantScope automatically filters queries
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app(CurrentTenant::class)->check()) {
            $builder->where('tenant_id', app(CurrentTenant::class)->id());
        }
    }
}
```

### Security Considerations

- Tenant context is **ONLY** resolved from the `X-Tenant-ID` header (never from request body)
- Global scopes ensure queries are **always** filtered by tenant
- Policies perform **double-check** by verifying user's tenant matches resource's tenant
- Inactive tenants are **denied access** at the middleware level

## Authentication & Authorization

### Authentication (Laravel Sanctum)

- **Register**: `POST /api/v1/auth/register`
- **Login**: `POST /api/v1/auth/login`
- **Get Profile**: `GET /api/v1/auth/me`
- **Logout**: `POST /api/v1/auth/logout`

### Roles

| Role  | Products | Customers | Orders | Reports |
|-------|----------|-----------|--------|---------|
| Owner | Full CRUD | Full CRUD | Full CRUD + Cancel | View |
| Staff | View only | View only | View + Create | View |

### Policy-Based Authorization

Authorization is **NOT** hard-coded in controllers. Policies are used:

```php
// In controller
$this->authorize('create', Product::class);
$this->authorize('update', $product);

// Policy checks both permission AND tenant ownership
public function update(User $user, Product $product): bool
{
    return $user->isOwner() && $user->tenant_id === $product->tenant_id;
}
```

## API Documentation

### Base URL

```
http://localhost:8000/api/v1
```

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization` | Yes* | `Bearer {token}` for protected routes |
| `X-Tenant-ID` | Yes** | Tenant ID for tenant-scoped routes |
| `Accept` | Recommended | `application/json` |

*Required for all routes except register/login
**Required for routes under tenant middleware

### Endpoints

#### Authentication
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login and get token
- `GET /auth/me` - Get authenticated user
- `POST /auth/logout` - Revoke current token

#### Products (requires tenant header)
- `GET /products` - List products (paginated, searchable)
- `POST /products` - Create product (Owner only)
- `GET /products/{id}` - Get product
- `PUT /products/{id}` - Update product (Owner only)
- `DELETE /products/{id}` - Delete product (Owner only)

#### Customers (requires tenant header)
- `GET /customers` - List customers
- `POST /customers` - Create customer (Owner only)
- `GET /customers/{id}` - Get customer
- `PUT /customers/{id}` - Update customer (Owner only)
- `DELETE /customers/{id}` - Delete customer (Owner only)

#### Orders (requires tenant header)
- `GET /orders` - List orders (filterable by status, date)
- `POST /orders` - Create order (Owner + Staff)
- `GET /orders/{id}` - Get order with items
- `PATCH /orders/{id}/status` - Update order status (Owner only)
- `POST /orders/{id}/cancel` - Cancel order and restore stock (Owner only)

#### Reports (requires tenant header)
- `GET /reports/daily-sales?date=YYYY-MM-DD` - Daily sales summary
- `GET /reports/top-selling-products?start_date=...&end_date=...` - Top 5 products
- `GET /reports/low-stock` - Low stock products

### Response Format

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

Error responses:
```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }
}
```

## Database Schema

### Tables

- `tenants` - Business/tenant information
- `users` - Users with `tenant_id` and `role`
- `products` - Products with unique SKU per tenant
- `customers` - Customer information
- `orders` - Order headers with status tracking
- `order_items` - Order line items

### Key Indexes

```sql
-- Products
UNIQUE INDEX (tenant_id, sku)
INDEX (tenant_id, stock_quantity)

-- Orders
UNIQUE INDEX (tenant_id, order_number)
INDEX (tenant_id, status, created_at)

-- Customers
INDEX (tenant_id, email)
INDEX (tenant_id, phone)
```

## Performance Considerations

### Eager Loading

All API endpoints use eager loading to prevent N+1 queries:

```php
Order::with(['customer', 'items.product'])->paginate(15);
```

### Database Indexing

Indexes are added for:
- Tenant scoping queries (`tenant_id`)
- Unique constraints (`tenant_id + sku`, `tenant_id + order_number`)
- Reporting queries (`tenant_id + status + created_at`)
- Stock queries (`tenant_id + stock_quantity`)

### Caching (Reports)

Daily sales reports are cached for past dates (TTL: 30 minutes) since historical data doesn't change:

```php
Cache::remember("daily_sales_{$tenantId}_{$date}", 1800, fn () => ...);
```

### Transaction Safety

All order operations use database transactions with row-level locking:

```php
DB::transaction(function () {
    $products = Product::whereIn('id', $ids)->lockForUpdate()->get();
    // ... create order, deduct stock
});
```

## Testing

### Running Tests

```bash
# All tests
php artisan test

# Specific test file
php artisan test tests/Feature/TenantIsolationTest.php

# Filter by name
php artisan test --filter=tenant
```

### Test Coverage

- **TenantIsolationTest**: Verifies complete tenant data isolation
- **AuthenticationTest**: Tests registration, login, and token management
- **ProductControllerTest**: Tests product CRUD with authorization
- **OrderControllerTest**: Tests order workflow including stock transactions

## Docker Setup

### Services

- **app**: PHP 8.2 FPM with Laravel
- **webserver**: Nginx
- **db**: MySQL 8.0
- **redis**: Redis (for caching/queues)

### Commands

```bash
# Start services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Run tests
docker-compose exec app php artisan test

# Stop services
docker-compose down
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
