<?php
/**
 * The member's own statement.
 *
 * Everything is fetched in one full_report call and rendered client-side, so
 * the Excel and PDF exports work from data already in the page rather than
 * making the member wait on a second round trip.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

requireMemberPage();

portalHead('My Statement', true);
?>
<div class="min-h-screen flex flex-col">

    <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <img src="../image/mhwun_logo.png" alt="MHWUN" class="w-9 h-9 object-contain flex-shrink-0">
                <div class="min-w-0">
                    <p class="font-bold text-sm truncate"><?php echo e(currentMemberName()); ?></p>
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Member Statement</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="toggleTheme()" class="p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg" title="Toggle theme">
                    <span class="material-icons-round dark:!hidden">dark_mode</span>
                    <span class="material-icons-round !hidden dark:!block">light_mode</span>
                </button>
                <a href="logout.php" class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">
                    <span class="material-icons-round text-lg">logout</span>
                    <span class="hidden sm:inline">Sign out</span>
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-6 space-y-6">

        <div id="loader" class="py-20 text-center text-slate-400">
            <span class="material-icons-round animate-spin text-3xl">refresh</span>
            <p class="text-sm mt-2">Loading your statement...</p>
        </div>

        <div id="content" class="hidden space-y-6">

            <!-- Balances -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Savings Balance</p>
                    <p class="text-lg sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400" id="sSavings">₦0.00</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Loan Balance</p>
                    <p class="text-lg sm:text-2xl font-bold text-red-500" id="sLoanBal">₦0.00</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Total Contributed</p>
                    <p class="text-lg sm:text-2xl font-bold text-primary" id="sContrib">₦0.00</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Bank Loan Deducted</p>
                    <p class="text-lg sm:text-2xl font-bold text-purple-600 dark:text-purple-400" id="sBank">₦0.00</p>
                </div>
            </section>

            <!-- Period filter -->
            <section class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 sm:p-5">
                <div class="flex flex-col lg:flex-row lg:items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">From period</label>
                        <select id="fromPeriod" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"></select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">To period</label>
                        <select id="toPeriod" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"></select>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="resetPeriods()" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center gap-1.5">
                            <span class="material-icons-round text-base">restart_alt</span> All periods
                        </button>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-3" id="filterNote"></p>
            </section>

            <!-- Export -->
            <section class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <p class="font-bold text-sm">Download your statement</p>
                    <p class="text-xs text-slate-500 mt-0.5">Generated <span id="genAt"></span> · exports follow the period filter</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportExcel()" class="flex-1 sm:flex-none px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-medium flex items-center justify-center gap-1.5">
                        <span class="material-icons-round text-base">table_view</span> Excel
                    </button>
                    <button onclick="exportPdf()" class="flex-1 sm:flex-none px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-medium flex items-center justify-center gap-1.5">
                        <span class="material-icons-round text-base">picture_as_pdf</span> PDF
                    </button>
                </div>
            </section>

            <!-- Tabs -->
            <section class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 overflow-x-auto">
                    <div class="flex min-w-max" id="tabs"></div>
                </div>
                <div id="tabBody" class="overflow-x-auto"></div>
            </section>
        </div>
    </main>

    <footer class="py-5 text-center text-[11px] text-slate-400 uppercase tracking-widest">
        &copy; <?php echo date('Y'); ?> MHWUN OOUTH Branch
    </footer>
</div>

<script>
    let REPORT = null;

    // label, data key, columns: [header, field, isMoney]
    const TABS = [
        ['Contributions', 'contributions', [
            ['Period', 'period', false], ['Contribution', 'contribution', true]]],
        ['Deductions', 'deductions', [
            ['Period', 'period', false], ['Contribution', 'contribution', true],
            ['Union Loan', 'union_loan', true], ['Bank Loan', 'bank_loan', true],
            ['Total Deducted', 'gross_deduction', true]]],
        ['Loans', 'loans', [
            ['Period', 'period', false], ['Loan Amount', 'loanAmount', true],
            ['Interest', 'interest', true], ['Total Repayable', 'total_repayable', true]]],
        ['Repayments', 'repayments', [
            ['Period', 'period', false], ['From Salary', 'loanRepayment', true],
            ['Bank Deposit', 'repayment_bank', true], ['Total', 'total_repaid', true]]],
        ['Bank Loan', 'bank_loans', [
            ['Period', 'period', false], ['Bank Loan', 'bank_loan', true],
            ['Total Deducted', 'gross_deduction', true]]],
        ['Refunds', 'refunds', [
            ['Period', 'period', false], ['Amount', 'amount', true]]],
        ['Withdrawals', 'withdrawals', [
            ['Period', 'period', false], ['Amount', 'withdrawal', true]]],
    ];

    $(function () {
        api('full_report')
            .done(res => { REPORT = res.data; render(); })
            .fail(xhr => {
                if (xhr.status === 401) { window.location = 'index.php'; return; }
                $('#loader').html('<p class="text-red-500 text-sm">Could not load your statement. Please refresh.</p>');
            });
    });

    /**
     * Period label. PayrollPeriod normally already carries the year
     * ("June - 2018"), so only append PhysicalYear when it is genuinely absent.
     */
    function periodOf(row) {
        const name = (row.PayrollPeriod || '').trim();
        const year = (row.PhysicalYear || '').toString().trim();
        if (!name && !year) return 'Period ' + (row.period_id || row.periodid || '—');
        if (year && name.indexOf(year) === -1) return (name + ' ' + year).trim();
        return name || year;
    }

    function periodIdOf(row) {
        return parseInt(row.period_id ?? row.periodid ?? 0, 10) || 0;
    }

    // --- Period filter -----------------------------------------------------
    let activeTab = 0;

    function buildPeriodFilter() {
        const periods = REPORT.periods || [];

        // A dot marks periods this member actually has records in; the rest stay
        // selectable so the range can always reach the current period.
        const option = p => `<option value="${p.periodid}">${$('<div>').text(p.label).html()}${p.has_activity ? ' •' : ''}</option>`;

        // Periods arrive newest first; "From" reads more naturally oldest first.
        $('#fromPeriod').html(periods.slice().reverse().map(option).join(''));
        $('#toPeriod').html(periods.map(option).join(''));

        resetPeriods();
        $('#fromPeriod, #toPeriod').on('change', applyFilter);
    }

    function resetPeriods() {
        const periods = REPORT.periods || [];
        if (!periods.length) { $('#filterNote').text('No payroll periods defined yet.'); return; }
        $('#fromPeriod').val(periods[periods.length - 1].periodid);
        $('#toPeriod').val(periods[0].periodid);
        applyFilter();
    }

    /** Rows within the selected period range. Empty selection means everything. */
    function filtered(key) {
        const rows = REPORT[key] || [];
        const from = parseInt($('#fromPeriod').val(), 10);
        const to   = parseInt($('#toPeriod').val(), 10);
        if (!from || !to) return rows;

        const lo = Math.min(from, to), hi = Math.max(from, to);
        return rows.filter(r => { const p = periodIdOf(r); return p >= lo && p <= hi; });
    }

    function applyFilter() {
        const from = parseInt($('#fromPeriod').val(), 10);
        const to   = parseInt($('#toPeriod').val(), 10);
        const all  = REPORT.periods || [];
        const isAll = all.length && Math.min(from, to) <= all[all.length - 1].periodid
                                 && Math.max(from, to) >= all[0].periodid;

        const shown = TABS.reduce((n, [, key]) => n + filtered(key).length, 0);
        const withActivity = all.filter(p => p.has_activity).length;

        let note = isAll
            ? `Showing all ${all.length} payroll period(s) · ${shown} record(s).`
            : `Filtered to the selected range · ${shown} record(s). Exports use this range.`;

        if (shown === 0 && withActivity > 0) {
            const latest = all.find(p => p.has_activity);
            note += ` You have no records in this range — your most recent activity was ${latest.label}.`;
        }
        $('#filterNote').text(note + (withActivity > 0 ? ' Periods marked • contain your records.' : ''));

        renderTabCounts();
        showTab(activeTab);
    }

    function renderTabCounts() {
        TABS.forEach(([, key], i) => {
            $(`.tab-btn[data-tab="${i}"] .tab-count`).text(filtered(key).length);
        });
    }

    function render() {
        const s = REPORT.summary;
        $('#sSavings').text(money(s.savings_balance));
        $('#sLoanBal').text(money(s.loan_balance));
        $('#sContrib').text(money(s.total_contribution));
        $('#sBank').text(money(s.total_bank_loan));
        $('#genAt').text(REPORT.generated_at);

        $('#tabs').html(TABS.map(([label], i) => `
            <button onclick="showTab(${i})" data-tab="${i}"
                class="tab-btn px-4 sm:px-5 py-3.5 text-sm font-semibold whitespace-nowrap border-b-2 transition-colors">
                ${label}
                <span class="tab-count ml-1.5 text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500">0</span>
            </button>`).join(''));

        buildPeriodFilter();
        $('#loader').hide();
        $('#content').removeClass('hidden');
    }

    function showTab(i) {
        activeTab = i;
        $('.tab-btn')
            .removeClass('border-primary text-primary').addClass('border-transparent text-slate-500')
            .filter('[data-tab="' + i + '"]')
            .removeClass('border-transparent text-slate-500').addClass('border-primary text-primary');

        const [label, key, cols] = TABS[i];
        const rows = filtered(key);

        if (!rows.length) {
            $('#tabBody').html(`<div class="p-12 text-center text-slate-400">
                <span class="material-icons-round text-4xl mb-2 block opacity-40">inbox</span>
                <p class="text-sm">No ${label.toLowerCase()} in the selected periods.</p></div>`);
            return;
        }

        const head = cols.map(([h, , m]) =>
            `<th class="px-4 sm:px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 ${m ? 'text-right' : 'text-left'}">${h}</th>`).join('');

        const body = rows.map(r => '<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">' + cols.map(([, f, m]) => {
            const v = f === 'period' ? periodOf(r) : (m ? money(r[f]) : (r[f] ?? ''));
            return `<td class="px-4 sm:px-6 py-3 text-sm ${m ? 'text-right font-mono' : 'font-medium'}">${$('<div>').text(v).html()}</td>`;
        }).join('') + '</tr>').join('');

        // Column totals for the money columns.
        const foot = '<tr class="bg-slate-50 dark:bg-slate-800/60 font-bold">' + cols.map(([, f, m], idx) => {
            if (!m) return `<td class="px-4 sm:px-6 py-3 text-sm">${idx === 0 ? 'Total' : ''}</td>`;
            const sum = rows.reduce((a, r) => a + parseFloat(r[f] || 0), 0);
            return `<td class="px-4 sm:px-6 py-3 text-sm text-right font-mono">${money(sum)}</td>`;
        }).join('') + '</tr>';

        $('#tabBody').html(`<table class="w-full border-collapse">
            <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800"><tr>${head}</tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">${body}</tbody>
            <tfoot class="border-t-2 border-slate-200 dark:border-slate-700">${foot}</tfoot>
        </table>`);
    }

    function memberLabel() {
        const p = REPORT.profile || {};
        return {
            name: [p.Lname, p.Fname, p.Mname].filter(Boolean).join(' '),
            id: p.patientid || '',
        };
    }

    /** The selected range, for labelling exports. */
    function rangeLabel() {
        const all = REPORT.periods || [];
        if (!all.length) return '';
        const from = parseInt($('#fromPeriod').val(), 10);
        const to   = parseInt($('#toPeriod').val(), 10);
        const lo = Math.min(from, to), hi = Math.max(from, to);
        if (lo <= all[all.length - 1].periodid && hi >= all[0].periodid) return 'All periods';

        const label = id => (all.find(p => p.periodid === id) || {}).label || id;
        return `${label(lo)} to ${label(hi)}`;
    }

    function exportExcel() {
        const wb = XLSX.utils.book_new();
        const who = memberLabel();
        const s = REPORT.summary;

        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet([
            { Item: 'Member', Value: who.name },
            { Item: 'Member ID', Value: who.id },
            { Item: 'Generated', Value: REPORT.generated_at },
            { Item: 'Periods', Value: rangeLabel() },
            { Item: 'Savings Balance', Value: +s.savings_balance },
            { Item: 'Total Contributed', Value: +s.total_contribution },
            { Item: 'Total Withdrawn', Value: +s.total_withdrawal },
            { Item: 'Loan Issued (incl. interest)', Value: +s.loan_issued },
            { Item: 'Loan Repaid', Value: +s.loan_repaid },
            { Item: 'Loan Balance', Value: +s.loan_balance },
            { Item: 'Bank Loan Deducted', Value: +s.total_bank_loan },
        ]), 'Summary');

        TABS.forEach(([label, key, cols]) => {
            const rows = filtered(key).map(r => {
                const o = {};
                cols.forEach(([h, f, m]) => { o[h] = f === 'period' ? periodOf(r) : (m ? +parseFloat(r[f] || 0) : (r[f] ?? '')); });
                return o;
            });
            if (rows.length) XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(rows), label.substring(0, 31));
        });

        XLSX.writeFile(wb, `MHWUN_Statement_${who.id}_${new Date().toISOString().slice(0, 10)}.xlsx`);
    }

    /**
     * jsPDF's built-in fonts are WinAnsi and have no glyph for the naira sign,
     * which rendered as a stray bar. Spell the currency out in PDFs instead;
     * the screen and Excel keep the symbol.
     */
    function pdfMoney(v) {
        return 'NGN ' + parseFloat(v || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function exportPdf() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const who = memberLabel();
        const s = REPORT.summary;
        const pageHeight = doc.internal.pageSize.height;

        doc.setFontSize(15).setFont(undefined, 'bold');
        doc.text('MHWUN OOUTH Branch', 14, 16);
        doc.setFontSize(10).setFont(undefined, 'normal');
        doc.text('Member Statement', 14, 22);
        doc.setFontSize(9);
        doc.text(`${who.name}   |   ID: ${who.id}`, 14, 30);
        doc.text(`Generated: ${REPORT.generated_at}`, 14, 35);
        doc.text(`Periods: ${rangeLabel()}`, 14, 40);

        doc.autoTable({
            startY: 46,
            head: [['Summary', 'Amount']],
            body: [
                ['Savings Balance', pdfMoney(s.savings_balance)],
                ['Total Contributed', pdfMoney(s.total_contribution)],
                ['Total Withdrawn', pdfMoney(s.total_withdrawal)],
                ['Loan Issued (incl. interest)', pdfMoney(s.loan_issued)],
                ['Loan Repaid', pdfMoney(s.loan_repaid)],
                ['Loan Balance', pdfMoney(s.loan_balance)],
                ['Bank Loan Deducted', pdfMoney(s.total_bank_loan)],
            ],
            theme: 'grid',
            headStyles: { fillColor: [2, 132, 199] },
            styles: { fontSize: 9 },
            margin: { top: 16, bottom: 16 },
        });

        // Draw each heading once, immediately above its own table. Doing this in
        // autoTable's didDrawPage instead stamped every section's label onto
        // every page at the same coordinates, so they piled up on each other.
        TABS.forEach(([label, key, cols]) => {
            const rows = filtered(key);
            if (!rows.length) return;

            let y = doc.lastAutoTable.finalY + 12;
            if (y > pageHeight - 45) {          // no room for a heading plus rows
                doc.addPage();
                y = 20;
            }

            doc.setFontSize(11).setFont(undefined, 'bold');
            doc.text(label, 14, y);

            const totals = cols.map(([, f, m], idx) =>
                m ? pdfMoney(rows.reduce((a, r) => a + parseFloat(r[f] || 0), 0)) : (idx === 0 ? 'Total' : ''));

            doc.autoTable({
                startY: y + 4,
                head: [cols.map(([h]) => h)],
                body: rows.map(r => cols.map(([, f, m]) => f === 'period' ? periodOf(r) : (m ? pdfMoney(r[f]) : (r[f] ?? '')))),
                foot: [totals],
                theme: 'striped',
                headStyles: { fillColor: [2, 132, 199] },
                footStyles: { fillColor: [241, 245, 249], textColor: [15, 23, 42], fontStyle: 'bold' },
                styles: { fontSize: 8 },
                margin: { top: 16, bottom: 16 },
            });
        });

        const pages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pages; i++) {
            doc.setPage(i).setFontSize(8).setFont(undefined, 'normal');
            doc.text(`Page ${i} of ${pages}`, 14, pageHeight - 8);
            doc.text('This statement is generated from union records.', 60, pageHeight - 8);
        }

        doc.save(`MHWUN_Statement_${who.id}_${new Date().toISOString().slice(0, 10)}.pdf`);
    }

    function toggleTheme() {
        const html = document.documentElement;
        html.classList.toggle('dark');
        localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
    }
</script>

<?php portalFoot(); ?>
