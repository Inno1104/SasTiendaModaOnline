<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClienteDesignAssetTest extends TestCase
{
    public function test_customer_views_share_styles_without_inline_presentation_or_handlers(): void
    {
        foreach (['index', 'create', 'edit', 'show'] as $viewName) {
            $view = file_get_contents(__DIR__.'/../../resources/views/clientes/'.$viewName.'.blade.php');

            $this->assertStringContainsString("asset('css/clientes.css')", $view);
            $this->assertStringNotContainsString('style=', $view);
            $this->assertStringNotContainsString('onclick=', $view);
            $this->assertStringNotContainsString('onsubmit=', $view);
        }
    }

    public function test_create_and_edit_reuse_one_complete_accessible_form(): void
    {
        $create = file_get_contents(__DIR__.'/../../resources/views/clientes/create.blade.php');
        $edit = file_get_contents(__DIR__.'/../../resources/views/clientes/edit.blade.php');
        $form = file_get_contents(__DIR__.'/../../resources/views/clientes/partials/form.blade.php');

        $this->assertStringContainsString("@include('clientes.partials.form'", $create);
        $this->assertStringContainsString("@include('clientes.partials.form'", $edit);

        foreach (['nombre', 'apellido', 'dni', 'fecha_nacimiento', 'genero', 'email', 'telefono', 'ciudad', 'direccion'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $form);
            $this->assertStringContainsString('for="'.$field.'"', $form);
            $this->assertStringContainsString("@error('".$field."')", $form);
        }
    }

    public function test_customer_styles_define_reusable_responsive_components(): void
    {
        $css = file_get_contents(__DIR__.'/../../public/css/clientes.css');

        foreach (['.customer-page-heading', '.customer-avatar', '.customer-form-grid', '.customer-profile-card', '.customer-metrics', '.customer-empty-result'] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('@media (max-width: 900px)', $css);
        $this->assertStringContainsString('@media (max-width: 600px)', $css);
    }

    public function test_customer_status_interactions_remain_in_the_shared_script(): void
    {
        $script = file_get_contents(__DIR__.'/../../public/js/clientes.js');

        $this->assertStringContainsString("document.querySelectorAll('[data-customer-status]')", $script);
        $this->assertStringContainsString('dialog.showModal()', $script);
        $this->assertStringContainsString('dialog.close()', $script);
    }
}
