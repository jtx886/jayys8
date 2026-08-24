</div>

<script src="../assets/js/app.js"></script>
<script>
function showBanModal(userId, username) {
    document.getElementById('banUserId').value = userId;
    document.getElementById('banUserName').textContent = username;
    document.getElementById('banModal').classList.add('active');
}

async function submitBan() {
    const form = document.getElementById('banForm');
    const formData = new FormData(form);
    formData.append('action', 'admin_ban_user');
    const res = await fetch('../ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) {
        JayTV.toast('封禁成功');
        setTimeout(() => location.reload(), 1000);
    } else {
        JayTV.toast(data.msg || '操作失败', 'error');
    }
}

async function unbanUser(id) {
    if (!confirm('确定要解封该用户吗？')) return;
    const formData = new FormData();
    formData.append('action', 'admin_unban_user');
    formData.append('id', id);
    const res = await fetch('../ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) {
        JayTV.toast('已解封');
        setTimeout(() => location.reload(), 800);
    }
}

async function deleteSource(id) {
    if (!confirm('确定要删除该播放源吗？')) return;
    const formData = new FormData();
    formData.append('action', 'admin_delete_source');
    formData.append('id', id);
    const res = await fetch('../ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) {
        JayTV.toast('已删除');
        setTimeout(() => location.reload(), 800);
    }
}

function selectColor(el, color) {
    document.querySelectorAll('.color-option').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('themeColor').value = color;
    document.getElementById('colorPreview').style.background = color;
    document.getElementById('colorValue').textContent = color;
}

function selectCustomColor(color) {
    document.querySelectorAll('.color-option').forEach(c => c.classList.remove('active'));
    document.getElementById('themeColor').value = color;
    document.getElementById('colorPreview').style.background = color;
    document.getElementById('colorValue').textContent = color;
}
</script>

</body>
</html>
