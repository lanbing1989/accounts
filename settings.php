<?php
require_once 'inc/functions.php';
checkLogin();
$book = getCurrentBook();
$sysinfo = getSystemInfo();
$users = getAllUsers();
include 'templates/header.php';
?>
<style>
.settings-wrap {max-width:960px;margin:40px auto 60px auto;background:#fff;padding:36px 30px 60px 30px;border-radius:18px;box-shadow:0 2px 24px #e4edfa;}
.settings-title {font-size:26px;color:#2676f5;font-weight:700;margin-bottom:28px;text-align:center;}
.settings-section {margin-bottom:38px;}
.settings-section-title {font-size:19px;color:#247bfc;font-weight:600;margin-bottom:13px;}
.settings-form label {display:block;font-size:15px;font-weight:600;margin:12px 0 5px 0;}
.settings-form input[type="text"],
.settings-form input[type="password"],
.settings-form input[type="file"],
.settings-form select {width:320px;max-width:100%;padding:7px 10px;border:1px solid #c7dafc;border-radius:5px;margin-bottom:12px;}
.settings-form input[type="checkbox"] {margin-right:8px;}
.settings-users-table {width:100%;margin-top:10px;border-collapse:collapse;}
.settings-users-table th, .settings-users-table td {padding:8px 10px;border: 1px solid #e4edfa;}
.settings-users-table th {background:#f3f8ff;}
.settings-btn {background:#2676f5;color:#fff;border:none;padding:7px 26px;border-radius:6px;font-size:15px;cursor:pointer;}
.settings-btn-danger {background:#f63a3a;}
.settings-btn-small {padding:4px 12px;font-size:13px;}
.settings-section-desc {color:#888;font-size:13px;margin-bottom:5px;}
@media(max-width:700px){.settings-wrap{padding:10px 2vw;}.settings-form input, .settings-form select{width:99%;}}
</style>

<div class="settings-wrap">
    <div class="settings-title">系统设置</div>
    <form class="settings-form" method="post" enctype="multipart/form-data" action="settings_save.php">
        <!-- 1. 基础信息设置 -->
        <div class="settings-section">
            <div class="settings-section-title">机构基础信息</div>
            <label>公司名称</label>
            <input type="text" name="company_name" value="<?=htmlspecialchars($sysinfo['company_name'] ?? '')?>" required>
            <label>Logo上传</label>
            <input type="file" name="company_logo" accept="image/*">
            <label>联系人</label>
            <input type="text" name="contact" value="<?=htmlspecialchars($sysinfo['contact'] ?? '')?>">
            <label>联系电话</label>
            <input type="text" name="contact_phone" value="<?=htmlspecialchars($sysinfo['contact_phone'] ?? '')?>">
        </div>

        <!-- 2. 用户与权限管理 -->
         <?php if ($role === '超级管理员'): ?>
        <div class="settings-section">
            <div class="settings-section-title">用户与权限</div>
            <table class="settings-users-table">
                <tr>
                    <th>用户名</th>
                    <th>角色</th>
                    <th>操作</th>
                </tr>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?=htmlspecialchars($u['username'])?></td>
                    <td><?=htmlspecialchars($u['role'])?></td>
                    <td>
                        <a href="user_edit.php?id=<?=$u['id']?>" class="settings-btn settings-btn-small">编辑</a>
                        <a href="user_resetpw.php?id=<?=$u['id']?>" class="settings-btn settings-btn-small">重置密码</a>
                        <?php if($u['username']!='admin'): ?>
                        <a href="user_delete.php?id=<?=$u['id']?>" class="settings-btn settings-btn-small settings-btn-danger" onclick="return confirm('确定要删除此用户？');">删除</a>
                        <?php endif;?>
                    </td>
                </tr>
                <?php endforeach;?>
            </table>
            <a href="user_add.php" class="settings-btn settings-btn-small" style="margin-top:8px;">新增用户</a>
        </div>
<?php endif; ?>
        <!-- 4. 数据与备份 -->
        <?php if ($role === '超级管理员'): ?>
        <div class="settings-section">
            <div class="settings-section-title">数据与备份</div>
            <a href="db_backup.php" class="settings-btn settings-btn-small">下载数据备份</a>
            <a href="db_restore.php" class="settings-btn settings-btn-small">上传恢复</a>
            <a href="data_export.php" class="settings-btn settings-btn-small">数据导出</a>
        </div>
<?php endif; ?>
        <div style="text-align:center;margin-top:30px;">
            <button type="submit" class="settings-btn" style="font-size:16px;">保存设置</button>
        </div>
    </form>
</div>
<?php include 'templates/footer.php'; ?>