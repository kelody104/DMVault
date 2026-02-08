<?php
require_once 'skyway_config.php';

/**
 * SkyWay Auth Token (JWT) 生成 API
 */

header('Content-Type: application/json');

if (SKYWAY_SECRET_KEY === 'YOUR_SECRET_KEY_HERE') {
    echo json_encode(['error' => 'Secret Key が設定されていません。skyway_config.php を編集してください。']);
    exit;
}

$roomName = isset($_GET['roomName']) ? $_GET['roomName'] : '*';

try {
    $token = generateSkyWayToken(SKYWAY_APP_ID, SKYWAY_SECRET_KEY, $roomName);
    echo json_encode(['authToken' => $token]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * JWT を生成する関数
 */
function generateSkyWayToken($appId, $secretKey, $roomName) {
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    $now = time();
    $payload = [
        'jti' => bin2hex(random_bytes(16)),
        'iat' => $now,
        'exp' => $now + 3600, // 1時間有効
        'version' => 3,
        'scope' => [
            'appId' => $appId,
            'analytics' => ['enabled' => true],
            'turn' => ['enabled' => true],
            'rooms' => [
                [
                    'name' => $roomName,
                    'methods' => ['create', 'close', 'updateMetadata'],
                    'member' => [
                        'methods' => ['publish', 'subscribe', 'updateMetadata']
                    ],
                    'sfu' => ['enabled' => true]
                ]
            ]
        ]
    ];

    $base64UrlHeader = base64UrlEncode(json_encode($header));
    $base64UrlPayload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secretKey, true);
    $base64UrlSignature = base64UrlEncode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}
