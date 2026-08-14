<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CompanySettingController extends Controller
{
    public function index()
    {
        $company = CompanySetting::current();

        return Inertia::render('master/company/page', [
            'company' => $company,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'nullable|string|max:50',
            'nama_perusahaan' => 'nullable|string|max:255',
            'alamat' => 'nullable|string|max:1000',
            'telp' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:5120',
            'stamp' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $company = CompanySetting::current();

        $data = [
            'kode' => $validated['kode'] ?? null,
            'nama_perusahaan' => $validated['nama_perusahaan'] ?? null,
            'alamat' => $validated['alamat'] ?? null,
            'telp' => $validated['telp'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'instagram' => $validated['instagram'] ?? null,
            'linkedin' => $validated['linkedin'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $logo = $request->file('logo');
            $logoName = time() . '_' . $logo->getClientOriginalName();
            $data['logo_path'] = $logo->storeAs('company', $logoName, 'public');
        }

        if ($request->hasFile('stamp')) {
            if ($company->stamp_path) {
                Storage::disk('public')->delete($company->stamp_path);
            }
            $stamp = $request->file('stamp');
            $stampName = time() . '_' . $stamp->getClientOriginalName();
            $data['stamp_path'] = $stamp->storeAs('company', $stampName, 'public');
        }

        $company->update($data);

        return redirect()->back()->with('success', 'Company settings updated successfully.');
    }
}
