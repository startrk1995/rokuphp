<?php
/**
 * Log viewer — shows app.log and ffmpeg.log
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/logger.php';

require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'clear_app') {
    file_put_contents(get_app_log(), '');
} elseif ($action === 'clear_ffmpeg') {
    file_put_contents(get_ffmpeg_log(), '');
}

function read_log_tail(string $path, int $lines = 100): string {
    if (!file_exists($path)) return '(no log file yet)';
    $content = file_get_contents($path);
    if ($content === false || $content === '') return '(empty)';
    $all = explode("\n", trim($content));
    return implode("\n", array_slice($all, -$lines));
}

$app_log    = read_log_tail(get_app_log());
$ffmpeg_log = read_log_tail(get_ffmpeg_log(), 200);

page_header('Logs');
?>

<a href="/menu.php" class="btn btn-sm btn-outline-light mb-3">← Back</a>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center fw-bold">
    <span>📋 Application Log <small class="text-muted fw-normal">(last 100 lines — <?= htmlspecialchars(get_app_log()) ?>)</small></span>
    <form method="post" class="d-inline">
      <input type="hidden" name="action" value="clear_app">
      <button type="submit" class="btn btn-sm btn-outline-danger"
              onclick="return confirm('Clear app log?')">Clear</button>
    </form>
  </div>
  <div class="card-body p-0">
    <pre class="p-3 mb-0" style="background:#0d1117;color:#b0e0b0;font-size:0.8rem;max-height:350px;overflow-y:auto;white-space:pre-wrap"><?= htmlspecialchars($app_log) ?></pre>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center fw-bold">
    <span>🎬 FFmpeg Log <small class="text-muted fw-normal">(last 200 lines — <?= htmlspecialchars(get_ffmpeg_log()) ?>)</small></span>
    <form method="post" class="d-inline">
      <input type="hidden" name="action" value="clear_ffmpeg">
      <button type="submit" class="btn btn-sm btn-outline-danger"
              onclick="return confirm('Clear ffmpeg log?')">Clear</button>
    </form>
  </div>
  <div class="card-body p-0">
    <pre class="p-3 mb-0" style="background:#0d1117;color:#e0c0b0;font-size:0.8rem;max-height:400px;overflow-y:auto;white-space:pre-wrap"><?= htmlspecialchars($ffmpeg_log) ?></pre>
  </div>
</div>

<script>
// Auto-scroll log panes to bottom
document.querySelectorAll('pre').forEach(el => el.scrollTop = el.scrollHeight);
</script>

<?php page_footer(); ?>
