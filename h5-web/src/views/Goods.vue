<template>
  <div class="page plain">
    <NavBar title="商品详情" />
    <div v-if="goods.id">
      <div class="hero">
        <img v-if="goods.goods_img" :src="goods.goods_img" :alt="goods.goods_name" />
        <div v-else class="ph">🛍️</div>
      </div>
      <div class="wrap">
        <div class="card">
          <div class="price" style="font-size:24px"><small>¥</small>{{ goods.real_price }}</div>
          <div class="name">{{ goods.goods_name }}</div>
          <div class="muted" style="margin-top:8px">
            已售 {{ (Number(goods.sales_num||0)+Number(goods.virtual_num||0)) }} · 库存 {{ goods.stock_real }}
          </div>
        </div>

        <div class="card">
          <div v-if="needAccount" class="field" style="margin-top:0">
            <label>充值账号</label>
            <input v-model="form.recharge_account" class="input" placeholder="请输入充值账号" />
          </div>
          <div class="field" :style="needAccount ? '' : 'margin-top:0'">
            <label>购买数量</label>
            <div class="stepper">
              <button @click="dec">−</button>
              <input v-model.number="form.quantity" type="number" />
              <button @click="inc">＋</button>
            </div>
          </div>
        </div>

        <div v-if="goods.goods_info" class="card">
          <div style="font-weight:600; margin-bottom:8px">商品详情</div>
          <div class="rich" v-html="goods.goods_info" />
        </div>
      </div>

      <div class="buybar">
        <div>
          <div class="muted">应付</div>
          <div class="price" style="font-size:20px"><small>¥</small>{{ totalAmount }}</div>
        </div>
        <button class="btn" style="min-width:150px" :disabled="buying || Number(goods.stock_num) <= 0" @click="doBuy">
          {{ Number(goods.stock_num) <= 0 ? '暂时缺货' : (buying ? '处理中…' : '立即购买') }}
        </button>
      </div>
    </div>
    <div v-else class="empty"><div class="big">🔍</div>商品不存在或已下架</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import NavBar from '@/components/NavBar.vue';
import { goodsRead, orderBuy } from '@/api';
import { toast } from '@/utils/request';

const route = useRoute();
const router = useRouter();
const goods = ref({});
const buying = ref(false);
const form = ref({ quantity: 1, recharge_account: '' });

const needAccount = computed(() => Number(goods.value.goods_type) === 2);
const minCount = computed(() => Math.max(1, Number(goods.value.start_count || 1)));
const maxCount = computed(() => Math.max(minCount.value, Number(goods.value.end_count || 999)));
const totalAmount = computed(() => (Number(goods.value.real_price || 0) * form.value.quantity).toFixed(2));

const dec = () => { if (form.value.quantity > minCount.value) form.value.quantity--; };
const inc = () => { if (form.value.quantity < maxCount.value) form.value.quantity++; };

const doBuy = async () => {
  if (!localStorage.getItem('mks_member_token')) {
    router.push({ name: 'Login', query: { redirect: route.fullPath } });
    return;
  }
  if (needAccount.value && !form.value.recharge_account) {
    toast('请输入充值账号');
    return;
  }
  buying.value = true;
  try {
    const res = await orderBuy({
      goods_id: goods.value.id,
      quantity: form.value.quantity,
      recharge_account: form.value.recharge_account,
      pay_type: 'balance',
    });
    toast('支付成功，正在发货');
    router.replace(`/order/${res.data?.ordersn}`);
  } catch (e) {
    // 余额不足时会创建待支付订单，提示后去登录/充值说明
    toast(e?.message || '下单失败');
  } finally {
    buying.value = false;
  }
};

onMounted(async () => {
  try {
    const res = await goodsRead(route.params.id);
    goods.value = res.data || {};
    form.value.quantity = Math.max(1, Number(goods.value.start_count || 1));
  } catch (e) { /* ignore */ }
});
</script>

<style scoped>
.hero { height: 240px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.hero img { width: 100%; height: 100%; object-fit: contain; }
.ph { font-size: 64px; }
.name { font-size: 17px; font-weight: 700; margin-top: 6px; line-height: 1.5; }
.rich { font-size: 13px; line-height: 1.8; color: #444; }
.rich :deep(img) { max-width: 100%; }
.stepper { display: inline-flex; align-items: center; border: 1px solid #e4e7ed; border-radius: 8px; overflow: hidden; }
.stepper button { width: 40px; height: 38px; border: 0; background: #f7f8fa; font-size: 18px; }
.stepper input { width: 64px; height: 38px; text-align: center; border: 0; outline: none; font-size: 15px; }
.buybar { position: fixed; left: 0; right: 0; bottom: 0; max-width: 560px; margin: 0 auto; background: #fff;
  padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); display: flex; justify-content: space-between;
  align-items: center; border-top: 1px solid #eee; }
</style>
