-- 法智云数据库初始化脚本
-- 创建数据库
CREATE DATABASE IF NOT EXISTS fazhiyun CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fazhiyun;

-- ============================================
-- 用户相关表
-- ============================================

-- 用户表
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT '邮箱',
    phone VARCHAR(20) UNIQUE COMMENT '手机号',
    password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希',
    nickname VARCHAR(50) COMMENT '昵称',
    avatar VARCHAR(255) COMMENT '头像URL',
    user_type TINYINT DEFAULT 1 COMMENT '用户类型：1个人 2企业 3律师 4管理员',
    status TINYINT DEFAULT 1 COMMENT '状态：0禁用 1正常 2待审核',
    email_verified TINYINT DEFAULT 0 COMMENT '邮箱验证状态',
    phone_verified TINYINT DEFAULT 0 COMMENT '手机验证状态',
    real_name VARCHAR(50) COMMENT '真实姓名',
    id_card VARCHAR(18) COMMENT '身份证号',
    company_name VARCHAR(100) COMMENT '公司名称',
    company_code VARCHAR(50) COMMENT '统一社会信用代码',
    business_license VARCHAR(255) COMMENT '营业执照URL',
    lawyer_license VARCHAR(255) COMMENT '律师执业证URL',
    lawyer_firm VARCHAR(100) COMMENT '所属律所',
    lawyer_title VARCHAR(50) COMMENT '律师职称',
    specialty VARCHAR(255) COMMENT '专业领域',
    province VARCHAR(50) COMMENT '省份',
    city VARCHAR(50) COMMENT '城市',
    address VARCHAR(255) COMMENT '详细地址',
    last_login_at DATETIME COMMENT '最后登录时间',
    last_login_ip VARCHAR(50) COMMENT '最后登录IP',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_status (status),
    INDEX idx_phone (phone),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 用户会员订阅表
CREATE TABLE user_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    plan_type VARCHAR(50) NOT NULL COMMENT '套餐类型：personal_month personal_year enterprise_month enterprise_year',
    plan_name VARCHAR(100) NOT NULL COMMENT '套餐名称',
    price DECIMAL(10,2) NOT NULL COMMENT '价格',
    start_date DATE NOT NULL COMMENT '开始日期',
    end_date DATE NOT NULL COMMENT '结束日期',
    status TINYINT DEFAULT 1 COMMENT '状态：0已过期 1有效 2已取消',
    payment_id INT UNSIGNED COMMENT '支付记录ID',
    contract_review_limit INT DEFAULT 0 COMMENT '合同审查次数限制',
    contract_review_used INT DEFAULT 0 COMMENT '已使用合同审查次数',
    consult_limit INT DEFAULT 0 COMMENT '咨询次数限制',
    consult_used INT DEFAULT 0 COMMENT '已使用咨询次数',
    doc_gen_limit INT DEFAULT 0 COMMENT '文书生成次数限制',
    doc_gen_used INT DEFAULT 0 COMMENT '已使用文书生成次数',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户会员订阅表';

-- ============================================
-- 合同相关表
-- ============================================

-- 合同审查记录表
CREATE TABLE contract_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    contract_name VARCHAR(255) NOT NULL COMMENT '合同名称',
    contract_type VARCHAR(50) COMMENT '合同类型',
    original_file VARCHAR(255) NOT NULL COMMENT '原文件URL',
    file_size INT UNSIGNED COMMENT '文件大小(字节)',
    content TEXT COMMENT '合同内容文本',
    ai_analysis TEXT COMMENT 'AI分析结果JSON',
    risk_level TINYINT COMMENT '风险等级：1低 2中 3高',
    risk_points TEXT COMMENT '风险点JSON数组',
    suggestions TEXT COMMENT '修改建议',
    review_status TINYINT DEFAULT 0 COMMENT '审查状态：0待审查 1审查中 2已完成 3失败',
    reviewed_by INT UNSIGNED COMMENT '审查律师ID',
    lawyer_opinion TEXT COMMENT '律师意见',
    is_ai_review TINYINT DEFAULT 1 COMMENT '是否AI审查',
    is_lawyer_review TINYINT DEFAULT 0 COMMENT '是否律师审查',
    ai_review_at DATETIME COMMENT 'AI审查时间',
    lawyer_review_at DATETIME COMMENT '律师审查时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (review_status),
    INDEX idx_contract_type (contract_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同审查记录表';

-- 合同模板表
CREATE TABLE contract_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT '模板名称',
    category VARCHAR(50) NOT NULL COMMENT '分类',
    subcategory VARCHAR(50) COMMENT '子分类',
    description TEXT COMMENT '模板描述',
    content TEXT NOT NULL COMMENT '模板内容',
    file_url VARCHAR(255) COMMENT '文件下载URL',
    is_premium TINYINT DEFAULT 0 COMMENT '是否付费模板',
    price DECIMAL(10,2) DEFAULT 0 COMMENT '价格',
    download_count INT UNSIGNED DEFAULT 0 COMMENT '下载次数',
    usage_count INT UNSIGNED DEFAULT 0 COMMENT '使用次数',
    status TINYINT DEFAULT 1 COMMENT '状态：0下架 1上架',
    created_by INT UNSIGNED COMMENT '创建者ID',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_is_premium (is_premium)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同模板表';

-- ============================================
-- 法律咨询相关表
-- ============================================

-- 法律咨询表
CREATE TABLE legal_consults (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    title VARCHAR(255) NOT NULL COMMENT '咨询标题',
    content TEXT NOT NULL COMMENT '咨询内容',
    category VARCHAR(50) COMMENT '咨询分类',
    urgency TINYINT DEFAULT 1 COMMENT '紧急程度：1普通 2紧急 3特急',
    is_public TINYINT DEFAULT 0 COMMENT '是否公开',
    status TINYINT DEFAULT 0 COMMENT '状态：0待回复 1已回复 2已解决 3已关闭',
    ai_reply TEXT COMMENT 'AI回复内容',
    lawyer_reply TEXT COMMENT '律师回复内容',
    replied_by INT UNSIGNED COMMENT '回复律师ID',
    reply_at DATETIME COMMENT '回复时间',
    satisfaction TINYINT COMMENT '满意度：1-5星',
    view_count INT UNSIGNED DEFAULT 0 COMMENT '浏览次数',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='法律咨询表';

-- 咨询对话记录表
CREATE TABLE consult_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consult_id INT UNSIGNED NOT NULL COMMENT '咨询ID',
    sender_id INT UNSIGNED NOT NULL COMMENT '发送者ID',
    sender_type TINYINT NOT NULL COMMENT '发送者类型：1用户 2AI 3律师',
    content TEXT NOT NULL COMMENT '消息内容',
    attachments TEXT COMMENT '附件JSON',
    is_read TINYINT DEFAULT 0 COMMENT '是否已读',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consult_id) REFERENCES legal_consults(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_consult_id (consult_id),
    INDEX idx_sender_id (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='咨询对话记录表';

-- ============================================
-- 法律文书相关表
-- ============================================

-- 法律文书生成记录表
CREATE TABLE document_generations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    doc_type VARCHAR(50) NOT NULL COMMENT '文书类型',
    doc_name VARCHAR(255) NOT NULL COMMENT '文书名称',
    template_id INT UNSIGNED COMMENT '使用的模板ID',
    parameters TEXT COMMENT '生成参数JSON',
    content TEXT COMMENT '生成的文书内容',
    file_url VARCHAR(255) COMMENT '文件下载URL',
    status TINYINT DEFAULT 0 COMMENT '状态：0生成中 1已完成 2失败',
    ai_model VARCHAR(50) COMMENT '使用的AI模型',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_doc_type (doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='法律文书生成记录表';

-- 文书模板表
CREATE TABLE document_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT '模板名称',
    category VARCHAR(50) NOT NULL COMMENT '分类',
    description TEXT COMMENT '模板描述',
    template_content TEXT NOT NULL COMMENT '模板内容（含变量）',
    required_fields TEXT COMMENT '必填字段JSON',
    sample_url VARCHAR(255) COMMENT '示例文件URL',
    is_premium TINYINT DEFAULT 0 COMMENT '是否付费',
    price DECIMAL(10,2) DEFAULT 0 COMMENT '价格',
    usage_count INT UNSIGNED DEFAULT 0 COMMENT '使用次数',
    status TINYINT DEFAULT 1 COMMENT '状态：0下架 1上架',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文书模板表';

-- ============================================
-- 支付相关表
-- ============================================

-- 支付订单表
CREATE TABLE payment_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(64) NOT NULL UNIQUE COMMENT '订单号',
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    order_type VARCHAR(50) NOT NULL COMMENT '订单类型：subscription contract consult document',
    product_id INT UNSIGNED COMMENT '产品ID',
    product_name VARCHAR(255) NOT NULL COMMENT '产品名称',
    amount DECIMAL(10,2) NOT NULL COMMENT '金额',
    currency VARCHAR(10) DEFAULT 'CNY' COMMENT '货币',
    payment_method VARCHAR(50) COMMENT '支付方式：wechat alipay',
    payment_status TINYINT DEFAULT 0 COMMENT '支付状态：0待支付 1已支付 2已取消 3已退款',
    paid_at DATETIME COMMENT '支付时间',
    transaction_id VARCHAR(255) COMMENT '第三方支付流水号',
    refund_amount DECIMAL(10,2) DEFAULT 0 COMMENT '退款金额',
    refund_at DATETIME COMMENT '退款时间',
    refund_reason VARCHAR(255) COMMENT '退款原因',
    expire_at DATETIME COMMENT '订单过期时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_order_no (order_no),
    INDEX idx_user_id (user_id),
    INDEX idx_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付订单表';

-- 用户余额表
CREATE TABLE user_balances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    balance DECIMAL(10,2) DEFAULT 0 COMMENT '余额',
    frozen_amount DECIMAL(10,2) DEFAULT 0 COMMENT '冻结金额',
    total_recharge DECIMAL(10,2) DEFAULT 0 COMMENT '累计充值',
    total_consumption DECIMAL(10,2) DEFAULT 0 COMMENT '累计消费',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户余额表';

-- 余额明细表
CREATE TABLE balance_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    transaction_type VARCHAR(50) NOT NULL COMMENT '交易类型：recharge consumption refund withdraw',
    amount DECIMAL(10,2) NOT NULL COMMENT '金额（正数收入，负数支出）',
    balance_before DECIMAL(10,2) NOT NULL COMMENT '交易前余额',
    balance_after DECIMAL(10,2) NOT NULL COMMENT '交易后余额',
    related_order_id INT UNSIGNED COMMENT '关联订单ID',
    remark VARCHAR(255) COMMENT '备注',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (transaction_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='余额明细表';

-- ============================================
-- 律师相关表
-- ============================================

-- 律师委托表
CREATE TABLE lawyer_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lawyer_id INT UNSIGNED NOT NULL COMMENT '律师ID',
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    assignment_type VARCHAR(50) NOT NULL COMMENT '委托类型：contract consult document',
    related_id INT UNSIGNED NOT NULL COMMENT '关联记录ID',
    status TINYINT DEFAULT 0 COMMENT '状态：0待处理 1处理中 2已完成 3已拒绝',
    fee DECIMAL(10,2) COMMENT '律师费用',
    deadline DATETIME COMMENT '截止日期',
    completed_at DATETIME COMMENT '完成时间',
    rating TINYINT COMMENT '评分',
    comment TEXT COMMENT '评价内容',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lawyer_id (lawyer_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='律师委托表';

-- 律师提现表
CREATE TABLE lawyer_withdrawals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lawyer_id INT UNSIGNED NOT NULL COMMENT '律师ID',
    amount DECIMAL(10,2) NOT NULL COMMENT '提现金额',
    fee DECIMAL(10,2) DEFAULT 0 COMMENT '手续费',
    actual_amount DECIMAL(10,2) NOT NULL COMMENT '实际到账金额',
    bank_name VARCHAR(100) COMMENT '银行名称',
    bank_account VARCHAR(50) COMMENT '银行账号',
    account_name VARCHAR(50) COMMENT '账户名',
    status TINYINT DEFAULT 0 COMMENT '状态：0待审核 1审核通过 2已打款 3已拒绝',
    reviewed_by INT UNSIGNED COMMENT '审核人ID',
    reviewed_at DATETIME COMMENT '审核时间',
    remark VARCHAR(255) COMMENT '备注',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_lawyer_id (lawyer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='律师提现表';

-- ============================================
-- 系统相关表
-- ============================================

-- 系统配置表
CREATE TABLE system_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
    config_value TEXT COMMENT '配置值',
    description VARCHAR(255) COMMENT '描述',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- 轮播图表
CREATE TABLE banners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) COMMENT '标题',
    image_url VARCHAR(255) NOT NULL COMMENT '图片URL',
    link_url VARCHAR(255) COMMENT '链接URL',
    position VARCHAR(50) DEFAULT 'home' COMMENT '位置',
    sort_order INT DEFAULT 0 COMMENT '排序',
    status TINYINT DEFAULT 1 COMMENT '状态：0禁用 1启用',
    start_at DATETIME COMMENT '开始时间',
    end_at DATETIME COMMENT '结束时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_position (position),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='轮播图表';

-- 文章/帮助中心表
CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT '标题',
    category VARCHAR(50) NOT NULL COMMENT '分类',
    content TEXT NOT NULL COMMENT '内容',
    summary TEXT COMMENT '摘要',
    cover_image VARCHAR(255) COMMENT '封面图',
    author VARCHAR(50) COMMENT '作者',
    view_count INT UNSIGNED DEFAULT 0 COMMENT '浏览次数',
    is_top TINYINT DEFAULT 0 COMMENT '是否置顶',
    status TINYINT DEFAULT 1 COMMENT '状态：0草稿 1已发布 2已下架',
    published_at DATETIME COMMENT '发布时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表';

-- 操作日志表
CREATE TABLE operation_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED COMMENT '用户ID',
    user_type TINYINT DEFAULT 1 COMMENT '用户类型',
    action VARCHAR(100) NOT NULL COMMENT '操作',
    module VARCHAR(50) COMMENT '模块',
    description TEXT COMMENT '描述',
    ip_address VARCHAR(50) COMMENT 'IP地址',
    user_agent TEXT COMMENT '用户代理',
    request_data TEXT COMMENT '请求数据',
    response_data TEXT COMMENT '响应数据',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ============================================
-- 插入初始数据
-- ============================================

-- 插入默认管理员
INSERT INTO users (username, email, phone, password_hash, nickname, user_type, status, email_verified, phone_verified, created_at) VALUES
('admin', 'admin@fzyai.cn', '13800138000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员', 4, 1, 1, 1, NOW());

-- 插入系统配置
INSERT INTO system_configs (config_key, config_value, description) VALUES
('site_name', '法智云', '网站名称'),
('site_logo', '/assets/images/logo.png', '网站Logo'),
('site_icp', '', 'ICP备案号'),
('contact_phone', '400-888-8888', '联系电话'),
('contact_email', 'service@fzyai.cn', '联系邮箱'),
('ai_review_enabled', '1', 'AI合同审查是否启用'),
('ai_consult_enabled', '1', 'AI法律咨询是否启用'),
('ai_doc_gen_enabled', '1', 'AI文书生成是否启用'),
('free_trial_enabled', '1', '免费试用是否启用'),
('free_trial_contract_count', '1', '免费试用合同审查次数'),
('wechat_pay_enabled', '1', '微信支付是否启用'),
('alipay_enabled', '1', '支付宝是否启用'),
('personal_month_price', '99', '个人月会员价格'),
('personal_year_price', '899', '个人年会员价格'),
('enterprise_month_price', '299', '企业月会员价格'),
('enterprise_year_price', '2999', '企业年会员价格');

-- 插入合同模板分类数据
INSERT INTO contract_templates (name, category, subcategory, description, content, is_premium, price, status, created_at) VALUES
('劳动合同模板', '劳动用工', '劳动合同', '标准劳动合同模板，适用于企业与员工签订劳动合同', '劳动合同正文内容...', 0, 0, 1, NOW()),
('房屋租赁合同', '房产租赁', '住宅租赁', '标准房屋租赁合同模板', '房屋租赁合同正文内容...', 0, 0, 1, NOW()),
('借款合同模板', '金融借贷', '个人借款', '个人借款合同标准模板', '借款合同正文内容...', 0, 0, 1, NOW()),
('股权转让协议', '公司股权', '股权转让', '公司股权转让协议模板，专业版', '股权转让协议正文内容...', 1, 99, 1, NOW()),
('保密协议模板', '商业合作', '保密协议', '员工保密协议及竞业限制协议', '保密协议正文内容...', 0, 0, 1, NOW()),
('服务合同模板', '商业合作', '服务合同', '通用服务合同模板', '服务合同正文内容...', 0, 0, 1, NOW());

-- 插入文书模板数据
INSERT INTO document_templates (name, category, description, template_content, required_fields, is_premium, price, status, created_at) VALUES
('起诉状', '诉讼文书', '民事起诉状标准模板', '起诉状模板内容...', '["原告姓名","被告姓名","案由","诉讼请求","事实与理由"]', 0, 0, 1, NOW()),
('律师函', '律师文书', '律师函标准模板', '律师函模板内容...', '["委托人","被函告人","事由","要求"]', 1, 49, 1, NOW()),
('授权委托书', '委托文书', '授权委托书模板', '授权委托书模板内容...', '["委托人","受托人","委托事项","委托权限"]', 0, 0, 1, NOW()),
('仲裁申请书', '仲裁文书', '仲裁申请书模板', '仲裁申请书模板内容...', '["申请人","被申请人","仲裁请求","事实与理由"]', 0, 0, 1, NOW());

-- 插入帮助文章
INSERT INTO articles (title, category, content, summary, author, status, published_at, created_at) VALUES
('如何使用AI合同审查功能', '使用指南', '详细的使用说明...', '快速了解如何使用AI合同审查功能', '法智云团队', 1, NOW(), NOW()),
('会员权益说明', '会员服务', '会员权益详细介绍...', '了解不同会员等级的权益差异', '法智云团队', 1, NOW(), NOW()),
('常见问题解答', '帮助中心', '常见问题汇总...', '使用过程中常见问题的解答', '法智云团队', 1, NOW(), NOW()),
('隐私政策', '法律声明', '隐私政策全文...', '法智云平台隐私政策', '法智云法务', 1, NOW(), NOW()),
('服务条款', '法律声明', '服务条款全文...', '使用法智云服务的条款约定', '法智云法务', 1, NOW(), NOW());
