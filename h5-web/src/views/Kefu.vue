<template>
  <div class="page plain kefu-page">
    <NavBar title="在线客服" />
    <div class="status-bar">
      <span class="dot" :class="{ off: closed }"></span>
      {{ closed ? '会话已结束' : '客服在线，通常几分钟内回复' }}
      <span v-if="closed" class="reopen" @click="restart">重新发起</span>
    </div>

    <div ref="boxRef" class="chat">
      <div v-for="m in messages" :key="m.id" class="msg" :class="msgClass(m)">
        <div v-if="m.from_type === 3" class="sys">{{ m.content }}</div>
        <template v-else>
          <div class="avatar">{{ m.from_type === 2 ? '客' : '我' }}</div>
          <div class="bubble">{{ m.content }}</div>
        </template>
      </div>
      <div v-if="!loading && !messages.length" class="empty"><div class="big">💬</div>暂无消息</div>
    </div>

    <div class="composer">
      <input v-model="content" class="input" placeholder="请输入问题" maxlength="500"
        :disabled="closed" @keyup.enter="send" />
      <button class="btn send" :disabled="closed || !content.trim() || sending" @click="send">发送</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import NavBar from '@/components/NavBar.vue';
import { kefuStart, kefuMessages, kefuSend } from '@/api';
import { toast } from '@/utils/request';

const boxRef = ref(null);
const messages = ref([]);
const content = ref('');
const sessionId = ref(0);
const lastId = ref(0);
const closed = ref(false);
const loading = ref(true);
const sending = ref(false);
let timer = null;

const msgClass = (m) => (m.from_type === 3 ? 'sys-row' : m.from_type === 1 ? 'mine' : 'other');

const scrollBottom = async () => {
  await nextTick();
  const el = boxRef.value;
  if (el) el.scrollTop = el.scrollHeight;
};

const fetchMessages = async () => {
  if (!sessionId.value) return;
  try {
    const res = await kefuMessages({ session_id: sessionId.value, last_id: lastId.value });
    const rows = res.data?.list || [];
    if (rows.length) {
      messages.value.push(...rows);
      lastId.value = rows[rows.length - 1].id;
      scrollBottom();
    }
    closed.value = (res.data?.status ?? 1) !== 1;
  } catch (e) { /* 轮询失败静默 */ }
};

const start = async () => {
  loading.value = true;
  try {
    const res = await kefuStart('h5');
    sessionId.value = res.data?.session_id || 0;
    lastId.value = 0;
    messages.value = [];
    closed.value = (res.data?.status ?? 1) !== 1;
    await fetchMessages();
    scrollBottom();
  } catch (e) { /* request 层已提示 */ } finally {
    loading.value = false;
  }
};

const restart = () => start();

const send = async () => {
  const text = content.value.trim();
  if (!text || closed.value || sending.value) return;
  sending.value = true;
  try {
    await kefuSend({ session_id: sessionId.value, content: text });
    content.value = '';
    await fetchMessages();
    scrollBottom();
  } catch (e) {
    toast(e.message || '发送失败');
  } finally {
    sending.value = false;
  }
};

onMounted(async () => {
  await start();
  timer = setInterval(fetchMessages, 3000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<style scoped>
.kefu-page { display: flex; flex-direction: column; height: 100vh; padding-bottom: 0; }
.status-bar { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #909399; padding: 8px 14px; background: #fff; border-bottom: 1px solid #f2f3f5; }
.dot { width: 7px; height: 7px; border-radius: 50%; background: #16a34a; }
.dot.off { background: #c0c4cc; }
.reopen { margin-left: auto; color: #1d6fe8; font-size: 13px; }
.chat { flex: 1; overflow-y: auto; padding: 12px; background: #f4f5f7; }
.msg { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 12px; }
.msg.mine { flex-direction: row-reverse; }
.msg.sys-row { justify-content: center; }
.sys { background: rgba(0,0,0,.05); color: #909399; font-size: 11px; padding: 4px 10px; border-radius: 10px; max-width: 86%; }
.avatar { width: 30px; height: 30px; border-radius: 50%; background: #1d6fe8; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
.msg.other .avatar { background: #16a34a; }
.bubble { max-width: 74%; background: #fff; border-radius: 10px; padding: 9px 12px; font-size: 14px; line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
.msg.mine .bubble { background: #1d6fe8; color: #fff; }
.composer { display: flex; gap: 8px; padding: 8px 12px calc(8px + env(safe-area-inset-bottom)); background: #fff; border-top: 1px solid #eee; }
.composer .input { flex: 1; }
.btn.send { height: 42px; border-radius: 8px; padding: 0 20px; font-size: 14px; }
</style>
