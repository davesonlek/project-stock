<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Login' }} - Stock Management Prototype</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center bg-light min-vh-100 py-4">
    <div class="container" style="max-width: 440px;">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary mb-1">
                <i class="bi bi-boxes me-2"></i>Stock Prototype
            </h3>
            <p class="text-muted small">Warehouse & Inventory Management System</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                {{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')

        <div class="text-center mt-4 text-muted small">
            &copy; {{ date('Y') }} Stock Management Prototype. Laravel 12 & PostgreSQL.
        </div>
    </div>
</body>
</html>
