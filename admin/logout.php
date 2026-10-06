<?php
require_once __DIR__ . '/../inc/admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    logout();
}
redirect('login.php');
