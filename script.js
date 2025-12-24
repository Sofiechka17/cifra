/**
 * Скрипт для главной страницы:
 *  - инициализация карты Яндекс;
 *  - логика модального окна регистрации/авторизации;
 *  - AJAX-регистрация и авторизация;
 *  - валидация ФИО и телефона;
 *  - отправка формы обратной связи;
 *  - динамическое построение таблицы и сохранение данных.
 */

/**
 * Приложение главной страницы.
 */
class MainPageApp {
  /** Инициализация приложения. */
  init() {
    this.initYandexMap();
    document.addEventListener("DOMContentLoaded", () => {
      this.initAuthModal();
      this.initInputsValidation();
      this.initFeedbackForm();
      this.initDataFormSubmission();
    });
  }

  /** Инициализация карты  */
  initYandexMap() {
    if (typeof ymaps === "undefined") return;
    ymaps.ready(() => this.createMap());
  }

  /** Создаёт карту на #map и добавляет метку учреждения. */
  createMap() {
    const myMap = new ymaps.Map("map", { center: [51.767134, 55.095994], zoom: 16 });
    const myPlacemark = new ymaps.Placemark([51.767134, 55.095994], {
      balloonContent: "Адрес: 9 Января, 62, Оренбург<br>Телефон: +7 (3532) 91-01-00",
    });
    myMap.geoObjects.add(myPlacemark);
  }

  /** Инициализация модалки регистрации/входа + AJAX. */
  initAuthModal() {
    const loginBtn = document.getElementById("loginBtn");
    const modal = document.getElementById("authModal");
    const closeModal = document.getElementById("closeModal");
    const signUpForm = document.getElementById("signUpForm");
    const signInForm = document.getElementById("signInForm");
    const showLogin = document.getElementById("showLogin");
    const showRegister = document.getElementById("showRegister");
    const feedbackLink = document.getElementById("feedback-link");

    if (!modal || !signUpForm || !signInForm) return;

    const openModal = (mode) => {
      modal.style.display = "flex";
      signUpForm.style.display = mode === "register" ? "block" : "none";
      signInForm.style.display = mode === "login" ? "block" : "none";
    };

    if (loginBtn) loginBtn.addEventListener("click", () => openModal("register"));
    if (closeModal) closeModal.addEventListener("click", () => (modal.style.display = "none"));

    window.addEventListener("click", (e) => {
      if (e.target === modal) modal.style.display = "none";
    });

    if (showLogin) showLogin.addEventListener("click", (e) => { e.preventDefault(); openModal("login"); });
    if (showRegister) showRegister.addEventListener("click", (e) => { e.preventDefault(); openModal("register"); });

    // AJAX регистрация
    const regForm = signUpForm.querySelector("form");
    if (regForm) {
      regForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const formData = new FormData(regForm);
        try {
          const res = await fetch("register.php", { method: "POST", body: formData });
          const data = await res.json();
          alert(data.message || (data.success ? "Регистрация успешна!" : "Ошибка!"));
          if (data.success) openModal("login");
        } catch (err) {
          alert("Ошибка сети: " + err);
        }
      });
    }

    // AJAX авторизация
    const loginForm = signInForm.querySelector("form");
    if (loginForm) {
      loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        const formData = new FormData(loginForm);

        try {
          const res = await fetch("login.php", { method: "POST", body: formData });
          const data = await res.json();

          if (data.success) {
            modal.style.display = "none";
            if (data.message) alert(data.message);
            if (data.redirect) window.location.href = data.redirect;
            else if (feedbackLink) {
              feedbackLink.style.pointerEvents = "auto";
              feedbackLink.style.opacity = 1;
            }
          } else {
            alert(data.message || "Ошибка авторизации");
          }
        } catch (err) {
          alert("Ошибка сети: " + err);
        }
      });
    }
  }

  /** Валидация ФИО и телефона в форме регистрации. */
  initInputsValidation() {
    const fioInput = document.getElementById("reg-fullname");
    const phoneInput = document.getElementById("reg-phone");
    const fioError = document.getElementById("fio-error");
    const phoneError = document.getElementById("phone-error");

    if (fioInput && fioError) {
      fioInput.addEventListener("input", () => {
        fioInput.value = fioInput.value.replace(/[^А-Яа-яЁё\s-]/g, "");
        const regexFio = /^[А-ЯЁ][а-яё]+(\s[А-ЯЁ][а-яё]+)*$/u;
        fioError.style.display = fioInput.value && !regexFio.test(fioInput.value) ? "block" : "none";
      });
    }

    if (phoneInput && phoneError) {
      phoneInput.addEventListener("input", () => {
        phoneInput.value = phoneInput.value.replace(/[^0-9+]/g, "");
        if (!phoneInput.value.startsWith("+7")) {
          phoneError.textContent = "Телефон должен начинаться с +7.";
          phoneError.style.display = "block";
        } else if (phoneInput.value.length !== 12) {
          phoneError.textContent = "Телефон должен содержать 11 цифр.";
          phoneError.style.display = "block";
        } else {
          phoneError.style.display = "none";
        }
      });
    }
  }

  /** Отправка формы обратной связи. */
  initFeedbackForm() {
    const feedbackForm = document.getElementById("feedbackForm");
    if (!feedbackForm) return;

    feedbackForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(feedbackForm);

      try {
        const res = await fetch("submit_form.php", { method: "POST", body: formData });
        const data = await res.json();

        if (data.success) {
          feedbackForm.reset();
          if (data.message) alert(data.message);
        } else if (data.errors) {
          alert(data.errors.join("\n"));
        }
      } catch (err) {
        alert("Ошибка сети: " + err);
      }
    });
  }

  /** Отправка таблицы (если на странице есть #data-form). */
  initDataFormSubmission() {
    const dataForm = document.getElementById("data-form");
    if (!dataForm) return;

    const modalSaved = document.getElementById("tableSavedModal");
    const closeSaved = document.getElementById("closeTableSavedModal");
    const msgSaved = document.getElementById("tableSavedMessage");

    const showSaved = (text) => {
      if (modalSaved && msgSaved) {
        msgSaved.textContent = text || "Данные успешно сохранены.";
        modalSaved.style.display = "flex";
      } else {
        alert(text || "Данные успешно сохранены.");
      }
    };

    if (closeSaved && modalSaved) {
      closeSaved.addEventListener("click", () => (modalSaved.style.display = "none"));
      modalSaved.addEventListener("click", (e) => {
        if (e.target === modalSaved) modalSaved.style.display = "none";
      });
    }

    // ограничение ввода в number
    dataForm.querySelectorAll('#data-table input[type="number"]').forEach((input) => {
      input.addEventListener("input", () => {
        input.value = input.value.replace(/[^0-9.,-]/g, "");
      });
    });

    const validateTable = () => {
      let hasErrors = false;
      const inputs = dataForm.querySelectorAll("#data-table input");
      inputs.forEach((input) => input.classList.remove("input-error"));

      inputs.forEach((input) => {
        const nameAttr = input.getAttribute("name") || "";
        const isComment = nameAttr.includes("[Комментарий]");
        const isTextCol = nameAttr.includes("[Показатели]") || nameAttr.includes("[Единица измерения]");
        if (isComment || isTextCol) return;

        const value = input.value.trim();
        if (value === "") {
          input.classList.add("input-error");
          hasErrors = true;
          return;
        }
        const normalized = value.replace(",", ".");
        if (isNaN(normalized)) {
          input.classList.add("input-error");
          hasErrors = true;
        }
      });

      return !hasErrors;
    };

    dataForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      if (!validateTable()) {
        alert("Не все числовые поля заполнены или заполнены некорректно.\nЗаполните все обязательные ячейки и повторите отправку.");
        return;
      }

      try {
        const formData = new FormData(dataForm);
        const res = await fetch(dataForm.action || "save_table.php", {
          method: "POST",
          body: formData,
          headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" },
        });

        const contentType = res.headers.get("content-type") || "";
        if (!contentType.includes("application/json")) {
          const text = await res.text();
          alert("Сервер вернул не JSON. Скорее всего ошибка PHP:\n\n" + text);
          return;
        }

        const data = await res.json();
        if (data.success) showSaved(data.message || "Данные успешно сохранены.");
        else alert(data.message || "Ошибка сохранения данных.");
      } catch (err) {
        alert("Ошибка сети: " + err);
      }
    });
  }
}

// Запуск
new MainPageApp().init();
