@extends('db-lite-admin::layouts.main')

@section('content')
<div class="d-flex" id="appRoot">
    <aside id="sidebar" class="sidebar border-end bg-body d-flex flex-column">
        <div class="p-3 border-bottom flex-shrink-0">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <h2 class="h6 mb-0">
                    <i class="bi bi-plug me-1"></i>
                    Connections
                </h2>
                <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" id="openAddConnectionBtn" type="button" data-bs-toggle="modal" data-bs-target="#addConnectionModal" title="Add new connection">
                    <i class="bi bi-plus-lg"></i>
                    <span class="d-none d-sm-inline">New</span>
                </button>
            </div>
            <p class="text-secondary small mb-0">Connect to a MySQL database</p>
        </div>

        <div class="p-3 border-bottom flex-shrink-0">
            <label for="savedConnections" class="form-label small mb-1 fw-semibold">Saved Databases</label>
            <div class="d-flex gap-2">
                <select id="savedConnections" class="form-select form-select-sm"></select>
                <button class="btn btn-success btn-sm flex-shrink-0" id="connectBtn" type="button">
                    <i class="bi bi-plug-fill me-1"></i>Connect
                </button>
            </div>
        </div>

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
                <form method="POST" action="{{ route('db-lite-admin.logout') }}">
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
                            <textarea id="sqlInput" class="form-control query-editor mb-3" placeholder="SELECT * FROM users LIMIT 20."></textarea>
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
                                            <th>Key</th>
                                            <th>Default</th>
                                            <th>Extra</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="columnsBody">
                                        <tr><td colspan="7" class="text-center py-3 text-secondary">Select a table from the Explorer.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
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
@endsection
