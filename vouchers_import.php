<?php
require_once 'inc/functions.php';
require_once 'vendor/autoload.php'; // phpoffice
checkLogin();
global $db;
session_start();

$book = getCurrentBook();
$book_id = $book ? $book['id'] : 0;

$err = ""; $msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls','xlsx','csv'])) {
            $err = "仅支持Excel或CSV文件！";
        } else {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
                // 检查表头
                $expected = ['A'=>'日期','B'=>'号','C'=>'摘要','D'=>'科目编号','E'=>'借/贷','F'=>'金额'];
                $header = $rows[1];
                foreach($expected as $k=>$v){
                    if(trim($header[$k])!=$v) $err = "模板表头不正确！";
                }
                if(!$err){
                    // 按凭证号和日期分组
                    $groups = [];
                    for($i=2;$i<=count($rows);$i++){
                        $row = $rows[$i];
                        $date = trim($row['A']);
                        $number = trim($row['B']);
                        $summary = trim($row['C']);
                        $account_code = trim($row['D']);
                        $dc = trim($row['E']);
                        $amount = floatval($row['F']);
                        if(!$date||!$account_code||!$summary||!$amount||!in_array($dc,['借','贷'])){
                            $err .= "第{$i}行数据有误<br>";
                            continue;
                        }
                        $key = $date.'_'.$number;
                        $groups[$key]['date'] = $date;
                        $groups[$key]['number'] = $number;
                        $groups[$key]['items'][] = [
                            'summary'=>$summary,
                            'account_code'=>$account_code,
                            'debit'=>$dc=='借'?$amount:0,
                            'credit'=>$dc=='贷'?$amount:0,
                        ];
                    }
                    // 校验与入库
                    $success = 0; $fail = 0;
                    $db->beginTransaction();
                    foreach($groups as $k=>$g){
                        // 检查所有科目是否存在
                        $all_ok = true;
                        foreach($g['items'] as $item){
                            $stmt = $db->prepare("SELECT COUNT(*) FROM accounts WHERE code=? AND book_id=?");
                            $stmt->execute([$item['account_code'],$book_id]);
                            if(!$stmt->fetchColumn()) {
                                $all_ok = false;
                                $err .= "凭证{$g['number']}科目{$item['account_code']}不存在<br>";
                            }
                        }
                        // 校验借贷平衡
                        $total_debit = array_sum(array_column($g['items'],'debit'));
                        $total_credit = array_sum(array_column($g['items'],'credit'));
                        if(abs($total_debit-$total_credit)>0.01) {
                            $all_ok = false;
                            $err .= "凭证{$g['number']}借贷不平衡<br>";
                        }
                        if($all_ok){
                            // 插入凭证主表
                            $db->prepare("INSERT INTO vouchers (book_id, date, number, creator, created_at) VALUES (?,?,?,?,?)")
                                ->execute([$book_id, $g['date'], $g['number']?:null, $_SESSION['username'] ?? '', date('Y-m-d H:i:s')]);
                            $voucher_id = $db->lastInsertId();
                            foreach($g['items'] as $item){
                                $db->prepare("INSERT INTO voucher_items (voucher_id, summary, account_code, debit, credit) VALUES (?,?,?,?,?)")
                                    ->execute([$voucher_id, $item['summary'],$item['account_code'],$item['debit'],$item['credit']]);
                            }
                            $success++;
                        } else {
                            $fail++;
                        }
                    }
                    $db->commit();
                    $msg = "成功导入{$success}张凭证，失败{$fail}张。";
                }
            } catch (Exception $e) {
                $err = "文件解析失败：" . $e->getMessage();
            }
        }
    } else {
        $err = "文件上传失败";
    }
}
include 'templates/header.php';
?>
<div class="account-main-wrap">
    <h2 style="color:#2676f5;text-align:center;">批量导入凭证</h2>
    <?php if($err): ?><div style="color:#d83d31"><?=$err?></div><?php endif;?>
    <?php if($msg): ?><div style="color:#276c27"><?=$msg?></div><?php endif;?>
    <form method="post" enctype="multipart/form-data">
        <label>选择Excel/CSV文件：</label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
        <button type="submit" class="btn" style="margin-left:18px;">上传并导入</button>
    </form>
    <div style="color:#888;margin-top:14px;">
        <b>模板说明：</b>第一行为表头，依次为“日期,号,摘要,科目编号,借/贷,金额”。可下载
        <a href="static/voucher_import_template.xlsx" style="color:#3386f1;" download>模板文件</a>。
    </div>
</div>
<?php include 'templates/footer.php'; ?>