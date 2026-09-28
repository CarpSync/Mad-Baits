(() => {
  "use strict";

  const cfg = window.madBaitsWaterFinder || {};
  const shell = document.querySelector("[data-water-finder-app]");
  if (!(shell instanceof HTMLElement)) {
    return;
  }

  const quizEl = shell.querySelector("[data-water-finder-quiz]");
  const resultEl = shell.querySelector("[data-water-finder-result]");
  const startBtn = shell.querySelector("[data-water-finder-start]");
  const progressEl = shell.querySelector("[data-water-finder-progress]");
  const stepLabelEl = shell.querySelector("[data-water-finder-step-label]");
  const questionTitleEl = shell.querySelector("[data-water-finder-question-title]");
  const optionsEl = shell.querySelector("[data-water-finder-options]");
  const recommendationsEl = shell.querySelector("[data-water-finder-recommendations]");
  const productsEl = shell.querySelector("[data-water-finder-products]");
  const fallbackEl = shell.querySelector("[data-water-finder-fallback]");
  const resetBtn = shell.querySelector("[data-water-finder-reset]");
  const shopBtn = shell.querySelector("[data-water-finder-shop]");

  if (
    !(quizEl instanceof HTMLElement) ||
    !(resultEl instanceof HTMLElement) ||
    !(optionsEl instanceof HTMLElement) ||
    !(questionTitleEl instanceof HTMLElement) ||
    !(recommendationsEl instanceof HTMLElement) ||
    !(productsEl instanceof HTMLElement)
  ) {
    return;
  }

  if (fallbackEl instanceof HTMLElement) {
    fallbackEl.hidden = true;
  }

  const questions = [
    { key: "venueType", title: "Where are you fishing?", options: ["Lake", "River", "Canal", "Commercial", "French Venue"] },
    { key: "condition", title: "What are the conditions?", options: ["Clear", "Coloured", "Weedy", "Silty", "Pressured"] },
    { key: "sessionLength", title: "How long is your session?", options: ["Day Session", "Overnight", "Weekend", "Week Trip"] },
    { key: "season", title: "What season?", options: ["Spring", "Summer", "Autumn", "Winter"] },
    { key: "style", title: "What’s your approach?", options: ["Instant Bite", "Big Hit Feeding", "Match The Hatch", "High Attraction"] },
  ];

  let step = -1;
  const answers = {};
  let isBusy = false;

  const vibrate = (pattern) => {
    if (!cfg.enableHaptics) {
      return;
    }
    if (!("vibrate" in navigator) || typeof navigator.vibrate !== "function") {
      return;
    }
    navigator.vibrate(pattern);
  };

  const setProgress = () => {
    const ratio = step < 0 ? 0 : Math.min(100, Math.round((step / questions.length) * 100));
    if (progressEl instanceof HTMLElement) {
      progressEl.style.width = `${ratio}%`;
    }
    if (stepLabelEl instanceof HTMLElement) {
      stepLabelEl.textContent = step < 0 ? "Ready" : `Step ${Math.min(step + 1, questions.length)} of ${questions.length}`;
    }
  };

  const showQuestion = () => {
    const question = questions[step];
    if (!question) {
      runRecommendation();
      return;
    }
    resultEl.hidden = true;
    quizEl.hidden = false;
    questionTitleEl.textContent = question.title;
    optionsEl.innerHTML = "";

    question.options.forEach((label) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "water-finder-page__option";
      btn.textContent = label;
      if (answers[question.key] === label) {
        btn.classList.add("is-selected");
      }
      btn.addEventListener("click", () => {
        answers[question.key] = label;
        vibrate(10);
        step += 1;
        setProgress();
        showQuestion();
      });
      optionsEl.appendChild(btn);
    });
  };

  const renderRecommendations = (recommendations) => {
    recommendationsEl.innerHTML = "";
    (recommendations || []).forEach((item) => {
      const card = document.createElement("article");
      card.className = "water-finder-page__recommendation";
      card.innerHTML = `<strong>${item.label}</strong><span>${item.value}</span>`;
      recommendationsEl.appendChild(card);
    });
  };

  const renderProducts = (products) => {
    productsEl.innerHTML = "";
    if (!Array.isArray(products) || products.length < 1) {
      const fallback = document.createElement("p");
      fallback.className = "water-finder-page__product-price";
      fallback.textContent = "Open the shop to browse matching ranges.";
      productsEl.appendChild(fallback);
      return;
    }
    products.forEach((item) => {
      const card = document.createElement("article");
      card.className = "water-finder-page__product";
      const img = item.image ? `<img src="${item.image}" alt="">` : `<img src="${cfg.fallbackImage || ""}" alt="">`;
      card.innerHTML = `
        ${img}
        <div>
          <h3>${item.title || "Recommended bait"}</h3>
          <p class="water-finder-page__product-price">${item.price || ""}</p>
          <a class="mad-button mad-button--small" href="${item.url || cfg.shopUrl || "/shop/"}">Shop</a>
        </div>
      `;
      productsEl.appendChild(card);
    });
  };

  const runRecommendation = async () => {
    if (isBusy) {
      return;
    }
    isBusy = true;
    quizEl.hidden = true;
    resultEl.hidden = false;
    recommendationsEl.innerHTML = "<p>Building your setup...</p>";
    productsEl.innerHTML = "";

    try {
      const body = new FormData();
      body.append("action", "mad_baits_water_finder_page_recommend");
      body.append("nonce", cfg.nonce || "");
      Object.keys(answers).forEach((key) => body.append(`answers[${key}]`, answers[key]));

      const response = await fetch(cfg.ajaxUrl || "", {
        method: "POST",
        credentials: "same-origin",
        body,
      });
      const json = await response.json();
      const data = json && json.success ? json.data || {} : {};
      renderRecommendations(data.recommendations || []);
      renderProducts(data.products || []);
      if (shopBtn instanceof HTMLAnchorElement && data.shopUrl) {
        shopBtn.href = String(data.shopUrl);
      }
      vibrate([16, 24, 16]);
      setProgress();
      if (progressEl instanceof HTMLElement) {
        progressEl.style.width = "100%";
      }
    } catch (_error) {
      recommendationsEl.innerHTML = "<p>Could not load recommendations. Please try again.</p>";
      if (shopBtn instanceof HTMLAnchorElement && cfg.shopUrl) {
        shopBtn.href = cfg.shopUrl;
      }
    } finally {
      isBusy = false;
    }
  };

  const resetQuiz = () => {
    step = -1;
    Object.keys(answers).forEach((key) => delete answers[key]);
    resultEl.hidden = true;
    quizEl.hidden = true;
    setProgress();
    if (progressEl instanceof HTMLElement) {
      progressEl.style.width = "0%";
    }
  };

  if (startBtn instanceof HTMLButtonElement) {
    startBtn.addEventListener("click", () => {
      if (step < 0) {
        step = 0;
      }
      setProgress();
      showQuestion();
      quizEl.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  if (resetBtn instanceof HTMLButtonElement) {
    resetBtn.addEventListener("click", () => {
      resetQuiz();
      if (startBtn instanceof HTMLButtonElement) {
        startBtn.focus();
      }
    });
  }

  resetQuiz();
})();
