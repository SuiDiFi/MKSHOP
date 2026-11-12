<template>
  <div>
    <Topbar />
    <div class="wrap page-body" style="max-width: 780px">
      <el-card shadow="never" style="margin-top: 14px" body-style="padding:0">
        <template #header>
          <div style="display:flex; justify-content:space-between; align-items:center">
            <div>
              <b>在线客服</b>
              <el-tag size="small" :type="closed ? 'info' : 'success'" style="margin-left:8px">
                {{ closed ? '会话已结束' : '客服在线' }}
              </el-tag>
            </div>
            <div>
              <el-button v-if="closed" size="small" type="primary" @click="restart">重新发起</el-button>
              <el-button size="small" link type="primary" @click="$router.push('/help')">帮助中心</el-button>
            </div>
          </div>
        </template>

        <div ref="boxRef" class="chat-box" v-loading="loading">
          <div v-for="m in messages" :key="m.id" class="msg" :class="msgClass(m)">
            <div v-if="m.from_type === 3" class="sys">{{ m.content }}</div>
            <template v-else>
              <div class="avatar">{{ m.from_type === 2 ? '客' : '我' }}</div>
              <div class="bubble">{{ m.content }}</div>
            </template>
          </div>
          <el-empty v-if="!loading && !messages.length" description="暂无消息" :image-size="72" />
        </div>

        <div class="chat-input">
          <el-input v-model="content" type="textarea" :rows="3" maxlength="500" show-word-limit
            resize="none" placeholder="请输入您的问题，Ctrl+Enter 发送"
            :disabled="closed" @keydown.ctrl.enter.prevent="send" />
          <div class="input-bar">
            <span class="tip">客服消息会通过轮询自动刷新（约 3 秒）</span>
            <el-button type="primary" :loading="sending" :disabled="closed || !content.trim()" @click="send">发送</el-button>
          </div>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import Topbar from '@/components/Topbar.vue';
import { kefuStart, kefuMessages, kefuSend } from '@/api';

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
    const res = await kefuStart('pc');
    sessionId.value = res.data?.session_id || 0;
    lastId.value = 0;
    messages.value = [];
    closed.value = (res.data?.status ?? 1) !== 1;
    await fetchMessages();
    scrollBottom();
  } finally {
    loading.value = false;
  }
};

const restart = () => start();

const send = async () => {
  const text = content.value.trim();
  if (!text || closed.value) return;
  sending.value = true;
  try {
    await kefuSend({ session_id: sessionId.value, content: text });
    content.value = '';
    await fetchMessages();
    scrollBottom();
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
.chat-box { height: 460px; overflow-y: auto; padding: 16px; background: #fafbfc; }
.msg { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 14px; }
.msg.mine { flex-direction: row-reverse; }
.msg.sys-row { justify-content: center; }
.sys { background: #f0f2f5; color: #909399; font-size: 12px; padding: 5px 12px; border-radius: 12px; max-width: 80%; }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
.msg.other .avatar { background: #67c23a; }
.bubble { max-width: 70%; background: #fff; border: 1px solid #ebeef5; border-radius: 8px; padding: 9px 12px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
.msg.mine .bubble { background: #409eff; border-color: #409eff; color: #fff; }
.chat-input { padding: 12px 16px 16px; border-top: 1px solid #f2f3f5; }
.input-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.tip { color: #c0c4cc; font-size: 12px; }
</style>
