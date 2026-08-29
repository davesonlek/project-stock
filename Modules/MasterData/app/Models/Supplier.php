<?php

namespace Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\MasterData\Traits\BelongsToOrganization;

class Supplier extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'suppliers';

    protected $fillable = [
        'organization_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'tax_id',
        'address',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function goodsSuppliers(): HasMany
    {
        return $this->hasMany(GoodsSupplier::class, 'supplier_id');
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Goods::class, 'goods_suppliers', 'supplier_id', 'goods_id')
            ->withPivot(['supplier_sku', 'purchase_price', 'lead_time_days', 'is_primary'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
