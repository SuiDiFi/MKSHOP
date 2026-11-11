# MKSHOP

虚拟商品电商系统（卡密 / 直充 / 代充 + 上游供货 + 两级分销 + 多端），基于 KASUSHOU 逆向分析报告全功能对齐自研重构。

## 技术栈

| 层 | 选型 |
| --- | --- |
| 后端 | PHP 8.1+ / ThinkPHP 6.1（多应用：admin / front / h5 / api / dockapi / install） |
| 数据 | MySQL 8.0（utf8mb4，表前缀 `mks_`）+ Redis 7 |
| 运行时 | PHP-FPM + Supervisor（队列）+ Swoole（WebSocket 客服，M5 接入） |
| 管理后台 | Vue3 + Element Plus + Vite（`admin-web/`，M0 后期初始化） |
| PC 商城 | Vue3 + Element Plus + Vite（`front-web/`，M5） |
| 移动端 | Taro（React）→ H5 + 微信小程序（`mobile/`，M5） |

## 快速开始（本地开发）

```powershell
cd mkshop
docker compose -f docker-compose.dev.yml up -d      # MySQL 8 + Redis 7 + PHP 8.2 + Nginx
docker compose -f docker-compose.dev.yml exec php composer install
# 建库（容器内）
docker compose -f docker-compose.dev.yml exec mysql mysql -uroot -proot mkshop < deploy/sql/mks_schema.sql
docker compose -f docker-compose.dev.yml exec mysql mysql -uroot -proot mkshop < deploy/sql/mks_seed.sql
# 访问 http://localhost:8080
```

## 目录结构

```
mkshop/
├── app/            # TP6 应用（控制器/逻辑/模型/任务）
├── mks/            # 自研框架基座（CurdTrait/Form/Upload/服务层）
├── config/ route/
├── public/         # Web 入口 + 静态产物
├── admin-web/      # 管理后台前端（Vue3）
├── front-web/      # PC 商城前端（Vue3）
├── mobile/         # Taro 移动端（H5 + 小程序）
├── deploy/         # 部署：SQL / nginx / supervisor / docker
├── tools/          # 开发工具脚本
└── docs/           # 开发文档（分支规范、上游对接、业务规格索引）
```

## 开发红线（架构约束）

1. 卡密下单即预占（Redis 锁 + DB 行锁），履约失败释放，杜绝超卖
2. 支付幂等：`ordersn + trade_no` 唯一索引 + 状态机 CAS
3. 卡密 AES-256-GCM 加密存储
4. RBAC 默认拒绝，方法级校验，超管用角色标记
5. 队列失败落库 + 重放
6. 金额统一 `decimal(16,4)`（成本 16,5）+ bcmath + HALF_UP
7. 认证统一中间件，标准 JWT，前端不持签名密钥
8. 上传白名单收紧（禁 svg/pem/key/zip/kss 直传）
9. 软删统一 `delete_time`，唯一索引一律带 `delete_time`
10. 枚举走 `sys_dict` 字典表，前后端文案统一取字典

## 文档索引

- [分支与发布流程](docs/BRANCHING.md)
- [上游供货对接](docs/upstream-docking.md)
- 业务规格（只读参考）：`../_analysis/00~07` 系列分析文档
