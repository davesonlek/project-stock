<?php

namespace Modules\InventoryTransaction\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'organization_id' => (string) $this->organization_id,
            'goods_id' => $this->goods_id,
            'lot_id' => $this->lot_id,
            'warehouse_id' => $this->warehouse_id,
            'location_id' => $this->location_id,
            'document_id' => (string) $this->document_id,
            'document_line_id' => $this->document_line_id,
            'quantity' => (string) $this->quantity,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_by' => $this->created_by,
            'released_by' => $this->released_by,
            'released_at' => $this->released_at?->toISOString(),
            'consumed_by' => $this->consumed_by,
            'consumed_at' => $this->consumed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'goods' => $this->whenLoaded('goods', function () {
                return [
                    'id' => $this->goods->id,
                    'sku' => $this->goods->sku,
                    'name' => $this->goods->name,
                ];
            }),
            'stock_lot' => $this->whenLoaded('stockLot', function () {
                return $this->stockLot ? [
                    'id' => $this->stockLot->id,
                    'lot_no' => $this->stockLot->lot_no,
                    'expired_at' => $this->stockLot->expired_at?->toISOString(),
                ] : null;
            }),
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'code' => $this->warehouse->code,
                    'name' => $this->warehouse->name,
                ];
            }),
            'location' => $this->whenLoaded('location', function () {
                return [
                    'id' => $this->location->id,
                    'code' => $this->location->code,
                    'name' => $this->location->name,
                ];
            }),
        ];
    }
}
