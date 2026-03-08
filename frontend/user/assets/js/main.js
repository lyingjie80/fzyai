/**
 * 法智云 - 主页面JavaScript
 */

new Vue({
    el: '#app',
    data: {
        isScrolled: false,
        showMobileMenu: false,
        showLoginModal: false,
        showRegisterModal: false,
        isLoading: false,
        isLoggedIn: false,
        user: {},
        loginForm: {
            account: '',
            password: ''
        },
        registerForm: {
            username: '',
            email: '',
            password: '',
            confirmPassword: ''
        }
    },
    mounted() {
        // 监听滚动
        window.addEventListener('scroll', this.handleScroll);
        
        // 检查登录状态
        this.checkLoginStatus();
        
        // 平滑滚动
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    },
    methods: {
        handleScroll() {
            this.isScrolled = window.scrollY > 50;
        },
        
        checkLoginStatus() {
            const token = localStorage.getItem('fzy_token');
            if (token) {
                // 验证token并获取用户信息
                $.ajax({
                    url: '/api/index.php?module=auth&action=profile',
                    method: 'GET',
                    headers: { 'Authorization': 'Bearer ' + token },
                    success: (res) => {
                        if (res.code === 200) {
                            this.isLoggedIn = true;
                            this.user = res.data;
                        } else {
                            localStorage.removeItem('fzy_token');
                        }
                    },
                    error: () => {
                        localStorage.removeItem('fzy_token');
                    }
                });
            }
        },
        
        login() {
            if (!this.loginForm.account || !this.loginForm.password) {
                alert('请填写完整信息');
                return;
            }
            
            this.isLoading = true;
            
            $.ajax({
                url: '/api/index.php?module=auth&action=login',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(this.loginForm),
                success: (res) => {
                    this.isLoading = false;
                    if (res.code === 200) {
                        localStorage.setItem('fzy_token', res.data.token);
                        this.isLoggedIn = true;
                        this.user = res.data.user;
                        this.showLoginModal = false;
                        alert('登录成功');
                        window.location.href = '/app.html';
                    } else {
                        alert(res.message);
                    }
                },
                error: () => {
                    this.isLoading = false;
                    alert('登录失败，请稍后重试');
                }
            });
        },
        
        register() {
            if (!this.registerForm.username || !this.registerForm.email || !this.registerForm.password) {
                alert('请填写完整信息');
                return;
            }
            
            if (this.registerForm.password !== this.registerForm.confirmPassword) {
                alert('两次输入的密码不一致');
                return;
            }
            
            if (this.registerForm.password.length < 6) {
                alert('密码长度不能少于6位');
                return;
            }
            
            this.isLoading = true;
            
            $.ajax({
                url: '/api/index.php?module=auth&action=register',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    username: this.registerForm.username,
                    email: this.registerForm.email,
                    password: this.registerForm.password,
                    confirmPassword: this.registerForm.confirmPassword
                }),
                success: (res) => {
                    this.isLoading = false;
                    if (res.code === 200) {
                        localStorage.setItem('fzy_token', res.data.token);
                        this.isLoggedIn = true;
                        this.user = res.data.user;
                        this.showRegisterModal = false;
                        alert('注册成功');
                        window.location.href = '/app.html';
                    } else {
                        alert(res.message);
                    }
                },
                error: () => {
                    this.isLoading = false;
                    alert('注册失败，请稍后重试');
                }
            });
        },
        
        subscribe(planType) {
            if (!this.isLoggedIn) {
                this.showLoginModal = true;
                return;
            }
            window.location.href = '/app.html#/subscription';
        }
    }
});
