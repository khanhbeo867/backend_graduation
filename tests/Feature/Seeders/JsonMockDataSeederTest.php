<?php

namespace Tests\Feature\Seeders;

use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemCategory;
use App\Models\LoanForm;
use App\Models\LoanFormItem;
use App\Models\PenaltyForm;
use App\Models\ReturnForm;
use App\Models\ReturnFormItem;
use App\Models\User;
use Database\Seeders\JsonMockDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JsonMockDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_mock_data_seeder_imports_required_data(): void
    {
        $data = json_decode(
            (string) file_get_contents(database_path('seeders/data/costume-rental-db.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->seed(JsonMockDataSeeder::class);

        $this->assertSame(count($data['users']), User::query()->count());
        $this->assertSame(count($data['employees']), Employee::query()->count());
        $this->assertSame(count($data['categories']), ItemCategory::query()->count());
        $this->assertSame(count($data['images']), GalleryImage::query()->count());
        $this->assertSame(count($data['costumes']) + count($data['equipment_props']), EquipmentProp::query()->count());
        $this->assertSame(count($data['inventory']), InventoryItem::query()->count());
        $this->assertSame(count($data['loan_forms']), LoanForm::query()->count());
        $this->assertSame(count($data['loan_form_items']), LoanFormItem::query()->count());
        $this->assertSame(count($data['return_forms']), ReturnForm::query()->count());
        $this->assertSame(count($data['return_form_items']), ReturnFormItem::query()->count());
        $this->assertSame(count($data['penalty_forms']), PenaltyForm::query()->count());
        $this->assertSame(count($data['invoices']), Invoice::query()->count());

        $this->assertDatabaseHas('users', [
            'id' => 1,
            'username' => 'quanghiep031',
            'employee_id' => 1,
        ]);
        $this->assertTrue(Hash::check('123123', User::query()->findOrFail(1)->password));

        $this->assertDatabaseHas('employees', [
            'id' => 1,
            'user_id' => 1,
            'employee_code' => 'D00001',
        ]);

        $this->assertDatabaseHas('equipment_prop_gallery_image', [
            'gallery_image_id' => $data['costumes'][0]['images'][0],
        ]);
    }
}
