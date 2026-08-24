</main>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div>
                <div class="logo" style="margin-bottom: 16px;">
                    <span class="logo-icon">🎬</span>
                    <span><?= esc(setting('site_name')) ?></span>
                </div>
                <p style="color: var(--text2); font-size: 14px; line-height: 1.8;">
                    现代化影视网站，提供海量高清影视资源在线观看。<br>
                    支持电影、电视剧、综艺、动漫等多种类型。
                </p>
            </div>
            <div>
                <h4>快速导航</h4>
                <a href="index.php">首页</a>
                <a href="list.php?type=movie">电影</a>
                <a href="list.php?type=tv">电视剧</a>
                <a href="list.php?type=综艺">综艺</a>
                <a href="list.php?type=动漫">动漫</a>
            </div>
            <div>
                <h4>用户中心</h4>
                <?php if (is_logged_in()): ?>
                <a href="profile.php">个人中心</a>
                <a href="profile.php?tab=favorites">我的收藏</a>
                <a href="profile.php?tab=history">观看历史</a>
                <a href="feedback.php">意见反馈</a>
                <?php if (is_admin()): ?><a href="admin/">管理后台</a><?php endif; ?>
                <a href="logout.php">退出登录</a>
                <?php else: ?>
                <a href="javascript:;" data-modal="login-modal">登录</a>
                <a href="javascript:;" data-modal="login-modal">注册</a>
                <?php endif; ?>
            </div>
            <div>
                <h4>关于我们</h4>
                <p>本站资源均来自网络收集，仅供学习交流使用。</p>
                <p>如有侵权请联系我们删除。</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?= date('Y') ?> <?= esc(setting('site_name')) ?>  All Rights Reserved.</p>
        </div>
    </div>
</footer>

<?php if ($current_page === 'index' && ($announcement = get_active_announcement())):
$cookie_name = 'announcement_' . $announcement['id'];
if (!isset($_COOKIE[$cookie_name])):
?>
<div class="modal-overlay announcement-modal active" id="announcementModal">
    <div class="modal">
        <div class="modal-header">
            <h3>📢 网站公告</h3>
            <button class="modal-close" onclick="closeAnnouncement()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="announcement-icon">📣</div>
            <h3 class="announcement-title"><?= esc($announcement['title']) ?></h3>
            <div class="announcement-content"><?= nl2br(esc($announcement['content'])) ?></div>
            <label class="announcement-checkbox">
                <input type="checkbox" id="dontShowAgain">
                <span class="checkbox-custom"></span>
                不再提示此公告
            </label>
            <button class="btn btn-primary btn-block btn-lg" onclick="closeAnnouncement()">我知道了</button>
        </div>
    </div>
</div>
<script>
function closeAnnouncement() {
    const dontShow = document.getElementById('dontShowAgain');
    if (dontShow.checked) {
        document.cookie = '<?= $cookie_name ?>=1; max-age=86400*30; path=/';
    }
    document.getElementById('announcementModal').classList.remove('active');
}
</script>
<?php endif; endif; ?>

<script src="assets/js/app.js"></script>
<script>
document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('#loginForm, #registerForm').forEach(f => f.style.display = 'none');
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab + 'Form').style.display = 'block';
    });
});

const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(loginForm);
        formData.append('action', 'login');
        const res = await fetch('ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.code === 200) {
            JayTV.toast('登录成功');
            setTimeout(() => location.reload(), 800);
        } else {
            JayTV.toast(data.msg || '登录失败', 'error');
        }
    });
}

const registerForm = document.getElementById('registerForm');
if (registerForm) {
    let codeTimer = null;
    document.getElementById('sendCodeBtn')?.addEventListener('click', async function() {
        const email = registerForm.querySelector('input[name="email"]').value;
        if (!email || !email.includes('@')) {
            JayTV.toast('请输入有效邮箱', 'error');
            return;
        }
        const formData = new FormData();
        formData.append('action', 'send_code');
        formData.append('email', email);
        const res = await fetch('ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.code === 200) {
            JayTV.toast('验证码已发送');
            let sec = 60;
            this.disabled = true;
            const origText = this.textContent;
            this.textContent = sec + 's';
            codeTimer = setInterval(() => {
                sec--;
                this.textContent = sec + 's';
                if (sec <= 0) {
                    clearInterval(codeTimer);
                    this.disabled = false;
                    this.textContent = origText;
                }
            }, 1000);
        } else {
            JayTV.toast(data.msg || '发送失败', 'error');
        }
    });
    
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(registerForm);
        formData.append('action', 'register');
        const res = await fetch('ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.code === 200) {
            JayTV.toast('注册成功，正在登录...');
            setTimeout(() => location.reload(), 1000);
        } else {
            JayTV.toast(data.msg || '注册失败', 'error');
        }
    });
}

document.addEventListener('click', (e) => {
    const favBtn = e.target.closest('.favorite-btn');
    if (favBtn) {
        e.preventDefault();
        e.stopPropagation();
        JayTV.toggleFavorite(favBtn.dataset.id, favBtn.dataset.type, favBtn);
    }
    
    const likeBtn = e.target.closest('.like-btn');
    if (likeBtn) {
        e.preventDefault();
        JayTV.likeFeedback(likeBtn.dataset.id, likeBtn);
    }
});
</script>

</body>
</html>
