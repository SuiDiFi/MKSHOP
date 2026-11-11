/**
 * apifox_import.mjs — 将 docs/apifox/openapi.json 导入 Apifox 项目
 *
 * API: POST https://api.apifox.com/v1/projects/{projectId}/import-openapi
 * 凭据从 H:\文档\MKSHOP服务器.txt 解析（apifox API 访问令牌 / 项目ID），不入仓库不入日志。
 * 用法: node tools/apifox_import.mjs [openapi文件路径]
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const info = readFileSync('H:\\文档\\MKSHOP服务器.txt', 'utf8');
const token = (info.match(/apifox\s*API\s*访问令牌[：:]\s*(\S+)/) || [])[1];
const projectId = (info.match(/项目ID[：:]\s*(\d+)/) || [])[1];
if (!token || !projectId) { console.error('FAIL: 无法解析 Apifox 令牌/项目ID'); process.exit(1); }

const specPath = process.argv[2] || join(here, '..', 'docs', 'apifox', 'openapi.json');
const spec = JSON.parse(readFileSync(specPath, 'utf8'));

const body = {
  input: JSON.stringify(spec), // 字符串方式导入
  options: {
    endpointOverwriteBehavior: 'OVERWRITE_EXISTING',
    schemaOverwriteBehavior: 'OVERWRITE_EXISTING',
    updateFolderOfChangedEndpoint: true,
    deleteUnmatchedResources: true, // 与数据源保持同步
  },
};

const res = await fetch(`https://api.apifox.com/v1/projects/${projectId}/import-openapi?locale=zh-CN`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${token}`,
    'X-Apifox-Api-Version': '2024-03-28',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(body),
});
const text = await res.text();
console.log(`HTTP ${res.status}`);
console.log(text.slice(0, 2000));
if (!res.ok) process.exit(1);
console.log(`\nOK: 已导入 Apifox 项目 ${projectId}`);
