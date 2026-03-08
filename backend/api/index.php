<?php
/**
 * 法智云 API 入口文件
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 设置字符编码
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 引入函数库
require_once __DIR__ . '/includes/functions.php';

// 获取路由参数
$module = getParam('module', '');
$action = getParam('action', '');

// 路由分发
try {
    switch ($module) {
        case 'auth':
            require_once __DIR__ . '/modules/auth.php';
            break;
            
        case 'contract':
            require_once __DIR__ . '/modules/contract.php';
            break;
            
        case 'consult':
            require_once __DIR__ . '/modules/consult.php';
            break;
            
        case 'document':
            require_once __DIR__ . '/modules/document.php';
            break;
            
        case 'payment':
            require_once __DIR__ . '/modules/payment.php';
            break;
            
        case 'admin':
            require_once __DIR__ . '/modules/admin.php';
            break;
            
        case 'common':
            handleCommon();
            break;
            
        default:
            // 返回API信息
            success([
                'name' => '法智云 API',
                'version' => '1.0.0',
                'description' => 'AI驱动的中小企业法律服务平台',
                'modules' => [
                    'auth' => '用户认证',
                    'contract' => '合同管理',
                    'consult' => '法律咨询',
                    'document' => '法律文书',
                    'payment' => '支付系统',
                    'admin' => '管理后台'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    error('服务器内部错误', 500);
}

/**
 * 通用接口
 */
function handleCommon() {
    $action = getParam('action');
    
    switch ($action) {
        case 'getBanners':
            getBanners();
            break;
        case 'getArticles':
            getArticles();
        case 'getArticleDetail':
            getArticleDetail();
            break;
        case 'getStats':
            getStats();
            break;
        case 'upload':
            handleUpload();
            break;
        case 'contact':
            submitContact();
            break;
        default:
            error('未知操作');
    }
}

/**
 * 获取轮播图
 */
function getBanners() {
    $position = getParam('position', 'home');
    
    $db = Database::getInstance();
    $banners = $db->query(
        "SELECT id, title, image_url, link_url, sort_order 
         FROM banners 
         WHERE position = ? AND status = 1 
         AND (start_at IS NULL OR start_at <= NOW()) 
         AND (end_at IS NULL OR end_at >= NOW()) 
         ORDER BY sort_order ASC",
        [$position]
    )->fetchAll();
    
    success($banners);
}

/**
 * 获取文章列表
 */
function getArticles() {
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
    
    $total = $db->query("SELECT COUNT(*) as count FROM articles $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT id, title, category, summary, cover_image, author, view_count, published_at 
         FROM articles $where 
         ORDER BY is_top DESC, published_at DESC LIMIT ? OFFSET ?",
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
 * 获取文章详情
 */
function getArticleDetail() {
    $id = intval(getParam('id'));
    
    if (!$id) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $article = $db->query(
        "SELECT * FROM articles WHERE id = ? AND status = 1",
        [$id]
    )->fetch();
    
    if (!$article) {
        error('文章不存在');
    }
    
    // 增加浏览次数
    $db->query("UPDATE articles SET view_count = view_count + 1 WHERE id = ?", [$id]);
    
    success($article);
}

/**
 * 获取统计数据
 */
function getStats() {
    $db = Database::getInstance();
    
    $stats = [
        'users' => $db->query("SELECT COUNT(*) as count FROM users WHERE status = 1")->fetch()['count'],
        'contracts' => $db->query("SELECT COUNT(*) as count FROM contract_reviews")->fetch()['count'],
        'consults' => $db->query("SELECT COUNT(*) as count FROM legal_consults")->fetch()['count'],
        'documents' => $db->query("SELECT COUNT(*) as count FROM document_generations")->fetch()['count'],
        'lawyers' => $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 3 AND status = 1")->fetch()['count']
    ];
    
    success($stats);
}

/**
 * 处理文件上传
 */
function handleUpload() {
    $user = getCurrentUser();
    if (!$user) {
        error('请先登录', 401);
    }
    
    if (!isset($_FILES['file'])) {
        error('请选择要上传的文件');
    }
    
    $dir = getParam('dir', 'files');
    $result = uploadFile($_FILES['file'], $dir);
    
    if (!$result['success']) {
        error($result['message']);
    }
    
    success([
        'url' => $result['url'],
        'name' => $result['filename'],
        'size' => $result['size']
    ]);
}

/**
 * 提交联系表单
 */
function submitContact() {
    $params = getParams();
    
    $name = trim($params['name'] ?? '');
    $email = trim($params['email'] ?? '');
    $phone = trim($params['phone'] ?? '');
    $message = trim($params['message'] ?? '');
    
    if (empty($name) || empty($message)) {
        error('请填写完整信息');
    }
    
    // TODO: 发送邮件或保存到数据库
    
    success(null, '提交成功，我们会尽快与您联系');
}
?>
