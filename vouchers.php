<?php
require_once 'inc/functions.php';
checkLogin();
$accounts = getAccounts();
$acct_map = [];
foreach($accounts as $a){
    $acct_map[$a['code']] = $a['code'].' '.$a['name'];
}
function get_items($v) {
    if (isset($v['items']) && is_array($v['items'])) return $v['items'];
    if (function_exists('getVoucherItems')) return getVoucherItems($v['id']);
    return [];
}

// 删除凭证功能
if (isset($_GET['delete']) && intval($_GET['delete']) > 0 && function_exists('deleteVoucher')) {
    $del_id = intval($_GET['delete']);
    deleteVoucher($del_id);
    header('Location: vouchers.php');
    exit;
}

$vouchers = getVouchers();

include 'templates/header.php';
?>
<style>
.voucher-list-wrap {
    max-width: 1100px; margin: 30px auto 0; background: #f7faff; border-radius: 12px; box-shadow: 0 4px 14px rgba(0,0,0,0.07);
    padding: 32px 18px 28px 18px;
}
.voucher-card {
    background: #fff; border-radius: 8px; margin-bottom: 28px; box-shadow: 0 2px 11px rgba(0,0,0,0.05);
    overflow: hidden; border: 1.5px solid #e2e6f0;
}
.voucher-head {
    display:flex;justify-content:space-between;align-items:center; background: #f6fafe; padding: 11px 18px; border-bottom:1px solid #edf0f7;
}
.voucher-head-meta {font-size:16px;color:#357;letter-spacing:1px;}
.voucher-head-meta span {margin-right:18px; color: #555;}
.voucher-head-op {font-size:15px;}
.voucher-head-op a {color:#3386f1;text-decoration:none;margin-right:12px;}
.voucher-head-op a.btn-del {color: #f55;}
.voucher-head-op a.btn-del:hover {color: #b00;}
.voucher-head-op a:hover {text-decoration:underline;}
.voucher-items-table {
    width:100%;border-collapse:collapse;background: #fff;
}
.voucher-items-table th,.voucher-items-table td {
    border-bottom:1px solid #f0f0f0;padding:8px 8px;font-size:15px;
}
.voucher-items-table th {
    color:#666;background:#f8fafc;font-weight:500;
}
.voucher-items-table td {color:#444;}
.voucher-items-table tr:last-child td {border-bottom:none;}
.voucher-total-row td {background:#f3f6fa;font-weight:bold;}
.voucher-empty {
    color:#bbb;text-align:center;font-size:17px;margin:64px 0;
}
@media (max-width:900px) {
    .voucher-list-wrap {padding:8px;}
    .voucher-card {padding:0;}
}
</style>
<script>
function confirmDelete(id) {
    if(confirm('确定要删除该凭证吗？删除后无法恢复！')) {
        window.location = 'vouchers.php?delete=' + id;
    }
}
</script>
<div class="voucher-list-wrap">
    <div style="text-align:right;margin-bottom:16px;">
        <a class="new-btn" href="voucher_edit.php" style="background:#3386f1;color:#fff;padding:7px 20px;border-radius:4px;text-decoration:none;">新建凭证</a>
    </div>
    <?php if(!$vouchers): ?>
        <div class="voucher-empty">暂无凭证</div>
    <?php else: foreach($vouchers as $v): ?>
        <div class="voucher-card">
            <div class="voucher-head">
                <div class="voucher-head-meta">
                    <span><?=htmlspecialchars($v['date'])?></span>
                    <span>记-<?=isset($v['number']) ? htmlspecialchars($v['number']) : str_pad($v['id'],3,'0',STR_PAD_LEFT)?></span>
                    <?php if(isset($v['attach_count'])): ?>
                    <span>附单据: <?=intval($v['attach_count'])?> 张</span>
                    <?php endif; ?>
                    <?php if(isset($v['creator'])): ?>
                    <span>制单人: <?=htmlspecialchars($v['creator'])?></span>
                    <?php endif; ?>
                </div>
                <div class="voucher-head-op">
                    <a href="voucher_edit.php?id=<?=$v['id']?>">编辑</a>
                    <a href="javascript:void(0);" class="btn-del" onclick="confirmDelete(<?=$v['id']?>)">删除</a>
                </div>
            </div>
            <table class="voucher-items-table">
                <tr>
                    <th style="width:40%;">摘要</th>
                    <th style="width:28%;">科目</th>
                    <th style="width:16%;">借方金额</th>
                    <th style="width:16%;">贷方金额</th>
                </tr>
                <?php
                    $items = get_items($v);
                    $total_debit = 0; $total_credit = 0;
                    foreach($items as $row):
                        $debit = floatval($row['debit']);
                        $credit = floatval($row['credit']);
                        $total_debit += $debit;
                        $total_credit += $credit;
                ?>
                <tr>
                    <td><?=htmlspecialchars($row['summary'])?></td>
                    <td><?=isset($acct_map[$row['account_code']]) ? htmlspecialchars($acct_map[$row['account_code']]) : htmlspecialchars($row['account_code'])?></td>
                    <td style="text-align:right;"><?= $debit ? number_format($debit,2) : '' ?></td>
                    <td style="text-align:right;"><?= $credit ? number_format($credit,2) : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="voucher-total-row">
                    <td colspan="2" style="text-align:right;">合计：</td>
                    <td style="text-align:right;"><?=number_format($total_debit,2)?></td>
                    <td style="text-align:right;"><?=number_format($total_credit,2)?></td>
                </tr>
            </table>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php include 'templates/footer.php'; ?>