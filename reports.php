<?php
require_once 'inc/functions.php';
checkLogin();
include 'templates/header.php';
?>
<style>
body {
    background: #f6f8fc;
}
.reports-main-wrap {
    max-width: 1000px;
    margin: 48px auto 70px auto;
    padding: 40px 0 40px 0;
    background: rgba(255,255,255,0.92);
    border-radius: 22px;
    box-shadow: 0 6px 48px #dbeafd44;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.reports-title {
    font-size: 2.1rem;
    color: #247bfc;
    font-weight: bold;
    margin-bottom: 32px;
    letter-spacing: 2px;
    text-align: center;
    text-shadow: 0 2px 8px #eaf2fc;
}
.reports-list-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 20px 26px;
    justify-content: center;
}
.report-card {
    background: linear-gradient(135deg,#f5faff 0%,#e8f0ff 100%);
    border: 1.3px solid #e3ebfa;
    border-radius: 12px;
    box-shadow: 0 1px 10px #eaf3fd88;
    padding: 18px 18px 15px 18px;
    display: flex;
    flex-direction: row;
    align-items: center;
    width: 275px;
    min-height: 74px;
    transition: box-shadow .14s, border .16s, background .14s, transform .13s;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    text-decoration: none !important;
    gap: 13px;
}
.report-card:before {
    display:none;
}
.report-card:hover {
    border: 1.3px solid #247bfc;
    background: linear-gradient(135deg,#e5f1ff 0%,#fafdff 100%);
    box-shadow: 0 6px 18px #bad7fc55;
    transform: translateY(-2px) scale(1.025);
}
.report-icon {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    background: #e3ecfd;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
    color: #247bfc;
    margin-right: 3px;
    flex-shrink: 0;
    box-shadow: 0 2px 8px #e7f2ff33;
    border: 1px solid #e0eefd;
}
.report-card-title {
    font-size: 1.11rem;
    color: #247bfc;
    font-weight: bold;
    margin-bottom: 2px;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.report-card-title span {
    background: #eaf3ff;
    color: #3890f7;
    border-radius: 6px;
    font-size: 0.86em;
    padding: 2px 7px;
    font-weight: normal;
    margin-left: 2px;
    display: inline-block;
}
.report-card-desc {
    font-size: 0.95rem;
    color: #568afc;
    margin-bottom: 0;
    line-height: 1.4;
    min-height: 19px;
}
.report-card-link {
    display: none;
}
.report-card-main {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    flex:1;
}
@media(max-width:1050px){
    .reports-main-wrap { padding: 16px 2px;}
    .reports-list-wrap { gap:12px;}
    .report-card { width:92vw; min-width:0; }
}
@media(max-width:700px){
    .reports-list-wrap { gap:7px;}
    .report-card { width:98vw; min-width:0; padding:12px 2vw 10px 2vw;}
    .reports-main-wrap { margin:9px 0 40px 0;}
}
.report-card-title-links {
    display: flex;
    gap: 6px;
    margin-top: 5px;
}
.report-card-title-link {
    font-size: 0.92em;
    background: #e6f4ff;
    color: #247bfc;
    border-radius: 5px;
    padding: 1px 9px;
    margin-right: 2px;
    text-decoration: none !important;
    border: 1px solid #d4eaff;
    transition: background .13s, color .13s, border .13s;
    font-weight: 500;
    display: inline-block;
}
.report-card-title-link:hover {
    background: #247bfc;
    color: #fff;
    border-color: #247bfc;
}
</style>
<div class="reports-main-wrap">
    <div class="reports-title">报表中心</div>
    <div class="reports-list-wrap">

        <a href="reports_balancesheet.php" class="report-card">
            <div class="report-icon" style="background:#e3ecfd;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="4" width="16" height="16" rx="4" fill="#247bfc" fill-opacity=".12"/><path d="M8 16V8M12 16V12M16 16V10" stroke="#247bfc" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">资产负债表</div>
                <div class="report-card-desc">反映企业在某一时点的资产、负债和所有者权益。</div>
            </div>
        </a>

        <div class="report-card" style="cursor:default;">
            <div class="report-icon" style="background:#e5f6f8;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="4" width="16" height="16" rx="4" fill="#00c6ae" fill-opacity=".12"/><path d="M8 16l3-8 3 6 2-4" stroke="#15bba8" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">利润表</div>
                <div class="report-card-title-links">
                    <a href="reports_profit.php" class="report-card-title-link">月度版</a>
                    <a href="reports_profit_quarter.php" class="report-card-title-link">季度版</a>
                </div>
                <div class="report-card-desc">反映企业在一定期间的经营成果。</div>
            </div>
        </div>

        <div class="report-card" style="cursor:default;">
            <div class="report-icon" style="background:#f4fbe4;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="4" width="16" height="16" rx="4" fill="#a6e22e" fill-opacity=".13"/><path d="M16 8h-8M8 8v8m8-8v8" stroke="#7ace33" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">现金流量表</div>
                <div class="report-card-title-links">
                    <a href="reports_cashflow.php" class="report-card-title-link">月度版</a>
                    <a href="reports_cashflow_quarter.php" class="report-card-title-link">季度版</a>
                </div>
                <div class="report-card-desc">反映企业现金和现金等价物的流入与流出。</div>
            </div>
        </div>

        <a href="balance_sheet.php" class="report-card">
            <div class="report-icon" style="background:#e6eaff;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><circle cx="12" cy="12" r="8" fill="#247bfc" fill-opacity=".10"/><path d="M8 15h8M8 12h8M8 9h8" stroke="#247bfc" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">科目余额表</div>
                <div class="report-card-desc">各会计科目的期初、发生、期末余额，支持明细跳转与打印。</div>
            </div>
        </a>
        <a href="ledger.php" class="report-card">
            <div class="report-icon" style="background:#eaf9e8;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="6" width="16" height="12" rx="2.5" fill="#0bbd51" fill-opacity=".13"/><path d="M8 10h8M8 14h8" stroke="#21a045" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">总账</div>
                <div class="report-card-desc">按科目汇总显示分录流水，支持区间与明细展开。</div>
            </div>
        </a>
        <a href="ledger_detail.php" class="report-card">
            <div class="report-icon" style="background:#fcefee;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="4" width="16" height="16" rx="4" fill="#fd5999" fill-opacity=".10"/><path d="M8 8h8M8 12h8M8 16h5" stroke="#fd5999" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">明细账</div>
                <div class="report-card-desc">按科目与期间查询明细账流水，支持科目切换与打印。</div>
            </div>
        </a>
        <a href="voucher_journal.php" class="report-card">
            <div class="report-icon" style="background:#f2f7fd;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="5" width="16" height="14" rx="3" fill="#247bfc" fill-opacity=".08"/><path d="M8 9h8M8 13h8" stroke="#247bfc" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">凭证序时簿</div>
                <div class="report-card-desc">全账套凭证流水，按区间和科目筛选，便于稽核。</div>
            </div>
        </a>
        <a href="ledger_multicolumn.php" class="report-card">
            <div class="report-icon" style="background:#f6f3ff;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="4" width="16" height="16" rx="4" fill="#7c61ff" fill-opacity=".11"/><path d="M8 8h2v8H8zM14 8h2v8h-2z" fill="#7c61ff"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">多栏账</div>
                <div class="report-card-desc">主科目及下属明细多栏对照，适合成本、往来辅助账。</div>
            </div>
        </a>
        <a href="reports_notes.php" class="report-card">
            <div class="report-icon" style="background:#fffbe6;">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none"><rect x="4" y="4" width="16" height="16" rx="4" fill="#ffb300" fill-opacity=".12"/><path d="M8 9h8M8 13h8M8 17h8" stroke="#ffb300" stroke-width="2" stroke-linecap="round"/><circle cx="7" cy="7" r="2" fill="#ffb300" fill-opacity=".20"/></svg>
            </div>
            <div class="report-card-main">
                <div class="report-card-title">财务报表附注</div>
                <div class="report-card-desc">自动生成财务报表项目注释与补充说明。</div>
            </div>
        </a>
        <!-- 可扩展更多报表类型 -->
    </div>
</div>
<?php include 'templates/footer.php'; ?>