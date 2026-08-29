<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\AuthenticationAudit\Models\AuditLog;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Models\StockDocument;
use Modules\InventoryTransaction\Models\StockMovement;
use Modules\InventoryTransaction\Models\StockReservation;
use Modules\InventoryTransaction\Services\CreateStockDocumentLineService;
use Modules\InventoryTransaction\Services\CreateStockDocumentService;
use Modules\InventoryTransaction\Services\DeleteStockDocumentLineService;
use Modules\InventoryTransaction\Services\QueryStockDocumentService;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class WebStockDocumentController extends Controller
{
    public function __construct(
        protected QueryStockDocumentService $queryService,
        protected CreateStockDocumentService $createDocService,
        protected CreateStockDocumentLineService $createLineService,
        protected DeleteStockDocumentLineService $deleteLineService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockDocument::class);

        $documents = $this->queryService->list([
            'search' => $request->query('search'),
            'document_type' => $request->query('document_type'),
            'status' => $request->query('status'),
            'warehouse_id' => $request->query('warehouse_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'sort' => $request->query('sort_by', 'created_at'),
            'direction' => $request->query('sort_direction', 'desc'),
            'per_page' => $request->query('per_page', 20),
        ]);

        $orgId = session('current_organization_id');
        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('stock-documents.index', compact('documents', 'warehouses'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', StockDocument::class);

        $type = strtoupper($request->query('type', 'RECEIVE'));
        $orgId = session('current_organization_id');

        $warehouses = Warehouse::forOrganization($orgId)->where('is_active', true)->with(['locations' => function ($q) {
            $q->where('is_active', true)->orderBy('code');
        }])->orderBy('name')->get();

        $suppliers = Supplier::forOrganization($orgId)->where('is_active', true)->orderBy('name')->get();

        return view('stock-documents.create', compact('type', 'warehouses', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', StockDocument::class);

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:RECEIVE,ISSUE,TRANSFER,ADJUSTMENT'],
            'source_warehouse_id' => ['nullable', 'integer'],
            'source_location_id' => ['nullable', 'integer'],
            'destination_warehouse_id' => ['nullable', 'integer'],
            'destination_location_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $document = $this->createDocService->execute($validated);
            return redirect()->route('stock.documents.show', $document->id)->with('success', 'Draft stock document created. You can now add items.');
        } catch (StockDocumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error creating document: ' . $e->getMessage());
        }
    }

    public function show(string $id): View
    {
        $document = $this->queryService->getById($id);
        Gate::authorize('view', $document);

        $orgId = session('current_organization_id');

        // Load lines, lot, serials
        $document->load([
            'sourceWarehouse', 'sourceLocation',
            'destinationWarehouse', 'destinationLocation',
            'supplier', 'creator', 'submitter', 'approver', 'canceller', 'poster',
            'lines.goods.product', 'lines.goods.unit', 'lines.stockLot', 'lines.lineSerials'
        ]);

        // Load active reservations for this document
        $activeReservations = StockReservation::where('document_id', $document->id)
            ->with(['goods.product', 'goods.unit', 'stockLot', 'creator'])
            ->get()
            ->keyBy('document_line_id');

        // Load stock movements for this document (or reversal document)
        $movements = StockMovement::where('document_id', $document->id)
            ->with(['goods.product', 'goods.unit', 'warehouse', 'location', 'performer', 'stockLot', 'reversalOriginalMovement'])
            ->orderBy('id', 'asc')
            ->get();

        // Load audit trail
        $auditLogs = AuditLog::where('organization_id', $orgId)
            ->where('entity_type', 'StockDocument')
            ->where('entity_id', (string) $document->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Available goods for adding lines (if DRAFT)
        $goodsList = Goods::forOrganization($orgId)->where('is_active', true)->with(['product', 'unit', 'stockLots'])->orderBy('name')->get();

        return view('stock-documents.show', compact('document', 'activeReservations', 'movements', 'auditLogs', 'goodsList'));
    }

    public function addLine(Request $request, string $id): RedirectResponse
    {
        $document = $this->queryService->getById($id);
        Gate::authorize('addLine', $document);

        $validated = $request->validate([
            'goods_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'counted_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lot_id' => ['nullable', 'integer'],
            'lot_no' => ['nullable', 'string', 'max:100'],
            'manufactured_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
            'serials_text' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        // Parse serials textarea (1 per line)
        if (!empty($validated['serials_text'])) {
            $serials = preg_split('/\r\n|\r|\n/', trim($validated['serials_text']));
            $validated['serials'] = array_values(array_filter(array_map('trim', $serials)));
        }

        try {
            $this->createLineService->execute($id, $validated);
            return redirect()->route('stock.documents.show', $id)->with('success', 'Document line added successfully.');
        } catch (StockDocumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error adding line: ' . $e->getMessage());
        }
    }

    public function deleteLine(string $documentId, int $lineId): RedirectResponse
    {
        $document = $this->queryService->getById($documentId);
        Gate::authorize('deleteLine', $document);

        try {
            $this->deleteLineService->execute($documentId, $lineId);
            return redirect()->route('stock.documents.show', $documentId)->with('success', 'Document line removed successfully.');
        } catch (StockDocumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error deleting line: ' . $e->getMessage());
        }
    }
}
