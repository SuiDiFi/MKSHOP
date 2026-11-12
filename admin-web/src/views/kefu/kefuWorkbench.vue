<template>
  <div class="kefu-wrap">
    <!-- 左侧会话列表 -->
    <aside class="kefu-side">
      <div class="side-head">
        <el-radio-group v-model="filterStatus" size="small" @change="loadSessions">
          <el-radio-button :value="1">进行中</el-radio-button>
          <el-radio-button :value="''">全部</el-radio-button>
          <el-radio-button :value="2">已关闭</el-radio-button>
        </el-radio-group>
        <el-input v-model="keyword" placeholder="搜索昵称/会员ID/消息" clearable size="small" style="margin-top:8px"
          @keyup.enter="loadSessions" @clear="loadSessions">
          <template #append><el-button :icon="Search" @click="loadSessions" /></template>
        </el-input>
      </div>
      <div class="session-list" v-loading="loadingSessions">
        <div v-for="s in sessions" :key="s.id" class="session" :class="{ active: current && current.id === s.id }"
          @click="openSession(s)">
          <div class="s-avatar">{{ (s.nickname || '客').slice(0, 1) }}</div>
          <div class="s-main">
            <div class="s-top">
              <span class="s-name">{{ s.nickname || ('会员#' + s.uid) }}</span>
              <span class="s-time">{{ timeText(s.last_time) }}</span>
            </div>
            <div class="s-bottom">
              <span class="s-msg">{{ s.last_msg || '暂无消息' }}</span>
              <span v-if="s.unread_admin > 0" class="s-badge">{{ s.unread_admin > 99 ? '99+' : s.unread_admin }}</span>
            </div>
          </div>
        </div>
        <el-empty v-if="!loadingSessions && !sessions.length" description="暂无会话" :image-size="60" />
      </div>
    </aside>

    <!-- 右侧聊天窗 -->
    <section class="kefu-main">
      <template v-if="current">
        <div class="chat-head">
          <div>
            <b>{{ current.nickname || ('会员#' + current.uid) }}</b>
            <span class="muted" style="margin-left:8px">ID {{ current.uid }} · {{ current.source }}</span>
          </div>
          <div>
            <el-tag :type="current.status === 1 ? 'success' : 'info'" size="small">
              {{ current.status === 1 ? '进行中' : '已关闭' }}
            </el-tag>
            <el-button v-if="current.status === 1" size="small" style="margin-left:8px" @click="doClose">结束会话</el-button>
          </div>
        </div>
        <div ref="msgBox" class="chat-body">
          <div v-for="m in messages" :key="m.id" class="msg-row" :class="{ mine: m.from_type === 2, sys: m.from_type === 3 }">
            <template v-if="m.from_type === 3">
              <div class="sys-tip">{{ m.content }}</div>
            </template>
            <template v-else>
              <div class="bubble">{{ m.content }}</div>
            </template>
          </div>
          <div v-if="!messages.length" class="empty-tip">暂无消息，等待会员咨询</div>
        </div>
        <div class="chat-input">
          <el-input v-model="replyText" type="textarea" :rows="3" maxlength="500" show-word-limit
            placeholder="输入回复内容，Ctrl+Enter 发送" :disabled="current.status !== 1"
            @keydown.ctrl.enter.prevent="doReply" />
          <div class="send-bar">
            <span class="muted">每 5 秒自动刷新</span>
            <el-button type="primary" :loading="sending" :disabled="current.status !== 1" @click="doReply">发送回复</el-button>
          </div>
        </div>
      </template>
      <div v-else class="chat-empty">
        <el-empty description="请选择左侧会话开始接待" />
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onUnmounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import { kefuIndex, kefuMessages, kefuReply, kefuClose } from '../../api';

const sessions = ref([]);
const current = ref(null);
const messages = ref([]);
const filterStatus = ref(1);
const keyword = ref('');
const loadingSessions = ref(false);
const sending = ref(false);
const replyText = ref('');
const msgBox = ref(null);
let timer = null;
let listTimer = null;

const timeText = (t) => {
  if (!t) return '';
  const d = new Date(t * 1000);
  const today = new Date();
  const sameDay = d.toDateString() === today.toDateString();
  return sameDay
    ? d.toLocaleTimeString('zh-CN', { hour12: false, hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString('zh-CN', { month: '2-digit', day: '2-digit' });
};

const scrollBottom = async () => {
  await nextTick();
  if (msgBox.value) msgBox.value.scrollTop = msgBox.value.scrollHeight;
};

const loadSessions = async () => {
  loadingSessions.value = true;
  try {
    const params = { page: 1, limit: 50 };
    if (filterStatus.value !== '') params.status = filterStatus.value;
    if (keyword.value) params.keyword = keyword.value;
    const res = await kefuIndex(params);
    sessions.value = res.data?.list || [];
  } finally {
    loadingSessions.value = false;
  }
};

const pullMessages = async () => {
  if (!current.value) return;
  const lastId = messages.value.length ? messages.value[messages.value.length - 1].id : 0;
  try {
    const res = await kefuMessages({ session_id: current.value.id, last_id: lastId });
    const rows = res.data?.list || [];
    if (rows.length) {
      messages.value = messages.value.concat(rows);
      scrollBottom();
    }
    if (res.data?.session) {
      current.value = { ...current.value, ...res.data.session };
    }
  } catch (e) { /* ignore */ }
};

const openSession = async (s) => {
  current.value = { ...s };
  messages.value = [];
  replyText.value = '';
  await pullMessages();
  // 清掉列表未读
  const idx = sessions.value.findIndex((x) => x.id === s.id);
  if (idx >= 0) sessions.value[idx].unread_admin = 0;
};

const doReply = async () => {
  if (!replyText.value.trim()) {
    ElMessage.warning('请输入回复内容');
    return;
  }
  sending.value = true;
  try {
    await kefuReply({ session_id: current.value.id, content: replyText.value.trim() });
    replyText.value = '';
    await pullMessages();
    loadSessions();
  } finally {
    sending.value = false;
  }
};

const doClose = async () => {
  try {
    await ElMessageBox.confirm('结束后会员需重新发起咨询，确定结束本次会话？', '提示', { type: 'warning' });
  } catch (e) { return; }
  await kefuClose({ session_id: current.value.id });
  ElMessage.success('会话已结束');
  await pullMessages();
  loadSessions();
};

onMounted(() => {
  loadSessions();
  timer = setInterval(pullMessages, 5000);
  listTimer = setInterval(() => { if (!current.value) loadSessions(); }, 10000);
});
onUnmounted(() => {
  clearInterval(timer);
  clearInterval(listTimer);
});
</script>

<style scoped>
.kefu-wrap { display: flex; gap: 12px; height: calc(100vh - 130px); }
.kefu-side { width: 320px; background: #fff; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; }
.side-head { padding: 12px; border-bottom: 1px solid #f0f1f3; }
.session-list { flex: 1; overflow-y: auto; }
.session { display: flex; gap: 10px; padding: 12px; cursor: pointer; border-bottom: 1px solid #f7f8fa; }
.session:hover { background: #f8fafd; }
.session.active { background: #ecf5ff; }
.s-avatar { width: 38px; height: 38px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.s-main { flex: 1; min-width: 0; }
.s-top { display: flex; justify-content: space-between; gap: 8px; }
.s-name { font-weight: 600; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.s-time { color: #c0c4cc; font-size: 11px; flex-shrink: 0; }
.s-bottom { display: flex; justify-content: space-between; gap: 8px; margin-top: 4px; align-items: center; }
.s-msg { color: #909399; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.s-badge { background: #f56c6c; color: #fff; font-size: 11px; border-radius: 9px; padding: 0 6px; height: 18px; line-height: 18px; }
.kefu-main { flex: 1; background: #fff; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; }
.chat-head { height: 50px; padding: 0 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f0f1f3; }
.chat-body { flex: 1; overflow-y: auto; padding: 16px; background: #f7f8fa; }
.msg-row { display: flex; margin-bottom: 12px; }
.msg-row.mine { justify-content: flex-end; }
.msg-row.sys { justify-content: center; }
.sys-tip { color: #909399; font-size: 12px; background: #eef0f3; padding: 4px 12px; border-radius: 12px; }
.bubble { max-width: 70%; padding: 9px 12px; border-radius: 8px; background: #fff; font-size: 13px; line-height: 1.6; word-break: break-word; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.msg-row.mine .bubble { background: #409eff; color: #fff; }
.empty-tip { text-align: center; color: #b0b3b8; font-size: 13px; padding: 40px 0; }
.chat-input { padding: 10px 14px; border-top: 1px solid #f0f1f3; }
.send-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.chat-empty { flex: 1; display: flex; align-items: center; justify-content: center; }
.muted { color: #909399; font-size: 12px; }
</style>
