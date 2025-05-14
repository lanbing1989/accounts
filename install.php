<?php
if (!is_dir('db')) mkdir('db');
$db = new PDO('sqlite:db/accounting.db');

// 创建表结构
$sql = <<<EOT
CREATE TABLE IF NOT EXISTS accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    parent_code TEXT,
    direction TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS vouchers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL,
    description TEXT,
    user_id INTEGER
);
CREATE TABLE IF NOT EXISTS voucher_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    voucher_id INTEGER NOT NULL,
    account_code TEXT NOT NULL,
    debit REAL DEFAULT 0,
    credit REAL DEFAULT 0,
    summary TEXT
);
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT
);
EOT;
$db->exec($sql);

// 初始化默认用户
if ($db->query("SELECT count(*) FROM users")->fetchColumn()==0) {
    $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
        ->execute(['admin', password_hash('admin', PASSWORD_DEFAULT), '超级管理员']);
}

// 会计科目
$accounts = [
    ['1001', '库存现金', '资产', null, '借'],
    ['1002', '银行存款', '资产', null, '借'],
    ['1012', '其他货币资金', '资产', null, '借'],
    ['1101', '短期投资', '资产', null, '借'],
    ['1121', '应收票据', '资产', null, '借'],
    ['1122', '应收账款', '资产', null, '借'],
    ['1123', '预付账款', '资产', null, '借'],
    ['1131', '应收股利', '资产', null, '借'],
    ['1132', '应收利息', '资产', null, '借'],
    ['1221', '其他应收款', '资产', null, '借'],
    ['1401', '材料采购', '资产', null, '借'],
    ['1402', '在途物资', '资产', null, '借'],
    ['1403', '原材料', '资产', null, '借'],
    ['1404', '材料成本差异', '资产', null, '借'],
    ['1405', '库存商品', '资产', null, '借'],
    ['1407', '商品进销差价', '资产', null, '借'],
    ['1408', '委托加工物资', '资产', null, '借'],
    ['1411', '周转材料', '资产', null, '借'],
    ['1421', '消耗性生物资产', '资产', null, '借'],
    ['1501', '长期债券投资', '资产', null, '借'],
    ['1511', '长期股权投资', '资产', null, '借'],
    ['1601', '固定资产', '资产', null, '借'],
    ['1602', '累计折旧', '资产', null, '贷'],
    ['1604', '在建工程', '资产', null, '借'],
    ['1605', '工程物资', '资产', null, '借'],
    ['1606', '固定资产清理', '资产', null, '借'],
    ['1621', '生产性生物资产', '资产', null, '借'],
    ['1622', '生产性生物资产累计折旧', '资产', null, '贷'],
    ['1701', '无形资产', '资产', null, '借'],
    ['1702', '累计摊销', '资产', null, '贷'],
    ['1801', '长期待摊费用', '资产', null, '借'],
    ['1901', '待处理财产损溢', '资产', null, '借'],
    ['2001', '短期借款', '负债', null, '贷'],
    ['2201', '应付票据', '负债', null, '贷'],
    ['2202', '应付账款', '负债', null, '贷'],
    ['2203', '预收账款', '负债', null, '贷'],
    ['2211', '应付职工薪酬', '负债', null, '贷'],
    ['2221', '应交税费', '负债', null, '贷'],
    ['2231', '应付利息', '负债', null, '贷'],
    ['2232', '应付利润', '负债', null, '贷'],
    ['2241', '其他应付款', '负债', null, '贷'],
    ['2401', '递延收益', '负债', null, '贷'],
    ['2501', '长期借款', '负债', null, '贷'],
    ['2701', '长期应付款', '负债', null, '贷'],
    ['3001', '实收资本', '所有者权益', null, '贷'],
    ['3002', '资本公积', '所有者权益', null, '贷'],
    ['3101', '盈余公积', '所有者权益', null, '贷'],
    ['3103', '本年利润', '所有者权益', null, '贷'],
    ['3104', '利润分配', '所有者权益', null, '贷'],
    ['4001', '生产成本', '成本', null, '借'],
    ['4101', '制造费用', '成本', null, '借'],
    ['4301', '研发支出', '成本', null, '借'],
    ['4401', '工程施工', '成本', null, '借'],
    ['4403', '机械作业', '成本', null, '借'],
    ['5001', '主营业务收入', '损益', null, '贷'],
    ['5051', '其他业务收入', '损益', null, '贷'],
    ['5111', '投资收益', '损益', null, '贷'],
    ['5301', '营业外收入', '损益', null, '贷'],
    ['5401', '主营业务成本', '损益', null, '借'],
    ['5402', '其他业务成本', '损益', null, '借'],
    ['5403', '营业税金及附加', '损益', null, '借'],
    ['5601', '销售费用', '损益', null, '借'],
    ['5602', '管理费用', '损益', null, '借'],
    ['5603', '财务费用', '损益', null, '借'],
    ['5711', '营业外支出', '损益', null, '借'],
    ['5801', '所得税费用', '损益', null, '借'],
];

// 插入会计科目
$stmt = $db->prepare("INSERT OR IGNORE INTO accounts (code, name, category, parent_code, direction) VALUES (?, ?, ?, ?, ?)");
foreach ($accounts as $a) { $stmt->execute($a); }

echo "数据库结构初始化完成，默认用户admin/admin，会计科目初始化完成";
?>