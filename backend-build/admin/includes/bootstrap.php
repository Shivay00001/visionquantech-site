<?php
// ============================================================================
// Admin panel bootstrap — require this FIRST in every admin/*.php page.
// Starts the session and pulls in the API config (DB), auth and layout.
// ============================================================================
declare(strict_types=1);

session_start();

// api/config.php lives one level above the web root's admin/ dir:
//   <webroot>/admin/includes/bootstrap.php  →  <webroot>/api/config.php
require_once dirname(__DIR__, 2) . '/api/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
