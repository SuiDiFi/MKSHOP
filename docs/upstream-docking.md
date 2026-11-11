# 上游供货渠道对接（M3 规格 · 来自 KASUSHOU 源码提取 + 官方 API 文档）

> 数据来源：`config/kss.php` docking_list、`_deobf/route/dockapi.php`、`_deobf/app/common/logic/OrderLogic.php`、`_analysis/04-核心业务逻辑.md §5-7`
> **协议权威规格：`docs/kasushou-v2api/`（官方文档 35 页抓取存档，索引见 KASUSHOU-V2API-INDEX.md）**
> 卡速售 2.0 API 是卡密供货行业的事实标准协议——绝大多数上游渠道（同行业系统）均兼容此协议，MKSHOP 的上游适配器以它为第一适配目标。

## 0. 官方协议核心（v2api，M3 适配器必须实现）

- **签名**：`sign = sha1(13位毫秒时间戳 + ksort(body)的JSON(UNESCAPED_SLASHES|UNESCAPED_UNICODE) + apikey)`；body 为空时用 `{}` 参与签名；Header 三件套：`Sign` / `Timestamp` / `UserId`（appid）
- **异步下单模型**：`POST /api/v1/order/buy` 下单成功 ≠ 充值成功；必须实现「订单详情轮询（间隔 1~3s）」+「异步回调」双通道取卡；`card_list` 仅卡密订单返回
- **上游订单状态**：1等待处理 / 2正在处理 / 3交易成功 / 4取消交易 / 5已退款 / -1未支付 → 需要做「上游状态 ↔ MKSHOP 内部 order_status」映射表
- **safe_price 安全价**：下单可传安全价（不能小于总售价），上游调价时拒单防亏本——适配器必须支持
- **attach 下单模板参数**：直充类商品的动态参数（recharge_account、lblName1…），对应 `mks_order_tpl` 模板体系
- **双向角色**：同一套协议既是「用户接口」（我们调上游）也是「商家/供货接口」（上游调用我们）——MKSHOP 自身的 `/dockapi` 须实现供货侧（商家订单列表/处理、售后列表/处理、获取订单/处理订单）
- **回调验签**：回调地址按原样参与签名，我们的回调接收端必须验签 + 幂等

## 1. 对接双通道

| 方向 | 入口 | 说明 |
| --- | --- | --- |
| **我们 → 上游** | `Docking` 表配置 + 适配器 | 下单 / 查单 / 退款 / 库存价格同步 |
| **上游 → 我们** | `/dockapi/*`（9 条路由，签名 + 限流 KssThrottle） | 上游主动推送商品、订单状态回调 |

## 2. dockapi 路由（上游 → 我们，重写须协议兼容）

| # | 接口 | 说明 |
| --- | --- | --- |
| 1 | 统一下单 | 上游在 MKSHOP 下单（分销反向场景） |
| 2 | 商户信息查询 | 上游查询合作商户余额/状态 |
| 3 | 查询订单 | 上游查询订单状态 |
| 4/5 | 旧版商品列表 / 详情 | 兼容旧协议 |
| 6/7/8 | 新版商品列表 / 详情 / 分类 | v2 协议 |
| 9 | 测试回调 | 联调用 |

CORS：dockapi 组单独处理。

## 3. 我们 → 上游协议要点

- 渠道配置存 `docking` 表：接口地址 / 密钥（**加密存储**，AES-256-GCM）/ 自动补单策略 `syncConfig`
- 关键调用时机：
  - 下单：`doOrder()` 按库存类型选上游 API 下单，失败换库存重试或退款
  - 查单/补单：`auto_bu_count` 自动补单计数；异常单主动查单补偿
  - 退款/售后：`order_complaint` 通知上游撤单
  - 库存价格同步：`SyncGoodsStockJob` 队列异步同步，成本变化触发 `DockingLogic::stopSync`
- 字段映射：上游商品 → 本地 `goods_stock`（`docking_id` + `docking_goods_id` 绑定，`mkshop_id` 店铺/商品标识）
- 回写：上游返回 `card_list` 写入订单 `docking_id/docking_goods_id`

## 4. 渠道清单

44 家上游见 `config/kss.php` 的 `docking_list`（key + 中文名，未含 URL）。
重写策略：**通用适配器 + 渠道参数化配置**（接口地址/签名方式全部进 `docking` 表），首期接入 3~5 家。

## 5. M3 设计要点

1. 适配器接口：`DockerInterface { order(), query(), refund(), syncGoods(), syncStock() }`
2. 保留原始报文（`docking_log`），失败可人工重放
3. 金额对账：每日与上游余额对账，差异告警
4. 上游乱序防护：状态机 CAS + 幂等键（`ordersn` + 上游单号唯一索引）
5. 签名：与原 dockapi 协议兼容（旧上游客户可平滑切换），但**修正**原系统签名密钥硬编码问题（密钥走 `.env` MKS.SIGN_KEY）
