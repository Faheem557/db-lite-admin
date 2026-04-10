@extends('layouts.main')

@section('content')
<div class="d-flex" id="appRoot">
    <aside id="sidebar" class="sidebar border-end bg-body d-flex flex-column">

        {{-- Sidebar Header: Connections --}}
        <div class="p-3 border-bottom flex-shrink-0">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <h2 class="h6 mb-0">
                    <i class="bi bi-plug me-1"></i>
                    Connections
                </h2>
                <button
                    class="btn btn-primary btn-sm d-flex align-items-center gap-1"
                    id="openAddConnectionBtn"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#addConnectionModal"
                    title="Add new connection"
                >
                    <i class="bi bi-plus-lg"></i>
                    <span class="d-none d-sm-inline">New</span>
                </button>
            </div>
            <p class="text-secondary small mb-0">Connect to a MySQL database</p>
        </div>

        {{-- Saved Connections Selector --}}
        <div class="p-3 border-bottom flex-shrink-0">
            <label for="savedConnections" class="form-label small mb-1 fw-semibold">Saved Databases</label>
            <div class="d-flex gap-2">
                <select id="savedConnections" class="form-select form-select-sm"></select>
                <button class="btn btn-success btn-sm flex-shrink-0" id="connectBtn" type="button">
                    <i class="bi bi-plug-fill me-1"></i>Connect
                </button>
            </div>
        </div>

        {{-- Explorer (scrollable) --}}
        <div class="p-3 d-flex flex-column flex-grow-1 overflow-hidden">
            <h3 class="h6 mb-2 flex-shrink-0">
                <i class="bi bi-diagram-3 me-1"></i>
                Explorer
            </h3>
            <div id="databaseTree" class="small text-secondary explorer-tree flex-grow-1 overflow-auto">
                Connect to a database to load tables.
            </div>
        </div>

    </aside>

    <main class="main-panel flex-grow-1">
        <nav class="navbar navbar-expand border-bottom px-3 bg-body sticky-top">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="toggleSidebar" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <span class="navbar-brand mb-0 h1 fs-6">DB Lite Admin</span>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="themeToggle" type="button">
                    <i class="bi bi-moon-stars"></i>
                    Theme
                </button>
                <span class="text-secondary small">{{ $user->email }}</span>
                <form method="POST" action="/logout">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm" type="submit">
                        <i class="bi bi-box-arrow-right"></i>
                        Logout
                    </button>
                </form>
            </div>
        </nav>

        <div class="container-fluid py-3">
            <div id="alertArea"></div>

            <ul class="nav nav-tabs mb-3" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#queryTab" type="button" role="tab">
                        <i class="bi bi-code-slash me-1"></i>
                        Query Console
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tableTab" type="button" role="tab">
                        <i class="bi bi-layout-three-columns me-1"></i>
                        Table Viewer
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#dataPreviewTab" type="button" role="tab" id="dataPreviewTabBtn">
                        <i class="bi bi-grid-3x2-gap me-1"></i>
                        Data Preview
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="queryTab" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>SQL Query Editor</span>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="dangerSwitch">
                                <label class="form-check-label small" for="dangerSwitch">Allow dangerous queries</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <textarea id="sqlInput" class="form-control query-editor mb-3" placeholder="SELECT * FROM users LIMIT 20;"></textarea>
                            <button id="executeQueryBtn" class="btn btn-primary" type="button">
                                <i class="bi bi-lightning-charge me-1"></i>
                                Execute
                            </button>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header">Query Results</div>
                        <div class="card-body">
                            <div id="queryResult" class="result-box text-secondary">Run a query to view results.</div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header">Query History</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Duration</th>
                                            <th>Query</th>
                                        </tr>
                                    </thead>
                                    <tbody id="queryHistoryBody">
                                        <tr><td colspan="5" class="text-center py-3 text-secondary">No history yet.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tableTab" role="tabpanel">
                    {{-- Table Actions Toolbar --}}
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                            <strong>Selected Table:</strong>
                            <span id="selectedTableLabel" class="badge text-bg-secondary">None</span>
                            <button class="btn btn-success btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#createTableModal" type="button">
                                <i class="bi bi-table me-1"></i>Create Table
                            </button>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addColumnModal" type="button" id="openAddColumnModal">
                                <i class="bi bi-plus-square me-1"></i>Add Column
                            </button>
                            <button class="btn btn-outline-danger btn-sm" id="dropTableBtn" type="button">
                                <i class="bi bi-trash me-1"></i>Drop Table
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="exportCsvBtn" type="button">
                                <i class="bi bi-download me-1"></i>Export CSV
                            </button>
                            <button class="btn btn-outline-info btn-sm" id="goToDataPreviewBtn" type="button">
                                <i class="bi bi-grid-3x2-gap me-1"></i>View Data
                            </button>
                        </div>
                    </div>

                    {{-- Columns card (full width) --}}
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <i class="bi bi-list-columns me-1"></i>Column Structure
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Nullable</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="columnsBody">
                                        <tr><td colspan="4" class="text-center py-3 text-secondary">Select a table from the Explorer.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ══════ DATA PREVIEW TAB ══════ --}}
                <div class="tab-pane fade" id="dataPreviewTab" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-grid-3x2-gap me-1"></i>
                                <span class="fw-semibold">Data Preview</span>
                                <span class="badge text-bg-info" id="dataPreviewTableBadge">No table selected</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-success btn-sm" id="insertRowBtn" type="button" title="Insert new row">
                                    <i class="bi bi-plus-circle me-1"></i>Insert Row
                                </button>
                                <span class="text-secondary small" id="paginationInfo">Page 1</span>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" id="prevPageBtn">
                                        <i class="bi bi-chevron-left"></i> Prev
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="nextPageBtn">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered mb-0" id="tableDataPreview">
                                    <thead><tr><th>Preview</th></tr></thead>
                                    <tbody><tr><td class="text-center py-4 text-secondary">
                                        <i class="bi bi-grid-3x2-gap fs-3 d-block mb-2 opacity-25"></i>
                                        Select a table from the Explorer, then click <strong>View Data</strong>.
                                    </td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

{{-- ======================== EDIT ROW MODAL ======================== --}}
<div class="modal fade" id="editRowModal" tabindex="-1" aria-labelledby="editRowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title" id="editRowModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Row
                    <small class="text-muted ms-2" id="editRowTableName"></small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editRowFields" class="row g-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="saveEditRowBtn">
                    <i class="bi bi-floppy me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ======================== INSERT ROW MODAL ======================== --}}
<div class="modal fade" id="insertRowModal" tabindex="-1" aria-labelledby="insertRowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success-subtle">
                <h5 class="modal-title" id="insertRowModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Insert New Row
                    <small class="text-muted ms-2" id="insertRowTableName"></small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="insertRowFields" class="row g-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveInsertRowBtn">
                    <i class="bi bi-plus-circle me-1"></i>Insert Row
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ======================== ADD CONNECTION MODAL ======================== --}}
<div class="modal fade" id="addConnectionModal" tabindex="-1" aria-labelledby="addConnectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addDatabaseForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addConnectionModalLabel">
                        <i class="bi bi-plug me-2"></i>New Database Connection
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Label</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Production DB" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Host</label>
                        <input type="text" class="form-control" name="host" placeholder="127.0.0.1" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-semibold mb-1">Port</label>
                            <input type="number" class="form-control" name="port" value="3306">
                        </div>
                        <div class="col-8">
                            <label class="form-label small fw-semibold mb-1">Database Name</label>
                            <input type="text" class="form-control" name="database_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold mb-1">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="(leave blank if none)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit" id="saveConnectionBtn">
                        <i class="bi bi-floppy me-1"></i>
                        Save Connection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ======================== CREATE TABLE MODAL ======================== --}}
<div class="modal fade" id="createTableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="createTableForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create Table</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Table Name</label>
                        <input type="text" class="form-control" name="table_name" required>
                    </div>
                    <div id="columnBuilderRows" class="d-flex flex-column gap-2"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addBuilderRowBtn">
                        <i class="bi bi-plus-lg me-1"></i>Add Column Row
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Table</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ======================== ADD COLUMN MODAL ======================== --}}
<div class="modal fade" id="addColumnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addColumnForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add Column</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Column Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" required>
                            <option>INT</option>
                            <option>BIGINT</option>
                            <option selected>VARCHAR</option>
                            <option>TEXT</option>
                            <option>DATE</option>
                            <option>DATETIME</option>
                            <option>TIMESTAMP</option>
                            <option>BOOLEAN</option>
                            <option>DECIMAL</option>
                            <option>FLOAT</option>
                            <option>DOUBLE</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <label class="form-label">Length</label>
                            <input type="number" class="form-control" name="length">
                        </div>
                        <div class="col">
                            <label class="form-label">Scale</label>
                            <input type="number" class="form-control" name="scale">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Default (optional)</label>
                        <input type="text" class="form-control" name="default">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="nullable" id="addColumnNullable" value="1">
                        <label for="addColumnNullable" class="form-check-label">Nullable</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="auto_increment" id="addColumnAuto" value="1">
                        <label for="addColumnAuto" class="form-check-label">Auto Increment</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Column</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/app.js') }}"></script>
@endpush
