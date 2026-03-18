<?php
/**
 * Live broadcast to YouTube / Twitch
 * PHP 8.x compatible — replaces RPCL PageLive
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/logger.php';

require_auth();

$msg      = '';
$msg_type = 'info';
$cameras  = get_cameras();
$platforms = get_live_platforms();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save stream key for a platform
    if ($action === 'save_key') {
        $platform = $_POST['platform'] ?? '';
        $key      = $_POST['stream_key'] ?? '';
        if (save_platform_key($platform, $key)) {
            $msg      = 'Stream key saved for ' . htmlspecialchars($platform) . '.';
            $msg_type = 'success';
        } else {
            $msg      = 'Failed to save stream key.';
            $msg_type = 'danger';
        }
        $platforms = get_live_platforms(); // reload
    }

    // Start live broadcast
    if ($action === 'start') {
        $cam_name    = $_POST['camera'] ?? '';
        $platform_nm = $_POST['platform'] ?? '';

        $cam = find_camera($cam_name);
        if (!$cam) {
            $msg = 'Select a camera first.';
            $msg_type = 'danger';
        } else {
            $rtsp    = $cam['url'];   // get_cameras() already url-decodes
            $doaudio = (int)$cam['sound'];
            $cfg     = get_streamer_config();
            $plat    = null;
            foreach (get_live_platforms() as $p) {
                if ($p['name'] === $platform_nm) { $plat = $p; break; }
            }
            if (!$plat) {
                $msg = 'Select a platform first.'; $msg_type = 'danger';
            } elseif (($plat['key'] ?? '') === '') {
                $msg = 'Save a stream key for ' . htmlspecialchars($platform_nm) . ' first.'; $msg_type = 'danger';
            } else {
                // Check if already running
                $check = shell_exec("ps ax | grep -v grep | grep ffmpeg | grep " . escapeshellarg($rtsp));
                if ($check !== null && trim($check) !== '') {
                    $msg = 'Already ON AIR!'; $msg_type = 'info';
                } else {
                    killall_ffmpeg();
                    $ffmpeg  = $cfg['app'] . ' ';
                    if (!$doaudio) $ffmpeg .= $cfg['disable_audio'] . ' ';
                    $ffmpeg .= $plat['infile_options'] . ' ' . escapeshellarg($rtsp) . ' ';
                    $ffmpeg .= $plat['outfile_options'] . escapeshellarg($plat['key']);
                    $ffmpeg .= ' > /dev/null 2>&1 &';
                    shell_exec($ffmpeg);
                    log_info("broadcast: started for cam='{$cam_name}' platform='{$platform_nm}' rtsp='{$rtsp}'");
                    $msg = '🔴 ON AIR! Broadcasting to ' . htmlspecialchars($platform_nm) . '.';
                    $msg_type = 'success';
                }
            }
        }
    }

    // Stop broadcast
    if ($action === 'stop') {
        killall_ffmpeg();
        log_info('broadcast: stopped by user');
        $msg = 'Live stream terminated.'; $msg_type = 'info';
    }
}

page_header('Live Broadcast');
?>

<a href="/menu.php" class="btn btn-sm btn-outline-light mb-3">← Back</a>

<?php if ($msg !== ''): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($cameras)): ?>
  <div class="alert alert-danger">No cameras configured. <a href="/manualm.php">Add one first.</a></div>
<?php else: ?>

<!-- Stream Keys -->
<div class="card mb-3">
  <div class="card-header fw-bold">🔑 Stream Keys</div>
  <div class="card-body">
    <?php foreach ($platforms as $p): ?>
    <form method="post" class="row g-2 align-items-end mb-2">
      <input type="hidden" name="action" value="save_key">
      <input type="hidden" name="platform" value="<?= htmlspecialchars($p['name']) ?>">
      <div class="col-auto fw-bold" style="min-width:90px"><?= htmlspecialchars($p['name']) ?></div>
      <div class="col">
        <input type="text" name="stream_key" class="form-control form-control-sm"
               placeholder="Paste your stream key here"
               value="<?= htmlspecialchars($p['key']) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Save</button>
      </div>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<!-- Start / Stop -->
<div class="card">
  <div class="card-header fw-bold">📡 Start / Stop Broadcast</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <div class="col-12 col-sm-6">
        <label class="form-label">Camera</label>
        <select name="camera" class="form-select">
          <?php foreach ($cameras as $c): ?>
            <option value="<?= htmlspecialchars($c['name']) ?>">
              <?= htmlspecialchars($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-6">
        <label class="form-label">Platform</label>
        <select name="platform" class="form-select">
          <?php foreach ($platforms as $p): ?>
            <option value="<?= htmlspecialchars($p['name']) ?>">
              <?= htmlspecialchars($p['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" name="action" value="start" class="btn btn-danger">
          🔴 Start Broadcast
        </button>
        <button type="submit" name="action" value="stop" class="btn btn-outline-light">
          ⏹ Stop Broadcast
        </button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<?php page_footer(); ?>
