<?php
/**
 * 法律文书模块
 */

require_once __DIR__ . '/../includes/functions.php';

$action = getParam('action');

switch ($action) {
    case 'getTemplates':
        getTemplates();
        break;
    case 'getTemplateDetail':
        getTemplateDetail();
        break;
    case 'generate':
        generateDocument();
        break;
    case 'getHistory':
        getHistory();
        break;
    case 'getDetail':
        getDetail();
        break;
    case 'preview':
        previewDocument();
        break;
    case 'download':
        downloadDocument();
        break;
    default:
        error('未知操作');
}

/**
 * 获取文书模板列表
 */
function getTemplates() {
    $params = getParams();
    $category = $params['category'] ?? null;
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
    
    $total = $db->query("SELECT COUNT(*) as count FROM document_templates $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT id, name, category, description, is_premium, price, usage_count, created_at 
         FROM document_templates $where 
         ORDER BY usage_count DESC, created_at DESC LIMIT ? OFFSET ?",
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
        "SELECT * FROM document_templates WHERE id = ? AND status = 1",
        [$templateId]
    )->fetch();
    
    if (!$template) {
        error('模板不存在');
    }
    
    // 解析必填字段
    $template['requiredFields'] = json_decode($template['required_fields'], true) ?: [];
    
    success($template);
}

/**
 * 生成文书
 */
function generateDocument() {
    $user = requireAuth();
    $params = getParams();
    
    $templateId = intval($params['templateId'] ?? 0);
    $parameters = $params['parameters'] ?? [];
    $docName = trim($params['name'] ?? '');
    
    if (!$templateId) {
        error('请选择文书模板');
    }
    
    if (empty($parameters)) {
        error('请填写文书内容');
    }
    
    $db = Database::getInstance();
    
    // 获取模板
    $template = $db->query(
        "SELECT * FROM document_templates WHERE id = ? AND status = 1",
        [$templateId]
    )->fetch();
    
    if (!$template) {
        error('模板不存在');
    }
    
    // 检查权限
    if ($template['is_premium']) {
        $subscription = $db->query(
            "SELECT * FROM user_subscriptions 
             WHERE user_id = ? AND status = 1 AND end_date >= CURDATE()",
            [$user['id']]
        )->fetch();
        
        if (!$subscription) {
            error('该文书为付费模板，请先购买会员', 403);
        }
    }
    
    // 验证必填字段
    $requiredFields = json_decode($template['required_fields'], true) ?: [];
    foreach ($requiredFields as $field) {
        if (empty($parameters[$field])) {
            error("请填写必填字段: {$field}");
        }
    }
    
    // 生成文书内容
    $content = generateDocContent($template['template_content'], $parameters);
    
    // 保存记录
    $db->query(
        "INSERT INTO document_generations (user_id, doc_type, doc_name, template_id, parameters, content, status, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
        [
            $user['id'],
            $template['category'],
            $docName ?: $template['name'],
            $templateId,
            json_encode($parameters),
            $content
        ]
    );
    
    $docId = $db->lastInsertId();
    
    // 更新模板使用次数
    $db->query(
        "UPDATE document_templates SET usage_count = usage_count + 1 WHERE id = ?",
        [$templateId]
    );
    
    logOperation($user['id'], 'generateDocument', 'document', '生成文书: ' . $docName);
    
    success([
        'docId' => $docId,
        'content' => $content,
        'name' => $docName ?: $template['name']
    ], '生成成功');
}

/**
 * 生成文书内容
 */
function generateDocContent($template, $parameters) {
    $content = $template;
    
    foreach ($parameters as $key => $value) {
        $placeholder = '{' . $key . '}';
        $content = str_replace($placeholder, $value, $content);
    }
    
    // 添加文书头部
    $header = "法智云法律文书生成系统\n";
    $header .= "生成时间：" . date('Y年m月d日') . "\n";
    $header .= str_repeat('=', 50) . "\n\n";
    
    // 添加文书尾部
    $footer = "\n\n" . str_repeat('=', 50) . "\n";
    $footer .= "本文书由法智云AI系统生成，仅供参考\n";
    $footer .= "建议咨询专业律师审核后使用\n";
    
    return $header . $content . $footer;
}

/**
 * 获取生成历史
 */
function getHistory() {
    $user = requireAuth();
    $params = getParams();
    
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $total = $db->query(
        "SELECT COUNT(*) as count FROM document_generations WHERE user_id = ?",
        [$user['id']]
    )->fetch()['count'];
    
    $list = $db->query(
        "SELECT dg.id, dg.doc_type, dg.doc_name, dg.status, dg.created_at, dt.name as template_name
         FROM document_generations dg 
         LEFT JOIN document_templates dt ON dg.template_id = dt.id 
         WHERE dg.user_id = ? 
         ORDER BY dg.created_at DESC LIMIT ? OFFSET ?",
        [$user['id'], $pageSize, $offset]
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
 * 获取文书详情
 */
function getDetail() {
    $user = requireAuth();
    $docId = intval(getParam('id'));
    
    if (!$docId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $doc = $db->query(
        "SELECT dg.*, dt.name as template_name 
         FROM document_generations dg 
         LEFT JOIN document_templates dt ON dg.template_id = dt.id 
         WHERE dg.id = ? AND dg.user_id = ?",
        [$docId, $user['id']]
    )->fetch();
    
    if (!$doc) {
        error('文书不存在');
    }
    
    $doc['parameters'] = json_decode($doc['parameters'], true);
    
    success($doc);
}

/**
 * 预览文书
 */
function previewDocument() {
    $params = getParams();
    $templateId = intval($params['templateId'] ?? 0);
    $parameters = $params['parameters'] ?? [];
    
    if (!$templateId) {
        error('请选择模板');
    }
    
    $db = Database::getInstance();
    $template = $db->query(
        "SELECT template_content FROM document_templates WHERE id = ? AND status = 1",
        [$templateId]
    )->fetch();
    
    if (!$template) {
        error('模板不存在');
    }
    
    $content = generateDocContent($template['template_content'], $parameters);
    
    success(['content' => $content]);
}

/**
 * 下载文书
 */
function downloadDocument() {
    $user = requireAuth();
    $docId = intval(getParam('id'));
    
    if (!$docId) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $doc = $db->query(
        "SELECT * FROM document_generations WHERE id = ? AND user_id = ?",
        [$docId, $user['id']]
    )->fetch();
    
    if (!$doc) {
        error('文书不存在');
    }
    
    // 生成Word文档（简化版，实际应使用PHPWord等库）
    $filename = $doc['doc_name'] . '_' . date('Ymd') . '.txt';
    $content = $doc['content'];
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    
    echo $content;
    exit;
}
?>
