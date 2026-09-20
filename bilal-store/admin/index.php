<?php
require_once __DIR__ . '/includes/auth.php';
admin_redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
