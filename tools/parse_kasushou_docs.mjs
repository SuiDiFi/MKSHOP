/**
 * parse_kasushou_docs.mjs — 抓取的 KASUSHOU 2.0 API 文档 HTML → Markdown
 * 输入：C:\Users\along\AppData\Local\Temp\kasushou_api\*.html
 * 输出：docs/kasushou-v2api/*.md（协议规格参考：M3 上游适配器 + 自有 dockapi 兼容目标）
 */
import { readFileSync, writeFileSync, mkdirSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const srcDir = 'C:\\Users\\along\\AppData\\Local\\Temp\\kasushou_api';
const outDir = join(here, '..', 'docs', 'kasushou-v2api');
mkdirSync(outDir, { recursive: true });

// 提取正文区（editormd 容器）并做 HTML→Markdown 粗转换
const SIDEBAR_ENTRIES = ['平台简介','开发指南','签名规范','全局状态码','基础数据','用户余额查询','商品接口','商品分类列表','商品列表接口','商品详情接口','商品调价记录','商品变更通知','商品下单模板','订单接口','订单提交接口','订单详情接口','订单异步回调','订单撤单接口','售后申请接口','售后处理回调','订单列表接口','订单撤单回调','后台接口','获取订单接口','处理订单接口','用户加款接口','售后列表接口','售后详情接口','售后处理接口','供货接口','商家订单列表','商家订单处理','商家售后列表','商家售后详情','商家售后处理'];

function htmlToMd(html) {
  // 定位正文容器
  let m = html.match(/<div[^>]*class="[^"]*editormd-preview[^"]*"[^>]*>([\s\S]*?)<div[^>]*class="[^"]*footer/i)
       || html.match(/<div[^>]*class="[^"]*content[^"]*"[^>]*>([\s\S]*)$/i);
  let body = m ? m[1] : html;
  // 去掉 script/style
  body = body.replace(/<script[\s\S]*?<\/script>/gi, '').replace(/<style[\s\S]*?<\/style>/gi, '');
  const t = body
    .replace(/<table[^>]*>/gi, '\n').replace(/<\/table>/gi, '\n')
    .replace(/<tr[^>]*>/gi, '| ').replace(/<\/tr>/gi, ' |\n')
    .replace(/<t[hd][^>]*>/gi, '').replace(/<\/t[hd]>/gi, ' | ')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<h1[^>]*>/gi, '\n# ').replace(/<h2[^>]*>/gi, '\n## ').replace(/<h3[^>]*>/gi, '\n### ')
    .replace(/<h4[^>]*>/gi, '\n#### ').replace(/<\/h[1-6]>/gi, '\n')
    .replace(/<li[^>]*>/gi, '\n- ').replace(/<\/(p|div|pre)>/gi, '\n')
    .replace(/<pre[^>]*>/gi, '\n```\n').replace(/<\/pre>/gi, '\n```\n')
    .replace(/<code[^>]*>/gi, '`').replace(/<\/code>/gi, '`')
    .replace(/<text[^>]*>/gi, '**').replace(/<\/text>/gi, '**')
    .replace(/<[^>]+>/g, '');
  // 反转义 + 压缩多余空行
  const text = t
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&nbsp;/g, ' ')
    .replace(/&#x[0-9a-f]+;/gi, '').replace(/&#\d+;/g, '')
    .replace(/<text[^>]*>/gi, '**').replace(/<\/text>/gi, '**').replace(/<\/?br>/gi, '')
    .replace(/[ \t]+/g, ' ')                  // 先压缩空格
    .replace(/- +\n(?=[^\n])/g, '- ')         // 再合并侧边栏 "- \n条目"
    .replace(/\n{3,}/g, '\n\n')
    .trim();
  // 去掉侧边栏导航：从最后一个侧边栏条目行之后截取正文
  const lines = text.split('\n');
  let lastSidebar = -1;
  for (let i = 0; i < lines.length; i++) {
    const s = lines[i].trim();
    if (/^[-|]?\s*$/.test(s)) continue;
    if (SIDEBAR_ENTRIES.some((e) => s === '- ' + e || s === '| - ' + e || s === '- ' + e + ' ')) lastSidebar = i;
  }
  const content = (lastSidebar >= 0 ? lines.slice(lastSidebar + 1) : lines).join('\n').trim();
  return content;
}

const files = readdirSync(srcDir).filter((f) => f.endsWith('.html'));
let index = ['# KASUSHOU 2.0 API 协议文档（官方抓取存档）', '', '> 抓取时间：2026-11-11 · 来源 doc.kasushou.com/api/v2api · 用途：M3 上游对接适配器协议规格 + 自有 /dockapi 兼容目标', ''];
const nameMap = {
  33: '00-平台简介', 34: '01-开发指南', 38: '02-基础数据', 39: '03-签名规范', 40: '04-全局状态码',
  41: '10-用户余额查询', 115: '11-用户加款',
  42: '20-商品接口', 43: '21-商品分类列表', 44: '22-商品列表', 45: '23-商品详情', 46: '24-商品调价记录',
  143: '25-商品变更通知', 151: '26-商品下单模板',
  47: '30-订单接口', 48: '31-订单提交', 49: '32-订单详情', 50: '33-订单异步回调',
  161: '34-订单列表', 145: '35-订单撤单', 162: '36-订单撤单回调',
  146: '40-售后申请', 147: '41-售后处理回调', 148: '42-售后列表', 149: '43-售后详情', 150: '44-售后处理',
  59: '50-后台接口', 60: '51-获取订单', 61: '52-处理订单',
  134: '60-供货接口', 135: '61-商家订单列表', 137: '62-商家订单处理',
  138: '63-商家售后列表', 139: '64-商家售后详情', 140: '65-商家售后处理',
};

for (const f of files) {
  const id = parseInt(f, 10);
  const name = nameMap[id] || `${id}-未命名`;
  const html = readFileSync(join(srcDir, f), 'utf8');
  const md = htmlToMd(html);
  writeFileSync(join(outDir, `${name}.md`), `# ${name.replace(/^\d+-/, '')}\n\n> 来源：doc.kasushou.com/api/v2api/${id}\n\n${md}\n`, 'utf8');
  index.push(`- [${name}](kasushou-v2api/${encodeURIComponent(name)}.md) — 来源 /api/v2api/${id}`);
}
writeFileSync(join(here, '..', 'docs', 'KASUSHOU-V2API-INDEX.md'), index.join('\n') + '\n', 'utf8');
console.log(`OK: ${files.length} 页已转换为 Markdown → docs/kasushou-v2api/`);
