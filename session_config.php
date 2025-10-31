<?php
// Session configuration for all pages
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_name('VetCareQR_Session');
session_start();
?>