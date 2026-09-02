<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreReceiptPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_page_contains_same_page_a4_and_ticket_preview_with_fiscal_data(): void
    {
        [$admin] = $this->context();

        $this->actingAs($admin)->get(route('tienda.edit'))
            ->assertOk()
            ->assertSee('Vista previa del comprobante')
            ->assertSee('Factura A4')
            ->assertSee('Ticket 80 mm')
            ->assertSee('DATOS DE FACTURACIÓN')
            ->assertSee('DATOS DE LA VENTA')
            ->assertSee('PAGOS Y REVERSIONES')
            ->assertSee('Snapshot comercial')
            ->assertSee('USD ($)')
            ->assertSee('B009')
            ->assertSee('F009')
            ->assertSee('T009')
            ->assertSee('data-receipt-preview', false)
            ->assertSee('data-store-profile-form', false)
            ->assertSee('css/tienda.css', false)
            ->assertSee('js/tienda.js', false)
            ->assertSee('data-logo-confirm', false)
            ->assertSee('data-submit-button', false);
    }

    public function test_preview_uses_safe_client_side_fields_without_an_extra_endpoint(): void
    {
        $script = file_get_contents(public_path('js/tienda.js'));

        $this->assertStringContainsString('showModal()', $script);
        $this->assertStringContainsString('target.textContent = fieldValue', $script);
        $this->assertStringContainsString("button.dataset.receiptFormat === 'ticket'", $script);
        $this->assertStringContainsString("'TICKET DE VENTA'", $script);
        $this->assertStringContainsString("item.setAttribute('aria-pressed'", $script);
        $this->assertStringNotContainsString('fetch(', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }

    public function test_store_experience_protects_submit_and_uses_accessible_dialogs(): void
    {
        $script = file_get_contents(public_path('js/tienda.js'));
        $styles = file_get_contents(public_path('css/tienda.css'));
        $view = file_get_contents(resource_path('views/tienda/edit.blade.php'));

        $this->assertStringContainsString('if (submitting)', $script);
        $this->assertStringContainsString("button.setAttribute('aria-busy', 'true')", $script);
        $this->assertStringContainsString('form.checkValidity()', $script);
        $this->assertStringNotContainsString('window.confirm', $script);
        $this->assertStringContainsString('data-logo-confirm aria-labelledby=', $view);
        $this->assertStringContainsString('<x-store.section', $view);
        $this->assertStringContainsString('@media(max-width:700px)', $styles);
        $this->assertStringContainsString(':focus-visible', $styles);
    }

    /** @return array{User, Tienda} */
    private function context(): array
    {
        $suffix = Str::lower(Str::random(8));
        $store = Tienda::create([
            'nombre' => 'Moda Preview',
            'slug' => 'preview-'.$suffix,
            'ruc' => '1790012345001',
            'email' => 'ventas@preview.test',
            'telefono' => '+593991234567',
            'direccion' => 'Av. Moda 123',
            'estado' => 'activa',
            'moneda' => 'USD',
            'simbolo_moneda' => '$',
            'igv' => 15,
            'serie_boleta' => 'B009',
            'serie_factura' => 'F009',
            'serie_ticket' => 'T009',
        ]);
        $admin = User::create([
            'rol_id' => Rol::firstOrCreate(['nombre' => Rol::ADMINISTRATOR])->id,
            'tienda_id' => $store->id,
            'name' => 'Administrador',
            'email' => $suffix.'@receipt-preview.test',
            'password' => 'password1',
            'activo' => true,
            'es_super_admin' => false,
        ]);

        return [$admin, $store];
    }
}
