<?php
/**
 * Roku API endpoint — returns camera list as XML items
 * Called by IP Camera Viewer Pro on Roku.
 * No authentication required (Roku can't do session auth).
 * Compatible with original API.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');

$path = get_data_path();
$response = '';

if (file_exists($path . 'cameras.xml')) {
    $cameras = simplexml_load_file($path . 'cameras.xml');
    if ($cameras !== false) {
        foreach ($cameras->camera as $cam) {
            $title       = htmlspecialchars((string)$cam->name, ENT_XML1);
            $snapshoturl = htmlspecialchars(urldecode((string)$cam->snapshoturl), ENT_XML1);
            $response   .= "<item title=\"{$title}\" command=\"hls\" description=\"HLS (Live Stream)\" getpicture=\"{$snapshoturl}\" />";
        }
    }
}

echo $response;
