<?php

namespace App\Http\Controllers;

use App\Exceptions\UserPlanLimitReached;
use App\Http\Requests\ChangeUsuarioStatusRequest;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Requests\UserIndexRequest;
use App\Models\Rol;
use App\Models\User;
use App\Services\UserAdministrationService;
use App\Services\UserQuery;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly UserAdministrationService $userAdministration,
        private readonly UserQuery $userQuery
    ) {}

    public function index(UserIndexRequest $request)
    {
        $tid = (int) $request->user()->tienda_id;
        $filters = $request->validated();
        $query = User::query()
            ->where('tienda_id', $tid)
            ->where('es_super_admin', false)
            ->with('rol');
        $usuarios = $this->userQuery
            ->apply($query, $filters)
            ->paginate((int) ($filters['por_pagina'] ?? 15))
            ->withQueryString();
        $roles = Rol::query()->whereIn('nombre', Rol::ASSIGNABLE_NAMES)->orderBy('id')->get();
        $usuariosRegistrados = User::query()
            ->where('tienda_id', $tid)
            ->where('es_super_admin', false)
            ->count();
        $editar = null;
        if (isset($filters['edit'])) {
            $editar = $this->storeUserOrFail($filters['edit'], $tid);
            $this->authorize('update', $editar);
        }
        $tienda = $request->user()->tienda;

        return view('usuarios.index', compact('usuarios', 'roles', 'editar', 'tienda', 'usuariosRegistrados'));
    }

    public function store(StoreUsuarioRequest $request)
    {
        $this->authorize('create', User::class);
        $data = $request->validated();

        try {
            $this->userAdministration->create((int) $request->user()->tienda_id, $data, $request->user());
        } catch (UserPlanLimitReached $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado exitosamente.');
    }

    public function update(UpdateUsuarioRequest $request, int $usuarioId)
    {
        $usuario = $this->storeUserOrFail($usuarioId, (int) $request->user()->tienda_id);
        $this->authorize('update', $usuario);

        $data = $request->validated();

        $this->userAdministration->update($usuario, $data, $request->user());

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function toggle(ChangeUsuarioStatusRequest $request, int $usuarioId)
    {
        $usuario = $this->storeUserOrFail($usuarioId, (int) $request->user()->tienda_id);
        $this->authorize('changeStatus', $usuario);
        $data = $request->validated();
        $this->userAdministration->changeStatus($usuario, (bool) $data['activo'], $data['motivo'], $request->user());

        return back()->with('success', 'Estado del usuario actualizado.');
    }

    private function storeUserOrFail(mixed $userId, int $storeId): User
    {
        $userId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($userId === false, 404);

        return User::query()
            ->where('tienda_id', $storeId)
            ->where('es_super_admin', false)
            ->findOrFail($userId);
    }
}
