<?php
/**
 * Penjaga halaman admin: include di baris pertama setiap file admin/*.php.
 * Belum login → alihkan ke login.php (atau balas 401 JSON untuk request AJAX).
 */
require_once __DIR__ . '/_auth.php';

if (!ddAdminIsLoggedIn()) {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
    $isAjax = stripos($accept, 'json') !== false || stripos($ctype, 'json') !== false
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $next = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        ? $self . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')
        : '';
    header('Location: login.php' . ($next !== '' ? '?next=' . urlencode($next) : ''));
    exit;
}

// Halaman admin jangan di-cache browser/proxy
header('Cache-Control: no-store');
