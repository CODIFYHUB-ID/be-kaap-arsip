<?php

namespace App\Services\Dashboard;

use App\Models\ActivityLog;
use App\Models\AuditContract;
use App\Models\BankConfirmation;
use App\Models\Category;
use App\Models\DebtorCreditorConfirmation;
use App\Models\Document;
use App\Models\GeneratedLetter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Mitra;
use App\Models\OutgoingLetter;

class DashboardService
{
    /**
     * Retrieve summary statistics and recent activities for the dashboard with optional date filtering.
     */
    public function getSummary(?string $startDate = null, ?string $endDate = null, ?\App\Models\User $currentUser = null): array
    {
        $currentUser = $currentUser ?? auth()->user();

        $docQuery = Document::query();
        $mitraQuery = Mitra::where('status', 'active');
        $activityQuery = ActivityLog::with('user:id,name');

        if ($startDate && $endDate) {
            $start = $startDate . ' 00:00:00';
            $end = $endDate . ' 23:59:59';

            $docQuery->whereBetween('created_at', [$start, $end]);
            $mitraQuery->whereBetween('created_at', [$start, $end]);
            $activityQuery->whereBetween('created_at', [$start, $end]);
        }

        $totalDocuments = $docQuery->count();
        $totalMitras = $mitraQuery->count();
        $totalStorageBytes = $docQuery->sum('file_size');

        $recentActivities = $activityQuery
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentDocQuery = Document::with(['mitra:id,name', 'category:id,name', 'uploader:id,name']);
        if ($startDate && $endDate) {
            $recentDocQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }
        $recentDocuments = $recentDocQuery
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $categoryDistribution = Category::withCount(['documents' => function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        }])
            ->get()
            ->map(function ($cat) use ($totalDocuments) {
                $count = $cat->documents_count;
                $percentage = $totalDocuments > 0 ? round(($count / $totalDocuments) * 100, 1) : 0;
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'count' => $count,
                    'percentage' => $percentage,
                ];
            });

        // 1. Modul Pembuatan Berkas Resmi (Korespondensi & Penugasan)
        $suratTugasQuery = GeneratedLetter::query();
        $auditContractQuery = AuditContract::query();
        $outgoingLetterQuery = OutgoingLetter::query();
        $bankConfQuery = BankConfirmation::query();
        $debtorCreditorConfQuery = DebtorCreditorConfirmation::query();

        if ($startDate && $endDate) {
            $start = $startDate . ' 00:00:00';
            $end = $endDate . ' 23:59:59';
            $suratTugasQuery->whereBetween('created_at', [$start, $end]);
            $auditContractQuery->whereBetween('created_at', [$start, $end]);
            $outgoingLetterQuery->whereBetween('created_at', [$start, $end]);
            $bankConfQuery->whereBetween('created_at', [$start, $end]);
            $debtorCreditorConfQuery->whereBetween('created_at', [$start, $end]);
        }

        $totalSuratTugas = $suratTugasQuery->count();
        $totalKontrak = $auditContractQuery->count();
        $totalSuratKeluar = $outgoingLetterQuery->count();
        $totalBankConf = $bankConfQuery->count();
        $totalDebtorCreditorConf = $debtorCreditorConfQuery->count();
        $totalKonfirmasi = $totalBankConf + $totalDebtorCreditorConf;
        $totalGeneratedDocs = $totalSuratTugas + $totalKontrak + $totalSuratKeluar + $totalKonfirmasi;

        $draftCount = (clone $suratTugasQuery)->where('status', 'draft')->count()
            + (clone $auditContractQuery)->where('status', 'draft')->count()
            + (clone $outgoingLetterQuery)->where('status', 'draft')->count();

        $finalCount = (clone $suratTugasQuery)->whereIn('status', ['final', 'signed', 'completed'])->count()
            + (clone $auditContractQuery)->whereIn('status', ['final', 'signed'])->count()
            + (clone $outgoingLetterQuery)->whereIn('status', ['final', 'sent'])->count()
            + $totalKonfirmasi;

        $correspondenceOverview = [
            'total_surat_tugas' => $totalSuratTugas,
            'total_kontrak' => $totalKontrak,
            'total_surat_keluar' => $totalSuratKeluar,
            'total_bank_confirmation' => $totalBankConf,
            'total_debtor_creditor_confirmation' => $totalDebtorCreditorConf,
            'total_konfirmasi' => $totalKonfirmasi,
            'total_generated' => $totalGeneratedDocs,
            'total_incoming' => $totalKonfirmasi,
            'total_outgoing' => $totalSuratKeluar + $totalSuratTugas + $totalKontrak,
            'draft_count' => $draftCount,
            'final_count' => $finalCount,
        ];

        // 2. Modul Keuangan & Kwitansi Penugasan
        $contractFeeQuery = AuditContract::query();
        $invoiceQuery = Invoice::query();
        if ($startDate && $endDate) {
            $contractFeeQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $invoiceQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }

        $totalContractFee = (float) $contractFeeQuery->sum('fee_amount');
        $totalContractsCount = $contractFeeQuery->count();
        $totalInvoicesRevenue = (float) $invoiceQuery->sum('total_amount');
        $totalInvoicesCount = $invoiceQuery->count();
        $totalPaidInvoices = (float) (clone $invoiceQuery)->where('status', 'paid')->sum('total_amount');
        $totalUnpaidInvoices = (float) (clone $invoiceQuery)->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'paid');
        })->sum('total_amount');

        // Rincian kategori item kwitansi (DP, Termin, Pelunasan, Operasional)
        $dpSum = (float) InvoiceItem::whereHas('invoice', function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        })->where(function ($q) {
            $q->where('description', 'like', '%dp%')
                ->orWhere('description', 'like', '%uang muka%')
                ->orWhere('description', 'like', '%tahap 1%');
        })->sum('amount');

        $terminSum = (float) InvoiceItem::whereHas('invoice', function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        })->where(function ($q) {
            $q->where('description', 'like', '%termin%')
                ->orWhere('description', 'like', '%tahap 2%');
        })->sum('amount');

        $pelunasanSum = (float) InvoiceItem::whereHas('invoice', function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        })->where(function ($q) {
            $q->where('description', 'like', '%lunas%')
                ->orWhere('description', 'like', '%pelunasan%')
                ->orWhere('description', 'like', '%final%');
        })->sum('amount');

        $operasionalSum = (float) InvoiceItem::whereHas('invoice', function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        })->where(function ($q) {
            $q->where('description', 'like', '%operasional%')
                ->orWhere('description', 'like', '%biaya lain%')
                ->orWhere('description', 'like', '%akomodasi%');
        })->sum('amount');

        $financialOverview = [
            'total_contract_fee' => $totalContractFee,
            'total_contracts_count' => $totalContractsCount,
            'total_revenue' => $totalInvoicesRevenue,
            'total_invoices_count' => $totalInvoicesCount,
            'total_paid' => $totalPaidInvoices,
            'total_unpaid' => $totalUnpaidInvoices,
            'total_dp' => $dpSum,
            'total_termin' => $terminSum,
            'total_pelunasan' => $pelunasanSum,
            'total_operasional' => $operasionalSum,
        ];

        // 3. Tren Bulanan 6 Bulan Terakhir
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthLabel = $monthDate->translatedFormat('M');

            $mDocCount = Document::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)->count();

            $mIncomingCount = BankConfirmation::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)->count()
                + DebtorCreditorConfirmation::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)->count();

            $mOutgoingCount = OutgoingLetter::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)->count()
                + GeneratedLetter::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)->count()
                + AuditContract::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)->count();

            $monthlyTrends[] = [
                'month' => $monthLabel,
                'year' => $monthDate->year,
                'month_num' => $monthDate->month,
                'upload_count' => $mDocCount,
                'incoming_count' => $mIncomingCount,
                'outgoing_count' => $mOutgoingCount,
            ];
        }

        // 4. Pending Final Archive Approvals
        $pendingApprovalsCount = Document::where('approval_status', 'pending')->count();
        $pendingApprovalsList = Document::with(['mitra:id,name,company_name', 'category:id,name', 'uploader:id,name'])
            ->where('approval_status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'stats' => [
                'total_documents' => $totalDocuments,
                'total_mitras' => $totalMitras,
                'total_contracts' => $totalKontrak,
                'total_contracts_fee' => $totalContractFee,
                'total_invoices' => $totalInvoicesCount,
                'total_invoices_amount' => $totalInvoicesRevenue,
                'total_surat_tugas' => $totalSuratTugas,
                'total_outgoing_letters' => $totalSuratKeluar,
                'total_bank_confirmations' => $totalBankConf,
                'total_debtor_creditor_confirmations' => $totalDebtorCreditorConf,
                'total_storage_bytes' => (int) $totalStorageBytes,
                'pending_approvals_count' => $pendingApprovalsCount,
            ],
            'category_distribution' => $categoryDistribution,
            'recent_activities' => $recentActivities,
            'recent_documents' => $recentDocuments,
            'monthly_trends' => $monthlyTrends,
            'financial_overview' => $financialOverview,
            'correspondence_overview' => $correspondenceOverview,
            'pending_approvals' => $pendingApprovalsList,
            'auditor_overview' => null,
        ];
    }
}
