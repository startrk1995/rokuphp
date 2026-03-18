<?php
/**
 * Add / Edit RTSP camera manually
 * PHP 8.x compatible — replaces RPCL PageManual
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/logger.php';

require_auth();

$msg        = '';
$msg_type   = 'info';
$edit_name  = urldecode($_GET['edit'] ?? '');
$is_edit    = $edit_name !== '';

// Pre-fill fields when editing
$name     = '';
$rtsp     = 'rtsp://';
$snapshot = '';
$sound    = 0;

if ($is_edit) {
    $existing = find_camera($edit_name);
    if ($existing) {
        $name     = $existing['name'];
        $rtsp     = $existing['url'];
        $snapshot = $existing['snapshoturl'];
        $sound    = $existing['sound'];
    }
}

/**
 * Validate a URL, explicitly allowing rtsp:// and rtsps:// schemes
 * in addition to http/https, since filter_var rejects rtsp on some platforms.
 */
function validate_stream_url(string $url): bool {
    if (preg_match('#^rtsp[s]?://.+#i', $url)) {
        return true;
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $rtsp     = trim($_POST['rtsp'] ?? '');
    $snapshot = trim($_POST['snapshot'] ?? '');
    $sound    = isset($_POST['sound']) ? 1 : 0;

    // Validation
    if ($name === '') {
        $msg = 'Camera name is required and must be unique.';
    } elseif ($rtsp === '' || $rtsp === 'rtsp://') {
        $msg = 'RTSP address is required.';
    } elseif (!validate_stream_url($rtsp)) {
        $msg = 'RTSP address is not a valid URL (expected rtsp://host/path).';
    } elseif ($snapshot !== '' && filter_var($snapshot, FILTER_VALIDATE_URL) === false) {
        $msg = 'Snapshot URL is not valid.';
    } else {
        // Retrieve existing encrypted creds if editing (keep them unchanged)
        $profileToken = '';
        $login        = '';
        $password     = '';
        $mediaurl     = '';
        if ($is_edit) {
            $existing = find_camera($edit_name);
            if ($existing) {
                $profileToken = $existing['profileToken'];
                $login        = $existing['login'];
                $password     = do_decrypt(urldecode($existing['password']));
                $mediaurl     = $existing['mediaurl'];
            }
        }

        upsert_camera([
            'name'         => $name,
            'url'          => $rtsp,
            'snapshoturl'  => $snapshot,
            'sound'        => $sound,
            'profileToken' => $profileToken,
            'login'        => $login,
            'password'     => $password,
            'mediaurl'     => $mediaurl,
        ]);

        $action = $is_edit ? 'updated' : 'added';
        log_info("Camera '{$name}' {$action} via web UI. RTSP: {$rtsp}");

        $msg      = 'Camera "' . htmlspecialchars($name) . '" saved.';
        $msg_type = 'success';
    }
}

page_header($is_edit ? 'Edit Camera' : 'Add RTSP Camera');
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-7">

    <a href="/menu.php" class="btn btn-sm btn-outline-light mb-3">← Back</a>

    <div class="card">
      <div class="card-header fw-bold">
        <?= $is_edit ? '✏️ Edit Camera: ' . htmlspecialchars($edit_name) : '📡 Add RTSP Camera' ?>
      </div>
      <div class="card-body">

        <?php if ($msg !== ''): ?>
          <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
        <?php endif; ?>

        <form method="post" action="/manualm.php<?= $is_edit ? '?edit=' . urlencode($edit_name) : '' ?>">

          <div class="mb-3">
            <label class="form-label">Camera Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control"
                   value="<?= htmlspecialchars($name) ?>"
                   <?= $is_edit ? 'readonly' : '' ?> required>
            <?php if (!$is_edit): ?>
              <div class="form-text text-muted">Must be unique.</div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label">RTSP URL <span class="text-danger">*</span></label>
            <!-- type="text" is intentional — browsers reject rtsp:// for type="url" -->
            <input type="text" name="rtsp" class="form-control"
                   value="<?= htmlspecialchars($rtsp) ?>"
                   placeholder="rtsp://camera-ip/stream" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Snapshot URL <span class="text-muted">(optional)</span></label>
            <input type="url" name="snapshot" class="form-control"
                   value="<?= htmlspecialchars($snapshot) ?>"
                   placeholder="http://camera-ip/snapshot.jpg">
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" name="sound" class="form-check-input" id="chkSound"
                   <?= $sound ? 'checked' : '' ?>>
            <label class="form-check-label" for="chkSound">Enable Audio 🔊</label>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <?= $is_edit ? 'Save Changes' : 'Add Camera' ?>
            </button>
            <a href="/menu.php" class="btn btn-outline-light">Cancel</a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php page_footer(); ?>
