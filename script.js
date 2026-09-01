(() => {
  "use strict";

  const canvas = document.querySelector("#game");
  const ctx = canvas.getContext("2d");
  const overlay = document.querySelector("#overlay");
  const menu = document.querySelector("#menu");
  const actionButton = document.querySelector("#actionButton");
  const overlayLabel = document.querySelector("#overlayLabel");
  const overlayTitle = document.querySelector("#overlayTitle");
  const hint = document.querySelector("#hint");
  const scoreElement = document.querySelector("#score");
  const highScoreElement = document.querySelector("#highScore");
  const soundButton = document.querySelector("#soundButton");

  const MODES = {
    classic: { columns: 20, rows: 15, interval: 165 },
    fast: { columns: 20, rows: 15, interval: 82 },
    impossible: { columns: 15, rows: 10, interval: 55 },
    daily: { columns: 20, rows: 15, interval: 165 }
  };
  const VECTORS = {
    UP: { x: 0, y: -1 }, DOWN: { x: 0, y: 1 },
    LEFT: { x: -1, y: 0 }, RIGHT: { x: 1, y: 0 }
  };
  const OPPOSITE = { UP: "DOWN", DOWN: "UP", LEFT: "RIGHT", RIGHT: "LEFT" };
  const KEYS = {
    ArrowUp: "UP", ArrowDown: "DOWN", ArrowLeft: "LEFT", ArrowRight: "RIGHT",
    w: "UP", W: "UP", s: "DOWN", S: "DOWN", a: "LEFT", A: "LEFT", d: "RIGHT", D: "RIGHT"
  };

  let mode = "classic";
  let state = "menu";
  let snake = [];
  let food = { x: 0, y: 0 };
  let direction = "RIGHT";
  let queue = [];
  let score = 0;
  let highScore = Number(localStorage.getItem("406snake-high-score") || 0);
  let lastTime = 0;
  let accumulator = 0;
  let soundEnabled = false;
  let audioContext = null;
  let random = Math.random;

  highScoreElement.textContent = formatScore(highScore);

  function formatScore(value) {
    return String(value).padStart(4, "0");
  }

  function seededRandom(seed) {
    let value = seed >>> 0;
    return () => {
      value += 0x6D2B79F5;
      let t = value;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  function dailySeed() {
    const now = new Date();
    return Number(`${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, "0")}${String(now.getDate()).padStart(2, "0")}`);
  }

  function start(selectedMode = mode) {
    mode = selectedMode;
    const board = MODES[mode];
    const centerX = Math.floor(board.columns / 2);
    const centerY = Math.floor(board.rows / 2);
    snake = [0, 1, 2].map(offset => ({ x: centerX - offset, y: centerY }));
    direction = "RIGHT";
    queue = [];
    score = 0;
    accumulator = 0;
    random = mode === "daily" ? seededRandom(dailySeed()) : Math.random;
    food = placeFood();
    state = "playing";
    scoreElement.textContent = formatScore(score);
    overlay.classList.add("hidden");
    beep(440, .04);
    draw();
  }

  function placeFood() {
    const board = MODES[mode];
    const available = [];
    for (let y = 0; y < board.rows; y++) {
      for (let x = 0; x < board.columns; x++) {
        if (!snake.some(part => part.x === x && part.y === y)) available.push({ x, y });
      }
    }
    return available[Math.floor(random() * available.length)] || { x: 0, y: 0 };
  }

  function enqueue(nextDirection) {
    if (state !== "playing") return;
    const previous = queue.at(-1) || direction;
    if (nextDirection !== previous && nextDirection !== OPPOSITE[previous] && queue.length < 2) {
      queue.push(nextDirection);
    }
  }

  function tick() {
    if (queue.length) direction = queue.shift();
    const vector = VECTORS[direction];
    const head = { x: snake[0].x + vector.x, y: snake[0].y + vector.y };
    const board = MODES[mode];
    const hitWall = head.x < 0 || head.y < 0 || head.x >= board.columns || head.y >= board.rows;
    const hitSelf = snake.some(part => part.x === head.x && part.y === head.y);

    if (hitWall || hitSelf) {
      gameOver();
      return;
    }

    snake.unshift(head);
    if (head.x === food.x && head.y === food.y) {
      score += mode === "impossible" ? 20 : mode === "fast" ? 15 : 10;
      scoreElement.textContent = formatScore(score);
      if (score > highScore) {
        highScore = score;
        localStorage.setItem("406snake-high-score", String(highScore));
        highScoreElement.textContent = formatScore(highScore);
      }
      food = placeFood();
      beep(880, .055);
    } else {
      snake.pop();
    }
  }

  function gameOver() {
    state = "gameover";
    beep(120, .22);
    overlayLabel.textContent = "FIM DE JOGO";
    overlayTitle.textContent = `${formatScore(score)} PONTOS`;
    menu.classList.add("hidden");
    actionButton.classList.remove("hidden");
    hint.textContent = "Enter para tentar novamente · M para o menu";
    overlay.classList.remove("hidden");
  }

  function showMenu() {
    state = "menu";
    overlayLabel.textContent = "ESCOLHA O MODO";
    overlayTitle.textContent = "406 SNAKE";
    menu.classList.remove("hidden");
    actionButton.classList.add("hidden");
    hint.textContent = "Setas ou WASD para mover";
    overlay.classList.remove("hidden");
    draw();
  }

  function togglePause() {
    if (state === "playing") {
      state = "paused";
      overlayLabel.textContent = "JOGO PAUSADO";
      overlayTitle.textContent = "PAUSA";
      menu.classList.add("hidden");
      actionButton.classList.remove("hidden");
      actionButton.textContent = "CONTINUAR";
      hint.textContent = "Espaço para continuar";
      overlay.classList.remove("hidden");
    } else if (state === "paused") {
      state = "playing";
      actionButton.textContent = "JOGAR NOVAMENTE";
      overlay.classList.add("hidden");
    }
  }

  function beep(frequency, duration) {
    if (!soundEnabled) return;
    audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    oscillator.type = "square";
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(.06, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(.001, audioContext.currentTime + duration);
    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start();
    oscillator.stop(audioContext.currentTime + duration);
  }

  function draw() {
    const board = MODES[mode];
    const cell = Math.min(canvas.width / board.columns, canvas.height / board.rows);
    const width = board.columns * cell;
    const height = board.rows * cell;
    const offsetX = (canvas.width - width) / 2;
    const offsetY = (canvas.height - height) / 2;

    ctx.fillStyle = "#9fbd72";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = "rgba(23,32,21,.08)";
    ctx.lineWidth = 1;
    for (let x = 0; x <= board.columns; x++) {
      ctx.beginPath(); ctx.moveTo(offsetX + x * cell, offsetY); ctx.lineTo(offsetX + x * cell, offsetY + height); ctx.stroke();
    }
    for (let y = 0; y <= board.rows; y++) {
      ctx.beginPath(); ctx.moveTo(offsetX, offsetY + y * cell); ctx.lineTo(offsetX + width, offsetY + y * cell); ctx.stroke();
    }
    ctx.strokeStyle = "#172015";
    ctx.lineWidth = 3;
    ctx.strokeRect(offsetX + 1.5, offsetY + 1.5, width - 3, height - 3);

    ctx.fillStyle = "#172015";
    snake.forEach((part, index) => {
      const gap = index === 0 ? 2 : 3;
      ctx.fillRect(offsetX + part.x * cell + gap, offsetY + part.y * cell + gap, cell - gap * 2, cell - gap * 2);
    });

    const pulse = 2 + Math.sin(performance.now() / 130);
    ctx.fillRect(
      offsetX + food.x * cell + cell * .25 - pulse / 2,
      offsetY + food.y * cell + cell * .25 - pulse / 2,
      cell * .5 + pulse,
      cell * .5 + pulse
    );
  }

  function loop(time) {
    const delta = Math.min(time - lastTime, 100);
    lastTime = time;
    if (state === "playing") {
      accumulator += delta;
      while (accumulator >= MODES[mode].interval) {
        tick();
        accumulator -= MODES[mode].interval;
      }
    }
    draw();
    requestAnimationFrame(loop);
  }

  document.addEventListener("keydown", event => {
    if (KEYS[event.key]) {
      event.preventDefault();
      enqueue(KEYS[event.key]);
    } else if (event.key === "Enter") {
      if (state === "menu" || state === "gameover") start(mode);
    } else if (event.key === " " || event.key === "Escape") {
      event.preventDefault();
      togglePause();
    } else if (event.key.toLowerCase() === "m") {
      showMenu();
    }
  });

  menu.addEventListener("click", event => {
    const button = event.target.closest("[data-mode]");
    if (button) start(button.dataset.mode);
  });
  actionButton.addEventListener("click", () => state === "paused" ? togglePause() : start(mode));
  document.querySelector(".controls").addEventListener("pointerdown", event => {
    const button = event.target.closest("[data-direction]");
    if (button) enqueue(button.dataset.direction);
  });
  soundButton.addEventListener("click", () => {
    soundEnabled = !soundEnabled;
    soundButton.textContent = `SOM: ${soundEnabled ? "ON" : "OFF"}`;
    soundButton.setAttribute("aria-label", soundEnabled ? "Desativar som" : "Ativar som");
    beep(660, .06);
  });
  document.addEventListener("visibilitychange", () => {
    if (document.hidden && state === "playing") togglePause();
  });

  showMenu();
  requestAnimationFrame(loop);
})();
