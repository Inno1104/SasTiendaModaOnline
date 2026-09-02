<?php

namespace Tests\Unit;

use Tests\TestCase;

class UsuarioDesignAssetTest extends TestCase
{
    public function test_user_view_uses_reusable_form_and_independent_assets(): void
    {
        $view = file_get_contents(resource_path('views/usuarios/index.blade.php'));
        $form = file_get_contents(resource_path('views/usuarios/_form.blade.php'));

        $this->assertStringContainsString("@include('usuarios._form')", $view);
        $this->assertStringContainsString("asset('css/usuarios.css')", $view);
        $this->assertStringContainsString("asset('js/usuarios.js')", $view);
        $this->assertStringNotContainsString('<style>', $view);
        $this->assertStringContainsString('data-user-status-dialog', $view);
        $this->assertStringContainsString('aria-labelledby="userStatusDialogTitle"', $view);
        $this->assertStringContainsString('aria-live="assertive"', $view);
        $this->assertStringContainsString('aria-invalid="true"', $form);
        $this->assertStringContainsString('autocomplete="new-password"', $form);
    }

    public function test_user_assets_include_responsive_focus_and_confirmation_behaviour(): void
    {
        $css = file_get_contents(public_path('css/usuarios.css'));
        $script = file_get_contents(public_path('js/usuarios.js'));

        $this->assertStringContainsString('@media (max-width: 900px)', $css);
        $this->assertStringContainsString('.user-dialog::backdrop', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('dialog.showModal()', $script);
        $this->assertStringContainsString('pendingForm.requestSubmit()', $script);
        $this->assertStringContainsString('setCustomValidity', $script);
    }
}
