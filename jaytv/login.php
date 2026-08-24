<?php
$page_title = '登录';
require_once __DIR__ . '/includes/header.php';
$redirect = $_GET['redirect'] ?? 'index.php';
?>

<div class="auth-page">
    <div class="auth-box" style="max-width: 450px;">
        <div class="logo" style="justify-content:center;margin-bottom:30px;font-size:28px;">
            <span class="logo-icon">🎬</span>
            <span><?= esc(setting('site_name')) ?></span>
        </div>
        
        <?php if (isset($_GET['msg'])): ?>
        <div style="padding:14px 16px;background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.3);color:#fcd34d;border-radius:10px;margin-bottom:20px;text-align:center;">
            <?= esc($_GET['msg']) ?>
        </div>
        <?php endif; ?>
        
        <div class="auth-tabs">
            <div class="auth-tab active" data-tab="login">登录</div>
            <div class="auth-tab" data-tab="register">注册</div>
        </div>
        
        <form id="loginFormFull">
            <input type="hidden" name="redirect" value="<?= esc($redirect) ?>">
            <div class="form-group">
                <label>邮箱/用户名</label>
                <input type="text" name="account" class="form-control" required placeholder="请输入邮箱或用户名" autofocus>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-control" required placeholder="请输入密码">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">登录</button>
        </form>
        
        <form id="registerFormFull" style="display:none;">
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" class="form-control" required placeholder="请输入邮箱">
            </div>
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" class="form-control" required placeholder="请输入用户名(2-20位)">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-control" required placeholder="请输入密码(至少6位)">
            </div>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="password2" class="form-control" required placeholder="请再次输入密码">
            </div>
            <div class="form-group">
                <label>邮箱验证码</label>
                <div class="form-row">
                    <input type="text" name="code" class="form-control" required placeholder="请输入验证码">
                    <button type="button" class="btn btn-outline btn-sm" id="sendCodeBtnFull">发送验证码</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">注册账号</button>
        </form>
        
        <div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
            <a href="index.php" style="color:var(--text2);font-size:14px;">← 返回首页</a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('#loginFormFull, #registerFormFull').forEach(f => f.style.display = 'none');
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab + 'FormFull').style.display = 'block';
    });
});

document.getElementById('loginFormFull').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'login');
    const res = await fetch('ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) {
        JayTV.toast('登录成功');
        setTimeout(() => location.href = formData.get('redirect') || 'index.php', 800);
    } else {
        JayTV.toast(data.msg || '登录失败', 'error');
    }
});

let codeTimerFull = null;
document.getElementById('sendCodeBtnFull')?.addEventListener('click', async function() {
    const form = document.getElementById('registerFormFull');
    const email = form.querySelector('input[name="email"]').value;
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
        codeTimerFull = setInterval(() => {
            sec--;
            this.textContent = sec + 's';
            if (sec <= 0) {
                clearInterval(codeTimerFull);
                this.disabled = false;
                this.textContent = origText;
            }
        }, 1000);
    } else {
        JayTV.toast(data.msg || '发送失败', 'error');
    }
});

document.getElementById('registerFormFull').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'register');
    const res = await fetch('ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) {
        JayTV.toast('注册成功，正在登录...');
        setTimeout(() => location.href = 'index.php', 1000);
    } else {
        JayTV.toast(data.msg || '注册失败', 'error');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
