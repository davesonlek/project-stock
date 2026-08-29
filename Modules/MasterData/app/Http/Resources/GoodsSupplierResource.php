<?php

namespace Modules\MasterData\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsSupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'goods_id' => $this->goods_id,
            'supplier_sku' => $this->supplier_sku,
            'purchase_price' => (float) $this->purchase_price,
            'lead_time_days' => (int) $this->lead_time_days,
            'is_primary' => (bool) $this->is_primary,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                ];
            }),
            'goods' => $this->whenLoaded('goods', function () {
                return [
                    'id' => $this->goods->id,
                    'barcode' => $this->goods->barcode,
                    'pack_size' => (float) $this->goods->pack_size,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
