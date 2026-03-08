<?php
/**
 * 法律咨询模块
 */

require_once __DIR__ . '/../includes/functions.php';

$action = getParam('action');

switch ($action) {
    case 'create':
        createConsult();
        break;
    case 'getList':
        getConsultList();
        break;
    case 'getDetail':
        getConsultDetail();
        break;
    case 'sendMessage':
        sendMessage();
        break;
    case 'aiReply':
        aiReply();
        break;
    case 'close':
        closeConsult();
        break;
    case 'rate':
        rateConsult();
        break;
    case 'getCategories':
        getCategories();
        break;
    case 'getPublicList':
        getPublicList();
        break;
    default:
        error('未知操作');
}

/**
 * 创建咨询
 */
function createConsult() {
    $user = requireAuth();
    $params = getParams();
    
    $title = trim($params['title'] ?? '');
    $content = trim($params['content'] ?? '');
    $category = $params['category'] ?? '其他';
    $urgency = intval($params['urgency'] ?? 1);
    $isPublic = intval($params['isPublic'] ?? 0);
    
    if (empty($title)) {
        error('请填写咨询标题');
    }
    
    if (empty($content)) {
        error('请填写咨询内容');
    }
    
    // 检查用户会员状态
    $db = Database::getInstance();
    $subscription = $db->query(
        "SELECT * FROM user_subscriptions 
         WHERE user_id = ? AND status = 1 AND end_date >= CURDATE() 
         AND consult_limit > 0 AND consult_used < consult_limit
         ORDER BY end_date DESC LIMIT 1",
        [$user['id']]
    )->fetch();
    
    if (!$subscription) {
        // 检查免费次数
        $freeCount = $db->query(
            "SELECT COUNT(*) as count FROM legal_consults WHERE user_id = ?",
            [$user['id']]
        )->fetch()['count'];
        
        if ($freeCount >= 3) {
            error('您的免费咨询次数已用完，请购买会员');
        }
    }
    
    // 创建咨询
    $db->query(
        "INSERT INTO legal_consults (user_id, title, content, category, urgency, is_public, status, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW())",
        [$user['id'], $title, $content, $category, $urgency, $isPublic]
    );
    
    $consultId = $db->lastInsertId();
    
    // 添加第一条消息
    $db->query(
        "INSERT INTO consult_messages (consult_id, sender_id, sender_type, content, created_at) 
         VALUES (?, ?, 1, ?, NOW())",
        [$consultId, $user['id'], $content]
    );
    
    // 更新会员使用次数
    if ($subscription) {
        $db->query(
            "UPDATE user_subscriptions SET consult_used = consult_used + 1 WHERE id = ?",
            [$subscription['id']]
        );
    }
    
    // AI自动回复
    $aiReply = generateAIReply($content, $category);
    
    $db->query(
        "INSERT INTO consult_messages (consult_id, sender_id, sender_type, content, created_at) 
         VALUES (?, 0, 2, ?, NOW())",
        [$consultId, $aiReply]
    );
    
    $db->query(
        "UPDATE legal_consults SET ai_reply = ?, status = 1 WHERE id = ?",
        [$aiReply, $consultId]
    );
    
    logOperation($user['id'], 'createConsult', 'consult', '创建咨询: ' . $title);
    
    success([
        'consultId' => $consultId,
        'aiReply' => $aiReply
    ], '咨询已提交');
}

/**
 * 生成AI回复
 */
function generateAIReply($content, $category) {
    // 模拟AI回复
    $replies = [
        '劳动纠纷' => "根据您描述的情况，这属于劳动争议范畴。\n\n【法律分析】\n1. 根据《劳动合同法》相关规定，用人单位应当...\n2. 您可以主张的权益包括...\n\n【建议措施】\n1. 首先与用人单位协商解决\n2. 协商不成可向劳动仲裁委员会申请仲裁\n3. 注意收集相关证据材料\n\n【注意事项】\n- 劳动仲裁时效为一年\n- 建议保留工资条、考勤记录等证据\n\n如需更详细的法律意见，建议咨询专业律师。",
        
        '合同纠纷' => "关于您提到的合同问题，我的分析如下：\n\n【法律分析】\n1. 合同效力的认定需要考虑...\n2. 对方的行为可能构成违约...\n\n【建议措施】\n1. 先发函催告对方履行义务\n2. 保留相关证据材料\n3. 必要时可向法院提起诉讼\n\n【风险提示】\n- 注意诉讼时效问题\n- 证据收集要全面\n\n建议携带完整材料咨询专业律师。",
        
        '知识产权' => "针对您的知识产权问题：\n\n【法律分析】\n1. 您的权利归属需要确认...\n2. 对方的行为可能构成侵权...\n\n【维权建议】\n1. 首先进行证据保全\n2. 可发送律师函要求停止侵权\n3. 必要时提起诉讼维权\n\n【注意事项】\n- 知识产权案件专业性较强\n- 建议委托专业律师处理\n\n如需进一步帮助，请联系平台律师。",
        
        '婚姻家庭' => "关于您的婚姻家庭问题：\n\n【法律分析】\n1. 根据《民法典》婚姻家庭编...\n2. 财产分割原则为...\n\n【建议措施】\n1. 尽量协商解决\n2. 协商不成可向法院起诉\n3. 注意保护自身合法权益\n\n【温馨提示】\n- 涉及情感问题，建议冷静处理\n- 子女利益应优先考量\n\n如需专业法律帮助，可预约平台律师。",
        
        '其他' => "感谢您的咨询。根据您描述的情况：\n\n【初步分析】\n您遇到的问题涉及法律层面的考量，需要结合具体情况进行分析。\n\n【一般建议】\n1. 保留好相关证据材料\n2. 了解相关法律法规\n3. 必要时寻求专业法律帮助\n\n【下一步】\n由于问题的复杂性，建议您：\n- 补充更详细的情况说明\n- 或预约平台律师进行一对一咨询\n\n我们将竭诚为您提供帮助。"
    ];
    
    return isset($replies[$category]) ? $replies[$category] : $replies['其他'];
}

/**
 * 获取咨询列表
 */
function getConsultList() {
    $user = requireAuth();
    $params = getParams();
    
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $status = $params['status'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE user_id = ?";
    $values = [$user['id']];
    
    if ($status !== null) {
        $where .= " AND status = ?";
        $values[] = $status;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM legal_consults $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT id, title, category, urgency, status, is_public, view_count, created_at, updated_at 
         FROM legal_consults $where 
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
 * 获取咨询详情
 */
function getConsultDetail() {
    $user = requireAuth();
    $consultId = intval(getParam('id'));
    
    if (!$consultId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $consult = $db->query(
        "SELECT * FROM legal_consults WHERE id = ? AND user_id = ?",
        [$consultId, $user['id']]
    )->fetch();
    
    if (!$consult) {
        error('咨询记录不存在');
    }
    
    // 获取消息记录
    $messages = $db->query(
        "SELECT m.*, u.nickname as sender_name, u.avatar as sender_avatar 
         FROM consult_messages m 
         LEFT JOIN users u ON m.sender_id = u.id 
         WHERE m.consult_id = ? 
         ORDER BY m.created_at ASC",
        [$consultId]
    )->fetchAll();
    
    $consult['messages'] = $messages;
    
    success($consult);
}

/**
 * 发送消息
 */
function sendMessage() {
    $user = requireAuth();
    $params = getParams();
    
    $consultId = intval($params['consultId'] ?? 0);
    $content = trim($params['content'] ?? '');
    
    if (!$consultId || empty($content)) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    
    // 检查咨询是否存在
    $consult = $db->query(
        "SELECT * FROM legal_consults WHERE id = ? AND user_id = ? AND status < 3",
        [$consultId, $user['id']]
    )->fetch();
    
    if (!$consult) {
        error('咨询不存在或已关闭');
    }
    
    // 保存消息
    $db->query(
        "INSERT INTO consult_messages (consult_id, sender_id, sender_type, content, created_at) 
         VALUES (?, ?, 1, ?, NOW())",
        [$consultId, $user['id'], $content]
    );
    
    // 更新咨询时间
    $db->query(
        "UPDATE legal_consults SET updated_at = NOW() WHERE id = ?",
        [$consultId]
    );
    
    success(null, '发送成功');
}

/**
 * AI回复
 */
function aiReply() {
    $user = requireAuth();
    $params = getParams();
    
    $consultId = intval($params['consultId'] ?? 0);
    $question = trim($params['question'] ?? '');
    
    if (!$consultId || empty($question)) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    
    // 检查咨询
    $consult = $db->query(
        "SELECT * FROM legal_consults WHERE id = ? AND user_id = ?",
        [$consultId, $user['id']]
    )->fetch();
    
    if (!$consult) {
        error('咨询不存在');
    }
    
    // 生成AI回复
    $aiReply = generateAIReply($question, $consult['category']);
    
    // 保存消息
    $db->query(
        "INSERT INTO consult_messages (consult_id, sender_id, sender_type, content, created_at) 
         VALUES (?, 0, 2, ?, NOW())",
        [$consultId, $aiReply]
    );
    
    // 更新咨询AI回复
    $db->query(
        "UPDATE legal_consults SET ai_reply = ?, status = 1 WHERE id = ?",
        [$aiReply, $consultId]
    );
    
    success(['reply' => $aiReply]);
}

/**
 * 关闭咨询
 */
function closeConsult() {
    $user = requireAuth();
    $consultId = intval(getParam('id'));
    
    if (!$consultId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query(
        "UPDATE legal_consults SET status = 3 WHERE id = ? AND user_id = ?",
        [$consultId, $user['id']]
    );
    
    success(null, '咨询已关闭');
}

/**
 * 评价咨询
 */
function rateConsult() {
    $user = requireAuth();
    $params = getParams();
    
    $consultId = intval($params['consultId'] ?? 0);
    $rating = intval($params['rating'] ?? 0);
    $comment = trim($params['comment'] ?? '');
    
    if (!$consultId || $rating < 1 || $rating > 5) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $db->query(
        "UPDATE legal_consults SET satisfaction = ? WHERE id = ? AND user_id = ?",
        [$rating, $consultId, $user['id']]
    );
    
    success(null, '评价成功');
}

/**
 * 获取咨询分类
 */
function getCategories() {
    $categories = [
        ['id' => '劳动纠纷', 'name' => '劳动纠纷', 'icon' => 'work'],
        ['id' => '合同纠纷', 'name' => '合同纠纷', 'icon' => 'contract'],
        ['id' => '知识产权', 'name' => '知识产权', 'icon' => 'copyright'],
        ['id' => '婚姻家庭', 'name' => '婚姻家庭', 'icon' => 'family'],
        ['id' => '房产纠纷', 'name' => '房产纠纷', 'icon' => 'home'],
        ['id' => '债权债务', 'name' => '债权债务', 'icon' => 'money'],
        ['id' => '交通事故', 'name' => '交通事故', 'icon' => 'car'],
        ['id' => '刑事辩护', 'name' => '刑事辩护', 'icon' => 'gavel'],
        ['id' => '公司法律', 'name' => '公司法律', 'icon' => 'business'],
        ['id' => '其他', 'name' => '其他咨询', 'icon' => 'help']
    ];
    
    success($categories);
}

/**
 * 获取公开咨询列表
 */
function getPublicList() {
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $category = $params['category'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE is_public = 1 AND status > 0";
    $values = [];
    
    if ($category) {
        $where .= " AND category = ?";
        $values[] = $category;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM legal_consults $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT lc.id, lc.title, lc.category, lc.ai_reply, lc.view_count, lc.created_at, u.nickname as author
         FROM legal_consults lc 
         LEFT JOIN users u ON lc.user_id = u.id 
         $where 
         ORDER BY lc.created_at DESC LIMIT ? OFFSET ?",
        $values
    )->fetchAll();
    
    // 增加浏览次数
    foreach ($list as $item) {
        $db->query(
            "UPDATE legal_consults SET view_count = view_count + 1 WHERE id = ?",
            [$item['id']]
        );
    }
    
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
?>
