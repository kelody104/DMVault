<?php
require_once 'db_config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$playerId = $_GET['player_id'] ?? '';

if (!$playerId) {
    echo json_encode(['success' => false, 'error' => 'Player ID is required']);
    exit;
}

switch ($action) {
    // 待ち受け（部屋作成または待機）
    case 'wait':
        try {
            // 既存の待機中の部屋を探す
            $stmt = $pdo->prepare("SELECT * FROM matches WHERE status = 'waiting' AND host_id != ? LIMIT 1");
            $stmt->execute([$playerId]);
            $room = $stmt->fetch();

            if ($room) {
                // 既存の部屋に参加
                $stmt = $pdo->prepare("UPDATE matches SET guest_id = ?, status = 'matched' WHERE id = ?");
                $stmt->execute([$playerId, $room['id']]);
                echo json_encode(['success' => true, 'room_id' => $room['room_id'], 'role' => 'guest']);
            } else {
                // 自分の部屋が既にあるか確認
                $stmt = $pdo->prepare("SELECT room_id FROM matches WHERE host_id = ? AND status = 'waiting'");
                $stmt->execute([$playerId]);
                $myRoom = $stmt->fetch();

                if ($myRoom) {
                    echo json_encode(['success' => true, 'room_id' => $myRoom['room_id'], 'role' => 'host', 'status' => 'waiting']);
                } else {
                    // 新しく部屋を作成
                    $roomId = uniqid('room_');
                    $stmt = $pdo->prepare("INSERT INTO matches (room_id, host_id, status) VALUES (?, ?, 'waiting')");
                    $stmt->execute([$roomId, $playerId]);
                    echo json_encode(['success' => true, 'room_id' => $roomId, 'role' => 'host', 'status' => 'waiting']);
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // マッチング状態の確認（ホスト用）
    case 'check':
        $roomId = $_GET['room_id'] ?? '';
        if (!$roomId) {
            echo json_encode(['success' => false, 'error' => 'Room ID is required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT status, guest_id FROM matches WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        echo json_encode(['success' => true, 'status' => $room['status'], 'guest_id' => $room['guest_id']]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
