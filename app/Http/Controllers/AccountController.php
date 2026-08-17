<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('master/accounts/page', [
            'accounts' => Account::with('parent:id,code,name')->orderBy('code')->get(),
        ]);
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

        $account->delete();

        return redirect()->back()->with('success', __('messages.account.deleted'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:30|unique:accounts,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => 'nullable|exists:accounts,id',
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }
}
