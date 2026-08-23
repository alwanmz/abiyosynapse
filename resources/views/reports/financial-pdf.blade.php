<!doctype html>
<html lang="{{ $snapshot['meta']['language'] ?? 'id' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $snapshot['meta']['report'] ?? 'Financial report' }}</title>
    <style>
        @page { margin: 36px 40px 42px; }
        body { color: #17202a; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; }
        h1 { color: #0f3d5e; font-size: 18px; margin: 0 0 3px; }
        h2 { color: #0f3d5e; font-size: 12px; border-bottom: 1px solid #14b8c4; padding-bottom: 4px; margin: 18px 0 6px; }
        h3 { color: #0f3d5e; font-size: 10px; margin: 10px 0 4px; }
        .muted { color: #64748b; }
        .meta { border: 1px solid #cbd5e1; padding: 8px; margin: 12px 0; }
        .meta td { padding: 2px 12px 2px 0; }
        table { border-collapse: collapse; width: 100%; margin: 4px 0 10px; }
        th { background: #dceff2; color: #0f3d5e; font-weight: bold; text-align: left; }
        th, td { border-bottom: 1px solid #dbe3e8; padding: 4px 5px; }
        .number { text-align: right; white-space: nowrap; }
        .total td { border-top: 1px solid #0f3d5e; font-weight: bold; }
        .warning { color: #8a5b0a; background: #fbf2e3; padding: 5px 7px; margin: 3px 0; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; color: #64748b; font-size: 8px; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $language = $snapshot['meta']['language'] ?? 'id';
        $labels = [
            'id' => ['framework' => 'Standar akuntansi', 'language' => 'Bahasa', 'period' => 'Periode', 'presentation' => 'Mata uang penyajian', 'functional' => 'Mata uang fungsional', 'rate' => 'Kurs', 'code' => 'Kode', 'name' => 'Nama', 'amount' => 'Jumlah', 'debit' => 'Debit', 'credit' => 'Kredit', 'warning' => 'Peringatan', 'generated' => 'Dibuat pada', 'disclaimer' => 'Output sistem dan wajib ditinjau akuntan perusahaan sebelum pelaporan statutori.'],
            'en' => ['framework' => 'Accounting framework', 'language' => 'Language', 'period' => 'Period', 'presentation' => 'Presentation currency', 'functional' => 'Functional currency', 'rate' => 'Rate', 'code' => 'Code', 'name' => 'Name', 'amount' => 'Amount', 'debit' => 'Debit', 'credit' => 'Credit', 'warning' => 'Warning', 'generated' => 'Generated at', 'disclaimer' => 'System-generated output and requires review by the company accountant before statutory filing.'],
            'zh' => ['framework' => '会计框架', 'language' => '语言', 'period' => '期间', 'presentation' => '列报货币', 'functional' => '功能货币', 'rate' => '汇率', 'code' => '代码', 'name' => '名称', 'amount' => '金额', 'debit' => '借方', 'credit' => '贷方', 'warning' => '警告', 'generated' => '生成时间', 'disclaimer' => '系统生成内容，提交法定报告前须由公司会计复核。'],
            'ja' => ['framework' => '会計基準', 'language' => '言語', 'period' => '期間', 'presentation' => '表示通貨', 'functional' => '機能通貨', 'rate' => '為替レート', 'code' => 'コード', 'name' => '名称', 'amount' => '金額', 'debit' => '借方', 'credit' => '貸方', 'warning' => '警告', 'generated' => '作成日時', 'disclaimer' => 'システム生成のため、法定報告前に会社の会計担当者が確認してください。'],
            'ko' => ['framework' => '회계 기준', 'language' => '언어', 'period' => '기간', 'presentation' => '표시 통화', 'functional' => '기능 통화', 'rate' => '환율', 'code' => '코드', 'name' => '이름', 'amount' => '금액', 'debit' => '차변', 'credit' => '대변', 'warning' => '경고', 'generated' => '생성 시각', 'disclaimer' => '시스템 생성 자료이며 법정 보고 전 회사 회계 담당자의 검토가 필요합니다.'],
        ][$language] ?? [];
        $reportTitles = [
            'id' => ['balance_sheet' => 'Laporan Posisi Keuangan', 'profit_loss' => 'Laporan Laba Rugi', 'equity' => 'Perubahan Ekuitas', 'cash_flow' => 'Laporan Arus Kas', 'notes' => 'Catatan atas Laporan Keuangan', 'trial_balance' => 'Neraca Saldo', 'general_ledger' => 'Buku Besar'],
            'en' => ['balance_sheet' => 'Statement of Financial Position', 'profit_loss' => 'Statement of Profit or Loss', 'equity' => 'Statement of Changes in Equity', 'cash_flow' => 'Statement of Cash Flows', 'notes' => 'Notes to Financial Statements', 'trial_balance' => 'Trial Balance', 'general_ledger' => 'General Ledger'],
            'zh' => ['balance_sheet' => '财务状况表', 'profit_loss' => '损益表', 'equity' => '权益变动表', 'cash_flow' => '现金流量表', 'notes' => '财务报表附注', 'trial_balance' => '试算平衡表', 'general_ledger' => '总账'],
            'ja' => ['balance_sheet' => '財政状態計算書', 'profit_loss' => '損益計算書', 'equity' => '株主資本等変動計算書', 'cash_flow' => 'キャッシュ・フロー計算書', 'notes' => '財務諸表注記', 'trial_balance' => '試算表', 'general_ledger' => '総勘定元帳'],
            'ko' => ['balance_sheet' => '재무상태표', 'profit_loss' => '손익계산서', 'equity' => '자본변동표', 'cash_flow' => '현금흐름표', 'notes' => '재무제표 주석', 'trial_balance' => '합계잔액시산표', 'general_ledger' => '총계정원장'],
        ][$language] ?? [];
    @endphp
    <div class="footer">Nexumi ERP · Export #{{ $run->id }} · {{ $snapshot['meta']['presentation_currency'] ?? '' }}</div>
    <h1>{{ $snapshot['company']['name'] ?? 'Nexumi ERP' }}</h1>
    <div class="muted">{{ $snapshot['company']['legal_name'] ?? '' }}</div>
    <h2>{{ $reportTitles[$snapshot['meta']['report'] ?? ''] ?? ucwords(str_replace('_', ' ', $snapshot['meta']['report'] ?? 'Financial report')) }}</h2>

    <table class="meta">
        <tr><td>{{ $labels['framework'] }}</td><td><strong>{{ strtoupper(str_replace('_', ' ', $snapshot['meta']['reporting_standard'] ?? '')) }}</strong></td><td>{{ $labels['language'] }}</td><td>{{ strtoupper($snapshot['meta']['language'] ?? 'id') }}</td></tr>
        <tr><td>{{ $labels['period'] }}</td><td>{{ $snapshot['meta']['from_date'] ?? '' }} - {{ $snapshot['meta']['to_date'] ?? '' }}</td><td>{{ $labels['presentation'] }}</td><td><strong>{{ $snapshot['meta']['presentation_currency'] ?? '' }}</strong></td></tr>
        <tr><td>{{ $labels['functional'] }}</td><td>{{ $snapshot['meta']['functional_currency'] ?? '' }}</td><td>{{ $labels['rate'] }}</td><td>{{ $snapshot['meta']['presentation_rate'] ?? '1' }} ({{ $snapshot['meta']['rate_effective_date'] ?? '' }})</td></tr>
    </table>

    @foreach ($snapshot['warnings'] ?? [] as $warning)
        <div class="warning"><strong>{{ $labels['warning'] }}:</strong> {{ $warning }}</div>
    @endforeach

    @php
        $renderBlock = function (array $data, string $heading = '') use (&$renderBlock, $labels): void {
            if ($heading !== '') echo '<h2>' . e(ucwords(str_replace('_', ' ', $heading))) . '</h2>';
            foreach ($data as $key => $value) {
                if (is_array($value) && array_is_list($value)) {
                    $items = array_values(array_filter($value, 'is_array'));
                    if ($items === []) continue;
                    echo '<h3>' . e(ucwords(str_replace('_', ' ', (string) $key))) . '</h3><table><thead><tr><th>' . e($labels['code']) . '</th><th>' . e($labels['name']) . '</th><th class="number">' . e($labels['amount']) . '</th><th class="number">' . e($labels['debit']) . '</th><th class="number">' . e($labels['credit']) . '</th></tr></thead><tbody>';
                    foreach ($items as $item) {
                        echo '<tr><td>' . e((string) ($item['code'] ?? '')) . '</td><td>' . e((string) ($item['name'] ?? $item['description'] ?? '')) . '</td><td class="number">' . e((string) ($item['amount'] ?? $item['closing'] ?? '')) . '</td><td class="number">' . e((string) ($item['debit'] ?? '')) . '</td><td class="number">' . e((string) ($item['credit'] ?? '')) . '</td></tr>';
                    }
                    echo '</tbody></table>';
                } elseif (is_array($value)) {
                    $renderBlock($value, (string) $key);
                } elseif (is_scalar($value) && ! in_array((string) $key, ['id', 'type', 'normal_balance'], true)) {
                    echo '<div><strong>' . e(ucwords(str_replace('_', ' ', (string) $key))) . ':</strong> ' . e((string) $value) . '</div>';
                }
            }
        };
    @endphp

    @if (($snapshot['meta']['report'] ?? '') === 'financial_statements')
        @foreach (($snapshot['data']['package'] ?? []) as $reportCode => $child)
            <div class="page-break"></div>
            <h2>{{ ucwords(str_replace('_', ' ', $reportCode)) }}</h2>
            @php $renderBlock($child['data'] ?? []); @endphp
        @endforeach
    @else
        @php $renderBlock($snapshot['data'] ?? []); @endphp
    @endif

    <p class="muted" style="margin-top: 18px;">{{ $labels['generated'] }} {{ $snapshot['meta']['generated_at'] ?? '' }}. {{ $labels['disclaimer'] }}</p>
</body>
</html>
