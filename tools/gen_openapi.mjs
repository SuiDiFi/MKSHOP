/**
 * gen_openapi.mjs — 生成 MKSHOP API 目录（OpenAPI 3.0）供 Apifox 导入
 *
 * 输入：../../_analysis/appendix/R-全站路由清单.csv（1633 条路由）
 * 输出：docs/apifox/openapi.json
 *
 * 路径/方法/中文名以 CSV 为准；请求/响应体在实现各模块时按模块细化后重新生成导入。
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const csvPath = join(here, '..', '..', '_analysis', 'appendix', 'R-全站路由清单.csv');
const outDir = join(here, '..', 'docs', 'apifox');

// ---- 简易 CSV 解析（支持引号包裹与 "" 转义）----
function parseCsv(text) {
  const rows = [];
  let row = [], field = '', inQuote = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (inQuote) {
      if (c === '"') {
        if (text[i + 1] === '"') { field += '"'; i++; }
        else inQuote = false;
      } else field += c;
    } else if (c === '"') inQuote = true;
    else if (c === ',') { row.push(field); field = ''; }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++;
      row.push(field); field = '';
      if (row.some((f) => f !== '')) rows.push(row);
      row = [];
    } else field += c;
  }
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  return rows;
}

const TAG_MAP = {
  'admin.php': '管理后台',
  'front.php': 'PC前台',
  'h5.php': '移动端H5',
  'api.php': '开放API',
  'dockapi.php': '上游对接',
  'addonvip.php': '分站(vip)',
  'diy.php': 'DIY装修',
  'agiso.php': 'Agiso对接',
  'goofish.php': '闲鱼对接',
  'route.php': '系统',
};

const rows = parseCsv(readFileSync(csvPath, 'utf8').replace(/^\uFEFF/, ''));
const header = rows.shift();
const idxFile = 0, idxMethod = 1, idxPath = 2, idxCtrl = 3, idxName = 4;

const spec = {
  openapi: '3.0.3',
  info: {
    title: 'MKSHOP API',
    description:
      'MKSHOP 虚拟商品电商系统 API 目录。\n来源：逆向分析交付包 R-全站路由清单（接口对齐基准）。\n各模块实现时按实际请求/响应结构细化，并重新导入本文件。',
    version: '0.1.0',
  },
  servers: [
    { url: 'http://localhost:8080', description: '本地开发' },
    { url: 'https://mksup.top', description: '生产（域名待备案确认）' },
  ],
  tags: Object.entries(TAG_MAP).map(([file, name]) => ({ name, description: `路由文件 ${file}` })),
  paths: {},
  components: {
    securitySchemes: {
      AdminToken: { type: 'http', scheme: 'bearer', bearerFormat: 'JWT', description: '管理后台 JWT' },
      UserToken: { type: 'http', scheme: 'bearer', bearerFormat: 'JWT', description: '会员 Token' },
      ApiSign: {
        type: 'apiKey', in: 'header', name: 'X-MKS-Sign',
        description: '开放 API 签名（auth_key/sign_key，见 .env [MKS]）',
      },
    },
    schemas: {
      ApiResponse: {
        type: 'object',
        description: '统一响应结构',
        properties: {
          code: { type: 'integer', description: '0=成功，非0=业务错误码' },
          msg: { type: 'string' },
          data: { type: 'object', description: '业务数据（实现后细化）' },
          time: { type: 'integer', description: '服务端时间戳' },
        },
        required: ['code', 'msg'],
      },
    },
  },
};

let count = 0;
for (const r of rows) {
  if (r.length < 5) continue;
  const file = (r[idxFile] || '').trim();
  const method = (r[idxMethod] || 'get').trim().toLowerCase();
  const path = '/' + (r[idxPath] || '').trim().replace(/^\/+/, '');
  const ctrl = (r[idxCtrl] || '').trim();
  const name = (r[idxName] || '').trim();
  if (!spec.paths[path]) spec.paths[path] = {};
  // 同路径多文件场景：tag 取首个，方法冲突时加后缀
  const opId = ctrl.replace(/[^A-Za-z0-9]/g, '_') || path.replace(/[^A-Za-z0-9]/g, '_');
  spec.paths[path][method] = {
    tags: [TAG_MAP[file] || '其他'],
    summary: name || opId,
    description: `控制器: ${ctrl}\n路由文件: ${file}`,
    operationId: spec.paths[path][method] ? opId + '_' + method : opId,
    security: file === 'admin.php' || file === 'diy.php'
      ? [{ AdminToken: [] }]
      : file === 'api.php' || file === 'dockapi.php'
        ? [{ ApiSign: [] }]
        : [],
    responses: {
      200: {
        description: '统一响应',
        content: { 'application/json': { schema: { $ref: '#/components/schemas/ApiResponse' } } },
      },
    },
  };
  count++;
}

mkdirSync(outDir, { recursive: true });
writeFileSync(join(outDir, 'openapi.json'), JSON.stringify(spec, null, 2), 'utf8');
console.log(`OK docs/apifox/openapi.json — ${count} 个接口，${Object.keys(spec.paths).length} 个路径`);
