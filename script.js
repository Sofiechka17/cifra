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
    
    // Отправка таблицы 
    const dataForm = document.getElementById("data-form");
    if (dataForm) {
        const modalSaved = document.getElementById("tableSavedModal");
        const closeSaved = document.getElementById("closeTableSavedModal");
        const msgSaved = document.getElementById("tableSavedMessage");

        function showSaved(text) {
            if (modalSaved && msgSaved) {
                msgSaved.textContent = text || "Данные успешно сохранены.";
                modalSaved.style.display = "flex";
            } else {
                alert(text || "Данные успешно сохранены.");
            }
        }

        if (closeSaved && modalSaved) {
            closeSaved.addEventListener("click", () => (modalSaved.style.display = "none"));
            modalSaved.addEventListener("click", (e) => {
                if (e.target === modalSaved) modalSaved.style.display = "none";
            });
        }

        // Ограничение ввода в числовых полях
        const numericInputs = dataForm.querySelectorAll('#data-table input[type="number"]');
        numericInputs.forEach((input) => {
            input.addEventListener("input", () => {
                input.value = input.value.replace(/[^0-9.,-]/g, "");
            });
        });

        // Проверка таблицы перед отправкой
        function validateTable() {
            let hasErrors = false;

            const inputs = dataForm.querySelectorAll("#data-table input");
            inputs.forEach((input) => input.classList.remove("input-error"));

            inputs.forEach((input) => {
                const nameAttr = input.getAttribute("name") || "";

                const isComment = nameAttr.includes("[Комментарий]");
                const isTextCol =
                    nameAttr.includes("[Показатели]") ||
                    nameAttr.includes("[Единица измерения]");

                // комментарий и текстовые колонки не проверяем
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
                    return;
                }
            });

            return !hasErrors;
        }

        // перехватываем submit и отправляем AJAX
        dataForm.addEventListener("submit", async (e) => {
            e.preventDefault(); 

            if (!validateTable()) {
                alert(
                    "Не все числовые поля заполнены или заполнены некорректно.\n" +
                    "Заполните все обязательные ячейки и повторите отправку."
                );
                return;
            }

            try {
                const formData = new FormData(dataForm);

                const res = await fetch(dataForm.action || "save_table.php", {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    }
                });

                // Если сервер вернул не JSON — покажем текст 
                const contentType = res.headers.get("content-type") || "";
                if (!contentType.includes("application/json")) {
                    const text = await res.text();
                    alert("Сервер вернул не JSON. Скорее всего ошибка PHP:\n\n" + text);
                    return;
                }

                const data = await res.json();

                if (data.success) {
                    showSaved(data.message || "Данные успешно сохранены.");
                } else {
                    alert(data.message || "Ошибка сохранения данных.");
                }
            } catch (err) {
                alert("Ошибка сети: " + err);
            }
        });
    }
});