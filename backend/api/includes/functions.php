<?php
/**
 * 通用函数库
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

/**
 * JSON响应
 */
function jsonResponse($code, $message, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    $response = [
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 成功响应
 */
function success($data = null, $message = '操作成功') {
    jsonResponse(200, $message, $data);
}

/**
 * 错误响应
 */
function error($message = '操作失败', $code = 400, $data = null) {
    jsonResponse($code, $message, $data);
}

/**
 * 获取请求参数
 */
function getParam($key, $default = null) {
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input[$key])) {
        return $input[$key];
    }
    return $default;
}

/**
 * 获取所有请求参数
 */
function getParams() {
    $params = array_merge($_GET, $_POST);
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $params = array_merge($params, $input);
    }
    return $params;
}

/**
 * JWT Token 生成
 */
function generateToken($userId, $userType = 1) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $time = time();
    $payload = json_encode([
        'iss' => 'fazhiyun',
        'iat' => $time,
        'exp' => $time + JWT_EXPIRE,
        'sub' => $userId,
        'type' => $userType
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

/**
 * JWT Token 验证
 */
function verifyToken($token) {
    if (empty($token)) {
        return false;
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[2]));
    
    $expectedSignature = hash_hmac('sha256', $parts[0] . "." . $parts[1], JWT_SECRET, true);
    
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }
    
    $payloadData = json_decode($payload, true);
    if (!$payloadData || $payloadData['exp'] < time()) {
        return false;
    }
    
    return $payloadData;
}

/**
 * 获取当前登录用户
 */
function getCurrentUser() {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
    
    if (empty($token)) {
        $token = getParam('token');
    }
    
    $payload = verifyToken($token);
    if (!$payload) {
        return null;
    }
    
    $db = Database::getInstance();
    $user = $db->query("SELECT * FROM users WHERE id = ? AND status = 1", [$payload['sub']])->fetch();
    
    return $user;
}

/**
 * 验证登录
 */
function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        error('请先登录', 401);
    }
    return $user;
}

/**
 * 验证管理员权限
 */
function requireAdmin() {
    $user = requireAuth();
    if ($user['user_type'] != 4) {
        error('无权访问', 403);
    }
    return $user;
}

/**
 * 密码哈希
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * 验证密码
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 生成随机字符串
 */
function randomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * 生成订单号
 */
function generateOrderNo() {
    return date('YmdHis') . substr(microtime(), 2, 6) . rand(1000, 9999);
}

/**
 * 分页处理
 */
function paginate($page, $pageSize) {
    $page = max(1, intval($page));
    $pageSize = min(MAX_PAGE_SIZE, max(1, intval($pageSize ?: PAGE_SIZE)));
    $offset = ($page - 1) * $pageSize;
    return [$page, $pageSize, $offset];
}

/**
 * 上传文件
 */
function uploadFile($file, $dir = 'files') {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => '上传失败'];
    }
    
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain' => 'txt'
    ];
    
    $mimeType = mime_content_type($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType])) {
        return ['success' => false, 'message' => '不支持的文件类型'];
    }
    
    $maxSize = 100 * 1024 * 1024; // 100MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => '文件大小超过限制'];
    }
    
    $uploadDir = UPLOAD_PATH . $dir . '/' . date('Y/m/d') . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = randomString(16) . '.' . $allowedTypes[$mimeType];
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $url = UPLOAD_URL . $dir . '/' . date('Y/m/d') . '/' . $filename;
        return [
            'success' => true,
            'path' => $filepath,
            'url' => $url,
            'filename' => $file['name'],
            'size' => $file['size']
        ];
    }
    
    return ['success' => false, 'message' => '保存文件失败'];
}

/**
 * 发送HTTP请求
 */
function httpRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    return ['success' => true, 'data' => $response];
}

/**
 * 记录操作日志
 */
function logOperation($userId, $action, $module, $description = '', $requestData = null, $responseData = null) {
    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $db->query(
            "INSERT INTO operation_logs (user_id, action, module, description, ip_address, user_agent, request_data, response_data) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId, $action, $module, $description, $ip, $userAgent,
                $requestData ? json_encode($requestData) : null,
                $responseData ? json_encode($responseData) : null
            ]
        );
    } catch (Exception $e) {
        error_log("记录日志失败: " . $e->getMessage());
    }
}

/**
 * 过滤XSS
 */
function xssClean($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = xssClean($value);
        }
    } else {
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * 获取客户端IP
 */
function getClientIp() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
?>
