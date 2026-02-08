-- マッチングテーブル: プレイヤーの対戦待ちと部屋の状態を管理
CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(50) UNIQUE NOT NULL,
    -- 部屋の一意識別子
    host_id VARCHAR(50) NOT NULL,
    -- ホスト（待機者）のID
    guest_id VARCHAR(50) DEFAULT NULL,
    -- 参加者のID
    status ENUM('waiting', 'matched', 'finished') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- シグナリングテーブル: WebRTC のハンドシェイクデータを中継
CREATE TABLE IF NOT EXISTS signaling (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(50) NOT NULL,
    from_id VARCHAR(50) NOT NULL,
    -- 送信元ID
    to_id VARCHAR(50) NOT NULL,
    -- 送信先ID
    type ENUM('offer', 'answer', 'candidate') NOT NULL,
    data TEXT NOT NULL,
    -- SDP または ICE Candidate の JSON データ
    is_read BOOLEAN DEFAULT FALSE,
    -- 相手が読み取ったか
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (room_id),
    INDEX (to_id, is_read)
);