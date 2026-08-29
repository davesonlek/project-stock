<?php

namespace Modules\InventoryTransaction\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_no' => $this->document_no,
            'document_type' => $this->document_type instanceof \BackedEnum ? $this->document_type->value : $this->document_type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'source_warehouse_id' => $this->source_warehouse_id,
            'source_warehouse' => $this->whenLoaded('sourceWarehouse', function () {
                return $this->sourceWarehouse ? [
                    'id' => $this->sourceWarehouse->id,
                    'code' => $this->sourceWarehouse->code,
                    'name' => $this->sourceWarehouse->name,
                ] : null;
            }),
            'source_location_id' => $this->source_location_id,
            'source_location' => $this->whenLoaded('sourceLocation', function () {
                return $this->sourceLocation ? [
                    'id' => $this->sourceLocation->id,
                    'code' => $this->sourceLocation->code,
                    'name' => $this->sourceLocation->name,
                ] : null;
            }),
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'destination_warehouse' => $this->whenLoaded('destinationWarehouse', function () {
                return $this->destinationWarehouse ? [
                    'id' => $this->destinationWarehouse->id,
                    'code' => $this->destinationWarehouse->code,
                    'name' => $this->destinationWarehouse->name,
                ] : null;
            }),
            'destination_location_id' => $this->destination_location_id,
            'destination_location' => $this->whenLoaded('destinationLocation', function () {
                return $this->destinationLocation ? [
                    'id' => $this->destinationLocation->id,
                    'code' => $this->destinationLocation->code,
                    'name' => $this->destinationLocation->name,
                ] : null;
            }),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return $this->supplier ? [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                ] : null;
            }),
            'remarks' => $this->remarks,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return $this->creator ? [
                    'id' => $this->creator->id,
                    'username' => $this->creator->username,
                    'email' => $this->creator->email,
                ] : null;
            }),
            'submitted_by' => $this->submitted_by,
            'submitter' => $this->whenLoaded('submitter', function () {
                return $this->submitter ? [
                    'id' => $this->submitter->id,
                    'username' => $this->submitter->username,
                    'email' => $this->submitter->email,
                ] : null;
            }),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'username' => $this->approver->username,
                    'email' => $this->approver->email,
                ] : null;
            }),
            'approved_at' => $this->approved_at?->toISOString(),
            'cancelled_by' => $this->cancelled_by,
            'canceller' => $this->whenLoaded('canceller', function () {
                return $this->canceller ? [
                    'id' => $this->canceller->id,
                    'username' => $this->canceller->username,
                    'email' => $this->canceller->email,
                ] : null;
            }),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'posted_by' => $this->posted_by,
            'poster' => $this->whenLoaded('poster', function () {
                return $this->poster ? [
                    'id' => $this->poster->id,
                    'username' => $this->poster->username,
                    'email' => $this->poster->email,
                ] : null;
            }),
            'posted_at' => $this->posted_at?->toISOString(),
            'reversal_of' => $this->reversal_of ? (string) $this->reversal_of : null,
            'lines' => StockDocumentLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
