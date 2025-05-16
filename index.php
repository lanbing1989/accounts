<?php
require_once 'inc/functions.php';
checkLogin();
include 'templates/header.php';
?>
<style>
.home-main {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 46px;
}
.home-title {
    font-size: 26px;
    color: #2676f5;
    font-weight: bold;
    margin-bottom: 7px;
    letter-spacing: 2.5px;
    text-shadow: 0 2px 6px rgba(38,118,245,0.08);
}
.home-welcome {
    color: #444;
    font-size: 17px;
    margin-bottom: 30px;
    letter-spacing: 1px;
}
.home-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 36px 36px;
    justify-content: center;
    margin-top: 10px;
}
.home-card {
    background: #f7fbfe;
    border: 1.5px solid #e6f0fa;
    border-radius: 15px;
    width: 176px;
    height: 142px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 18px rgba(38,118,245,0.07);
    transition: box-shadow 0.16s, border 0.16s, transform 0.16s;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    position: relative;
}
.home-card:hover {
    border: 1.8px solid #2676f5;
    box-shadow: 0 7px 26px rgba(38,118,245,0.15);
    transform: translateY(-3px) scale(1.045);
}
.home-card-icon {
    font-size: 39px;
    margin-bottom: 14px;
    color: #2676f5;
    text-shadow: 0 2px 8px rgba(38,118,245,0.10);
}
.home-card-title {
    font-size: 18px;
    color: #1954a2;
    font-weight: bold;
    margin-bottom: 7px;
}
.home-card-desc {
    font-size: 13px;
    color: #769ac0;
}
@media (max-width: 820px) {
    .home-cards { flex-direction: column; gap: 18px; }
    .home-card { width: 93vw; }
}
</style>
<div class="home-main">
    <div class="home-title">高端会计系统</div>
    <div class="home-welcome">欢迎使用高端会计系统，助力您的专业财务管理！请选择下方功能入口：</div>
    <div class="home-cards">
        <a class="home-card" href="books.php">
            <div class="home-card-icon">📚</div>
            <div class="home-card-title">账套管理</div>
            <div class="home-card-desc">多账套、新建/切换/归档</div>
        </a>
        <a class="home-card" href="accounts.php">
            <div class="home-card-icon">📂</div>
            <div class="home-card-title">科目管理</div>
            <div class="home-card-desc">科目树、个性化设置</div>
        </a>
        <a class="home-card" href="vouchers.php">
            <div class="home-card-icon">🧾</div>
            <div class="home-card-title">凭证管理</div>
            <div class="home-card-desc">录入/查阅会计凭证</div>
        </a>
        <a class="home-card" href="ledger.php">
            <div class="home-card-icon">📒</div>
            <div class="home-card-title">账簿</div>
            <div class="home-card-desc">总账、明细账查询</div>
        </a>
        <a class="home-card" href="reports.php">
            <div class="home-card-icon">📊</div>
            <div class="home-card-title">报表中心</div>
            <div class="home-card-desc">标准/自定义财务报表</div>
        </a>
        <a class="home-card" href="closings.php">
            <div class="home-card-icon">🔒</div>
            <div class="home-card-title">结账管理</div>
            <div class="home-card-desc">月末结账/反结账</div>
        </a>
        <a class="home-card" href="resetpw.php">
            <div class="home-card-icon">👤</div>
            <div class="home-card-title">密码修改</div>
            <div class="home-card-desc">密码修改</div>
        </a>
        <a class="home-card" href="settings.php">
            <div class="home-card-icon">⚙️</div>
            <div class="home-card-title">系统设置</div>
            <div class="home-card-desc">参数/数据维护</div>
        </a>
        <!-- 预留扩展功能卡片，如预算分析、智能归集、批量导入、模板中心等 -->
    </div>
</div>
<?php include 'templates/footer.php'; ?>