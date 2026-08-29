<?php

namespace Modules\InventoryTransaction\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\InventoryTransaction\Exceptions\StockDocumentException;
use Modules\InventoryTransaction\Services\ApproveStockDocumentService;
use Modules\InventoryTransaction\Services\CancelStockDocumentService;
use Modules\InventoryTransaction\Services\PostStockDocumentService;
use Modules\InventoryTransaction\Services\QueryStockDocumentService;
use Modules\InventoryTransaction\Services\ReserveStockService;
use Modules\InventoryTransaction\Services\ReverseStockDocumentService;
use Modules\InventoryTransaction\Services\SubmitStockDocumentService;

class WebStockDocumentWorkflowController extends Controller
{
    public function __construct(
        protected QueryStockDocumentService $queryService,
        protected SubmitStockDocumentService $submitService,
        protected ApproveStockDocumentService $approveService,
        protected CancelStockDocumentService $cancelService,
        protected ReserveStockService $reserveService,
        protected PostStockDocumentService $postService,
        protected ReverseStockDocumentService $reverseService
    ) {}

    public function submit(string $id): RedirectResponse
    {
        $document = $this->queryService->getById($id);
        Gate::authorize('submit', $document);

        try {
            $this->submitService->execute($id);
            return redirect()->route('stock.documents.show', $id)->with('success', 'Document submitted for approval. It is now locked from further edits.');
        } catch (StockDocumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error submitting document: ' . $e->getMessage());
        }
    }

    public function approve(string $id): RedirectResponse
    {
        $document = $this->queryService->getById($id);
        Gate::authorize('approve', $document);

        try {
            $this->approveService->execute($id);
            return redirect()->route('stock.documents.show', $id)->with('success', 'Document approved successfully. Stock will NOT change until POST.');
        } catch (StockDocumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error approving document: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $document = $this->queryService->getById($id);
        Gate::authorize('cancel', $document);

        try {
            $reason = $request->input('reason', 'Cancelled by user');
            $this->cancelService->execute($id, $reason);

            return redirect()->route('stock.documents.show', $id)->with('success', 'Document cancelled successfully. Any active reservations were released.');
        } catch (StockDocumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error cancelling document: ' . $e->getMessage());
        }
    }

    public function reserve(Request $request, string $documentId, int $lineId): RedirectResponse
    {
        $document = $this->queryService->getById($documentId);
        Gate::authorize('update', $document);

        try {
            $expiresAt = $request->input('expires_at');
            $this->reserveService->execute($documentId, $lineId, $expiresAt);

            return redirect()->route('stock.documents.show', $documentId)->with('success', 'Stock reserved successfully for this line.');
        } catch (StockDocumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Error reserving stock: ' . $e->getMessage());
        }
    }

    public function post(Request $request, string $id): RedirectResponse
    {
        $document = $this->queryService->getById($id);
        Gate::authorize('post', $document);

        try {
            $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();

            $result = $this->postService->execute($id, $idempotencyKey, $request->all());

            $msg = $result['is_replay']
                ? 'Document already posted (idempotent replay).'
                : 'Document posted successfully. Inventory balances and immutable stock movements updated.';

            return redirect()->route('stock.documents.show', $id)->with('success', $msg);
        } catch (StockDocumentException $e) {
            $userMsg = match ($e->getErrorCode()) {
                'INSUFFICIENT_AVAILABLE_STOCK' => 'Unable to post document: Available stock has changed since approval or is insufficient. Please review current stock balance.',
                'INSUFFICIENT_LOT_STOCK' => 'Unable to post document: Insufficient stock available in the specified lot.',
                'LOT_EXPIRED' => 'Unable to post document: One or more selected lots have expired.',
                'SERIAL_ALREADY_EXISTS' => 'Unable to post document: One or more serial numbers already exist in stock.',
                'SERIAL_NOT_AVAILABLE' => 'Unable to post document: One or more serial numbers are not available in the required status.',
                default => $e->getMessage(),
            };

            return back()->with('error', $userMsg . ' (Code: ' . $e->getErrorCode() . ')');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error posting document: ' . $e->getMessage());
        }
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        $document = $this->queryService->getById($id);
        Gate::authorize('reverse', $document);

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();

            $result = $this->reverseService->execute($id, $idempotencyKey, $request->only('reason'));

            return redirect()->route('stock.documents.show', $id)->with('success', 'Document reversed successfully. Compensating reversal movements and document generated.');
        } catch (StockDocumentException $e) {
            $userMsg = match ($e->getErrorCode()) {
                'REVERSAL_INSUFFICIENT_STOCK' => 'Unable to reverse this document: The required stock is no longer available at the original location.',
                'DOCUMENT_ALREADY_REVERSED' => 'This document has already been reversed.',
                'RESERVATION_STATE_CONFLICT' => 'Cannot reverse document while active reservations exist.',
                default => $e->getMessage(),
            };

            return back()->with('error', $userMsg . ' (Code: ' . $e->getErrorCode() . ')');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error reversing document: ' . $e->getMessage());
        }
    }
}
