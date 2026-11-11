/**
 * transform_sql.mjs — 生成 MKSHOP（mks_ 前缀）建库 SQL
 *
 * 输入（只读，来自逆向分析交付包）：
 *   ../../_analysis/appendix/F-重写建表SQL.sql   （106 表 DDL，xinmeng_ 前缀）
 *   ../../_analysis/appendix/G-字典与种子数据.sql （834 行种子）
 *
 * 替换规则（kss → mks 品牌化）：
 *   1. xinmeng_kss_  → mks_          （表名内嵌 kss，如 xinmeng_kss_pay_list → mks_pay_list）
 *   2. xinmeng_      → mks_          （通用表前缀）
 *   3. kss           → mks           （其余标识符/值，大小写不敏感：KSS/Kss/kss）
 *   4. KASUSHOU / 卡速售 → MKSHOP    （品牌名）
 *   5. kasushou.com  → mksup.top     （站点域名）
 *
 * 输出：
 *   ../deploy/sql/mks_schema.sql
 *   ../deploy/sql/mks_seed.sql
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const src = join(here, '..', '..', '_analysis', 'appendix');
const out = join(here, '..', 'deploy', 'sql');

const rules = [
  [/xinmeng_kss_/gi, 'mks_'],
  [/xinmeng_/gi, 'mks_'],
  [/kssshop/gi, 'mkshop'],
  [/\bkss\b/gi, 'mks'],
  [/kss/gi, 'mks'],
  [/KASUSHOU/g, 'MKSHOP'],
  [/卡速售/g, 'MKSHOP'],
  [/kasushou\.com/gi, 'mksup.top'],
];

const stats = {};
function apply(text) {
  let t = text;
  for (const [re, to] of rules) {
    const key = re.source;
    const n = (t.match(new RegExp(re.source, re.flags.includes('g') ? re.flags : re.flags + 'g')) || []).length;
    stats[key] = (stats[key] || 0) + n;
    t = t.replace(re, to);
  }
  return t;
}

const header =
  `-- ============================================================================\n` +
  `-- MKSHOP · 建库脚本（由 tools/transform_sql.mjs 自动生成，勿手改）\n` +
  `-- 表前缀 mks_ · 字符集 utf8mb4 · 金额 decimal(16,4)/成本(16,5) · 时间 int\n` +
  `-- 源：逆向分析交付包 appendix/F（DDL 106 表）与 appendix/G（种子 834 行）\n` +
  `-- 生成时间：${new Date().toISOString()}\n` +
  `-- ============================================================================\n\n`;

for (const [inFile, outFile] of [
  ['F-重写建表SQL.sql', 'mks_schema.sql'],
  ['G-字典与种子数据.sql', 'mks_seed.sql'],
]) {
  const text = readFileSync(join(src, inFile), 'utf8');
  const converted = header + apply(text);
  writeFileSync(join(out, outFile), converted, 'utf8');
  console.log(`OK ${outFile}: ${(converted.length / 1024).toFixed(1)} KB`);
}

console.log('替换统计:', JSON.stringify(stats, null, 2));

// 自检：产物中不应再有任何 kss/xinmeng 残留（大小写不敏感）
for (const f of ['mks_schema.sql', 'mks_seed.sql']) {
  const c = readFileSync(join(out, f), 'utf8');
  const bad = c.match(/kss|xinmeng|卡速售|KASUSHOU/gi);
  console.log(`${f} 残留检查: ${bad ? 'FAIL(' + bad.length + ')' : 'PASS'}`);
  if (bad) {
    const i = c.search(/kss|xinmeng|卡速售|KASUSHOU/i);
    console.log('  首个残留上下文:', c.slice(Math.max(0, i - 80), i + 80).replace(/\n/g, ' '));
  }
}
