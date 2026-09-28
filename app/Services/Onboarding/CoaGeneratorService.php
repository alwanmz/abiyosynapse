<?php

namespace App\Services\Onboarding;

use App\Models\Company;
use App\Services\AiService;
use App\Support\AccountRole;
use App\Support\DefaultChartOfAccounts;
use Throwable;

class CoaGeneratorService
{
    public function __construct(
        private readonly AiService $ai,
        private readonly CoaDraftValidator $validator,
    ) {
    }

    /**
     * @return array{accounts: array<int, array<string, mixed>>, mapping: array<string, string>, source: string, notice: ?string}
     */
    public function generate(Company $company): array
    {
        if (! $this->ai->isConfigured()) {
            return $this->standard('AI belum dikonfigurasi, jadi kami siapkan COA standar yang bisa Anda sesuaikan.');
        }

        $userPrompt = $this->profilePrompt($company);

        try {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $answer = $this->ai->generateJson($this->systemPrompt(), $userPrompt, config('services.deepseek.model'));
                $result = $this->validator->normalize(is_array($answer['accounts'] ?? null) ? $answer['accounts'] : []);

                if ($result['errors'] === [] && count($result['accounts']) >= 10) {
                    $suggested = is_array($answer['role_mapping'] ?? null) ? $answer['role_mapping'] : [];

                    return [
                        'accounts' => $result['accounts'],
                        'mapping' => $this->validator->suggestMapping($result['accounts'], $suggested),
                        'source' => 'ai',
                        'notice' => null,
                    ];
                }

                $userPrompt .= "\n\nJawaban sebelumnya tidak valid:\n- " . implode("\n- ", array_slice($result['errors'] ?: ['Terlalu sedikit akun.'], 0, 15))
                    . "\nPerbaiki dan kirim ulang JSON lengkap.";
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $this->standard('AI gagal menyusun COA saat ini, jadi kami siapkan COA standar yang bisa Anda sesuaikan.');
    }

    /** @return array{accounts: array<int, array<string, mixed>>, mapping: array<string, string>, source: string, notice: ?string} */
    public function standard(?string $notice = null): array
    {
        $accounts = $this->validator->normalize(DefaultChartOfAccounts::accounts())['accounts'];

        return [
            'accounts' => $accounts,
            'mapping' => $this->validator->suggestMapping($accounts),
            'source' => 'standard',
            'notice' => $notice,
        ];
    }

    private function systemPrompt(): string
    {
        $lines = collect(DefaultChartOfAccounts::REPORT_LINES)
            ->map(fn (array $items, string $report) => $report . ': ' . implode(', ', array_keys($items)))
            ->implode("\n");

        $roles = collect(AccountRole::cases())
            ->map(fn (AccountRole $role) => "- {$role->value}: {$role->label()} ({$role->description()}) tipe " . implode('/', $role->allowedTypes())
                . ($role->isParentRole() ? ', boleh akun header' : ', harus akun postable'))
            ->implode("\n");

        return <<<PROMPT
Anda adalah akuntan senior Indonesia yang menyusun Bagan Akun (Chart of Accounts) untuk software ERP, sesuai SAK EP/PSAK.
Susun COA berjenjang yang lengkap dan relevan untuk profil usaha yang diberikan: sertakan akun khusus industrinya (mis. klinik: Pendapatan Jasa Medis, Persediaan Obat), tetapi tetap ringkas (40-90 akun).
Gunakan nama akun Bahasa Indonesia dan kode bertingkat konsisten (contoh 1, 1.1, 1.1.01). Akun induk (header) is_postable=false; akun transaksi is_postable=true.
Tipe akun hanya: asset, liability, equity, revenue, expense. Anak akun wajib bertipe sama dengan induknya. normal_balance: debit untuk asset/expense, credit untuk liability/equity/revenue (kecuali akun kontra seperti Akumulasi Penyusutan).
report_line untuk akun postable wajib salah satu dari daftar berikut (asset/liability/equity pakai balance_sheet, revenue/expense pakai profit_loss); akun header report_line null:
{$lines}
COA wajib punya akun yang cocok untuk setiap peran sistem berikut, dan isi role_mapping dengan kode akunnya:
{$roles}
Jawab HANYA JSON object dengan schema:
{"accounts":[{"code":string,"name":string,"type":string,"normal_balance":"debit"|"credit","is_postable":boolean,"parent_code":string|null,"report_line":string|null}],"role_mapping":{"<peran>":"<kode akun>"}}
PROMPT;
    }

    private function profilePrompt(Company $company): string
    {
        $type = $company->business_type ?? 'campuran';

        return implode("\n", array_filter([
            "Nama perusahaan: {$company->name}",
            'Industri: ' . ($company->industry ?? '-'),
            "Jenis usaha: {$type}",
            'Skala usaha: ' . ($company->business_scale ?? '-'),
            'Mengelola persediaan barang: ' . ($company->uses_inventory ? 'ya' : 'tidak'),
            'Pengusaha Kena Pajak (PPN): ' . ($company->is_pkp ? 'ya' : 'tidak'),
            $company->business_description ? "Deskripsi usaha: {$company->business_description}" : null,
            "Mata uang dasar: {$company->currency}",
        ]));
    }
}
