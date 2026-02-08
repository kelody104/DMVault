<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// メッセージの送信 (POST)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $roomId = $input['room_id'] ?? '';
    $fromId = $input['from_id'] ?? '';
    $toId = $input['to_id'] ?? '';
    $type = $input['type'] ?? '';
    $data = $input['data'] ?? '';

    if (!$roomId || !$fromId || !$toId || !$type || !$data) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO signaling (room_id, from_id, to_id, type, data) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$roomId, $fromId, $toId, $type, is_string($data) ? $data : json_encode($data)]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} 
// メッセージの受信 (GET)
else if ($method === 'GET') {
    $playerId = $_GET['player_id'] ?? '';
    
    if (!$playerId) {
        echo json_encode(['success' => false, 'error' => 'Player ID is required']);
        exit;
    }

    try {
        // 自分宛ての未読メッセージを取得
        $stmt = $pdo->prepare("SELECT * FROM signaling WHERE to_id = ? AND is_read = FALSE ORDER BY created_at ASC");
        $stmt->execute([$playerId]);
        $messages = $stmt->fetchAll();

        if ($messages) {
            // 受信したメッセージを既読にする
            $ids = array_column($messages, 'id');
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("UPDATE signaling SET is_read = TRUE WHERE id IN ($placeholders)");
            $stmt->execute($ids);
        }

        echo json_encode(['success' => true, 'messages' => $messages]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
