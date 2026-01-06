
# Technical Assignment

## Project Name: Mini SaaS / POS Backend System (API-First)

**Project Submission Deadline:** 10 January 2026 at 10:00 AM

### 1. Problem Statement

You are required to design and develop a **Multi-Tenant POS / Inventory Management Backend System** using  **Laravel** (latest version 12).

The system must be:

- API-first
- Secure
- Scalable
- Optimized for real-world production use

Each business operates as an independent **tenant**, and **strict data isolation between tenants is mandatory**.

### 2. Authentication & Roles

- Implement authentication using **Laravel Sanctum**

- Support the following user roles:
    - **Owner**
    - **Staff**

- Apply **role-based access control** using **Laravel Policies or Gates**

- Authorization logic **must not** be hard-coded inside controllers

### 3. Multi-Tenancy (Critical Requirement)

- Each tenant (business) must have its own isolated data for:
    - **Products**
    - **Customers**
    - **Orders**

- Tenant context must be resolved using the HTTP request header: `X-Tenant-ID`

- Data must be **fully isolated** between tenants at all levels (queries, authorization, and business logic) **Under no circumstances should one tenant be able to access another tenant’s data.**

### 4. Inventory & Orders

#### Product

Each product must include the following fields:

- Name
- SKU (**must be unique per tenant**)
- Price
- Stock quantity
- Low stock threshold

#### Order

- Orders may contain multiple products

- Order creation must:
    - Deduct stock accurately
    - Prevent negative inventory
    - Use **database transactions**

- Order statuses:
    - Pending
    - Paid
    - Cancelled

- Cancelling an order must correctly **restore stock**

### 5. Reporting Module

Implement the following reports:

1. **Daily sales summary**
2. **Top 5 selling products** (based on a selected date range)
3. **Low stock report**

Reporting requirements:

- Queries must be optimized
- No N+1 query issues
- Use eager loading
- Apply appropriate database indexing where required

### 6. Validation & Security

- Use **Form Request** validation for all inputs
- Enforce authorization using **Policies**
- Protect against:
    - Mass assignment vulnerabilities
    - Unauthorized access
- Implement API rate limiting
- Ensure secure error handling without exposing sensitive system information

### 7. Performance Considerations

- Use eager loading wherever applicable
- Optimize database queries
- Apply appropriate database indexes
- Clearly explain all performance-related decisions in the **README**

### 8. API Design Standards

- Follow RESTful API conventions
- Maintain a consistent JSON response structure
- Use **Laravel API Resources**
- Implement pagination for list endpoints

### 9. Bonus (Optional – Not Mandatory)

Additional credit will be given for implementing any of the following:

- PHPUnit feature tests
- Docker-based development setup
- Swagger / OpenAPI documentation
- Background jobs for reporting or heavy operations

### 10. Submission Guidelines

Please submit the following items:

- **GitHub repository link**
- **README.md** including:
    - Project setup instructions
    - Architecture overview
    - Multi-tenancy strategy
    - Key design decisions and trade-offs
- Sample **Postman collection** or API usage examples
- **A short video demonstration of the working system**, clearly explaining:
    - The overall system architecture
    - How multi-tenancy is handled (tenant isolation)
    - Authentication and role-based access control
    - Inventory and order workflow (including stock handling)
    - Reporting features

**Video Guidelines:**

- Duration: **5–10 minutes**
- Screen recording with voice explanation is preferred
- The video may be shared via **Google Drive, YouTube (unlisted), or similar platforms**

### 11. Disqualification Criteria

Submissions may be rejected if:

- Tenant isolation is missing or incorrectly implemented
- Database transactions are not used for order-related operations
- Authorization logic is placed directly inside controllers
- Input validation is missing
- The solution is clearly copied from tutorials without meaningful customization

### 12. Evaluation Focus

Your submission will be evaluated based on:

- Overall system architecture and code structure
- Multi-tenant data isolation
- Business logic correctness and transaction handling
- Security and performance awareness
- Code readability and documentation quality

Please submit your completed assignment by replying to this email with your **GitHub repository link** on or before **10 January 2026 at 10:00 AM**.

We appreciate the time and effort you invest in this assignment and look forward to reviewing your submission.

Best regards,

**Avanteca Limited**

**Hiring Team**

career@avantecatech.com