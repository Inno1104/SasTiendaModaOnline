<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\StoreLogoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreLogoIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_administrator_can_upload_and_preview_ready_logo_with_generated_isolated_name(): void
    {
        [$admin, $store] = $this->context();

        $this->actingAs($admin)->post(route('tienda.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('mi logo.png', 500, 500),
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $path = $store->fresh()->logo;
        $this->assertMatchesRegularExpression(
            '#^tiendas/'.$store->id.'/logos/[a-f0-9-]+\.png$#',
            $path
        );
        $this->assertStringNotContainsString('mi logo', $path);
        Storage::disk('public')->assertExists($path);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('storage/'.$path, false)
            ->assertSee($store->fresh()->nombre);
    }

    public function test_replacing_logo_deletes_unreferenced_previous_file_after_success(): void
    {
        [$admin, $store] = $this->context();
        $storage = app(StoreLogoStorage::class);
        $oldPath = $storage->store(UploadedFile::fake()->image('old.png', 500, 500), $store->id);
        $store->forceFill(['logo' => $oldPath])->save();

        $this->actingAs($admin)->post(route('tienda.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('new.jpg', 500, 500),
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($store->fresh()->logo);
    }

    public function test_logo_can_be_removed_and_default_image_is_rendered(): void
    {
        [$admin, $store] = $this->context();
        $path = app(StoreLogoStorage::class)->store(
            UploadedFile::fake()->image('logo.png', 500, 500),
            $store->id
        );
        $store->forceFill(['logo' => $path])->save();

        $this->actingAs($admin)->post(route('tienda.update'), $this->payload([
            'eliminar_logo' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($store->fresh()->logo);
        Storage::disk('public')->assertMissing($path);
        $this->get(route('tienda.edit'))->assertOk()->assertSee('images/store-logo-default.svg');
    }

    public function test_scripted_svg_and_disguised_files_are_rejected(): void
    {
        [$admin, $store] = $this->context();
        $svg = UploadedFile::fake()->createWithContent(
            'unsafe.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs($admin)->post(route('tienda.update'), $this->payload(['logo' => $svg]))
            ->assertSessionHasErrors('logo');
        $this->assertNull($store->fresh()->logo);

        $fakePng = UploadedFile::fake()->createWithContent('fake.png', '<?php echo "unsafe";');
        $this->post(route('tienda.update'), $this->payload(['logo' => $fakePng]))
            ->assertSessionHasErrors('logo');
    }

    public function test_safe_svg_is_accepted_but_replace_and_remove_cannot_be_combined(): void
    {
        [$admin, $store] = $this->context();
        $svg = UploadedFile::fake()->createWithContent(
            'safe.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500"><rect width="500" height="500" fill="#ec4899"/></svg>'
        );

        $this->actingAs($admin)->post(route('tienda.update'), $this->payload([
            'logo' => $svg,
            'eliminar_logo' => '1',
        ]))->assertSessionHasErrors('logo');
        $this->assertNull($store->fresh()->logo);

        $this->post(route('tienda.update'), $this->payload(['logo' => $svg]))
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($store->fresh()->logo);
    }

    public function test_receipt_snapshot_gets_an_immutable_logo_copy(): void
    {
        [, $store] = $this->context();
        $storage = app(StoreLogoStorage::class);
        $current = $storage->store(UploadedFile::fake()->image('logo.png', 500, 500), $store->id);

        $snapshot = $storage->snapshot($current, $store->id);
        $this->assertNotSame($current, $snapshot);
        $this->assertStringStartsWith("comprobantes/{$store->id}/logos/", $snapshot);
        Storage::disk('public')->assertExists($snapshot);

        $storage->deleteCurrentIfUnreferenced($current, $store->id);
        Storage::disk('public')->assertMissing($current);
        Storage::disk('public')->assertExists($snapshot);
    }

    public function test_storage_rejects_foreign_and_manipulated_paths(): void
    {
        [, $store] = $this->context();
        Storage::disk('public')->put('tiendas/999/logos/shared.png', 'shared');
        $storage = app(StoreLogoStorage::class);

        $this->assertFalse($storage->deleteNew('tiendas/999/logos/shared.png', $store->id));
        $this->assertFalse($storage->deleteNew("tiendas/{$store->id}/logos/../secret.png", $store->id));
        Storage::disk('public')->assertExists('tiendas/999/logos/shared.png');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['nombre' => 'Moda Logo'], $overrides);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Tienda '.$suffix,
            'slug' => 'tienda-'.$suffix,
            'estado' => 'activa',
        ]);
        $admin = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@store-logo.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$admin, $store];
    }
}
