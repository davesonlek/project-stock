<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;
use Modules\MasterData\Models\Category;
use Modules\MasterData\Models\Product;
use Modules\MasterData\Models\Unit;
use Tests\TestCase;

class MasterDataWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@test.com')->first();
        $this->org = Organization::where('name', 'Global Supply Co.')->first();
    }

    protected function authSession(): array
    {
        return ['current_organization_id' => $this->org->id];
    }

    public function test_categories_web_crud(): void
    {
        // 1. Index
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get('/categories')
            ->assertStatus(200)
            ->assertSee('Product Categories');

        // 2. Create
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post('/categories', [
                'name' => 'Web Test Category',
            ])
            ->assertRedirect(route('categories.index'));

        // 3. Edit & Update
        $cat = Category::where('name', 'Web Test Category')->first();
        $this->assertNotNull($cat);

        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get("/categories/{$cat->id}/edit")
            ->assertStatus(200);

        $this->actingAs($this->admin)->withSession($this->authSession())
            ->put("/categories/{$cat->id}", [
                'name' => 'Updated Category Name',
            ])
            ->assertRedirect(route('categories.index'));

        // 4. Deactivate
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post("/categories/{$cat->id}/deactivate")
            ->assertRedirect();

        $cat->refresh();
        $this->assertFalse($cat->is_active);
    }

    public function test_products_web_crud(): void
    {
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get('/products')
            ->assertStatus(200)
            ->assertSee('Products');

        $category = Category::forOrganization($this->org->id)->first();
        $sku = 'WEB-PRD-' . uniqid();
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post('/products', [
                'sku' => $sku,
                'name' => 'Web Test Product',
                'category_id' => $category->id,
            ])
            ->assertRedirect(route('products.index'));

        $prd = Product::where('sku', $sku)->first();
        $this->assertNotNull($prd);

        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get("/products/{$prd->id}")
            ->assertStatus(200)
            ->assertSee('Web Test Product');
    }

    public function test_goods_web_crud(): void
    {
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get('/goods')
            ->assertStatus(200)
            ->assertSee('Goods (Stock Keeping Units)');

        $product = Product::where('organization_id', $this->org->id)->first();
        $unit = Unit::where('organization_id', $this->org->id)->first();

        $this->actingAs($this->admin)->withSession($this->authSession())
            ->post('/goods', [
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'pack_size' => 1,
                'cost_price' => 15.50,
                'selling_price' => 25.00,
                'is_lot_tracked' => 1,
                'is_serial_tracked' => 0,
            ])
            ->assertRedirect(route('goods.index'));
    }

    public function test_warehouses_and_locations_web_crud(): void
    {
        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get('/warehouses')
            ->assertStatus(200)
            ->assertSee('Warehouses');

        $this->actingAs($this->admin)->withSession($this->authSession())
            ->get('/warehouse-locations')
            ->assertStatus(200)
            ->assertSee('Warehouse Storage Locations');
    }
}
