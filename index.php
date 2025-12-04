<?php
session_start();
include "db.php";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Информационная система сбора данных</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" defer></script>
    <script src="script.js" defer></script>
</head>
<body>
<header>
    <div class="logo">
        <img src="default-logo_w152_fitted.webp" alt="Логотип" style="width:30%; height:100%; object-fit:contain;">
    </div>
    <span class="system-name">Информационная система сбора данных</span>
    <nav>
        <div class="nav-links">
            <a href="#about">Главная</a>
            <a href="#contacts">Контакты</a>
            <a href="#coursework">О курсовой</a>
            <a href="#feedback-form">Обратная связь</a>
            <a href="#" id="feedback-link">Заполнить форму</a>
        </div>
        <button class="login-btn" id="loginBtn">Войти</button>
    </nav>
</header>

<main>
    <section id="about">
        <h2>Об учреждении</h2>
        <p class="main-text">
            Государственное казенное учреждение "Центр информационных технологий Оренбургской области"
            выполняет работы и оказывает услуги, направленных на решение комплексных задач по информатизации органов власти Оренбургской области.
        </p>
    </section>

    <section id="location">
        <h2>Где мы находимся</h2>
        <div id="map" style="width: 100%; height: 400px;"></div>
    </section>

    <section id="contacts">
        <h2>Контакты</h2>
        <ul class="main-text">
            <li>Телефон приёмной: +7 (3532) 91-01-00</li>
            <li>Электронная почта: <a href="mailto:cit@mail.orb.ru">cit@mail.orb.ru</a></li>
            <li>Юридический адрес: 460000, г. Оренбург, ул. Кобозева, 30, помещение 3</li>
            <li>Фактический адрес: 460015, г. Оренбург, ул. 9 Января, 62</li>
            <li>Сайт: <a href="https://cit.orb.ru" target="_blank">cit.orb.ru</a></li>
        </ul>
    </section>

    <section id="coursework">
    <h2>О курсовой</h2>
    <p class="main-text">
        Курсовую работу "Информационная система сбора данных" разработала Суюндукова С.А.
    </p>
    </section>

    <section id="feedback-form">
        <h2>Оставить заявку</h2>
        <form id="feedbackForm" method="POST">
            <label for="full-name">ФИО:</label>
            <input type="text" id="full-name" name="full-name" required>

            <label for="phone">Номер телефона:</label>
            <input type="tel" id="phone" name="phone" pattern="\+7\d{10}" placeholder="+7XXXXXXXXXX" required>

            <label for="problem-description">Текст обращения:</label>
            <textarea id="problem-description" name="problem-description" required></textarea>

            <button type="submit">Оставить заявку</button>
        </form>
    </section>
</main>

<div class="modal" id="successModal" style="display:none; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:#fff; padding:20px; border-radius:8px; text-align:center; max-width:400px;">
    <span class="close" id="closeSuccessModal" style="float:right; cursor:pointer;">&times;</span>
    <p id="successMessage">Ваша заявка успешно отправлена!</p>
  </div>
</div>

<div class="modal" id="authModal">
    <div class="modal-content">
        <span class="close" id="closeModal">&times;</span>

        <div class="form-wrapper" id="signUpForm">
            <form action="register.php" method="POST">
                <h2>Регистрация</h2>
                <label for="reg-fullname">ФИО:</label>
                <input type="text" id="reg-fullname" name="fullname" required>
                <small id="fio-error" style="color:red; display:none;">ФИО должно начинаться с заглавной буквы.</small>

                <label for="reg-phone">Номер телефона:</label>
                <input type="tel" id="reg-phone" name="phone" required maxlength="12" placeholder="+7XXXXXXXXXX">
                <small id="phone-error" style="color:red; display:none;">Телефон должен начинаться с +7 и содержать 11 цифр.</small>


                <label for="reg-email">Эл. почта:</label>
                <input type="email" id="reg-email" name="email" required>

                <label for="reg-municipality">Муниципальное образование:</label>
                <select id="reg-municipality" name="municipality_id" required>
                    <option value="">Выберите МО</option>
                    <?php
                    $result = pg_query($conn, "SELECT municipality_id, municipality_name FROM municipalities ORDER BY municipality_name");
                    if ($result) {
                        while ($row = pg_fetch_assoc($result)) {
                            echo "<option value='" . htmlspecialchars($row['municipality_id'], ENT_QUOTES) . "'>" .
                                 htmlspecialchars($row['municipality_name'], ENT_QUOTES) . "</option>";
                        }
                    } else {
                        echo "<option disabled>Ошибка загрузки данных</option>";
                    }
                    ?>
                </select>

                <label for="reg-username">Логин:</label>
                <input type="text" id="reg-username" name="username" required>

                <label for="reg-password">Пароль:</label>
                <input type="password" id="reg-password" name="password" required>

                <button type="submit">Зарегистрироваться</button>
                <div class="signUp-link">
                    <p>Уже есть аккаунт? <a href="#" id="showLogin">Войти</a></p>
                </div>
            </form>
        </div>

        <div class="form-wrapper" id="signInForm" style="display:none;">
            <form action="login.php" method="POST">
                <h2>Авторизация</h2>
                <label for="login-username">Логин:</label>
                <input type="text" id="login-username" name="username" required>

                <label for="login-password">Пароль:</label>
                <input type="password" id="login-password" name="password" required>

                <button type="submit">Войти</button>
                <div class="signUp-link">
                    <p>Нет аккаунта? <a href="#" id="showRegister">Зарегистрироваться</a></p>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>