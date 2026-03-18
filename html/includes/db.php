<?php
/**
 * Camera data access helpers for rokuphp
 * PHP 8.x compatible — replaces RPCL DataModule
 */

require_once __DIR__ . '/auth.php';

/**
 * Load all cameras from cameras.xml.
 * Returns array of associative arrays.
 */
function get_cameras(): array {
    $path = get_data_path();
    $file = $path . 'cameras.xml';
    if (!file_exists($file)) {
        return [];
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        return [];
    }
    $result = [];
    foreach ($xml->camera as $cam) {
        $result[] = [
            'name'         => (string)$cam->name,
            'url'          => urldecode((string)$cam->url),
            'snapshoturl'  => urldecode((string)$cam->snapshoturl),
            'sound'        => (int)$cam->sound,
            'profileToken' => (string)$cam->profileToken,
            'login'        => (string)$cam->login,
            'password'     => urldecode((string)$cam->password),   // still encrypted
            'mediaurl'     => urldecode((string)$cam->mediaurl),
        ];
    }
    return $result;
}

/**
 * Find a single camera by name (case-insensitive). Returns null if not found.
 */
function find_camera(string $name): ?array {
    foreach (get_cameras() as $cam) {
        if (strcasecmp($cam['name'], $name) === 0) {
            return $cam;
        }
    }
    return null;
}

/**
 * Save the full cameras list to cameras.xml.
 * Each element must have: name, url, snapshoturl, sound, profileToken, login, password, mediaurl.
 * url and snapshoturl are expected already URL-encoded.
 */
function save_cameras(array $cameras): bool {
    $path = get_data_path();
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
    $xml = new SimpleXMLElement('<xml/>');
    foreach ($cameras as $c) {
        $cam = $xml->addChild('camera');
        // All text values must be XML-escaped before addChild to prevent
        // "unterminated entity reference" warnings on values containing &
        $x = fn(string $v) => htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $cam->addChild('name',         $x($c['name']));
        $cam->addChild('url',          $x($c['url']));
        $cam->addChild('snapshoturl',  $x($c['snapshoturl']));
        $cam->addChild('sound',        (string)(int)$c['sound']);
        $cam->addChild('profileToken', $x($c['profileToken']));
        $cam->addChild('login',        $x($c['login']));
        $cam->addChild('password',     $x($c['password']));
        $cam->addChild('mediaurl',     $x($c['mediaurl']));
    }
    return $xml->saveXML($path . 'cameras.xml') !== false;
}

/**
 * Add or update a camera by name. If a camera with the same name exists it is replaced.
 * $data keys: name, url (decoded), snapshoturl (decoded), sound (0/1),
 *             profileToken, login, password (plaintext — will be encrypted),
 *             mediaurl (decoded)
 */
function upsert_camera(array $data): bool {
    $cameras  = get_cameras();
    $filtered = array_filter($cameras, fn($c) => strcasecmp($c['name'], $data['name']) !== 0);
    $filtered = array_values($filtered);

    $filtered[] = [
        'name'         => $data['name'],
        'url'          => urlencode($data['url']),
        'snapshoturl'  => urlencode($data['snapshoturl'] ?? ''),
        'sound'        => (int)($data['sound'] ?? 0),
        'profileToken' => $data['profileToken'] ?? '',
        'login'        => $data['login'] ?? '',
        'password'     => urlencode(do_encrypt($data['password'] ?? '')),
        'mediaurl'     => urlencode($data['mediaurl'] ?? ''),
    ];

    return save_cameras($filtered);
}

/**
 * Delete a camera by name (case-insensitive).
 */
function delete_camera(string $name): bool {
    $cameras  = get_cameras();
    $filtered = array_values(array_filter(
        $cameras,
        fn($c) => strcasecmp($c['name'], $name) !== 0
    ));
    return save_cameras($filtered);
}

/**
 * Load streamer config (ffmpeg options) from streamer.xml.
 */
function get_streamer_config(): array {
    $file = get_config_path() . 'streamer.xml';
    if (!file_exists($file)) {
        return [];
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        return [];
    }
    return [
        'app'             => (string)$xml->app->name,
        'disable_audio'   => (string)$xml->audio->disable,
        'infile_options'  => (string)$xml->hls->infile_options,
        'outfile_options' => (string)$xml->hls->outfile_options,
        'outfile_dir'     => (string)$xml->hls->outfile_dir,
    ];
}

/**
 * Load live-streaming platforms from streamer.xml.
 * Returns array of ['name'=>..., 'infile_options'=>..., 'outfile_options'=>..., 'key'=>...]
 */
function get_live_platforms(): array {
    $file = get_config_path() . 'streamer.xml';
    if (!file_exists($file)) {
        return [];
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        return [];
    }
    $result = [];
    foreach ($xml->live->platform as $p) {
        $result[] = [
            'name'            => (string)$p->name,
            'infile_options'  => (string)$p->infile_options,
            'outfile_options' => (string)$p->outfile_options,
            'key'             => do_decrypt(urldecode((string)$p->key)),
        ];
    }
    return $result;
}

/**
 * Save a stream key for a live platform.
 */
function save_platform_key(string $platform_name, string $key): bool {
    $file = get_config_path() . 'streamer.xml';
    if (!file_exists($file)) {
        return false;
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        return false;
    }
    foreach ($xml->live->platform as $p) {
        if ((string)$p->name === $platform_name) {
            unset($p->key);
            $p->addChild('key', urlencode(do_encrypt($key)));
            break;
        }
    }
    return $xml->saveXML($file) !== false;
}

/**
 * Kill all ffmpeg processes owned by the current user (www-data).
 * Uses shell kill to avoid requiring the posix PHP extension.
 */
function killall_ffmpeg(): void {
    exec("ps ax | grep -v grep | grep ffmpeg | awk '{print $1}'", $pids);
    foreach ($pids as $pid) {
        $pid = trim($pid);
        if (preg_match('/^\d+$/', $pid)) {
            shell_exec('kill ' . escapeshellarg($pid) . ' 2>/dev/null');
        }
    }
}

/**
 * Generate a random alphanumeric string.
 */
function random_string(int $length = 10): string {
    $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $result = '';
    $max    = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, $max)];
    }
    return $result;
}
