(() => {
  const dateEl = document.getElementById("js-date");
  const dayCountEl = document.getElementById("js-daycount");
  const dialogBox = document.getElementById("dialog-box");
  const dialogText = document.getElementById("dialog-text");

  const dialogues = [
    "欢迎回来，今天也想用更顺滑的支付体验吗？",
    "这里是 老司机支付：多通道接入、低成本维护、稳定出单。",
    "请在「API文档」查看快速接入方式，一键就绪。",
    "需要帮助的话，点击「联系管理员」，我会在最短时间回应你。",
    "准备好了就出发吧，祝你每一笔交易都顺利到账！"
  ];

  let dialogIndex = 0;
  let typing = false;
  let typingTimer = null;

  const typeLine = (text) => {
    if (!dialogText) return;
    typing = true;
    dialogText.textContent = "";
    let i = 0;
    clearInterval(typingTimer);
    typingTimer = setInterval(() => {
      if (i < text.length) {
        dialogText.textContent += text.charAt(i);
        i += 1;
      } else {
        clearInterval(typingTimer);
        typing = false;
      }
    }, 42);
  };

  const nextDialogue = () => {
    if (typing) {
      clearInterval(typingTimer);
      dialogText.textContent = dialogues[dialogIndex];
      typing = false;
      return;
    }
    dialogIndex = (dialogIndex + 1) % dialogues.length;
    typeLine(dialogues[dialogIndex]);
  };

  if (dialogBox) {
    dialogBox.addEventListener("click", nextDialogue);
  }

  const initDate = () => {
    if (!dateEl || !dayCountEl) return;
    const now = new Date();
    const dateString = now.toLocaleDateString("zh-CN", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit"
    });
    const base = new Date("2024-01-01T00:00:00");
    const dayCount = Math.max(
      1,
      Math.floor((now - base) / (1000 * 60 * 60 * 24)) + 1
    );
    dateEl.textContent = dateString;
    dayCountEl.textContent = String(dayCount);
  };

  const initPetals = () => {
    const canvas = document.getElementById("petals");
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    let width = 0;
    let height = 0;
    let petals = [];

    const resize = () => {
      const rect = canvas.parentElement.getBoundingClientRect();
      width = Math.max(1, Math.floor(rect.width));
      height = Math.max(1, Math.floor(rect.height));
      canvas.width = width;
      canvas.height = height;
    };

    class Petal {
      constructor() {
        this.reset();
        this.y = Math.random() * height - height;
      }

      reset() {
        this.x = Math.random() * width;
        this.y = -20;
        this.size = Math.random() * 10 + 6;
        this.speed = Math.random() * 1.2 + 0.6;
        this.angle = Math.random() * Math.PI * 2;
        this.spin = Math.random() * 0.02 - 0.01;
        this.opacity = Math.random() * 0.35 + 0.35;
      }

      update() {
        this.y += this.speed;
        this.x += Math.sin(this.angle) * 0.6;
        this.angle += this.spin;
        if (this.y > height + 20) {
          this.reset();
        }
      }

      draw(context) {
        context.save();
        context.translate(this.x, this.y);
        context.rotate(this.angle);
        context.globalAlpha = this.opacity;
        context.beginPath();
        context.moveTo(0, 0);
        context.bezierCurveTo(6, -6, 14, 0, 0, 16);
        context.bezierCurveTo(-14, 0, -6, -6, 0, 0);
        context.fillStyle = "#ff9db8";
        context.fill();
        context.restore();
      }
    }

    const seedPetals = () => {
      const count = Math.min(48, Math.floor(width / 18));
      petals = Array.from({ length: count }, () => new Petal());
    };

    const animate = () => {
      ctx.clearRect(0, 0, width, height);
      petals.forEach((petal) => {
        petal.update();
        petal.draw(ctx);
      });
      window.requestAnimationFrame(animate);
    };

    resize();
    seedPetals();
    window.addEventListener("resize", () => {
      resize();
      seedPetals();
    });
    animate();
  };

  initDate();
  typeLine(dialogues[0]);
  initPetals();
})();
