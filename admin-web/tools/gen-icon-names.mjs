/**
 * 生成合法 RemixIcon 名清单 → src/utils/iconNames.js
 * ----------------------------------------------------------
 * 背景：菜单 icon 字段来自数据库，存在历史非法值（brush / money / shop / coin），
 *      渲染结果是 0×0 完全隐形；而"运行时量宽度兜底"会把隐藏 popper 里的图标
 *      误判为非法（上轮验收就出现 6 个假阳性）。故改为构建期取权威名单。
 * 用法：node tools/gen-icon-names.mjs   （remixicon 版本升级后重跑一次）
 * 输入：node_modules/remixicon/fonts/remixicon.glyph.json（4.6MB，不入库不入包）
 * 输出：src/utils/iconNames.js（约 3200 个名字，gzip 后 ~6KB）
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const src = join(root, 'node_modules', 'remixicon', 'fonts', 'remixicon.glyph.json');
const out = join(root, 'src', 'utils', 'iconNames.js');

const glyphs = JSON.parse(readFileSync(src, 'utf8'));
const names = Object.keys(glyphs).sort();

const body =
  '/* 自动生成，勿手工修改：node tools/gen-icon-names.mjs */\n' +
  `/* 来源 remixicon@${JSON.parse(readFileSync(join(root, 'node_modules', 'remixicon', 'package.json'), 'utf8')).version}，共 ${names.length} 个图标 */\n` +
  'export const ICON_NAMES = new Set([\n' +
  names.map((n) => `  '${n}',`).join('\n') +
  '\n]);\n\n' +
  '/** 判断图标名是否合法（自动忽略 ri- 前缀） */\n' +
  'export function hasIcon(name) {\n' +
  "  return ICON_NAMES.has(String(name || '').replace(/^ri-/, ''));\n" +
  '}\n';

writeFileSync(out, body, 'utf8');
console.log(`已生成 ${out}：${names.length} 个图标名，${(body.length / 1024).toFixed(1)} KB`);
