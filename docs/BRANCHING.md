# MKSHOP 分支策略与发布流程

## 分支模型（GitHub Flow + 环境分支）

```
main ───────────────────────────────────────► 生产（tag v* → GitHub Release）
  │   ▲                                  ▲
  │   │ PR（release/hotfix 合入）          │
develop ──────► 集成分支（功能汇合、联调）
  │  ▲
  │  │ PR
  └── feature/m0-infra, feature/m1-goods ... 功能分支
```

| 分支 | 用途 | 保护规则 |
| --- | --- | --- |
| `main` | 生产代码，永远可部署 | **禁止直推**；只接受 PR（squash merge）；打 tag 触发发布 |
| `develop` | 日常集成，下一个版本的开发线 | 只接受 PR；可直推冲突自动合并除外 |
| `feature/<里程碑>-<模块>` | 功能开发（如 `feature/m2-payment`） | 从 `develop` 切出，PR 回 `develop` |
| `release/vX.Y.Z` | 发布准备（冻结功能、只修 bug） | 从 `develop` 切出，PR 同时合入 `main` 与 `develop` |
| `hotfix/<问题描述>` | 生产紧急修复 | 从 `main` 切出，PR 回 `main` + `develop` |

## 提交规范（Conventional Commits）

```
feat(订单): 新增下单预占逻辑        # 新功能
fix(支付): 回调重复验签失败         # 缺陷修复
chore(部署): 更新 docker 镜像版本   # 杂项
docs(对接): 上游协议规格补充        # 文档
refactor(卡密): 预占改行锁实现      # 重构（不改行为）
```

- 提交信息用「类型(模块): 描述」格式，中文描述
- 每个 PR 必须关联里程碑任务，描述含「改动点 + 验证方式」

## 发布流程（变更发布）

1. 功能开发：`feature/*` → PR → `develop`（自测通过）
2. 发版：从 `develop` 切 `release/vX.Y.Z`，冻结新功能、回归测试
3. 发布 PR：`release/vX.Y.Z` → `main`，评审通过后合并
4. 打 tag：`git tag vX.Y.Z && git push origin vX.Y.Z`
5. GitHub Release：基于 tag 生成变更说明（changelog），附部署 SQL 变更（如有）
6. 回流：`release/vX.Y.Z` 合回 `develop`，删除 feature/release 分支
7. 服务器部署：`main` 拉取对应 tag 部署（154.219.97.238，配置走环境变量不入库）

## 版本号

语义化 `vMAJOR.MINOR.PATCH`；里程碑首个版本 `v0.1.0`（M0 基础设施）。
