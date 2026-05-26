<?php
require_once __DIR__ . '/auth.php';
fazerLogout();
header('Location: ' . BASE_URL . '/index.php');
exit;
