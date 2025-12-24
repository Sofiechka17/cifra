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
 /**
  * Класс, который показывает пользователю сообщения на странице
  * (успех или ошибка).
  */
  class MessageBus {
    /**
     * @param {HTMLElement} container Контейнер сообщений.
     */
    constructor(container) {
      this.container = container;
    }

    /**
     * Показать сообщение.
     * @param {"success"|"error"} type Тип сообщения.
     * @param {string} text Текст сообщения.
     */
    show(type, text) {
      if (!this.container) return;
      this.container.innerHTML = "";
      const div = document.createElement("div");
      div.className = "message " + (type === "success" ? "message-success" : "message-error");
      div.textContent = text;
      this.container.appendChild(div);
    }
  }

  /**
   * API-клиент сохранения шаблонов.
   */
  class TemplateApi {
    /**
     * Сохраняет шаблон.
     * @param {Object} payload Данные шаблона.
     * @returns {Promise<Object>} JSON-ответ.
     */
    async save(payload) {
      const response = await fetch("save_template.php", {
        method: "POST",
        headers: { "Content-Type": "application/json;charset=utf-8" },
        body: JSON.stringify(payload)
      });
      return response.json();
    }
  }

  /**
   * Управление выделением диапазона ячеек (Shift+клик).
   */
  class SelectionManager {
    /**
     * @param {HTMLTableElement} table Таблица конструктора.
     * @param {HTMLElement|null} infoLabel Метка "выделено ячеек".
     */
    constructor(table, infoLabel) {
      this.table = table;
      this.infoLabel = infoLabel;

      /** @type {{row:number, col:number}|null} */
      this.start = null;
      /** @type {{row:number, col:number}|null} */
      this.end = null;
    }

    /**
     * Сбрасывает выделение.
     */
    clear() {
      this.start = null;
      this.end = null;
      this._clearStyles();
      this._setCount(0);
    }

    /**
     * Устанавливает старт/конец выделения по клику.
     * @param {number} rowIndex Индекс строки.
     * @param {number} colIndex Индекс столбца.
     * @param {boolean} extend Признак Shift.
     */
    setPoint(rowIndex, colIndex, extend) {
      if (!extend || !this.start) {
        this.start = { row: rowIndex, col: colIndex };
        this.end = { row: rowIndex, col: colIndex };
      } else {
        this.end = { row: rowIndex, col: colIndex };
      }
      this.applyStyles();
    }

    /**
     * Подсвечивает выделенные ячейки
     */
    applyStyles() {
      this._clearStyles();
      if (!this.start || !this.end) {
        this._setCount(0);
        return;
      }

      const startRow = Math.min(this.start.row, this.end.row);
      const endRow = Math.max(this.start.row, this.end.row);
      const startCol = Math.min(this.start.col, this.end.col);
      const endCol = Math.max(this.start.col, this.end.col);

      const tds = this.table.querySelectorAll("td[data-row-index]");
      let count = 0;

      tds.forEach((td) => {
        const r = parseInt(td.dataset.rowIndex, 10);
        const c = parseInt(td.dataset.colIndex, 10);
        if (Number.isNaN(r) || Number.isNaN(c)) return;

        if (r >= startRow && r <= endRow && c >= startCol && c <= endCol) {
          td.classList.add("cell-selected");
          count++;
        }
      });

      this._setCount(count);
    }

    /**
     * Возвращает нормализованный прямоугольник выделения.
     * @returns {{startRow:number,endRow:number,startCol:number,endCol:number}|null}
     */
    getRect() {
      if (!this.start || !this.end) return null;
      return {
        startRow: Math.min(this.start.row, this.end.row),
        endRow: Math.max(this.start.row, this.end.row),
        startCol: Math.min(this.start.col, this.end.col),
        endCol: Math.max(this.start.col, this.end.col),
      };
    }

    /** Удаляет стили. */
    _clearStyles() {
      const tds = this.table.querySelectorAll("td[data-row-index]");
      tds.forEach((td) => td.classList.remove("cell-selected"));
    }

    /**
     * @param {number} n Кол-во выделенных ячеек.
     */
    _setCount(n) {
      if (this.infoLabel) this.infoLabel.textContent = "Выделено ячеек: " + n;
    }
  }

  /**
   * Главный класс приложения конструктора.
   */
  class TemplateConstructorApp {
    /**
     * @param {Object} deps Зависимости.
     * @param {HTMLTableElement} deps.table Таблица.
     * @param {HTMLInputElement} deps.templateNameInput Поле названия.
     * @param {HTMLInputElement} deps.makeActiveCheckbox Чекбокс "активный".
     * @param {HTMLInputElement} deps.rowsCountInput Кол-во строк.
     * @param {HTMLInputElement} deps.colsCountInput Кол-во столбцов.
     * @param {HTMLButtonElement} deps.generateBtn Кнопка генерации.
     * @param {HTMLButtonElement} deps.clearBtn Кнопка очистки.
     * @param {HTMLButtonElement} deps.resetBtn Кнопка сброса.
     * @param {HTMLButtonElement} deps.saveBtn Кнопка сохранения.
     * @param {HTMLButtonElement|null} deps.mergeBtn Кнопка обьединить ячейки.
     * @param {HTMLButtonElement|null} deps.unmergeBtn Кнопка разьединить.
     * @param {HTMLButtonElement|null} deps.deleteRowBtn Кнопка удаления строк.
     * @param {HTMLButtonElement|null} deps.deleteColBtn Кнопка удаления столбцов.
     * @param {MessageBus} deps.messages Сообщения.
     * @param {SelectionManager} deps.selection Выделение.
     * @param {TemplateApi} deps.api API.
     */
    constructor(deps) {
      this.table = deps.table;
      this.templateNameInput = deps.templateNameInput;
      this.makeActiveCheckbox = deps.makeActiveCheckbox;
      this.rowsCountInput = deps.rowsCountInput;
      this.colsCountInput = deps.colsCountInput;

      this.generateBtn = deps.generateBtn;
      this.clearBtn = deps.clearBtn;
      this.resetBtn = deps.resetBtn;
      this.saveBtn = deps.saveBtn;

      this.mergeBtn = deps.mergeBtn;
      this.unmergeBtn = deps.unmergeBtn;
      this.deleteRowBtn = deps.deleteRowBtn;
      this.deleteColBtn = deps.deleteColBtn;

      this.messages = deps.messages;
      this.selection = deps.selection;
      this.api = deps.api;

      /** @type {Array<{name:string,type:"text"|"number",readonly:boolean}>} */
      this.headers = [];
      /** @type {Array<{rowType:"normal"|"comment", cells:Object}>} */
      this.rows = [];
      /** @type {Array<{startRow:number,startCol:number,rowSpan:number,colSpan:number}>} */
      this.merges = [];
    }

    /** Инициализирует приложение. */
    init() {
      this._bindGlobalClearSelection();
      this._bindButtons();

      if (window.initialTemplate) {
        this.initFromExistingTemplate(window.initialTemplate);
      } else {
        this.initDefaultTemplate();
      }
    }

    /** клик вне таблицы снимает выделение. */
    _bindGlobalClearSelection() {
      document.addEventListener("click", (e) => {
        const isCell = e.target.closest("#constructor-table td");
        const isHeader = e.target.closest("#constructor-table th");
        const isToolbar = e.target.closest(".constructor-toolbar") || e.target.closest(".merge-toolbar");
        if (!isCell && !isHeader && !isToolbar) {
          this.selection.clear();
        }
      });
    }

    /** Назначает кнопкам действия при нажатии (что делать по клику). */
    _bindButtons() {
      this.generateBtn.addEventListener("click", () => this.generateTableByCounts());
      this.clearBtn.addEventListener("click", () => this.clearTableContents());
      this.resetBtn.addEventListener("click", () => this.resetToDefault());
      this.saveBtn.addEventListener("click", () => this.saveTemplate());

      if (this.mergeBtn) this.mergeBtn.addEventListener("click", () => this.mergeSelectedCells());
      if (this.unmergeBtn) this.unmergeBtn.addEventListener("click", () => this.unmergeSelectedCells());
      if (this.deleteRowBtn) this.deleteRowBtn.addEventListener("click", () => this.deleteSelectedRows());
      if (this.deleteColBtn) this.deleteColBtn.addEventListener("click", () => this.deleteSelectedCols());
    }

    /**
     * Инициализация из существующего шаблона (загруженного из БД).
     * @param {Object} dto DTO шаблона.
     */
    initFromExistingTemplate(dto) {
      this.templateNameInput.value = dto.template_name || dto.name || "Шаблон";

      this.headers = Array.isArray(dto.headers) ? dto.headers : [];
      const structure = dto.structure || {};
      this.rows = Array.isArray(structure.rows) ? structure.rows : [];
      this.merges = Array.isArray(structure.merges) ? structure.merges : [];

      if (!this.rows.length || !this.headers.length) {
        this.initDefaultTemplate();
        return;
      }

      this.rowsCountInput.value = String(this.rows.length);
      this.colsCountInput.value = String(this.headers.length);
      this.selection.clear();
      this.renderTable();
    }

    /** Инициализация дефолтного шаблона. */
    initDefaultTemplate() {
      this.templateNameInput.value = "Новый шаблон";
      this.headers = [
        { name: "Показатели", type: "text", readonly: true },
        { name: "Единица измерения", type: "text", readonly: true },
        { name: "2022", type: "number", readonly: false },
        { name: "2023", type: "number", readonly: false },
        { name: "2024", type: "number", readonly: false },
        { name: "2025", type: "number", readonly: false },
      ];

      let rowsCount = parseInt(this.rowsCountInput.value, 10);
      if (!Number.isInteger(rowsCount) || rowsCount <= 0) rowsCount = 5;

      this.rows = [];
      const commentRowIndex = rowsCount - 1;

      for (let i = 0; i < rowsCount; i++) {
        const cells = {};
        this.headers.forEach((h) => (cells[h.name] = ""));
        this.rows.push({ rowType: i === commentRowIndex ? "comment" : "normal", cells });
      }

      this.rowsCountInput.value = String(this.rows.length);
      this.colsCountInput.value = String(this.headers.length);

      this.merges = [];
      if (this.rows.length > 0) {
        this.merges.push({ startRow: commentRowIndex, startCol: 0, rowSpan: 1, colSpan: this.headers.length });
      }

      this.selection.clear();
      this.renderTable();
    }

    /**
     * Возвращает информацию об объединении для ячейки.
     * @param {number} rowIndex Индекс строки.
     * @param {number} colIndex Индекс столбца.
     * @returns {{merge:Object,isTopLeft:boolean}|null}
     */
    getMergeInfo(rowIndex, colIndex) {
      for (let i = 0; i < this.merges.length; i++) {
        const m = this.merges[i];
        const sr = m.startRow;
        const sc = m.startCol;
        const er = sr + m.rowSpan - 1;
        const ec = sc + m.colSpan - 1;

        if (rowIndex >= sr && rowIndex <= er && colIndex >= sc && colIndex <= ec) {
          if (rowIndex === sr && colIndex === sc) return { merge: m, isTopLeft: true };
          return { merge: m, isTopLeft: false };
        }
      }
      return null;
    }

    /**
     * Обработчик клика по ячейке.
     * @param {MouseEvent} e
     */
    onCellClick(e) {
      const td = e.target.closest("td");
      if (!td || !td.dataset.rowIndex) return;

      const rowIndex = parseInt(td.dataset.rowIndex, 10);
      const colIndex = parseInt(td.dataset.colIndex, 10);
      if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) return;

      this.selection.setPoint(rowIndex, colIndex, e.shiftKey);
    }

    /** Отрисовывает таблицу на основе headers/rows/merges. */
    renderTable() {
      this.table.innerHTML = "";

      const thead = document.createElement("thead");
      const trHead = document.createElement("tr");

      const thType = document.createElement("th");
      thType.textContent = "Тип строки";
      trHead.appendChild(thType);

      this.headers.forEach((h, index) => {
        const th = document.createElement("th");

        const nameInput = document.createElement("input");
        nameInput.type = "text";
        nameInput.value = h.name || "";
        nameInput.className = "header-name-input";
        nameInput.dataset.index = String(index);
        nameInput.addEventListener("input", (e) => this.onHeaderNameChange(e));

        const typeSelect = document.createElement("select");
        typeSelect.className = "header-type-select";
        typeSelect.dataset.index = String(index);
        ["text", "number"].forEach((t) => {
          const opt = document.createElement("option");
          opt.value = t;
          opt.textContent = t === "text" ? "Текст" : "Число";
          if (h.type === t) opt.selected = true;
          typeSelect.appendChild(opt);
        });
        typeSelect.addEventListener("change", (e) => this.onHeaderTypeChange(e));

        const readonlyLabel = document.createElement("label");
        readonlyLabel.className = "readonly-label";

        const readonlyCheckbox = document.createElement("input");
        readonlyCheckbox.type = "checkbox";
        readonlyCheckbox.checked = !!h.readonly;
        readonlyCheckbox.dataset.index = String(index);
        readonlyCheckbox.addEventListener("change", (e) => this.onHeaderReadonlyChange(e));

        readonlyLabel.appendChild(readonlyCheckbox);
        readonlyLabel.appendChild(document.createTextNode(" только чтение"));

        th.appendChild(nameInput);
        th.appendChild(document.createElement("br"));
        th.appendChild(typeSelect);
        th.appendChild(readonlyLabel);

        trHead.appendChild(th);
      });

      thead.appendChild(trHead);
      this.table.appendChild(thead);

      const tbody = document.createElement("tbody");

      this.rows.forEach((row, rIndex) => {
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

        typeSelect.addEventListener("change", (e) => this.onRowTypeChange(e));

        tdType.appendChild(typeSelect);
        tr.appendChild(tdType);

        for (let cIndex = 0; cIndex < this.headers.length; cIndex++) {
          const mergeInfo = this.getMergeInfo(rIndex, cIndex);
          if (mergeInfo && !mergeInfo.isTopLeft) continue;

          const h = this.headers[cIndex];

          const td = document.createElement("td");
          td.dataset.rowIndex = String(rIndex);
          td.dataset.colIndex = String(cIndex);
          td.addEventListener("click", (e) => this.onCellClick(e));

          const input = document.createElement("input");
          input.type = h.type === "number" ? "number" : "text";

          if (h.type === "number") {
            input.addEventListener("input", () => {
              input.value = input.value.replace(/[^0-9.,-]/g, "");
            });
          }

          const val = row.cells && Object.prototype.hasOwnProperty.call(row.cells, h.name) ? row.cells[h.name] : "";
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

      this.table.appendChild(tbody);
      this.selection.applyStyles();
    }

    /**
     * Меняет имя заголовка 
     * @param {Event} e
     */
    onHeaderNameChange(e) {
      const input = e.target;
      const index = parseInt(input.dataset.index, 10);
      if (!this.headers[index]) return;

      const oldName = this.headers[index].name;
      const newName = input.value.trim();

      if (!newName) {
        input.value = oldName;
        return;
      }

      this.headers[index].name = newName;

      this.rows.forEach((row) => {
        if (row.cells && Object.prototype.hasOwnProperty.call(row.cells, oldName)) {
          row.cells[newName] = row.cells[oldName];
          delete row.cells[oldName];
        }
      });
    }

    /**
     * Меняет тип заголовка.
     * @param {Event} e
     */
    onHeaderTypeChange(e) {
      const select = e.target;
      const index = parseInt(select.dataset.index, 10);
      if (!this.headers[index]) return;

      this.headers[index].type = select.value === "number" ? "number" : "text";
    }

    /**
     * Меняет readonly заголовка.
     * @param {Event} e
     */
    onHeaderReadonlyChange(e) {
      const checkbox = e.target;
      const index = parseInt(checkbox.dataset.index, 10);
      if (!this.headers[index]) return;

      this.headers[index].readonly = checkbox.checked;
    }

    /**
     * Меняет тип строки (normal/comment) и корректирует merges.
     * @param {Event} e
     */
    onRowTypeChange(e) {
      const select = e.target;
      const rIndex = parseInt(select.dataset.rowIndex, 10);
      if (!this.rows[rIndex]) return;

      const newType = select.value === "comment" ? "comment" : "normal";
      this.rows[rIndex].rowType = newType;

      // убираем старые объединения, которые целиком лежат в этой строке
      this.merges = this.merges.filter((m) => !(m.rowSpan === 1 && m.startRow === rIndex));

      // если это комментарий — объединяем всю строку (все данные столбцов)
      if (newType === "comment") {
        this.merges.push({ startRow: rIndex, startCol: 0, rowSpan: 1, colSpan: this.headers.length });
      }

      this.selection.clear();
      this.renderTable();
    }

    /** Генерация таблицы по количеству строк/столбцов. */
    generateTableByCounts() {
      let rowsCount = parseInt(this.rowsCountInput.value, 10);
      let colsCount = parseInt(this.colsCountInput.value, 10);

      if (!Number.isInteger(rowsCount) || rowsCount <= 0) rowsCount = 5;
      if (!Number.isInteger(colsCount) || colsCount <= 0) colsCount = 4;

      if (colsCount > this.headers.length) {
        for (let i = this.headers.length; i < colsCount; i++) {
          this.headers.push({ name: "Столбец " + (i + 1), type: "text", readonly: false });
        }
      } else if (colsCount < this.headers.length) {
        this.headers = this.headers.slice(0, colsCount);
      }

      this.rows = [];
      const commentRowIndex = rowsCount - 1;

      for (let r = 0; r < rowsCount; r++) {
        const cells = {};
        this.headers.forEach((h) => (cells[h.name] = ""));
        this.rows.push({ rowType: r === commentRowIndex ? "comment" : "normal", cells });
      }

      this.merges = [];
      if (this.rows.length > 0) {
        this.merges.push({ startRow: commentRowIndex, startCol: 0, rowSpan: 1, colSpan: this.headers.length });
      }

      this.selection.clear();
      this.renderTable();
    }

    /** Очищает содержимое таблицы (все ячейки). */
    clearTableContents() {
      this.rows.forEach((row) => {
        Object.keys(row.cells || {}).forEach((key) => {
          row.cells[key] = "";
        });
      });
      this.renderTable();
    }

    /** Сброс к дефолтному виду. */
    resetToDefault() {
      this.initDefaultTemplate();
    }

    /**
     * Считывает все введённые значения из таблицы на странице
     * и обновляет this.rows перед сохранением.
     */
    syncRowsFromDom() {
      const bodyRows = this.table.querySelectorAll("tbody tr");

      this.rows = Array.from(bodyRows).map((tr) => {
        const rowObj = { rowType: "normal", cells: {} };

        const typeSelect = tr.querySelector(".row-type-select");
        if (typeSelect) rowObj.rowType = typeSelect.value === "comment" ? "comment" : "normal";

        const inputs = tr.querySelectorAll("td[data-row-index] .cell-input");
        inputs.forEach((input) => {
          const parentTd = input.closest("td");
          if (!parentTd) return;

          const cIndex = parseInt(parentTd.dataset.colIndex, 10);
          const header = this.headers[cIndex];
          if (!header) return;

          let value = input.value;
          if (header.type === "number" && value !== "") value = value.replace(",", ".");
          rowObj.cells[header.name] = value;
        });

        return rowObj;
      });
    }

    /**
     * Валидация перед сохранением.
     * @returns {boolean}
     */
    validateBeforeSave() {
      if (!this.templateNameInput.value.trim()) {
        this.messages.show("error", "Введите название шаблона.");
        return false;
      }
      if (!this.headers.length) {
        this.messages.show("error", "Добавьте хотя бы один столбец.");
        return false;
      }
      for (const h of this.headers) {
        if (!h.name || !h.name.trim()) {
          this.messages.show("error", "Имя столбца не может быть пустым.");
          return false;
        }
      }
      return true;
    }

    /** Объединяет выделенные ячейки. */
    mergeSelectedCells() {
      const rect = this.selection.getRect();
      if (!rect) {
        this.messages.show("error", "Сначала выделите диапазон ячеек (Shift + клик).");
        return;
      }

      const { startRow, endRow, startCol, endCol } = rect;
      if (startRow === endRow && startCol === endCol) {
        this.messages.show("error", "Нужно выделить хотя бы две ячейки для объединения.");
        return;
      }

      const intersects = (m) => {
        const msr = m.startRow;
        const msc = m.startCol;
        const mer = msr + m.rowSpan - 1;
        const mec = msc + m.colSpan - 1;
        return !(endRow < msr || startRow > mer || endCol < msc || startCol > mec);
      };

      for (const m of this.merges) {
        if (intersects(m)) {
          this.messages.show("error", "Выбранный диапазон пересекается с уже объединёнными ячейками. Сначала разъедините их.");
          return;
        }
      }

      this.merges.push({
        startRow,
        startCol,
        rowSpan: endRow - startRow + 1,
        colSpan: endCol - startCol + 1,
      });

      this.selection.clear();
      this.renderTable();
      this.messages.show("success", "Ячейки объединены.");
    }

    /** Разъединяет объединённые ячейки в выделенном диапазоне. */
    unmergeSelectedCells() {
      const rect = this.selection.getRect();
      if (!rect) {
        this.messages.show("error", "Сначала выделите диапазон ячеек.");
        return;
      }

      const { startRow, endRow, startCol, endCol } = rect;

      const beforeCount = this.merges.length;
      this.merges = this.merges.filter((m) => {
        const msr = m.startRow;
        const msc = m.startCol;
        const mer = msr + m.rowSpan - 1;
        const mec = msc + m.colSpan - 1;

        const noOverlap = endRow < msr || startRow > mer || endCol < msc || startCol > mec;
        return noOverlap;
      });

      if (beforeCount === this.merges.length) {
        this.messages.show("error", "В выделенном диапазоне нет объединённых ячеек.");
      } else {
        this.messages.show("success", "Объединения в выделенном диапазоне удалены.");
      }

      this.renderTable();
    }

    /** Удаляет выделенные строки. */
    deleteSelectedRows() {
      const rect = this.selection.getRect();
      if (!rect) {
        this.messages.show("error", "Сначала выделите хотя бы одну ячейку (по строкам).");
        return;
      }

      const startRow = rect.startRow;
      const endRow = rect.endRow;
      const count = endRow - startRow + 1;

      if (this.rows.length <= count) {
        this.messages.show("error", "Нельзя удалить все строки.");
        return;
      }

      this.rows.splice(startRow, count);
      this.merges = [];
      this.rowsCountInput.value = String(this.rows.length);

      this.selection.clear();
      this.renderTable();
      this.messages.show("success", "Строки удалены.");
    }

    /** Удаляет выделенные столбцы. */
    deleteSelectedCols() {
      const rect = this.selection.getRect();
      if (!rect) {
        this.messages.show("error", "Сначала выделите хотя бы одну ячейку (по столбцам).");
        return;
      }

      const startCol = rect.startCol;
      const endCol = rect.endCol;
      const count = endCol - startCol + 1;

      if (this.headers.length <= count) {
        this.messages.show("error", "Нельзя удалить все столбцы.");
        return;
      }

      const namesToRemove = this.headers.slice(startCol, endCol + 1).map((h) => h.name);

      this.headers = this.headers.filter((_, idx) => idx < startCol || idx > endCol);

      this.rows.forEach((row) => {
        const newCells = {};
        this.headers.forEach((h) => {
          newCells[h.name] = row.cells[h.name] || "";
        });

        // удаляем старые ключи 
        namesToRemove.forEach((n) => delete row.cells[n]);

        row.cells = newCells;
      });

      this.merges = [];
      this.colsCountInput.value = String(this.headers.length);

      this.selection.clear();
      this.renderTable();
      this.messages.show("success", "Столбцы удалены.");
    }

    /** Сохраняет шаблон в БД через AJAX. */
    async saveTemplate() {
      this.syncRowsFromDom();
      if (!this.validateBeforeSave()) return;

      const payload = {
        template_name: this.templateNameInput.value.trim(),
        make_active: this.makeActiveCheckbox.checked ? 1 : 0,
        headers: this.headers,
        structure: { rows: this.rows, merges: this.merges },
      };

      try {
        const data = await this.api.save(payload);
        if (data.success) this.messages.show("success", data.message || "Шаблон успешно сохранён.");
        else this.messages.show("error", data.message || "Ошибка при сохранении шаблона.");
      } catch (err) {
        this.messages.show("error", "Ошибка сети: " + err);
      }
    }
  }

  // --- Инициализация ---
  const table = document.getElementById("constructor-table");
  const templateNameInput = document.getElementById("template-name");
  if (!table || !templateNameInput) return;

  const messages = new MessageBus(document.getElementById("constructor-messages"));
  const selection = new SelectionManager(table, document.getElementById("selection-info"));
  const api = new TemplateApi();

  const app = new TemplateConstructorApp({
    table,
    templateNameInput,
    makeActiveCheckbox: document.getElementById("make-active-checkbox"),
    rowsCountInput: document.getElementById("rows-count"),
    colsCountInput: document.getElementById("cols-count"),
    generateBtn: document.getElementById("generate-table-btn"),
    clearBtn: document.getElementById("clear-table-btn"),
    resetBtn: document.getElementById("reset-table-btn"),
    saveBtn: document.getElementById("save-template-btn"),
    mergeBtn: document.getElementById("merge-cells-btn"),
    unmergeBtn: document.getElementById("unmerge-cells-btn"),
    deleteRowBtn: document.getElementById("delete-row-btn"),
    deleteColBtn: document.getElementById("delete-col-btn"),
    messages,
    selection,
    api,
  });

  app.init();
});
