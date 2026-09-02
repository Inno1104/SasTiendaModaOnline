<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return Auth::user()->es_super_admin
                ? redirect()->route('admin.index')
                : redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'Ingrese un correo válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if (!$user->activo) {
                Auth::logout();
                return back()->with('error', 'Tu cuenta está desactivada. Contacta al administrador.');
            }

            // Super administrador → panel de control de la plataforma
            if ($user->es_super_admin) {
                $request->session()->regenerate();
                return redirect()->route('admin.index');
            }

            // Validar estado de la suscripción de la tienda
            $tienda = $user->tienda;
            if ($tienda && !$tienda->estaActiva()) {
                Auth::logout();
                $msg = $tienda->estado === 'suspendida'
                    ? 'Tu tienda está suspendida. Contacta al administrador de la plataforma.'
                    : 'Tu periodo de suscripción ha vencido. Renueva tu plan para continuar.';
                return back()->with('error', $msg);
            }

            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withInput()->with('error', 'Credenciales incorrectas. Verifica tu correo y contraseña.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
