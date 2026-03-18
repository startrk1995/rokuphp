<?php
/**
 * Main menu / camera list
 * PHP 8.x compatible — replaces RPCL PageIndex
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

require_auth();

$cameras = get_cameras();

page_header('Camera Manager');
?>

<div class="row g-3">

  <!-- Camera list -->
  <div class="col-12 col-md-7">
    <div class="card">
      <div class="card-header">📹 Configured Cameras</div>
      <div class="card-body p-0">
        <?php if (empty($cameras)): ?>
          <p class="p-3 mb-0 text-muted">No cameras added yet. Use the options on the right to add one.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($cameras as $cam): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>📷 <?= htmlspecialchars($cam['name']) ?>
                <?php if ($cam['sound']): ?>
                  <span class="badge bg-secondary ms-1">🔊</span>
                <?php endif; ?>
              </span>
              <div class="btn-group btn-group-sm">
                <a href="/manualm.php?edit=<?= urlencode($cam['name']) ?>"
                   class="btn btn-outline-light" title="Edit">✏️</a>
                <a href="/delete.php?id=<?= urlencode($cam['name']) ?>"
                   class="btn btn-outline-danger" title="Delete">🗑️</a>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="col-12 col-md-5">
    <div class="card mb-3">
      <div class="card-header">➕ Add Camera</div>
      <div class="card-body d-grid gap-2">
        <a href="/manualm.php" class="btn btn-primary">
          📡 Add RTSP Camera Manually
        </a>
        <a href="/onvifm.php" class="btn btn-primary">
          🔍 Discover ONVIF Cameras
        </a>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header">📺 Live Broadcast</div>
      <div class="card-body d-grid">
        <a href="/broadcastm.php" class="btn btn-primary">
          🎥 Broadcast to YouTube / Twitch
        </a>
      </div>
    </div>
    <div class="card">
      <div class="card-header">🔍 Diagnostics</div>
      <div class="card-body d-grid">
        <a href="/logs.php" class="btn btn-outline-light">
          📋 View Logs
        </a>
      </div>
    </div>
  </div>

</div>

<div class="mt-3 text-muted small">
  Cameras are automatically imported by <strong>IP Camera Viewer Pro</strong> on Roku.
</div>

<?php page_footer(); ?>
