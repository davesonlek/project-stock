<?php

namespace Modules\AuthenticationAudit\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\MasterData\Models\Brand;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Goods;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Supplier;
use Modules\MasterData\Models\Unit;
use Modules\MasterData\Models\Warehouse;
use Modules\MasterData\Models\WarehouseLocation;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'organizations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'industry',
        'address',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_organizations', 'organization_id', 'user_id')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function userOrganizations(): HasMany
    {
        return $this->hasMany(UserOrganization::class, 'organization_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'organization_id');
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'organization_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'organization_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'organization_id');
    }

    public function goods(): HasMany
    {
        return $this->hasMany(Goods::class, 'organization_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'organization_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'organization_id');
    }

    public function warehouseLocations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'organization_id');
    }
}
