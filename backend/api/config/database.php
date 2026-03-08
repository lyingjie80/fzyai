<?php
/**
 * 数据库配置文件
 */

// 数据库配置
define('DB_HOST', getenv('DB_HOST') ?: 'mariadb');
define('DB_NAME', getenv('DB_NAME') ?: 'fazhiyun');
define('DB_USER', getenv('DB_USER') ?: 'fzy_user');
define('DB_PASS', getenv('DB_PASS') ?: 'FzyAI@2024!User');
define('DB_CHARSET', 'utf8mb4');

// Redis配置
define('REDIS_HOST', getenv('REDIS_HOST') ?: 'redis');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);

// 站点配置
define('SITE_URL', 'https://www.fzyai.cn');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// JWT配置
define('JWT_SECRET', 'FzyAI_2024_Secret_Key_For_Token_Generation');
define('JWT_EXPIRE', 86400 * 7); // 7天

// 分页配置
define('PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// AI服务配置（模拟）
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_API_URL', getenv('AI_API_URL') ?: '');

// 支付配置
define('WECHAT_MCH_ID', getenv('WECHAT_MCH_ID') ?: '');
define('WECHAT_APPID', getenv('WECHAT_APPID') ?: '');
define('WECHAT_KEY', getenv('WECHAT_KEY') ?: '');
define('ALIPAY_APPID', getenv('ALIPAY_APPID') ?: '');
define('ALIPAY_PRIVATE_KEY', getenv('ALIPAY_PRIVATE_KEY') ?: '');
define('ALIPAY_PUBLIC_KEY', getenv('ALIPAY_PUBLIC_KEY') ?: '');
?>
