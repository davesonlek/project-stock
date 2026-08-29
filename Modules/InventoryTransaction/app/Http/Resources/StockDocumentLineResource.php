<?php

namespace Modules\InventoryTransaction\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDocumentLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'goods_id' => $this->goods_id,
            'goods' => $this->whenLoaded('goods', function () {
                return [
                    'id' => $this->goods->id,
                    'barcode' => $this->goods->barcode,
                    'pack_size' => (float) $this->goods->pack_size,
                    'is_lot_tracked' => (bool) $this->goods->is_lot_tracked,
                    'is_serial_tracked' => (bool) $this->goods->is_serial_tracked,
                    'product' => $this->goods->relationLoaded('product') ? [
                        'id' => $this->goods->product->id,
                        'sku' => $this->goods->product->sku,
                        'name' => $this->goods->product->name,
                    ] : null,
                    'unit' => $this->goods->relationLoaded('unit') ? [
                        'id' => $this->goods->unit->id,
                        'code' => $this->goods->unit->code,
                        'name' => $this->goods->unit->name,
                    ] : null,
                ];
            }),
            'quantity' => $this->quantity !== null ? (float) $this->quantity : null,
            'counted_quantity' => $this->counted_quantity !== null ? (float) $this->counted_quantity : null,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'lot_id' => $this->lot_id,
            'lot' => $this->whenLoaded('stockLot', function () {
                return $this->stockLot ? [
                    'id' => $this->stockLot->id,
                    'lot_no' => $this->stockLot->lot_no,
                    'expired_at' => $this->stockLot->expired_at?->toDateString(),
                ] : null;
            }),
            'lot_no' => $this->lot_no,
            'manufactured_at' => $this->manufactured_at?->toDateString(),
            'expired_at' => $this->expired_at?->toDateString(),
            'serials' => $this->whenLoaded('lineSerials', function () {
                return $this->lineSerials->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'serial_id' => $s->serial_id,
                        'serial_no' => $s->serial_no,
                    ];
                });
            }),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
