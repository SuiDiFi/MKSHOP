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
6. **表格**：商品列表无边框无斑马；订单/用户/财务**有边框**；表头 51px（财务 63px）bg `#f5f7fa`；行高 82/74/63.9/40.9px；操作列 `el-button--text` 文字按钮 + fixed-right；可点单元格与复制的**具体形态见 §1.4-D**（本节初稿把它写成「`floating-btn` 单元格快捷改值」是误标，已在 §1.4 纠正）。
7. **三种编辑载体**：复杂实体＝独立页面 + 页内 tabs +「下一步/保存」；简单实体＝600px dialog（label-top，仅"提交"）；详情＝**1000px 右抽屉 + 8 tabs**。⚠️ 本条初稿是**由编译产物粗略推断**写成，其中「8 tabs」「600px + label-top + 仅提交」三处与实物不符；**以 §1.4 的双向取证为准**。
8. **其他**：nprogress 顶部进度条、`fade-transform` 页面切换（仅淡入，**无 keep-alive**）、`vab-data-empty` 空状态插画（非"暂无数据"文字）、footer 55px、右下角悬浮客服/AI 60×60。

---

### 1.4 编辑载体专项取证（第六轮，线上只读实测 + 编译产物双向确认）

本轮起因：§1.3 第 7 条当年只翻了编译产物就下了结论。铺开载体前先在**对方线上后台**（`gl.monkm.cn/airuan`，人工登录、全程只点读态、未点任何「保存/确认」）实测，再与 `H:\MKSHOP\public\template\admin\static\js\`（467 个压缩 chunk，中文串与接口路径明文）对账。两路证据一致处标「双向」，只有单路支撑处单独注明。

**A. 详情抽屉（双向，证据最强）**

| 项 | 线上实测 | 编译产物 |
| --- | --- | --- |
| 载体 | `el-drawer rtl`，内联 `style.width="1000px"`，`getBoundingClientRect().width` = **1000** | `DrawerOrderDetail`（`chunk-1eaec9b0.0c5d9ab3.js`）与 `DrawerMemberDetail`（`chunk-6761df96.5d89c49f.js`）均 `el-drawer` `append-to-body` `with-header` `size:"1000px"`；全库 `"1000px"` 字面量 43 处，无按布局切换的绑定 |
| 页签数 | 订单详情实测 **7 个**：订单详情 / 订单处理 / 商品信息 / 订单日志 / 订单佣金 / 佣金明细 / 资金明细 | `tabList` 计算属性**动态过滤**，最多 8：`订单处理`＝Tab6/Tab6_2/Tab6_3 按 `goods_type` 三选一，`供货明细` 仅 `order.supplier_id>0`。**7 与 8 不矛盾**：实测订单无渠道供货方 → 少一个 |
| 固定 8 tab 的是谁 | — | **用户详情抽屉**：用户信息 / 余额明细 / 购买记录 / 充值记录 / 佣金明细 / 佣金订单 / 浏览足迹 / 登录日志（`name=Tab1..7,10`）→ §1.3 的「8 tabs」其实来自这里 |
| 页签 → 接口 | 逐个点开抓包：订单详情 `GET /order/info`、订单处理 **零请求**、商品信息 `GET /order/goods`、订单日志 `GET /order_log/index`、订单佣金 `GET /order_commission/index`、佣金明细 `POST /member/one_info`、资金明细 `GET /member_money/index` | 抽屉 `open()` 只发一次主请求（订单 `GET /order/info?id&search_data`，回包带 `last_order_id/next_order_id/goods_type/supplier_id`）；每页签是 `()=>import()` 异步组件 + `keep-alive`，各自发列表请求 |
| 头部操作 | 实测按钮：订单处理 / 下一单 / 同步订单 / 渠道订单列表 / 申请撤单 / 撤单补单 / 停止补单 / 复制卡密 / 卡密撤单 | `title` 插槽自定义 `Top`：`h3` + `border-left:3px solid #1890ff`、订单号 `el-link` + `i.el-icon-document-copy`、4 项 li（订单状态/订单总价/实际支付/下单来源）；按钮随 `status`/`goods_type` 显隐 |
| 底部 | 实测**无 footer 按钮** | 两抽屉均无 footer |
| 复用面 | — | 约 34 个 chunk 复用（订单列表/核销卡密/订单卡密/客户佣金明细/返利明细/在线充值记录/卡密库存/对冲明细/电商订单/兑换核销/监控日志/接单大厅/财务对账…） |

**B. 复杂实体独立编辑页（商品）**

* **路由**：`/goods/add`(GoodsAdd) 与 `/goods/edit/:id`(GoodsEdit) **共用同一页面模块 `dd76`**，meta `{hidden:true, activeMenu:"/goods/list", dynamicNewTab:true}`；物理文件 `views/goods/goodsList/goodsAdd/index.vue`（`_analysis\admin_views.txt:233` + `admin_routes.json`）。全库**只有商品**用这种 add/edit 同组件独立页（`/add`、`/edit/:id` 枚举仅此一组）。线上订单列表行操作实测只有「查看详情 / 删除」→ **订单不走编辑页，只用抽屉**。
* **页签**：线上可见 **7 个** = 基本信息 / 库存管理 / 商品详情 / 注意事项 / 使用说明 / 商品配置 / 二级分佣；产物 `tabList()` 过滤 `show`：基本信息(Config1) → **规格库存(Config6) 与 库存管理(Config2) 二选一**（按 `spec_type`）→ 商品详情(3) → 注意事项(4) → 使用说明(5) → 商品配置(8) →〔二级分佣 Config9，addon `goodsrebate`〕→〔批采定价 Config11，addon `batchend`〕。**基础 6 tab，满配 8**，线上这次是「spec_type=0 + 装了 rebate、未装 batchend」→ 7。
* **不是分步向导**（双向）：线上点「下一步」`apiReqsDelta = 0`、无校验报错，仅 `基本信息 → 库存管理`；产物 `nextStep/prevStep` 只改 `currentView` 切 tab，**不 validate**；`保存` 才整表 `validate` + 一次 `POST /goods/save`。按钮 `上一步`(tab1 时 `offsetHeight = 0`，即 `v-show`) / `下一步` 68×32 / `保存` 56×32，全部 `el-button--primary el-button--small`。校验失败文案 `请完善数据`，成功 `$baseMessage(res.msg,'success')` 后 `$router.push('/goods/list')` + `delVisitedRoute`。
* **表单**（线上量到 + 产物一致）：`label-width: 110px`；`label-position` 桌面端是 **right**（`computed labelPosition(){return "mobile"==device?"top":"right"}`）→ §1.3 写的 label-top 只对 `FormSubmit` 和移动端成立。**实测 collapse 数量 0**（无折叠面板）；分栏以 `el-col-24` 为主，商品配置页 `el-col-12` 双列，线上另见 6/8/11/16。数值框用指令 `v-onlyNumber:{precision,min,max}`，窄框 `width:180px`。
* **各页签实测字段数**：基本信息 16 / 库存管理 1(switch) / 商品详情 1(富文本) / 注意事项 1 / 使用说明 1 / 商品配置 25 / 二级分佣 2。图片 `PicUploader :max=5 multiple` + tips「建议尺寸：800 * 800px…最多上传5张」+ 文字按钮`网络图片`；视频 `VideoPreview upload-type="video"`。SKU 整块交给子组件 `GoodsAttr`，父页只通过 `syncAttr/syncSpec` 回灌。
* **易混淆**：另有 41 个 chunk 是「页内 tabs + keep-alive 异步子页」形态（定价模板 `priceReadyData` Tab1–4、批采模板、零售定价模板、电商管理…），但它们是 **tab-of-lists**（每个 tab 是列表页，无保存按钮），不要当成商品编辑页同类。

**C. 简单实体 dialog（纠正「统一 600px」）**

* 全库 `el-dialog` 653 处宽度分布：**600px 243(37%)** / 500px 113 / 800px 47 / 1200px 33 / 1000px 28 / 900px 24 / 700px 22 / 400px 12 / 90% 13 / 未写 51 → **600 是众数而非严格规范**。
* **§1.3 漏记**：602/653 带 `v-dialogDrag`（可拖拽），普遍 `append-to-body`，200 处 `close-on-click-modal:false`。
* 两套并存约定，别混：
  1. 动态表单载体 `FormSubmit`（模块 `ef53`）：`el-card(shadow:never,border:0)` > `el-form label-position:"top"`（硬编码）`label-width:"110px"`，footer **只有 1 个按钮**，`butName` 默认「提交」，`type=primary` + `loading` + `throttle(submit,1000,{trailing:false})`；rules/url/width/buttonHide 由服务端下发；失败 `errorsValidate=e; $message.error(e[0].message)`；`hasConfirm` → `$baseConfirm("确认继续操作吗？")`；二次验证 `$prompt("请输入验证码","操作验证")`。
  2. 手写小 dialog（`StatusEdit`/`CheckStatusEdit` 等）：`width:"500px"`、`label-width:"80px"`（**label 在左**），footer **两个**按钮「取 消」+「确 定」（primary，中间带空格）。
* 成功提示统一取服务端 `msg`；硬编码文案计数：`提交成功` 84、`复制成功` 82。

**D. 表格内快捷改值 + 复制（纠正 `floating-btn` 误标）**

* `floating-btn` 全库仅 2 处命中，是右下角 60×60 可拖拽悬浮客服/AI 按钮（`position:fixed;z-index:9999;cursor:move`），**与单元格快捷改值无关**；§1.3 当初拿它当证据是错的。
* 真实实现（`chunk-14f25374.75d95795.js`，商品列表模块 `5bb7`）：行内布尔位 `row.sortEdit` 为真渲染 `el-input`（`v-focus` + `v-only-number{max:999999999}` `type="number"`），`@blur/@change` → `$delete(row,'sortEdit')` + `handleUpdateField(row.id,'sort',row.sort)`；为假渲染可点数字。**无弹窗、不整表刷新**。
* 接口：`POST {module}/modify`，body `{id, field, value}`，返回 `msg` → `$baseMessage(msg,'success')`；同一方法也用于 `field:'status'`。（产物证据；线上未触发，触发即写库，本轮只读。）
* 打开专属小 dialog 的变体：`费率` 列 `el-link type="primary" :underline="false"` → `showRate`；`定价成本` 列 `el-link ￥cost_value` + `i.el-icon-edit`（tooltip「点击查看售价」）→ `showDefaultPrice`；`售价/库存` 列价格用 `el-link`，库存用**双段胶囊 `el-tag`**（深底标签 `border-radius:3px 0 0 3px` + 浅底数值 `min-width:72px;max-width:102px;border-left:0` + `i.el-icon-edit`），按 `stock_num<=warn_num` 切 warning/danger。
* 复制：ID 本身是 `Link`（跳转）+ 紧随 `el-link :underline="false"` 包 `i.el-icon-document-copy`，`click → handleClipboard(row.goods_url,$event)`（vab mixin，clipboard 库），成功 `$message.success("复制成功")`；全库 `el-icon-document-copy` 152 处。
* 视觉锚点清单（写样式照这个）：`el-link[type=primary]`、双段 `el-tag` 胶囊、`el-icon-edit`、`el-icon-document-copy`。
* 覆盖页面（`pageChunk` 反查 `_analysis\admin_routes.json`）：商品列表、用户列表、卡密库存、属性管理、商品模板、派单模板/商品/分类、监控日志、物流公司、管理员列表、角色列表、公告管理、广告管理、内容管理、话术管理、自动回复、优惠券列表、推荐目录、拆分组合、核销资金追扣、提现记录、支付配置、域名绑定/分站、抢单/接单渠道、货源渠道、商户、兑换核销、下单任务、财务对账等。**订单/财务列表用 1000px 抽屉，不用行内改值**。
* **线上实测确认**（`#/goods/list`，只读量 DOM，未进入编辑态以免触发写）：表头 8 列 = 排序 / 商品ID / 商品图 / 商品名称 / 定价成本 / 售价·库存 / 排序·来源 / 操作；整表 40 行的元素计数 = `el-link` 200、`el-tag` 400、`el-icon-edit` 120、`el-icon-document-copy` 40、**`input.el-input__inner` 0**（→ 非编辑态不插输入框，与 `row.xxxEdit` 布尔位机制一致）。逐列实测：商品ID 列 `el-link--default` + 复制图标（每行 1 个）；商品名称列 `el-link--primary` + `el-icon-edit` + 3 段 `el-tag--success`；定价成本列 `el-link--primary ￥3.10 面值3.1000` + `el-icon-edit`；售价/库存列 `el-link--primary ￥10.50~13.00` + **双段 `el-tag`（dark + light）**「库存74」+ `el-icon-edit`；排序/来源列 3 段 `el-tag--danger`。每行恰好 5 link + 3 edit + 1 copy，与产物代码逐一对齐。

**取证边界（诚实标注）**：本轮线上仅点击读态；未点任何「保存/确认/撤销」，未提交表单，未触碰对方任何写接口。§1.4-D 的 `/modify` 请求体形态来自编译产物，线上未实测绘得。对方后台是生产站（订单号已到 32508），改值会污染真实数据，故刻意避开。

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
| 编辑载体 | 详情＝460–560px 单栏抽屉；新增/编辑＝dialog | 缺 1000px 详情抽屉（动态页签 + 页签级懒加载接口，见 §1.4-A）、商品 add/edit 同组件独立页（§1.4-B）、表格内快捷改值 `POST {module}/modify {id,field,value}` + 复制图标（§1.4-D） |

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
2. 编辑载体未铺开：**精确规格见 §1.4**（1000px 详情抽屉 = 动态页签 + 每页签懒加载列表接口；复杂实体 = add/edit 同组件独立页 + 页内 tabs +「上一步/下一步/保存」一次性提交，**不是分步向导**；简单实体 = 600px 众数 dialog，动态表单 `FormSubmit` 才是 label-top + 仅「提交」；表格内 = `row.xxxEdit` 行内改值 + `POST {module}/modify {id,field,value}` + `el-icon-document-copy` 复制）。
   —— 其中**详情抽屉半边已落地并线上验收**（4 页接入，见 §5.8）；**复杂实体半边（商品独立编辑页）也已落地并线上验收**（见 §5.9）；剩下两半边是简单实体 dialog 规范、表格内快捷改值。
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

### 5.7 第六轮：编辑载体取证（线上只读实测 + 编译产物双向确认）

起因：§1.3 第 7 条（详情＝1000px + 8 tabs、简单实体＝600px label-top 仅提交、复杂实体＝分步向导）当年只依据编译产物粗估就写进文档，属于**未经证实的断言**。本轮按"先线上补取证，再写代码"执行：由使用者在浏览器里手动登录对方后台（凭据不经过我、不落任何文件），我用 `evaluate_script` 做只读探测（点页签、量 DOM、抓 XHR/fetch），全程**不点保存、不写任何数据**。

#### 5.7.1 纠正的旧断言（详细证据见 §1.4）

1. **抽屉宽度成立、"8 tabs" 需限定**：实测订单抽屉 1000px、**7 个**页签；`tabList` 按数据动态过滤（`订单处理` 三选一、`供货明细` 仅 `supplier_id>0`），固定 8 tab 的是**用户详情抽屉**。
2. **商品编辑页不是分步向导**：线上无 `[class*=step]`、`collapseCount = 0`，点「下一步」`apiReqsDelta = 0` 且不校验；`保存` 才整表校验 + 一次提交 → 范式是「tab 分组 + 一次性提交」。桌面端 `label-position` 是 **right**（`label-width:110px`），不是 top。
3. **dialog 宽度没有统一值**：653 处里 600px 只占 37%，还有 500/700/800/900/1000/1200；「label-top + 仅提交」是服务端下发的 `FormSubmit` 动态表单专属，手写小 dialog 是 500px / 左 label / 取消+确定。§1.3 原来那条把两者混为一谈。
4. **`floating-btn` 是误标**：全库仅 2 处且是右下角悬浮客服/AI 按钮。快捷改值的真身在商品列表模块：`row.xxxEdit` 位 → `el-input(v-focus + v-only-number)` → `@blur/@change` → `POST {module}/modify {id,field,value}`，成功只取服务端 `msg`、**不整表刷新**。线上 `#/goods/list` 只读实测已确认：40 行里 `input` **0 个**、`el-icon-document-copy` 40 个（每行 1 个，在商品ID 列）、`el-icon-edit` 120 个（名称/定价成本/售价·库存各 1）、双段 `el-tag` 胶囊实测存在。
5. **新增/编辑同页只适用于商品**：`/goods/add` 与 `/goods/edit/:id` 共用一个模块；线上订单列表行操作实测只有「查看详情 / 删除」→ 订单不走编辑页，别把复杂实体范式推广到订单。

线上另量到商品各页签可见字段数：基本信息 16 / 库存管理 1 / 商品详情 1 / 注意事项 1 / 使用说明 1 / 商品配置 25 / 二级分佣 2；分栏 `el-col-6/12/24`。

#### 5.7.2 抽屉页签 → 接口 实测映射（逐个点开抓包）

| 页签 | 线上请求 | 我方现状（已 grep 确认） | 缺口 |
| --- | --- | --- | --- |
| 订单详情 | `GET /order/info?id&search_data` | `OrderController::info` → `{order, logs}` | 无 |
| 订单处理 | **零请求**（纯前端表单） | 同页已有 | 无 |
| 商品信息 | `GET /order/goods` | `OrderController::goods` → 卡密行 `id,card_no,card_password,status,create_time` | 无 |
| 订单日志 | `GET /order_log/index` | 模型 `OrderLog` 有；[OrderController.php:121](file:///H:/MKSHOP/mkshop/app/controller/admin/OrderController.php#L121) 只在 `info` 里内联返回全量 logs，**无分页路由** | 需新增分页列表（或先用内联 logs 渲染） |
| 订单佣金 | `GET /order_commission/index` | [CommissionController.php:22](file:///H:/MKSHOP/mkshop/app/controller/admin/CommissionController.php#L22) `commission/index` 已支持 `order_id` 过滤 | **无，可直接复用** |
| 佣金明细 | `POST /member/one_info` | `commission/index?uid=` 已按 `uid1/uid2` 过滤 | 可复用，语义需对齐 |
| 资金明细 | `GET /member_money/index`（列：用户ID/操作金额/交易类型/变化前金额/变化后金额/收支类型/创建时间/备注） | 模型 `MemberMoney`（含 `T_*`/`C_*` 枚举）+ `MemberMoneyService` 有，仅 `MoneyDayController` 用过，**无后台流水列表路由** | 需新增按 uid/订单的流水列表 |

#### 5.7.3 我方编辑载体现状盘点（`admin-web/src/views`）

| 载体 | 数量 | 明细 |
| --- | --- | --- |
| `el-drawer` 详情抽屉 | 3 个文件 | `order/orderList.vue`(560px)、`order/complainList.vue`(520px)、`merchant/merchantList.vue`(520px)、`setting/addonList.vue`(640px) |
| 有「详情」入口的页面 | 5 | member / merchant / complain / order / addon |
| `el-dialog` | 21 文件 / 60 处 | 宽度分布 420×1 460×4 480×3 500×1 520×3 540×1 560×8 620×2 640×2 680×2 720×1 760×1 **1000×1**（`vip/vipDetail`，已是 12 页签 dialogs 先例） |
| 商品新增/编辑 | dialog | `goods/goodsList.vue` 单个 680px、~24 个 `el-form-item` 堆在一屏 |

据此重排后续任务：**#6 详情抽屉**改为"1000px + 动态页签 + 页签级懒加载"（订单先做，顺带补 `order_log`/`member_money` 两个列表接口）；**#7 复杂实体独立页**范围收窄到**只有商品**；**#8 简单实体**按实测拆成「手写小 dialog：500–600px + 取消/确定」与「动态表单：label-top + 仅提交」两条规则，不再写死 600px；**#9 表格内快捷改值**按 `row.xxxEdit` + `POST {module}/modify {id,field,value}` 落地，覆盖商品/用户/卡密等列表页（订单/财务不铺，它们走抽屉）。

本轮仅取证与文档修订，未改动任何业务代码；上一轮的路由遮蔽修复与 `mks_m8d.sql` 已单独提交推送。

### 5.8 第七轮：详情抽屉载体落地（§5.3 第 2 项的「详情」半边）

按 §1.4 实测规格实现，只动载体、不再猜结构。

**新增组件** [admin-web/src/components/mksDetailDrawer.vue](file:///H:/MKSHOP/mkshop/admin-web/src/components/mksDetailDrawer.vue)

* `el-drawer` `size="1000px"` + `append-to-body`，**无底部按钮**（与两抽屉实测一致）。
* `#actions` 插槽＝页头操作按钮区（对方头部按钮位），`#top` 插槽＝页头主信息（单号 + 复制 + 关键指标 li）。
* `tabs` 由使用方按数据算出后传入，对应对方 `tabList` 的动态显隐；`visited` 集合 + `tab-load` 事件实现**页签首次激活才请求**，切走再切回不重复请求。
* 样式不 scoped：`.mks-dt-*` 需要作用到使用方插槽内容（页头 h3 左边框 3px 主色、60×60 图、指标行）。

**已接入 4 个页面**

| 页面 | 原载体 | 现载体页签 | 数据来源 |
| --- | --- | --- | --- |
| [order/orderList.vue](file:///H:/MKSHOP/mkshop/admin-web/src/views/order/orderList.vue) | 560px 抽屉，三段堆叠 | 订单详情 / 商品信息 / 订单日志 / 订单佣金 / 资金明细 | `order/info`·`order/goods` 已有；佣金 `commission/index?order_id`；资金 `member_money/index?order_id` |
| [order/complainList.vue](file:///H:/MKSHOP/mkshop/admin-web/src/views/order/complainList.vue) | 520px 抽屉 | 售后信息 / 关联订单 | `complain/read`（含 order） |
| [merchant/merchantList.vue](file:///H:/MKSHOP/mkshop/admin-web/src/views/merchant/merchantList.vue) | 520px 抽屉，三段堆叠 | 商户信息 / 货款钱包 / 结算汇总 | `merchant/read`（含 wallet/settlement），「货款流水」仍是原 640px 抽屉 |
| [member/memberList.vue](file:///H:/MKSHOP/mkshop/admin-web/src/views/member/memberList.vue) | 640px **dialog** | 用户信息 / 资金明细 / 佣金明细 / 购买记录 | `member/read` + `member_money/index?uid` + `commission/index?uid` + `order/index?where=[uid]` |

动态页签规则按实测落成两处：订单抽屉**无卡密不出「商品信息」**、**无 uid 不出「资金明细」**；订单处理类按钮（同步/补单/停补/处理成功/退款/复制卡密）从表格行搬进页头 `#actions`，显隐条件与行内按钮完全一致，退款仍走 `el-popconfirm`。

**后端**：新增 [MemberMoneyController::index](file:///H:/MKSHOP/mkshop/app/controller/admin/MemberMoneyController.php)（`uid` / `order_id` / `extend_id` / `trade_type` 过滤 + 分页，输出 `trade_type_text`·`change_type_text`，列名与对方「资金明细」页签逐项对齐）；[MemberMoney](file:///H:/MKSHOP/mkshop/app/common/model/MemberMoney.php) 补 `TRADE_TYPE_TEXT`；[route/admin.php](file:///H:/MKSHOP/mkshop/route/admin.php) 注册 `GET member_money/index`——权限点早在 [deploy/sql/mks_seed.sql:554](file:///H:/MKSHOP/mkshop/deploy/sql/mks_seed.sql#L554) 就有（id 336「资金明细」`api_url = member_money/index`、`methods = GET`），无需补菜单 SQL。

**部署**：`php -l` ×3（`MemberMoneyController` / `MemberMoney` / `route/admin.php`）全部 No syntax errors；产物 staged `js=46 css=40` → 原子切换（备份 `public/admin-web.bak-20261114012935`）→ `php think clear` → 冒烟 `index.html 200` / 入口 chunk 200 / 无 token 打 `member_money/index` 得 401（中间件先拦截，路由存在）。验收中发现 2 条 `el-link underline` 布尔值弃用告警，已改 `underline="never"` 并二次切换（`admin-web.bak-20261114014010`，入口 `index-M6MAhHPx.js`）。

**浏览器实测（登录态，只读）**

| 项 | 订单页 | 用户页 |
| --- | --- | --- |
| 抽屉宽度 | `style.width: 1000px` / `rectW 1000` | 同（1000） |
| 页签 | 订单详情·商品信息·订单日志·订单佣金·资金明细（动态：无卡密/无 uid 自动少一项） | 用户信息·资金明细·佣金明细·购买记录 |
| 打开时请求数 | 2（`order/info`、`order/goods`） | 1（`member/read`，首个页签不额外发请求） |
| 懒加载页签 | 订单佣金 1 次、资金明细 1 次（`?order_id=6` 200） | 资金明细 1 次（`?uid=10022` → **9 行真实流水**）、佣金 1 次、购买记录 1 次（5 行） |
| 重复切回同一页签 | 追加请求 **0** | 追加请求 **0** |
| 底部按钮 | **0** | **0** |
| 页头操作 | 按状态显隐正确：该单「已经退款」→ 只剩「复制卡密」 | — |
| 复制 | 单号旁 `file-copy-line` 图标存在，点击提示「复制成功」 | — |
| 控制台 | 修复后无 error、无 warn | 同 |

权限说明：当前登录身份是超管，`AdminAuth` 在 `isSuper` 处短路，所以这轮 200 **只证明路由与中间件放行链路通**，不证明非超管角色的登记表校验。非超管要靠 `admin_router` 那行授权，seed 里 id 336（`member_money/index`、GET、auth_type=2）已就位；线上菜单管理页因接口限 200 条未展示到该行，属**待补证据**。

**运行时未覆盖的两页**：`#/order/complain` 与 `#/member/merchant` 库里都是 **0 条记录**，抽屉没有数据可打开，只完成了「页面正常加载、组件无报错、构建产物含各自 chunk」。为验收去造售后/商户数据属于写生产库，未做。

### 5.9 第八轮：商品新增/编辑落成独立页（§5.3 第 2 项的「复杂实体」半边）

按 §1.4 实测的 `goodsAdd` 结构实现，替换商品列表里那个 680px 的 `el-dialog` 大杂烩。

**载体** [admin-web/src/views/goods/goodsList/goodsAdd.vue](file:///H:/MKSHOP/mkshop/admin-web/src/views/goods/goodsList/goodsAdd.vue)（新增/编辑同一组件）

* 路由靠菜单表已有的两行：id 10「添加商品」`path=add`、id 11「编辑商品」`path=edit/:id`，`component` 都是 `@/views/goods/goodsList/goodsAdd`，`hidden=1` + `activeMenu=/goods/list` + `dynamicNewTab=1`——线上库里早就存在，无需补菜单 SQL。
* 页签用 `el-tabs` 只做导航，内容全部 `v-show` 常驻：这样「保存」一次 `validate()` 能覆盖 6 个页签的字段，校验失败按 `tabOfProp` 跳回出错字段所在页签并提示「请完善数据」（对方原文）。
* 「上一步/下一步」只切 `activeTab`，实测口径是**零请求、零校验**；底部三按钮居中，`上一步` 在首页签用 `visibility:hidden` 占位（保持按钮位置不跳）。
* 页签分组（线上实测可见字段数）：基本信息 16 / 库存管理 5 / 商品详情 1 / 注意事项 1 / 使用说明 4 / 商品配置 12，合计 39；`label-width="110px"`、右对齐。

**字段口径（这轮最重要的一条约束）**：先做了一次「列消费普查」——`mks_goods` 171 列里只有约 49 列真被后端读，其余是历史遗留。表单只放**有人读**的列，白名单里躺着但没人读的列（`only_balance`、`is_batch`、`is_recharge`、`goods_tags`、`check_content`、`stop_reason`、`demo_url`、`can_level`、`can_no_buy`、`take_up_time`）一律不放——放上去就是一个存了也不生效的开关，比缺字段更坏。反过来补进了 4 个真被读的列：`is_rebate`（佣金开关）、`docking_order_bu`/`order_bu_count`/`auto_order_time`（自动补单扫描条件）。两处只做前台显示、后端未接入的（`can_back` 只进订单快照、`goods_name_color`/`blod_flag` 只作用于后台列表）在页面上直接写明，不假装已生效。
`recharge_time_type` 的单位口径按 [front-web/src/views/Goods.vue:17](file:///H:/MKSHOP/mkshop/front-web/src/views/Goods.vue#L17) 取：`1=分钟 2=小时 3=天`，不是猜的。

**图片上传** 新增 [mksPicUploader.vue](file:///H:/MKSHOP/mkshop/admin-web/src/components/mksPicUploader.vue)：数组模型、`:max` 限张数、可左移调序（轮播第一张即封面）、「网络图片」入口带地址正则校验、尺寸提示。直接打现成的 `POST file/upload`（权限点 id 388 已登记），返回 `data.url`；主图 max 1、轮播 max 5 + 「建议尺寸：800 * 800px，最多上传 5 张」。

**后端** [GoodsController](file:///H:/MKSHOP/mkshop/app/controller/admin/GoodsController.php)：`crudFields` 补上述 4 列；`read()` 覆写，附带 `stock_bind`（`goods_stock` type=1 的卡库名/余量/成本），库存页签才能回显当前绑定。「库存管理」页签的绑定/换绑走 `POST goods/save_goods_stock`，成功后重新 `read()` 刷新余量——卡库余量与 `goods.stock_num` 的同步逻辑后端本来就有，前端不自己算。

**发现的 RBAC 缺口**：`AdminAuth` 默认拒绝（接口未在 `admin_router` 登记即 403），而商品权限点里有 `goods/save`(更新)、`goods/read`、`goods/delete`… 唯独没有 `goods/create`，新增商品走的正是它。超管短路看不出来，普通角色点保存必然 403 → 商品根本加不出来。已写成幂等迁移 [deploy/sql/mks_m8e.sql](file:///H:/MKSHOP/mkshop/deploy/sql/mks_m8e.sql)（parentId=9、auth_type=2、`goods/create` POST，按 api_url 去重）。登记≠授权：非超管角色仍需在「角色管理」勾选该项，与其它新增权限点同口径。

**部署与线上验收（2026-11-14，mksup.top，超管账号）**

* 迁移：`mks_m8e.sql` 已执行（执行前 `mks_admin_router` 整表 dump 到 `/root/mks_admin_router.bak-20261114101139.sql`）；新增行 id 662，goods 权限点 27 → 28。
* 产物：前端做了三次原子切换，最后一次备份 `admin-web.bak-20261114104456`，线上入口 `assets/index-BUSuKXSZ.js`；`GoodsController.php` + `mks/util/Upload.php` `php -l` 均通过；冒烟 3 个 200，无 token 打 `goods/create` 得 401（不是 403，说明登记生效、鉴权在下一层）。
* 导航口径实测：进 `/goods/add` 发 7 个枚举请求；`下一步` ×5 每次 **0 请求**；`上一步` 仅首页签 `visibility:hidden`；6 个页签标签按序为 基本信息/库存管理/商品详情/注意事项/使用说明/商品配置。
* 校验跳页签实测：停在「商品配置」、清空商品名称点保存 → toast「请完善数据」+ 行内「请填写商品名称」+ 自动跳回「基本信息」，全程 **0 个写请求**。
* 上传落盘实测：`POST file/upload` 200，`data.url = /uploads/goods/20261114/0e98c1….png`，主图缩略图 `naturalWidth=1`（真取到文件），达到 `max=1` 后上传框收起。这里挖出一个**线上缺陷**：`Upload::save()` 原来在 `$file->move()` 之后才 `getSize()`，临时文件已被搬走 → RuntimeException → HTTP 500，文件落盘但接口失败（留下孤儿文件）。已把 size 取值挪到 move 之前并部署。
* 编辑回显实测：`GET goods/read/21` 200；名称/编号/成本 60.00000/售价 100.0000 一致；`stock_bind` 渲染为「当前卡库 验收卡库A / 卡库余量 0 / 卡库成本 60.00000」；`tags_id`/`tpl_id` 为 0 时转 `null`，下拉显示占位符（「请选择」/「不使用模板」）而不是裸值 0。
* 写往返实测（只用自造测试商品）：`create` 200「创建成功」→ 列表出现 id 39 `G202611144801` → `delete` 200「已移入回收站」→ 回收站 `destroy` 200「已彻底删除」→ 0 行 → 该行随后物理清除。真实商品 21 全程未改动。
* 隐藏页高亮实测：布局层改用 `activePathOf(route, menuPathMap)` 后，在 `/goods/add` 上「商品」渠道与「商品列表」同时高亮，面包屑 商品 > 添加商品，console 无报错。
* 本轮修掉的两个真缺陷：① `tabIndex` 写成 `t.name === activeTab`（漏 `.value`，比较恒不相等 → 求值恒 -1，`下一步` 不前进、`上一步` 永远隐藏）；② 上面那个 `getSize()` 时机。另外 `PHPStan` 式的"响应 200 但 code=1"（如对不存在的 id 调 `goods/read` 返回「商品不存在」）经核实是全站既有的业务失败约定，前端拦截器据此弹错并 reject，**不是缺陷**，不改。
* 造数据清理：测试商品、4 个 1×1 测试 PNG（含失败上传留下的孤儿文件）、1 行 `mks_file` 已全部删除，`public/uploads/goods/20261114/` 已空；`runtime/log/202611/14.log` 属主已回到 `82:82`（此前被我用 root `docker exec` 跑 CLI 时改成 root，导致 web 进程写不进日志、异常"静默"）。
* 量测注意（下次别再误判）：浏览器标签页在后台时 `requestAnimationFrame` 不触发，`<transition>` 卡在 `fade-transform-leave-*`，新页看起来"没渲染"——这是量测环境，不是代码；验收必须在可见标签页里做。`el-upload` 的 `input[type=file]` 是 `display:none`，自动化点不到它，要用 `new DataTransfer(); dt.items.add(new File(...)); input.files=dt.files;` 再派发 `change`，走组件自身的 `http-request`；`visibility:hidden` 的元素 `innerText` 为空，判断存在性要用 `textContent`。
* 仍未验证：非超管角色对 `goods/create` 的授权效果（超管短路 `isSuper`，在这个账号上测不出来）；`save_goods_stock` 的写入侧（只验了读/回显，不写是为了不动真实商品的卡库绑定）。

本地 `vite build` 通过（最新一轮 `goodsAdd-DgVyoO22.js` 22.74 kB / `index-C3GrhNHN.js`，与线上入口仅差一行被 `tags_id`/`tpl_id` 兜底覆盖的死赋值）。本轮改动尚未提交，推送需另行确认。






