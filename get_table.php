<?php
/**
 * Страница заполнения активной отчётной таблицы.
 *
 * Это НОВАЯ версия get_table.php.
 * Теперь файл не отдаёт JSON, а сразу выводит HTML-форму с таблицей:
 *  - проверяем, что пользователь авторизован;
 *  - получаем активный шаблон через TemplateService (паттерн Фасад);
 *  - строим таблицу по заголовкам и структуре шаблона;
 *  - отправляем заполненные данные в save_table.php.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/core/TemplateService.php';

require_auth();

/**
 * Класс, который готовит информацию про объединённые ячейки.
 *
 * Он делает две вещи:
 * - Запоминает, где начинается объединение (верхняя левая ячейка) и какой у неё rowspan/colspan.
 * - Помечает остальные ячейки внутри объединения как "их не рисовать",
 *    чтобы таблица не дублировала объединённые клетки.
 */
final class MergeLayoutBuilder
{
    /**
     * Строит карты объединений для рендера HTML.
     *
     * @param array<int, mixed> $rowDefs Структура строк.
     * @param int $columnsCount Количество колонок.
     * @param array<int, mixed> $merges Объединения.
     * @return array{mergeTopLeft: array, skipCells: array} Карты объединений.
     */
    public function build(array $rowDefs, int $columnsCount, array $merges): array
    {
        $mergeTopLeft = [];
        $skipCells = [];

        foreach ($merges as $merge) {
            if (!is_array($merge)) {
                continue;
            }

            $sr = isset($merge['startRow']) ? (int)$merge['startRow'] : 0;
            $sc = isset($merge['startCol']) ? (int)$merge['startCol'] : 0;
            $rs = isset($merge['rowSpan']) ? (int)$merge['rowSpan'] : 1;
            $cs = isset($merge['colSpan']) ? (int)$merge['colSpan'] : 1;

            if ($sr < 0 || $sc < 0 || $rs < 1 || $cs < 1) {
                continue;
            }

            if ($sr >= count($rowDefs) || $sc >= $columnsCount) {
                continue;
            }

            $mergeTopLeft[$sr][$sc] = [
                'rowSpan' => $rs,
                'colSpan' => $cs,
            ];

            for ($r = $sr; $r < $sr + $rs; $r++) {
                for ($c = $sc; $c < $sc + $cs; $c++) {
                    if ($r === $sr && $c === $sc) {
                        continue;
                    }
                    $skipCells[$r][$c] = true;
                }
            }
        }

        return ['mergeTopLeft' => $mergeTopLeft, 'skipCells' => $skipCells];
    }
}

/**
 * Страница заполнения формы.
 */
final class FillTablePage
{
    /** @var TemplateService */
    private TemplateService $service;

    /** @var MergeLayoutBuilder */
    private MergeLayoutBuilder $mergeBuilder;

    /**
     * @param TemplateService $service Сервис шаблонов.
     * @param MergeLayoutBuilder $mergeBuilder Билдер объединений.
     */
    public function __construct(TemplateService $service, MergeLayoutBuilder $mergeBuilder)
    {
        $this->service = $service;
        $this->mergeBuilder = $mergeBuilder;
    }

    /**
     * Формирует данные для рендера.
     *
     * @return array<string, mixed>
     */
    public function buildViewModel(): array
    {
        $template = $this->service->getActiveTemplate();
        $noTemplate = !$template->canBeUsedForFill();

        $municipalityName = current_municipality_name() ?? 'Муниципальное образование';

        $headers = $template->getHeaders();
        $structure = $template->getStructure();
        $rowDefs = $structure['rows'] ?? [];
        $merges = $structure['merges'] ?? [];

        $columnsCount = count($headers);

        $layout = $this->mergeBuilder->build(
            is_array($rowDefs) ? $rowDefs : [],
            $columnsCount,
            is_array($merges) ? $merges : []
        );

        return [
            'template' => $template,
            'noTemplate' => $noTemplate,
            'municipalityName' => $municipalityName,
            'headers' => $headers,
            'rowDefs' => $rowDefs,
            'columnsCount' => $columnsCount,
            'mergeTopLeft' => $layout['mergeTopLeft'],
            'skipCells' => $layout['skipCells'],
        ];
    }
}

$service = new TemplateService($conn);
$page = new FillTablePage($service, new MergeLayoutBuilder());
$vm = $page->buildViewModel();

$template = $vm['template'];
$noTemplate = $vm['noTemplate'];
$municipalityName = $vm['municipalityName'];
$headers = $vm['headers'];
$rowDefs = $vm['rowDefs'];
$columnsCount = $vm['columnsCount'];
$mergeTopLeft = $vm['mergeTopLeft'];
$skipCells = $vm['skipCells'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Заполнение таблицы — ИССД</title>
    <link rel="stylesheet" href="styles.css">
    <script src="script.js" defer></script>
</head>

<body class="fill-form-page">
<header>
    <div class="brand">
        <div class="logo">
            <img src="default-logo_w152_fitted.webp" alt="Логотип" style="width:30%; height:100%; object-fit:contain;">
        </div>
        <span class="system-name">Информационная система сбора данных</span>
    </div>

    <nav class="centered-nav">
        <div class="nav-links">
            <a href="index.php">Главная</a>
            <a href="get_table.php">Заполнить форму</a>
        </div>
        <div class="user-municipality">
            <?= htmlspecialchars($municipalityName) ?>
        </div>
    </nav>
</header>

<main>
    <section>
        <h2>Заполнение формы</h2>

        <?php if ($noTemplate): ?>
            <div class="message message-error">
                Активный шаблон таблицы ещё не создан администратором.
            </div>
        <?php else: ?>

            <p class="main-text">
                Муниципальное образование: <strong><?= htmlspecialchars($municipalityName) ?></strong><br>
                Шаблон: <strong><?= htmlspecialchars($template->getName()) ?></strong>
            </p>

            <form id="data-form" method="post" action="save_table.php">
                <input type="hidden" name="template_id" value="<?= (int)$template->getId() ?>">

                <div class="table-scroll">
                    <table id="data-table">
                        <thead>
                        <tr>
                            <?php foreach ($headers as $h): ?>
                                <th><?= htmlspecialchars($h['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($rowDefs as $rIndex => $rowDef): ?>
                            <?php
                            if (is_array($rowDef) && array_key_exists('rowType', $rowDef) && array_key_exists('cells', $rowDef) && is_array($rowDef['cells'])) {
                                $rowType = $rowDef['rowType'] ?? 'normal';
                                $cells = $rowDef['cells'];
                            } else {
                                $rowType = 'normal';
                                $cells = is_array($rowDef) ? $rowDef : [];
                            }
                            ?>

                            <?php if ($rowType === 'comment'): ?>
                                <?php $commentValue = $cells['Комментарий'] ?? ''; ?>
                                <tr class="comment-row">
                                    <td colspan="<?= (int)$columnsCount ?>">
                                        <textarea name="cell[<?= (int)$rIndex ?>][Комментарий]"><?= htmlspecialchars($commentValue) ?></textarea>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <?php for ($cIndex = 0; $cIndex < $columnsCount; $cIndex++): ?>
                                        <?php
                                        if (!empty($skipCells[$rIndex][$cIndex])) {
                                            continue;
                                        }

                                        $h = $headers[$cIndex];
                                        $name = $h['name'];
                                        $type = $h['type'] ?? 'text';
                                        $readonly = !empty($h['readonly']);
                                        $value = $cells[$name] ?? '';

                                        $rowspan = 1;
                                        $colspan = 1;

                                        if (!empty($mergeTopLeft[$rIndex][$cIndex])) {
                                            $rowspan = (int)$mergeTopLeft[$rIndex][$cIndex]['rowSpan'];
                                            $colspan = (int)$mergeTopLeft[$rIndex][$cIndex]['colSpan'];
                                        }

                                        $attrs = '';
                                        if ($rowspan > 1) $attrs .= ' rowspan="' . $rowspan . '"';
                                        if ($colspan > 1) $attrs .= ' colspan="' . $colspan . '"';
                                        ?>
                                        <td<?= $attrs ?>>
                                            <input
                                                class="table-input"
                                                <?= $readonly ? 'readonly' : '' ?>
                                                type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                                name="cell[<?= (int)$rIndex ?>][<?= htmlspecialchars($name, ENT_QUOTES) ?>]"
                                                value="<?= htmlspecialchars($value) ?>"
                                            >
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="button" id="saveTableBtn">Сохранить и отправить</button>
            </form>

        <?php endif; ?>
    </section>
</main>

<div class="modal" id="tableSavedModal" style="display:none; align-items:center; justify-content:center;">
    <div class="modal-content" style="background:#fff; padding:20px; border-radius:12px; text-align:center; max-width:420px;">
        <span class="close" id="closeTableSavedModal" style="float:right; cursor:pointer;">&times;</span>
        <p id="tableSavedMessage" style="color:#000; margin:0;">Данные успешно сохранены.</p>
    </div>
</div>

<script>
/**
 * Мини-контроллер отправки формы заполнения (логика в методах).
 */
class FillFormSubmitter {
    /**
     * @param {HTMLFormElement} form
     * @param {HTMLButtonElement} button
     */
    constructor(form, button) {
        this.form = form;
        this.button = button;
    }

    /** Инициализация обработчиков. */
    init() {
        if (!this.form || !this.button) return;

        this.limitNumberInputs();
        this.button.addEventListener("click", () => this.onSaveClick());
        this.form.addEventListener("submit", (e) => e.preventDefault());
    }

    /** Ограничение ввода в number. */
    limitNumberInputs() {
        this.form.querySelectorAll('#data-table input[type="number"]').forEach(inp => {
            inp.addEventListener("input", () => {
                inp.value = inp.value.replace(/[^0-9.,-]/g, "");
            });
        });
    }

    /**
     * Валидация: все числовые поля (кроме текстовых колонок и комментария) должны быть заполнены и быть числом.
     * @returns {boolean}
     */
    validateTable() {
        let hasErrors = false;
        const inputs = this.form.querySelectorAll("#data-table input");
        inputs.forEach(i => i.classList.remove("input-error"));

        inputs.forEach(input => {
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
    }

    /** Нажатие “Сохранить и отправить”. */
    async onSaveClick() {
        if (!this.validateTable()) {
            alert("Не все числовые поля заполнены или заполнены некорректно.\nЗаполните все обязательные ячейки и повторите отправку.");
            return;
        }

        try {
            const formData = new FormData(this.form);
            const res = await fetch(this.form.action || "save_table.php", {
                method: "POST",
                body: formData,
                headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }
            });

            const contentType = res.headers.get("content-type") || "";
            if (!contentType.includes("application/json")) {
                const text = await res.text();
                alert("Сервер вернул не JSON (скорее всего ошибка PHP). Вот ответ:\n\n" + text);
                return;
            }

            const data = await res.json();
            alert(data.message || (data.success ? "Данные успешно сохранены." : "Ошибка сохранения данных."));
        } catch (err) {
            alert("Ошибка сети: " + err);
        }
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const submitter = new FillFormSubmitter(
        document.getElementById("data-form"),
        document.getElementById("saveTableBtn")
    );
    submitter.init();
});
</script>

</body>
</html>
