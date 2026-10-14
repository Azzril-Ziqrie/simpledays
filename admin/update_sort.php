<?php
require_once __DIR__ . '/_guard.php';
// Sesuaikan path config database kamu
require_once __DIR__ . '/../config/database.php';

// Set header response ke JSON
header('Content-Type: application/json');

// Ambil data JSON yang dikirim dari JavaScript
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (isset($data['order']) && is_array($data['order'])) {
    try {
        // Gunakan transaction agar jika ada error, database tidak jadi diupdate setengah-setengah
        $pdo->beginTransaction();
        
        // Siapkan query update
        $stmt = $pdo->prepare("UPDATE products SET sort_order = :sort_order WHERE id = :id");

        // Looping data urutan baru. Index 0 jadi urutan 1, index 1 jadi urutan 2, dst.
        foreach ($data['order'] as $index => $id) {
            $stmt->execute([
                ':sort_order' => $index + 1,
                ':id'         => (int) $id
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[admin] save product order failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save the new order.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
}
