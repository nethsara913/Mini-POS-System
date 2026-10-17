<?php
/**
 * Automated System Verification Script
 * Tests PHP syntax, database integrity, auth helpers, and transaction logic.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

echo "========================================================\n";
echo " Mini-POS Automated System Verification Suite\n";
echo "========================================================\n\n";

$testsPassed = 0;
$testsTotal  = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $testsPassed, $testsTotal;
    $testsTotal++;
    if ($condition) {
        $testsPassed++;
        echo "[PASS] " . $name . "\n";
    } else {
        echo "[FAIL] " . $name . ($details ? " - " . $details : "") . "\n";
    }
}

// 1. Test Database Connection
try {
    $pdo = get_db_connection();
    assert_test("Database connection to " . DB_NAME, $pdo instanceof PDO);
} catch (Exception $e) {
    assert_test("Database connection", false, $e->getMessage());
    exit(1);
}

// 2. Test Users and Password Hashing
$stmtUsers = $pdo->query("SELECT username, password FROM users");
$users = $stmtUsers->fetchAll(PDO::FETCH_KEY_PAIR);

$adminHash = $pdo->query("SELECT password FROM users WHERE username = 'admin'")->fetchColumn();
$staffHash = $pdo->query("SELECT password FROM users WHERE username = 'cashier1'")->fetchColumn();

assert_test("Admin user exists and password_verify('Admin@123') matches", password_verify('Admin@123', (string)$adminHash));
assert_test("Staff user exists and password_verify('Staff@123') matches", password_verify('Staff@123', (string)$staffHash));

// 3. Test Categories
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
assert_test("Categories exist in database (Found: {$catCount})", $catCount >= 5);

// 4. Test Products and Low Stock Detection
$prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
assert_test("Products catalog populated (Found: {$prodCount})", $prodCount >= 10);

$lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= alert_threshold")->fetchColumn();
assert_test("Low stock threshold query operational (Found: {$lowStockCount} items)", $lowStockCount >= 1);

// 5. Test Helper Functions
assert_test("XSS Escaping Helper e()", e("<script>alert(1)</script>") === "&lt;script&gt;alert(1)&lt;/script&gt;");
assert_test("Currency Formatter format_currency(12.5)", format_currency(12.5) === '$12.50');
assert_test("CSRF Token generation", strlen(csrf_token()) === 64);
assert_test("CSRF Token validation", validate_csrf(csrf_token()) === true);

// 6. Test PDO Transaction & Row Locking (FOR UPDATE)
$txSuccess = false;
try {
    $pdo->beginTransaction();
    $stmtLock = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = 1 FOR UPDATE");
    $stmtLock->execute();
    $p = $stmtLock->fetch();
    if ($p && isset($p['stock_quantity'])) {
        $initialStock = (int)$p['stock_quantity'];
        // Test deduction simulation
        $stmtDed = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - 1 WHERE id = 1");
        $stmtDed->execute();
        
        $newStock = (int)$pdo->query("SELECT stock_quantity FROM products WHERE id = 1")->fetchColumn();
        if ($newStock === $initialStock - 1) {
            $txSuccess = true;
        }
    }
    // Rollback so sample test data is restored!
    $pdo->rollBack();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $txSuccess = false;
}
assert_test("PDO Transaction with SELECT ... FOR UPDATE row locking & rollback", $txSuccess);

// Verify stock was restored by rollback
$restoredStock = (int)$pdo->query("SELECT stock_quantity FROM products WHERE id = 1")->fetchColumn();
assert_test("Rollback verified: original stock quantity intact", $restoredStock === 24);

// 7. Test Sales & Reports Aggregation
$salesCount = (int)$pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
assert_test("Sales transactions table operational (Records: {$salesCount})", $salesCount >= 3);

$totalRev = (float)$pdo->query("SELECT COALESCE(SUM(final_total), 0) FROM sales")->fetchColumn();
assert_test("Revenue aggregation query functioning (Total: \${$totalRev})", $totalRev > 0);

// Summary
echo "\n--------------------------------------------------------\n";
echo " Test Suite Complete: {$testsPassed} / {$testsTotal} passed.\n";
echo "--------------------------------------------------------\n";
