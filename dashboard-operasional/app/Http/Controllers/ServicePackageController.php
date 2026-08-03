<?php

namespace App\Http\Controllers;

use App\Models\ServicePackage;
use Illuminate\Http\Request;

class ServicePackageController extends Controller
{
    public function index()
    {
        $packages = ServicePackage::latest()->get();

        return view('service-packages.index', compact('packages'));
    }

    public function create()
    {
        return view('service-packages.form', ['package' => new ServicePackage()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        ServicePackage::create($data);

        return redirect()->route('service-packages.index')->with('success', 'Paket berhasil ditambahkan.');
    }

    public function edit(ServicePackage $servicePackage)
    {
        return view('service-packages.form', ['package' => $servicePackage]);
    }

    public function update(Request $request, ServicePackage $servicePackage)
    {
        $servicePackage->update($this->validated($request));

        return redirect()->route('service-packages.index')->with('success', 'Paket berhasil diperbarui.');
    }

    public function destroy(ServicePackage $servicePackage)
    {
        $servicePackage->delete();

        return back()->with('success', 'Paket dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'     => 'required|string|max:255',
            'category' => 'required|in:paket_utama,tambahan',
            'price'    => 'required|numeric',
            'unit'     => 'nullable|string|max:50',
            'features' => 'required|string',
        ]);
    }
}