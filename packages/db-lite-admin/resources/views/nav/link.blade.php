@if (Route::has('db-lite-admin.dashboard'))
<li class="nav-item">
    <a class="nav-link" href="{{ route('db-lite-admin.dashboard') }}">
        <i class="bi bi-database2 me-1"></i>
        DB Lite Admin
    </a>
</li>
@endif
