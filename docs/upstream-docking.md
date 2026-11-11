# 上游供货渠道对接（M3 规格 · 来自 KASUSHOU 源码提取）

> 数据来源：`config/kss.php` docking_list、`_deobf/route/dockapi.php`、`_deobf/app/common/logic/OrderLogic.php`、`_analysis/04-核心业务逻辑.md §5-7`

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
