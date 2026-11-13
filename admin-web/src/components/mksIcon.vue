<template>
  <i v-if="cls" :class="cls" :style="style" aria-hidden="true"></i>
</template>

<script setup>
/**
 * RemixIcon 图标（后台菜单 icon 字段直接存 remixicon 名，如 home-2-line）
 *
 * 合法性判定用构建期生成的权威名单（tools/gen-icon-names.mjs），不做运行时量宽：
 * 上轮验收证明"量宽度"会把隐藏 popper 内的图标误判为非法（6 个假阳性）。
 *
 * 非法名处理：先查 ALIAS 语义映射（数据库历史裸名 brush/money/shop/coin 等），
 * 仍非法则回退 apps-line，避免出现 0×0 隐形图标或豆腐块。
 */
import { computed } from 'vue';
import { hasIcon } from '@/utils/iconNames';

/** 数据库历史遗留值 → 合法 remixicon 名 */
const ALIAS = {
  shop: 'store-2-line',
  coin: 'coins-line',
  brush: 'brush-line',
  money: 'money-cny-circle-line',
  'unfold-line': 'menu-unfold-line',
};

const FALLBACK = 'apps-line';

const props = defineProps({
  name: { type: [String, Object], default: '' },
  size: { type: [Number, String], default: 16 },
  color: { type: String, default: '' },
});

const cls = computed(() => {
  const raw = typeof props.name === 'string' ? props.name : '';
  const bare = raw.replace(/^ri-/, '').trim();
  if (!bare) return '';
  const fixed = ALIAS[bare] || bare;
  return `ri-${hasIcon(fixed) ? fixed : FALLBACK}`;
});

const style = computed(() => {
  const s = {};
  if (props.size) s.fontSize = `${props.size}px`;
  if (props.color) s.color = props.color;
  return s;
});
</script>
