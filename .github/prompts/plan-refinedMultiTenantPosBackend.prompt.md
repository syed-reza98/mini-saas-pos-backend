# Refined Plan: Secure Multi-Tenant POS Backend System with Laravel 12

Build a robust, API-first Multi-Tenant POS/Inventory system using Laravel 12, emphasizing secure tenant data isolation, efficient performance, and modern development practices. Strictly adhere to the provided Technical Assignment document. Critical deadline: January 10, 2026.

## Steps

1.  **Establish Secure Multi-Tenant Foundation**
    *   Install Laravel Sanctum (`composer require laravel/sanctum`, `php artisan sanctum:install --api`).
    *   Create a [Tenant](database/migrations) model and migration with fields like `id`, `name`, `is_active`, etc.
    *   Add `tenant_id` (foreign key to `tenants.id`) and `role` (enum: `Owner`, `Staff`) columns to the [users](database/migrations/0001_01_01_000000_create_users_table.php) table migration.
    *   **CRITICAL:** Create [ResolveTenantByHeader](app/Http/Middleware/ResolveTenantByHeader.php) middleware to extract the `X-Tenant-ID` from the request header. This middleware must resolve the active tenant context *before* any application logic executes. Store the resolved tenant ID in a request attribute or a service container singleton for consistent access throughout the request lifecycle.
    *   Create a [BelongsToTenant](app/Traits/BelongsToTenant.php) trait and a [TenantScope](app/Scopes/TenantScope.php) global query scope. The `TenantScope` must automatically append a `where('tenant_id', resolved_tenant_id)` clause to all queries for models using the trait. Apply this trait to all tenant-specific models (Product, Customer, Order, OrderItem).
    *   Register the `ResolveTenantByHeader` middleware in [bootstrap/app.php](bootstrap/app.php) (e.g., as part of the 'api' middleware group).
    *   Implement a `CurrentTenant` service or helper to provide easy access to the resolved tenant context within the application.
    *   Write comprehensive feature tests ([TenantIsolationTest](tests/Feature/TenantIsolationTest.php)) to prove that a user authenticated for Tenant A cannot access, modify, or even *see* data belonging to Tenant B. This is a **Disqualification Criterion**.

2.  **Build Core Domain Models with Tenant Scoping**
    *   Create [Product](app/Models/Product.php) model and migration with fields: `id`, `tenant_id`, `name`, `sku` (unique per `tenant_id`), `price` (decimal 10,2), `stock_quantity`, `low_stock_threshold`, `created_at`, `updated_at`, `deleted_at` (soft deletes). Add a unique index on `(tenant_id, sku)`.
    *   Create [Customer](app/Models/Customer.php) model and migration: `id`, `tenant_id`, `name`, `email`, `phone`, `created_at`, `updated_at`, `deleted_at`.
    *   Create [Order](app/Models/Order.php) model and migration: `id`, `tenant_id`, `customer_id` (foreign key, nullable), `order_number` (unique per `tenant_id`), `status` (enum: `Pending`, `Paid`, `Cancelled`), `total_amount`, `created_at`, `updated_at`.
    *   Create [OrderItem](app/Models/OrderItem.php) model and migration: `id`, `order_id` (foreign key), `product_id` (foreign key), `quantity`, `unit_price` (captured price at time of order), `subtotal`.
    *   Apply the `BelongsToTenant` trait to `Product`, `Customer`, `Order`, and `OrderItem` models.
    *   Define Eloquent relationships (e.g., `Order` `hasMany` `OrderItem`, `OrderItem` `belongsTo` `Product`).
    *   Create Laravel Factories ([ProductFactory](database/factories/ProductFactory.php), [CustomerFactory](database/factories/CustomerFactory.php), [OrderFactory](database/factories/OrderFactory.php), [OrderItemFactory](database/factories/OrderItemFactory.php)) for each model to facilitate testing.

3.  **Implement Robust Transaction-Based Order Processing**
    *   Build an [OrderService](app/Services/OrderService.php) class.
    *   Implement `createOrder(array $data, User $user)` method within the service:
        *   Use `DB::transaction()` to wrap the entire operation.
        *   Validate stock availability *before* creating the order, potentially using `lockForUpdate()` on the relevant `Product` models to prevent race conditions.
        *   If stock is insufficient for any item, throw a custom `InsufficientStockException`.
        *   Create the `Order` record.
        *   Create associated `OrderItem` records.
        *   Atomically decrement the `stock_quantity` on the corresponding `Product` records.
        *   Generate a unique `order_number` per tenant (e.g., `ORD-{tenant_id}-{date}-{unique_suffix}`).
    *   Implement `cancelOrder(Order $order, User $user)` method:
        *   Verify the order's status is `Pending` or `Paid`.
        *   Use `DB::transaction()`.
        *   Update the order status to `Cancelled`.
        *   Atomically restore the `stock_quantity` for the products listed in the order's items.
    *   Ensure all order-related operations are encapsulated within this service and always use database transactions.

4.  **Implement Policy-Based Authorization**
    *   Create [ProductPolicy](app/Policies/ProductPolicy.php) (Owner: full CRUD, Staff: view only), [OrderPolicy](app/Policies/OrderPolicy.php) (Owner: all actions, Staff: view + create), [CustomerPolicy](app/Policies/CustomerPolicy.php) (similar to Product), [ReportPolicy](app/Policies/ReportPolicy.php) (both roles can view).
    *   Register these policies in [AppServiceProvider](app/Providers/AppServiceProvider.php) using `Gate::policy()`.
    *   In all relevant API controllers, call `$this->authorize('action', $model)` *before* performing the action. Ensure policies check that the authenticated user's `tenant_id` matches the `tenant_id` of the model instance being accessed.

5.  **Build RESTful API Endpoints with Validation & Resources**
    *   Create API Resource classes ([ProductResource](app/Http/Resources/ProductResource.php), [OrderResource](app/Http/Resources/OrderResource.php), [CustomerResource](app/Http/Resources/CustomerResource.php), [ReportResource](app/Http/Resources/ReportResource.php)) for consistent JSON output.
    *   Create API Controllers ([AuthController](app/Http/Controllers/Api/AuthController.php), [ProductController](app/Http/Controllers/Api/ProductController.php), [OrderController](app/Http/Controllers/Api/OrderController.php), [CustomerController](app/Http/Controllers/Api/CustomerController.php), [ReportController](app/Http/Controllers/Api/ReportController.php)).
    *   Implement standard CRUD endpoints following REST conventions.
    *   Use Laravel Form Requests ([StoreProductRequest](app/Http/Requests/Api/Product/StoreProductRequest.php), [UpdateProductRequest](app/Http/Requests/Api/Product/UpdateProductRequest.php), [StoreOrderRequest](app/Http/Requests/Api/Order/StoreOrderRequest.php), etc.) for all input validation. Ensure unique rules for `sku` are scoped per tenant (e.g., `Rule::unique('products')->where(function ($query) { return $query->where('tenant_id', auth()->user()->tenant_id); })`).
    *   Implement pagination for list endpoints (e.g., `ProductController@index`).
    *   Use eager loading (`with(['relationship'])`) in index methods to prevent N+1 queries (e.g., `OrderController@index` should eager load `customer` and `items.product`).
    *   Configure API rate limiting (e.g., 60 requests per minute per IP/user) in [bootstrap/app.php](bootstrap/app.php).

6.  **Build Optimized Reporting Module**
    *   Create a [ReportService](app/Services/ReportService.php) class.
    *   Implement methods: `getDailySalesSummary(\Carbon\Carbon $date, int $tenantId)`, `getTopSellingProducts(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate, int $tenantId, int $limit = 5)`, `getLowStockProducts(int $tenantId)`.
    *   Write optimized queries using `selectRaw`, `join`, `groupBy`, `orderBy`, and `where` clauses. Explicitly use eager loading or `with()` if necessary within the service.
    *   Add crucial database indexes based on query patterns: e.g., `(tenant_id, status, created_at)` on `orders`, `(tenant_id, stock_quantity)` on `products`, `(tenant_id, order_id)` on `order_items`.
    *   Create [ReportController](app/Http/Controllers/Api/ReportController.php) with GET endpoints for each report, accepting query parameters for dates/ranges. Apply `ReportPolicy` for authorization.

7.  **Integrate Bonus Features (As Per Assignment Guidelines)**
    *   **Docker:** Create [Dockerfile](Dockerfile) and [docker-compose.yml](docker-compose.yml) for a containerized development environment (PHP, Nginx, Database, Redis).
    *   **Swagger/OpenAPI:** Integrate a package like `darkaonline/l5-swagger` or `scribe`. Annotate your API endpoints using `@OA` annotations to generate interactive documentation. Configure it to recognize the `X-Tenant-ID` header.
    *   **Background Jobs:** Identify potential candidates for background processing (e.g., sending email receipts, generating complex reports). Create Jobs ([SendOrderReceiptJob](app/Jobs/SendOrderReceiptJob.php), [GenerateReportJob](app/Jobs/GenerateReportJob.php)) using `php artisan make:job`. Configure and use the queue system (e.g., database or Redis queue driver). Ensure jobs are tenant-aware by passing necessary tenant context or resolving it within the job.
    *   **PHPUnit Tests:** Write comprehensive feature tests covering authentication, tenant isolation, order workflow (creation, stock deduction, cancellation, stock restoration), authorization (role-based access), and reporting calculations. Use the `RefreshDatabase` trait and factories.

8.  **Finalize Documentation & Submission**
    *   Update [README.md](README.md): Include setup instructions (Docker, standard), architecture overview (focus on multi-tenancy strategy: header -> middleware -> scoping), performance decisions (indexes, eager loading), key trade-offs.
    *   Generate a Postman collection or similar API documentation showcasing all major endpoints, including the `X-Tenant-ID` header usage.
    *   Ensure code is formatted (`php artisan pint`).
    *   Prepare the demonstration video as per assignment guidelines.

## Further Considerations

1.  **Tenant Resolution & Data Isolation:**
    *   **Clarification:** The `X-Tenant-ID` header is the *only* source for determining tenant context. Never rely on a `tenant_id` sent within the request body or query parameters for scoping queries. The middleware must set the context, and the global scope must enforce it automatically. This is non-negotiable for security.

2.  **Laravel 12 Features:**
    *   **Clarification:** While the plan doesn't explicitly mandate every new Laravel 12 feature, consider adopting beneficial ones like lazy database connections (`DB_CONNECTION_LAZY=true` in `.env`) if it improves performance in your setup, and the `softDeletableResources()` helper in `RouteServiceProvider` if you extensively use soft deletes in API routes. Prioritize features that directly enhance the core requirements (security, performance, maintainability).

3.  **Bonus Features Integration:**
    *   **Clarification:** The bonus features (Docker, Swagger, Jobs) should be seamlessly integrated into the development and deployment workflow. For example, Docker setup should be functional and documented. Swagger docs should be generated and accessible. Queued jobs should be used for appropriate tasks and be tenant-aware.

4.  **Testing Depth:**
    *   **Clarification:** Tests must go beyond basic CRUD. Focus heavily on verifying tenant isolation, transactional integrity (stock changes), and authorization rules. Simulate concurrent orders to ensure stock handling is robust. This is crucial for meeting evaluation criteria.

5.  **Enum vs String for Status/Role:**
    *   **Clarification:** Use **PHP 8.2 Enums** ([OrderStatus](app/Enums/OrderStatus.php) backed by string, [UserRole](app/Enums/UserRole.php)) for type safety and IDE autocomplete vs database enum for validation. **Recommendation: PHP Enums** cast in models for better Laravel 12 integration and testability. Update the `status` column in the `orders` table and the `role` column in the `users` table to be `string` types, and use casts in the respective models (e.g., `'status' => OrderStatus::class`). This provides better type safety and maintainability compared to raw database enums.

6.  **Single vs Multiple Tenant per User:**
    *   **Clarification:** The current requirement suggests a single tenant per user (with `tenant_id` directly on the `users` table). This approach is simpler, faster to implement, and meets the assignment requirements. If users need multi-tenant access later, a pivot table `tenant_user` with a role column would be required. **Recommendation: Implement the simple single-tenant model now**, as it aligns with the immediate needs. Document this design choice and the potential pivot table approach as a possible future enhancement in the README's trade-offs section.

7.  **Caching Strategy for Reports:**
    *   **Clarification:** For performance, consider caching frequently accessed reports like daily sales, which don't change once the day is over. **Recommendation: Implement basic caching** using `Cache::remember()` for the `getDailySalesSummary` method in the `ReportService` (e.g., TTL of 30 minutes to 1 hour). This provides a low-effort, high-impact performance boost. Document the cache invalidation strategy in the README, such as the cache being invalidated (e.g., using `Cache::forget()`) when a new order with status 'Paid' is successfully created for the relevant date, ensuring data freshness.

8.  **Order Number Generation:**
    *   **Clarification:** Generating a human-readable, sequential order number per tenant can be prone to race conditions if not handled carefully. UUIDs are unique but not human-friendly. **Recommendation: Use a format** like `ORD-{tenant_id}-{date}-{sequence}`. For concurrency safety, you can use a database transaction to fetch the current highest sequence number for the tenant and date, increment it, and then create the order. Alternatively, for true atomicity without complex locking, create a separate `order_sequences` table with `(tenant_id, date, last_number)` and use `DB::transaction()` to update it and get the next number before creating the order. This ensures uniqueness and handles high concurrency correctly.
