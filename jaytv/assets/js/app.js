const JayTV = {
    theme: '<?= THEME_COLOR ?>',
    
    init() {
        this.initScroll();
        this.initMobileMenu();
        this.initModals();
        this.initToasts();
        this.initSearch();
    },
    
    initScroll() {
        const header = document.querySelector('.header');
        if (!header) return;
        window.addEventListener('scroll', () => {
            header.classList.toggle('scrolled', window.scrollY > 10);
        });
    },
    
    initMobileMenu() {
        const btn = document.querySelector('.mobile-menu-btn');
        const menu = document.querySelector('.nav-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', () => {
            btn.classList.toggle('active');
            menu.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (!btn.contains(e.target) && !menu.contains(e.target)) {
                btn.classList.remove('active');
                menu.classList.remove('active');
            }
        });
    },
    
    initModals() {
        document.querySelectorAll('[data-modal]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openModal(btn.dataset.modal);
            });
        });
        document.querySelectorAll('.modal-overlay, .modal-close').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target === el || el.classList.contains('modal-close')) {
                    this.closeModal();
                }
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeModal();
        });
    },
    
    openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            const input = modal.querySelector('input');
            if (input) setTimeout(() => input.focus(), 100);
        }
    },
    
    closeModal() {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        document.body.style.overflow = '';
    },
    
    initToasts() {
        if (!document.querySelector('.toast-container')) {
            const c = document.createElement('div');
            c.className = 'toast-container';
            document.body.appendChild(c);
        }
    },
    
    toast(msg, type = 'success') {
        const container = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },
    
    initSearch() {
        const input = document.querySelector('.search-box input');
        if (!input) return;
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const q = input.value.trim();
                if (q.length >= 2) {
                    window.location.href = 'search.php?q=' + encodeURIComponent(q);
                }
            }, 800);
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const q = input.value.trim();
                if (q) window.location.href = 'search.php?q=' + encodeURIComponent(q);
            }
        });
    },
    
    async request(url, data = {}, method = 'POST') {
        const options = { method, headers: {} };
        if (method === 'POST') {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.body = new URLSearchParams({...data, csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''});
        }
        try {
            const res = await fetch(url, options);
            return await res.json();
        } catch (e) {
            return { code: 500, msg: '网络错误' };
        }
    },
    
    async toggleFavorite(mediaId, mediaType, btn) {
        const isActive = btn.classList.contains('active');
        const action = isActive ? 'remove' : 'add';
        const res = await this.request('ajax.php?action=favorite', { id: mediaId, type: mediaType, do: action });
        if (res.code === 200) {
            btn.classList.toggle('active');
            this.toast(isActive ? '已取消收藏' : '已添加收藏');
        } else if (res.code === 401) {
            this.openModal('login-modal');
        } else {
            this.toast(res.msg || '操作失败', 'error');
        }
    },
    
    async likeFeedback(id, btn) {
        const res = await this.request('ajax.php?action=like_feedback', { id });
        if (res.code === 200) {
            btn.classList.toggle('active');
            const count = btn.querySelector('.count');
            if (count) count.textContent = res.data.likes;
            this.toast(res.data.liked ? '已点赞' : '已取消');
        } else if (res.code === 401) {
            this.openModal('login-modal');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => JayTV.init());

function showToast(msg, type) { JayTV.toast(msg, type); }
function openModal(id) { JayTV.openModal(id); }
function closeModal() { JayTV.closeModal(); }
