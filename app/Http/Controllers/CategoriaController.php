<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryIndexRequest;
use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Models\Categoria;
use App\Services\CategoryManager;
use App\Services\CategoryQuery;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Categoria::class, 'categoria');
    }

    public function index(CategoryIndexRequest $request, CategoryQuery $categoryQuery)
    {
        $filters = $request->validated();
        $categorias = $categoryQuery->apply(Categoria::query(), $filters)
            ->paginate(15)
            ->withQueryString();
        $editar = isset($filters['edit']) ? Categoria::find($filters['edit']) : null;
        if ($editar) {
            $this->authorize('update', $editar);
        }

        return view('categorias.index', compact('categorias', 'editar'));
    }

    public function store(StoreCategoriaRequest $request, CategoryManager $manager)
    {
        $manager->create($request->validated(), $request->user());

        return redirect()->route('categorias.index')->with('success', 'Categoría creada exitosamente.');
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria, CategoryManager $manager)
    {
        $manager->update($categoria, $request->validated(), $request->user());

        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Categoria $categoria, CategoryManager $manager, Request $request)
    {
        $manager->delete($categoria, $request->user());

        return back()->with('success', 'Categoría eliminada.');
    }
}
