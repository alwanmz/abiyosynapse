<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountRoleMapping;
use App\Support\AccountRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/accounts/page', [
            'accounts' => Account::with('parent:id,code,name')->orderBy('code')->get(),
            'roleOptions' => AccountRole::options(),
            'roleMappings' => AccountRoleMapping::pluck('account_id', 'role'),
        ]);
    }

    public function updateRoles(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (AccountRole::cases() as $role) {
            $rules["mappings.{$role->value}"] = ['required', 'integer', $this->tenantExists('accounts')];
        }
        $mappings = $request->validate($rules)['mappings'];

        $accounts = Account::whereIn('id', $mappings)->get()->keyBy('id');
        $errors = [];

        foreach (AccountRole::cases() as $role) {
            $account = $accounts->get((int) $mappings[$role->value]);

            if (! in_array($account->type, $role->allowedTypes(), true)) {
                $errors["mappings.{$role->value}"] = "\"{$role->label()}\" butuh akun bertipe " . implode('/', $role->allowedTypes()) . '.';
            } elseif (! $role->isParentRole() && ! $account->is_postable) {
                $errors["mappings.{$role->value}"] = "\"{$role->label()}\" butuh akun yang bisa diposting, bukan akun header.";
            }
        }

        if ($errors !== []) {
            return redirect()->back()->withErrors($errors);
        }

        DB::transaction(function () use ($mappings): void {
            foreach ($mappings as $role => $accountId) {
                AccountRoleMapping::updateOrCreate(['role' => $role], ['account_id' => (int) $accountId]);
            }
        });

        return redirect()->back()->with('success', 'Mapping akun inti berhasil disimpan.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Account::create($validated);

        return redirect()->back()->with('success', __('messages.account.created'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $validated = $this->validated($request, $account->id);

        if (($validated['parent_id'] ?? null) === $account->id) {
            return redirect()->back()->withErrors(['parent_id' => __('messages.account.cannot_be_own_parent')]);
        }

        foreach (AccountRoleMapping::where('account_id', $account->id)->get() as $mapping) {
            $role = $mapping->role;
            $postable = (bool) ($validated['is_postable'] ?? $account->is_postable);

            if (! in_array($validated['type'], $role->allowedTypes(), true) || (! $role->isParentRole() && ! $postable)) {
                return redirect()->back()->withErrors([
                    'type' => "Akun ini dipetakan sebagai \"{$role->label()}\"; tipe atau status header-nya tidak boleh diubah seperti itu.",
                ]);
            }
        }

        $account->update($validated);

        return redirect()->back()->with('success', __('messages.account.updated'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->children()->exists()) {
            return redirect()->back()->with('error', __('messages.account.cannot_delete_has_children'));
        }

        if ($account->journalLines()->exists()) {
            return redirect()->back()->with('error', __('messages.account.cannot_delete_in_use'));
        }

        if (AccountRoleMapping::where('account_id', $account->id)->exists()) {
            return redirect()->back()->with('error', 'Akun ini dipakai di Mapping Akun Inti. Ganti mapping-nya terlebih dahulu.');
        }

        $account->delete();

        return redirect()->back()->with('success', __('messages.account.deleted'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', $this->tenantUnique('accounts', 'code', $ignoreId)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => ['nullable', $this->tenantExists('accounts')],
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }
}
