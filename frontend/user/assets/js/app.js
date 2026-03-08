/**
 * 法智云 - 应用页面JavaScript
 */

new Vue({
    el: '#app',
    data: {
        sidebarOpen: false,
        currentPage: 'dashboard',
        isLoggedIn: false,
        user: {},
        subscription: null,
        unreadCount: 0,
        
        // 合同审查
        contractContent: '',
        isReviewing: false,
        contractRecords: [],
        
        // 法律咨询
        consultCategories: [
            { id: '劳动纠纷', name: '劳动纠纷', icon: 'fas fa-briefcase' },
            { id: '合同纠纷', name: '合同纠纷', icon: 'fas fa-file-signature' },
            { id: '知识产权', name: '知识产权', icon: 'fas fa-lightbulb' },
            { id: '婚姻家庭', name: '婚姻家庭', icon: 'fas fa-heart' },
            { id: '房产纠纷', name: '房产纠纷', icon: 'fas fa-home' },
            { id: '债权债务', name: '债权债务', icon: 'fas fa-money-bill' },
            { id: '交通事故', name: '交通事故', icon: 'fas fa-car' },
            { id: '刑事辩护', name: '刑事辩护', icon: 'fas fa-gavel' },
            { id: '公司法律', name: '公司法律', icon: 'fas fa-building' },
            { id: '其他', name: '其他咨询', icon: 'fas fa-question-circle' }
        ],
        selectedCategory: null,
        chatMessages: [],
        chatInput: '',
        isAiTyping: false,
        
        // 文书生成
        documentTypes: [
            { id: '起诉状', name: '起诉状', icon: 'fas fa-file-alt', color: 'text-blue-600' },
            { id: '律师函', name: '律师函', icon: 'fas fa-envelope', color: 'text-purple-600' },
            { id: '授权委托书', name: '授权委托书', icon: 'fas fa-handshake', color: 'text-green-600' },
            { id: '仲裁申请书', name: '仲裁申请书', icon: 'fas fa-balance-scale', color: 'text-orange-600' },
            { id: '答辩状', name: '答辩状', icon: 'fas fa-reply', color: 'text-red-600' },
            { id: '上诉状', name: '上诉状', icon: 'fas fa-arrow-up', color: 'text-indigo-600' },
            { id: '执行申请书', name: '执行申请书', icon: 'fas fa-play-circle', color: 'text-teal-600' },
            { id: '财产保全申请', name: '财产保全申请', icon: 'fas fa-lock', color: 'text-pink-600' }
        ],
        selectedDocType: null,
        docForm: {},
        isGenerating: false,
        generatedDoc: '',
        
        // 合同模板
        templates: [],
        
        // 会员
        plans: [
            { id: 1, name: '个人月会员', price: 99, period: '月', recommended: false, features: ['AI合同审查 10次/月', 'AI法律咨询 无限次', '文书生成 5次/月', '合同模板免费下载'] },
            { id: 2, name: '个人年会员', price: 899, period: '年', recommended: true, features: ['AI合同审查 150次/年', 'AI法律咨询 无限次', '文书生成 80次/年', '律师咨询9折优惠'] },
            { id: 3, name: '企业月会员', price: 299, period: '月', recommended: false, features: ['AI合同审查 50次/月', 'AI法律咨询 无限次', '用工合规体检', '团队协作功能'] },
            { id: 4, name: '企业年会员', price: 2999, period: '年', recommended: false, features: ['AI合同审查 800次/年', '文书生成 300次/年', '律师咨询8折优惠', '定制化法律服务'] }
        ],
        
        // 个人中心
        profileForm: {
            nickname: '',
            realName: '',
            email: '',
            phone: ''
        },
        passwordForm: {
            oldPassword: '',
            newPassword: '',
            confirmPassword: ''
        },
        
        // 提示
        toast: {
            show: false,
            type: 'success',
            message: ''
        },
        
        // 最近记录
        recentRecords: []
    },
    
    computed: {
        pageTitle() {
            const titles = {
                dashboard: '工作台',
                contract: '合同审查',
                consult: '法律咨询',
                document: '文书生成',
                template: '合同模板',
                subscription: '会员中心',
                profile: '个人中心'
            };
            return titles[this.currentPage] || '法智云';
        },
        
        userTypeText() {
            const types = { 1: '个人用户', 2: '企业用户', 3: '律师', 4: '管理员' };
            return types[this.user.userType] || '用户';
        },
        
        selectedCategoryName() {
            const cat = this.consultCategories.find(c => c.id === this.selectedCategory);
            return cat ? cat.name : '';
        },
        
        docFields() {
            const fields = {
                '起诉状': [
                    { name: 'plaintiff', label: '原告姓名/名称', placeholder: '请输入原告姓名或公司名称' },
                    { name: 'defendant', label: '被告姓名/名称', placeholder: '请输入被告姓名或公司名称' },
                    { name: 'caseType', label: '案由', placeholder: '如：合同纠纷、借款纠纷等' },
                    { name: 'claim', label: '诉讼请求', placeholder: '请描述您的诉讼请求' },
                    { name: 'facts', label: '事实与理由', placeholder: '请描述案件事实和理由' }
                ],
                '律师函': [
                    { name: 'client', label: '委托人', placeholder: '请输入委托人姓名或名称' },
                    { name: 'recipient', label: '被函告人', placeholder: '请输入被函告人姓名或名称' },
                    { name: 'matter', label: '事由', placeholder: '请描述发函事由' },
                    { name: 'demand', label: '要求', placeholder: '请描述您的要求' }
                ],
                '授权委托书': [
                    { name: 'principal', label: '委托人', placeholder: '请输入委托人姓名' },
                    { name: 'agent', label: '受托人', placeholder: '请输入受托人姓名' },
                    { name: 'matter', label: '委托事项', placeholder: '请描述委托事项' },
                    { name: 'authority', label: '委托权限', placeholder: '如：全权代理、一般代理等' }
                ],
                '仲裁申请书': [
                    { name: 'applicant', label: '申请人', placeholder: '请输入申请人姓名或名称' },
                    { name: 'respondent', label: '被申请人', placeholder: '请输入被申请人姓名或名称' },
                    { name: 'request', label: '仲裁请求', placeholder: '请描述仲裁请求' },
                    { name: 'facts', label: '事实与理由', placeholder: '请描述案件事实和理由' }
                ]
            };
            return fields[this.selectedDocType] || [
                { name: 'title', label: '标题', placeholder: '请输入文书标题' },
                { name: 'content', label: '内容', placeholder: '请输入文书内容' }
            ];
        }
    },
    
    mounted() {
        this.checkLoginStatus();
    },
    
    methods: {
        // 检查登录状态
        checkLoginStatus() {
            const token = localStorage.getItem('fzy_token');
            if (!token) {
                window.location.href = '/';
                return;
            }
            
            $.ajax({
                url: '/api/index.php?module=auth&action=profile',
                method: 'GET',
                headers: { 'Authorization': 'Bearer ' + token },
                success: (res) => {
                    if (res.code === 200) {
                        this.isLoggedIn = true;
                        this.user = res.data;
                        this.subscription = res.data.subscription;
                        this.profileForm = {
                            nickname: res.data.nickname || '',
                            realName: res.data.realName || '',
                            email: res.data.email || '',
                            phone: res.data.phone || ''
                        };
                        this.loadData();
                    } else {
                        localStorage.removeItem('fzy_token');
                        window.location.href = '/';
                    }
                },
                error: () => {
                    localStorage.removeItem('fzy_token');
                    window.location.href = '/';
                }
            });
        },
        
        // 加载数据
        loadData() {
            this.loadContractRecords();
            this.loadTemplates();
        },
        
        // 导航
        navigate(page) {
            this.currentPage = page;
            this.sidebarOpen = false;
            window.scrollTo(0, 0);
        },
        
        // 退出登录
        logout() {
            localStorage.removeItem('fzy_token');
            window.location.href = '/';
        },
        
        // 显示提示
        showToast(message, type = 'success') {
            this.toast = { show: true, type, message };
            setTimeout(() => {
                this.toast.show = false;
            }, 3000);
        },
        
        // 合同审查
        handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                this.uploadContract(file);
            }
        },
        
        handleFileDrop(e) {
            const file = e.dataTransfer.files[0];
            if (file) {
                this.uploadContract(file);
            }
        },
        
        uploadContract(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('name', file.name);
            
            const token = localStorage.getItem('fzy_token');
            
            $.ajax({
                url: '/api/index.php?module=contract&action=upload',
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token },
                data: formData,
                processData: false,
                contentType: false,
                success: (res) => {
                    if (res.code === 200) {
                        this.showToast('上传成功，正在审查...');
                        this.reviewContract(res.data.id);
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                error: () => {
                    this.showToast('上传失败', 'error');
                }
            });
        },
        
        submitContractReview() {
            if (!this.contractContent.trim()) {
                this.showToast('请输入合同内容', 'error');
                return;
            }
            
            this.isReviewing = true;
            const token = localStorage.getItem('fzy_token');
            
            $.ajax({
                url: '/api/index.php?module=contract&action=aiReview',
                method: 'POST',
                headers: { 
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    content: this.contractContent,
                    name: '合同审查-' + new Date().toLocaleDateString()
                }),
                success: (res) => {
                    this.isReviewing = false;
                    if (res.code === 200) {
                        this.showToast('审查完成');
                        this.contractContent = '';
                        this.loadContractRecords();
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                error: () => {
                    this.isReviewing = false;
                    this.showToast('审查失败', 'error');
                }
            });
        },
        
        loadContractRecords() {
            const token = localStorage.getItem('fzy_token');
            
            $.ajax({
                url: '/api/index.php?module=contract&action=getReviewList',
                method: 'GET',
                headers: { 'Authorization': 'Bearer ' + token },
                success: (res) => {
                    if (res.code === 200) {
                        this.contractRecords = res.data.list;
                    }
                }
            });
        },
        
        getRiskClass(level) {
            const classes = { 1: 'risk-low', 2: 'risk-medium', 3: 'risk-high' };
            return classes[level] || 'risk-low';
        },
        
        getRiskText(level) {
            const texts = { 1: '低风险', 2: '中风险', 3: '高风险' };
            return texts[level] || '未知';
        },
        
        // 法律咨询
        selectConsultCategory(category) {
            this.selectedCategory = category.id;
            this.chatMessages = [{
                type: 'ai',
                content: `您好！我是您的AI法律顾问，专门为您解答${category.name}相关问题。请描述您遇到的情况，我会为您提供专业的法律建议。`
            }];
        },
        
        sendMessage() {
            if (!this.chatInput.trim() || this.isAiTyping) return;
            
            const message = this.chatInput.trim();
            this.chatMessages.push({ type: 'user', content: message });
            this.chatInput = '';
            this.isAiTyping = true;
            
            // 滚动到底部
            this.$nextTick(() => {
                const container = this.$refs.chatContainer;
                if (container) container.scrollTop = container.scrollHeight;
            });
            
            // 模拟AI回复
            setTimeout(() => {
                const replies = [
                    '根据您描述的情况，这涉及到相关法律法规。建议您：\n\n1. 首先收集和保存相关证据\n2. 与对方协商解决\n3. 如协商不成，可寻求法律途径\n\n如需更详细的建议，建议咨询专业律师。',
                    '您的问题我已经了解了。从法律角度分析：\n\n- 您的权益受到法律保护\n- 建议保留相关证据材料\n- 可以考虑通过调解或诉讼解决\n\n请问还有其他问题吗？',
                    '针对您的情况，我建议您：\n\n1. 了解相关法律规定\n2. 评估自身权益\n3. 选择合适的维权方式\n\n如需进一步帮助，可以继续提问或预约律师咨询。'
                ];
                const reply = replies[Math.floor(Math.random() * replies.length)];
                
                this.chatMessages.push({ type: 'ai', content: reply });
                this.isAiTyping = false;
                
                this.$nextTick(() => {
                    const container = this.$refs.chatContainer;
                    if (container) container.scrollTop = container.scrollHeight;
                });
            }, 1500);
        },
        
        // 文书生成
        selectDocType(doc) {
            this.selectedDocType = doc.id;
            this.docForm = {};
            this.generatedDoc = '';
        },
        
        previewDocument() {
            this.showToast('预览功能开发中');
        },
        
        generateDocument() {
            // 验证必填字段
            for (let field of this.docFields) {
                if (!this.docForm[field.name]) {
                    this.showToast(`请填写${field.label}`, 'error');
                    return;
                }
            }
            
            this.isGenerating = true;
            
            // 模拟生成
            setTimeout(() => {
                this.generatedDoc = this.mockGenerateDoc(this.selectedDocType, this.docForm);
                this.isGenerating = false;
                this.showToast('文书生成成功');
            }, 2000);
        },
        
        mockGenerateDoc(type, form) {
            const date = new Date().toLocaleDateString('zh-CN');
            
            if (type === '起诉状') {
                return `${form.plaintiff}诉${form.defendant}${form.caseType}一案

原告：${form.plaintiff}
被告：${form.defendant}

案由：${form.caseType}

诉讼请求：
${form.claim}

事实与理由：
${form.facts}

此致

人民法院

具状人：${form.plaintiff}
日期：${date}`;
            }
            
            return `${type}

生成日期：${date}

${Object.entries(form).map(([k, v]) => `${k}: ${v}`).join('\n')}

（此文书由法智云AI生成，仅供参考）`;
        },
        
        downloadDocument() {
            const blob = new Blob([this.generatedDoc], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${this.selectedDocType}_${new Date().toLocaleDateString()}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        },
        
        // 合同模板
        loadTemplates() {
            const token = localStorage.getItem('fzy_token');
            
            $.ajax({
                url: '/api/index.php?module=contract&action=getTemplates',
                method: 'GET',
                headers: { 'Authorization': 'Bearer ' + token },
                success: (res) => {
                    if (res.code === 200) {
                        this.templates = res.data.list;
                    }
                }
            });
        },
        
        downloadTemplate(template) {
            if (template.is_premium && !this.subscription) {
                this.showToast('该模板为VIP模板，请先订阅会员', 'error');
                this.navigate('subscription');
                return;
            }
            this.showToast('开始下载：' + template.name);
        },
        
        // 会员订阅
        subscribe(planId) {
            const token = localStorage.getItem('fzy_token');
            
            $.ajax({
                url: '/api/index.php?module=payment&action=createOrder',
                method: 'POST',
                headers: { 
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    orderType: 'subscription',
                    productId: planId,
                    paymentMethod: 'wechat'
                }),
                success: (res) => {
                    if (res.code === 200) {
                        this.showToast('订单创建成功，请完成支付');
                        // 实际应调起支付
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                error: () => {
                    this.showToast('创建订单失败', 'error');
                }
            });
        },
        
        // 个人中心
        saveProfile() {
            const token = localStorage.getItem('fzy_token');
            
            $.ajax({
                url: '/api/index.php?module=auth&action=updateProfile',
                method: 'POST',
                headers: { 
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(this.profileForm),
                success: (res) => {
                    if (res.code === 200) {
                        this.showToast('保存成功');
                        this.user.nickname = this.profileForm.nickname;
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                error: () => {
                    this.showToast('保存失败', 'error');
                }
            });
        },
        
        changePassword() {
            if (!this.passwordForm.oldPassword || !this.passwordForm.newPassword) {
                this.showToast('请填写完整信息', 'error');
                return;
            }
            
            if (this.passwordForm.newPassword !== this.passwordForm.confirmPassword) {
                this.showToast('两次输入的新密码不一致', 'error');
                return;
            }
            
            const token = localStorage.getItem('fzy_token');
            
            $.ajax({
                url: '/api/index.php?module=auth&action=changePassword',
                method: 'POST',
                headers: { 
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(this.passwordForm),
                success: (res) => {
                    if (res.code === 200) {
                        this.showToast('密码修改成功');
                        this.passwordForm = { oldPassword: '', newPassword: '', confirmPassword: '' };
                    } else {
                        this.showToast(res.message, 'error');
                    }
                },
                error: () => {
                    this.showToast('修改失败', 'error');
                }
            });
        }
    }
});
