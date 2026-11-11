<template>
  <div class="page-card">
    <el-row :gutter="12">
      <el-col :span="24">
        <el-card shadow="never">
          <div class="hello">
            <h2>欢迎使用 MKSHOP 管理后台</h2>
            <p>当前登录：{{ user.userinfo.realname || user.userinfo.username }}（ID: {{ user.userinfo.id }}）</p>
            <p>系统版本 v0.1.0 · M0 基础设施里程碑</p>
          </div>
        </el-card>
      </el-col>
    </el-row>
    <el-row :gutter="12" style="margin-top: 12px">
      <el-col :span="8" v-for="card in cards" :key="card.title">
        <el-card shadow="hover">
          <div class="stat">
            <div class="stat-icon" :style="{ background: card.color }">
              <el-icon :size="26"><component :is="card.icon" /></el-icon>
            </div>
            <div>
              <div class="stat-num">{{ card.num }}</div>
              <div class="stat-title">{{ card.title }}</div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
    <el-card shadow="never" style="margin-top: 12px">
      <template #header>里程碑进度</template>
      <el-steps :active="1" align-center>
        <el-step title="M0 基础设施" description="进行中：骨架/基座/系统模块已就绪" />
        <el-step title="M1 商品+卡密" />
        <el-step title="M2 交易+支付" />
        <el-step title="M3 上游对接" />
        <el-step title="M4 会员分销" />
        <el-step title="M5 前端+装修" />
        <el-step title="M6 上线" />
      </el-steps>
    </el-card>
  </div>
</template>

<script setup>
import { useUserStore } from '@/store/user';

const user = useUserStore();
const cards = [
  { title: '今日订单', num: '-', icon: 'ShoppingCart', color: '#409eff' },
  { title: '今日销售额', num: '-', icon: 'Coin', color: '#67c23a' },
  { title: '会员总数', num: '-', icon: 'User', color: '#e6a23c' },
];
</script>

<style scoped>
.hello h2 { margin: 0 0 8px; }
.hello p { color: #909399; margin: 4px 0; font-size: 13px; }
.stat { display: flex; align-items: center; gap: 14px; }
.stat-icon { width: 52px; height: 52px; border-radius: 10px; color: #fff; display: flex; align-items: center; justify-content: center; }
.stat-num { font-size: 22px; font-weight: 700; color: #303133; }
.stat-title { font-size: 13px; color: #909399; }
</style>
