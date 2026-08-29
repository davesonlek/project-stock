<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\InventoryTransaction\Services\DashboardService;

class WebDashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(): View
    {
        $metrics = $this->dashboardService->getDashboardMetrics();

        return view('dashboard', $metrics);
    }
}
