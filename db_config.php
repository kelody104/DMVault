<?php
// データベース接続設定
// レンタルサーバーの環境に合わせて適宜変更してください
$host = 'localhost';
$dbname = 'dmvault_db'; // 実際のデータベース名に変更
$user = 'root';         // 実際のユーザー名に変更
$pass = '';             // 実際のパスワードに変更

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // エラー時のレスポンス（本番環境では詳細は隠蔽するのが望ましい）
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
