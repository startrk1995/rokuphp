<?php
/**
 * Shared HTML layout helpers.
 * Bootstrap 5 — mobile-first, replaces jQuery Mobile / RPCL renderer.
 */

function page_header(string $title, bool $show_logout = true): void {
    $logout = $show_logout ? '<a href="/logout.php" class="btn btn-sm btn-outline-light ms-auto">Logout</a>' : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} — RokuPHP</title>
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
  <style>
    body { background: #1a1a2e; color: #e0e0e0; }
    .navbar { background: #16213e !important; }
    .card  { background: #16213e; border: 1px solid #0f3460; color: #e0e0e0; }
    .card-header { background: #0f3460; }
    .btn-primary   { background: #0f3460; border-color: #0f3460; }
    .btn-primary:hover { background: #1a5276; border-color: #1a5276; }
    .btn-danger    { background: #922b21; border-color: #922b21; }
    .list-group-item { background: #16213e; border-color: #0f3460; color: #e0e0e0; }
    .list-group-item:hover { background: #0f3460; }
    .form-control, .form-select {
        background: #0d1117; border-color: #0f3460; color: #e0e0e0;
    }
    .form-control:focus, .form-select:focus {
        background: #0d1117; border-color: #1a5276; color: #e0e0e0;
        box-shadow: 0 0 0 0.2rem rgba(26,82,118,0.4);
    }
    .alert-info    { background: #0f3460; border-color: #1a5276; color: #e0e0e0; }
    .alert-danger  { background: #5b0e0e; border-color: #922b21; color: #f5b7b1; }
    .alert-success { background: #0b5345; border-color: #1e8449; color: #a9dfbf; }
    a { color: #5dade2; }
    a:hover { color: #85c1e9; }
  </style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/menu.php">📹 RokuPHP</a>
    {$logout}
  </div>
</nav>
<div class="container pb-4">
  <h4 class="mb-3">{$title}</h4>
HTML;
}

function page_footer(): void {
    echo <<<HTML
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmq4E1DPNABGGnhGLsrPTRiafDX9"
        crossorigin="anonymous"></script>
</body>
</html>
HTML;
}

function alert(string $msg, string $type = 'info'): void {
    if ($msg === '') return;
    echo "<div class=\"alert alert-{$type} mb-3\">" . htmlspecialchars($msg) . "</div>\n";
}
