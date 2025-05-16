<?php
require_once 'inc/functions.php';
checkLogin();
global $db;

// 读取所有会计制度
$systems = $db->query("SELECT id, code, name FROM accounting_systems ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// 预定义制度-模板映射
$template_map = [
    'xiaoqiye2013' => [
        'standard' => '标准模板（小企业会计准则2013）',
    ],
];


// 处理POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $start_year = intval($_POST['start_year']);
    $start_month = intval($_POST['start_month']);
    $system_id = intval($_POST['system_id']);
    $template = $_POST['template'] ?? 'standard';

    // 获取所选制度code
    $system_code = '';
    foreach($systems as $sys) if($sys['id']==$system_id) $system_code=$sys['code'];

    if (!$name || $start_year < 2000 || $start_month < 1 || $start_month > 12 || !$system_id) {
        $msg = "请填写完整账套名称、会计制度和起始年月";
    } else {
        // 检查重名
        $stmt = $db->prepare("SELECT COUNT(*) FROM books WHERE name=?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            $msg = "账套名称已存在，请换一个名称！";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO books (name, start_year, start_month, system_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $start_year, $start_month, $system_id]);
                session_start();
                $new_book_id = $db->lastInsertId();
                $_SESSION['book_id'] = $new_book_id;
                $_SESSION['period_year'] = $start_year;
                $_SESSION['period_month'] = $start_month;

                // 二选一：优先用上传的JSON模板，否则用下拉模板
                $accounts = null;
                if (isset($_FILES['jsonfile']) && $_FILES['jsonfile']['error'] == 0 && $_FILES['jsonfile']['size'] > 0) {
                    $json = file_get_contents($_FILES['jsonfile']['tmp_name']);
                    $accounts = json_decode($json, true);
                    if (!is_array($accounts)) $accounts = null;
                }
                if (!$accounts) {
                    if ($template==='standard') {
                        $stmt = $db->prepare("SELECT code,name,category,direction,parent_code FROM standard_accounts WHERE system_id=?");
                        $stmt->execute([$system_id]);
                        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $template_file = __DIR__ . "/data/account_template_{$system_code}_{$template}.json";
                        if (file_exists($template_file)) {
                            $accounts = json_decode(file_get_contents($template_file), true);
                            if (!is_array($accounts)) $accounts = null;
                        }
                    }
                }
                if (!$accounts) {
                    $msg = "未能正确获取会计科目模板，请检查模板文件！";
                } else {
                    $insert = $db->prepare("INSERT INTO accounts (book_id, code, name, category, direction, parent_code, is_custom) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    foreach ($accounts as $a) {
                        $insert->execute([
                            $new_book_id,
                            $a['code'],
                            $a['name'],
                            $a['category'],
                            $a['direction'],
                            $a['parent_code'] ?? null
                        ]);
                    }
                    header("Location: books.php?msg=" . urlencode("账套创建成功！"));
                    exit;
                }
            } catch (PDOException $e) {
                $msg = "数据库错误：" . $e->getMessage();
            }
        }
    }
}

include 'templates/header.php';
?>
<style>
.addbook-panel {
    max-width: 496px;
    margin: 46px auto;
    padding: 32px 36px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px #e5ecf5;
}
.addbook-panel h2 {
    margin-bottom: 24px;
    font-size: 24px;
    color: #2676f5;
    letter-spacing: 1.5px;
}
.addbook-panel label {
    font-weight: bold;
    color: #1954a2;
    display: block;
    margin-bottom: 6px;
    letter-spacing: 0.8px;
}
.addbook-panel input[type=text], .addbook-panel select {
    width: 97%;
    padding: 7px 8px;
    font-size: 16px;
    border: 1px solid #c2d2ea;
    border-radius: 5px;
    margin-bottom: 18px;
    background: #f8fbfd;
    transition: border 0.2s;
}
.addbook-panel input[type=text]:focus, .addbook-panel select:focus {
    border: 1.5px solid #2676f5;
    outline: none;
}
.addbook-panel .row { margin-bottom: 20px; }
.addbook-panel .row.flex { display: flex; gap: 12px; align-items: center;}
.addbook-panel .row.flex > * { flex: 1; }
.addbook-panel .row .tip { color: #888; font-size: 13px; margin-top: 3px; }
.addbook-panel .submit-btn {
    background: #2676f5;
    color: #fff;
    font-size: 17px;
    padding: 9px 32px;
    border: none;
    border-radius: 5px;
    margin-top: 12px;
    margin-right: 18px;
    letter-spacing: 1px;
    font-weight: bold;
    box-shadow: 0 2px 8px #2676f51a;
    cursor: pointer;
    transition: background 0.18s;
}
.addbook-panel .submit-btn:hover { background: #185fcb; }
.addbook-panel .back-link { margin-left:24px; color:#2676f5; text-decoration:underline; font-size: 15px;}
.addbook-panel .msg-err { color:#e54f4f; margin-bottom: 18px; }
.addbook-panel .upload-tip { color:#666; font-size:13px; margin-top:2px; }
.addbook-panel .custom-upload {
    display: flex;
    align-items: center;
    gap: 8px;
}
.addbook-panel .custom-upload input[type="file"] {
    border:none;
    background: transparent;
    font-size: 14px;
}
</style>
<script>
function onJsonFileChange() {
    // 如果文件有内容，禁用模板选择；否则启用
    var file = document.querySelector('input[name="jsonfile"]').files[0];
    document.getElementById('template').disabled = !!file;
}
</script>
<div class="addbook-panel">
    <h2>新建账套</h2>
    <?php if(isset($msg)): ?><div class="msg-err"><?=htmlspecialchars($msg)?></div><?php endif;?>
    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="row">
            <label>账套名称</label>
            <input type="text" name="name" maxlength="30" required placeholder="如：2025年度主账套">
        </div>
        <div class="row flex">
            <div>
                <label>会计制度</label>
                <select name="system_id" id="system_id" required>
                    <option value="">请选择制度</option>
                    <?php foreach($systems as $sys): ?>
                        <option value="<?=$sys['id']?>" data-code="<?=$sys['code']?>"><?=$sys['name']?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>起始期间</label>
                <select name="start_year" style="width:53%;">
                    <?php $y=date('Y'); for($i=$y-5;$i<=$y+2;$i++) echo '<option value="'.$i.'"'.($i==$y?' selected':'').'>'.$i.'年</option>'; ?>
                </select>
                <select name="start_month" style="width:37%;">
                    <?php for($m=1;$m<=12;$m++) echo '<option value="'.$m.'"'.($m==1?' selected':'').'>'.$m.'月</option>'; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <label>科目模板</label>
            <select name="template" id="template">
                <?php foreach($systems as $sys):
                    $code = $sys['code'];
                    $tpls = $template_map[$code] ?? ['standard'=>'标准模板'];
                    foreach($tpls as $k=>$txt):
                ?>
                <option value="<?=$k?>" data-system="<?=$code?>"><?=$txt?></option>
                <?php endforeach; endforeach; ?>
            </select>
            <div class="tip">如需导入自定义模板，上传JSON后本项自动禁用。<a href="/account_template_sample.json" style="color:#2676f5;text-decoration:underline;">下载JSON模板</a></div>
        </div>
        <div class="row">
            <label>导入自定义科目模板（JSON）</label>
            <div class="custom-upload">
                <input type="file" name="jsonfile" accept=".json,application/json" onchange="onJsonFileChange()">
                <span class="upload-tip">如上传，则以此为准。<br>字段包含code,name,category,direction,parent_code。</span>
            </div>
        </div>
        <button type="submit" class="submit-btn">创建账套</button>
        <a href="books.php" class="back-link">返回</a>
    </form>
</div>
<?php include 'templates/footer.php'; ?>