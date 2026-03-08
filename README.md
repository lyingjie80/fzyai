# 法智云 - AI驱动的中小企业法律服务平台

<p align="center">
  <img src="frontend/user/assets/images/favicon.png" width="120" alt="法智云Logo">
</p>

<p align="center">
  <strong>让每家小微企业，都有自己的AI法务部</strong>
</p>

<p align="center">
  <a href="#功能特色">功能特色</a> •
  <a href="#技术架构">技术架构</a> •
  <a href="#快速开始">快速开始</a> •
  <a href="#部署指南">部署指南</a> •
  <a href="#使用说明">使用说明</a>
</p>

---

## 项目简介

法智云是一个专注于中小企业的AI法律服务平台，提供合同审查、法律咨询、文书生成、合规体检等一站式法律服务。通过人工智能技术，降低法律服务门槛，让每一家企业都能享受到专业、高效、经济的法律服务。

## 功能特色

### 🤖 AI合同审查
- 3分钟智能审查合同
- 自动识别风险点
- 提供专业修改建议
- 支持多种合同类型

### 💬 AI法律咨询
- 7×24小时在线问答
- 覆盖劳动、合同、知识产权等多领域
- 即时响应，无需等待
- 可转接专业律师

### 📝 法律文书生成
- 起诉状、律师函、授权委托书等常用文书
- 一键生成，专业规范
- 海量文书模板
- 即下即用

### 🔍 合规体检
- 全面扫描企业合规风险
- 劳动用工合规检查
- 生成详细体检报告
- 提供整改建议

### 👨‍⚖️ 专业律师团队
- 平台认证专业律师
- 深度法律服务
- 一对一解答
- 透明收费标准

## 技术架构

### 前端技术栈
- **Vue 2** - 渐进式JavaScript框架
- **jQuery** - DOM操作和Ajax请求
- **Tailwind CSS** - 实用优先的CSS框架
- **Font Awesome** - 图标库

### 后端技术栈
- **PHP 8.2** - 服务端脚本语言
- **MariaDB 10.11** - 关系型数据库
- **Redis** - 缓存和会话存储
- **Nginx** - Web服务器和反向代理

### 部署架构
- **Docker** - 容器化部署
- **Docker Compose** - 多容器编排
- **腾讯云轻量应用服务器** - 云主机
- **Rocky Linux 9.4** - 操作系统

## 项目结构

```
fazhiyun/
├── docker/                 # Docker配置
│   └── php/               # PHP Dockerfile
├── nginx/                 # Nginx配置
│   └── conf.d/           # 站点配置
├── backend/              # 后端代码
│   └── api/             # API接口
│       ├── config/      # 配置文件
│       ├── includes/    # 公共类库
│       ├── modules/     # 功能模块
│       └── uploads/     # 上传文件
├── frontend/            # 前端代码
│   ├── user/           # 用户端
│   │   ├── index.html  # 官网首页
│   │   ├── app.html    # 应用页面
│   │   └── assets/     # 静态资源
│   └── admin/          # 管理员端
│       └── index.html  # 管理后台
├── database/           # 数据库
│   └── init.sql       # 初始化SQL
├── scripts/           # 部署脚本
│   └── deploy.sh     # 一键部署脚本
├── ssl/              # SSL证书
├── docker-compose.yml # Docker编排配置
└── README.md         # 项目说明
```

## 快速开始

### 环境要求

- Docker 20.10+
- Docker Compose 2.0+
- 2核4G以上服务器配置
- 20GB以上磁盘空间

### 本地开发

1. 克隆项目
```bash
git clone https://github.com/your-repo/fazhiyun.git
cd fazhiyun
```

2. 启动服务
```bash
docker-compose up -d
```

3. 访问服务
- 用户端: http://localhost
- 管理端: http://localhost/admin
- phpMyAdmin: http://localhost:8080

### 生产部署

#### 方式一：使用部署脚本

```bash
# 上传项目到服务器
scp -r fazhiyun root@your-server-ip:/opt/

# 登录服务器
ssh root@your-server-ip

# 运行部署脚本
cd /opt/fazhiyun
chmod +x scripts/deploy.sh
./scripts/deploy.sh
```

#### 方式二：手动部署

1. 安装Docker和Docker Compose
```bash
# Rocky Linux 9.4
dnf update -y
dnf install -y docker docker-compose
systemctl start docker
systemctl enable docker
```

2. 配置SSL证书
```bash
# 将SSL证书放入ssl目录
# ssl/fzyai.cn.crt
# ssl/fzyai.cn.key
```

3. 启动服务
```bash
docker-compose up -d
```

4. 配置Nginx
```bash
# 参考 nginx/conf.d/default.conf
```

## 部署指南

### 腾讯云轻量应用服务器部署

1. 购买腾讯云轻量应用服务器（推荐2核4G配置）
2. 选择Rocky Linux 9.4镜像
3. 开放80、443、3306、8080端口
4. 按照快速开始步骤部署

### 域名配置

1. 在域名服务商处添加解析记录
```
A记录: www.fzyai.cn -> 服务器IP
A记录: admin.fzyai.cn -> 服务器IP
```

2. 申请SSL证书（推荐使用Let's Encrypt）
```bash
certbot --nginx -d www.fzyai.cn -d admin.fzyai.cn
```

### 数据库配置

默认数据库配置：
- 主机: mariadb
- 端口: 3306
- 数据库: fazhiyun
- 用户名: fzy_user
- 密码: FzyAI@2024!User

## 使用说明

### 用户端

1. 访问 https://www.fzyai.cn
2. 注册账号并登录
3. 选择需要的服务（合同审查、法律咨询、文书生成）
4. 根据提示完成操作

### 管理员端

1. 访问 https://admin.fzyai.cn
2. 使用管理员账号登录
   - 默认账号: admin
   - 默认密码: password
3. 管理用户、订单、内容等

### API接口

API基础路径: `/api/index.php`

主要接口模块：
- `auth` - 用户认证
- `contract` - 合同管理
- `consult` - 法律咨询
- `document` - 文书生成
- `payment` - 支付系统
- `admin` - 管理后台

示例请求：
```bash
# 用户登录
curl -X POST "https://www.fzyai.cn/api/index.php?module=auth&action=login" \
  -H "Content-Type: application/json" \
  -d '{"account":"user@example.com","password":"123456"}'
```

## 系统配置

### 环境变量

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| DB_HOST | 数据库主机 | mariadb |
| DB_NAME | 数据库名 | fazhiyun |
| DB_USER | 数据库用户 | fzy_user |
| DB_PASS | 数据库密码 | FzyAI@2024!User |
| REDIS_HOST | Redis主机 | redis |
| JWT_SECRET | JWT密钥 | 自动生成 |

### 会员价格配置

在数据库 `system_configs` 表中配置：
- `personal_month_price` - 个人月会员价格
- `personal_year_price` - 个人年会员价格
- `enterprise_month_price` - 企业月会员价格
- `enterprise_year_price` - 企业年会员价格

## 开发计划

- [x] 用户系统
- [x] AI合同审查
- [x] AI法律咨询
- [x] 文书生成
- [x] 会员系统
- [x] 支付系统
- [x] 管理后台
- [ ] 律师入驻
- [ ] 在线签约
- [ ] 企业合规体检
- [ ] 移动端APP

## 贡献指南

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 打开 Pull Request

## 许可证

本项目采用 MIT 许可证 - 详见 [LICENSE](LICENSE) 文件

## 联系我们

- 官网: https://www.fzyai.cn
- 邮箱: service@fzyai.cn
- 电话: 400-888-8888

---

<p align="center">
  Made with ❤️ by 法智云团队
</p>
