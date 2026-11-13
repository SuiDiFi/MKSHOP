#!/usr/bin/env node
/**
 * 小程序配置注入（构建 / 提审前执行）
 *
 * 用法（在 miniprogram/ 目录下）：
 *   Windows PowerShell:
 *     $env:MP_APPID="wx1234567890"; $env:MP_BASE_URL="https://mksup.top"; node scripts/inject-config.js
 *   macOS / Linux:
 *     MP_APPID=wx1234567890 MP_BASE_URL=https://mksup.top node scripts/inject-config.js
 *
 * 写入：config.build.js（appid + baseUrl）与 project.config.json（appid）。
 * 仅注入公开值，AppSecret、支付密钥、证书路径一律留在后台配置，绝不下发小程序端。
 */
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const appid = String(process.env.MP_APPID || '').trim();
const baseUrl = String(process.env.MP_BASE_URL || '').trim().replace(/\/+$/, '');

if (!appid || !baseUrl) {
  console.error('用法：MP_APPID=wx... MP_BASE_URL=https://... node scripts/inject-config.js');
  process.exit(1);
}
if (!/^https:\/\//i.test(baseUrl)) {
  console.warn('警告：微信正式版小程序要求 HTTPS 域名，当前注入的是 ' + baseUrl);
}

const buildFile = path.join(root, 'config.build.js');
fs.writeFileSync(
  buildFile,
  '// 本文件由 scripts/inject-config.js 生成（构建/提审前执行），请勿手工修改。\n'
  + 'module.exports = ' + JSON.stringify({ appid, baseUrl }, null, 2) + ';\n',
  'utf8'
);

const projectFile = path.join(root, 'project.config.json');
const project = JSON.parse(fs.readFileSync(projectFile, 'utf8'));
project.appid = appid;
fs.writeFileSync(projectFile, JSON.stringify(project, null, 2) + '\n', 'utf8');

console.log('已注入小程序配置：');
console.log('  AppID  :', appid);
console.log('  请求域名:', baseUrl);
console.log('提示：AppSecret / 支付密钥 / 证书路径请在后台「系统设置 → 小程序配置」填写。');
