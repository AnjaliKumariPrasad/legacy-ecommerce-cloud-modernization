<?php
require_once __DIR__ . '/includes/auth.php';
logout();
session_start();
flash('info', 'You have been logged out.');
admin_redirect('login.php');
