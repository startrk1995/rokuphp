<?php
/**
 * Simple file logger for rokuphp.
 * Writes timestamped entries to data/app.log
 * ffmpeg output goes to data/ffmpeg.log
 */

function get_app_log(): string {
    return get_data_path() . 'app.log';
}

function get_ffmpeg_log(): string {
    return get_data_path() . 'ffmpeg.log';
}

function app_log(string $level, string $message): void {
    $logfile = get_app_log();
    $dir = dirname($logfile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = date('Y-m-d H:i:s') . ' [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
}

function log_info(string $msg): void  { app_log('INFO',  $msg); }
function log_error(string $msg): void { app_log('ERROR', $msg); }
function log_warn(string $msg): void  { app_log('WARN',  $msg); }
