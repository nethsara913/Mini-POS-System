<?php
/**
 * Authentication Module: Login
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

// Redirect if already authenticated
if (is_logged_in()) {
    if (is_admin()) {
        redirect('modules/dashboard/index.php');
    } else {
        redirect('modules/pos/index.php');
    }
}

$errorMessage = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($token)) {
        $errorMessage = 'Invalid or expired session token. Please try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $usernameVal = $username;

        if (empty($username) || empty($password)) {
            $errorMessage = 'Please enter both username and password.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("SELECT id, username, password, full_name, role, is_active FROM users WHERE username = :username LIMIT 1");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password'])) {
                    // Session regeneration against fixation attacks
                    session_regenerate_id(true);

                    $_SESSION['user_id']       = (int)$user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_fullname'] = $user['full_name'];
                    $_SESSION['user_role']     = $user['role'];

                    set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

                    if ($user['role'] === 'admin') {
                        redirect('modules/dashboard/index.php');
                    } else {
                        redirect('modules/pos/index.php');
                    }
                } else {
                    $errorMessage = 'Invalid username or password, or account is disabled.';
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $errorMessage = 'A system error occurred. Please ensure database is configured and try again.';
            }
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center my-4">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card shadow border-0">
            <div class="card-body p-4 p-sm-5">
                <div class="text-center mb-4">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px;">
                        <i class="bi bi-cart4 fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1">LK Super</h3>
                    <p class="text-muted small">Sign in to access POS & Inventory System</p>
                </div>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= e($errorMessage); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('modules/auth/login.php'); ?>" novalidate autocomplete="off">
                    <?= csrf_field(); ?>

                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold small">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?= e($usernameVal); ?>" required autofocus placeholder="e.g. admin">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold small">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </button>
                </form>

               
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
