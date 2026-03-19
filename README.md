# 老司机支付系统

> Fork: [maajiko/Epay](https://github.com/maajiko/Epay) → [RusianHu/LsjEpay](https://github.com/RusianHu/LsjEpay)

**基于彩虹易支付系统** 一款开源的免签约支付产品，能够帮助开发者一站式接入支付宝、微信、财付通、QQ钱包等多种支付方式，实现高效的支付集成。

---

## 从 GitHub 部署到运行

### 1. 获取代码

```bash
git clone https://github.com/RusianHu/LsjEpay.git
cd LsjEpay
```

### 2. 准备运行环境

- PHP >= 7.4（推荐 8.2+） 启用扩展：`curl`, `fileinfo`, `gd`, `mbstring`, `mysqli`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sqlite3`
- MySQL >= 5.6

### 3. 配置数据库

> [!IMPORTANT]
> 项目运行时 **实际读取的是根目录 `config.php`**，**不会自动读取 `config.php.example`**。
> `config.php.example` 只是数据库配置模板，需要复制为 `config.php` 后再使用。

方式 A（推荐）：启动服务后访问安装向导填写数据库信息。

- 安装入口：`http://localhost:8000/install/`
- 如果项目根目录有写入权限，安装器会自动生成 `config.php`

方式 B（手动）：先复制模板，再编辑根目录 `config.php`。

```powershell
Copy-Item .\config.php.example .\config.php
```

`config.php` 中需要正确填写以下字段：

- `host`：数据库地址
- `port`：数据库端口，默认一般为 `3306`
- `user`：数据库用户名
- `pwd`：数据库密码
- `dbname`：数据库名称
- `dbqz`：数据表前缀，默认是 `pay`

常见问题：

- **只保留 `config.php.example`，没有创建 `config.php`**：程序仍然会报数据库配置相关错误
- **数据库用户名、密码、数据库名为空**：安装器不会继续安装
- **项目根目录不可写**：安装器可能无法保存配置文件，需要手动创建 `config.php`
- **修改了表前缀**：请确保安装和后续使用时保持一致，否则可能出现数据表不存在的问题

### 4. 启动服务

```powershell
.\php\php.exe -S localhost:8000 -t .
```

### 5. 安装/升级

- 新装：访问 `http://localhost:8000/install/` 并完成安装。
- 升级：访问 `http://localhost:8000/install/update.php`。
- 安装完成后确保 `install/install.lock` 存在（用于防止重复安装）。

### 6. Nginx 伪静态（可选但推荐）

不配置会导致短路径（如 `/pay/...`、`/api/...`、`/doc/...`、`/xxx.html`）404，只能用 `index.php?mod=...` 等原始参数地址访问。  
将 `nginx.txt` 的内容加入对应站点的 `server` 配置即可。

---

## 功能特色

- **多渠道支付集成**：支持支付宝、微信、财付通、QQ钱包、微信WAP、银联等多种支付方式  
- **便捷的支付解决方案**：简化支付流程，支持快速集成和上线，提供完整的 API 接口  
- **后台管理和数据统计**：提供支付统计、代付统计、利润分析等多种后台管理功能  
- **安全可靠**：采用 RSA 公私钥验证，支持风控检测和黑名单管理  
- **插件扩展**：支持丰富的支付插件，可根据需求灵活扩展  
- **移动端优化**：全新的手机版支付页面，支持各种移动端支付场景  

---

## 更新日志

### 2025/12/30
1. 新增 `index11` 前台模板 UI
2. 新增 `jfyui`（缴费易v1）支付插件：支持支付宝/微信，含计划任务查单脚本
3. `.gitignore` 新增忽略 `/doc`

### 2025/11/10
1. 后台新增转账付款统计  
2. 付款页面新增最近付款人按钮  
3. 支持开启分账失败的订单 24 小时后重试  
4. 支付通道支持设置开放时间段  
5. 新增订单小票打印功能  
6. API 接口增加参数，可限制买家身份证号 / 姓名 / 最小年龄（仅支持支付宝官方接口）

### 2025/10/23
1. 修复微信收付通合单支付确认结算  
2. 修复轮询情况下订单偶尔支付通道错乱的问题  
3. 未支付订单清理时间调整为 48 小时  

### 2025/09/27
1. 微信小程序支付前支持获取手机号码  
2. 投诉单兼容关联多个订单的情况  
3. 部分支付插件增加关闭订单接口  

---

## 推荐插件

推荐使用 **Bepusdt** 插件进行 USDT（TRC20）收款。  
Bepusdt 是适用于彩虹易支付系统的 USDT 收款插件，收到的货币直接转入商户钱包，不经过任何第三方。

**插件开源地址**：  
🔗 [https://github.com/v03413/bepusdt](https://github.com/v03413/bepusdt)

---
