<?php
if(!defined('IN_CRONLITE'))exit();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>老司机支付</title>
  <meta name="keywords" content="<?php echo $conf['keywords']?>">
  <meta name="description" content="<?php echo $conf['description']?>">
  <link rel="stylesheet" href="<?php echo STATIC_ROOT?>css/style.css">
</head>
<body>
  <div class="gal-shell">
    <div class="gal-window" role="region" aria-label="老司机支付 Galgame 风格界面">
      <div class="gal-titlebar">
        <div class="titlebar-left">
          <span class="title-dot dot-red"></span>
          <span class="title-dot dot-yellow"></span>
          <span class="title-dot dot-green"></span>
          <span class="title-text">老司机支付 / GALGAME EDITION</span>
        </div>
        <div class="titlebar-right">
          <span class="badge-secure">SECURE MODE</span>
        </div>
      </div>
      <div class="gal-content">
        <div class="gal-bg">
          <div class="bg-glow"></div>
          <div class="bg-grid"></div>
          <div class="bg-character"></div>
        </div>
        <canvas id="petals" class="gal-petals" aria-hidden="true"></canvas>
        <div class="gal-ui">
          <div class="gal-top">
            <div class="date-card">
              <div class="date-label" id="js-date">--/--/--</div>
              <div class="date-sub">Episode <span id="js-daycount">0</span></div>
            </div>
            <div class="brand">
              <div class="brand-kicker">GALGAME PAYMENT GATEWAY</div>
              <h1 class="brand-title">老司机支付</h1>
              <p class="brand-sub">稳定 · 安全 · 轻量接入</p>
            </div>
          </div>

          <div class="gal-mid">
            <div class="gal-actions" aria-label="快捷入口">
              <a class="gal-btn primary" href="/user/">商户登录</a>
              <a class="gal-btn" href="/user/reg.php">立即接入</a>
              <?php if($conf['test_open']){?><a class="gal-btn" href="/user/test.php" rel="nofollow">体验DEMO</a><?php }?>
              <a class="gal-btn" href="/doc.html" rel="nofollow">API文档</a>
              <a class="gal-btn ghost" href="https://wpa.qq.com/msgrd?v=3&uin=<?php echo $conf['kfqq']?>&site=pay&menu=yes" rel="nofollow noopener noreferrer" target="_blank">联系管理员</a>
            </div>
            <div class="gal-stats" aria-label="平台指标">
              <div class="stat">
                <span class="stat-label">结算费率</span>
                <span class="stat-value"><?php echo $conf['settle_rate']?>%</span>
              </div>
              <div class="stat">
                <span class="stat-label">结算门槛</span>
                <span class="stat-value"><?php echo $conf['settle_money']?>元</span>
              </div>
              <div class="stat">
                <span class="stat-label">客服QQ</span>
                <span class="stat-value"><?php echo $conf['kfqq']?></span>
              </div>
            </div>
          </div>

          <div class="gal-dialog">
            <div class="dialog-name">看板娘</div>
            <button class="dialog-box" id="dialog-box" type="button">
              <p class="dialog-text" id="dialog-text">欢迎来到 老司机支付，点击继续。</p>
              <span class="dialog-hint">点击继续 ▾</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <footer class="gal-footer">
      <div class="footer-line">Copyright © <?php echo date("Y")?> 老司机支付 All Rights Reserved.</div>
      <div class="footer-note"><?php echo $conf['footer']?></div>
    </footer>
  </div>

  <script src="<?php echo STATIC_ROOT?>js/main.js"></script>
</body>
</html>
