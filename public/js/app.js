(() => {
    const state = {
        activeConnectionId: null,
        currentTable: null,
        currentPage: 1,
        lastPage: 1,
        primaryKey: null,
        columnMeta: [],
        editRowData: null,
    };

    const elements = {
        alertArea: document.getElementById("alertArea"),
        sidebar: document.getElementById("sidebar"),
        toggleSidebar: document.getElementById("toggleSidebar"),
        themeToggle: document.getElementById("themeToggle"),
        addDatabaseForm: document.getElementById("addDatabaseForm"),
        savedConnections: document.getElementById("savedConnections"),
        connectBtn: document.getElementById("connectBtn"),
        databaseTree: document.getElementById("databaseTree"),
        sqlInput: document.getElementById("sqlInput"),
        executeQueryBtn: document.getElementById("executeQueryBtn"),
        queryResult: document.getElementById("queryResult"),
        dangerSwitch: document.getElementById("dangerSwitch"),
        queryHistoryBody: document.getElementById("queryHistoryBody"),
        selectedTableLabel: document.getElementById("selectedTableLabel"),
        dataPreviewTableBadge: document.getElementById("dataPreviewTableBadge"),
        columnsBody: document.getElementById("columnsBody"),
        tableDataPreview: document.getElementById("tableDataPreview"),
        paginationInfo: document.getElementById("paginationInfo"),
        prevPageBtn: document.getElementById("prevPageBtn"),
        nextPageBtn: document.getElementById("nextPageBtn"),
        createTableForm: document.getElementById("createTableForm"),
        columnBuilderRows: document.getElementById("columnBuilderRows"),
        addBuilderRowBtn: document.getElementById("addBuilderRowBtn"),
        dropTableBtn: document.getElementById("dropTableBtn"),
        addColumnForm: document.getElementById("addColumnForm"),
        exportCsvBtn: document.getElementById("exportCsvBtn"),
        goToDataPreviewBtn: document.getElementById("goToDataPreviewBtn"),
        dataPreviewTabBtn: document.getElementById("dataPreviewTabBtn"),
    };

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute("content");

    function escapeHtml(value) {
        return String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    async function api(url, options = {}) {
        const config = {
            method: options.method || "GET",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
                ...(options.headers || {}),
            },
            credentials: "same-origin",
        };

        if (options.body !== undefined) {
            config.headers["Content-Type"] = "application/json";
            config.body = JSON.stringify(options.body);
        }

        const response = await fetch(url, config);
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errorMessage =
                payload.message ||
                payload.error ||
                "Request failed. Please try again.";
            throw new Error(errorMessage);
        }

        return payload;
    }

    function showAlert(message, type = "info") {
        const alertId = `alert-${Date.now()}`;
        const html = `
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        elements.alertArea.insertAdjacentHTML("beforeend", html);
        setTimeout(() => {
            const node = document.getElementById(alertId);
            if (node) {
                const alert = bootstrap.Alert.getOrCreateInstance(node);
                alert.close();
            }
        }, 5000);
    }

    function getTheme() {
        return localStorage.getItem("db_lite_theme") || "light";
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute("data-bs-theme", theme);
        document.body.classList.toggle("dark-mode", theme === "dark");
        localStorage.setItem("db_lite_theme", theme);
    }

    function initTheme() {
        applyTheme(getTheme());
        elements.themeToggle.addEventListener("click", () => {
            applyTheme(getTheme() === "dark" ? "light" : "dark");
        });
    }

    function setSelectedTable(tableName) {
        state.currentTable = tableName;
        state.currentPage = 1;
        elements.selectedTableLabel.textContent = tableName || "None";
        if (elements.dataPreviewTableBadge) {
            elements.dataPreviewTableBadge.textContent = tableName || "No table selected";
        }
    }

    async function loadDatabases() {
        const data = await api("/databases");
        state.activeConnectionId = data.active_connection_id;

        elements.savedConnections.innerHTML = "";
        if (!data.connections.length) {
            elements.savedConnections.innerHTML =
                '<option value="">No saved connections</option>';
            return;
        }

        data.connections.forEach((connection) => {
            const option = document.createElement("option");
            option.value = String(connection.id);
            option.textContent = `${connection.name} (${connection.database_name})`;
            elements.savedConnections.appendChild(option);
        });

        if (state.activeConnectionId) {
            elements.savedConnections.value = String(state.activeConnectionId);
        }
    }

    function renderTree(data) {
        if (!data.tables.length) {
            elements.databaseTree.innerHTML =
                '<div class="text-secondary">No tables found in this database.</div>';
            return;
        }

        const html = data.tables
            .map((table) => {
                const columns = table.columns
                    .map(
                        (column) =>
                            `<div><i class="bi bi-dot"></i> ${escapeHtml(
                                column.name
                            )} <span class="text-secondary">(${escapeHtml(
                                column.type
                            )})</span></div>`
                    )
                    .join("");
                return `
                    <div class="tree-table" data-table="${escapeHtml(table.name)}">
                        <i class="bi bi-table me-1"></i>
                        ${escapeHtml(table.name)}
                        <span class="text-secondary">(${table.estimated_rows})</span>
                    </div>
                    <div class="tree-columns small">${columns}</div>
                `;
            })
            .join("");

        elements.databaseTree.innerHTML = html;
    }

    async function loadTables() {
        try {
            const data = await api("/tables");
            renderTree(data);
        } catch (error) {
            elements.databaseTree.innerHTML =
                '<div class="text-secondary">Unable to load tables until a connection is active.</div>';
        }
    }

    async function connectToDatabase(connectionId) {
        if (!connectionId) {
            showAlert("Please select a saved connection first.", "warning");
            return;
        }

        const data = await api("/connect", {
            method: "POST",
            body: { connection_id: Number(connectionId) },
        });

        state.activeConnectionId = Number(connectionId);
        showAlert(data.message, "success");
        await loadTables();
        await loadHistory();
    }

    function renderQueryResult(data) {
        if (data.result_type === "table") {
            const headerHtml = data.columns
                .map((column) => `<th>${escapeHtml(column)}</th>`)
                .join("");
            const rowHtml = data.rows
                .map((row) => {
                    const cells = data.columns
                        .map((column) => `<td>${escapeHtml(row[column])}</td>`)
                        .join("");
                    return `<tr>${cells}</tr>`;
                })
                .join("");

            elements.queryResult.innerHTML = `
                <p class="mb-2"><strong>${escapeHtml(data.message)}</strong> (${data.duration_ms} ms)</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead><tr>${headerHtml}</tr></thead>
                        <tbody>${rowHtml || "<tr><td>No rows</td></tr>"}</tbody>
                    </table>
                </div>
            `;
            return;
        }

        elements.queryResult.innerHTML = `
            <div class="alert alert-success mb-0">
                ${escapeHtml(data.message)} <span class="text-secondary">(${data.duration_ms} ms)</span>
            </div>
        `;
    }

    async function runQuery() {
        const sql = elements.sqlInput.value.trim();
        if (!sql) {
            showAlert("Please write a SQL query first.", "warning");
            return;
        }

        const data = await api("/query", {
            method: "POST",
            body: {
                sql,
                allow_dangerous: elements.dangerSwitch.checked,
            },
        });

        renderQueryResult(data);
        await loadHistory();

        if (["CREATE", "ALTER", "DROP"].includes(data.query_type)) {
            await loadTables();
        }
    }

    async function loadHistory() {
        const data = await api("/query-history");
        if (!data.history.length) {
            elements.queryHistoryBody.innerHTML =
                '<tr><td colspan="5" class="text-center py-3 text-secondary">No history yet.</td></tr>';
            return;
        }

        elements.queryHistoryBody.innerHTML = data.history
            .map((entry) => {
                const timestamp = new Date(entry.executed_at).toLocaleString();
                const queryPreview =
                    entry.sql.length > 80
                        ? `${entry.sql.slice(0, 80)}...`
                        : entry.sql;
                return `
                    <tr>
                        <td>${escapeHtml(timestamp)}</td>
                        <td>${escapeHtml(entry.query_type)}</td>
                        <td>
                            <span class="badge text-bg-${
                                entry.status === "success" ? "success" : "danger"
                            }">${escapeHtml(entry.status)}</span>
                        </td>
                        <td>${escapeHtml(entry.duration_ms)} ms</td>
                        <td title="${escapeHtml(entry.sql)}"><code>${escapeHtml(
                    queryPreview
                )}</code></td>
                    </tr>
                `;
            })
            .join("");
    }

    async function loadColumns(tableName) {
        const data = await api(`/columns?table=${encodeURIComponent(tableName)}`);
        if (!data.columns.length) {
            elements.columnsBody.innerHTML =
                '<tr><td colspan="7" class="text-center py-3 text-secondary">No columns found.</td></tr>';
            return;
        }

        // Store full column metadata for the edit modal
        state.columnDetailsMeta = data.columns;

        elements.columnsBody.innerHTML = data.columns
            .map((column) => {
                const keyBadge = column.key === 'PRI'
                    ? '<span class="badge text-bg-primary">PRI</span>'
                    : column.key === 'UNI'
                        ? '<span class="badge text-bg-info">UNI</span>'
                        : column.key === 'MUL'
                            ? '<span class="badge text-bg-secondary">MUL</span>'
                            : '<span class="text-secondary">—</span>';
                const defVal = column.default !== null && column.default !== undefined
                    ? `<code class="text-success">${escapeHtml(String(column.default))}</code>`
                    : '<span class="text-secondary">NULL</span>';
                const extra = column.extra ? `<span class="badge text-bg-secondary">${escapeHtml(column.extra)}</span>` : '—';
                // Build data attrs for the edit button — use btoa to safely embed JSON
                const colDataB64 = btoa(unescape(encodeURIComponent(JSON.stringify(column))));
                return `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(column.name)}</td>
                        <td><code>${escapeHtml(column.type)}</code></td>
                        <td>${column.nullable
                            ? '<span class="badge text-bg-warning">YES</span>'
                            : '<span class="badge text-bg-secondary">NO</span>'}</td>
                        <td>${keyBadge}</td>
                        <td>${defVal}</td>
                        <td>${extra}</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-warning edit-column-btn me-1"
                                data-column-b64="${colDataB64}"
                                title="Edit column">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-column-btn"
                                data-column="${escapeHtml(column.name)}"
                                title="Drop column">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            })
            .join("");
    }

    async function loadPrimaryKey(tableName) {
        try {
            const data = await api(`/tables/${encodeURIComponent(tableName)}/primary-key`);
            state.primaryKey = data.primary_key || null;
        } catch {
            state.primaryKey = null;
        }
    }

    async function loadTableData(tableName, page = 1) {
        const data = await api(
            `/table-data?table=${encodeURIComponent(tableName)}&page=${page}&per_page=50`
        );

        state.currentPage = data.pagination.page;
        state.lastPage = data.pagination.last_page;
        state.columnMeta = data.columns || [];

        const table = elements.tableDataPreview;
        const headers = data.columns.length ? data.columns : ["No columns"];
        const hasPk = !!state.primaryKey;

        const headerHtml = (hasPk ? ['<th class="text-center" style="width:90px">Actions</th>'] : [])
            .concat(headers.map((h) => `<th>${escapeHtml(h)}</th>`))
            .join("");

        const bodyHtml = data.rows.length
            ? data.rows
                  .map((row) => {
                      const pkVal = hasPk ? escapeHtml(String(row[state.primaryKey] ?? "")) : "";
                      const actionCol = hasPk
                          ? `<td class="text-center text-nowrap">
                              <button type="button" class="btn btn-xs btn-warning me-1 row-edit-btn"
                                  data-pk="${pkVal}"
                                  data-row='${escapeHtml(JSON.stringify(row))}'
                                  title="Edit">
                                  <i class="bi bi-pencil-fill"></i>
                              </button>
                              <button type="button" class="btn btn-xs btn-danger row-delete-btn"
                                  data-pk="${pkVal}"
                                  title="Delete">
                                  <i class="bi bi-trash-fill"></i>
                              </button>
                           </td>`
                          : "";
                      const cells = headers
                          .map((h) => `<td class="cell-value" data-col="${escapeHtml(h)}">${escapeHtml(row[h])}</td>`)
                          .join("");
                      return `<tr data-pk="${pkVal}">${actionCol}${cells}</tr>`;
                  })
                  .join("")
            : `<tr><td colspan="${headers.length + (hasPk ? 1 : 0)}" class="text-center py-3 text-secondary">No rows found.</td></tr>`;

        table.innerHTML = `
            <thead><tr>${headerHtml}</tr></thead>
            <tbody>${bodyHtml}</tbody>
        `;

        elements.paginationInfo.textContent = `Page ${data.pagination.page} of ${data.pagination.last_page} | Total: ${data.pagination.total} rows`;
    }

    async function selectTable(tableName) {
        setSelectedTable(tableName);
        await Promise.all([
            loadColumns(tableName),
            loadPrimaryKey(tableName),
        ]);
        await loadTableData(tableName, 1);
    }

    /* ── Edit Column Modal ─────────────────────────────────────────────── */
    function openEditColumnModal(columnData) {
        const typeUpper = columnData.type ? columnData.type.toUpperCase() : '';
        // Detect base type (strip length info, e.g. varchar(255) -> VARCHAR)
        const baseTypes = ['BIGINT','INT','VARCHAR','TEXT','DATE','DATETIME','TIMESTAMP','BOOLEAN','DECIMAL','FLOAT','DOUBLE'];
        let matchedType = 'VARCHAR';
        for (const t of baseTypes) {
            if (typeUpper.startsWith(t)) { matchedType = t; break; }
        }

        // Extract length from e.g. varchar(255) or decimal(10,2)
        const lenMatch = columnData.type ? columnData.type.match(/(\d+)/) : null;
        const scaleMatch = columnData.type ? columnData.type.match(/,\s*(\d+)/) : null;

        document.getElementById('editColOriginalName').value = columnData.name;
        document.getElementById('editColName').value = columnData.name;
        document.getElementById('editColType').value = matchedType;
        document.getElementById('editColLength').value = lenMatch ? lenMatch[1] : '';
        document.getElementById('editColScale').value = scaleMatch ? scaleMatch[1] : '';
        document.getElementById('editColDefault').value =
            columnData.default !== null && columnData.default !== undefined ? columnData.default : '';
        document.getElementById('editColNullable').checked = columnData.nullable === true;
        document.getElementById('editColAutoIncrement').checked =
            typeof columnData.extra === 'string' && columnData.extra.toLowerCase().includes('auto_increment');
        document.getElementById('editColumnTableName').textContent = `— ${state.currentTable}`;

        updateEditColumnSqlPreview();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editColumnModal')).show();
    }

    function updateEditColumnSqlPreview() {
        const table  = state.currentTable || '?';
        const oldCol = document.getElementById('editColOriginalName').value || '?';
        const newCol = document.getElementById('editColName').value || '?';
        const type   = document.getElementById('editColType').value;
        const len    = document.getElementById('editColLength').value;
        const scale  = document.getElementById('editColScale').value;
        const def    = document.getElementById('editColDefault').value;
        const nullable = document.getElementById('editColNullable').checked;
        const ai     = document.getElementById('editColAutoIncrement').checked;

        let typeSql = type;
        if (type === 'VARCHAR') typeSql = `VARCHAR(${len || 255})`;
        else if (type === 'DECIMAL') typeSql = `DECIMAL(${len || 10},${scale || 2})`;
        else if (['INT','BIGINT','FLOAT','DOUBLE'].includes(type) && len) typeSql = `${type}(${len})`;

        const nullSql = ai ? 'NOT NULL AUTO_INCREMENT' : (nullable ? 'NULL' : 'NOT NULL');
        const defSql  = (def !== '') ? ` DEFAULT '${def}'` : '';

        const previewEl = document.getElementById('editColSqlPreview');
        previewEl.textContent = `ALTER TABLE \`${table}\` CHANGE COLUMN \`${oldCol}\` \`${newCol}\` ${typeSql} ${nullSql}${defSql};`;
    }

    function openEditRowModal(rowData) {
        state.editRowData = rowData;
        const container = document.getElementById("editRowFields");
        document.getElementById("editRowTableName").textContent = `— ${state.currentTable}`;

        container.innerHTML = state.columnMeta
            .map((col) => {
                const val = rowData[col] ?? "";
                const isPk = col === state.primaryKey;
                return `
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">
                            ${escapeHtml(col)}
                            ${isPk ? '<span class="badge text-bg-primary ms-1">PK</span>' : ""}
                        </label>
                        <input type="text" class="form-control form-control-sm"
                            name="${escapeHtml(col)}"
                            value="${escapeHtml(String(val === null ? "" : val))}"
                            ${isPk ? "readonly" : ""}>
                    </div>
                `;
            })
            .join("");

        bootstrap.Modal.getOrCreateInstance(document.getElementById("editRowModal")).show();
    }

    async function saveEditRow() {
        if (!state.editRowData || !state.primaryKey || !state.currentTable) return;

        const container = document.getElementById("editRowFields");
        const inputs = container.querySelectorAll("input:not([readonly])");
        const pkValue = state.editRowData[state.primaryKey];
        const btn = document.getElementById("saveEditRowBtn");
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            for (const input of inputs) {
                const col = input.name;
                const newVal = input.value;
                if (String(state.editRowData[col] ?? "") === newVal) continue;
                await api(`/tables/${encodeURIComponent(state.currentTable)}/rows`, {
                    method: "PUT",
                    body: {
                        primary_key: state.primaryKey,
                        primary_key_value: pkValue,
                        column: col,
                        value: newVal,
                    },
                });
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById("editRowModal")).hide();
            showAlert("Row updated successfully.", "success");
            await loadTableData(state.currentTable, state.currentPage);
        } catch (error) {
            showAlert(error.message, "danger");
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    async function deleteRowByPk(pkValue) {
        const confirmed = confirm(`Delete this row where ${state.primaryKey} = ${pkValue}?\nThis cannot be undone.`);
        if (!confirmed) return;

        try {
            await api(`/tables/${encodeURIComponent(state.currentTable)}/rows`, {
                method: "DELETE",
                body: {
                    primary_key: state.primaryKey,
                    primary_key_value: pkValue,
                },
            });
            showAlert("Row deleted.", "success");
            await loadTableData(state.currentTable, state.currentPage);
        } catch (error) {
            showAlert(error.message, "danger");
        }
    }

    function openInsertRowModal() {
        if (!state.currentTable) {
            showAlert("Select a table first.", "warning");
            return;
        }
        const container = document.getElementById("insertRowFields");
        document.getElementById("insertRowTableName").textContent = `— ${state.currentTable}`;

        container.innerHTML = state.columnMeta
            .map((col) => {
                const isPk = col === state.primaryKey;
                return `
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">
                            ${escapeHtml(col)}
                            ${isPk ? '<span class="badge text-bg-primary ms-1">PK / AI</span>' : ""}
                        </label>
                        <input type="text" class="form-control form-control-sm"
                            name="${escapeHtml(col)}"
                            placeholder="${isPk ? "auto" : ""}"
                            ${isPk ? "" : ""}>
                    </div>
                `;
            })
            .join("");

        bootstrap.Modal.getOrCreateInstance(document.getElementById("insertRowModal")).show();
    }

    async function saveInsertRow() {
        if (!state.currentTable) return;

        const container = document.getElementById("insertRowFields");
        const inputs = container.querySelectorAll("input");
        const values = {};
        inputs.forEach((inp) => {
            if (inp.value.trim() !== "") values[inp.name] = inp.value.trim();
        });

        if (!Object.keys(values).length) {
            showAlert("Please fill at least one field.", "warning");
            return;
        }

        const cols = Object.keys(values).map((c) => `\`${c}\``).join(", ");
        const placeholders = Object.keys(values).map(() => "?").join(", ");
        const sql = `INSERT INTO \`${state.currentTable}\` (${cols}) VALUES (${placeholders})`;
        const vals = Object.values(values);

        const btn = document.getElementById("saveInsertRowBtn");
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Inserting…';

        try {
            await api("/query", {
                method: "POST",
                body: { sql: sql.replace(/\?/g, (_, i) => `'${vals.shift()}'`), allow_dangerous: true },
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById("insertRowModal")).hide();
            showAlert("Row inserted successfully.", "success");
            await loadTableData(state.currentTable, state.currentPage);
        } catch (error) {
            showAlert(error.message, "danger");
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    function parseBooleanFromInput(value) {
        return value === "1" || value === "true" || value === true;
    }

    function getBuilderRows() {
        const rows = elements.columnBuilderRows.querySelectorAll(".builder-row");
        return Array.from(rows).map((row) => {
            const type = row.querySelector('[name="type"]').value;
            const lengthRaw = row.querySelector('[name="length"]').value;
            const scaleRaw = row.querySelector('[name="scale"]').value;
            return {
                name: row.querySelector('[name="name"]').value.trim(),
                type,
                length: lengthRaw ? Number(lengthRaw) : null,
                scale: scaleRaw ? Number(scaleRaw) : null,
                default: row.querySelector('[name="default"]').value.trim() || null,
                nullable: parseBooleanFromInput(
                    row.querySelector('[name="nullable"]').checked
                ),
                primary: parseBooleanFromInput(
                    row.querySelector('[name="primary"]').checked
                ),
                auto_increment: parseBooleanFromInput(
                    row.querySelector('[name="auto_increment"]').checked
                ),
            };
        });
    }

    function addBuilderRow(initialValues = {}) {
        const row = document.createElement("div");
        row.className = "builder-row border rounded p-2";
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1 small">Name</label>
                    <input type="text" class="form-control form-control-sm" name="name" value="${
                        initialValues.name || ""
                    }" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small">Type</label>
                    <select class="form-select form-select-sm" name="type">
                        <option ${
                            initialValues.type === "INT" ? "selected" : ""
                        }>INT</option>
                        <option ${
                            initialValues.type === "BIGINT" ? "selected" : ""
                        }>BIGINT</option>
                        <option ${
                            !initialValues.type || initialValues.type === "VARCHAR"
                                ? "selected"
                                : ""
                        }>VARCHAR</option>
                        <option ${
                            initialValues.type === "TEXT" ? "selected" : ""
                        }>TEXT</option>
                        <option ${
                            initialValues.type === "DATE" ? "selected" : ""
                        }>DATE</option>
                        <option ${
                            initialValues.type === "DATETIME" ? "selected" : ""
                        }>DATETIME</option>
                        <option ${
                            initialValues.type === "TIMESTAMP" ? "selected" : ""
                        }>TIMESTAMP</option>
                        <option ${
                            initialValues.type === "BOOLEAN" ? "selected" : ""
                        }>BOOLEAN</option>
                        <option ${
                            initialValues.type === "DECIMAL" ? "selected" : ""
                        }>DECIMAL</option>
                        <option ${
                            initialValues.type === "FLOAT" ? "selected" : ""
                        }>FLOAT</option>
                        <option ${
                            initialValues.type === "DOUBLE" ? "selected" : ""
                        }>DOUBLE</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label mb-1 small">Len</label>
                    <input type="number" class="form-control form-control-sm" name="length" value="${
                        initialValues.length || ""
                    }">
                </div>
                <div class="col-md-1">
                    <label class="form-label mb-1 small">Scale</label>
                    <input type="number" class="form-control form-control-sm" name="scale" value="${
                        initialValues.scale || ""
                    }">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small">Default</label>
                    <input type="text" class="form-control form-control-sm" name="default" value="${
                        initialValues.default || ""
                    }">
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="nullable" ${
                                initialValues.nullable ? "checked" : ""
                            }>
                            <label class="form-check-label small">Null</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="primary" ${
                                initialValues.primary ? "checked" : ""
                            }>
                            <label class="form-check-label small">PK</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="auto_increment" ${
                                initialValues.auto_increment ? "checked" : ""
                            }>
                            <label class="form-check-label small">AI</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-builder-row">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        `;
        elements.columnBuilderRows.appendChild(row);
    }

    function resetTableView() {
        setSelectedTable(null);
        state.primaryKey = null;
        state.columnMeta = [];
        state.columnDetailsMeta = [];
        state.editRowData = null;
        elements.columnsBody.innerHTML =
            '<tr><td colspan="7" class="text-center py-3 text-secondary">Select a table.</td></tr>';
        elements.tableDataPreview.innerHTML =
            '<thead><tr><th>Preview</th></tr></thead><tbody><tr><td class="text-center py-3 text-secondary">No table selected.</td></tr></tbody>';
        elements.paginationInfo.textContent = "Page 1";
    }

    function bindEvents() {
        elements.toggleSidebar.addEventListener("click", () => {
            if (window.innerWidth < 992) {
                elements.sidebar.classList.toggle("is-collapsed");
                return;
            }
            elements.sidebar.classList.toggle("d-none");
        });

        elements.addDatabaseForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            const saveBtn = document.getElementById("saveConnectionBtn");
            const originalText = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
            try {
                const formData = new FormData(elements.addDatabaseForm);
                const payload = Object.fromEntries(formData.entries());
                payload.port = payload.port ? Number(payload.port) : 3306;
                await api("/add-database", { method: "POST", body: payload });
                elements.addDatabaseForm.reset();
                // Close the modal
                const modalEl = document.getElementById("addConnectionModal");
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                showAlert("Database connection saved.", "success");
                await loadDatabases();
            } catch (error) {
                showAlert(error.message, "danger");
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            }
        });

        elements.connectBtn.addEventListener("click", async () => {
            try {
                await connectToDatabase(elements.savedConnections.value);
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        elements.databaseTree.addEventListener("click", async (event) => {
            const tableNode = event.target.closest("[data-table]");
            if (!tableNode) {
                return;
            }

            const tableName = tableNode.getAttribute("data-table");
            try {
                await selectTable(tableName);
                // Switch to Table Viewer tab to show column structure
                const tableTabTrigger = document.querySelector(
                    '[data-bs-target="#tableTab"]'
                );
                bootstrap.Tab.getOrCreateInstance(tableTabTrigger).show();
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        // "View Data" button — switch to Data Preview tab
        const goToDataPreviewBtn = document.getElementById("goToDataPreviewBtn");
        if (goToDataPreviewBtn) {
            goToDataPreviewBtn.addEventListener("click", async () => {
                if (!state.currentTable) {
                    showAlert("Please select a table first.", "warning");
                    return;
                }
                try {
                    await loadTableData(state.currentTable, state.currentPage);
                    const dataPreviewTabBtn = document.getElementById("dataPreviewTabBtn");
                    bootstrap.Tab.getOrCreateInstance(dataPreviewTabBtn).show();
                } catch (error) {
                    showAlert(error.message, "danger");
                }
            });
        }

        elements.executeQueryBtn.addEventListener("click", async () => {
            try {
                await runQuery();
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        elements.prevPageBtn.addEventListener("click", async () => {
            if (!state.currentTable || state.currentPage <= 1) {
                return;
            }
            try {
                await loadTableData(state.currentTable, state.currentPage - 1);
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        elements.nextPageBtn.addEventListener("click", async () => {
            if (!state.currentTable || state.currentPage >= state.lastPage) {
                return;
            }
            try {
                await loadTableData(state.currentTable, state.currentPage + 1);
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        elements.addBuilderRowBtn.addEventListener("click", () => addBuilderRow());

        elements.columnBuilderRows.addEventListener("click", (event) => {
            const removeBtn = event.target.closest(".remove-builder-row");
            if (!removeBtn) {
                return;
            }
            removeBtn.closest(".builder-row").remove();
        });

        elements.createTableForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            try {
                const tableName = elements.createTableForm
                    .querySelector('[name="table_name"]')
                    .value.trim();
                const columns = getBuilderRows();
                await api("/tables/create", {
                    method: "POST",
                    body: { table_name: tableName, columns },
                });

                showAlert(`Table ${tableName} created successfully.`, "success");
                elements.createTableForm.reset();
                elements.columnBuilderRows.innerHTML = "";
                addBuilderRow();
                bootstrap.Modal.getInstance(
                    document.getElementById("createTableModal")
                ).hide();
                await loadTables();
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        elements.dropTableBtn.addEventListener("click", async () => {
            if (!state.currentTable) {
                showAlert("Please select a table first.", "warning");
                return;
            }

            const confirmation = prompt(
                `Type "${state.currentTable}" to confirm dropping the table.`
            );
            if (!confirmation) {
                return;
            }

            try {
                await api(`/tables/${encodeURIComponent(state.currentTable)}`, {
                    method: "DELETE",
                    body: { confirm_name: confirmation },
                });
                showAlert(`Table ${state.currentTable} dropped.`, "success");
                resetTableView();
                await loadTables();
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        elements.addColumnForm.addEventListener("submit", async (event) => {
            event.preventDefault();

            if (!state.currentTable) {
                showAlert("Select a table before adding a column.", "warning");
                return;
            }

            try {
                const formData = new FormData(elements.addColumnForm);
                const payload = Object.fromEntries(formData.entries());
                payload.nullable = elements.addColumnForm.querySelector(
                    '[name="nullable"]'
                ).checked;
                payload.auto_increment = elements.addColumnForm.querySelector(
                    '[name="auto_increment"]'
                ).checked;
                payload.length = payload.length ? Number(payload.length) : null;
                payload.scale = payload.scale ? Number(payload.scale) : null;

                await api(
                    `/tables/${encodeURIComponent(state.currentTable)}/columns`,
                    {
                        method: "POST",
                        body: payload,
                    }
                );

                showAlert(`Column added to ${state.currentTable}.`, "success");
                elements.addColumnForm.reset();
                bootstrap.Modal.getInstance(
                    document.getElementById("addColumnModal")
                ).hide();
                await loadColumns(state.currentTable);
                await loadTableData(state.currentTable, state.currentPage);
                await loadTables();
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        elements.columnsBody.addEventListener("click", async (event) => {
            // Edit column button
            const editColBtn = event.target.closest('.edit-column-btn');
            if (editColBtn && state.currentTable) {
                try {
                    const b64 = editColBtn.getAttribute('data-column-b64');
                    const colData = JSON.parse(decodeURIComponent(escape(atob(b64))));
                    openEditColumnModal(colData);
                } catch (e) {
                    showAlert('Could not parse column data.', 'danger');
                }
                return;
            }

            const removeBtn = event.target.closest(".remove-column-btn");
            if (!removeBtn || !state.currentTable) {
                return;
            }

            const column = removeBtn.getAttribute("data-column");
            const confirmed = confirm(
                `Remove column "${column}" from "${state.currentTable}"?`
            );
            if (!confirmed) {
                return;
            }

            try {
                await api(
                    `/tables/${encodeURIComponent(
                        state.currentTable
                    )}/columns/${encodeURIComponent(column)}`,
                    {
                        method: "DELETE",
                        body: { confirm: true },
                    }
                );
                showAlert(`Column ${column} removed.`, "success");
                await loadColumns(state.currentTable);
                await loadTableData(state.currentTable, state.currentPage);
                await loadTables();
            } catch (error) {
                showAlert(error.message, "danger");
            }
        });

        // ─── Edit Column Form (live SQL preview + submit) ────────────────────────
        const editColumnForm = document.getElementById('editColumnForm');
        if (editColumnForm) {
            // Live SQL preview on any input change
            editColumnForm.addEventListener('input', updateEditColumnSqlPreview);
            editColumnForm.addEventListener('change', updateEditColumnSqlPreview);

            editColumnForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!state.currentTable) return;

                const originalCol = document.getElementById('editColOriginalName').value;
                const payload = {
                    new_name: document.getElementById('editColName').value.trim(),
                    type: document.getElementById('editColType').value,
                    length: document.getElementById('editColLength').value
                        ? Number(document.getElementById('editColLength').value) : null,
                    scale: document.getElementById('editColScale').value
                        ? Number(document.getElementById('editColScale').value) : null,
                    default: document.getElementById('editColDefault').value || null,
                    nullable: document.getElementById('editColNullable').checked,
                    auto_increment: document.getElementById('editColAutoIncrement').checked,
                };

                const saveBtn = document.getElementById('saveEditColumnBtn');
                const orig = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

                try {
                    await api(
                        `/tables/${encodeURIComponent(state.currentTable)}/columns/${encodeURIComponent(originalCol)}`,
                        { method: 'PUT', body: payload }
                    );
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editColumnModal')).hide();
                    showAlert(`Column '${originalCol}' updated successfully.`, 'success');
                    await loadColumns(state.currentTable);
                    await loadTableData(state.currentTable, state.currentPage);
                    await loadTables();
                } catch (error) {
                    showAlert(error.message, 'danger');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = orig;
                }
            });
        }

        elements.exportCsvBtn.addEventListener("click", () => {
            if (!state.currentTable) {
                showAlert("Please select a table first.", "warning");
                return;
            }
            window.location.href = `/tables/${encodeURIComponent(
                state.currentTable
            )}/export`;
        });

        // ── Row Edit / Delete (event delegation on data preview table) ──────────
        elements.tableDataPreview.addEventListener("click", async (event) => {
            // Edit button
            const editBtn = event.target.closest(".row-edit-btn");
            if (editBtn) {
                try {
                    const rowData = JSON.parse(editBtn.getAttribute("data-row"));
                    openEditRowModal(rowData);
                } catch (e) {
                    showAlert("Could not parse row data.", "danger");
                }
                return;
            }

            // Delete button
            const deleteBtn = event.target.closest(".row-delete-btn");
            if (deleteBtn) {
                const pkVal = deleteBtn.getAttribute("data-pk");
                try {
                    await deleteRowByPk(pkVal);
                } catch (error) {
                    showAlert(error.message, "danger");
                }
            }
        });

        // ── Save Edit Row ────────────────────────────────────────────────────────
        const saveEditRowBtn = document.getElementById("saveEditRowBtn");
        if (saveEditRowBtn) {
            saveEditRowBtn.addEventListener("click", () => saveEditRow());
        }

        // ── Insert Row button (opens modal) ──────────────────────────────────────
        const insertRowBtn = document.getElementById("insertRowBtn");
        if (insertRowBtn) {
            insertRowBtn.addEventListener("click", () => openInsertRowModal());
        }

        // ── Save Insert Row ──────────────────────────────────────────────────────
        const saveInsertRowBtn = document.getElementById("saveInsertRowBtn");
        if (saveInsertRowBtn) {
            saveInsertRowBtn.addEventListener("click", () => saveInsertRow());
        }
    }

    async function init() {
        initTheme();
        bindEvents();
        addBuilderRow();

        try {
            await loadDatabases();
            if (state.activeConnectionId) {
                await connectToDatabase(state.activeConnectionId);
            }
            await loadHistory();
        } catch (error) {
            showAlert(error.message, "danger");
        }

        if (window.innerWidth < 992) {
            elements.sidebar.classList.add("is-collapsed");
        }
    }

    init();
})();
