#!/bin/bash

# 法智云部署脚本
# 适用于腾讯云轻量应用服务器 + Rocky Linux 9.4

set -e

echo "=========================================="
echo "法智云 - 部署脚本"
echo "=========================================="

# 配置
DOMAIN="www.fzyai.cn"
ADMIN_DOMAIN="admin.fzyai.cn"
PROJECT_DIR="/opt/fazhiyun"

# 检查root权限
if [ "$EUID" -ne 0 ]; then
    echo "请使用root权限运行此脚本"
    exit 1
fi

echo "[1/8] 更新系统..."
dnf update -y

echo "[2/8] 安装必要软件..."
dnf install -y epel-release
dnf install -y nginx git curl wget vim

# 安装Docker
echo "[3/8] 安装Docker..."
if ! command -v docker &> /dev/null; then
    dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
    dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
    systemctl start docker
    systemctl enable docker
fi

# 安装Docker Compose
echo "[4/8] 安装Docker Compose..."
if ! command -v docker-compose &> /dev/null; then
    curl -L "https://github.com/docker/compose/releases/download/v2.23.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    ln -sf /usr/local/bin/docker-compose /usr/bin/docker-compose
fi

# 创建项目目录
echo "[5/8] 创建项目目录..."
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# 复制项目文件
echo "[6/8] 部署项目文件..."
# 假设项目文件已通过git或其他方式上传到服务器
# git clone https://your-repo.git . || true

# 创建SSL目录
mkdir -p ssl

# 生成自签名证书（仅用于测试，生产环境请使用Let's Encrypt或购买证书）
if [ ! -f "ssl/fzyai.cn.crt" ]; then
    echo "生成自签名SSL证书..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout ssl/fzyai.cn.key \
        -out ssl/fzyai.cn.crt \
        -subj "/C=CN/ST=Beijing/L=Beijing/O=Fazhiyun/OU=IT/CN=www.fzyai.cn"
fi

# 设置权限
chmod 600 ssl/*.key
chmod 644 ssl/*.crt

# 启动Docker容器
echo "[7/8] 启动Docker容器..."
docker-compose down 2>/dev/null || true
docker-compose up -d --build

# 等待数据库启动
echo "等待数据库启动..."
sleep 10

# 配置Nginx
echo "[8/8] 配置Nginx..."
cat > /etc/nginx/nginx.conf << 'EOF'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    
    access_log /var/log/nginx/access.log main;
    
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript;
    
    # 用户端
    server {
        listen 80;
        server_name www.fzyai.cn fzyai.cn;
        return 301 https://$server_name$request_uri;
    }
    
    server {
        listen 443 ssl http2;
        server_name www.fzyai.cn fzyai.cn;
        
        ssl_certificate /opt/fazhiyun/ssl/fzyai.cn.crt;
        ssl_certificate_key /opt/fazhiyun/ssl/fzyai.cn.key;
        ssl_session_timeout 1d;
        ssl_session_cache shared:SSL:50m;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
        ssl_prefer_server_ciphers off;
        
        root /opt/fazhiyun/frontend/user;
        index index.html;
        
        location / {
            try_files $uri $uri/ /index.html;
        }
        
        location /api/ {
            proxy_pass http://localhost:8080/api/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
        
        location /uploads/ {
            alias /opt/fazhiyun/backend/api/uploads/;
        }
    }
    
    # 管理端
    server {
        listen 80;
        server_name admin.fzyai.cn;
        return 301 https://$server_name$request_uri;
    }
    
    server {
        listen 443 ssl http2;
        server_name admin.fzyai.cn;
        
        ssl_certificate /opt/fazhiyun/ssl/fzyai.cn.crt;
        ssl_certificate_key /opt/fazhiyun/ssl/fzyai.cn.key;
        
        root /opt/fazhiyun/frontend/admin;
        index index.html;
        
        location / {
            try_files $uri $uri/ /index.html;
        }
        
        location /api/ {
            proxy_pass http://localhost:8080/api/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
    }
}
EOF

# 测试Nginx配置
nginx -t

# 启动Nginx
systemctl restart nginx
systemctl enable nginx

# 配置防火墙
echo "配置防火墙..."
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload

echo ""
echo "=========================================="
echo "部署完成！"
echo "=========================================="
echo ""
echo "访问地址:"
echo "  用户端: https://www.fzyai.cn"
echo "  管理端: https://admin.fzyai.cn"
echo "  phpMyAdmin: http://your-server-ip:8080"
echo ""
echo "默认管理员账号:"
echo "  用户名: admin"
echo "  密码: password"
echo ""
echo "数据库信息:"
echo "  主机: localhost:3306"
echo "  数据库: fazhiyun"
echo "  用户名: fzy_user"
echo "  密码: FzyAI@2024!User"
echo ""
echo "=========================================="
