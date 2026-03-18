<?php
/**
 * Login / Create-user page
 * PHP 8.x compatible — replaces RPCL PageLogin
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

session_init();

// Already logged in? go to menu
if (is_logged_in()) {
    header('Location: /menu.php');
    exit;
}

$msg   = '';
$mode  = user_exists() ? 'login' : 'create';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'create') {
        $u  = trim($_POST['username'] ?? '');
        $p  = $_POST['password'] ?? '';
        $p2 = $_POST['password2'] ?? '';
        if ($u === '') {
            $msg = 'Username is required.';
        } elseif ($p === '') {
            $msg = 'Password is required.';
        } elseif ($p !== $p2) {
            $msg = 'Passwords do not match.';
        } else {
            create_user($u, $p);
            // Auto-login after creating user
            login($u, $p);
            header('Location: /menu.php');
            exit;
        }
    } else {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        if (!login($u, $p)) {
            $msg = 'Invalid username or password.';
        } else {
            header('Location: /menu.php');
            exit;
        }
    }
}

page_header($mode === 'create' ? 'Create Admin User' : 'Login', false);
?>

<div class="row justify-content-center">
  <div class="col-12 col-sm-8 col-md-5">
    <div class="card shadow">
      <div class="card-header text-center fw-bold">
        <?= $mode === 'create' ? '🔐 Create Admin User' : '🔑 Login' ?>
      </div>
      <div class="card-body">

        <?php alert($msg, 'danger'); ?>

        <?php if ($mode === 'create'): ?>
        <p class="text-muted small">No user exists yet. Create an admin account to get started.</p>
        <?php endif; ?>

        <form method="post" action="/index.php">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control"
                   autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   autocomplete="<?= $mode === 'create' ? 'new-password' : 'current-password' ?>" required>
          </div>
          <?php if ($mode === 'create'): ?>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password2" class="form-control"
                   autocomplete="new-password" required>
          </div>
          <?php endif; ?>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary">
              <?= $mode === 'create' ? 'Create User & Login' : 'Login' ?>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<?php page_footer(); ?>
