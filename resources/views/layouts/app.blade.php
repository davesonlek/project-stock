<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} - Stock Management Prototype</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="main-wrapper">
        <!-- Sidebar -->
        <aside class="app-sidebar d-flex flex-column flex-shrink-0 p-3">
            <div class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none px-2 py-1">
                <i class="bi bi-boxes fs-4 text-primary me-2"></i>
                <span class="fs-5 fw-bold">Stock Prototype</span>
            </div>
            <hr class="border-secondary my-2">

            <!-- Organization & Role Badge -->
            <div class="bg-dark rounded p-2 mb-3 border border-secondary text-white small">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-truncate fw-semibold" title="{{ $currentOrganization->name ?? 'Organization' }}">
                        <i class="bi bi-building me-1 text-info"></i>{{ $currentOrganization->name ?? 'Organization' }}
                    </span>
                    <span class="badge bg-primary text-uppercase">{{ $currentRole->code ?? 'STAFF' }}</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                </li>

                <li class="sidebar-heading">Inventory</li>
                <li>
                    <a href="{{ route('inventory.stock') }}" class="nav-link {{ request()->routeIs('inventory.stock*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam"></i>Stock Balance
                    </a>
                </li>
                <li>
                    <a href="{{ route('inventory.lots') }}" class="nav-link {{ request()->routeIs('inventory.lots*') ? 'active' : '' }}">
                        <i class="bi bi-tags"></i>Stock Lots
                    </a>
                </li>
                <li>
                    <a href="{{ route('inventory.serials') }}" class="nav-link {{ request()->routeIs('inventory.serials*') ? 'active' : '' }}">
                        <i class="bi bi-upc-scan"></i>Serial Numbers
                    </a>
                </li>
                <li>
                    <a href="{{ route('inventory.reservations') }}" class="nav-link {{ request()->routeIs('inventory.reservations*') ? 'active' : '' }}">
                        <i class="bi bi-bookmark-check"></i>Reservations
                    </a>
                </li>

                <li class="sidebar-heading">Transactions</li>
                <li>
                    <a href="{{ route('stock.documents.index') }}" class="nav-link {{ request()->routeIs('stock.documents.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-text"></i>Stock Documents
                    </a>
                </li>

                <li class="sidebar-heading">Master Data</li>
                <li>
                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                        <i class="bi bi-box"></i>Products
                    </a>
                </li>
                <li>
                    <a href="{{ route('goods.index') }}" class="nav-link {{ request()->routeIs('goods.*') ? 'active' : '' }}">
                        <i class="bi bi-grid"></i>Goods (SKUs)
                    </a>
                </li>
                <li>
                    <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <i class="bi bi-folder"></i>Categories
                    </a>
                </li>
                <li>
                    <a href="{{ route('brands.index') }}" class="nav-link {{ request()->routeIs('brands.*') ? 'active' : '' }}">
                        <i class="bi bi-award"></i>Brands
                    </a>
                </li>
                <li>
                    <a href="{{ route('units.index') }}" class="nav-link {{ request()->routeIs('units.*') ? 'active' : '' }}">
                        <i class="bi bi-rulers"></i>Units
                    </a>
                </li>
                <li>
                    <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                        <i class="bi bi-truck"></i>Suppliers
                    </a>
                </li>
                <li>
                    <a href="{{ route('warehouses.index') }}" class="nav-link {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
                        <i class="bi bi-building"></i>Warehouses
                    </a>
                </li>
                <li>
                    <a href="{{ route('warehouse-locations.index') }}" class="nav-link {{ request()->routeIs('warehouse-locations.*') ? 'active' : '' }}">
                        <i class="bi bi-geo-alt"></i>Locations
                    </a>
                </li>

                <li class="sidebar-heading">History & Logs</li>
                <li>
                    <a href="{{ route('inventory.movements') }}" class="nav-link {{ request()->routeIs('inventory.movements*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-left-right"></i>Stock Movements
                    </a>
                </li>
                @can('viewAny', \Modules\AuthenticationAudit\Models\AuditLog::class)
                <li>
                    <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-check"></i>Audit Logs
                    </a>
                </li>
                @endcan
            </ul>

            <hr class="border-secondary my-2">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle px-2 py-1" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5 me-2"></i>
                    <strong class="text-truncate">{{ $currentUser->username ?? $currentUser->email }}</strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                    @if(isset($userOrganizations) && $userOrganizations->count() > 1)
                        <li><a class="dropdown-item" href="{{ route('organizations.select') }}"><i class="bi bi-arrow-repeat me-2"></i>Switch Organization</a></li>
                        <li><hr class="dropdown-divider"></li>
                    @endif
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navbar -->
            <nav class="app-navbar d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">@yield('page_title', 'Stock Management')</h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    @if(isset($userOrganizations) && $userOrganizations->count() > 1)
                        <a href="{{ route('organizations.select') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-building me-1"></i>{{ $currentOrganization->name }} <span class="badge bg-secondary ms-1">Switch</span>
                        </a>
                    @else
                        <span class="text-muted small"><i class="bi bi-building me-1"></i>{{ $currentOrganization->name }}</span>
                    @endif

                    <div class="d-none d-md-flex align-items-center gap-2 border-start ps-3">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                            {{ $currentRole->name ?? $currentRole->code }}
                        </span>
                        <span class="small fw-semibold text-secondary">{{ $currentUser->email }}</span>
                    </div>
                </div>
            </nav>

            <!-- Body Container -->
            <main class="content-body">
                <!-- Flash Alerts -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                        <div>{{ session('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                        <div>{{ session('error') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-circle-fill fs-5 me-2"></i>
                        <div>{{ session('warning') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('info'))
                    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                        <div>{{ session('info') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="text-center py-3 text-muted small border-top bg-white">
                Stock Management Prototype &bull; Multi-Tenant Laravel 12 &bull; PostgreSQL
            </footer>
        </div>
    </div>

    <!-- Scripts: Double Click Protection & Idempotency Key Injection -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Intercept forms with action buttons (Post, Submit, Reverse, Reserve, Release)
            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.classList.contains('no-disable')) {
                        // Ensure hidden idempotency key input is populated
                        const idemInput = form.querySelector('input[name="idempotency_key"]');
                        if (idemInput && !idemInput.value) {
                            idemInput.value = 'web-' + crypto.randomUUID();
                        }
                        
                        setTimeout(() => {
                            submitBtn.disabled = true;
                            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
                        }, 10);
                    }
                });
            });
        });
    </script>
    @yield('scripts')
</body>
</html>
