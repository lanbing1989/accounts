<?php
require_once 'inc/functions.php';
checkLogin();
$accounts = getAccounts(); // 取出所有当前账套科目
include 'templates/header.php';

// 组装树形结构
function buildTree($accounts, $parent_code = null) {
    $tree = [];
    foreach($accounts as $a) {
        if ((is_null($parent_code) && (empty($a['parent_code']) || $a['parent_code'] === '0')) || $a['parent_code'] == $parent_code) {
            $children = buildTree($accounts, $a['code']);
            if ($children) $a['children'] = $children;
            $tree[] = $a;
        }
    }
    return $tree;
}
$tree = buildTree($accounts);

function renderTree($nodes, $level=0) {
    foreach($nodes as $n) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        ?>
        <tr>
            <td style="text-align:left;"><?=$indent?><span><?=htmlspecialchars($n['code'])?></span></td>
            <td style="text-align:left;"><?=htmlspecialchars($n['name'])?></td>
            <td><?=htmlspecialchars($n['category'])?></td>
            <td><?=htmlspecialchars($n['direction'])?></td>
            <td class="account-ops">
                <a href="account_add.php?parent=<?=urlencode($n['code'])?>" class="btn-mini">+下级</a>
                <a href="account_edit.php?code=<?=urlencode($n['code'])?>" class="btn-mini">编辑</a>
                <a href="account_delete.php?code=<?=urlencode($n['code'])?>" class="btn-mini"
                   onclick="return confirm('确定要删除该科目吗？若有下级/凭证无法删除！')">删除</a>
            </td>
        </tr>
        <?php
        if (!empty($n['children'])) renderTree($n['children'], $level+1);
    }
}
?>
<style>
body {
    background: #f8fbfd;
}
.account-main-wrap {
    max-width: 1100px;
    margin: 32px auto 0 auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 16px #e7ecf5;
    padding: 30px 32px 30px 32px;
}
.account-title {
    font-size: 22px;
    font-weight: bold;
    color: #2676f5;
    text-align: center;
    margin-bottom: 18px;
    letter-spacing: 1.5px;
}
.account-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 10px #e7ecf5;
}
.account-table th, .account-table td {
    padding: 13px 8px;
    font-size: 16px;
    border-bottom: 1px solid #f0f5fa;
}
.account-table th {
    background: #f4f8fc;
    color: #2676f5;
    font-size: 15.5px;
    font-weight: bold;
    border-bottom: 2px solid #e0eafe;
    text-align: center;
}
.account-table td {
    text-align: center;
}
.account-table tr:last-child td {
    border-bottom: none;
}
.account-ops {
    text-align: right;
    min-width: 160px;
}
.btn-mini {
    display: inline-block;
    padding: 4px 14px;
    margin-right: 8px;
    font-size: 14px;
    color: #2676f5;
    border: 1px solid #2676f5;
    border-radius: 5px;
    background: #f4f8fc;
    cursor: pointer;
    transition: background 0.16s, color 0.16s;
    outline: none;
    margin-bottom: 2px;
    text-decoration: none;
}
.btn-mini:last-child { margin-right: 0; }
.btn-mini:hover {
    background: #2676f5;
    color: #fff;
}
.account-search-bar {
    margin-bottom: 22px;
    text-align: right;
}
.account-search-bar input[type="text"] {
    padding: 5px 10px;
    border: 1px solid #c2d2ea;
    border-radius: 5px;
    font-size: 15px;
    width: 180px;
    margin-right: 6px;
}
.account-search-bar button {
    padding: 5px 18px;
    border-radius: 5px;
    border: none;
    background: #2676f5;
    color: #fff;
    font-size: 15px;
    cursor: pointer;
    transition: background 0.16s;
}
.account-search-bar button:hover {
    background: #185fcb;
}
@media (max-width: 1200px) {
    .account-main-wrap { max-width: 98vw; padding: 12px 4px; }
    .account-table { font-size: 15px; }
}
@media (max-width: 700px) {
    .account-main-wrap { padding: 2px 0; }
    .account-table { font-size: 13px; }
    .account-search-bar input[type="text"] { width: 120px; }
}
</style>
<div class="account-main-wrap">
    <div class="account-title">会计科目管理</div>
    <div class="account-search-bar">
        <form method="get" style="display:inline;">
            <input type="text" name="q" value="<?=htmlspecialchars($_GET['q']??'')?>" placeholder="科目编号/名称搜索">
            <button type="submit">搜索</button>
        </form>
    </div>
    <table class="account-table">
        <tr>
            <th>编号</th>
            <th>名称</th>
            <th>类别</th>
            <th>方向</th>
            <th>操作</th>
        </tr>
        <?php
        // 简易搜索
        if (!empty($_GET['q'])) {
            $q = strtolower(trim($_GET['q']));
            $accounts = array_filter($accounts, function($a) use ($q){
                return strpos(strtolower($a['code']), $q)!==false || strpos(strtolower($a['name']), $q)!==false;
            });
            foreach($accounts as $n) { ?>
                <tr>
                    <td style="text-align:left;"><?=htmlspecialchars($n['code'])?></td>
                    <td style="text-align:left;"><?=htmlspecialchars($n['name'])?></td>
                    <td><?=htmlspecialchars($n['category'])?></td>
                    <td><?=htmlspecialchars($n['direction'])?></td>
                    <td class="account-ops">
                        <a href="account_add.php?parent=<?=urlencode($n['code'])?>" class="btn-mini">+下级</a>
                        <a href="account_edit.php?code=<?=urlencode($n['code'])?>" class="btn-mini">编辑</a>
                        <a href="account_delete.php?code=<?=urlencode($n['code'])?>" class="btn-mini"
                           onclick="return confirm('确定要删除该科目吗？若有下级/凭证无法删除！')">删除</a>
                    </td>
                </tr>
            <?php }
        } else {
            renderTree($tree);
        }
        ?>
    </table>
    <div style="color:#888;font-size:13px;margin-top:18px;">
        <b>说明：</b> 只能从已有科目“+下级”新增（不可新增一级科目）；新增下级时如父级已有数据，数据将自动转移到新下级科目。不能删除有凭证或下级的科目。支持按科目编码或名称搜索。
    </div>
</div>
<?php include 'templates/footer.php'; ?>