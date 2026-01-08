# API Testing Instructions

## Quick Start

### 1. Import Postman Collection

1. Open Postman
2. Click "Import" button
3. Select the `postman_collection.json` file from the project root
4. Collection "Mini SaaS POS Backend API" will be imported

### 2. Set Up Environment Variables

Create a new environment in Postman with these variables:

| Variable | Value |
|----------|-------|
| baseUrl | `http://localhost:8000/api/v1` |
| tenantId | `1` |
| authToken | (leave empty, auto-populated on login) |

### 3. Start the Application

```bash
# Standard
php artisan serve

# OR with Docker
docker-compose up -d
```

### 4. Seed Test Data

Run in terminal:
```bash
php artisan tinker
```

Then paste:
```php
$tenant = \App\Models\Tenant::create([
    'name' => 'Test Business',
    'slug' => 'test-business',
    'email' => 'test@business.com',
    'is_active' => true
]);

$owner = \App\Models\User::create([
    'tenant_id' => $tenant->id,
    'name' => 'John Owner',
    'email' => 'owner@test.com',
    'password' => bcrypt('password123'),
    'role' => 'owner'
]);

$staff = \App\Models\User::create([
    'tenant_id' => $tenant->id,
    'name' => 'Jane Staff',
    'email' => 'staff@test.com',
    'password' => bcrypt('password123'),
    'role' => 'staff'
]);

app(\App\Services\CurrentTenant::class)->set($tenant);

\App\Models\Product::create([
    'tenant_id' => $tenant->id,
    'name' => 'Laptop',
    'sku' => 'LAP-001',
    'price' => 999.99,
    'stock_quantity' => 50,
    'low_stock_threshold' => 10
]);

\App\Models\Product::create([
    'tenant_id' => $tenant->id,
    'name' => 'Mouse',
    'sku' => 'MOU-001',
    'price' => 29.99,
    'stock_quantity' => 100,
    'low_stock_threshold' => 20
]);

\App\Models\Product::create([
    'tenant_id' => $tenant->id,
    'name' => 'Keyboard',
    'sku' => 'KEY-001',
    'price' => 149.99,
    'stock_quantity' => 5,
    'low_stock_threshold' => 10
]);

\App\Models\Customer::create([
    'tenant_id' => $tenant->id,
    'name' => 'Alice Customer',
    'email' => 'alice@customer.com',
    'phone' => '555-1234',
    'address' => '123 Main St'
]);

return "Test data seeded successfully!";
```

## Testing Flow

### Step 1: Authentication
1. Run "Login (Owner)" request
   - Token will be auto-saved to `authToken` variable
2. Run "Get Profile" to verify authentication

### Step 2: Product Management
1. Run "List Products" - should see 3 products
2. Run "Create Product (Owner Only)" - creates new product
3. Try same with Staff login - should get 403 Forbidden

### Step 3: Order Creation
1. Run "Create Order" - creates order with 2 items
2. Verify stock is deducted from products
3. Order number format: `ORD-1-20260108-0001`

### Step 4: Order Status Management
1. Run "Mark Order as Paid (Owner Only)"
2. Verify `paid_at` timestamp is set

### Step 5: Order Cancellation
1. Create another order
2. Run "Cancel Order (Owner Only)"
3. Verify stock is restored

### Step 6: Reports
1. Run "Daily Sales Summary" - shows today's sales
2. Run "Top Selling Products" - shows best sellers
3. Run "Low Stock Report" - shows Keyboard (qty: 5, threshold: 10)

### Step 7: Testing Error Scenarios
1. Run "Test: Missing Tenant Header" - should return 400
2. Run "Test: Insufficient Stock" - should return 422

## Expected Responses

### Successful Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }
}
```

## Key Features to Test

### ✅ Multi-Tenancy
- All tenant-scoped requests require `X-Tenant-ID` header
- Data is completely isolated between tenants
- Invalid tenant ID returns 404

### ✅ Authentication
- Token-based authentication with Sanctum
- Token automatically included in requests via Bearer auth
- Logout revokes the token

### ✅ Authorization
- Owner: Full CRUD on products, customers, orders
- Staff: View only on products/customers, can create orders
- Unauthorized actions return 403

### ✅ Stock Management
- Creating order automatically deducts stock
- Cancelling order automatically restores stock
- Insufficient stock throws proper error
- Uses database transactions for data integrity

### ✅ Reporting
- Daily sales cached for past dates
- Top products calculated from paid orders only
- Low stock identified correctly

## Troubleshooting

### "Missing required header: X-Tenant-ID"
- Ensure X-Tenant-ID header is set to a valid tenant ID

### "Unauthenticated"
- Run Login request first
- Check that authToken variable is populated
- Verify Bearer token is included in Authorization header

### "Unauthorized" (403)
- Check user role (Owner vs Staff)
- Verify tenant ID matches user's tenant

### "Resource not found" (404)
- Resource may not exist
- Resource may belong to different tenant (filtered by scope)
