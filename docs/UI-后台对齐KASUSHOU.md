# 管理后台 UI 对齐 KASUSHOU · 取证与实施方案

> 目标：把 MKSHOP 管理后台的**框架观感与页面规范**对齐已授权的 KASUSHOU 线上后台（`gl.monkm.cn/airuan`）。
> 原则：**复刻布局结构与视觉 token，不复制对方源码**（Vue2 + 商业 admin-pro 产物，版权与可维护性双重不可行）。

---

## 1. 取证（全部可复现）

### 1.1 对方框架定性

| 证据 | 结论 |
| --- | --- |
| 产物 `public/template/admin/index.html`：`vab-chunk-*.js/css`、`apple-mobile-web-app-title=admin-pro`、`meta author=chuzhixin` | 基于 **vue-admin-beautiful（vab）** |
| `_analysis/03-前端与DIY装修体系.md`：`框架 Vue2 + Element UI`；产物有 `element-ui.*.js/css` | **Vue2 + Element UI 2.x**（非 Element Plus） |
| 菜单 `icon` 取值 `home-2-line / archive-drawer-line / exchange-cny-fill`；DOM 类名 `ri-*` | 图标库 **RemixIcon** |
| 编辑器 monaco、上传 `fpUpload`、PWA workbox、video.js | 见 `_analysis/03` §技术栈 |

### 1.2 视觉 token（本地编译产物 CSS 命中数复核，大小写敏感已修正）

| 用途 | 值 | CSS 命中 |
| --- | --- | --- |
| 主色 | `#1890ff` | 1107 |
| 主色浅底（激活/hover） | `#e8f4ff` | 70 |
| logo 渐变 | `linear-gradient(90deg,#006cff,#399efd)` | 24 / 26 |
| 深色侧栏底 | `#282c34` | 1253 |
| 页面底 | `#f6f8f9` | 302 |
| 成功 / 危险 | `#13ce66` / `#ff4d4f` | 233 / 266 |
| 主文本 / 次文本 / 禁用 | `#515a6e` / `#626b7d` / `#c0c4cc` | 87 / 308 / — |
| 字体 | `"PingFang SC", Arial, "Microsoft YaHei", sans-serif` | 7 |
| 尺寸 | 分栏左栏 266px(64+202) · nav 60px · tabs 50px · header 合计 110px · footer 55px · 菜单项 50px | 11 / 168 / 211 / 31 / 21 |
| 圆角 | 组件 2.5px · 面板 8px · 卡片 10px · 胶囊按钮 16px | — |
| 字号 | 表格 12px · 输入 13px · 正文 14px · 登录标题 22px | — |
| 滚动条 | 8×8px，thumb `rgba(40,44,52,.1)`（hover `.2`），radius 7px | 35 |

### 1.3 布局与交互特性（浏览器实测 + CSS 类名双向确认）

* CSS 命中：`vab-tabs-content-smooth` 31、`vab-data-empty` 35、`nprogress` 24、`fade-transform` 8、`vab-column-grid` 27、`vab-drawer` 17、`vab-query-form` 44、`order-status-bar` 8、`floating-btn` 6、`vab-footer` 4、`.el-tabs__item{margin-right:-18px}` 1。

1. **6 种布局**：column(分栏·线上默认) / comprehensive(综合) / vertical(纵向) / horizontal(横向) / common(常规) / float(浮动)；**7 套命名主题**：blue-black / blue-white / green-black / green-white / red-black / red-white / ocean(渐变)。
2. **多标签页**：高 50px，`smooth` 叠压造型（`margin-right:-18px`），带图标、首页 `noClosable`、右侧「更多」下拉＝关闭其他/左侧/右侧/全部（**无右键菜单**），持久化 `localStorage._kss_data_.tabs.visitedRoutes`。
3. **主题配置抽屉**：右上 `ri-palette-line` → 280px 右抽屉；13 项：布局、主题、菜单背景、标签、标签图标、标签风格(card/smart/smooth)、分栏风格(vertical/horizontal/card/arrow)、头部固定、国际化、进度条、刷新、搜索、通知、全屏；footer＝保存 / 恢复默认。
4. **顶栏**：折叠按钮 → 面包屑（**每项带图标**、分隔符 `>`、可点）→ 右侧 前台首页 / 通知(badge+300px popover：待办·消息·系统公告) / 全屏 / 主题 / 刷新 / 清除缓存 / 头像下拉(个人中心·退出登录)。
5. **列表页规范**：顶部 `el-alert--success` 引导条（含"快捷入口"内链）→ 查询区默认 5 项 +「展开/合并」→ 状态页签（带数量）→ **订单页额外一排胶囊状态按钮** → 工具栏全部左上（无右上）→ 表格 → 分页 `is-background` + `total,sizes,prev,pager,next,jumper` + pageSizes `[20,50,100,200,500,1000]` 默认 20。
6. **表格**：商品列表无边框无斑马；订单/用户/财务**有边框**；表头 51px（财务 63px）bg `#f5f7fa`；行高 82/74/63.9/40.9px；操作列 `el-button--text` 文字按钮 + fixed-right；单元格内数字/ID 直接渲染为可点文字按钮做快捷改值 + 复制图标。
7. **三种编辑载体**：复杂实体＝独立页面 + 页内 tabs +「下一步/保存」；简单实体＝600px dialog（label-top，仅"提交"）；详情＝**1000px 右抽屉 + 8 tabs**。
8. **其他**：nprogress 顶部进度条、`fade-transform` 页面切换（仅淡入，**无 keep-alive**）、`vab-data-empty` 空状态插画（非"暂无数据"文字）、footer 55px、右下角悬浮客服/AI 60×60。

---

## 2. 差距矩阵（我们现状 → 目标）

| 能力 | 现状 | 差距 |
| --- | --- | --- |
| 布局 | 单套 `el-container`（aside 220px + header 56px） | 无分栏/纵向/横向切换；尺寸全不符（60/50/266/110） |
| 菜单图标 | `icon` 字段入库但**完全没渲染** | 需引 RemixIcon + 别名映射（`shop`/`coin` 2 个历史值） |
| 多标签页 | 无 | 整块缺失（含持久化、更多下拉、noClosable） |
| 主题配置 | 无 | 整块缺失（布局/主题/标签风格/开关项 + 保存/恢复默认） |
| 面包屑 | 纯文字、写死"首页" | 需带图标、真实层级、可点、`breadcrumbHidden` |
| 顶栏功能 | 只有头像+退出 | 缺 通知/全屏/主题/刷新/清缓存/前台首页/个人中心 |
| 视觉 token | `main.css` 仅 22 行，主色仍是 EP 默认 `#409eff` | 需全量 token 化并覆盖 Element Plus 变量 |
| 页面切换/进度条/空态/滚动条/footer | 全无 | 整块缺失 |
| 列表页规范 | 各页自写 `page-card + toolbar + pager` | 缺 引导条/折叠查询/状态页签/胶囊按钮/自定义列/统一分页 |
| 编辑载体 | 全部 dialog | 缺 1000px 详情抽屉、复杂实体分步页 |

---

## 3. 实施阶段

### A 框架层（本轮）
1. `styles/tokens.css`：全量 CSS 变量 + Element Plus 变量覆盖（主色 `#1890ff`、浅底 `#e8f4ff`、文本/边框/圆角/尺寸/滚动条）。
2. `styles/main.css`：页面底 `#f6f8f9`、`fade-transform`、`.mks-app-main` padding 20px、footer、空态、进度条样式。
3. `store/settings.js`：布局/主题/标签/开关项，localStorage `mks_admin_settings`，保存 / 恢复默认。
4. `store/tabs.js`：visitedViews、noClosable、关闭其他/左/右/全部，localStorage `mks_admin_tabs`。
5. `layout/*`：`index.vue`(column/vertical/horizontal) + `columnBar.vue`(64+202) + `navBar.vue` + `tabsView.vue`(smooth 叠压) + `sideMenu.vue` + `themeDrawer.vue`(280px)。
6. `components/mksIcon.vue`：DB 图标名 → `ri-*`，含历史值别名。
7. `utils/progress.js`：自实现顶部进度条（**不引 nprogress**）。
8. 依赖：仅新增 **remixicon@4.9.1**（唯一新增）。
9. `views/login.vue`：对齐 903×518 双栏卡片 + 324px 表单 + 48px 按钮。

### B 通用组件层（本轮）
`mksPage`（引导条+查询+工具栏+表格+分页容器）、`mksQueryForm`（默认 5 项 + 展开/合并）、`mksStatusTabs`（数量角标 tabs + 胶囊状态排）、`mksColumnSetter`（500px 自定义列）、`mksEmpty`（空态插画位）。

### C 页面改造（本轮做 2 个样板，后续铺开）
先改 **订单列表**（双轨筛选 + 自定义列 + 文字按钮操作列）与 **商品列表**（折叠查询 + 状态页签 + 无边框表格），确立可复制范式；其余 20+ 页按范式跟进。

### D 验收
* 构建产物 0 error；服务器开发环境部署后浏览器实测。
* 逐项核对 §1.3 的 8 类特性（截图对比 KASUSHOU 同页）。
* 回归：菜单跳转、标签持久化、主题切换后刷新保持、分页与筛选仍可用。

---

## 4. 明确不做

* 不复制 vab/admin-pro 源码与 SCSS 主题包（版权 + Vue2 不兼容）；只复刻**结构与数值**。
* 不引入 echarts / nprogress / vuedraggable 等非必要依赖。
* 6 套布局先落地 column / vertical / horizontal 三套（覆盖线上默认与两种主流形态），其余留开关位。
* 深色主题 7 套用 CSS 变量实现（不做 1000+ 条预编译规则），视觉等价即可。

---

## 5. 实施记录（第二轮浏览器验收后）

### 5.1 已修复（服务器开发环境 E2E 16/16 + 浏览器实测）

| 上轮缺陷 | 修法 | 证据 |
| --- | --- | --- |
| 首页标签多出一个关闭 X + 锁图标 | 不再依赖 EP 的 closable 叠加（源码 `tab-nav.mjs:234` 证明 pane 级 false 会被 tabs 级 true 或运算覆盖），改为自渲染关闭图标 | `hasCloseIcon=false`、class 无 `is-closable` |
| smooth 风格多出底部激活蓝条 | `.el-tabs__active-bar{display:none}` | `display:none`，叠压 `-18px` 保留 |
| 面包屑上一级不可点 / 末级多余 `>` | 非末级改用 `:to` 生成 `is-link`；补 `cursor:pointer`；末级分隔符隐藏 | `innerCls` 含 `is-link`、`lastSep=none` |
| 隐形图标（brush/money/shop/coin） | 废弃“运行时量宽度”兜底（会把隐藏 popper 误判为非法，上轮 6 个假阳性），改为构建期生成权威名单 `tools/gen-icon-names.mjs` → `utils/iconNames.js` | 12 个渠道真实隐形图标 0 |
| 页脚透明 | 白底 + 上边框 55px | `bg=rgb(255,255,255) h=55` |
| 横向布局丢「首页」渠道 | 新增 `navMenus()`，不再按 `path!=='/'` 一刀切过滤 | topItems 12 项含首页 |
| 「分栏风格」是死控件 | 4 个取值全部实现真实差异（纵向/卡片/箭头/横向） | 实测 groupW/menuColW/dividers 均变化 |
| **订单归属页签过滤后端静默失效** | `OrderController::$crudSearchable` 补 `provide_type` | 供货(5)=0、平台(1)=6，与角标一致 |
| 「订单配置」快捷入口白屏 | 目标改为真实页面 `/setting/base/general` | 不再白屏 |
| 重复菜单覆盖真实页面（定价模板/财务对账） | `mks_m8c.sql` 隐藏 id=13/428/594 | 重复菜单数=0 |
| 菜单标题双重编码乱码 | `mks_m8c.sql` 修正 6 行 + `SqlImport` 强制 `SET NAMES utf8mb4` 防复发 | 乱码标题数=0 |
| 统计页 path 与渠道 path 冲突 | `mks_m8c.sql` 改 path 为 `*_analysis` | 已生效 |

### 5.2 有意偏离 KASUSHOU 的一处行为

面包屑里的**容器节点（component=Layout）不给链接**。KASUSHOU 自身存在该歧义：首页下统计页 path=`goods/member/order` 与商品/用户/订单渠道同路径，点面包屑“订单”会落到“交易分析”页。我们已通过 `mks_m8c.sql` 把统计页 path 改为 `*_analysis` 并让容器不可点 —— 属于可用性纠正，不是还原度损失。

### 5.3 剩余差距（按可见度排序，下一轮做）

1. 布局只有 4 套（缺 `common` 常规 / `float` 浮动）。
2. 编辑载体未铺开：1000px 详情抽屉 + 多 tab、复杂实体独立页 + 页内 tab + 下一步/保存（当前仍是 dialog 为主）。
3. 右下角悬浮客服/AI 60×60 未做。
4. ~~其余 20+ 列表页尚未套用 `mksPage` 范式（目前只有订单/商品两页）~~ → 见 §5.5，已铺开 23 页；表格内“数字即快捷操作 + 复制图标”仍未铺开。
5. 通知 popover 的「消息/系统公告」仍是空态，等 notice 模块（死表 `mks_notice`/`mks_email`）接入。
6. 页签角标不随筛选联动（`status_counts` 只按近 30 天口径），需把 where DSL 复用进聚合查询。
7. ~~表格空态插画目前只在订单/商品接入~~ → 随 §5.5 全量列表页已接 `mks-empty`。

### 5.4 第三轮：部署后截图复查的两处视觉缺陷

| 缺陷 | 根因 | 修法 | 复验证据 |
| --- | --- | --- | --- |
| 查询区中文标签被压成逐字竖排（"订单号"→订/单/号） | `mksQueryForm` 写了 `label-width="0"`，EP 标签盒宽度归零后按 min-content 折行 | 删掉该属性，标签交回 EP 自适应宽度，并补 `:deep(.el-form-item__label){white-space:nowrap}` | 订单页 5 个标签 `h=32`（单行）、`w=54~68`；商品页 5 个标签同 |
| 状态页签的数量角标掉到第二行 | `.el-tabs__item` 是 flex 项，标签与数量同处 `.tab-lab` 却无排版约束，被挤压时换行 | `.tab-lab` 显式 `inline-flex + align-items:center + white-space:nowrap` | 订单 4 个 + 商品 10 个页签全部 `countTop === labTop`、`h=40` |

* 部署方式改为**原子切换**：新产物先落 `public/admin-web.next`，校验 `index.html`+`assets/` 齐全后 `mv` 换目录并保留 `admin-web.bak-<ts>` 备份，失败即中止不动线上。
* 顺带解决遗留项「旧 hash chunk 累积」：服务器 `admin-web/assets` 曾累积到 **426 个文件**，切换后收敛为本次构建的 76 个。
* 本轮浏览器实测 0 应用侧 console error，17 个 XHR/fetch 全 200。

### 5.5 第四轮：`mksPage` 列表页范式铺开到全站（§5.3 第 4 项关闭）

统一骨架：`mks-page` → `#alert`（引导条 + 快捷入口内链）→ `#query`（`mks-query-form`，≤5 项常显、其余 `#more` 折叠）→ 卡片内 `#status`（`mks-status-tabs`）→ `#toolbar` → 表格（`#empty` 用 `mks-empty`）→ `#pager`。分页统一 `layout="total, sizes, prev, pager, next, jumper"` + `:page-sizes="[20,50,100,200,500,1000]"` + `v-model:page-size`，`limit` 基线 15 → 20，筛选空值统一 `''`（不再用 `null`）。

已套用（25 页，`grep -rl "<mks-page>" views` 计数）：订单/商品（第二轮）+ `finance/moneyDay`·`dockingDay`·`withdrawRecord`、`member/memberList`·`memberLevel`·`commissionList`·`userPriceTpl`、`goods/categoryList`·`goodsTpl`·`goodsTags`·`dockingList`·`priceReady`·`cardStockManagement`、`order/complainList`、`setting/dict`·`task`·`userManagement/menuList`·`roleList`·`userList`、`vip/vipList`、`diy/diyList`、`merchant/merchantList`、`setting/addonList`。

三条实施中定下来的规则：

| 场景 | 处理 | 原因 |
| --- | --- | --- |
| 一个页面切多个独立数据源（卡密库/卡密/导卡历史、管理员/操作日志/登录日志） | `#status` 放页签，default 用 `<template v-if>` 分支，分页退回 `<div class="pager">` | `v-slot` 不能嵌在 `<template v-if>` 内；`.toolbar`/`.pager` 在 `main.css` 已是全局类 |
| 页签切换数据源 vs 页签做状态筛选 | 前者 `paneTabs`（值=tab 名），后者 `statusTabs`（值=where 字段值） | 避免把「视图切换」误当筛选传给 `buildWhere` |
| 筛选只对某个页签生效（`userList` 的账号/状态只管管理员列表） | `mks-query-form` 上加 `v-if="tab === 'list'"` | 否则日志页签下出现一排无效筛选 |

* 快捷入口的内链目标不靠猜：从开发库 `mks_admin_router` 导出 `path`+`component` 对照表逐条核对（子级 `path` 是相对段，需按 parentId 逐级拼接，如 `/setting/usermanagement/dict`）。已用 12 个目标全部命中，未注册路径会掉进 `placeholder.vue`。
* 仍保留 `page-card` 的只剩首页工作台与 3 个分析页（非列表页）+ `placeholder`，不在本范式范围内。

### 5.6 第五轮：mksup.top 部署后全站路由实测，修掉「菜单遗留行遮蔽真实页面」

部署产物 `assets/index-C40KmQVq.js`（原子切换，备份 `admin-web.bak-20261113235448`），浏览器直读 `router.getRoutes()` + 逐页探针验收。

**发现的缺陷不是页面写的，而是路由注册被菜单脏数据遮蔽**（3 个已实现页打开是「排期中」占位符）：

| 现象 | 根因 | 修法 |
| --- | --- | --- |
| `/finance/moneyday`、`/goods/pricereadydata` 显示占位符 | `mks_admin_router` 同 path 两行：遗留行（id 428/13，`hidden=1`，component 写成 `finance/moneyday`、`goods/priceReadyData` —— 仓库里根本没有这些文件）先注册，Vue Router 先注册者胜，把新行（651/600）的真实组件遮掉 | 前端：`registerDynamicRoutes` 改为**先收集再按 path 择优注册**（组件已实现 2 分 > 未实现 0 分，可见 1 分 > hidden 0 分）；数据：`mks_m8d.sql` 把遗留行 component 改指真实视图 |
| `/order/complain` 占位符，另有一个游离顶级「售后」 | 订单组内 id 25 指向从未实现的 `order/orderComplain`；我们补的页面挂在首页容器下成 id 611（`/complain`） | `mks_m8d.sql`：id 25 改指 `@/views/order/complainList`，id 611 软删 —— 菜单只保留订单组那一条 |
| 面包屑出现遗留行旧标题（财务对账管理）、侧栏却是「财务对账」 | `breadcrumbOf` 精确匹配取第一条，hidden 遗留行插在前面 | 同 path 候选按 `hidden` 排序择优先取可见行 |
| 6 条路由注册成 `//index`、`//vip`、`//commission`、`//goods_analysis` 等 | `registerDynamicRoutes` 拼父路径时，父级是首页容器 `path='/'`，得到 `//xxx`；靠 Vue Router 归一化才勉强能跳（`utils/menu.js` 的 `joinPath` 早就折叠了重复斜杠，两边规则不一致） | 拼接后 `.replace(/\/{2,}/g,'/')`，与 `joinPath` 对齐 |

复验（全部浏览器实测，非静态检查）：

* `getRoutes()` 92 条：`path.includes('//')` **0 条**、同 path 重复注册 **0 条**。
* 「可见 + 组件已实现」的 33 条菜单路径逐条 resolve：**缺失 0**；moneyday/pricereadydata/order-complain 的 `meta.menuId` 分别是 **651 / 600 / 25**（可见行胜出），标题与侧栏一致。
* 上述 3 页 + `/index`·`/vip`·3 个分析页·`/service/workbench` 渲染真实组件，`placeholder=false`；moneyday 面包屑 `财务 > 财务对账`。
* 33 页范式体检沿用第四轮指标：`guide=1`、`mksCard=1`、竖排标签 **0**、掉行角标 **0**、分页含 `sizes`+`jumper`。`finance/moneyDay` 无分页属设计（按日聚合、无 page/limit 状态）。
* 当前构建 console **0 error / 0 warn**，74 个 script/xhr/fetch 全 200。
* 全站 `component` 对照视图文件名的审计脚本（`parentId` 逐级拼 path → 比对 `views/**`）留在临时目录，未入库；结果显示其余 50+ 条指向不存在视图的菜单是**有意为之**：它们是 KASUSHOU 原始菜单里我们尚未实现的功能，按约定落到 `placeholder.vue`。

部署副作用记录：原子切换会删掉旧 `assets/`，切换瞬间仍开着的旧页面在懒加载时拿到 404（本次实测到 35 条 `Failed to fetch dynamically imported module`，全部来自切换前的那个会话，刷新即愈）。若后续要消除，得保留上一版 assets 目录一段时间。



