<?php
require_once 'inc/functions.php';
checkLogin();
$accounts = getAccounts();
$acct_map = [];
foreach($accounts as $a) $acct_map[$a['code']] = $a['code'].' '.$a['name'];
$err = '';
function last_day_of_month($date) {
    return date('Y-m-t', strtotime($date));
}
$today = date('Y-m-d');
$date = last_day_of_month($today);

$summary = $account_code = $debit = $credit = [];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 编辑模式下加载原始数据
if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $voucher = function_exists('getVoucher') ? getVoucher($id) : null;
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
        // 不允许借贷同时有值
        if(floatval($debit[$i])>0 && floatval($credit[$i])>0) {
            $err = "第".($i+1)."行不能同时输入借贷金额！";
            break;
        }
        if(trim($account_code[$i]) && (floatval($debit[$i])>0 || floatval($credit[$i])>0)) {
            $items[] = [
                'summary'=>trim($summary[$i]),
                'account_code'=>$account_code[$i],
                'debit'=>floatval($debit[$i]),
                'credit'=>floatval($credit[$i])
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
            if ($id && function_exists('updateVoucher')) {
                updateVoucher($id, $date, '', $items);
            } else {
                addVoucher($date, '', $items);
            }
            header('Location: vouchers.php');
            exit;
        }
    }
}
include 'templates/header.php';
?>
<style>
.voucher-wrap {
    background: #fff;
    border-radius: 8px;
    padding: 32px 32px 24px 32px;
    margin: 36px auto;
    box-shadow: 0 3px 12px rgba(0,0,0,0.09);
    max-width: 900px;
    min-width: 600px;
}
.voucher-title {
    font-size: 24px; font-weight: bold; letter-spacing: 2px; margin-bottom: 18px;
    text-align:center;
}
.voucher-form-row {
    display: flex; align-items: center; margin-bottom: 18px; gap: 24px;
}
.voucher-form-row label {font-weight: normal; color: #444;}
.voucher-form-row input[type="date"] {
    height: 36px; font-size: 16px; border: 1px solid #ccc; border-radius: 4px; padding: 2px 10px;
}
.voucher-table {
    width: 100%; border-collapse: collapse; margin-bottom: 10px; background: #fafbfc;
}
.voucher-table th, .voucher-table td {
    border: 1px solid #e2e2e2;
    padding: 10px 3px; text-align: center; font-size: 16px;
}
.voucher-table th { background: #f3f6fa; font-weight: bold;}
.voucher-table tr:nth-child(even) { background: #fcfcfe;}
.voucher-table input, .voucher-table select {
    font-size: 16px; border: 1px solid #d0d0d0; border-radius: 2.5px; padding: 6px 7px; width: 96%; box-sizing: border-box; height: 34px;
}
.voucher-table input:focus, .voucher-table select:focus {
    border-color: #3386f1; outline: none; background: #f0f8ff;
}
.voucher-table .amt { text-align: right; }
.voucher-table .row-idx { color: #888; width: 38px;}
.voucher-table .remove-btn {
    color: #f55; background: none; border: none; font-size: 20px; cursor: pointer; margin-left: 4px;
}
.voucher-table .remove-btn:hover { color: #b00;}
.voucher-table .add-row-btn {
    color: #19be6b; font-size: 26px; background: none; border: none; cursor: pointer;
}
.voucher-total-row { background: #f6f6f6; font-weight: bold; }
.voucher-actions {margin-top: 18px;}
.voucher-actions .btn {
    margin-right: 14px; padding: 8px 28px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;
    background: #3386f1; color: #fff; transition: background 0.2s;
}
.voucher-actions .btn.cancel {
    background: #eee; color: #555; border: 1px solid #ddd;
}
.voucher-actions .btn:hover:not(.cancel) { background: #2066c9;}
.voucher-actions .btn.cancel:hover { background: #e0e0e0;}
.err-msg {color: #f55; font-size: 16px; margin-bottom: 12px;}
.account-search-dropdown {
    position: absolute; z-index: 20; background: #fff; border:1px solid #ddd; min-width:220px; max-height:240px; overflow:auto; box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    left:0; top:38px;
}
.account-search-item {
    padding: 7px 14px; cursor: pointer; font-size:16px;
}
.account-search-item:hover, .account-search-item.active { background: #eaf4ff;}
.voucher-table .acctcell {position:relative;}
.voucher-table .acctcell input {background: #fff;}
</style>
<div class="voucher-wrap">
    <div class="voucher-title"><?= $id ? '编辑凭证' : '录入凭证' ?></div>
    <?php if($err): ?><div class="err-msg"><?=$err?></div><?php endif;?>
    <form method="post" id="voucherform" autocomplete="off" onsubmit="return beforeSubmit()">
        <div class="voucher-form-row">
            <label>日期：
                <input type="date" name="date" value="<?=htmlspecialchars($date)?>">
            </label>
        </div>
        <table class="voucher-table" id="voucher-table">
            <tr>
                <th class="row-idx"></th>
                <th>摘要</th>
                <th>科目</th>
                <th>借方金额</th>
                <th>贷方金额</th>
                <th></th>
            </tr>
            <tbody id="voucher-tbody">
            <?php
            $row_cnt = max(4, count($summary));
            for($i=0;$i<$row_cnt;$i++): ?>
            <tr>
                <td class="row-idx"><?=($i+1)?></td>
                <td><input type="text" name="summary[]" value="<?=htmlspecialchars($summary[$i]??'')?>" maxlength="100" autocomplete="off"></td>
                <td class="acctcell">
                    <input type="text" class="account-search" placeholder="编码/名称检索" autocomplete="off"
                        value="<?php
                            $val = $account_code[$i]??'';
                            echo isset($acct_map[$val]) ? htmlspecialchars($acct_map[$val]) : htmlspecialchars($val);
                        ?>">
                    <input type="hidden" name="account_code[]" value="<?=htmlspecialchars($account_code[$i]??'')?>">
                    <div class="account-search-dropdown" style="display:none"></div>
                </td>
                <td><input type="text" name="debit[]" class="amt" value="<?=htmlspecialchars($debit[$i]??'')?>" autocomplete="off"
                    onkeydown="amountSync(event,this,'credit[]')" oninput="clearOpposite(this,'credit[]')"></td>
                <td><input type="text" name="credit[]" class="amt" value="<?=htmlspecialchars($credit[$i]??'')?>" autocomplete="off"
                    onkeydown="amountSync(event,this,'debit[]')" oninput="clearOpposite(this,'debit[]')"></td>
                <td>
                    <button type="button" class="remove-btn" title="删除行" onclick="removeRow(this)">&times;</button>
                </td>
            </tr>
            <?php endfor; ?>
            </tbody>
            <tr class="voucher-total-row">
                <td></td>
                <td colspan="2" style="text-align:right;">合计：</td>
                <td id="total-debit" class="amt" style="font-weight:bold;"><?=number_format(array_sum($debit),2)?></td>
                <td id="total-credit" class="amt" style="font-weight:bold;"><?=number_format(array_sum($credit),2)?></td>
                <td>
                    <button type="button" class="add-row-btn" onclick="addRow()" title="添加分录行">＋</button>
                </td>
            </tr>
        </table>
        <div class="voucher-actions">
            <button class="btn" type="submit">保存</button>
            <a class="btn cancel" href="vouchers.php">返回</a>
            <button class="btn cancel" type="button" onclick="clearVoucher()">清空</button>
        </div>
    </form>
</div>
<script>
const accountsData = <?php
    echo json_encode(array_map(function($a){
        return [
            'code'=>$a['code'],
            'name'=>$a['name'],
            'label'=>$a['code'].' '.$a['name']
        ];
    }, $accounts));
?>;

function bindAccountSearch() {
    document.querySelectorAll('.acctcell').forEach(function(cell){
        const inp = cell.querySelector('.account-search');
        const hidden = cell.querySelector('input[type=hidden]');
        const dd = cell.querySelector('.account-search-dropdown');
        inp.addEventListener('input', function(){
            showAccountDropdown(inp, dd, hidden, inp.value);
        });
        inp.addEventListener('focus', function(){
            showAccountDropdown(inp, dd, hidden, inp.value);
        });
        inp.addEventListener('blur', function(){
            setTimeout(function(){
                dd.style.display='none';
            }, 180);
        });
        inp.addEventListener('keydown', function(e){
            if(dd.style.display!=='block') return;
            let items = Array.from(dd.querySelectorAll('.account-search-item'));
            let curr = items.findIndex(item=>item.classList.contains('active'));
            if(e.key==='ArrowDown') {
                e.preventDefault();
                let next = curr+1>=items.length?0:curr+1;
                items.forEach(it=>it.classList.remove('active'));
                items[next].classList.add('active');
                items[next].scrollIntoView({block:"nearest"});
            } else if(e.key==='ArrowUp') {
                e.preventDefault();
                let prev = curr-1<0?items.length-1:curr-1;
                items.forEach(it=>it.classList.remove('active'));
                items[prev].classList.add('active');
                items[prev].scrollIntoView({block:"nearest"});
            } else if(e.key==='Enter') {
                if(curr>=0) {
                    e.preventDefault();
                    items[curr].click();
                }
            } else if(e.key==='Escape') {
                dd.style.display='none';
            }
        });
    });
}
function showAccountDropdown(inp, dd, hidden, val) {
    let v = (val||'').trim();
    let arr = accountsData.filter(a=>{
        return a.code.includes(v) || a.name.includes(v) || a.label.includes(v);
    });
    dd.innerHTML = arr.length ?
        arr.slice(0,20).map((a,i)=>`<div class="account-search-item${i===0?' active':''}" data-code="${a.code}">${a.label}</div>`).join('')
        : '<div class="account-search-item" data-code="">无匹配</div>';
    dd.style.display = 'block';
    dd.querySelectorAll('.account-search-item').forEach(item=>{
        item.onclick = function(){
            inp.value = item.innerText;
            hidden.value = item.getAttribute('data-code');
            dd.style.display='none';
        };
    });
}
function amountSync(e, input, targetName) {
    if(e.key === '=') {
        e.preventDefault();
        let tr = input.closest('tr');
        let target = tr.querySelector('input[name="'+targetName+'"]');
        input.value = '';
        if(target) input.value = target.value;
        clearOpposite(input, targetName);
    }
    setTimeout(calcTotal, 10);
}
function clearOpposite(input, targetName) {
    let tr = input.closest('tr');
    let target = tr.querySelector('input[name="'+targetName+'"]');
    if(input.value && target && parseFloat(target.value)>0) {
        target.value = '';
    }
    calcTotal();
}
function addRow() {
    let tb = document.getElementById('voucher-tbody');
    let tr = tb.rows[0].cloneNode(true);
    Array.from(tr.querySelectorAll('input')).forEach(el=>el.value='');
    tr.querySelector('.row-idx').innerText = tb.rows.length+1;
    tb.appendChild(tr);
    bindAccountSearch();
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
}
function calcTotal() {
    let debit = 0, credit = 0;
    document.querySelectorAll('input[name="debit[]"]').forEach(el=>debit+=parseFloat(el.value)||0);
    document.querySelectorAll('input[name="credit[]"]').forEach(el=>credit+=parseFloat(el.value)||0);
    document.getElementById('total-debit').innerText = debit.toFixed(2);
    document.getElementById('total-credit').innerText = credit.toFixed(2);
}
function beforeSubmit() {
    calcTotal();
    // 额外校验：确保所有 account_code[] 均为编码
    document.querySelectorAll('.acctcell').forEach(function(cell){
        let inp = cell.querySelector('.account-search');
        let hidden = cell.querySelector('input[type=hidden]');
        if(inp.value && !hidden.value) {
            // 如果input有内容但hidden为空，强制清空
            inp.value = '';
        }
    });
    return true;
}
document.querySelectorAll('input[name="debit[]"],input[name="credit[]"]').forEach(el=>{
    el.addEventListener('input', calcTotal);
});
bindAccountSearch();
window.addEventListener('DOMContentLoaded', calcTotal);
</script>
<?php include 'templates/footer.php'; ?>