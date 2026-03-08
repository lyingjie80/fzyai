<?php
/**
 * 支付模块
 */

require_once __DIR__ . '/../includes/functions.php';

$action = getParam('action');

switch ($action) {
    case 'createOrder':
        createOrder();
        break;
    case 'getOrderInfo':
        getOrderInfo();
        break;
    case 'queryOrder':
        queryOrder();
        break;
    case 'cancelOrder':
        cancelOrder();
        break;
    case 'getPlans':
        getPlans();
        break;
    case 'getSubscriptions':
        getSubscriptions();
        break;
    case 'wechatPay':
        wechatPay();
        break;
    case 'alipay':
        alipay();
        break;
    case 'notify':
        handleNotify();
        break;
    case 'getBalance':
        getBalance();
        break;
    case 'getTransactions':
        getTransactions();
        break;
    default:
        error('未知操作');
}

/**
 * 创建订单
 */
function createOrder() {
    $user = requireAuth();
    $params = getParams();
    
    $orderType = $params['orderType'] ?? '';
    $productId = intval($params['productId'] ?? 0);
    $paymentMethod = $params['paymentMethod'] ?? 'wechat';
    
    if (empty($orderType)) {
        error('请选择订单类型');
    }
    
    $db = Database::getInstance();
    
    $productName = '';
    $amount = 0;
    
    // 根据订单类型获取产品信息
    switch ($orderType) {
        case 'subscription':
            $plans = [
                1 => ['name' => '个人月会员', 'price' => 99],
                2 => ['name' => '个人年会员', 'price' => 899],
                3 => ['name' => '企业月会员', 'price' => 299],
                4 => ['name' => '企业年会员', 'price' => 2999]
            ];
            if (!isset($plans[$productId])) {
                error('无效的会员套餐');
            }
            $productName = $plans[$productId]['name'];
            $amount = $plans[$productId]['price'];
            break;
            
        case 'contract':
        case 'consult':
        case 'document':
            // 单次服务
            $productName = '单次' . ($orderType == 'contract' ? '合同审查' : ($orderType == 'consult' ? '法律咨询' : '文书生成'));
            $amount = $orderType == 'contract' ? 29.9 : ($orderType == 'consult' ? 19.9 : 9.9);
            break;
            
        default:
            error('未知的订单类型');
    }
    
    // 创建订单
    $orderNo = generateOrderNo();
    $expireAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $db->query(
        "INSERT INTO payment_orders (order_no, user_id, order_type, product_id, product_name, amount, payment_method, payment_status, expire_at, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())",
        [$orderNo, $user['id'], $orderType, $productId, $productName, $amount, $paymentMethod, $expireAt]
    );
    
    $orderId = $db->lastInsertId();
    
    logOperation($user['id'], 'createOrder', 'payment', '创建订单: ' . $orderNo);
    
    success([
        'orderId' => $orderId,
        'orderNo' => $orderNo,
        'amount' => $amount,
        'productName' => $productName,
        'expireAt' => $expireAt
    ], '订单创建成功');
}

/**
 * 获取订单信息
 */
function getOrderInfo() {
    $user = requireAuth();
    $orderNo = getParam('orderNo');
    
    if (empty($orderNo)) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $order = $db->query(
        "SELECT * FROM payment_orders WHERE order_no = ? AND user_id = ?",
        [$orderNo, $user['id']]
    )->fetch();
    
    if (!$order) {
        error('订单不存在');
    }
    
    success($order);
}

/**
 * 查询订单状态
 */
function queryOrder() {
    $user = requireAuth();
    $orderNo = getParam('orderNo');
    
    if (empty($orderNo)) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $order = $db->query(
        "SELECT order_no, payment_status, paid_at, amount, product_name FROM payment_orders WHERE order_no = ? AND user_id = ?",
        [$orderNo, $user['id']]
    )->fetch();
    
    if (!$order) {
        error('订单不存在');
    }
    
    success($order);
}

/**
 * 取消订单
 */
function cancelOrder() {
    $user = requireAuth();
    $orderNo = getParam('orderNo');
    
    if (empty($orderNo)) {
        error('参数错误');
    }
    
    $db = Database::getInstance();
    $order = $db->query(
        "SELECT * FROM payment_orders WHERE order_no = ? AND user_id = ?",
        [$orderNo, $user['id']]
    )->fetch();
    
    if (!$order) {
        error('订单不存在');
    }
    
    if ($order['payment_status'] != 0) {
        error('订单状态不允许取消');
    }
    
    $db->query(
        "UPDATE payment_orders SET payment_status = 2, updated_at = NOW() WHERE order_no = ?",
        [$orderNo]
    );
    
    success(null, '订单已取消');
}

/**
 * 获取会员套餐
 */
function getPlans() {
    $plans = [
        [
            'id' => 1,
            'type' => 'personal_month',
            'name' => '个人月会员',
            'price' => 99,
            'originalPrice' => 129,
            'period' => '月',
            'features' => [
                'AI合同审查 10次/月',
                'AI法律咨询 无限次',
                '法律文书生成 5次/月',
                '合同模板免费下载',
                '优先客服支持'
            ],
            'recommended' => false
        ],
        [
            'id' => 2,
            'type' => 'personal_year',
            'name' => '个人年会员',
            'price' => 899,
            'originalPrice' => 1188,
            'period' => '年',
            'features' => [
                'AI合同审查 150次/年',
                'AI法律咨询 无限次',
                '法律文书生成 80次/年',
                '合同模板免费下载',
                '专属客服支持',
                '律师咨询9折优惠'
            ],
            'recommended' => true
        ],
        [
            'id' => 3,
            'type' => 'enterprise_month',
            'name' => '企业月会员',
            'price' => 299,
            'originalPrice' => 399,
            'period' => '月',
            'features' => [
                'AI合同审查 50次/月',
                'AI法律咨询 无限次',
                '法律文书生成 20次/月',
                '用工合规体检',
                '团队协作功能',
                '专属客户经理'
            ],
            'recommended' => false
        ],
        [
            'id' => 4,
            'type' => 'enterprise_year',
            'name' => '企业年会员',
            'price' => 2999,
            'originalPrice' => 3588,
            'period' => '年',
            'features' => [
                'AI合同审查 800次/年',
                'AI法律咨询 无限次',
                '法律文书生成 300次/年',
                '用工合规体检',
                '团队协作功能',
                '专属客户经理',
                '律师咨询8折优惠',
                '定制化法律服务'
            ],
            'recommended' => false
        ]
    ];
    
    success($plans);
}

/**
 * 获取用户订阅
 */
function getSubscriptions() {
    $user = requireAuth();
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $total = $db->query(
        "SELECT COUNT(*) as count FROM user_subscriptions WHERE user_id = ?",
        [$user['id']]
    )->fetch()['count'];
    
    $list = $db->query(
        "SELECT * FROM user_subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [$user['id'], $pageSize, $offset]
    )->fetchAll();
    
    // 获取当前有效订阅
    $current = $db->query(
        "SELECT * FROM user_subscriptions 
         WHERE user_id = ? AND status = 1 AND end_date >= CURDATE() 
         ORDER BY end_date DESC LIMIT 1",
        [$user['id']]
    )->fetch();
    
    success([
        'current' => $current,
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
 * 微信支付
 */
function wechatPay() {
    $user = requireAuth();
    $params = getParams();
    $orderNo = $params['orderNo'] ?? '';
    
    if (empty($orderNo)) {
        error('订单号不能为空');
    }
    
    $db = Database::getInstance();
    $order = $db->query(
        "SELECT * FROM payment_orders WHERE order_no = ? AND user_id = ?",
        [$orderNo, $user['id']]
    )->fetch();
    
    if (!$order) {
        error('订单不存在');
    }
    
    if ($order['payment_status'] != 0) {
        error('订单状态错误');
    }
    
    // 模拟微信支付参数
    // 实际应调用微信支付API
    $payParams = [
        'appId' => WECHAT_APPID,
        'timeStamp' => (string)time(),
        'nonceStr' => randomString(16),
        'package' => 'prepay_id=mock_prepay_id_' . $orderNo,
        'signType' => 'RSA',
        'paySign' => 'mock_sign'
    ];
    
    success([
        'orderNo' => $orderNo,
        'amount' => $order['amount'],
        'payParams' => $payParams
    ]);
}

/**
 * 支付宝支付
 */
function alipay() {
    $user = requireAuth();
    $params = getParams();
    $orderNo = $params['orderNo'] ?? '';
    
    if (empty($orderNo)) {
        error('订单号不能为空');
    }
    
    $db = Database::getInstance();
    $order = $db->query(
        "SELECT * FROM payment_orders WHERE order_no = ? AND user_id = ?",
        [$orderNo, $user['id']]
    )->fetch();
    
    if (!$order) {
        error('订单不存在');
    }
    
    // 模拟支付宝支付参数
    $payForm = '<form action="https://openapi.alipay.com/gateway.do" method="POST">';
    $payForm .= '<input type="hidden" name="out_trade_no" value="' . $orderNo . '">';
    $payForm .= '<input type="hidden" name="total_amount" value="' . $order['amount'] . '">';
    $payForm .= '<input type="hidden" name="subject" value="' . $order['product_name'] . '">';
    $payForm .= '</form>';
    
    success([
        'orderNo' => $orderNo,
        'amount' => $order['amount'],
        'payForm' => $payForm
    ]);
}

/**
 * 处理支付通知
 */
function handleNotify() {
    $params = getParams();
    $orderNo = $params['out_trade_no'] ?? $params['out_trade_no'] ?? '';
    $tradeNo = $params['transaction_id'] ?? $params['trade_no'] ?? '';
    $totalAmount = $params['total_fee'] ?? $params['total_amount'] ?? 0;
    $tradeStatus = $params['trade_status'] ?? $params['result_code'] ?? '';
    
    if (empty($orderNo)) {
        error('订单号为空');
    }
    
    $db = Database::getInstance();
    $order = $db->query(
        "SELECT * FROM payment_orders WHERE order_no = ?",
        [$orderNo]
    )->fetch();
    
    if (!$order) {
        error('订单不存在');
    }
    
    if ($order['payment_status'] == 1) {
        success('SUCCESS');
    }
    
    // 验证支付状态
    $successStatus = ['SUCCESS', 'TRADE_SUCCESS', 'TRADE_FINISHED'];
    if (!in_array($tradeStatus, $successStatus)) {
        error('支付未成功');
    }
    
    try {
        $db->beginTransaction();
        
        // 更新订单状态
        $db->query(
            "UPDATE payment_orders 
             SET payment_status = 1, paid_at = NOW(), transaction_id = ?, updated_at = NOW() 
             WHERE order_no = ?",
            [$tradeNo, $orderNo]
        );
        
        // 如果是会员订阅，创建订阅记录
        if ($order['order_type'] == 'subscription') {
            createSubscription($db, $order);
        }
        
        $db->commit();
        
        echo 'SUCCESS';
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        error('处理失败: ' . $e->getMessage());
    }
}

/**
 * 创建订阅
 */
function createSubscription($db, $order) {
    $planConfig = [
        1 => ['plan_type' => 'personal_month', 'name' => '个人月会员', 'days' => 30, 'contract' => 10, 'consult' => 999999, 'doc' => 5],
        2 => ['plan_type' => 'personal_year', 'name' => '个人年会员', 'days' => 365, 'contract' => 150, 'consult' => 999999, 'doc' => 80],
        3 => ['plan_type' => 'enterprise_month', 'name' => '企业月会员', 'days' => 30, 'contract' => 50, 'consult' => 999999, 'doc' => 20],
        4 => ['plan_type' => 'enterprise_year', 'name' => '企业年会员', 'days' => 365, 'contract' => 800, 'consult' => 999999, 'doc' => 300]
    ];
    
    $plan = $planConfig[$order['product_id']] ?? $planConfig[1];
    
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+{$plan['days']} days"));
    
    $db->query(
        "INSERT INTO user_subscriptions (user_id, plan_type, plan_name, price, start_date, end_date, status, contract_review_limit, consult_limit, doc_gen_limit, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())",
        [
            $order['user_id'],
            $plan['plan_type'],
            $plan['name'],
            $order['amount'],
            $startDate,
            $endDate,
            $plan['contract'],
            $plan['consult'],
            $plan['doc']
        ]
    );
}

/**
 * 获取余额
 */
function getBalance() {
    $user = requireAuth();
    
    $db = Database::getInstance();
    $balance = $db->query(
        "SELECT * FROM user_balances WHERE user_id = ?",
        [$user['id']]
    )->fetch();
    
    if (!$balance) {
        success([
            'balance' => 0,
            'frozenAmount' => 0,
            'totalRecharge' => 0,
            'totalConsumption' => 0
        ]);
    }
    
    success([
        'balance' => floatval($balance['balance']),
        'frozenAmount' => floatval($balance['frozen_amount']),
        'totalRecharge' => floatval($balance['total_recharge']),
        'totalConsumption' => floatval($balance['total_consumption'])
    ]);
}

/**
 * 获取交易明细
 */
function getTransactions() {
    $user = requireAuth();
    $params = getParams();
    $page = intval($params['page'] ?? 1);
    $pageSize = intval($params['pageSize'] ?? PAGE_SIZE);
    $type = $params['type'] ?? null;
    
    list($page, $pageSize, $offset) = paginate($page, $pageSize);
    
    $db = Database::getInstance();
    
    $where = "WHERE user_id = ?";
    $values = [$user['id']];
    
    if ($type) {
        $where .= " AND transaction_type = ?";
        $values[] = $type;
    }
    
    $total = $db->query("SELECT COUNT(*) as count FROM balance_transactions $where", $values)->fetch()['count'];
    
    $values[] = $pageSize;
    $values[] = $offset;
    $list = $db->query(
        "SELECT * FROM balance_transactions $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
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
?>
