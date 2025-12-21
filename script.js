/**
 * Скрипт для главной страницы:
 *  - инициализация карты Яндекс;
 *  - логика модального окна регистрации/авторизации;
 *  - AJAX-регистрация и авторизация;
 *  - валидация ФИО и телефона;
 *  - отправка формы обратной связи;
 *  - динамическое построение таблицы и сохранение данных.
 */

// Инициализация карты 
ymaps.ready(initMap);
/**
 * Создаёт карту на элементе #map и добавляет метку учреждения.
 */
function initMap() {
    const myMap = new ymaps.Map("map", {
        center: [51.767134, 55.095994],
        zoom: 16 
    });

    const myPlacemark = new ymaps.Placemark([51.767134, 55.095994], {
        balloonContent: "Адрес: 9 Января, 62, Оренбург<br>Телефон: +7 (3532) 91-01-00"
    });

    myMap.geoObjects.add(myPlacemark);
}

// DOMContentLoaded
document.addEventListener("DOMContentLoaded", () => {
    const loginBtn = document.getElementById("loginBtn");
    const modal = document.getElementById("authModal");
    const closeModal = document.getElementById("closeModal");

    const signUpForm = document.getElementById("signUpForm");
    const signInForm = document.getElementById("signInForm");

    const showLogin = document.getElementById("showLogin");
    const showRegister = document.getElementById("showRegister");

    const feedbackLink = document.getElementById("feedback-link");

    // Если модалка вообще есть на странице
    if (modal && signUpForm && signInForm) {

        const openModal = (form) => {
            modal.style.display = "flex";
            signUpForm.style.display = form === "register" ? "block" : "none";
            signInForm.style.display = form === "login" ? "block" : "none";
        };

        if (loginBtn) {
            loginBtn.addEventListener("click", () => openModal("register"));
        }
        if (closeModal) {
            closeModal.addEventListener("click", () => modal.style.display = "none");
        }
        window.addEventListener("click", e => { if (e.target === modal) modal.style.display = "none"; });

        if (showLogin) {
            showLogin.addEventListener("click", e => { e.preventDefault(); openModal("login"); });
        }
        if (showRegister) {
            showRegister.addEventListener("click", e => { e.preventDefault(); openModal("register"); });
        }

    // AJAX регистрация
    const regForm = signUpForm.querySelector("form");
    regForm.addEventListener("submit", async e => {
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
    loginForm.addEventListener("submit", async e => {
        e.preventDefault();
        const formData = new FormData(loginForm);

        try {
            const res = await fetch("login.php", { method: "POST", body: formData });
            const data = await res.json();

            if (data.success) {
                modal.style.display = "none";
                if (data.message) alert(data.message);
                if (data.redirect) window.location.href = data.redirect;
                else {
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

    // Валидация ФИО и телефона 
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
    

    // Отправка заявки и обратной связи
    const feedbackForm = document.getElementById("feedbackForm");
    if (feedbackForm) {
        feedbackForm.addEventListener("submit", async e => {
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
    
    // Валидация таблицы перед отправкой (заполнение показателей) 
    const dataForm = document.getElementById("data-form");
    if (dataForm) {
        dataForm.addEventListener("submit", (e) => {
            let hasErrors = false;

            const inputs = dataForm.querySelectorAll("#data-table input");

            // Сначала убираем старую подсветку
            inputs.forEach(input => input.classList.remove("input-error"));

            inputs.forEach(input => {
                const nameAttr = input.getAttribute("name") || "";

                // Имя вида: cell[0][2022], cell[0][Показатели], cell[0][Комментарий]
                const isComment = nameAttr.includes("[Комментарий]");
                const isTextCol =
                    nameAttr.includes("[Показатели]") ||
                    nameAttr.includes("[Единица измерения]");

                // Текстовые и комментарий не проверяем
                if (isComment || isTextCol) {
                    return;
                }

                const value = input.value.trim();

                // Пустое числовое поле — ошибка
                if (value === "") {
                    input.classList.add("input-error");
                    hasErrors = true;
                    return;
                }

                // Заменяем запятую на точку и проверяем, число ли это
                const normalized = value.replace(",", ".");
                if (isNaN(normalized)) {
                    input.classList.add("input-error");
                    hasErrors = true;
                    return;
                }
            });

            if (hasErrors) {
                e.preventDefault(); // ошибка, не отправляем форму
                alert("Не все числовые поля заполнены или заполнены корректно.\nЗаполните все обязательные ячейки и повторите отправку.");
            }
        });
    }        
});
