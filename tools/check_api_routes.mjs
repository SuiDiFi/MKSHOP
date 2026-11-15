/**
 * check_api_routes.mjs — 核对「前端 api 封装」与「后端路由注册」是否一一对应
 *
 * 用法: node tools/check_api_routes.mjs
 * 退出码: 0 = 无缺口; 1 = 存在「前端在调、后端没注册」的路由（线上必 404「接口不存在」）
 *
 * 为什么要这个脚本：config/route.php 开了 url_route_must，路由没注册就是 404；
 * 而 CurdTrait 早就提供了 modify 之类的方法，缺的只是那一行注册 —— 靠人肉记忆必然漏
 * （goods_tpl/modify、price_ready/modify 就是这么在线上 404 的）。
 */
import { readFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

/** 参数段归一：ThinkPHP 的 :id 与前端的 ${id} 都当成 {x} */
function normalize(p) {
  return ('/' + p).replace(/\$\{[^}]*\}/g, '{x}').replace(/:[A-Za-z_][A-Za-z0-9_]*/g, '{x}')
    .replace(/\/{2,}/g, '/').replace(/\/+$/, '');
}

/** 扫描 Route::group 的嵌套前缀：先用括号配对算出每个 group 的作用域区间，再按位置归属 */
function collectRoutes(src, file, routes) {
  const opens = [];          // '{' 的位置
  const closeOf = new Map(); // '{' 位置 -> 对应 '}' 位置
  const stack = [];
  let inStr = null;
  for (let i = 0; i < src.length; i++) {
    const ch = src[i], prev = src[i - 1];
    if (inStr) { if (ch === inStr && prev !== '\\') inStr = null; continue; }
    if (ch === "'" || ch === '"') { inStr = ch; continue; }
    if (ch === '/' && src[i + 1] === '/') { i = src.indexOf('\n', i); if (i < 0) break; continue; }
    if (ch === '{') { stack.push(i); opens.push(i); }
    else if (ch === '}') { const o = stack.pop(); if (o !== undefined) closeOf.set(o, i); }
  }

  const groups = [];
  for (const m of src.matchAll(/Route::group\(\s*(?:'([^']*)'\s*,\s*)?function/g)) {
    const open = opens.find((o) => o > m.index && !groups.some((g) => g.open === o));
    if (open === undefined) continue;
    groups.push({ seg: m[1] || '', open, close: closeOf.get(open) ?? src.length });
  }

  const lineAt = (idx) => src.slice(0, idx).split('\n').length;
  for (const m of src.matchAll(/Route::(get|post|put|delete|rule|any|miss)\(\s*'([^']*)'/gi)) {
    const inside = groups.filter((g) => g.open < m.index && m.index < g.close).map((g) => g.seg).filter(Boolean);
    const full = normalize([...inside, m[2]].join('/'));
    const verbs = /rule|any/i.test(m[1]) ? ['get', 'post'] : [m[1].toUpperCase()];
    for (const v of verbs) {
      const key = `${v} ${full}`;
      if (!routes.has(key)) routes.set(key, `${file}:${lineAt(m.index)}`);
    }
  }
}

const routes = new Map();
for (const f of readdirSync(join(root, 'route')).filter((n) => n.endsWith('.php'))) {
  collectRoutes(readFileSync(join(root, 'route', f), 'utf8'), `route/${f}`, routes);
}

// 前端声明的调用
const apiSrc = readFileSync(join(root, 'admin-web/src/api/index.js'), 'utf8');
const lineAt = (idx) => apiSrc.slice(0, idx).split('\n').length;
const calls = [];
for (const m of apiSrc.matchAll(/mksRequest\(\s*(`[^`]+`|'[^']+')\s*(?:,\s*'([A-Za-z]+)')?/g)) {
  calls.push({ verb: (m[2] || 'GET').toUpperCase(), norm: normalize(m[1].slice(1, -1)), line: lineAt(m.index) });
}
// CSV 导出：路径由调用方传入，仍走 s= 路由
for (const m of apiSrc.matchAll(/exportCsv\(\s*'([^']+)'/g)) {
  calls.push({ verb: 'GET', norm: normalize(m[1]), line: lineAt(m.index) });
}

const missing = calls.filter((x) => !routes.has(`${x.verb} ${x.norm}`));
const apiSet = new Set(calls.map((x) => `${x.verb} ${x.norm}`));
const unused = [...routes.keys()].filter((k) => k.includes('/admin/') && !apiSet.has(k));

console.log(`后端路由 ${routes.size} 条 · 前端调用 ${calls.length} 处`);
if (missing.length) {
  console.log(`\n[缺注册] 前端在调、后端没路由（线上 404）：${missing.length} 条`);
  for (const x of missing) console.log(`  ${x.verb} ${x.norm}   ← admin-web/src/api/index.js:${x.line}`);
} else {
  console.log('\n[缺注册] 无');
}
console.log(`\n[前端未调用] ${unused.length} 条（多为登录白名单、外部回调、定时任务，不一定是问题）`);
for (const u of unused.slice(0, 60)) console.log(`  ${u}   → ${routes.get(u)}`);

// 3) 后台路由 ↔ 权限点登记（auth_type=2）：超管看不出缺登记，普通角色就是 403
// SQL 里权限点有四种写法（27 列种子、8 列简式、UNION 派生表、白名单表），逐条按语句归类后再比对。
const sqlDir = join(root, 'deploy/sql');
const registered = new Set();
const whitelisted = new Set();

function harvest(sql, file) {
  for (const raw of sql.split(/;\s*\n/)) {
    const st = raw.trim();
    if (!st) continue;
    const isPerm = /mks_admin_router/i.test(st) && /auth_type/i.test(st);
    const isWhite = /mks_api_permission/i.test(st);
    if (!isPerm && !isWhite) continue;
    if (isWhite) {
      for (const m of st.matchAll(/\(\s*'([^']+)'\s*,\s*'(?:GET|POST|GET\/POST)'/g)) whitelisted.add(m[1].toLowerCase());
      continue;
    }
    // 权限点在这些 SQL 里有 4 种写法，共同点是「api_url 紧跟 methods」或「'x' AS api_url」
    const ok = (s) => /^[a-z][a-z0-9_]*(?:\/[a-z0-9_]*)*$/i.test(s);
    for (const m of st.matchAll(/,\s*'([A-Za-z0-9_\/]+)'\s*,\s*'(?:GET|POST|GET\/POST)'/g)) if (ok(m[1])) registered.add(m[1].toLowerCase());
    for (const m of st.matchAll(/'([A-Za-z0-9_\/]+)'\s+(?:AS\s+)?api(?:_url)?\b/g)) if (ok(m[1])) registered.add(m[1].toLowerCase());
  }
}
for (const f of readdirSync(sqlDir).filter((n) => n.endsWith('.sql'))) harvest(readFileSync(join(sqlDir, f), 'utf8'), f);
const upDir = join(sqlDir, 'upgrade');
for (const f of readdirSync(upDir).filter((n) => n.endsWith('.sql'))) harvest(readFileSync(join(upDir, f), 'utf8'), f);

// 公开组接口（route/admin.php 里那组不带 AdminAuth 的），本就不需要权限点
const KNOWN_PUBLIC = new Set(['login/login']);
const noPerm = [...routes.entries()]
  .filter(([, at]) => at.startsWith('route/admin.php'))
  .map(([k, at]) => ({ api: k.replace(/^\w+ \/admin\//, '').replace(/\/\{x\}$/g, '').toLowerCase(), at }))
  .filter((x) => !registered.has(x.api) && !whitelisted.has(x.api) && !KNOWN_PUBLIC.has(x.api));

console.log(`\n[权限点核对] SQL 里已登记 ${registered.size} 个 auth_type=2 接口，白名单豁免 ${whitelisted.size} 个`);
console.log(`[缺权限点登记] 路由已注册、但既没登记也不在白名单：${noPerm.length} 个接口`);
console.log('  注意：SQL 文件不等于库里实际状态（历史上有手工执行未回写的迁移），下结论前用 mks:upgrade --status 或直接查库核对。');
for (const x of noPerm.slice(0, 60)) console.log(`  ${x.api}   ← ${x.at}`);

process.exit(missing.length ? 1 : 0);
