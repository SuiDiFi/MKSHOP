/**
 * ssh_put.mjs — 上传文件到 MKSHOP 服务器（SFTP）
 *
 * 用法: node tools/ssh_put.mjs "<本地路径1>[,<本地路径2>...]" "<远程目录>"
 * 远程目录不存在时自动创建。凭据读自 H:\文档\MKSHOP服务器.txt。
 */
import { readFileSync, statSync, readdirSync } from 'node:fs';
import { join, dirname, posix } from 'node:path';
import { Client } from 'ssh2';

const infoFile = 'H:\\文档\\MKSHOP服务器.txt';
const info = readFileSync(infoFile, 'utf8');
const host = (info.match(/(\d+\.\d+\.\d+\.\d+)/) || [])[1];
const pass = (info.match(/root密码[：:]\s*(\S+)/) || [])[1];

const localArg = process.argv[2] || '';
const remoteDir = process.argv[3] || '/opt/mkshop';
if (!localArg) { console.error('用法: node tools/ssh_put.mjs "<本地文件...>" "<远程目录>"'); process.exit(1); }

// 展开本地路径（支持目录递归）；文件保留完整相对路径，目录按 目录名/内部相对路径
function expand(p, base = '') {
  const st = statSync(p);
  const norm = (s) => s.replace(/\\/g, '/');
  if (st.isFile()) {
    const rel = base !== '' ? posix.join(norm(base), norm(p).split('/').pop()) : norm(p);
    return [{ local: p, remote: rel }];
  }
  const out = [];
  const dirName = norm(p).split('/').pop();
  for (const name of readdirSync(p)) out.push(...expand(join(p, name), base !== '' ? posix.join(base, dirName) : dirName));
  return out;
}

const files = localArg.split(',').map((s) => s.trim()).flatMap((p) => expand(p));
console.log(`待上传 ${files.length} 个文件 → ${host}:${remoteDir}`);

const conn = new Client();
conn.on('ready', () => {
  conn.sftp((err, sftp) => {
    if (err) { console.error('SFTP FAIL:', err.message); process.exit(1); }
    const dirs = new Set([remoteDir]);
    for (const f of files) dirs.add(posix.join(remoteDir, dirname(f.remote).replace(/\\/g, '/').replace(/^\.$/, '')));

    function mkdirp(dir, cb) {
      const parts = dir.split('/').filter(Boolean);
      let cur = '';
      (function next(i) {
        if (i >= parts.length) return cb();
        cur += '/' + parts[i];
        sftp.mkdir(cur, () => next(i + 1)); // 已存在时报错，忽略
      })(0);
    }

    const dirList = [...dirs];
    (function mkAll(i) {
      if (i >= dirList.length) return upload(0);
      mkdirp(dirList[i], () => mkAll(i + 1));
    })(0);

    function upload(i) {
      if (i >= files.length) { console.log(`\n全部上传完成 (${files.length})`); conn.end(); return; }
      const f = files[i];
      const remote = posix.join(remoteDir, f.remote.replace(/\\/g, '/'));
      sftp.fastPut(f.local, remote, (e) => {
        console.log(`${e ? 'FAIL ' : 'OK   '}${f.local} → ${remote}${e ? ' (' + e.message + ')' : ''}`);
        if (e) process.exitCode = 1;
        upload(i + 1);
      });
    }
  });
}).on('error', (e) => { console.error('CONNECT FAIL:', e.message); process.exit(1); })
  .connect({ host, port: 22, username: 'root', password: pass, readyTimeout: 20000 });
