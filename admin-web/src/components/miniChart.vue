<template>
  <div class="mini-chart">
    <div v-if="!hasData" class="chart-empty">暂无数据</div>
    <svg v-else :viewBox="`0 0 ${W} ${H}`" :style="{ height: height + 'px' }" preserveAspectRatio="none">
      <!-- 网格与刻度 -->
      <line v-for="(g, i) in gridLines" :key="'g' + i" :x1="PAD_L" :y1="g.y" :x2="W - PAD_R" :y2="g.y" class="grid" />
      <text v-for="(g, i) in gridLines" :key="'t' + i" :x="PAD_L - 6" :y="g.y + 3" class="tick" text-anchor="end">{{ g.label }}</text>

      <!-- 柱状 -->
      <template v-if="type === 'bar'">
        <rect
          v-for="(b, i) in bars"
          :key="'b' + i"
          :x="b.x"
          :y="b.y"
          :width="b.w"
          :height="b.h"
          :fill="b.color"
          rx="1"
        >
          <title>{{ b.title }}</title>
        </rect>
      </template>

      <!-- 折线 + 面积 -->
      <template v-else>
        <path v-for="(s, i) in paths" :key="'a' + i" :d="s.area" :fill="s.color" opacity="0.08" />
        <polyline
          v-for="(s, i) in paths"
          :key="'l' + i"
          :points="s.points"
          fill="none"
          :stroke="s.color"
          stroke-width="1.6"
          stroke-linejoin="round"
        />
        <circle v-for="(p, i) in dots" :key="'d' + i" :cx="p.x" :cy="p.y" r="2" :fill="p.color">
          <title>{{ p.title }}</title>
        </circle>
      </template>

      <!-- X 轴标签（稀疏显示，避免重叠） -->
      <text v-for="(x, i) in xLabels" :key="'x' + i" :x="x.x" :y="H - 4" class="tick" text-anchor="middle">{{ x.label }}</text>
    </svg>

    <div v-if="legend.length && showLegend" class="chart-legend">
      <span v-for="l in legend" :key="l.name">
        <i :style="{ background: l.color }"></i>{{ l.name }}
      </span>
    </div>
  </div>
</template>

<script setup>
/**
 * 零依赖 SVG 迷你图表（后台统计专用）
 * 不引 echarts/chart.js：admin-web 目前无图表依赖，构建产物与安装体积保持最小
 * labels: x 轴标签数组；series: [{ name, color, data: number[] }]
 */
import { computed } from 'vue';

const props = defineProps({
  labels: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  type: { type: String, default: 'line' }, // line | bar
  height: { type: Number, default: 180 },
  showLegend: { type: Boolean, default: true },
  fmt: { type: Function, default: (v) => String(Math.round(v)) },
});

const W = 720;
const PAD_L = 44;
const PAD_R = 10;
const PAD_T = 12;
const PAD_B = 20;
const COLORS = ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#9254de'];

const H = computed(() => Math.max(120, props.height));
const innerW = computed(() => W - PAD_L - PAD_R);
const innerH = computed(() => H.value - PAD_T - PAD_B);
const hasData = computed(() => props.labels.length > 0 && props.series.some((s) => (s.data || []).some((v) => Number(v) !== 0)));

const maxVal = computed(() => {
  const all = props.series.flatMap((s) => (s.data || []).map(Number));
  const m = all.length ? Math.max(...all) : 0;
  return m > 0 ? m : 1;
});

const xOf = (i) => PAD_L + (props.labels.length <= 1 ? innerW.value / 2 : (innerW.value * i) / (props.labels.length - 1));
const yOf = (v) => PAD_T + innerH.value - (Math.max(0, Number(v)) / maxVal.value) * innerH.value;

const gridLines = computed(() => {
  const step = maxVal.value / 4;
  return [0, 1, 2, 3, 4].map((k) => {
    const v = step * k;
    return { y: PAD_T + innerH.value - (v / maxVal.value) * innerH.value, label: props.fmt(v) };
  });
});

const paths = computed(() =>
  props.series.map((s, si) => {
    const color = s.color || COLORS[si % COLORS.length];
    const pts = (s.data || []).map((v, i) => `${xOf(i)},${yOf(v)}`);
    const base = PAD_T + innerH.value;
    return {
      color,
      points: pts.join(' '),
      area: pts.length ? `M${pts[0]} ${pts.slice(1).map((p) => 'L' + p).join(' ')} L${xOf(s.data.length - 1)},${base} L${xOf(0)},${base} Z` : '',
    };
  })
);

const dots = computed(() => {
  const out = [];
  props.series.forEach((s, si) => {
    const color = s.color || COLORS[si % COLORS.length];
    (s.data || []).forEach((v, i) => {
      // 点多时只画稀疏点，保持可读
      if (props.labels.length > 40 && i % Math.ceil(props.labels.length / 30) !== 0) return;
      out.push({ x: xOf(i), y: yOf(v), color, title: `${props.labels[i]} ${s.name}: ${props.fmt(v)}` });
    });
  });
  return out;
});

const bars = computed(() => {
  const out = [];
  const n = props.labels.length || 1;
  const group = innerW.value / n;
  const cnt = props.series.length || 1;
  const bw = Math.max(2, (group * 0.7) / cnt);
  props.series.forEach((s, si) => {
    const color = s.color || COLORS[si % COLORS.length];
    (s.data || []).forEach((v, i) => {
      const h = (Math.max(0, Number(v)) / maxVal.value) * innerH.value;
      out.push({
        x: PAD_L + group * i + (group - bw * cnt) / 2 + bw * si,
        y: PAD_T + innerH.value - h,
        w: bw,
        h,
        color,
        title: `${props.labels[i]} ${s.name}: ${props.fmt(v)}`,
      });
    });
  });
  return out;
});

const xLabels = computed(() => {
  const n = props.labels.length;
  if (!n) return [];
  const maxShow = 12;
  const stride = Math.ceil(n / maxShow);
  return props.labels
    .map((label, i) => ({ label, x: xOf(i) }))
    .filter((_, i) => i % stride === 0 || i === n - 1);
});

const legend = computed(() =>
  props.series.map((s, si) => ({ name: s.name, color: s.color || COLORS[si % COLORS.length] }))
);
</script>

<style scoped>
.mini-chart { width: 100%; }
.mini-chart svg { width: 100%; display: block; }
.grid { stroke: #ebeef5; stroke-width: 1; }
.tick { fill: #909399; font-size: 10px; }
.chart-empty { height: 120px; display: flex; align-items: center; justify-content: center; color: #c0c4cc; font-size: 13px; background: #fafafa; border-radius: 6px; }
.chart-legend { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 6px; font-size: 12px; color: #606266; }
.chart-legend i { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 4px; }
</style>
