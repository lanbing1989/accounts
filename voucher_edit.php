<?php
require_once 'inc/functions.php';
require_once 'inc/closing_utils.php';
checkLogin();

$book = getCurrentBook();
if (!$book) {
    header('Location: books_add.php');
    exit;
}
$book_id = intval($book['id']);

global $db;

if (isset($_SESSION['global_period']) && preg_match('/^(\d{4})-(\d{1,2})$/', $_SESSION['global_period'], $m)) {
    $global_year = intval($m[1]);
    $global_month = intval($m[2]);
} else {
    $global_year = 0;
    $global_month = 0;
}

$accounts = getAccounts($book_id);

function pinyin_abbr($str) {
    static $map = [
        '银'=>'y', '行'=>'h', '存'=>'c', '款'=>'k', '现'=>'x', '金'=>'j', '采'=>'c', '购'=>'g', '办'=>'b', '公'=>'g', '用'=>'y', '品'=>'p', '原'=>'y', '材'=>'c', '料'=>'l', '成'=>'c', '本'=>'b', '销'=>'x', '售'=>'s', '费'=>'f', '主'=>'z', '营'=>'y',
        '收'=>'s', '入'=>'r', '贷'=>'d', '应'=>'y', '付'=>'f', '职'=>'z', '工'=>'g', '薪'=>'x', '福'=>'f', '利'=>'l', '奖'=>'j', '津'=>'j', '贴'=>'t', '和'=>'h', '补'=>'b', '折'=>'z', '旧'=>'j', '摊'=>'t', '销'=>'x', '项'=>'x', '税'=>'s', '额'=>'e',
    ];
    $abbr = '';
    $len = mb_strlen($str, 'utf-8');
    for ($i=0;$i<$len;$i++) {
        $c = mb_substr($str, $i, 1, 'utf-8');
        $abbr .= $map[$c] ?? '';
    }
    return $abbr;
}
$acct_map = [];
$acct_pinyin = [];
foreach($accounts as $a) {
    $acct_map[$a['code']] = $a['code'].' '.$a['name'];
    $acct_pinyin[$a['code']] = $a['code'].' '.$a['name'].' '.pinyin_abbr($a['name']);
}

$common_summaries = [
    "收回货款",
    "支付工资",
    "购买办公用品",
    "支付房租",
    "销售收入",
    "银行存款",
    "现金收入",
    "采购原材料",
    "支付水电费",
    "计提折旧",
    "支付差旅费",
    "借款",
    "利息收入",
    "利息支出",
    "缴纳税费",
];

$err = '';

function last_day_of_month($year, $month) {
    return date('Y-m-t', strtotime("$year-$month-01"));
}

$stmt = $db->prepare("SELECT MAX(year) as y, MAX(month) as m FROM closings WHERE book_id=?");
$stmt->execute([$book_id]);
$closing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($global_year && $global_month) {
    $y = $global_year;
    $m = $global_month;
    $default_date = last_day_of_month($y, $m);
} elseif ($closing && $closing['y'] && $closing['m']) {
    $y = intval($closing['y']);
    $m = intval($closing['m']) + 1;
    if ($m > 12) { $y++; $m = 1; }
    $default_date = last_day_of_month($y, $m);
} else {
    $y = intval($book['start_year']);
    $m = intval($book['start_month']);
    $default_date = last_day_of_month($y, $m);
}

$date = $default_date;
$summary = $account_code = $debit = $credit = [];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$auto = isset($_GET['auto']) && $_GET['auto'] == 1 && isset($_SESSION['auto_voucher_items']);
if ($auto && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $date = $_SESSION['auto_voucher_date'];
    $summary = [];
    $account_code = [];
    $debit = [];
    $credit = [];
    $items = $_SESSION['auto_voucher_items'];
    foreach ($items as $row) {
        $summary[] = $row['summary'];
        $account_code[] = $row['account_code'];
        $debit[] = $row['debit'];
        $credit[] = $row['credit'];
    }
}

if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST' && !$auto) {
    $voucher = function_exists('getVoucher') ? getVoucher($id, $book_id) : null;
    $items = function_exists('getVoucherItems') ? getVoucherItems($id) : [];
    if ($voucher) {
        $date = $voucher['date'];
        foreach ($items as $row) {
            $summary[] = $row['summary'];
            $account_code[] = $row['account_code'];
            $debit[] = $row['debit'];
            $credit[] = $row['credit'];
        }
    } else {
        $err = '未找到该凭证';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'] ?? $date;
    $summary = $_POST['summary'] ?? [];
    $account_code = $_POST['account_code'] ?? [];
    $debit = $_POST['debit'] ?? [];
    $credit = $_POST['credit'] ?? [];
    $items = [];
    for($i=0;$i<count($summary);$i++) {
        if(floatval($debit[$i])>0 && floatval($credit[$i])>0) {
            $err = "第".($i+1)."行不能同时输入借贷金额！";
            break;
        }
        if(trim($account_code[$i]) && (floatval($debit[$i])>0 || floatval($credit[$i])>0)) {
            $items[] = [
                'summary'=>trim($summary[$i]),
                'account_code'=>$account_code[$i],
                'debit'=>floatval(str_replace(',','',$debit[$i])),
                'credit'=>floatval(str_replace(',','',$credit[$i]))
            ];
        }
    }
    if (!$err) {
        $total_debit = array_sum(array_column($items, 'debit'));
        $total_credit = array_sum(array_column($items, 'credit'));
        if(abs($total_debit-$total_credit)>0.001) {
            $err = "借贷不平衡！";
        } elseif(count($items)<2) {
            $err = "至少两条分录！";
        } else {
            try {
                if ($id && function_exists('updateVoucher')) {
                    updateVoucher($id, $date, '', $items, $book_id);
                    header('Location: vouchers.php');
                    exit;
                } else {
                    addVoucher($date, '', $items, $book_id);

                    if ($auto && isset($_SESSION['auto_voucher_back_to_close']) && $_SESSION['auto_voucher_back_to_close']) {
                        unset($_SESSION['auto_voucher_items'], $_SESSION['auto_voucher_date'], $_SESSION['auto_voucher_summary'], $_SESSION['auto_voucher_book_id'], $_SESSION['auto_voucher_year'], $_SESSION['auto_voucher_month'], $_SESSION['auto_voucher_back_to_close']);
                        header("Location: closings.php?msg=结转凭证已生成");
                        exit;
                    }

                    if (isset($_POST['save_add'])) {
                        $summary = $account_code = $debit = $credit = [];
                        $err = '';
                    } else {
                        header('Location: vouchers.php');
                        exit;
                    }
                }
            } catch(Exception $e) {
                $err = $e->getMessage();
            }
        }
    }
}
include 'templates/header.php';
?>
<style>
body {
    background: #f4f7fa;
}
.voucher-wrap {
    background: #fff;
    border-radius: 18px;
    padding: 40px 60px 28px 60px;
    margin: 38px auto;
    box-shadow: 0 8px 40px rgba(67, 133, 241, 0.07);
    width: 100%;
    max-width: 1360px;
    min-width: 900px;
    overflow-x: auto;
}
.voucher-title {
    font-size: 28px; font-weight: 700; letter-spacing: 2px; margin-bottom: 26px;
    text-align:center; color: #233866;
}
.voucher-form-row {
    display: flex; align-items: center; margin-bottom: 22px; gap: 28px;
}
.voucher-form-row label {font-weight: 500; color: #3a3a3a;}
.voucher-form-row input[type="date"] {
    height: 38px; font-size: 16px; border: 1.5px solid #e3e8ef; border-radius: 7px; padding: 2px 14px;
    background: #f6fafd;
}
.voucher-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 10px; background: #fff;
    border-radius: 12px;
    overflow: hidden;
    table-layout: fixed;
}
.voucher-table th, .voucher-table td {
    border-bottom: 1px solid #e8eef4;
    padding: 11px 6px;
    font-size: 16px;
    background: #fff;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.voucher-table th {
    background: #f4f8fc;
    color: #233866;
    font-weight: 700;
    font-size: 17px;
    text-align: center;
    border-bottom: 2.5px solid #e1e8f2;
}
.voucher-table tr:last-child td {
    border-bottom: none;
}
.voucher-table input, .voucher-table select {
    height: 36px;
    background: #f7fafc;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0 10px;
    font-size: 15px;
    transition: border-color 0.2s;
    outline: none;
    width: 100%;
    box-sizing: border-box;
}
.voucher-table input:focus, .voucher-table select:focus {
    border-color: #3386f1;
    background: #eaf4ff;
}
.voucher-table input[type="text"][list] {
    width: 100%;
    height: 36px;
    background: #f7fafc;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0 10px;
    font-size: 15px;
    transition: border-color 0.2s;
    outline: none;
}
.voucher-table input[type="text"][list]:focus {
    border-color: #3386f1;
    background: #eaf4ff;
}
.voucher-table .summarycell {
    min-width: 110px;
    width: 100%;
}
.voucher-table .summary-input,
.voucher-table .acctcell input.account-search,
.voucher-table .amt {
    width: 100%;
    max-width: unset;
    min-width: unset;
    box-sizing: border-box;
}
.voucher-table .amt {
    text-align: right;
    min-width: 90px;
    font-family: 'Consolas', 'Menlo', monospace;
}
.voucher-table th .summary-fill-btn {
    font-size: 13px;
    padding: 0 13px;
    height: 32px;
    background: #e2fbe5;
    color: #19be6b;
    border: 1px solid #b9e3cb;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.2s,color 0.2s;
    margin-left: 12px;
    vertical-align: middle;
}
.voucher-table th .summary-fill-btn:hover {
    background: #19be6b;
    color: #fff;
}
.remove-btn {
    background: #f7fafc;
    color: #c9c9c9;
    border-radius: 50%;
    border: none;
    width: 32px;
    height: 32px;
    font-size: 22px;
    line-height: 32px;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.remove-btn:hover {
    color: #fff;
    background: #f55;
}
.voucher-total-row td {
    background: #fffbe6 !important;
    font-weight: bold;
    color: #b3830e;
}
.voucher-total-row td.amtcap {
    text-align: left !important;
    color: #666;
    font-size: 15px;
    border: none !important;
}
.voucher-total-row td.high { background: #ffeaea !important; color:#d83d31; }
.voucher-actions {
    display: flex;
    justify-content: flex-end;
    gap: 18px;
    margin-top: 24px;
}
.voucher-actions .btn {
    height: 41px;
    padding: 0 34px;
    border-radius: 7px;
    font-size: 16px;
    margin-bottom: 0;
    font-weight: 500;
}
.voucher-actions .btn:not(.cancel) {
    background: linear-gradient(90deg, #3386f1 0%, #4aa3ff 100%);
    color: #fff;
    border: none;
}
.voucher-actions .btn.cancel {
    background: #f7fafc;
    color: #666;
    border: 1px solid #d1d5db;
}
.voucher-actions .btn:not(.cancel):hover {
    background: linear-gradient(90deg, #286ccd 0%, #3386f1 100%);
}
.voucher-actions .btn.cancel:hover { background: #e6ecf3;}
.err-msg {color: #f55; font-size: 16px; margin-bottom: 12px;}
.voucher-table .acctcell, .voucher-table .summarycell {position:relative;}
.voucher-table .acctcell input, .voucher-table .summarycell input {background: #fff;}
.voucher-table tr:hover {background: #f8fafc;}
@media (max-width: 800px) {
    .voucher-wrap {min-width: unset; padding: 14px;}
    .voucher-table {font-size:15px;}
    .voucher-table th, .voucher-table td {padding: 6px 2px;}
    .voucher-actions {flex-direction: column; gap: 8px;}
}
</style>
<div class="voucher-wrap">
    <div class="voucher-title"><?= $id ? '编辑凭证' : '录入凭证' ?></div>
    <?php if($err): ?><div class="err-msg" id="errBox"><?=$err?></div><?php endif;?>
    <form method="post" id="voucherform" autocomplete="off" onsubmit="return beforeSubmit()">
        <div class="voucher-form-row">
            <label>日期：
                <input type="date" name="date" value="<?=htmlspecialchars($date)?>">
            </label>
        </div>
        <table class="voucher-table" id="voucher-table">
            <tr>
                <th style="width:6%;">#</th>
                <th style="width:22%;">摘要
                    <button type="button" class="summary-fill-btn" onclick="fillAllSummary()">全部填充</button>
                </th>
                <th style="width:26%;">科目</th>
                <th style="width:14%;">借方金额</th>
                <th style="width:14%;">贷方金额</th>
                <th style="width:8%;">操作</th>
            </tr>
            <tbody id="voucher-tbody">
            <?php
            $row_cnt = max(4, count($summary));
            for($i=0;$i<$row_cnt;$i++): ?>
            <tr>
                <td class="row-idx"><?=($i+1)?></td>
                <td class="summarycell">
                    <input type="text" name="summary[]" class="summary-input" value="<?=htmlspecialchars($summary[$i]??'')?>" maxlength="100" autocomplete="off" list="common_summaries" placeholder="请输入或选择摘要">
                </td>
                <td class="acctcell">
                    <input type="text" name="account_code_text[]" class="account-search" list="accounts_datalist" autocomplete="off"
                        value="<?php
                            $val = $account_code[$i]??'';
                            echo isset($acct_map[$val]) ? htmlspecialchars($acct_map[$val]) : htmlspecialchars($val);
                        ?>" placeholder="编码/名称/拼音检索">
                    <input type="hidden" name="account_code[]" value="<?=htmlspecialchars($account_code[$i]??'')?>">
                </td>
                <td><input type="text" name="debit[]" class="amt" value="<?=htmlspecialchars($debit[$i]??'')?>" autocomplete="off"
                    onkeydown="amountSync(event,this,'credit[]',<?=$i?>)" oninput="formatAmtInput(this, false);calcFieldExpr(this);clearOpposite(this,'credit[]')" onblur="formatAmtInput(this, true)"></td>
                <td><input type="text" name="credit[]" class="amt" value="<?=htmlspecialchars($credit[$i]??'')?>" autocomplete="off"
                    onkeydown="amountSync(event,this,'debit[]',<?=$i?>)" oninput="formatAmtInput(this, false);calcFieldExpr(this);clearOpposite(this,'debit[]')" onblur="formatAmtInput(this, true)"></td>
                <td>
                    <button type="button" class="remove-btn" title="删除行" onclick="removeRow(this)">&times;</button>
                </td>
            </tr>
            <?php endfor; ?>
            </tbody>
            <tr class="voucher-total-row">
                <td class="amtcap" colspan="3" id="amtcap-tip"></td>
                <td id="total-debit" class="amt" style="font-weight:bold;"></td>
                <td id="total-credit" class="amt" style="font-weight:bold;"></td>
                <td>
                    <button type="button" class="add-row-btn" onclick="addRow()" title="添加分录行">＋</button>
                </td>
            </tr>
        </table>
        <datalist id="accounts_datalist">
            <?php foreach($accounts as $a): ?>
                <option value="<?=htmlspecialchars($a['code'].' '.$a['name'])?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="common_summaries">
            <?php foreach($common_summaries as $s): ?>
                <option value="<?=htmlspecialchars($s)?>">
            <?php endforeach; ?>
        </datalist>
        <div class="voucher-actions">
            <button class="btn" type="submit" name="save" id="saveBtn">保存</button>
            <button class="btn" type="submit" name="save_add">保存并新增</button>
            <button class="btn cancel" type="button" onclick="window.location='vouchers.php'">返回</button>
            <button class="btn cancel" type="button" onclick="clearVoucher()">清空</button>
        </div>
    </form>
</div>
<script>
function fillAllSummary() {
    let tb = document.getElementById('voucher-tbody');
    let rows = tb.querySelectorAll('tr');
    if (rows.length < 2) return;
    let first = rows[0].querySelector('input[name="summary[]"]');
    let val = first.value;
    for (let i = 1; i < rows.length; i++) {
        let summaryInput = rows[i].querySelector('input[name="summary[]"]');
        if (summaryInput && summaryInput.value === '') {
            summaryInput.value = val;
        }
    }
}

function bindAccountDatalist() {
    document.querySelectorAll('.acctcell').forEach(function(cell){
        const inp = cell.querySelector('input.account-search');
        const hidden = cell.querySelector('input[type=hidden]');
        inp.addEventListener('blur', function(){
            // 取 code 部分（假设 code 和 name间有空格分隔）
            var code = inp.value.split(' ')[0];
            // 校验是否为有效科目
            var found = false;
            <?php
            $valid_codes = array_map(function($a){ return $a['code']; }, $accounts);
            ?>
            var validCodes = <?=json_encode($valid_codes)?>;
            if (validCodes.indexOf(code) !== -1) {
                hidden.value = code;
            } else {
                hidden.value = '';
            }
        });
    });
}
function formatAmtInput(input, blur = false) {
    let val = input.value.replace(/,/g,'').replace(/[^\d.]/g, '');
    let idx = val.indexOf('.');
    if(idx >= 0) {
        val = val.substr(0, idx + 1) + val.substr(idx + 1).replace(/\./g,'');
    }
    let match = val.match(/^(\d*)(\.\d{0,2})?$/);
    if(!match) {
        input.value = '';
        return;
    }
    input.value = match[1] + (match[2] || '');
    if(blur && input.value) {
        let num = parseFloat(input.value);
        if (!isNaN(num)) {
            input.value = num.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    }
    calcTotal();
}
function amountSync(e, input, targetName, idx) {
    if(e.key === '=') {
        e.preventDefault();
        let tr = input.closest('tr');
        let target = tr.querySelector('input[name="'+targetName+'"]');
        input.value = '';
        if(target) input.value = target.value;
        clearOpposite(input, targetName);
    }
    setTimeout(function(){
        calcTotal();
        let tb = document.getElementById('voucher-tbody');
        let trs = tb.querySelectorAll('tr');
        if ((e.key==='Enter'||e.key==='Tab') && input.name==='credit[]' && idx===trs.length-1) {
            addRow();
            setTimeout(()=>{
                trs[trs.length-1].querySelector('input[name="summary[]"]').focus();
            }, 30);
        }
        if (e.ctrlKey && e.key==='Enter') {
            document.getElementById('saveBtn').click();
        }
        if (e.ctrlKey && (e.key==='ArrowDown'||e.key==='Down')) {
            addRow();
            setTimeout(()=>{
                tb.querySelectorAll('tr')[trs.length].querySelector('input[name="summary[]"]').focus();
            }, 30);
        }
    }, 10);
}
function calcFieldExpr(input) {
    let v = input.value;
    if (v && v[0] === '=') {
        try {
            if (!/^[=0-9+\-*/(). ]+$/.test(v)) return;
            let expr = v.substr(1);
            let res = Function('"use strict";return (' + expr + ')')();
            if (!isNaN(res)) input.value = res;
        } catch(e) { }
    }
    calcTotal();
}
function clearOpposite(input, targetName) {
    let tr = input.closest('tr');
    let target = tr.querySelector('input[name="'+targetName+'"]');
    if(input.value && target && parseFloat(target.value.replace(/,/g,''))>0) {
        target.value = '';
    }
    calcTotal();
}
function removeRow(btn) {
    let tb = document.getElementById('voucher-tbody');
    if(tb.rows.length<=2) return alert('至少两条分录');
    btn.closest('tr').remove();
    Array.from(tb.rows).forEach((tr,i)=>tr.querySelector('.row-idx').innerText=i+1);
    calcTotal();
}
function clearVoucher() {
    if(!confirm('确定要清空所有内容？')) return;
    let tb = document.getElementById('voucher-tbody');
    while(tb.rows.length>4) tb.deleteRow(tb.rows.length-1);
    Array.from(tb.rows).forEach(tr=>{
        Array.from(tr.querySelectorAll('input')).forEach(el=>el.value='');
    });
    calcTotal();
    setTimeout(()=>{tb.rows[0].querySelector('input[name="summary[]"]').focus();}, 50);
}
function calcTotal() {
    let debit = 0, credit = 0;
    document.querySelectorAll('input[name="debit[]"]').forEach(el=>debit+=parseFloat((el.value||'').replace(/,/g,''))||0);
    document.querySelectorAll('input[name="credit[]"]').forEach(el=>credit+=parseFloat((el.value||'').replace(/,/g,''))||0);
    document.getElementById('total-debit').innerText = debit.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('total-credit').innerText = credit.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    let cap = numberToChinese(debit);
    let capTip = debit > 0 ? '合计（大写）：' + cap : '';
    document.getElementById('amtcap-tip').innerText = capTip;
    let tr = document.querySelector('.voucher-total-row');
    if(Math.abs(debit-credit)>0.001) {
        tr.querySelectorAll('td').forEach(td=>td.classList.add('high'));
    } else {
        tr.querySelectorAll('td').forEach(td=>td.classList.remove('high'));
    }
}
function beforeSubmit() {
    calcTotal();
    document.querySelectorAll('.acctcell').forEach(function(cell){
        let inp = cell.querySelector('.account-search');
        let hidden = cell.querySelector('input[type=hidden]');
        if(inp.value && !hidden.value) {
            inp.value = '';
        }
    });
    return true;
}
function addRow() {
    let tb = document.getElementById('voucher-tbody');
    let tr = tb.rows[0].cloneNode(true);
    Array.from(tr.querySelectorAll('input')).forEach(el=>el.value='');
    tr.querySelector('.row-idx').innerText = tb.rows.length+1;
    tb.appendChild(tr);
    bindAccountDatalist();
    calcTotal();
}
bindAccountDatalist();
window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="debit[]"],input[name="credit[]"]').forEach(el=>{
        el.addEventListener('input', function(){ formatAmtInput(this, false); });
        el.addEventListener('blur', function(){ formatAmtInput(this, true); });
    });
    calcTotal();
});
function numberToChinese(n) {
    if(isNaN(n)) return '';
    n = parseFloat(n);
    if(n === 0) return '零元整';
    var fraction = ['角', '分'];
    var digit = ['零','壹','贰','叁','肆','伍','陆','柒','捌','玖'];
    var unit = [['元','万','亿'],['','拾','佰','仟']];
    var head = n < 0 ? '负' : '';
    n = Math.abs(n);

    var s = '';

    for (var i = 0; i < fraction.length; i++) {
        s += (digit[Math.floor(n * 10 * Math.pow(10, i)) % 10] + fraction[i]).replace(/零./, '');
    }
    s = s || '整';
    n = Math.floor(n);

    for (var i = 0; i < unit[0].length && n > 0; i++) {
        var p = '';
        for (var j = 0; j < unit[1].length && n > 0; j++) {
            p = digit[n % 10] + unit[1][j] + p;
            n = Math.floor(n / 10);
        }
        s = p.replace(/(零.)*零$/, '').replace(/^$/, '零') + unit[0][i] + s;
    }
    s = head + s.replace(/(零.)*零元/, '元')
                .replace(/(零.)+/g, '零')
                .replace(/^整$/, '零元整');
    s = s.replace(/元零整$/,"元整");
    return s;
}
</script>
<?php include 'templates/footer.php'; ?>