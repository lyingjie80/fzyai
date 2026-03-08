<?php
/**
 * 管理员模块
 */

require_once __DIR__ . '/../includes/functions.php';

$action = getParam('action');

// 验证管理员权限
$admin = requireAdmin();

switch ($action) {
    // 用户管理
    case 'getUsers':
        getUsers();
        break;
    case 'getUserDetail':
        getUserDetail();
        break;
    case 'updateUser':
        updateUser();
        break;
    case 'disableUser':
        disableUser();
        break;
        
    // 合同管理
    case 'getContracts':
        getContracts();
        break;
    case 'assignLawyer':
        assignLawyer();
        break;
        
    // 咨询管理
    case 'getConsults':
        getConsults();
        break;
    case 'replyConsult':
        replyConsult();
        break;
        
    // 订单管理
    case 'getOrders':
        getOrders();
        break;
    case 'refundOrder':
        refundOrder();
        break;
        
    // 律师管理
    case 'getLawyers':
        getLawyers();
        break;
    case 'verifyLawyer':
        verifyLawyer();
        break;
    case 'getWithdrawals':
        getWithdrawals();
        break;
    case 'approveWithdrawal':
        approveWithdrawal();
        break;
        
    // 内容管理
    case 'getArticles':
        adminGetArticles();
        break;
    case 'saveArticle':
        saveArticle();
        break;
    case 'deleteArticle':
        deleteArticle();
        break;
    case 'getBanners':
        adminGetBanners();
        break;
    case 'saveBanner':
        saveBanner();
        break;
    case 'deleteBanner':
        deleteBanner();
        break;
        
    // 模板管理
    case 'getTemplates':
        adminGetTemplates();
        break;
    case 'saveTemplate':
        saveTemplate();
        break;
    case 'deleteTemplate':
        deleteTemplate();
        break;
        
    // 数据统计
    case 'getDashboard':
        getDashboard();
        break;
    case 'getStats':
        getAdminStats();
        break;
        
    // 系统配置
    case 'getConfigs':
        getConfigs();
        break;
    case 'saveConfig':
        saveConfig();
        break;
        
    default:
        error('未知操作');
}

/**
 * 获取用户列表
 */
function getUsers() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $keyword = $params['keyword'] ?? null;
    $userType = $params['userType'] ?? null;
    $status = $params['status'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE 1=1";
    $values = [];
    
    if ($keyword) {
        $where .= " AND (username LIKE ? OR email LIKE ? OR phone LIKE ? OR nickname LIKE ?)";
        $like = "%$keyword%";
        $values = array_merge($values, [$like, $like, $like, $like]);
    }
    
    if ($userType !== null) {
        $where .= " AND user_type = ?";
        $values[] = $userType;
    }
    
    if ($status !== null) {
        $where .= " AND status = ?";
        $values[] = $status;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM users $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT id, username, email, phone, nickname, user_type, status, real_name, company_name, created_at, last_login_at 
         FROM users $where 
         ORDER BY created_at DESC LIMIT ? OFFSET ?",
        $values
    )->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 获取用户详情
 */
function getUserDetail() {
    $userId = intval(getParam('id'));
    
    if (!$userId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $user = $db->query("SELECT * FROM users WHERE id = ?", [$userId])->fetch();
    
    if (!$user) {
        error('用户不存在');
    }
    
    // 获取订阅信息
    $subscriptions = $db->query(
        "SELECT * FROM user_subscriptions WHERE user_id = ? ORDER BY created_at DESC",
        [$userId]
    )->fetchAll();
    
    // 获取余额
    $balance = $db->query(
        "SELECT * FROM user_balances WHERE user_id = ?",
        [$userId]
    )->fetch();
    
    $user['subscriptions'] = $subscriptions;
    $user['balance'] = $balance;
    
    success($user);
}

/**
 * 更新用户
 */
function updateUser() {
    global $admin;
    $params = getParams();
    $userId = intval($params['id'] ?? 0);
    
    if (!$userId) {
        error('参数错误');
    }
    
    $allowedFields = ['nickname', 'status', 'user_type', 'real_name', 'company_name'];
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
    
    $values[] = $userId;
    
    $db = Database::getInstance();
    $db->query("UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?", $values);
    
    logOperation($admin['id'], 'updateUser', 'admin', '更新用户: ' . $userId);
    
    success(null, '更新成功');
}

/**
 * 禁用用户
 */
function disableUser() {
    global $admin;
    $params = getParams();
    $userId = intval($params['id'] ?? 0);
    $status = intval($params['status'] ?? 0);
    
    if (!$userId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
    
    logOperation($admin['id'], 'disableUser', 'admin', ($status ? '启用' : '禁用') . '用户: ' . $userId);
    
    success(null, '操作成功');
}

/**
 * 获取合同列表
 */
function getContracts() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $status = $params['status'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE 1=1";
    $values = [];
    
    if ($status !== null) {
        $where .= " AND cr.review_status = ?";
        $values[] = $status;
    }
    
    $total = $db->query(
        "SELECT COUNT(*) as count FROM contract_reviews cr $where",
        $values
    )->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT cr.*, u.nickname as user_name, l.nickname as lawyer_name 
         FROM contract_reviews cr 
         LEFT JOIN users u ON cr.user_id = u.id 
         LEFT JOIN users l ON cr.reviewed_by = l.id 
         $where 
         ORDER BY cr.created_at DESC LIMIT ? OFFSET ?",
        $values
    )->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 分配律师
 */
function assignLawyer() {
    global $admin;
    $params = getParams();
    $contractId = intval($params['contractId'] ?? 0);
    $lawyerId = intval($params['lawyerId'] ?? 0);
    
    if (!$contractId || !$lawyerId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query(
        "UPDATE contract_reviews SET reviewed_by = ?, review_status = 1, updated_at = NOW() WHERE id = ?",
        [$lawyerId, $contractId]
    );
    
    logOperation($admin['id'], 'assignLawyer', 'admin', '分配律师: ' . $lawyerId . ' 到合同: ' . $contractId);
    
    success(null, '分配成功');
}

/**
 * 获取咨询列表
 */
function getConsults() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $status = $params['status'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE 1=1";
    $values = [];
    
    if ($status !== null) {
        $where .= " AND status = ?";
        $values[] = $status;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM legal_consults $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT lc.*, u.nickname as user_name 
         FROM legal_consults lc 
         LEFT JOIN users u ON lc.user_id = u.id 
         $where 
         ORDER BY lc.created_at DESC LIMIT ? OFFSET ?",
        $values
    )->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 回复咨询
 */
function replyConsult() {
    global $admin;
    $params = getParams();
    $consultId = intval($params['consultId'] ?? 0);
    $content = trim($params['content'] ?? '');
    
    if (!$consultId || empty($content)) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    
    // 保存消息
    $db->query(
        "INSERT INTO consult_messages (consult_id, sender_id, sender_type, content, created_at) 
         VALUES (?, ?, 3, ?, NOW())",
        [$consultId, $admin['id'], $content]
    );
    
    // 更新咨询状态
    $db->query(
        "UPDATE legal_consults SET lawyer_reply = ?, replied_by = ?, reply_at = NOW(), status = 2 WHERE id = ?",
        [$content, $admin['id'], $consultId]
    );
    
    logOperation($admin['id'], 'replyConsult', 'admin', '回复咨询: ' . $consultId);
    
    success(null, '回复成功');
}

/**
 * 获取订单列表
 */
function getOrders() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $status = $params['status'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE 1=1";
    $values = [];
    
    if ($status !== null) {
        $where .= " AND payment_status = ?";
        $values[] = $status;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM payment_orders $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT po.*, u.nickname as user_name 
         FROM payment_orders po 
         LEFT JOIN users u ON po.user_id = u.id 
         $where 
         ORDER BY po.created_at DESC LIMIT ? OFFSET ?",
        $values
    )->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 退款
 */
function refundOrder() {
    global $admin;
    $params = getParams();
    $orderId = intval($params['orderId'] ?? 0);
    $amount = floatval($params['amount'] ?? 0);
    $reason = trim($params['reason'] ?? '');
    
    if (!$orderId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $order = $db->query("SELECT * FROM payment_orders WHERE id = ?", [$orderId])->fetch();
    
    if (!$order || $order['payment_status'] != 1) {
        error('订单状态不允许退款');
    }
    
    $refundAmount = $amount > 0 ? $amount : $order['amount'];
    
    $db->query(
        "UPDATE payment_orders SET payment_status = 3, refund_amount = ?, refund_at = NOW(), refund_reason = ? WHERE id = ?",
        [$refundAmount, $reason, $orderId]
    );
    
    logOperation($admin['id'], 'refundOrder', 'admin', '退款订单: ' . $orderId);
    
    success(null, '退款成功');
}

/**
 * 获取律师列表
 */
function getLawyers() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $status = $params['status'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE user_type = 3";
    $values = [];
    
    if ($status !== null) {
        $where .= " AND status = ?";
        $values[] = $status;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM users $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT id, username, email, phone, nickname, real_name, lawyer_firm, lawyer_title, specialty, status, created_at 
         FROM users $where 
         ORDER BY created_at DESC LIMIT ? OFFSET ?",
        $values
    )->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 审核律师
 */
function verifyLawyer() {
    global $admin;
    $params = getParams();
    $lawyerId = intval($params['lawyerId'] ?? 0);
    $status = intval($params['status'] ?? 1);
    
    if (!$lawyerId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query("UPDATE users SET status = ? WHERE id = ?", [$status, $lawyerId]);
    
    logOperation($admin['id'], 'verifyLawyer', 'admin', '审核律师: ' . $lawyerId . ' 状态: ' . $status);
    
    success(null, '审核完成');
}

/**
 * 获取提现列表
 */
function getWithdrawals() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $status = $params['status'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE 1=1";
    $values = [];
    
    if ($status !== null) {
        $where .= " AND lw.status = ?";
        $values[] = $status;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM lawyer_withdrawals lw $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT lw.*, u.nickname as lawyer_name, u.real_name 
         FROM lawyer_withdrawals lw 
         LEFT JOIN users u ON lw.lawyer_id = u.id 
         $where 
         ORDER BY lw.created_at DESC LIMIT ? OFFSET ?",
        $values
    )->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 审批提现
 */
function approveWithdrawal() {
    global $admin;
    $params = getParams();
    $withdrawalId = intval($params['withdrawalId'] ?? 0);
    $status = intval($params['status'] ?? 1);
    $remark = trim($params['remark'] ?? '');
    
    if (!$withdrawalId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query(
        "UPDATE lawyer_withdrawals SET status = ?, reviewed_by = ?, reviewed_at = NOW(), remark = ? WHERE id = ?",
        [$status, $admin['id'], $remark, $withdrawalId]
    );
    
    logOperation($admin['id'], 'approveWithdrawal', 'admin', '审批提现: ' . $withdrawalId);
    
    success(null, '审批完成');
}

/**
 * 获取文章列表
 */
function adminGetArticles() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    $total = $db->query("SELECT COUNT(*) as count FROM articles")->fetch()['count'];
    
    $list = $db->query(
        "SELECT * FROM articles ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$pageSize, $offset]
    )->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 保存文章
 */
function saveArticle() {
    global $admin;
    $params = getParams();
    $id = intval($params['id'] ?? 0);
    
    $data = [
        'title' => $params['title'] ?? '',
        'category' => $params['category'] ?? '',
        'content' => $params['content'] ?? '',
        'summary' => $params['summary'] ?? '',
        'cover_image' => $params['coverImage'] ?? '',
        'author' => $params['author'] ?? '',
        'status' => $params['status'] ?? 1,
        'is_top' => $params['isTop'] ?? 0
    ];
    
    if (empty($data['title']) || empty($data['content'])) {
        error('标题和内容不能为空');
    }
    
    $db = Database::getInstance();
    
    if ($id) {
        // 更新
        $db->query(
            "UPDATE articles SET title = ?, category = ?, content = ?, summary = ?, cover_image = ?, author = ?, status = ?, is_top = ?, updated_at = NOW() WHERE id = ?",
            [$data['title'], $data['category'], $data['content'], $data['summary'], $data['cover_image'], $data['author'], $data['status'], $data['is_top'], $id]
        );
    } else {
        // 新增
        $db->query(
            "INSERT INTO articles (title, category, content, summary, cover_image, author, status, is_top, published_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$data['title'], $data['category'], $data['content'], $data['summary'], $data['cover_image'], $data['author'], $data['status'], $data['is_top']]
        );
        $id = $db->lastInsertId();
    }
    
    logOperation($admin['id'], 'saveArticle', 'admin', '保存文章: ' . $id);
    
    success(['id' => $id], '保存成功');
}

/**
 * 删除文章
 */
function deleteArticle() {
    global $admin;
    $id = intval(getParam('id'));
    
    if (!$id) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query("DELETE FROM articles WHERE id = ?", [$id]);
    
    logOperation($admin['id'], 'deleteArticle', 'admin', '删除文章: ' . $id);
    
    success(null, '删除成功');
}

/**
 * 获取轮播图列表
 */
function adminGetBanners() {
    $db = Database::getInstance();
    $list = $db->query("SELECT * FROM banners ORDER BY sort_order ASC")->fetchAll();
    success($list);
}

/**
 * 保存轮播图
 */
function saveBanner() {
    global $admin;
    $params = getParams();
    $id = intval($params['id'] ?? 0);
    
    $data = [
        'title' => $params['title'] ?? '',
        'image_url' => $params['imageUrl'] ?? '',
        'link_url' => $params['linkUrl'] ?? '',
        'position' => $params['position'] ?? 'home',
        'sort_order' => $params['sortOrder'] ?? 0,
        'status' => $params['status'] ?? 1
    ];
    
    if (empty($data['image_url'])) {
        error('图片不能为空');
    }
    
    $db = Database::getInstance();
    
    if ($id) {
        $db->query(
            "UPDATE banners SET title = ?, image_url = ?, link_url = ?, position = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?",
            [$data['title'], $data['image_url'], $data['link_url'], $data['position'], $data['sort_order'], $data['status'], $id]
        );
    } else {
        $db->query(
            "INSERT INTO banners (title, image_url, link_url, position, sort_order, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$data['title'], $data['image_url'], $data['link_url'], $data['position'], $data['sort_order'], $data['status']]
        );
        $id = $db->lastInsertId();
    }
    
    logOperation($admin['id'], 'saveBanner', 'admin', '保存轮播图: ' . $id);
    
    success(['id' => $id], '保存成功');
}

/**
 * 删除轮播图
 */
function deleteBanner() {
    global $admin;
    $id = intval(getParam('id'));
    
    if (!$id) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query("DELETE FROM banners WHERE id = ?", [$id]);
    
    logOperation($admin['id'], 'deleteBanner', 'admin', '删除轮播图: ' . $id);
    
    success(null, '删除成功');
}

/**
 * 获取模板列表
 */
function adminGetTemplates() {
    $params = getParams();
    $type = $params['type'] ?? 'contract';
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    if ($type == 'contract') {
        $table = 'contract_templates';
    } else {
        $table = 'document_templates';
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM $table")->fetch()['count'];
    $list = $db->query("SELECT * FROM $table ORDER BY created_at DESC LIMIT ? OFFSET ?", [$pageSize, $offset])->fetchAll();
    
    success([
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => intval($total),
            'totalPages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 保存模板
 */
function saveTemplate() {
    global $admin;
    $params = getParams();
    $type = $params['type'] ?? 'contract';
    $id = intval($params['id'] ?? 0);
    
    $db = Database::getInstance();
    
    if ($type == 'contract') {
        $table = 'contract_templates';
        $fields = ['name', 'category', 'subcategory', 'description', 'content', 'file_url', 'is_premium', 'price', 'status'];
    } else {
        $table = 'document_templates';
        $fields = ['name', 'category', 'description', 'template_content', 'required_fields', 'is_premium', 'price', 'status'];
    }
    
    // 构建SQL
    $updates = [];
    $values = [];
    
    foreach ($fields as $field) {
        if (isset($params[$field])) {
            $updates[] = "$field = ?";
            $values[] = is_array($params[$field]) ? json_encode($params[$field]) : $params[$field];
        }
    }
    
    if (empty($updates)) {
        error('没有要保存的数据');
    }
    
    if ($id) {
        $values[] = $id;
        $db->query("UPDATE $table SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?", $values);
    } else {
        $db->query("INSERT INTO $table SET " . implode(', ', $updates) . ", created_at = NOW()", $values);
        $id = $db->lastInsertId();
    }
    
    logOperation($admin['id'], 'saveTemplate', 'admin', '保存模板: ' . $id);
    
    success(['id' => $id], '保存成功');
}

/**
 * 删除模板
 */
function deleteTemplate() {
    global $admin;
    $params = getParams();
    $type = $params['type'] ?? 'contract';
    $id = intval($params['id'] ?? 0);
    
    if (!$id) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $table = $type == 'contract' ? 'contract_templates' : 'document_templates';
    $db->query("DELETE FROM $table WHERE id = ?", [$id]);
    
    logOperation($admin['id'], 'deleteTemplate', 'admin', '删除模板: ' . $id);
    
    success(null, '删除成功');
}

/**
 * 获取仪表盘数据
 */
function getDashboard() {
    $db = Database::getInstance();
    
    // 今日数据
    $today = date('Y-m-d');
    $todayUsers = $db->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?", [$today])->fetch()['count'];
    $todayOrders = $db->query("SELECT COUNT(*) as count FROM payment_orders WHERE DATE(created_at) = ? AND payment_status = 1", [$today])->fetch()['count'];
    $todayRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) as sum FROM payment_orders WHERE DATE(paid_at) = ? AND payment_status = 1", [$today])->fetch()['sum'];
    
    // 总数据
    $totalUsers = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 1")->fetch()['count'];
    $totalOrders = $db->query("SELECT COUNT(*) as count FROM payment_orders WHERE payment_status = 1")->fetch()['count'];
    $totalRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) as sum FROM payment_orders WHERE payment_status = 1")->fetch()['sum'];
    
    // 待处理事项
    $pendingContracts = $db->query("SELECT COUNT(*) as count FROM contract_reviews WHERE review_status = 0")->fetch()['count'];
    $pendingConsults = $db->query("SELECT COUNT(*) as count FROM legal_consults WHERE status = 0")->fetch()['count'];
    $pendingWithdrawals = $db->query("SELECT COUNT(*) as count FROM lawyer_withdrawals WHERE status = 0")->fetch()['count'];
    
    success([
        'today' => [
            'users' => intval($todayUsers),
            'orders' => intval($todayOrders),
            'revenue' => floatval($todayRevenue)
        ],
        'total' => [
            'users' => intval($totalUsers),
            'orders' => intval($totalOrders),
            'revenue' => floatval($totalRevenue)
        ],
        'pending' => [
            'contracts' => intval($pendingContracts),
            'consults' => intval($pendingConsults),
            'withdrawals' => intval($pendingWithdrawals)
        ]
    ]);
}

/**
 * 获取统计数据
 */
function getAdminStats() {
    $params = getParams();
    $startDate = $params['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $params['endDate'] ?? date('Y-m-d');
    
    $db = Database::getInstance();
    
    // 每日用户注册数
    $userStats = $db->query(
        "SELECT DATE(created_at) as date, COUNT(*) as count 
         FROM users 
         WHERE DATE(created_at) BETWEEN ? AND ? 
         GROUP BY DATE(created_at) 
         ORDER BY date",
        [$startDate, $endDate]
    )->fetchAll();
    
    // 每日订单数和金额
    $orderStats = $db->query(
        "SELECT DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount 
         FROM payment_orders 
         WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 1
         GROUP BY DATE(created_at) 
         ORDER BY date",
        [$startDate, $endDate]
    )->fetchAll();
    
    success([
        'userStats' => $userStats,
        'orderStats' => $orderStats
    ]);
}

/**
 * 获取系统配置
 */
function getConfigs() {
    $db = Database::getInstance();
    $configs = $db->query("SELECT * FROM system_configs ORDER BY config_key ASC")->fetchAll();
    success($configs);
}

/**
 * 保存系统配置
 */
function saveConfig() {
    global $admin;
    $params = getParams();
    $key = $params['key'] ?? '';
    $value = $params['value'] ?? '';
    
    if (empty($key)) {
        error('配置键不能为空');
    }
    
    $db = Database::getInstance();
    
    $exists = $db->query("SELECT id FROM system_configs WHERE config_key = ?", [$key])->fetch();
    
    if ($exists) {
        $db->query("UPDATE system_configs SET config_value = ?, updated_at = NOW() WHERE config_key = ?", [$value, $key]);
    } else {
        $db->query("INSERT INTO system_configs (config_key, config_value, created_at) VALUES (?, ?, NOW())", [$key, $value]);
    }
    
    logOperation($admin['id'], 'saveConfig', 'admin', '保存配置: ' . $key);
    
    success(null, '保存成功');
}

/**
 * 驼峰转下划线
 */
function snakeCase($str) {
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
}
?>
