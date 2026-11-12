function statusText(s) {
  return { '-1': '已取消', 0: '待支付', 1: '处理中', 2: '处理中', 3: '已完成', 4: '已取消', 5: '已退款' }[s] || '未知';
}

function formatTime(t) {
  if (!t) return '—';
  if (typeof t === 'string') return t;
  const d = new Date(t * 1000);
  const p = (n) => (n < 10 ? '0' + n : '' + n);
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function copyText(text) {
  wx.setClipboardData({ data: String(text || '') });
}

module.exports = { statusText, formatTime, copyText };
