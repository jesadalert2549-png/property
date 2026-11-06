<?php
require_once __DIR__ . '/../includes/staff.php';
start_session();

if (!empty($_SESSION['staff_id'])) {
    staff_log(db(), (int)$_SESSION['staff_id'], (string)($_SESSION['staff_username'] ?? ''), 'staff_logout');
}

unset(
    $_SESSION['staff_id'],
    $_SESSION['staff_username'],
    $_SESSION['staff_name'],
    $_SESSION['staff_role'],
    $_SESSION['staff_role_slug']
);

redirect('login.php');
