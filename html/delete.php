<?php
/**
 * Delete a camera
 * PHP 8.x compatible — replaces RPCL DeletePage
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

require_auth();

$name = urldecode($_GET['id'] ?? '');

if ($name === '') {
    header('Location: /menu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    delete_camera($name);
    header('Location: /menu.php');
    exit;
}

page_header('Delete Camera');
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-5">
    <div class="card border-danger">
      <div class="card-header text-danger fw-bold">🗑️ Confirm Delete</div>
      <div class="card-body">
        <p>Are you sure you want to delete camera
          <strong><?= htmlspecialchars($name) ?></strong>?
        </p>
        <form method="post" action="/delete.php?id=<?= urlencode($name) ?>">
          <input type="hidden" name="confirm" value="1">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger">Yes, Delete</button>
            <a href="/menu.php" class="btn btn-outline-light">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php page_footer(); ?>
