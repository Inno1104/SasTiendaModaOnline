<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Plan;
use App\Models\Rol;
use App\Models\Tienda;
use App\Models\User;
use App\Services\BrandManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistroController extends Controller
{
    public function showForm()
    {
        $planes = Plan::where('activo', true)->orderBy('precio')->get();

        return view('auth.registro', compact('planes'));
    }

    public function store(Request $request, BrandManager $brandManager)
    {
        $request->validate([
            'tienda' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'plan_id' => 'nullable|exists:planes,id',
        ], [
            'email.unique' => 'Este correo ya está registrado.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        DB::transaction(function () use ($request, $brandManager) {
            // Plan (por defecto el más económico / Básico)
            $plan = $request->plan_id
                ? Plan::find($request->plan_id)
                : Plan::orderBy('precio')->first();

            // Crear la tienda en periodo de prueba (15 días)
            $tienda = Tienda::create([
                'nombre' => $request->tienda,
                'slug' => $this->slugUnico($request->tienda),
                'email' => $request->email,
                'plan_id' => $plan?->id,
                'estado' => 'prueba',
                'fecha_inicio' => Carbon::today(),
                'fecha_vencimiento' => Carbon::today()->addDays(15),
            ]);

            // Rol administrador (crea los roles base si no existen)
            $rolAdmin = Rol::firstOrCreate(['nombre' => 'Administrador'], ['descripcion' => 'Acceso total a la tienda']);
            Rol::firstOrCreate(['nombre' => 'Vendedor'], ['descripcion' => 'Gestión de ventas y clientes']);
            Rol::firstOrCreate(['nombre' => 'Almacén'], ['descripcion' => 'Gestión de inventario']);

            // Usuario administrador de la tienda
            $user = User::create([
                'rol_id' => $rolAdmin->id,
                'tienda_id' => $tienda->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'activo' => true,
            ]);

            // Catálogo inicial para que la tienda no empiece vacía
            $this->crearDatosIniciales($tienda->id, $user, $brandManager);

            Auth::login($user);
        });

        return redirect()->route('dashboard')
            ->with('success', '¡Bienvenido! Tu tienda fue creada. Tienes 15 días de prueba gratis.');
    }

    private function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre);
        $slug = $base;
        $i = 1;
        while (Tienda::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function crearDatosIniciales(int $tiendaId, User $user, BrandManager $brandManager): void
    {
        $cats = [
            ['Camisas', 'caballeros'], ['Blusas', 'damas'], ['Pantalones', 'unisex'],
            ['Vestidos', 'damas'], ['Ropa Deportiva', 'unisex'], ['Ropa Niños', 'ninos'],
            ['Chaquetas', 'unisex'], ['Accesorios', 'unisex'],
        ];
        foreach ($cats as $c) {
            Categoria::create([
                'tienda_id' => $tiendaId,
                'nombre' => $c[0],
                'slug' => Str::slug($c[0]).'-'.$tiendaId,
                'genero' => $c[1],
            ]);
        }
        foreach (['Genérica', 'Zara', 'Nike', 'Adidas'] as $m) {
            $brandManager->create([
                'nombre' => $m,
                'activo' => true,
            ], $user);
        }
    }
}
