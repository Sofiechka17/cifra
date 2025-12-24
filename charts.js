/**
 * Скрипт для админки:
 * - заполняет выпадающий список МО
 * - по выбранному МО показывает список заполненных таблиц
 * - по выбранной таблице запрашивает данные (chart_data.php)
 * - строит график в Chart.js
 * - третий список: выбрать один показатель или показать все
 */
document.addEventListener("DOMContentLoaded", () => {
  const moSelect = document.getElementById("moSelect");
  const filledSelect = document.getElementById("filledSelect");
  const indicatorSelect = document.getElementById("indicatorSelect");
  const canvas = document.getElementById("filledChart");

  if (!moSelect || !filledSelect || !indicatorSelect || !canvas || typeof Chart === "undefined") return;

  // вспомогательные функции
  // генерирует разные цвета для линий
  const hslColor = (i, total) => {
    const hue = Math.round((i * 360) / Math.max(total, 1));
    return `hsl(${hue}, 75%, 55%)`;
  };

  const toRowArray = (filledData) => {
    // filled_data может быть массивом или объектом с ключами "0","1",...
    if (Array.isArray(filledData)) return filledData;
    if (filledData && typeof filledData === "object") {
      return Object.keys(filledData)
        .sort((a, b) => Number(a) - Number(b))
        .map(k => filledData[k]);
    }
    return [];
  };

  // Определяем колонку "Показатели" (если нет — берём первый text столбец)
  const findIndicatorColumn = (headers) => {
    const byName = headers.find(h => (h?.name || "") === "Показатели");
    if (byName) return byName.name;

    const firstText = headers.find(h => (h?.type || "text") === "text");
    return firstText ? firstText.name : null;
  };

  const findNumericColumns = (headers) => {
    // Берём все number-колонки — они станут осью X на графике
    return headers.filter(h => (h?.type || "") === "number").map(h => h.name);
  };

  // Инициализ графика по умолчанию
  const ctx = canvas.getContext("2d");
  let chart = new Chart(ctx, {
    type: "line",
    data: {
      labels: ["2022", "2023", "2024"],
      datasets: [{
        label: "Демо-данные",
        data: [10, 15, 12]
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: true }
      }
    }
  });

  //переменные для текущих данных
  let allDatasets = [];//все линии(показатели)
  let labelsX = [];//ось Х(числовые колонки)
  let indicatorKey = null;//колонка Показатели
  let currentRows = [];//строки таблицы

  // заполняем список мо (данные приходят из admin_view.php через window.municipalitiesList)
  const municipalities = Array.isArray(window.municipalitiesList) ? window.municipalitiesList : [];
  municipalities.forEach(mo => {
    const opt = document.createElement("option");
    opt.value = String(mo.municipality_id);
    opt.textContent = mo.municipality_name;
    moSelect.appendChild(opt);
  });

  // список заполненных таблиц
  const filledRows = Array.isArray(window.filledRowsForJs) ? window.filledRowsForJs : [];

  // перестраиваем список заполненных таблиц, когда выбрали МО
  function rebuildFilledSelect(municipalityId) {
    filledSelect.innerHTML = "";
    indicatorSelect.innerHTML = "";
    indicatorSelect.disabled = true;

    const baseOpt = document.createElement("option");
    baseOpt.value = "";
    baseOpt.textContent = "-- выберите заполненную таблицу --";
    filledSelect.appendChild(baseOpt);

    const list = filledRows.filter(r => String(r.municipality_id) === String(municipalityId));
    list.forEach(r => {
      const opt = document.createElement("option");
      opt.value = String(r.filled_data_id);
      opt.textContent = `ID ${r.filled_data_id} — ${r.template_name} — ${r.user_full_name} — ${r.filled_date}`;
      filledSelect.appendChild(opt);
    });

    filledSelect.disabled = list.length === 0;
    if (list.length === 0) {
      const o = document.createElement("option");
      o.value = "";
      o.textContent = "-- для этого МО нет заполненных таблиц --";
      filledSelect.appendChild(o);
    }
  }

  // Перерисовать график
  function setChart(labels, datasets) {
    chart.data.labels = labels;
    chart.data.datasets = datasets;
    chart.update();
  }

  // Заполнить третий комбобокс (Показатель)
  function rebuildIndicatorSelect() {
    indicatorSelect.innerHTML = "";

    const optAll = document.createElement("option");
    optAll.value = "__all__";
    optAll.textContent = "Все показатели";
    indicatorSelect.appendChild(optAll);

   // берём названия показателей из колонки indicatorKey
    const names = currentRows
      .map((row, idx) => {
        const val = row && indicatorKey ? row[indicatorKey] : "";
        const text = (val || "").toString().trim();
        return { idx, name: text || `Строка #${idx + 1}` };
      });

    names.forEach(n => {
      const opt = document.createElement("option");
      opt.value = String(n.idx);
      opt.textContent = n.name;
      indicatorSelect.appendChild(opt);
    });

    indicatorSelect.disabled = false;
    indicatorSelect.value = "__all__";
  }

  // Строим линии для всех показателей
  function buildAllDatasets() {
    const total = currentRows.length;
    return currentRows.map((row, i) => {
      const titleRaw = (row && indicatorKey) ? row[indicatorKey] : "";
      const title = (titleRaw || `Строка #${i + 1}`).toString();

      const data = labelsX.map(col => {
        const v = row ? row[col] : "";
        const n = Number(String(v ?? "").replace(",", "."));
        return Number.isFinite(n) ? n : null;
      });

      return {
        label: title,
        data,
        borderColor: hslColor(i, total),
        backgroundColor: hslColor(i, total),
        spanGaps: true
      };
    });
  }

  // Строим одну линию для выбранного показателя
  function buildSingleDataset(rowIndex) {
    const row = currentRows[rowIndex];
    const titleRaw = (row && indicatorKey) ? row[indicatorKey] : "";
    const title = (titleRaw || `Строка #${rowIndex + 1}`).toString();

    const data = labelsX.map(col => {
      const v = row ? row[col] : "";
      const n = Number(String(v ?? "").replace(",", "."));
      return Number.isFinite(n) ? n : null;
    });

    return [{
      label: title,
      data,
      spanGaps: true
    }];
  }

  // Загрузка данных и построение графика
  async function loadFilledAndDraw(filledId) {
    const res = await fetch(`chart_data.php?filled_id=${encodeURIComponent(filledId)}`);
    const json = await res.json();

    if (!json.success) {
      alert(json.message || "Ошибка загрузки данных");
      return;
    }

    const headers = Array.isArray(json.headers) ? json.headers : [];
    const filledData = json.filled_data;

    indicatorKey = findIndicatorColumn(headers);//показатели
    labelsX = findNumericColumns(headers);//числовые колонки ось х

    currentRows = toRowArray(filledData);

    // Убираем строку-комментарий 
    currentRows = currentRows.filter(r => r && typeof r === "object" && !("Комментарий" in r));

    if (!indicatorKey || labelsX.length === 0) {
      alert("Не удалось определить: колонку 'Показатели' или числовые колонки для графика.");
      return;
    }

    allDatasets = buildAllDatasets();
    rebuildIndicatorSelect();

    // по умолчанию показываем все показатели
    setChart(labelsX, allDatasets);
  }

  // события
  moSelect.addEventListener("change", () => {
    const moId = moSelect.value;
    if (!moId) {
      filledSelect.disabled = true;
      indicatorSelect.disabled = true;
      return;
    }
    rebuildFilledSelect(moId);
  });

  filledSelect.addEventListener("change", async () => {
    const filledId = filledSelect.value;
    indicatorSelect.disabled = true;

    if (!filledId) return;

    // сразу показываем все данные (разными цветами)
    await loadFilledAndDraw(filledId);
  });

  indicatorSelect.addEventListener("change", () => {
    const v = indicatorSelect.value;
    if (v === "__all__") {
      setChart(labelsX, allDatasets);
      return;
    }

    const idx = Number(v);
    if (!Number.isFinite(idx) || idx < 0 || idx >= currentRows.length) return;

    setChart(labelsX, buildSingleDataset(idx));
  });
});
