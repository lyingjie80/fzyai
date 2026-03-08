<?php
/**
 * 合同模块
 */

require_once __DIR__ . '/../includes/functions.php';

$action = getParam('action');

switch ($action) {
    case 'upload':
        uploadContract();
        break;
    case 'review':
        reviewContract();
        break;
    case 'getReviewList':
        getReviewList();
        break;
    case 'getReviewDetail':
        getReviewDetail();
        break;
    case 'getTemplates':
        getTemplates();
        break;
    case 'getTemplateDetail':
        getTemplateDetail();
        break;
    case 'downloadTemplate':
        downloadTemplate();
        break;
    case 'aiReview':
        aiReview();
        break;
    default:
        error('未知操作');
}

/**
 * 上传合同
 */
function uploadContract() {
    $user = requireAuth();
    
    if (!isset($_FILES['file'])) {
        error('请选择要上传的文件');
    }
    
    $file = $_FILES['file'];
    $contractName = getParam('name', $file['name']);
    $contractType = getParam('type', 'other');
    
    // 上传文件
    $result = uploadFile($file, 'contracts');
    if (!$result['success']) {
        error($result['message']);
    }
    
    // 读取文件内容
    $content = '';
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (in_array($ext, ['txt', 'doc', 'docx'])) {
        // 这里需要集成文档解析服务
        $content = '';
    }
    
    // 保存到数据库
    $db = Database::getInstance();
    $db->query(
        "INSERT INTO contract_reviews (user_id, contract_name, contract_type, original_file, file_size, content, review_status, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW())",
        [$user['id'], $contractName, $contractType, $result['url'], $result['size'], $content]
    );
    
    $reviewId = $db->lastInsertId();
    
    logOperation($user['id'], 'uploadContract', 'contract', '上传合同: ' . $contractName);
    
    success([
        'id' => $reviewId,
        'name' => $contractName,
        'url' => $result['url'],
        'size' => $result['size']
    ], '上传成功');
}

/**
 * 请求合同审查
 */
function reviewContract() {
    $user = requireAuth();
    $params = getParams();
    
    $reviewId = intval($params['reviewId'] ?? 0);
    $isLawyerReview = intval($params['isLawyerReview'] ?? 0);
    
    if (!$reviewId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    
    // 检查审查记录
    $review = $db->query(
        "SELECT * FROM contract_reviews WHERE id = ? AND user_id = ?",
        [$reviewId, $user['id']]
    )->fetch();
    
    if (!$review) {
        error('审查记录不存在');
    }
    
    // 检查用户会员状态
    $subscription = $db->query(
        "SELECT * FROM user_subscriptions 
         WHERE user_id = ? AND status = 1 AND end_date >= CURDATE() 
         AND contract_review_used < contract_review_limit
         ORDER BY end_date DESC LIMIT 1",
        [$user['id']]
    )->fetch();
    
    if (!$subscription) {
        // 检查是否有免费试用次数
        $freeTrialUsed = $db->query(
            "SELECT COUNT(*) as count FROM contract_reviews WHERE user_id = ? AND is_ai_review = 1",
            [$user['id']]
        )->fetch()['count'];
        
        if ($freeTrialUsed >= 1) {
            error('您的合同审查次数已用完，请购买会员');
        }
    }
    
    // 更新审查状态
    $db->query(
        "UPDATE contract_reviews SET review_status = 1, is_lawyer_review = ?, updated_at = NOW() WHERE id = ?",
        [$isLawyerReview, $reviewId]
    );
    
    // 如果使用会员次数，更新使用记录
    if ($subscription) {
        $db->query(
            "UPDATE user_subscriptions 
             SET contract_review_used = contract_review_used + 1 
             WHERE id = ?",
            [$subscription['id']]
        );
    }
    
    // 执行AI审查（异步）
    if (!$isLawyerReview) {
        // 模拟AI审查
        $aiResult = simulateAIReview($review['content']);
        
        $db->query(
            "UPDATE contract_reviews 
             SET review_status = 2, 
                 ai_analysis = ?,
                 risk_level = ?,
                 risk_points = ?,
                 suggestions = ?,
                 is_ai_review = 1,
                 ai_review_at = NOW(),
                 updated_at = NOW() 
             WHERE id = ?",
            [
                json_encode($aiResult['analysis']),
                $aiResult['riskLevel'],
                json_encode($aiResult['riskPoints']),
                $aiResult['suggestions'],
                $reviewId
            ]
        );
    }
    
    logOperation($user['id'], 'reviewContract', 'contract', '请求合同审查: ' . $review['contract_name']);
    
    success(['reviewId' => $reviewId], '审查请求已提交');
}

/**
 * 模拟AI审查
 */
function simulateAIReview($content) {
    // 模拟AI审查结果
    $riskPoints = [];
    $riskLevel = 1;
    
    // 常见风险点检测
    $riskKeywords = [
        ['keyword' => '违约金', 'risk' => '违约金条款需明确具体金额或计算方式', 'level' => 2],
        ['keyword' => '免责', 'risk' => '免责条款可能损害您的权益，请仔细审查', 'level' => 2],
        ['keyword' => '仲裁', 'risk' => '仲裁条款可能限制您的诉讼权利', 'level' => 1],
        ['keyword' => '保密', 'risk' => '保密条款范围可能过宽', 'level' => 1],
        ['keyword' => '知识产权', 'risk' => '知识产权归属需明确约定', 'level' => 2],
        ['keyword' => '终止', 'risk' => '合同终止条件需明确', 'level' => 1],
        ['keyword' => '不可抗力', 'risk' => '不可抗力条款需具体列举', 'level' => 1],
    ];
    
    foreach ($riskKeywords as $item) {
        if (strpos($content, $item['keyword']) !== false) {
            $riskPoints[] = [
                'type' => $item['keyword'],
                'description' => $item['risk'],
                'level' => $item['level'],
                'position' => '全文'
            ];
            $riskLevel = max($riskLevel, $item['level']);
        }
    }
    
    if (empty($riskPoints)) {
        $riskPoints[] = [
            'type' => '常规审查',
            'description' => '合同整体结构完整，未发现明显风险点',
            'level' => 1,
            'position' => '全文'
        ];
    }
    
    $suggestions = "1. 建议请专业律师进行二次审核\n";
    $suggestions .= "2. 重点关注违约责任条款\n";
    $suggestions .= "3. 确保争议解决条款符合您的利益\n";
    $suggestions .= "4. 核实对方主体资格和履约能力";
    
    return [
        'analysis' => [
            'summary' => 'AI智能分析完成，共发现' . count($riskPoints) . '个关注点',
            'detail' => $riskPoints
        ],
        'riskLevel' => $riskLevel,
        'riskPoints' => $riskPoints,
        'suggestions' => $suggestions
    ];
}

/**
 * 获取审查列表
 */
function getReviewList() {
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
        $where .= " AND review_status = ?";
        $values[] = $status;
    }
    
    // 获取总数
    $total = $db->query("SELECT COUNT(*) as count FROM contract_reviews $where", $values)->fetch()['count'];
    
    // 获取列表
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT id, contract_name, contract_type, risk_level, review_status, 
                is_ai_review, is_lawyer_review, created_at, updated_at 
         FROM contract_reviews $where 
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
 * 获取审查详情
 */
function getReviewDetail() {
    $user = requireAuth();
    $reviewId = intval(getParam('id'));
    
    if (!$reviewId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $review = $db->query(
        "SELECT * FROM contract_reviews WHERE id = ? AND user_id = ?",
        [$reviewId, $user['id']]
    )->fetch();
    
    if (!$review) {
        error('审查记录不存在');
    }
    
    // 解析JSON字段
    $review['ai_analysis'] = json_decode($review['ai_analysis'], true);
    $review['risk_points'] = json_decode($review['risk_points'], true);
    
    success($review);
}

/**
 * 获取合同模板列表
 */
function getTemplates() {
    $params = getParams();
    $category = $params['category'] ?? null;
    $isPremium = $params['isPremium'] ?? null;
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE status = 1";
    $values = [];
    
    if ($category) {
        $where .= " AND category = ?";
        $values[] = $category;
    }
    
    if ($isPremium !== null) {
        $where .= " AND is_premium = ?";
        $values[] = $isPremium;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM contract_templates $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT id, name, category, subcategory, description, is_premium, price, download_count, created_at 
         FROM contract_templates $where 
         ORDER BY sort_order DESC, created_at DESC LIMIT ? OFFSET ?",
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
 * 获取模板详情
 */
function getTemplateDetail() {
    $templateId = intval(getParam('id'));
    
    if (!$templateId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $template = $db->query(
        "SELECT * FROM contract_templates WHERE id = ? AND status = 1",
        [$templateId]
    )->fetch();
    
    if (!$template) {
        error('模板不存在');
    }
    
    // 增加浏览次数
    $db->query(
        "UPDATE contract_templates SET usage_count = usage_count + 1 WHERE id = ?",
        [$templateId]
    );
    
    success($template);
}

/**
 * 下载模板
 */
function downloadTemplate() {
    $user = requireAuth();
    $templateId = intval(getParam('id'));
    
    if (!$templateId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $template = $db->query(
        "SELECT * FROM contract_templates WHERE id = ? AND status = 1",
        [$templateId]
    )->fetch();
    
    if (!$template) {
        error('模板不存在');
    }
    
    // 检查是否需要付费
    if ($template['is_premium']) {
        // 检查用户是否已购买或是会员
        $hasAccess = false;
        
        // 检查会员
        $subscription = $db->query(
            "SELECT * FROM user_subscriptions 
             WHERE user_id = ? AND status = 1 AND end_date >= CURDATE()",
            [$user['id']]
        )->fetch();
        
        if ($subscription) {
            $hasAccess = true;
        }
        
        // TODO: 检查是否单独购买
        
        if (!$hasAccess) {
            error('该模板为付费模板，请先购买会员', 403);
        }
    }
    
    // 增加下载次数
    $db->query(
        "UPDATE contract_templates SET download_count = download_count + 1 WHERE id = ?",
        [$templateId]
    );
    
    logOperation($user['id'], 'downloadTemplate', 'contract', '下载模板: ' . $template['name']);
    
    success([
        'downloadUrl' => $template['file_url'],
        'name' => $template['name']
    ]);
}

/**
 * AI审查接口（直接提交文本）
 */
function aiReview() {
    $user = requireAuth();
    $params = getParams();
    
    $content = $params['content'] ?? '';
    $contractName = $params['name'] ?? '未命名合同';
    
    if (empty($content)) {
        error('请输入合同内容');
    }
    
    // 检查用户会员状态
    $db = Database::getInstance();
    $subscription = $db->query(
        "SELECT * FROM user_subscriptions 
         WHERE user_id = ? AND status = 1 AND end_date >= CURDATE() 
         AND contract_review_used < contract_review_limit
         ORDER BY end_date DESC LIMIT 1",
        [$user['id']]
    )->fetch();
    
    if (!$subscription) {
        // 检查免费试用
        $freeTrialUsed = $db->query(
            "SELECT COUNT(*) as count FROM contract_reviews WHERE user_id = ? AND is_ai_review = 1",
            [$user['id']]
        )->fetch()['count'];
        
        if ($freeTrialUsed >= 1) {
            error('您的合同审查次数已用完，请购买会员');
        }
    }
    
    // 执行AI审查
    $aiResult = simulateAIReview($content);
    
    // 保存记录
    $db->query(
        "INSERT INTO contract_reviews (user_id, contract_name, content, ai_analysis, risk_level, risk_points, suggestions, review_status, is_ai_review, ai_review_at, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 2, 1, NOW(), NOW())",
        [
            $user['id'],
            $contractName,
            $content,
            json_encode($aiResult['analysis']),
            $aiResult['riskLevel'],
            json_encode($aiResult['riskPoints']),
            $aiResult['suggestions']
        ]
    );
    
    $reviewId = $db->lastInsertId();
    
    // 更新会员使用次数
    if ($subscription) {
        $db->query(
            "UPDATE user_subscriptions 
             SET contract_review_used = contract_review_used + 1 
             WHERE id = ?",
            [$subscription['id']]
        );
    }
    
    logOperation($user['id'], 'aiReview', 'contract', 'AI审查合同: ' . $contractName);
    
    success([
        'reviewId' => $reviewId,
        'result' => $aiResult
    ], '审查完成');
}
?>
