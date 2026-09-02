<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Tienda;
use App\Models\Plan;
use App\Models\User;
use App\Models\Venta;
use App\Models\Producto;

class SuperAdminController extends Controller
{
    public function index()
    {
        $totalTiendas = Tienda::count();
        $activas      = Tienda::where('estado', 'activa')->count();
        $prueba       = Tienda::where('estado', 'prueba')->count();
        $suspendidas  = Tienda::where('estado', 'suspendida')->count();

        // Ingreso mensual recurrente estimado (tiendas no suspendidas)
        $mrr = Tienda::where('estado', '!=', 'suspendida')
            ->join('planes', 'tiendas.plan_id', '=', 'planes.id')
            ->sum('planes.precio');

        $totalUsuarios  = User::where('es_super_admin', false)->count();
        $totalProductos = Producto::count();          // global (sin scope para super-admin)
        $ventasGlobal   = Venta::where('estado', 'completada')->sum('total');

        // Tiendas por plan (para gráfico)
        $tiendasPorPlan = Tienda::select('planes.nombre', DB::raw('COUNT(*) as total'))
            ->join('planes', 'tiendas.plan_id', '=', 'planes.id')
            ->groupBy('planes.id', 'planes.nombre')->get();

        // Últimas tiendas registradas
        $ultimasTiendas = Tienda::with('plan')->orderByDesc('created_at')->limit(8)->get();

        return view('superadmin.index', compact(
            'totalTiendas', 'activas', 'prueba', 'suspendidas', 'mrr',
            'totalUsuarios', 'totalProductos', 'ventasGlobal',
            'tiendasPorPlan', 'ultimasTiendas'
        ));
    }

    public function tiendas(Request $request)
    {
        $q = Tienda::with('plan')->withCount('usuarios');

        if ($request->filled('buscar')) {
            $q->where('nombre', 'like', '%' . $request->buscar . '%');
        }
        if ($request->filled('estado')) {
            $q->where('estado', $request->estado);
        }

        $tiendas = $q->orderByDesc('created_at')->get();
        $planes  = Plan::orderBy('precio')->get();

        return view('superadmin.tiendas', compact('tiendas', 'planes'));
    }

    public function cambiarEstado(Request $request, Tienda $tienda)
    {
        $request->validate(['estado' => 'required|in:prueba,activa,suspendida']);
        $tienda->update(['estado' => $request->estado]);
        return back()->with('success', "Estado de «{$tienda->nombre}» actualizado a {$request->estado}.");
    }

    public function cambiarPlan(Request $request, Tienda $tienda)
    {
        $request->validate(['plan_id' => 'required|exists:planes,id']);
        $tienda->update(['plan_id' => $request->plan_id]);
        return back()->with('success', "Plan de «{$tienda->nombre}» actualizado.");
    }

    public function extender(Tienda $tienda)
    {
        $base = $tienda->fecha_vencimiento && $tienda->fecha_vencimiento->isFuture()
            ? $tienda->fecha_vencimiento
            : Carbon::today();
        $tienda->update([
            'fecha_vencimiento' => $base->copy()->addDays(30),
            'estado'            => $tienda->estado === 'suspendida' ? 'activa' : $tienda->estado,
        ]);
        return back()->with('success', "Suscripción de «{$tienda->nombre}» extendida 30 días.");
    }

    // ===================== PLANES =====================

    public function planes(Request $request)
    {
        $planes = Plan::withCount('tiendas')->orderBy('precio')->get();
        $editar = $request->filled('edit') ? Plan::find($request->edit) : null;
        return view('superadmin.planes', compact('planes', 'editar'));
    }

    public function guardarPlan(Request $request, Plan $plan = null)
    {
        $data = $request->validate([
            'nombre'         => 'required|string|max:100',
            'precio'         => 'required|numeric|min:0',
            'max_productos'  => 'required|integer|min:-1',
            'max_usuarios'   => 'required|integer|min:-1',
            'max_ventas_mes' => 'required|integer|min:-1',
            'descripcion'    => 'nullable|string|max:255',
            'caracteristicas'=> 'nullable|string',
        ]);

        $data['activo'] = $request->boolean('activo');
        $data['slug']   = $this->slugUnicoPlan($data['nombre'], $plan?->id);
        $data['caracteristicas'] = collect(preg_split('/\r\n|\r|\n/', $request->caracteristicas ?? ''))
            ->map(fn ($l) => trim($l))->filter()->values()->all();

        if ($plan) {
            $plan->update($data);
            $msg = "Plan «{$plan->nombre}» actualizado.";
        } else {
            Plan::create($data);
            $msg = "Plan «{$data['nombre']}» creado.";
        }

        return redirect()->route('admin.planes')->with('success', $msg);
    }

    public function togglePlan(Plan $plan)
    {
        $plan->update(['activo' => !$plan->activo]);
        $estado = $plan->activo ? 'activado' : 'desactivado';
        return back()->with('success', "Plan «{$plan->nombre}» {$estado}.");
    }

    public function eliminarPlan(Plan $plan)
    {
        if ($plan->tiendas()->count() > 0) {
            return back()->with('success', "No se puede eliminar «{$plan->nombre}»: tiene tiendas asignadas. Desactívalo en su lugar.");
        }
        $nombre = $plan->nombre;
        $plan->delete();
        return back()->with('success', "Plan «{$nombre}» eliminado.");
    }

    private function slugUnicoPlan(string $nombre, ?int $ignorarId = null): string
    {
        $base = Str::slug($nombre); $slug = $base; $i = 1;
        while (Plan::where('slug', $slug)->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // ===================== PERFIL =====================

    public function perfil()
    {
        $user = Auth::user();

        // Estadísticas globales para el perfil
        $stats = [
            'tiendas'    => Tienda::count(),
            'activas'    => Tienda::where('estado', 'activa')->count(),
            'usuarios'   => User::where('es_super_admin', false)->count(),
            'planes'     => Plan::where('activo', true)->count(),
        ];

        return view('superadmin.perfil', compact('user', 'stats'));
    }

    public function actualizarPerfil(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'telefono' => 'nullable|string|max:30',
        ], [
            'email.unique' => 'Ese correo ya está en uso por otra cuenta.',
        ]);

        $user->update($data);

        return back()->with('success', 'Tu perfil se actualizó correctamente.');
    }

    public function cambiarPassword(Request $request)
    {
        $request->validate([
            'password_actual' => 'required',
            'password'        => 'required|min:6|confirmed',
        ], [
            'password.confirmed' => 'La nueva contraseña y su confirmación no coinciden.',
            'password.min'       => 'La nueva contraseña debe tener al menos 6 caracteres.',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password_actual, $user->password)) {
            return back()->withErrors(['password_actual' => 'La contraseña actual es incorrecta.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Tu contraseña se cambió correctamente.');
    }
}
