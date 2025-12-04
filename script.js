// Инициализация карты 
ymaps.ready(initMap);
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

    // Функции открытия/закрытия модалки 
    const openModal = (form) => {
        modal.style.display = "flex";
        signUpForm.style.display = form === "register" ? "block" : "none";
        signInForm.style.display = form === "login" ? "block" : "none";
    };

    loginBtn.addEventListener("click", () => openModal("register"));
    closeModal.addEventListener("click", () => modal.style.display = "none");
    window.addEventListener("click", e => { if (e.target === modal) modal.style.display = "none"; });

    showLogin.addEventListener("click", e => { e.preventDefault(); openModal("login"); });
    showRegister.addEventListener("click", e => { e.preventDefault(); openModal("register"); });

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
    

    // Отправка заявки 
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

    // Кнопка Заполнить форму
    feedbackLink.addEventListener("click", async e => {
        e.preventDefault();

        try {
            const authCheck = await fetch("get_table.php");
            const data = await authCheck.json();

            if (data.error) {
                alert("Необходима авторизация");
                return;
            }

            if (document.getElementById("data-table-container")) {
                alert("Таблица уже отображается на странице.");
                return;
            }

            renderTable(data);
        } catch (err) {
            alert("Ошибка загрузки таблицы: " + err);
        }
    });

    // Функции рендера и сохранения таблицы 
    function renderTable(data) {
        const container = document.createElement("div");
        container.id = "data-table-container";
        container.style.overflowX = "auto";
        container.style.width = "100%";

        let html = `<h3 style="margin-bottom:10px;">Основные показатели, представляемые для разработки прогноза социального развития на период 2026-2028 г.</h3>`;
        html += `<p style="margin-bottom:20px;">Муниципальное образование: ${data.municipality_name}</p>`;

        html += `<table id="data-table"><thead>`;
        html += `<tr>
                   <th rowspan="2" style="min-width:500px;">Показатели</th>
                   <th rowspan="2">Единица измерения</th>
                   <th colspan="3">Отчет</th>
                   <th>Оценка показателей</th>
                   <th colspan="6">Прогноз</th>
                 </tr>`;
        html += `<tr>
                   <th>2022</th><th>2023</th><th>2024</th><th>2025</th>
                   <th>2026_консервативный</th><th>2026_базовый</th>
                   <th>2027_консервативный</th><th>2027_базовый</th>
                   <th>2028_консервативный</th><th>2028_базовый</th>
                 </tr>`;
        html += `</thead><tbody>`;

        data.rows.forEach(row => {
            html += `<tr>`;
            data.headers.forEach(h => {
                const value = row[h.name] || "";
                const readonly = h.readonly ? "readonly" : "";
                const type = h.type === "number" ? "number" : "text";
                const style = h.name === "Показатели" ? "min-width:500px;" : "";
                html += `<td><input type="${type}" value="${value}" ${readonly} style="width:100%; ${style}"></td>`;
            });
            html += `</tr>`;
        });

        html += `</tbody></table>`;
        html += `<button id="saveTableBtn">Сохранить</button>`;

        container.innerHTML = html;
        document.querySelector("main").appendChild(container);

        document.getElementById("saveTableBtn").addEventListener("click", async () => saveTable(container, data));
    }

    async function saveTable(container, data) {
        const inputs = container.querySelectorAll("input");
        const tableData = [];

        for (let i = 0; i < data.rows.length; i++) {
            const rowObj = {};
            data.headers.forEach((h, j) => {
                rowObj[h.name] = inputs[i * data.headers.length + j].value;
            });
            tableData.push(rowObj);
        }

        const formData = new FormData();
        formData.append("template_id", data.template_id);
        formData.append("municipality_name", data.municipality_name);
        formData.append("table_data", JSON.stringify(tableData));

        try {
            const checkRes = await fetch("save_table.php", { method: "HEAD" });
            if (!checkRes.ok) {
                alert("Файл save_table.php не найден на сервере. Проверьте путь.");
                return;
            }

            const saveRes = await fetch("save_table.php", { method: "POST", body: formData });
            const saveData = await saveRes.text();
            alert(saveData);
        } catch (err) {
            alert("Ошибка при отправке данных: " + err);
        }
    }
});
