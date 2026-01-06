# Plan: Multi-Tenant POS Backend System with Laravel 12

Build a secure, API-first Multi-Tenant POS/Inventory system with strict tenant isolation, Sanctum authentication, role-based access control, transaction-based order processing with stock management, and optimized reporting. **Critical deadline: January 10, 2026 (4 days remaining).**

## Steps

### 1. Establish Multi-Tenancy Foundation

Install Sanctum (`composer require laravel/sanctum && php artisan install:api`), create [Tenant](database/migrations) model/migration with `is_active` field, add `tenant_id` + `role` enum(Owner,Staff) to [users](database/migrations/0001_01_01_000000_create_users_table.php) table, build [ResolveTenant](app/Http/Middleware/ResolveTenant.php) middleware to extract/validate `X-Tenant-ID` header, create [BelongsToTenant](app/Traits/BelongsToTenant.php) trait with [TenantScope](app/Scopes/TenantScope.php) global scope auto-applying `tenant_id` filter, register middleware in [bootstrap/app.php](bootstrap/app.php), write isolation tests proving cross-tenant access fails

### 2. Build Core Domain Models with Tenant Scoping

Create [Product](app/Models/Product.php) model with migration (fields: `name`, `sku`, `price` decimal(10,2), `stock_quantity`, `low_stock_threshold`, soft deletes, **unique index on tenant_id+sku**), [Customer](app/Models/Customer.php) model (name, email, phone), [Order](app/Models/Order.php) model (customer_id FK nullable, order_number unique per tenant, status enum Pending/Paid/Cancelled, total_amount), [OrderItem](app/Models/OrderItem.php) model (order_id, product_id, quantity, unit_price captured, subtotal), apply `BelongsToTenant` trait to all, define relationships (Order hasMany OrderItems, OrderItem belongsTo Product), create factories for test data

### 3. Implement Transaction-Based Order Processing

Build [OrderService](app/Services/OrderService.php) with `createOrder()` method using `DB::transaction()` + `lockForUpdate()` on products: validate stock availability before creation, create order + order items, decrement stock atomically, throw `InsufficientStockException` if stock < quantity, implement `cancelOrder()` method validating status is Pending/Paid, restoring stock from order items, use transaction retry (`attempts: 3`) for deadlock handling, generate unique order_number per tenant with format `ORD-{tenant_id}-{date}-{sequence}`

### 4. Implement Policy-Based Authorization

Create [ProductPolicy](app/Policies/ProductPolicy.php) (Owner: full CRUD, Staff: view only), [OrderPolicy](app/Policies/OrderPolicy.php) (Owner: all actions, Staff: view + create), [CustomerPolicy](app/Policies/CustomerPolicy.php) (similar to Product), [ReportPolicy](app/Policies/ReportPolicy.php) (both roles can view), register in [AppServiceProvider](app/Providers/AppServiceProvider.php) with `Gate::policy()`, add authorization checks in controllers with `$this->authorize('action', Model::class)` **before** any business logic, ensure policies verify tenant_id match between user and resource

### 5. Build RESTful API Endpoints with Validation

Create [AuthController](app/Http/Controllers/Api/AuthController.php) (login returns Sanctum token, logout, me), [ProductController](app/Http/Controllers/Api/ProductController.php) with CRUD (index with pagination + eager loading, store, update, destroy), [OrderController](app/Http/Controllers/Api/OrderController.php) (index with `->with(['customer', 'items.product'])`, store calls OrderService, cancel), [CustomerController](app/Http/Controllers/Api/CustomerController.php), create Form Requests: [StoreProductRequest](app/Http/Requests/Product/StoreProductRequest.php) with unique SKU per tenant rule `Rule::unique('products','sku')->where('tenant_id', Tenant::current()->id)`, [StoreOrderRequest](app/Http/Requests/Order/StoreOrderRequest.php) validating items array, create API Resources: [ProductResource](app/Http/Resources/ProductResource.php), [OrderResource](app/Http/Resources/OrderResource.php) with nested relations, configure rate limiting to 60/minute in [bootstrap/app.php](bootstrap/app.php)

### 6. Build Optimized Reporting Module

Create [ReportService](app/Services/ReportService.php) with 3 methods: `dailySalesSummary(date)` using `whereBetween(created_at)` + `where(status, Paid)` + `selectRaw('COUNT(*) as total_orders, SUM(total_amount) as total_revenue')`, `topSellingProducts(start_date, end_date, limit=5)` joining orders+order_items+products with `SUM(quantity) as total_sold` grouped by product ordered DESC, `lowStockProducts()` with `whereColumn('stock_quantity', '<=', 'low_stock_threshold')`, add database indexes: `(tenant_id, status, created_at)` on orders for date filtering, `(tenant_id, stock_quantity)` on products, `(order_id, product_id)` on order_items, create [ReportController](app/Http/Controllers/Api/ReportController.php) with GET endpoints accepting date/date range query params, apply authorization via ReportPolicy

### 7. Write Comprehensive PHPUnit Feature Tests

Create [TenantIsolationTest](tests/Feature/TenantIsolationTest.php) proving user in tenant A cannot access tenant B's products/orders/customers (assert 404), [OrderWorkflowTest](tests/Feature/OrderWorkflowTest.php) testing stock deduction on order creation, insufficient stock validation, concurrent order handling with Race conditions, cancel order stock restoration, [AuthorizationTest](tests/Feature/AuthorizationTest.php) verifying Owner can create products but Staff cannot, [ReportTest](tests/Feature/ReportTest.php) validating calculations match expected values, use `RefreshDatabase` trait, create test data with factories, run tests after each implementation: `php artisan test --filter=OrderWorkflowTest`

### 8. Finalize Documentation & Submission

Update [README.md](README.md) with: setup instructions (composer install, migrations, seeders), architecture section explaining multi-tenancy strategy (X-Tenant-ID header → middleware → tenant scoping trait → global scope), performance optimizations (database indexes list, eager loading, transactions, pagination), key design decisions (single-tenant-per-user, captured pricing in order_items, pessimistic locking), create Postman collection with auth + CRUD + report examples including X-Tenant-ID headers, run `vendor/bin/pint` for code formatting, create 5-10min video demonstrating: tenant isolation (show 2 tenants with different data), order creation deducting stock, cancellation restoring stock, all 3 reports working, role-based access control

## Further Considerations

### 1. Enum vs String for Status/Role?

Use **PHP 8.2 Enums** ([OrderStatus](app/Enums/OrderStatus.php) backed by string, [UserRole](app/Enums/UserRole.php)) for type safety and IDE autocomplete vs database enum for validation — **Recommendation: PHP Enums** cast in models for better Laravel 12 integration and testability

### 2. Single vs Multiple Tenant per User?

Current requirement suggests single tenant per user (tenant_id directly on users table), but if users need multi-tenant access later, would require pivot table `tenant_user` with role column — **Recommendation: Implement simple single-tenant now** (faster, meets requirements), document as potential future enhancement in README trade-offs section

### 3. Caching Strategy for Reports?

Reports could be cached (15-60min TTL) especially daily sales which doesn't change frequently — **Recommendation: Implement basic caching** with `Cache::remember()` for daily sales only (low effort, high impact), document cache invalidation strategy (cache busts on new Paid orders)

### 4. Order Number Generation

Sequential per tenant could have race conditions, UUID would be unique but not human-friendly — **Recommendation: Use format** `ORD-{tenant_id}-{date}-{sequence}` with database transaction to get count, or use database auto-increment on separate sequences table for true concurrency safety

---

**Estimated effort: 4 days intensive work** | **Risk level: Medium** (tight timeline but achievable with focus on critical path) | **Success criteria: Perfect tenant isolation + transaction handling + policy authorization + passing tests**
