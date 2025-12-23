/**
 * Конструктор шаблонов таблицы для admin_view.php
 *
 * Возможности:
 *  - построение таблицы по указанным строкам/столбцам;
 *  - изменение названий столбцов;
 *  - выбор типа столбца: text / number;
 *  - признак "только чтение" для столбца, чтобы пользователь не мог отредактировать то что админ написал;
 *  - редактирование содержимого всех ячеек;
 *  - очистка содержимого таблицы (ячеек для заполнения данными от пользователя);
 *  - сброс к исходному виду шаблона;
 *  - объединение/разъединение выбранных ячеек;
 *  - удаление выбранных строк/столбцов;
 *  - сохранение шаблона через AJAX в save_template.php.
 */

document.addEventListener("DOMContentLoaded", () => {
    const table = document.getElementById("constructor-table");
    const templateNameInput = document.getElementById("template-name");
    const makeActiveCheckbox = document.getElementById("make-active-checkbox");
    const rowsCountInput = document.getElementById("rows-count");
    const colsCountInput = document.getElementById("cols-count");
    const generateBtn = document.getElementById("generate-table-btn");
    const clearBtn = document.getElementById("clear-table-btn");
    const resetBtn = document.getElementById("reset-table-btn");
    const saveBtn = document.getElementById("save-template-btn");
    const messagesDiv = document.getElementById("constructor-messages");

    const mergeBtn = document.getElementById("merge-cells-btn");
    const unmergeBtn = document.getElementById("unmerge-cells-btn");
    const deleteRowBtn = document.getElementById("delete-row-btn");
    const deleteColBtn = document.getElementById("delete-col-btn");
    const selectionInfo = document.getElementById("selection-info");

    if (!table || !templateNameInput) return;

    let headers = [];
    let rows = [];
    let merges = [];

    // Диапазон выделения
    let selectionStart = null;
    let selectionEnd = null;

    function showMessage(type, text) {
        messagesDiv.innerHTML = "";
        const div = document.createElement("div");
        div.className = "message " + (type === "success" ? "message-success" : "message-error");
        div.textContent = text;
        messagesDiv.appendChild(div);
    }

    function updateSelectionInfo(count) {
        if (selectionInfo) {
            selectionInfo.textContent = "Выделено ячеек: " + count;
        }
    }

    function clearSelection() {
        selectionStart = null;
        selectionEnd = null;
        const tds = table.querySelectorAll("td[data-row-index]");
        tds.forEach(td => td.classList.remove("cell-selected"));
        updateSelectionInfo(0);
    }

    function applySelectionStyles() {
        const tds = table.querySelectorAll("td[data-row-index]");
        tds.forEach(td => td.classList.remove("cell-selected"));

        if (!selectionStart || !selectionEnd) {
            updateSelectionInfo(0);
            return;
        }

        const startRow = Math.min(selectionStart.row, selectionEnd.row);
        const endRow   = Math.max(selectionStart.row, selectionEnd.row);
        const startCol = Math.min(selectionStart.col, selectionEnd.col);
        const endCol   = Math.max(selectionStart.col, selectionEnd.col);

        let count = 0;

        tds.forEach(td => {
            const r = parseInt(td.dataset.rowIndex, 10);
            const c = parseInt(td.dataset.colIndex, 10);
            if (isNaN(r) || isNaN(c)) return;

            if (r >= startRow && r <= endRow && c >= startCol && c <= endCol) {
                td.classList.add("cell-selected");
                count++;
            }
        });

        updateSelectionInfo(count);
    }

    // Очистка выделения кликом в свободное место страницы
    document.addEventListener("click", (e) => {
        const isCell = e.target.closest("#constructor-table td");
        const isHeader = e.target.closest("#constructor-table th");
        const isToolbar = e.target.closest(".constructor-toolbar") || e.target.closest(".merge-toolbar");
        if (!isCell && !isHeader && !isToolbar) {
            clearSelection();
        }
    });

    // Информация об объединении для ячейки
    function getMergeInfo(rowIndex, colIndex) {
        for (let i = 0; i < merges.length; i++) {
            const m  = merges[i];
            const sr = m.startRow;
            const sc = m.startCol;
            const er = sr + m.rowSpan - 1;
            const ec = sc + m.colSpan - 1;

            if (rowIndex >= sr && rowIndex <= er && colIndex >= sc && colIndex <= ec) {
                if (rowIndex === sr && colIndex === sc) {
                    return { merge: m, isTopLeft: true };
                }
                return { merge: m, isTopLeft: false };
            }
        }
        return null;
    }

    function onCellClick(e) {
        const td = e.target.closest("td");
        if (!td || !td.dataset.rowIndex) return;

        const rowIndex = parseInt(td.dataset.rowIndex, 10);
        const colIndex = parseInt(td.dataset.colIndex, 10);
        if (isNaN(rowIndex) || isNaN(colIndex)) return;

        if (!e.shiftKey || !selectionStart) {
            selectionStart = { row: rowIndex, col: colIndex };
            selectionEnd   = { row: rowIndex, col: colIndex };
        } else {
            selectionEnd = { row: rowIndex, col: colIndex };
        }

        applySelectionStyles();
    }

    /**
     * Инициализация из существующего шаблона (загруженного из БД)
     */
    function initFromExistingTemplate(dto) {
        templateNameInput.value = dto.template_name || dto.name || "Шаблон";

        headers = Array.isArray(dto.headers) ? dto.headers : [];
        const structure = dto.structure || {};
        rows   = Array.isArray(structure.rows)   ? structure.rows   : [];
        merges = Array.isArray(structure.merges) ? structure.merges : [];

        // если в шаблоне нет строк — сделаем дефолт
        if (!rows.length || !headers.length) {
            initDefaultTemplate();
            return;
        }

        rowsCountInput.value = rows.length;
        colsCountInput.value = headers.length;

        clearSelection();
        renderTable();
    }

    // исходный шаблон
    function initDefaultTemplate() {
        templateNameInput.value = "Новый шаблон";

        headers = [
            { name: "Показатели",          type: "text",   readonly: true  },
            { name: "Единица измерения",   type: "text",   readonly: true  },
            { name: "2022",                type: "number", readonly: false },
            { name: "2023",                type: "number", readonly: false },
            { name: "2024",                type: "number", readonly: false },
            { name: "2025",                type: "number", readonly: false }
        ];

        let rowsCount = parseInt(rowsCountInput.value, 10);
        if (!Number.isInteger(rowsCount) || rowsCount <= 0) rowsCount = 5;

        rows = [];
        const commentRowIndex = rowsCount - 1; // последняя строка — комментарий

        for (let i = 0; i < rowsCount; i++) {
            const cells = {};
            headers.forEach(h => { cells[h.name] = ""; });
            rows.push({
                rowType: i === commentRowIndex ? "comment" : "normal",
                cells: cells
            });
        }

        rowsCountInput.value = rows.length;
        colsCountInput.value = headers.length;

        merges = [];
        if (rows.length > 0) {
            merges.push({
                startRow: commentRowIndex,
                startCol: 0,
                rowSpan: 1,
                colSpan: headers.length
            });
        }
        clearSelection();
        renderTable();
    }

    // отрисовка
    function renderTable() {
        table.innerHTML = "";

        const thead = document.createElement("thead");
        const trHead = document.createElement("tr");

        const thType = document.createElement("th");
        thType.textContent = "Тип строки";
        trHead.appendChild(thType);

        headers.forEach((h, index) => {
            const th = document.createElement("th");

            const nameInput = document.createElement("input");
            nameInput.type = "text";
            nameInput.value = h.name || "";
            nameInput.className = "header-name-input";
            nameInput.dataset.index = String(index);
            nameInput.addEventListener("input", onHeaderNameChange);

            const typeSelect = document.createElement("select");
            typeSelect.className = "header-type-select";
            typeSelect.dataset.index = String(index);
            ["text", "number"].forEach(t => {
                const opt = document.createElement("option");
                opt.value = t;
                opt.textContent = t === "text" ? "Текст" : "Число";
                if (h.type === t) opt.selected = true;
                typeSelect.appendChild(opt);
            });
            typeSelect.addEventListener("change", onHeaderTypeChange);

            const readonlyLabel = document.createElement("label");
            readonlyLabel.className = "readonly-label";

            const readonlyCheckbox = document.createElement("input");
            readonlyCheckbox.type = "checkbox";
            readonlyCheckbox.checked = !!h.readonly;
            readonlyCheckbox.dataset.index = String(index);
            readonlyCheckbox.addEventListener("change", onHeaderReadonlyChange);

            readonlyLabel.appendChild(readonlyCheckbox);
            readonlyLabel.appendChild(document.createTextNode(" только чтение"));

            th.appendChild(nameInput);
            th.appendChild(document.createElement("br"));
            th.appendChild(typeSelect);
            th.appendChild(readonlyLabel);

            trHead.appendChild(th);
        });

        thead.appendChild(trHead);
        table.appendChild(thead);

        const tbody = document.createElement("tbody");

        rows.forEach((row, rIndex) => {
            const tr = document.createElement("tr");

            const tdType = document.createElement("td");
            const typeSelect = document.createElement("select");
            typeSelect.className = "row-type-select";
            typeSelect.dataset.rowIndex = String(rIndex);

            const optNormal = document.createElement("option");
            optNormal.value = "normal";
            optNormal.textContent = "Обычная";
            if (row.rowType === "normal") optNormal.selected = true;
            typeSelect.appendChild(optNormal);

            const optComment = document.createElement("option");
            optComment.value = "comment";
            optComment.textContent = "Коммент.";
            if (row.rowType === "comment") optComment.selected = true;
            typeSelect.appendChild(optComment);

            typeSelect.addEventListener("change", onRowTypeChange);
            tdType.appendChild(typeSelect);
            tr.appendChild(tdType);

            for (let cIndex = 0; cIndex < headers.length; cIndex++) {
                const mergeInfo = getMergeInfo(rIndex, cIndex);
                if (mergeInfo && !mergeInfo.isTopLeft) continue;

                const h = headers[cIndex];
                const td = document.createElement("td");
                td.dataset.rowIndex = String(rIndex);
                td.dataset.colIndex = String(cIndex);
                td.addEventListener("click", onCellClick);

                const input = document.createElement("input");
                input.type = h.type === "number" ? "number" : "text";
                // если столбец числовой — не даём вводить буквы
                if (h.type === "number") {
                    input.addEventListener("input", () => {
                        // только цифры, точка, запятая и минус
                        input.value = input.value.replace(/[^0-9.,-]/g, "");
                    });
                }
                const val = row.cells && Object.prototype.hasOwnProperty.call(row.cells, h.name)
                    ? row.cells[h.name]
                    : "";
                input.value = val;
                input.className = "cell-input";

                if (mergeInfo && mergeInfo.isTopLeft) {
                    if (mergeInfo.merge.rowSpan > 1) td.rowSpan = mergeInfo.merge.rowSpan;
                    if (mergeInfo.merge.colSpan > 1) td.colSpan = mergeInfo.merge.colSpan;
                }

                td.appendChild(input);
                tr.appendChild(td);
            }

            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        applySelectionStyles();
    }

    // Изменение заголовков
    function onHeaderNameChange(e) {
        const input = e.target;
        const index = parseInt(input.dataset.index, 10);
        if (!headers[index]) return;

        const oldName = headers[index].name;
        const newName = input.value.trim();
        if (!newName) {
            input.value = oldName;
            return;
        }

        headers[index].name = newName;

        rows.forEach(row => {
            if (row.cells && Object.prototype.hasOwnProperty.call(row.cells, oldName)) {
                row.cells[newName] = row.cells[oldName];
                delete row.cells[oldName];
            }
        });
    }

    function onHeaderTypeChange(e) {
        const select = e.target;
        const index = parseInt(select.dataset.index, 10);
        if (!headers[index]) return;
        headers[index].type = select.value === "number" ? "number" : "text";
    }

    function onHeaderReadonlyChange(e) {
        const checkbox = e.target;
        const index = parseInt(checkbox.dataset.index, 10);
        if (!headers[index]) return;
        headers[index].readonly = checkbox.checked;
    }

    function onRowTypeChange(e) {
        const select = e.target;
        const rIndex = parseInt(select.dataset.rowIndex, 10);
        if (!rows[rIndex]) return;

        const newType = select.value === "comment" ? "comment" : "normal";
        rows[rIndex].rowType = newType;

        // убираем старые объединения, которые целиком лежат в этой строке
        merges = merges.filter((m) => !(m.rowSpan === 1 && m.startRow === rIndex));

        // если это комментарий — объединяем всю строку (все данные столбцов)
        if (newType === "comment") {
            merges.push({
                startRow: rIndex,
                startCol: 0,
                rowSpan: 1,
                colSpan: headers.length
            });
        }

        clearSelection();
        renderTable();
    }

    // Генерация по количеству строк/столбцов
    function generateTableByCounts() {
        let rowsCount = parseInt(rowsCountInput.value, 10);
        let colsCount = parseInt(colsCountInput.value, 10);

        if (!Number.isInteger(rowsCount) || rowsCount <= 0) rowsCount = 5;
        if (!Number.isInteger(colsCount) || colsCount <= 0) colsCount = 4;

        if (colsCount > headers.length) {
            for (let i = headers.length; i < colsCount; i++) {
                headers.push({
                    name: "Столбец " + (i + 1),
                    type: "text",
                    readonly: false
                });
            }
        } else if (colsCount < headers.length) {
            headers = headers.slice(0, colsCount);
        }

        rows = [];
        const commentRowIndex = rowsCount - 1; // последняя строка — комментарий

        for (let r = 0; r < rowsCount; r++) {
            const cells = {};
            headers.forEach((h) => {
                cells[h.name] = "";
            });

            rows.push({
                rowType: r === commentRowIndex ? "comment" : "normal",
                cells: cells
            });
        }

        // авто-объединение строки комментария
        merges = [];
        if (rows.length > 0) {
            merges.push({
                startRow: commentRowIndex,
                startCol: 0,
                rowSpan: 1,
                colSpan: headers.length
            });
        }

        clearSelection();
        renderTable();
    }

    function clearTableContents() {
        rows.forEach(row => {
            Object.keys(row.cells || {}).forEach(key => {
                row.cells[key] = "";
            });
        });
        renderTable();
    }

    function resetToDefault() {
        initDefaultTemplate();
    }

    function syncRowsFromDom() {
        const bodyRows = table.querySelectorAll("tbody tr");
        rows = Array.from(bodyRows).map((tr, rIndex) => {
            const rowObj = { rowType: "normal", cells: {} };

            const typeSelect = tr.querySelector(".row-type-select");
            if (typeSelect) {
                rowObj.rowType = typeSelect.value === "comment" ? "comment" : "normal";
            }

            const inputs = tr.querySelectorAll("td[data-row-index] .cell-input");
            inputs.forEach(input => {
                const parentTd = input.closest("td");
                if (!parentTd) return;
                const cIndex = parseInt(parentTd.dataset.colIndex, 10);
                const header = headers[cIndex];
                if (!header) return;

                let value = input.value;
                if (header.type === "number" && value !== "") {
                    value = value.replace(",", ".");
                }
                rowObj.cells[header.name] = value;
            });

            return rowObj;
        });
    }

    function validateBeforeSave() {
        if (!templateNameInput.value.trim()) {
            showMessage("error", "Введите название шаблона.");
            return false;
        }
        if (!headers.length) {
            showMessage("error", "Добавьте хотя бы один столбец.");
            return false;
        }
        for (const h of headers) {
            if (!h.name || !h.name.trim()) {
                showMessage("error", "Имя столбца не может быть пустым.");
                return false;
            }
        }
        return true;
    }

    function mergeSelectedCells() {
        if (!selectionStart || !selectionEnd) {
            showMessage("error", "Сначала выделите диапазон ячеек (Shift + клик).");
            return;
        }

        const startRow = Math.min(selectionStart.row, selectionEnd.row);
        const endRow   = Math.max(selectionStart.row, selectionEnd.row);
        const startCol = Math.min(selectionStart.col, selectionEnd.col);
        const endCol   = Math.max(selectionStart.col, selectionEnd.col);

        if (startRow === endRow && startCol === endCol) {
            showMessage("error", "Нужно выделить хотя бы две ячейки для объединения.");
            return;
        }

        function intersects(m) {
            const msr = m.startRow;
            const msc = m.startCol;
            const mer = msr + m.rowSpan - 1;
            const mec = msc + m.colSpan - 1;
            return !(
                endRow < msr ||
                startRow > mer ||
                endCol < msc ||
                startCol > mec
            );
        }

        for (const m of merges) {
            if (intersects(m)) {
                showMessage("error",
                    "Выбранный диапазон пересекается с уже объединёнными ячейками. Сначала разъедините их."
                );
                return;
            }
        }

        merges.push({
            startRow: startRow,
            startCol: startCol,
            rowSpan: endRow - startRow + 1,
            colSpan: endCol - startCol + 1
        });

        clearSelection();
        renderTable();
        showMessage("success", "Ячейки объединены.");
    }

    function unmergeSelectedCells() {
        if (!selectionStart || !selectionEnd) {
            showMessage("error", "Сначала выделите диапазон ячеек.");
            return;
        }

        const startRow = Math.min(selectionStart.row, selectionEnd.row);
        const endRow   = Math.max(selectionStart.row, selectionEnd.row);
        const startCol = Math.min(selectionStart.col, selectionEnd.col);
        const endCol   = Math.max(selectionStart.col, selectionEnd.col);

        const beforeCount = merges.length;

        merges = merges.filter(m => {
            const msr = m.startRow;
            const msc = m.startCol;
            const mer = msr + m.rowSpan - 1;
            const mec = msc + m.colSpan - 1;

            const noOverlap =
                endRow < msr ||
                startRow > mer ||
                endCol < msc ||
                startCol > mec;

            return noOverlap;
        });

        if (beforeCount === merges.length) {
            showMessage("error", "В выделенном диапазоне нет объединённых ячеек.");
        } else {
            showMessage("success", "Объединения в выделенном диапазоне удалены.");
        }

        renderTable();
    }

    function deleteSelectedRows() {
        if (!selectionStart || !selectionEnd) {
            showMessage("error", "Сначала выделите хотя бы одну ячейку (по строкам).");
            return;
        }

        const startRow = Math.min(selectionStart.row, selectionEnd.row);
        const endRow   = Math.max(selectionStart.row, selectionEnd.row);
        const count    = endRow - startRow + 1;

        if (rows.length <= count) {
            showMessage("error", "Нельзя удалить все строки.");
            return;
        }

        rows.splice(startRow, count);
        merges = [];
        rowsCountInput.value = rows.length;

        clearSelection();
        renderTable();
        showMessage("success", "Строки удалены.");
    }

    function deleteSelectedCols() {
        if (!selectionStart || !selectionEnd) {
            showMessage("error", "Сначала выделите хотя бы одну ячейку (по столбцам).");
            return;
        }

        const startCol = Math.min(selectionStart.col, selectionEnd.col);
        const endCol   = Math.max(selectionStart.col, selectionEnd.col);
        const count    = endCol - startCol + 1;

        if (headers.length <= count) {
            showMessage("error", "Нельзя удалить все столбцы.");
            return;
        }

        const namesToRemove = headers.slice(startCol, endCol + 1).map(h => h.name);

        headers = headers.filter((_, idx) => idx < startCol || idx > endCol);

        rows.forEach(row => {
            const newCells = {};
            headers.forEach(h => {
                newCells[h.name] = row.cells[h.name] || "";
            });
            row.cells = newCells;
        });

        merges = [];
        colsCountInput.value = headers.length;

        clearSelection();
        renderTable();
        showMessage("success", "Столбцы удалены.");
    }

    async function saveTemplate() {
        syncRowsFromDom();
        if (!validateBeforeSave()) return;

        const payload = {
            template_name: templateNameInput.value.trim(),
            make_active: makeActiveCheckbox.checked ? 1 : 0,
            headers: headers,
            structure: {
                rows: rows,
                merges: merges
            }
        };

        try {
            const response = await fetch("save_template.php", {
                method: "POST",
                headers: { "Content-Type": "application/json;charset=utf-8" },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                showMessage("success", data.message || "Шаблон успешно сохранён.");
            } else {
                showMessage("error", data.message || "Ошибка при сохранении шаблона.");
            }
        } catch (err) {
            showMessage("error", "Ошибка сети: " + err);
        }
    }

    // Привязка кнопок
    generateBtn.addEventListener("click", generateTableByCounts);
    clearBtn.addEventListener("click", clearTableContents);
    resetBtn.addEventListener("click", resetToDefault);
    saveBtn.addEventListener("click", saveTemplate);

    if (mergeBtn)  mergeBtn.addEventListener("click", mergeSelectedCells);
    if (unmergeBtn) unmergeBtn.addEventListener("click", unmergeSelectedCells);
    if (deleteRowBtn) deleteRowBtn.addEventListener("click", deleteSelectedRows);
    if (deleteColBtn) deleteColBtn.addEventListener("click", deleteSelectedCols);

    // Инициализация: либо из БД, либо дефолт
    if (window.initialTemplate) {
        initFromExistingTemplate(window.initialTemplate);
    } else {
        initDefaultTemplate();
    }
});