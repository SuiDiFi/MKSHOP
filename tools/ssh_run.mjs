/**
 * ssh_run.mjs — 在 MKSHOP 服务器上执行命令（密码读自服务器信息文件，不入日志不入仓库）
 *
 * 用法: node tools/ssh_run.mjs "<远程命令>" [超时秒]
 * 服务器信息: H:\文档\MKSHOP服务器.txt（IP 与 root 密码）
 */
import { readFileSync } from 'node:fs';
import { Client } from 'ssh2';

const infoFile = 'H:\\文档\\MKSHOP服务器.txt';
const info = readFileSync(infoFile, 'utf8');
const host = (info.match(/(\d+\.\d+\.\d+\.\d+)/) || [])[1];
const pass = (info.match(/root密码[：:]\s*(\S+)/) || [])[1];
if (!host || !pass) { console.error('FAIL: 无法从服务器信息文件解析 IP/密码'); process.exit(1); }

const cmd = process.argv[2];
const timeoutSec = Number(process.argv[3] || 600);

const conn = new Client();
conn.on('ready', () => {
  conn.exec(cmd, (err, stream) => {
    if (err) { console.error('EXEC FAIL:', err.message); conn.end(); process.exit(1); }
    let out = '';
    const timer = setTimeout(() => { console.error('TIMEOUT'); stream.close(); conn.end(); process.exit(124); }, timeoutSec * 1000);
    stream.on('close', (code) => { clearTimeout(timer); conn.end(); process.stdout.write(out); console.log(`\n[exit ${code}]`); process.exit(code ?? 0); })
      .on('data', (d) => { out += d.toString(); })
      .stderr.on('data', (d) => { out += d.toString(); });
  });
}).on('error', (e) => { console.error('CONNECT FAIL:', e.message); process.exit(1); })
  .connect({ host, port: 22, username: 'root', password: pass, readyTimeout: 20000 });
