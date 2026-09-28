<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateOnboardingChart;
use App\Models\Company;
use App\Services\CurrentCompany;
use App\Services\Onboarding\ChartOfAccountsInstaller;
use App\Services\Onboarding\CoaDraftValidator;
use App\Services\Onboarding\CoaGeneratorService;
use App\Services\Onboarding\CoaSpreadsheetService;
use App\Services\TenantAuthorizationService;
use App\Support\AccountRole;
use App\Support\DefaultChartOfAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * First-login wizard: business profile → chart of accounts (AI, upload or
 * standard) → review → core-account mapping → install. The working draft
 * lives in the cache per company so an AI run started after the response
 * can hand its result back to the next page load.
 */
class OnboardingController extends Controller
{
    public const INDUSTRIES = [
        'Perdagangan Eceran / Toko',
        'Distribusi / Grosir',
        'Manufaktur / Pabrik',
        'Konstruksi / Kontraktor',
        'Jasa Profesional / Konsultan',
        'Teknologi Informasi / Software',
        'Kesehatan / Klinik / Apotek',
        'Pendidikan / Kursus',
        'Makanan & Minuman / Restoran',
        'Hotel & Pariwisata',
        'Transportasi & Logistik',
        'Properti / Real Estate',
        'Pertanian / Perkebunan / Peternakan',
        'Otomotif / Bengkel',
        'Kecantikan / Salon / Spa',
        'Percetakan / Media / Kreatif',
        'Lainnya',
    ];

    private const SCALES = ['mikro', 'kecil', 'menengah', 'besar'];

    public const DRAFT_TTL_HOURS = 24;

    private const GENERATION_TIMEOUT_SECONDS = 300;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly CoaDraftValidator $validator,
        private readonly TenantAuthorizationService $authorization,
    ) {
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $company = $this->company();

        if ($company->isOnboarded()) {
            return redirect()->route('dashboard');
        }

        $canManage = $this->authorization->can($request->user(), $company, 'accounts.manage', 'companies.manage');
        $draft = $this->draft($company);

        if (($draft['status'] ?? null) === 'generating' && now()->timestamp - ($draft['started_at'] ?? 0) > self::GENERATION_TIMEOUT_SECONDS) {
            $draft = [
                ...app(CoaGeneratorService::class)->standard('AI terlalu lama merespons, jadi kami siapkan COA standar yang bisa Anda sesuaikan.'),
                'status' => 'review',
            ];
            $this->storeDraft($company, $draft);
        }
        $hasProfile = $company->industry !== null && $company->business_type !== null;

        $step = match (true) {
            ! $hasProfile || $request->query('step') === 'profile' => 'profile',
            $draft === null || $request->query('step') === 'method' => 'method',
            $draft['status'] === 'generating' => 'generating',
            $draft['status'] === 'mapping' => 'mapping',
            default => 'review',
        };

        return Inertia::render('onboarding/page', [
            'step' => $step,
            'canManage' => $canManage,
            'company' => $company->only([
                'name', 'industry', 'business_type', 'business_scale', 'business_description', 'uses_inventory', 'is_pkp',
            ]),
            'industries' => self::INDUSTRIES,
            'scales' => self::SCALES,
            'businessTypes' => Company::BUSINESS_TYPES,
            'aiConfigured' => app(\App\Services\AiService::class)->isConfigured(),
            'draft' => $draft && $draft['status'] !== 'generating' ? [
                'accounts' => $draft['accounts'],
                'mapping' => $draft['mapping'],
                'source' => $draft['source'],
                'notice' => $draft['notice'],
            ] : null,
            'roleOptions' => AccountRole::options(),
            'reportLineOptions' => DefaultChartOfAccounts::reportLineOptions(),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $company = $this->managedCompany($request);

        $validated = $request->validate([
            'industry' => ['required', 'string', 'max:100'],
            'business_type' => ['required', Rule::in(Company::BUSINESS_TYPES)],
            'business_scale' => ['required', Rule::in(self::SCALES)],
            'business_description' => ['nullable', 'string', 'max:1000'],
            'uses_inventory' => ['required', 'boolean'],
            'is_pkp' => ['required', 'boolean'],
        ]);

        $company->update($validated);

        return redirect()->route('onboarding.show');
    }

    public function generate(Request $request, CoaGeneratorService $generator): RedirectResponse
    {
        $company = $this->managedCompany($request);
        $method = $request->validate(['method' => ['required', Rule::in(['ai', 'standard'])]])['method'];

        if ($method === 'standard') {
            $this->storeDraft($company, [...$generator->standard(), 'status' => 'review']);

            return redirect()->route('onboarding.show');
        }

        $this->storeDraft($company, ['status' => 'generating', 'started_at' => now()->timestamp]);
        GenerateOnboardingChart::dispatchAfterResponse($company->id);

        return redirect()->route('onboarding.show');
    }

    public function template(): StreamedResponse
    {
        return app(CoaSpreadsheetService::class)->template();
    }

    public function upload(Request $request, CoaSpreadsheetService $spreadsheet): RedirectResponse
    {
        $company = $this->managedCompany($request);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt']]);

        try {
            $rows = $spreadsheet->parse($request->file('file'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $result = $this->validator->normalize($rows);

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages($this->indexedErrors('file', $result['errors']));
        }

        $this->storeDraft($company, [
            'accounts' => $result['accounts'],
            'mapping' => $this->validator->suggestMapping($result['accounts']),
            'source' => 'upload',
            'notice' => null,
            'status' => 'review',
        ]);

        return redirect()->route('onboarding.show');
    }

    /** Save the reviewed accounts and move on to mapping. */
    public function updateDraft(Request $request): RedirectResponse
    {
        $company = $this->managedCompany($request);
        $draft = $this->draft($company) ?? abort(409, 'Draft COA tidak ditemukan.');
        $request->validate(['accounts' => ['required', 'array', 'min:1', 'max:' . CoaSpreadsheetService::MAX_ROWS]]);

        $result = $this->validator->normalize($request->input('accounts'));

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages($this->indexedErrors('accounts', $result['errors']));
        }

        $this->storeDraft($company, [
            ...$draft,
            'accounts' => $result['accounts'],
            'mapping' => $this->validator->suggestMapping($result['accounts'], $draft['mapping'] ?? []),
            'status' => 'mapping',
        ]);

        return redirect()->route('onboarding.show');
    }

    public function backToReview(Request $request): RedirectResponse
    {
        $company = $this->managedCompany($request);
        $draft = $this->draft($company) ?? abort(409, 'Draft COA tidak ditemukan.');
        $this->storeDraft($company, [...$draft, 'status' => 'review']);

        return redirect()->route('onboarding.show');
    }

    public function complete(Request $request, ChartOfAccountsInstaller $installer): RedirectResponse
    {
        $company = $this->managedCompany($request);
        $draft = $this->draft($company);
        abort_if($draft === null || $draft['status'] !== 'mapping', 409, 'Draft COA belum siap disimpan.');

        $mapping = $request->validate(['mapping' => ['required', 'array']])['mapping'];
        $errors = $this->validator->mappingErrors($draft['accounts'], $mapping);

        if ($errors !== []) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($message, $role) => ["mapping.{$role}" => $message])->all());
        }

        $roleMapping = collect(AccountRole::cases())
            ->mapWithKeys(fn (AccountRole $role) => [$role->value => trim((string) $mapping[$role->value])])
            ->all();

        $installer->install($company, $draft['accounts'], $roleMapping);
        $company->forceFill(['onboarded_at' => now()])->save();
        Cache::forget(self::draftKey($company->id));

        return redirect()->route('dashboard')->with('success', 'Bagan akun berhasil dibuat. Selamat menggunakan Nexumi!');
    }

    /**
     * Inertia only shares the first message per key, so every error gets its
     * own key (accounts.0, accounts.1, ...).
     *
     * @param  array<int, string>  $errors
     * @return array<string, string>
     */
    private function indexedErrors(string $key, array $errors): array
    {
        return collect(array_slice($errors, 0, 30))->mapWithKeys(fn ($message, $index) => ["{$key}.{$index}" => $message])->all();
    }

    public static function draftKey(int $companyId): string
    {
        return "onboarding:{$companyId}:coa-draft";
    }

    /** @return array<string, mixed>|null */
    private function draft(Company $company): ?array
    {
        return Cache::get(self::draftKey($company->id));
    }

    /** @param array<string, mixed> $draft */
    private function storeDraft(Company $company, array $draft): void
    {
        Cache::put(self::draftKey($company->id), $draft, now()->addHours(self::DRAFT_TTL_HOURS));
    }

    private function company(): Company
    {
        return $this->currentCompany->get() ?? abort(409, 'Perusahaan aktif tidak ditemukan.');
    }

    private function managedCompany(Request $request): Company
    {
        $company = $this->company();
        abort_if($company->isOnboarded(), 409, 'Perusahaan ini sudah menyelesaikan pengaturan awal.');
        $this->authorization->ensure($request->user(), $company, 'accounts.manage', 'companies.manage');

        return $company;
    }
}
