<?php
require_once __DIR__ . '/_auth.php';
ddAdminLogout();
header('Location: login.php');
exit;
