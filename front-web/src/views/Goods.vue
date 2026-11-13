<template>
  <div>
    <Topbar />
    <div class="wrap page-body" v-loading="loading">
      <template v-if="goods.id">
        <div class="detail-wrap">
          <div class="detail-img">
            <img v-if="goods.goods_img" :src="goods.goods_img" :alt="goods.goods_name" />
            <el-icon v-else :size="64" color="#c0c4cc"><GoodsFilled /></el-icon>
          </div>
          <div class="detail-info">
            <h2 style="margin: 0 0 10px">{{ goods.goods_name }}</h2>
            <div style="color: #909399; font-size: 13px; margin-bottom: 12px">
              <el-tag size="small" effect="plain" style="margin-right: 8px">{{ typeText(goods.goods_type) }}</el-tag>
              <span>已售 {{ (goods.sales_num || 0) + (goods.virtual_num || 0) }}</span>
              <span style="margin-left: 12px">库存 {{ goods.stock_real }}</span>
              <template v-if="Number(goods.recharge_time_value) > 0">
                <span style="margin-left: 12px">预计 {{ goods.recharge_time_value }}{{ ['', '分', '时', '天'][goods.recharge_time_type] || '' }}内到账</span>
              </template>
            </div>
            <div style="display:flex; align-items: baseline; gap: 10px; margin-bottom: 14px">
              <span style="color: #f56c6c; font-size: 26px; font-weight: 700">
                <small style="font-size: 14px">¥</small>{{ goods.real_price }}
              </span>
              <span v-if="Number(goods.face_value) > 0" style="color: #c0c4cc; text-decoration: line-through">
                ¥{{ goods.face_value }}
              </span>
            </div>

            <el-form label-width="100px" style="max-width: 460px">
              <template v-if="tplInputs.length">
                <el-form-item v-for="(it, i) in tplInputs" :key="i" :label="it.label" :required="Number(it.required) === 1">
                  <el-input v-model="tplValues[i]" maxlength="200"
                    :placeholder="it.placeholder || ('请输入' + it.label)" />
                </el-form-item>
              </template>
              <el-form-item v-else-if="needAccount" label="充值账号">
                <el-input v-model="buyForm.recharge_account" placeholder="请输入充值账号" />
              </el-form-item>
              <el-form-item label="购买数量">
                <el-input-number v-model="buyForm.quantity" :min="goods.start_count || 1"
                  :max="Math.max(goods.end_count || 1, goods.start_count || 1)" />
              </el-form-item>
              <el-form-item label="应付金额">
                <span style="color:#f56c6c; font-size: 20px; font-weight: 700">
                  ¥{{ totalAmount }}
                </span>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" size="large" style="width: 180px" :loading="buying" @click="doBuy">
                  立即购买
                </el-button>
              </el-form-item>
            </el-form>
          </div>
        </div>

        <div v-if="goods.goods_info" class="section-title">商品详情</div>
        <div v-if="goods.goods_info" style="background:#fff; border-radius:8px; padding: 16px" v-html="goods.goods_info" />
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { goodsRead, orderBuy } from '@/api';
import { useUserStore } from '@/store/user';

const route = useRoute();
const router = useRouter();
const store = useUserStore();

const goods = ref({});
const loading = ref(true);
const buying = ref(false);
const buyForm = ref({ quantity: 1, recharge_account: '' });
const tplValues = ref([]);

// 商品绑定下单模板时按模板定义渲染输入项（未绑定则维持原有输入）
const tplInputs = computed(() => (goods.value.tpl && Array.isArray(goods.value.tpl.inputs)) ? goods.value.tpl.inputs : []);

const needAccount = computed(() => Number(goods.value.goods_type) === 2);
const totalAmount = computed(() => {
  const p = Number(goods.value.real_price || 0);
  return (p * buyForm.value.quantity).toFixed(2);
});

const typeText = (t) => ({ 1: '自动发卡', 2: '虚拟充值', 3: '实物' }[t] || '虚拟商品');

const doBuy = async () => {
  if (!store.isLogin) {
    ElMessage.warning('请先登录');
    router.push({ name: 'Login', query: { redirect: route.fullPath } });
    return;
  }
  if (tplInputs.value.length) {
    for (let i = 0; i < tplInputs.value.length; i++) {
      const it = tplInputs.value[i];
      if (Number(it.required) === 1 && !String(tplValues.value[i] || '').trim()) {
        ElMessage.warning('请填写' + it.label);
        return;
      }
    }
  } else if (needAccount.value && !buyForm.value.recharge_account) {
    ElMessage.warning('请输入充值账号');
    return;
  }
  buying.value = true;
  try {
    const payload = {
      goods_id: goods.value.id,
      quantity: buyForm.value.quantity,
      pay_type: 'balance',
    };
    if (tplInputs.value.length) {
      payload.tpl_inputs = tplValues.value;
    } else {
      payload.recharge_account = buyForm.value.recharge_account;
    }
    const res = await orderBuy(payload);
    ElMessage.success('下单成功');
    router.push(`/order/${res.data?.ordersn}`);
  } catch (e) { /* 拦截器已提示 */ } finally {
    buying.value = false;
  }
};

onMounted(async () => {
  try {
    const res = await goodsRead(route.params.id);
    goods.value = res.data || {};
    tplValues.value = tplInputs.value.map(() => '');
  } catch (e) { /* ignore */ } finally {
    loading.value = false;
  }
});
</script>
