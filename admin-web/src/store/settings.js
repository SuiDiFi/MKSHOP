import { defineStore } from 'pinia';
import { watch } from 'vue';

/**
 * 后台外观与布局设置（对齐 KASUSHOU 后台「主题配置」抽屉的 13 项）
 * 持久化 localStorage.mks_admin_settings；「保存」写盘，「恢复默认」清盘
 * 取证来源：docs/UI-后台对齐KASUSHOU.md §1.3-3
 */
const KEY = 'mks_admin_settings';

/** 默认值 = 对方线上实测值（column 分栏 / ocean 渐变 / smooth 圆滑标签 / vertical 分栏风格） */
export const DEFAULTS = {
  layout: 'column',          // column 分栏 | comprehensive 综合 | vertical 纵向 | horizontal 横向
  theme: 'ocean',            // ocean 渐变 | blue-white | blue-black | green-white | green-black | red-white | red-black
  background: 'none',        // none | vab-background（菜单背景图）
  showTabs: true,
  showTabsIcon: true,
  tabsBarStyle: 'smooth',    // card 卡片 | smart 灵动 | smooth 圆滑
  columnStyle: 'vertical',   // vertical 纵向 | horizontal 横向 | card 卡片 | arrow 箭头
  fixedHeader: true,
  showProgressBar: true,
  showRefresh: true,
  showNotice: true,
  showFullScreen: true,
  showThemeSetting: true,
  showFrontHomeUrl: true,
  showCleanCache: true,
  showSearch: false,         // 菜单搜索（对方默认关）
  showLanguage: false,       // 国际化（对方默认关）
};

export const LAYOUT_OPTIONS = [
  { value: 'column', label: '分栏' },
  { value: 'comprehensive', label: '综合' },
  { value: 'vertical', label: '纵向' },
  { value: 'horizontal', label: '横向' },
];

export const THEME_OPTIONS = [
  { value: 'ocean', label: '渐变' },
  { value: 'blue-white', label: '蓝白' },
  { value: 'blue-black', label: '蓝黑' },
  { value: 'green-white', label: '绿白' },
  { value: 'green-black', label: '绿黑' },
  { value: 'red-white', label: '红白' },
  { value: 'red-black', label: '红黑' },
];

export const TABS_STYLE_OPTIONS = [
  { value: 'card', label: '卡片' },
  { value: 'smart', label: '灵动' },
  { value: 'smooth', label: '圆滑' },
];

export const COLUMN_STYLE_OPTIONS = [
  { value: 'vertical', label: '纵向' },
  { value: 'horizontal', label: '横向' },
  { value: 'card', label: '卡片' },
  { value: 'arrow', label: '箭头' },
];

function load() {
  try {
    const raw = localStorage.getItem(KEY);
    return raw ? { ...DEFAULTS, ...JSON.parse(raw) } : { ...DEFAULTS };
  } catch (e) {
    return { ...DEFAULTS };
  }
}

/** 把主题/布局写成 document 上的类名，供 tokens.css 变量与布局样式消费 */
function applyClasses(s) {
  const el = document.documentElement;
  [...el.classList]
    .filter((c) => c.startsWith('mks-theme-') || c.startsWith('mks-layout-'))
    .forEach((c) => el.classList.remove(c));
  el.classList.add(`mks-theme-${s.theme}`);
  el.classList.add(`mks-layout-${s.layout}`);
  if (s.background !== 'none') el.classList.add(s.background);
}

export const useSettingsStore = defineStore('settings', {
  state: () => ({ ...load(), collapse: false, _watcherOn: false }),
  getters: {
    /** 分栏布局：左栏 266px（64 图标 + 202 菜单） */
    isColumn: (s) => s.layout === 'column',
    isVertical: (s) => s.layout === 'vertical' || s.layout === 'comprehensive',
    isHorizontal: (s) => s.layout === 'horizontal',
    /** 侧栏宽度（纵向布局折叠时 64px，分栏恒为 266px——与对方实测一致） */
    sideWidth: (s) => (s.layout === 'vertical' && s.collapse ? 'var(--mks-side-collapse-w)' : 'var(--mks-side-w)'),
  },
  actions: {
    save() {
      const out = {};
      Object.keys(DEFAULTS).forEach((k) => (out[k] = this.$state[k]));
      localStorage.setItem(KEY, JSON.stringify(out));
      applyClasses(this.$state);
    },
    reset() {
      Object.assign(this.$state, DEFAULTS);
      localStorage.removeItem(KEY);
      applyClasses(DEFAULTS);
    },
    toggleCollapse() {
      this.collapse = !this.collapse;
    },
    /** 启动时应用一次，避免刷新后主题闪白 */
    boot() {
      applyClasses(this.$state);
      if (!this._watcherOn) {
        this._watcherOn = true;
        // 运行期改主题（未点保存）也即时生效，与对方抽屉预览行为一致
        watch(
          () => [this.theme, this.layout, this.background],
          () => applyClasses(this.$state)
        );
      }
    },
  },
});
