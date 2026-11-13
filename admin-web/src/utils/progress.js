/**
 * 顶部进度条（自实现，替代 nprogress —— 不新增依赖）
 * 行为对齐对方：路由跳转时 0 → 90% 快速推进，完成后补到 100% 再淡出
 */
let bar = null;
let timer = null;
let value = 0;
let finished = false;

function ensureEl() {
  if (bar) return bar;
  bar = document.createElement('div');
  bar.id = 'mks-progress';
  document.body.appendChild(bar);
  return bar;
}

function render() {
  if (!bar) return;
  bar.style.width = `${value}%`;
}

function tick() {
  // 逼近 90% 后减速，制造"网络请求中"的观感
  const target = finished ? 100 : Math.min(90, value + (90 - value) * 0.2 + 1);
  value = target;
  render();
  if (finished && value >= 100) {
    clearInterval(timer);
    timer = null;
    setTimeout(() => {
      bar.classList.remove('is-active');
      setTimeout(() => {
        value = 0;
        render();
      }, 300);
    }, 200);
    return;
  }
  if (!finished && value >= 89.5) {
    clearInterval(timer);
    timer = null;
  }
}

export function start() {
  if (!ensureEl()) return;
  finished = false;
  value = Math.min(value, 30);
  bar.classList.add('is-active');
  render();
  if (!timer) timer = setInterval(tick, 200);
}

export function done() {
  finished = true;
  if (!timer) timer = setInterval(tick, 200);
  else tick();
}

export default { start, done };
