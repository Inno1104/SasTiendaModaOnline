<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProveedorDesignAssetTest extends TestCase
{
    public function test_create_and_edit_reuse_the_same_form(): void
    {
        $root = dirname(__DIR__, 2);
        $create = file_get_contents($root.'/resources/views/proveedores/create.blade.php');
        $edit = file_get_contents($root.'/resources/views/proveedores/edit.blade.php');
        $form = file_get_contents($root.'/resources/views/proveedores/_form.blade.php');

        $this->assertStringContainsString("@include('proveedores._form'", $create);
        $this->assertStringContainsString("@include('proveedores._form'", $edit);
        foreach (['nombre', 'ruc', 'pais', 'contacto', 'email', 'telefono', 'ciudad', 'direccion'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $form);
        }
    }

    public function test_supplier_views_use_shared_assets_without_inline_presentation_or_handlers(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['index', 'create', 'edit', '_form'] as $view) {
            $contents = file_get_contents($root."/resources/views/proveedores/{$view}.blade.php");
            $this->assertStringNotContainsString('style="', $contents);
            $this->assertStringNotContainsString('onsubmit=', $contents);
            $this->assertStringNotContainsString('onclick=', $contents);
        }

        $index = file_get_contents($root.'/resources/views/proveedores/index.blade.php');
        $this->assertStringContainsString("asset('css/proveedores.css')", $index);
        $this->assertStringContainsString("asset('js/proveedores.js')", $index);
    }

    public function test_styles_and_script_define_responsive_accessible_states(): void
    {
        $root = dirname(__DIR__, 2);
        $css = file_get_contents($root.'/public/css/proveedores.css');
        $script = file_get_contents($root.'/public/js/proveedores.js');

        foreach (['supplier-state.is-active', 'supplier-state.is-inactive', '@media(max-width:640px)', 'prefers-reduced-motion', 'supplier-dialog'] as $token) {
            $this->assertStringContainsString($token, $css);
        }
        foreach (['showModal', 'data-state-message', 'data-state-consequence', "addEventListener('cancel'", 'window.confirm'] as $token) {
            $this->assertStringContainsString($token, $script);
        }
    }
}
