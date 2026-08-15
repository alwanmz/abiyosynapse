<?php

namespace App\Http\Controllers;

use App\Models\CompanyUser;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $user = $request->user();

        $isMember = CompanyUser::where('user_id', $user->id)
            ->where('company_id', $validated['company_id'])
            ->exists();

        if (! $isMember) {
            return back()->with('error', 'Anda bukan anggota perusahaan tersebut.');
        }

        $request->session()->put('current_company_id', $validated['company_id']);
        $user->forceFill(['current_company_id' => $validated['company_id']])->save();

        return redirect()->route('dashboard')->with('success', 'Perusahaan berhasil diganti.');
    }
}
