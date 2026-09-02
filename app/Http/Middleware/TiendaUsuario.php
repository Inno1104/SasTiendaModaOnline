<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TiendaUsuario
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->es_super_admin) {
            return redirect()->route('admin.index');
        }

        if ($user && ! $user->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Tu cuenta está desactivada. Contacta al administrador.');
        }

        if (! $user?->tienda_id) {
            abort(403, 'Tu usuario no está asociado a una tienda.');
        }

        return $next($request);
    }
}
