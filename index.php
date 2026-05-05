<?php
/**
 * Главная страница системы:
 *  - информация об учреждении;
 *  - контакты;
 *  - информация о курсовой;
 *  - форма обратной связи;
 *  - модальное окно регистрации и авторизации пользователя.
 */
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Информационная система сбора данных</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" defer></script>
    <script src="script.js" defer></script>
</head>
<body>
<script>window.csrfToken = <?= json_encode(Csrf::token()) ?>;</script>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#000; padding:15px 30px;">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <img src="default-logo_w152_fitted.webp" alt="Логотип" height="40" style="object-fit:contain;">
            <span class="fw-bold text-white fs-6">Информационная система сбора данных</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto gap-3">
                <li class="nav-item"><a class="nav-link" href="#about">Главная</a></li>
                <li class="nav-item"><a class="nav-link" href="#contacts">Контакты</a></li>
                <li class="nav-item"><a class="nav-link" href="#feedback-form">Обратная связь</a></li>
                <li class="nav-item"><a class="nav-link" href="get_table.php">Заполнить форму</a></li>
            </ul>
            <div class="ms-lg-3 mt-2 mt-lg-0">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <div class="user-circle" id="userCircle" title="Личный кабинет">МО</div>
                <?php else: ?>
                    <button class="login-btn" id="loginBtn">Войти</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="container-fluid px-4 px-md-5">
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

    <section id="feedback-form">
        <h2>Оставить заявку</h2>
        <form id="feedbackForm" method="POST">
            <?= Csrf::input() ?>
            <label class="form-label" for="full-name">ФИО:</label>
            <input class="form-control" type="text" id="full-name" name="full-name" required>

            <label class="form-label" for="phone">Номер телефона:</label>
            <input class="form-control" type="tel" id="phone" name="phone" pattern="\+7\d{10}" placeholder="+7XXXXXXXXXX" required>

            <label class="form-label" for="problem-description">Текст обращения:</label>
            <textarea class="form-control" id="problem-description" name="problem-description" required></textarea>

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

<!-- Модальное окно регистрации/авторизации -->
<div class="modal" id="authModal">
    <div class="modal-content" style="max-width:460px; width:100%;">
        <span class="close" id="closeModal">&times;</span>

        <div class="form-wrapper" id="signUpForm">
            <form action="register.php" method="POST">
                <?= Csrf::input() ?>
                <h2>Регистрация</h2>
                <label class="form-label" for="reg-fullname">ФИО:</label>
                <input class="form-control" type="text" id="reg-fullname" name="fullname" required>
                <small id="fio-error" style="color:red; display:none;">ФИО должно начинаться с заглавной буквы.</small>

                <label class="form-label" for="reg-phone">Номер телефона:</label>
                <input class="form-control" type="tel" id="reg-phone" name="phone" required maxlength="12" placeholder="+7XXXXXXXXXX">
                <small id="phone-error" style="color:red; display:none;">Телефон должен начинаться с +7 и содержать 11 цифр.</small>


                <label class="form-label" for="reg-email">Эл. почта:</label>
                <input class="form-control" type="email" id="reg-email" name="email" required>

                <label class="form-label" for="reg-municipality">Муниципальное образование:</label>
                <select class="form-select" id="reg-municipality" name="municipality_id" required>
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

                <label class="form-label" for="reg-username">Логин:</label>
                <input class="form-control" type="text" id="reg-username" name="username" required>

                <label class="form-label" for="reg-password">Пароль:</label>
                <input class="form-control" type="password" id="reg-password" name="password" required>

                <button type="submit">Зарегистрироваться</button>
                <div class="signUp-link">
                    <p>Уже есть аккаунт? <a href="#" id="showLogin">Войти</a></p>
                </div>
            </form>
        </div>

        <div class="form-wrapper" id="signInForm" style="display:none;">
            <form action="login.php" method="POST">
                <?= Csrf::input() ?>
                <h2>Авторизация</h2>
                <label class="form-label" for="login-username">Логин:</label>
                <input class="form-control" type="text" id="login-username" name="username" required>

                <label class="form-label" for="login-password">Пароль:</label>
                <input class="form-control" type="password" id="login-password" name="password" required>

                <button type="submit">Войти</button>
                <div class="signUp-link">
                    <p>Нет аккаунта? <a href="#" id="showRegister">Зарегистрироваться</a></p>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Скрытая форма выхода + JS -->
<form id="logoutForm" method="POST" action="logout.php" style="display:none;">
    <?= Csrf::input() ?>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const userCircle = document.getElementById('userCircle');
    if (userCircle) {
        userCircle.addEventListener('click', function () {
            if (confirm('Вы хотите выйти из личного кабинета?')) {
                document.getElementById('logoutForm').submit();
            }
        });
    }
});
</script>
</body>
</html>