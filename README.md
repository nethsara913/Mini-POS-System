# Mini-POS and Inventory Management System
> **Trainee Practical Assessment Project**  
> Built with PHP 8, PDO, MySQL, JavaScript, HTML5, CSS3, and Bootstrap 5 for XAMPP on Windows.

---

## 1. Project Overview

The **Mini-POS and Inventory Management System** is a modular web application developed for supermarket, grocery, and retail store points of sale. It provides complete inventory tracking, multi-category organization, a responsive POS cashier checkout register with real-time shopping cart and discount calculations, robust database transactions with row-level locking (`SELECT ... FOR UPDATE`), sales receipts with thermal print formatting, and comprehensive date-range analytical reports.

---

## 2. Default Demo Credentials

The database comes pre-seeded with two active accounts representing the system's role-based access control (RBAC):

| Role | Username | Password | Access Rights |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `Admin@123` | Full Access: KPI Dashboard, Product & Stock CRUD, Category CRUD, POS Terminal, Checkout, Reports & Printables |
| **Staff / Cashier** | `cashier1` | `Staff@123` | Operational Access: POS Terminal, Cart, Checkout, Thermal Receipts. Restricted from inventory CRUD and reports |

*Note: Passwords are encrypted using PHP's native `password_hash()` with `PASSWORD_BCRYPT`.*

---

## 3. Technology Stack

- **Backend:** PHP 8.2+ with PDO (PHP Data Objects)
- **Database:** MySQL 8.0 / MariaDB 10.4+ (InnoDB engine with foreign key constraints)
- **Frontend:** HTML5, CSS3, JavaScript (ES6), Bootstrap 5.3.3, Bootstrap Icons
- **Server Environment:** XAMPP on Windows (`Apache` & `MySQL`)

---

## 4. Modular Project Structure

```text
mini_pos/
├── config/
│   ├── config.php          # Session security, CSRF protection, URL router, escaping & RBAC helpers
│   └── database.php        # PDO connection singleton with error handling and DB fallback
│
├── includes/
│   ├── header.php          # Bootstrap 5 head tags, meta, navbar inclusion, flash alert renderer
│   ├── navbar.php          # Dynamic navigation bar with role-based links and active state detection
│   ├── footer.php          # Footer info and Bootstrap 5 bundle scripts
│   └── auth_check.php      # Centralized session guard helper
│
├── modules/
│   ├── auth/
│   │   ├── login.php       # Sign-in form with CSRF, password_verify(), and demo account quick-fill
│   │   └── logout.php      # Session destruction, cookie invalidation, and safe redirect
│   │
│   ├── dashboard/
│   │   └── index.php       # Admin KPI dashboard (Products, Low Stock count, Today's Revenue, Recent sales)
│   │
│   ├── categories/
│   │   ├── index.php       # Category catalog with product counter and status badges
│   │   ├── create.php      # Add category (Admin only, unique name check, CSRF)
│   │   ├── edit.php        # Edit category details & status (Admin only)
│   │   └── delete.php      # Delete category with foreign key dependency protection
│   │
│   ├── products/
│   │   ├── index.php       # Inventory master catalog with stock badges and category filter
│   │   ├── create.php      # Add product (SKU generator, cost, price, stock, alert threshold)
│   │   ├── edit.php        # Edit product details and inventory adjustments
│   │   └── delete.php      # Safe delete (deactivates if past sales exist to preserve audit history)
│   │
│   ├── pos/
│   │   ├── index.php       # POS Terminal: Catalog search, barcode scanner listener, dynamic cart
│   │   ├── checkout.php    # Checkout processor with PDO transaction & SELECT FOR UPDATE row locking
│   │   └── receipt.php     # 80mm thermal receipt view with printable stylesheet
│   │
│   └── reports/
│       ├── index.php       # Date-range sales report with filters, KPIs, and top-selling items
│       └── print.php       # Clean printable report layout for audit and paper/PDF export
│
├── assets/
│   ├── css/
│   │   └── style.css       # Custom styling for POS terminal, cards, and @media print rules
│   └── js/
│       └── pos.js          # Client-side cart logic, barcode search, discounts, and change calculations
│
├── index.php               # Root entry router redirecting based on auth status and user role
├── database.sql            # Full MySQL schema, foreign keys, indexes, and seed sample data
└── README.md               # Setup instructions, architecture overview, and test checklist
```

---

## 5. XAMPP on Windows Installation Guide

### Step 1: Place Files in XAMPP
Ensure the project is located inside your XAMPP web root directory:
```text
C:\xampp\htdocs\mini_pos
```

### Step 2: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### Step 3: Database Setup in phpMyAdmin
1. Open your browser and navigate to: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. In phpMyAdmin, check the left sidebar for the database `mini-pos-db` (or create it with Collation `utf8mb4_unicode_ci`).
3. Click on `mini-pos-db`, select the **Import** tab at the top.
4. Click **Choose File**, select `database.sql` located inside `C:\xampp\htdocs\mini_pos\database.sql`.
5. Click **Import** at the bottom.

*(Alternatively, via Windows Command Prompt:)*
```cmd
cd C:\xampp\mysql\bin
mysql.exe -u root < "C:\xampp\htdocs\mini_pos\database.sql"
```

### Step 4: Access the Application
Open your web browser and visit:
```text
http://localhost/mini_pos/
```

You will be redirected automatically to the login portal.

---

## 6. Key Assessment Feature Implementations

### 1. Concurrency-Safe Stock Deduction (Transactions & Row Locking)
In `modules/pos/checkout.php`:
```php
$pdo->beginTransaction();
foreach ($cart as $productId => $qty) {
    // Row lock acquired
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if ($product['stock_quantity'] < $qty) {
        throw new Exception("Insufficient stock for " . $product['name']);
    }

    $deductStmt->execute([':deduct_qty' => $qty, ':id' => $productId]);
}
// Sales and sale_items inserts...
$pdo->commit();
```
This guarantees that concurrent cashier terminals will not produce overselling or race conditions.

### 2. Role-Based Access Control (RBAC)
Server-side authorization is strictly enforced on every route:
- `require_admin()` guards all inventory management (Products, Categories) and executive reports.
- Non-admin staff attempting to access admin endpoints are denied with a HTTP `403` status and redirected.

### 3. Output Escaping and CSRF Defense
- All user-facing outputs pass through the `e()` helper (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
- Every form mutation includes a cryptographically secure token verified by `validate_csrf()`.

### 4. Interactive POS Cart & Real-Time Calculations
- Instant item addition and quantity adjustments (+ / -).
- Barcode scanner integration (press Enter on the search bar with an SKU).
- Dynamic discount application (Flat currency or percentage).
- Tendered cash and change return calculations in real-time.
- Thermal receipt printing (80mm width standard) with barcode visualization.

---

## 7. Testing & Verification Checklist

1. **Authentication:**
   - Sign in with `admin` / `Admin@123` &rarr; Redirects to Dashboard.
   - Sign in with `cashier1` / `Staff@123` &rarr; Redirects to POS Terminal.
   - Test invalid login credentials &rarr; Error alert appears.
2. **Authorization Guards:**
   - While signed in as `cashier1`, attempt opening `modules/products/index.php` &rarr; Access denied, redirected to POS.
3. **Inventory Management:**
   - Add, edit, and filter categories.
   - Add a product with alert threshold &rarr; Inspect low-stock counter update.
4. **Point of Sale:**
   - Search by SKU (`BEV-001`) or click items to populate cart.
   - Apply a 10% discount and calculate change.
   - Complete checkout &rarr; Redirects to printable thermal receipt.
   - Verify that product stock count was automatically decremented.
5. **Reports & Audit:**
   - Filter sales by date range or specific cashier.
   - Open and test the Printable Report view.
