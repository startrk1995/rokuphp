<?php
/**
 * ONVIF camera discovery and add
 * PHP 8.x compatible — replaces RPCL OnvifPage
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/lib/class.ponvif.php';

require_auth();

$msg      = '';
$msg_type = 'info';
$found    = [];
$profiles = [];

// ── AJAX / POST actions ────────────────────────────────────────────────────

// 1) Scan for ONVIF devices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'scan') {
    $onvif   = new Ponvif();
    $result  = $onvif->discover();
    $camarray = [];
    if (count($result) > 0) {
        foreach ($result as $r) {
            $pieces = explode(' ', $r['XAddrs']);
            foreach ($pieces as $murl) {
                if (strpos($murl, $r['IPAddr']) !== false) {
                    $camarray[$r['IPAddr']] = $murl;
                }
            }
            $found[] = $r['IPAddr'];
        }
        $path = get_data_path();
        if (!is_dir($path)) mkdir($path, 0775, true);
        file_put_contents($path . 'temp.json', json_encode($camarray));
    }
    header('Content-Type: application/json');
    echo json_encode(['cameras' => $found]);
    exit;
}

// 2) Get H264 profiles for a selected IP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profiles') {
    $ip       = $_POST['ip'] ?? '';
    $login    = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    $path     = get_data_path();
    $camarray = json_decode((string)file_get_contents($path . 'temp.json'), true) ?? [];
    try {
        $onvif = new Ponvif();
        $onvif->setUsername($login);
        $onvif->setPassword($password);
        $onvif->setIPAddress($ip);
        $onvif->setMediaUri($camarray[$ip] ?? '');
        $onvif->initialize();
        $sources = $onvif->getSources();
        $tokens  = [];
        if (count($sources[0] ?? []) > 0) {
            foreach ($sources[0] as $s) {
                if ($s['encoding'] === 'H264') {
                    $tokens[] = $s['profiletoken'];
                }
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['profiles' => $tokens]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cannot connect — check ONVIF login/password.']);
    }
    exit;
}

// 3) Add an ONVIF camera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $ip           = $_POST['ip'] ?? '';
    $login_u      = $_POST['login'] ?? '';
    $password_u   = $_POST['password'] ?? '';
    $cam_name     = trim($_POST['cam_name'] ?? '');
    $profile_tok  = $_POST['profile'] ?? '';
    $do_audio     = isset($_POST['sound']) ? 1 : 0;
    $path         = get_data_path();

    if ($cam_name === '') {
        $msg = 'Camera name is required.';
        $msg_type = 'danger';
    } else {
        $existing = find_camera($cam_name);
        if ($existing) {
            $msg = 'Camera name must be unique.';
            $msg_type = 'danger';
        } else {
            $camarray = json_decode((string)file_get_contents($path . 'temp.json'), true) ?? [];
            try {
                $onvif = new Ponvif();
                $onvif->setUsername($login_u);
                $onvif->setPassword($password_u);
                $onvif->setIPAddress($ip);
                $onvif->setMediaUri($camarray[$ip] ?? '');
                $onvif->initialize();
                $streamUri   = $onvif->media_GetStreamUri($profile_tok);
                $snapshotUri = $onvif->media_GetSnapshotUri($profile_tok);

                // Embed credentials into URI
                if ($login_u !== '' && $password_u !== '') {
                    $auth = $login_u . ':' . $password_u . '@' . $ip;
                } elseif ($login_u !== '') {
                    $auth = $login_u . '@' . $ip;
                } else {
                    $auth = $ip;
                }
                $streamUri   = str_replace($ip, $auth, $streamUri);
                $snapshotUri = str_replace($ip, $auth, $snapshotUri);

                upsert_camera([
                    'name'         => $cam_name,
                    'url'          => $streamUri,
                    'snapshoturl'  => $snapshotUri,
                    'sound'        => $do_audio,
                    'profileToken' => $profile_tok,
                    'login'        => $login_u,
                    'password'     => $password_u,
                    'mediaurl'     => $camarray[$ip] ?? '',
                ]);

                $msg      = 'Camera "' . htmlspecialchars($cam_name) . '" added successfully.';
                $msg_type = 'success';
            } catch (Exception $e) {
                $msg      = 'Failed to add camera: ' . $e->getMessage();
                $msg_type = 'danger';
            }
        }
    }
}

page_header('Discover ONVIF Cameras');
?>

<a href="/menu.php" class="btn btn-sm btn-outline-light mb-3">← Back</a>

<?php if ($msg !== ''): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-header fw-bold">🔍 Step 1 — Scan Network</div>
  <div class="card-body">
    <p class="text-muted small">Scans the local network for ONVIF-compliant cameras (WS-Discovery).</p>
    <button id="btnScan" class="btn btn-primary" onclick="scanOnvif()">Scan for Cameras</button>
    <div id="scanResult" class="mt-3"></div>
  </div>
</div>

<div class="card mb-3" id="step2" style="display:none">
  <div class="card-header fw-bold">🔑 Step 2 — Enter Credentials & Get Profiles</div>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">Camera IP</label>
      <select id="selIP" class="form-select"></select>
    </div>
    <div class="row g-2 mb-3">
      <div class="col">
        <label class="form-label">ONVIF Username</label>
        <input type="text" id="onvifUser" class="form-control" placeholder="admin">
      </div>
      <div class="col">
        <label class="form-label">ONVIF Password</label>
        <input type="password" id="onvifPass" class="form-control">
      </div>
    </div>
    <button class="btn btn-primary" onclick="getProfiles()">Get H.264 Profiles</button>
    <div id="profileResult" class="mt-3"></div>
  </div>
</div>

<div class="card" id="step3" style="display:none">
  <div class="card-header fw-bold">➕ Step 3 — Add Camera</div>
  <div class="card-body">
    <form method="post" action="/onvifm.php">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="ip" id="addIP">
      <input type="hidden" name="login" id="addLogin">
      <input type="hidden" name="password" id="addPassword">

      <div class="mb-3">
        <label class="form-label">Camera Name <span class="text-danger">*</span></label>
        <input type="text" name="cam_name" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">H.264 Profile</label>
        <select name="profile" id="selProfile" class="form-select"></select>
      </div>

      <div class="mb-3 form-check">
        <input type="checkbox" name="sound" class="form-check-input" id="chkSound">
        <label class="form-check-label" for="chkSound">Enable Audio 🔊</label>
      </div>

      <button type="submit" class="btn btn-primary">Add Camera</button>
      <a href="/menu.php" class="btn btn-outline-light ms-2">Cancel</a>
    </form>
  </div>
</div>

<script>
function scanOnvif() {
  const btn = document.getElementById('btnScan');
  btn.disabled = true;
  btn.textContent = 'Scanning…';
  const res = document.getElementById('scanResult');
  res.innerHTML = '<div class="spinner-border spinner-border-sm text-light" role="status"></div> Scanning (may take ~5 seconds)…';

  fetch('/onvifm.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=scan'
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.textContent = 'Scan for Cameras';
    if (data.cameras && data.cameras.length > 0) {
      const sel = document.getElementById('selIP');
      sel.innerHTML = '';
      data.cameras.forEach(ip => {
        const opt = document.createElement('option');
        opt.value = ip;
        opt.textContent = ip;
        sel.appendChild(opt);
      });
      document.getElementById('step2').style.display = 'block';
      res.innerHTML = '<div class="alert alert-success">Found ' + data.cameras.length + ' camera(s).</div>';
    } else {
      res.innerHTML = '<div class="alert alert-danger">No ONVIF cameras found on the network.</div>';
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.textContent = 'Scan for Cameras';
    res.innerHTML = '<div class="alert alert-danger">Scan failed. Check server logs.</div>';
  });
}

function getProfiles() {
  const ip   = document.getElementById('selIP').value;
  const user = document.getElementById('onvifUser').value;
  const pass = document.getElementById('onvifPass').value;
  const res  = document.getElementById('profileResult');
  res.innerHTML = '<div class="spinner-border spinner-border-sm text-light" role="status"></div> Loading profiles…';

  fetch('/onvifm.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=profiles&ip=' + encodeURIComponent(ip)
        + '&login=' + encodeURIComponent(user)
        + '&password=' + encodeURIComponent(pass)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      res.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
      return;
    }
    if (data.profiles && data.profiles.length > 0) {
      const sel = document.getElementById('selProfile');
      sel.innerHTML = '';
      data.profiles.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p;
        opt.textContent = p;
        sel.appendChild(opt);
      });
      // Pass values to the add form
      document.getElementById('addIP').value       = ip;
      document.getElementById('addLogin').value    = user;
      document.getElementById('addPassword').value = pass;
      document.getElementById('step3').style.display = 'block';
      res.innerHTML = '<div class="alert alert-success">Found ' + data.profiles.length + ' H.264 profile(s).</div>';
    } else {
      res.innerHTML = '<div class="alert alert-danger">No H.264 profiles found. Try different credentials.</div>';
    }
  })
  .catch(() => {
    res.innerHTML = '<div class="alert alert-danger">Failed to load profiles.</div>';
  });
}
</script>

<?php page_footer(); ?>
