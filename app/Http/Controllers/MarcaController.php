<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrandIndexRequest;
use App\Http\Requests\StoreMarcaRequest;
use App\Http\Requests\UpdateMarcaRequest;
use App\Models\Marca;
use App\Services\BrandManager;
use App\Services\BrandQuery;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Marca::class, 'marca');
    }

    public function index(BrandIndexRequest $request, BrandQuery $brandQuery)
    {
        $filters = $request->validated();
        $marcas = $brandQuery->apply(Marca::query(), $filters)
            ->paginate(15)
            ->withQueryString();
        $editar = isset($filters['edit']) ? Marca::find($filters['edit']) : null;
        if ($editar) {
            $this->authorize('update', $editar);
        }

        return view('marcas.index', compact('marcas', 'editar'));
    }

    public function store(StoreMarcaRequest $request, BrandManager $manager)
    {
        $manager->create($request->validated(), $request->user());

        return redirect()->route('marcas.index')->with('success', 'Marca creada exitosamente.');
    }

    public function update(UpdateMarcaRequest $request, Marca $marca, BrandManager $manager)
    {
        $manager->update($marca, $request->validated(), $request->user());

        return redirect()->route('marcas.index')->with('success', 'Marca actualizada.');
    }

    public function destroy(Marca $marca, BrandManager $manager, Request $request)
    {
        $manager->delete($marca, $request->user());

        return back()->with('success', 'Marca eliminada.');
    }
}
