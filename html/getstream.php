<?php
/**
 * Roku API endpoint — starts ffmpeg HLS stream and returns the .m3u8 URL
 * Called by IP Camera Viewer Pro on Roku.
 * No session authentication (called directly by Roku device).
 *
 * GET params:
 *   cam  — camera name (looks up URL from cameras.xml)
 *   url  — direct RTSP URL override (optional)
 *
 * Returns: /hls/XXXXXXXXXX.m3u8   on success
 *          error                  on failure
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/logger.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');

$cfg = get_streamer_config();

if (empty($cfg)) {
    log_error('getstream: streamer.xml missing or unreadable');
    echo 'error';
    exit;
}

$rtsp  = '';
$sound = 0;
$cam_label = 'unknown';

// Determine RTSP source
if (!empty($_GET['url'])) {
    $rtsp      = urldecode((string)$_GET['url']);
    $sound     = 1;
    $cam_label = 'direct-url';
} elseif (!empty($_GET['cam'])) {
    $cam_name = (string)$_GET['cam'];
    $cam_label = $cam_name;
    $data_path = get_data_path();
    $cameras_file = $data_path . 'cameras.xml';

    if (!file_exists($cameras_file)) {
        log_error("getstream: cameras.xml not found (cam={$cam_name})");
        echo 'error';
        exit;
    }

    $cameras = simplexml_load_file($cameras_file);
    if ($cameras === false) {
        log_error("getstream: failed to parse cameras.xml (cam={$cam_name})");
        echo 'error';
        exit;
    }

    foreach ($cameras->camera as $cam) {
        if (strcasecmp((string)$cam->name, $cam_name) === 0) {
            $rtsp  = urldecode((string)$cam->url);
            $sound = (int)$cam->sound;
            break;
        }
    }

    if ($rtsp === '') {
        log_error("getstream: camera '{$cam_name}' not found or has empty RTSP URL");
        echo 'error';
        exit;
    }
}

if ($rtsp === '') {
    log_error('getstream: no cam or url parameter supplied');
    echo 'error';
    exit;
}

log_info("getstream: request for cam='{$cam_label}' rtsp='{$rtsp}'");

$app             = $cfg['app'];
$disable_audio   = $cfg['disable_audio'];
$infile_options  = $cfg['infile_options'];
$outfile_options = $cfg['outfile_options'];
$outfile_dir     = rtrim($cfg['outfile_dir'], '/') . '/';
$ffmpeg_log      = get_ffmpeg_log();

$rtsp_escaped = escapeshellarg($rtsp);

// If the required stream is already running, return its existing .m3u8 path
$processStr = (string)shell_exec("ps ax 2>/dev/null | grep -v grep | grep ffmpeg | grep {$rtsp_escaped}");
if ($processStr !== '') {
    if (preg_match('#' . preg_quote($outfile_dir, '#') . '(\S+\.m3u8)#', $processStr, $m)) {
        log_info("getstream: reusing existing stream for cam='{$cam_label}': /hls/{$m[1]}");
        echo '/hls/' . $m[1];
        exit;
    }
}

// Kill any stale ffmpeg and clean up old HLS segments from tmpfs
killall_ffmpeg();
foreach (glob($outfile_dir . '*.ts')    ?: [] as $f) { @unlink($f); }
foreach (glob($outfile_dir . '*.m3u8')  ?: [] as $f) { @unlink($f); }

// Build and launch new ffmpeg process
$fprefix = random_string(10);
$fname   = $outfile_dir . $fprefix . '.m3u8';

$cmd  = $app . ' ';
if (!$sound) {
    $cmd .= $disable_audio . ' ';
}
$cmd .= $infile_options . ' ';
$cmd .= $rtsp_escaped . ' ';
$cmd .= $outfile_options . ' ';
$cmd .= escapeshellarg($fname);
$cmd .= ' >> ' . escapeshellarg($ffmpeg_log) . ' 2>&1 &';

log_info("getstream: launching ffmpeg for cam='{$cam_label}': {$cmd}");
shell_exec($cmd);

// Wait up to 20 seconds for the .m3u8 file to appear
$start   = time();
$timeout = false;

while (!file_exists($fname) && !$timeout) {
    $running = (string)shell_exec("ps ax 2>/dev/null | grep -v grep | grep ffmpeg | grep {$rtsp_escaped}");
    if ($running === '') {
        log_error("getstream: ffmpeg exited before producing .m3u8 for cam='{$cam_label}'. Check " . $ffmpeg_log);
        $timeout = true;
    } elseif ((time() - $start) > 20) {
        log_error("getstream: timeout waiting for .m3u8 for cam='{$cam_label}' after 20s");
        $timeout = true;
    } else {
        sleep(1);
    }
}

if (!$timeout && file_exists($fname)) {
    log_info("getstream: stream ready for cam='{$cam_label}': /hls/{$fprefix}.m3u8");
    echo '/hls/' . $fprefix . '.m3u8';
} else {
    echo 'error';
}
