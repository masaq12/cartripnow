<?php
require_once 'config/config.php';

// Destroy session and redirect to home
session_destroy();
redirect(SITE_URL . '/index.php');
?>
