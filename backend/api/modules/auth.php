<?php
/**
 * 用户认证模块
 */

require_once __DIR__ . '/../includes/functions.php';

$action = getParam('action');

switch ($action) {
    case 'register':
        register();
        break;
    case 'login':
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'refresh':
        refreshToken();
        break;
    case 'profile':
        getProfile();
        break;
    case 'updateProfile':
        updateProfile();
        break;
    case 'changePassword':
        changePassword();
        break;
    case 'forgotPassword':
        forgotPassword();
        break;
    case 'sendVerifyCode':
        sendVerifyCode();
        break;
    case 'bindPhone':
        bindPhone();
        break;
    case 'bindEmail':
        bindEmail();
        break;
    default:
        error('未知操作');
}

/**
 * 用户注册
 */
function register() {
    $params = getParams();
    
    $username = trim($params['username'] ?? '');
    $email = trim($params['email'] ?? '');
    $phone = trim($params['phone'] ?? '');
    $password = $params['password'] ?? '';
    $confirmPassword = $params['confirmPassword'] ?? '';
    $userType = intval($params['userType'] ?? 1);
    $verifyCode = $params['verifyCode'] ?? '';
    
    // 验证参数
    if (empty($username) && empty($email) && empty($phone)) {
        error('请填写用户名、邮箱或手机号');
    }
    
    if (empty($password)) {
        error('请填写密码');
    }
    
    if ($password !== $confirmPassword) {
        error('两次输入的密码不一致');
    }
    
    if (strlen($password) < 6) {
        error('密码长度不能少于6位');
    }
    
    $db = Database::getInstance();
    
    // 检查用户名是否已存在
    if (!empty($username)) {
        $exists = $db->query("SELECT id FROM users WHERE username = ?", [$username])->fetch();
        if ($exists) {
            error('用户名已存在');
        }
    }
    
    // 检查邮箱是否已存在
    if (!empty($email)) {
        $exists = $db->query("SELECT id FROM users WHERE email = ?", [$email])->fetch();
        if ($exists) {
            error('邮箱已被注册');
        }
    }
    
    // 检查手机号是否已存在
    if (!empty($phone)) {
        $exists = $db->query("SELECT id FROM users WHERE phone = ?", [$phone])->fetch();
        if ($exists) {
            error('手机号已被注册');
        }
    }
    
    // 创建用户
    $passwordHash = hashPassword($password);
    $nickname = $params['nickname'] ?: $username ?: '用户' . substr(time(), -6);
    
    try {
        $db->beginTransaction();
        
        $db->query(
            "INSERT INTO users (username, email, phone, password_hash, nickname, user_type, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [$username, $email, $phone, $passwordHash, $nickname, $userType]
        );
        
        $userId = $db->lastInsertId();
        
        // 初始化用户余额
        $db->query(
            "INSERT INTO user_balances (user_id, balance, created_at) VALUES (?, 0, NOW())",
            [$userId]
        );
        
        $db->commit();
        
        // 生成Token
        $token = generateToken($userId, $userType);
        
        logOperation($userId, 'register', 'auth', '用户注册');
        
        success([
            'token' => $token,
            'user' => [
                'id' => $userId,
                'username' => $username,
                'nickname' => $nickname,
                'email' => $email,
                'phone' => $phone,
                'userType' => $userType,
                'avatar' => null
            ]
        ], '注册成功');
        
    } catch (Exception $e) {
        $db->rollback();
        error('注册失败: ' . $e->getMessage());
    }
}

/**
 * 用户登录
 */
function login() {
    $params = getParams();
    
    $account = trim($params['account'] ?? '');
    $password = $params['password'] ?? '';
    $captcha = $params['captcha'] ?? '';
    
    if (empty($account)) {
        error('请填写账号');
    }
    
    if (empty($password)) {
        error('请填写密码');
    }
    
    $db = Database::getInstance();
    
    // 支持用户名/邮箱/手机号登录
    $user = $db->query(
        "SELECT * FROM users WHERE (username = ? OR email = ? OR phone = ?) AND status = 1",
        [$account, $account, $account]
    )->fetch();
    
    if (!$user) {
        error('账号或密码错误');
    }
    
    if (!verifyPassword($password, $user['password_hash'])) {
        error('账号或密码错误');
    }
    
    // 更新登录信息
    $ip = getClientIp();
    $db->query(
        "UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
        [$ip, $user['id']]
    );
    
    // 生成Token
    $token = generateToken($user['id'], $user['user_type']);
    
    logOperation($user['id'], 'login', 'auth', '用户登录');
    
    success([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'userType' => intval($user['user_type']),
            'avatar' => $user['avatar'],
            'companyName' => $user['company_name'],
            'isVip' => checkIsVip($user['id'])
        ]
    ], '登录成功');
}

/**
 * 检查是否是VIP
 */
function checkIsVip($userId) {
    $db = Database::getInstance();
    $subscription = $db->query(
        "SELECT * FROM user_subscriptions 
         WHERE user_id = ? AND status = 1 AND end_date >= CURDATE() 
         ORDER BY end_date DESC LIMIT 1",
        [$userId]
    )->fetch();
    return $subscription ? true : false;
}

/**
 * 退出登录
 */
function logout() {
    $user = getCurrentUser();
    if ($user) {
        logOperation($user['id'], 'logout', 'auth', '用户退出');
    }
    success(null, '退出成功');
}

/**
 * 刷新Token
 */
function refreshToken() {
    $user = requireAuth();
    $token = generateToken($user['id'], $user['user_type']);
    success(['token' => $token], '刷新成功');
}

/**
 * 获取用户信息
 */
function getProfile() {
    $user = requireAuth();
    
    // 获取会员信息
    $db = Database::getInstance();
    $subscription = $db->query(
        "SELECT * FROM user_subscriptions 
         WHERE user_id = ? AND status = 1 AND end_date >= CURDATE() 
         ORDER BY end_date DESC LIMIT 1",
        [$user['id']]
    )->fetch();
    
    // 获取余额
    $balance = $db->query(
        "SELECT * FROM user_balances WHERE user_id = ?",
        [$user['id']]
    )->fetch();
    
    success([
        'id' => $user['id'],
        'username' => $user['username'],
        'nickname' => $user['nickname'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'avatar' => $user['avatar'],
        'userType' => intval($user['user_type']),
        'realName' => $user['real_name'],
        'companyName' => $user['company_name'],
        'companyCode' => $user['company_code'],
        'province' => $user['province'],
        'city' => $user['city'],
        'address' => $user['address'],
        'subscription' => $subscription,
        'balance' => $balance ? floatval($balance['balance']) : 0,
        'createdAt' => $user['created_at']
    ]);
}

/**
 * 更新用户信息
 */
function updateProfile() {
    $user = requireAuth();
    $params = getParams();
    
    $allowedFields = ['nickname', 'realName', 'province', 'city', 'address', 'companyName'];
    $updates = [];
    $values = [];
    
    foreach ($allowedFields as $field) {
        if (isset($params[$field])) {
            $dbField = snakeCase($field);
            $updates[] = "$dbField = ?";
            $values[] = $params[$field];
        }
    }
    
    if (empty($updates)) {
        error('没有要更新的字段');
    }
    
    $values[] = $user['id'];
    
    $db = Database::getInstance();
    $db->query("UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?", $values);
    
    logOperation($user['id'], 'updateProfile', 'auth', '更新用户信息');
    
    success(null, '更新成功');
}

/**
 * 修改密码
 */
function changePassword() {
    $user = requireAuth();
    $params = getParams();
    
    $oldPassword = $params['oldPassword'] ?? '';
    $newPassword = $params['newPassword'] ?? '';
    $confirmPassword = $params['confirmPassword'] ?? '';
    
    if (empty($oldPassword) || empty($newPassword)) {
        error('请填写完整信息');
    }
    
    if ($newPassword !== $confirmPassword) {
        error('两次输入的新密码不一致');
    }
    
    if (strlen($newPassword) < 6) {
        error('新密码长度不能少于6位');
    }
    
    // 验证旧密码
    $db = Database::getInstance();
    $userData = $db->query("SELECT password_hash FROM users WHERE id = ?", [$user['id']])->fetch();
    
    if (!verifyPassword($oldPassword, $userData['password_hash'])) {
        error('原密码错误');
    }
    
    // 更新密码
    $newHash = hashPassword($newPassword);
    $db->query("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", [$newHash, $user['id']]);
    
    logOperation($user['id'], 'changePassword', 'auth', '修改密码');
    
    success(null, '密码修改成功');
}

/**
 * 忘记密码
 */
function forgotPassword() {
    $params = getParams();
    $account = trim($params['account'] ?? '');
    $verifyCode = $params['verifyCode'] ?? '';
    $newPassword = $params['newPassword'] ?? '';
    
    if (empty($account) || empty($verifyCode) || empty($newPassword)) {
        error('请填写完整信息');
    }
    
    if (strlen($newPassword) < 6) {
        error('新密码长度不能少于6位');
    }
    
    // TODO: 验证验证码
    
    $db = Database::getInstance();
    $user = $db->query(
        "SELECT id FROM users WHERE (username = ? OR email = ? OR phone = ?) AND status = 1",
        [$account, $account, $account]
    )->fetch();
    
    if (!$user) {
        error('账号不存在');
    }
    
    $newHash = hashPassword($newPassword);
    $db->query("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", [$newHash, $user['id']]);
    
    logOperation($user['id'], 'forgotPassword', 'auth', '重置密码');
    
    success(null, '密码重置成功');
}

/**
 * 发送验证码
 */
function sendVerifyCode() {
    $params = getParams();
    $type = $params['type'] ?? 'phone'; // phone or email
    $target = trim($params['target'] ?? '');
    
    if (empty($target)) {
        error('请填写手机号或邮箱');
    }
    
    // 生成验证码
    $code = rand(100000, 999999);
    
    // TODO: 发送验证码（集成短信/邮件服务）
    
    // 临时：直接返回验证码（仅开发环境）
    success(['code' => $code], '验证码已发送');
}

/**
 * 绑定手机号
 */
function bindPhone() {
    $user = requireAuth();
    $params = getParams();
    $phone = trim($params['phone'] ?? '');
    $verifyCode = $params['verifyCode'] ?? '';
    
    if (empty($phone)) {
        error('请填写手机号');
    }
    
    // TODO: 验证验证码
    
    $db = Database::getInstance();
    
    // 检查手机号是否已被绑定
    $exists = $db->query("SELECT id FROM users WHERE phone = ? AND id != ?", [$phone, $user['id']])->fetch();
    if ($exists) {
        error('该手机号已被其他账号绑定');
    }
    
    $db->query("UPDATE users SET phone = ?, phone_verified = 1, updated_at = NOW() WHERE id = ?", [$phone, $user['id']]);
    
    logOperation($user['id'], 'bindPhone', 'auth', '绑定手机号: ' . $phone);
    
    success(null, '绑定成功');
}

/**
 * 绑定邮箱
 */
function bindEmail() {
    $user = requireAuth();
    $params = getParams();
    $email = trim($params['email'] ?? '');
    $verifyCode = $params['verifyCode'] ?? '';
    
    if (empty($email)) {
        error('请填写邮箱');
    }
    
    // TODO: 验证验证码
    
    $db = Database::getInstance();
    
    // 检查邮箱是否已被绑定
    $exists = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user['id']])->fetch();
    if ($exists) {
        error('该邮箱已被其他账号绑定');
    }
    
    $db->query("UPDATE users SET email = ?, email_verified = 1, updated_at = NOW() WHERE id = ?", [$email, $user['id']]);
    
    logOperation($user['id'], 'bindEmail', 'auth', '绑定邮箱: ' . $email);
    
    success(null, '绑定成功');
}

/**
 * 驼峰转下划线
 */
function snakeCase($str) {
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
}
?>
