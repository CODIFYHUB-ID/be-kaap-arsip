<?php

namespace App\Services\Dashboard;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Mitra;
use App\Models\Receipt;

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
        $incomingQuery = Letter::where('type', 'incoming');
        $outgoingQuery = Letter::where('type', 'outgoing');
        $receiptQuery = Receipt::query();
        $activityQuery = ActivityLog::with('user:id,name');

        $isMitraUser = $currentUser && $currentUser->isMitra() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff']);
        $isAuditorUser = $currentUser && $currentUser->isAuditor() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff']);
        $assignedMitraIds = [];

        if ($isMitraUser) {
            $docQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('uploaded_by', $currentUser->id)
                  ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                      $mq->where('created_by', $currentUser->id);
                  });
            });
            // Total sub-client binaan under this partner
            $mitraQuery->where('created_by', $currentUser->id);

            $incomingQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id)
                  ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                      $mq->where('created_by', $currentUser->id);
                  });
            });
            $outgoingQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id)
                  ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                      $mq->where('created_by', $currentUser->id);
                  });
            });
            $receiptQuery->whereRaw('1 = 0');
            $activityQuery->where('user_id', $currentUser->id);
        } elseif ($isAuditorUser) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $currentUser->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();

            $docQuery->where(function ($q) use ($assignedMitraIds, $currentUser) {
                $q->whereIn('mitra_id', $assignedMitraIds)
                  ->orWhere('uploaded_by', $currentUser->id);
            });
            $mitraQuery->whereIn('id', $assignedMitraIds);

            $incomingQuery->where(function ($q) use ($assignedMitraIds, $currentUser) {
                $q->whereIn('mitra_id', $assignedMitraIds)
                  ->orWhere('created_by', $currentUser->id);
            });
            $outgoingQuery->where(function ($q) use ($assignedMitraIds, $currentUser) {
                $q->whereIn('mitra_id', $assignedMitraIds)
                  ->orWhere('created_by', $currentUser->id);
            });
            $receiptQuery->whereRaw('1 = 0'); // Clean financial boundary
            $activityQuery->where('user_id', $currentUser->id);
        }

        if ($startDate && $endDate) {
            $start = $startDate . ' 00:00:00';
            $end = $endDate . ' 23:59:59';

            $docQuery->whereBetween('created_at', [$start, $end]);
            $mitraQuery->whereBetween('created_at', [$start, $end]);
            $incomingQuery->whereBetween('created_at', [$start, $end]);
            $outgoingQuery->whereBetween('created_at', [$start, $end]);
            $receiptQuery->whereBetween('created_at', [$start, $end]);
            $activityQuery->whereBetween('created_at', [$start, $end]);
        }

        $totalDocuments = $docQuery->count();
        $totalMitras = $mitraQuery->count();
        $totalIncomingLetters = $incomingQuery->count();
        $totalOutgoingLetters = $outgoingQuery->count();
        $totalReceipts = $receiptQuery->count();
        $totalStorageBytes = $docQuery->sum('file_size');

        $recentActivities = $activityQuery
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentDocQuery = Document::with(['mitra:id,name', 'category:id,name', 'uploader:id,name']);
        if ($isMitraUser) {
            $recentDocQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('uploaded_by', $currentUser->id)
                  ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                      $mq->where('created_by', $currentUser->id);
                  });
            });
        } elseif ($isAuditorUser) {
            $recentDocQuery->where(function ($q) use ($assignedMitraIds, $currentUser) {
                $q->whereIn('mitra_id', $assignedMitraIds)
                  ->orWhere('uploaded_by', $currentUser->id);
            });
        }
        if ($startDate && $endDate) {
            $recentDocQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }
        $recentDocuments = $recentDocQuery
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $categoryDistribution = Category::withCount(['documents' => function ($q) use ($startDate, $endDate, $currentUser, $isMitraUser, $isAuditorUser, $assignedMitraIds) {
            if ($isMitraUser) {
                $q->where(function ($sub) use ($currentUser) {
                    if ($currentUser->mitra_id) {
                        $sub->where('mitra_id', $currentUser->mitra_id);
                    }
                    $sub->orWhere('uploaded_by', $currentUser->id)
                        ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                            $mq->where('created_by', $currentUser->id);
                        });
                });
            } elseif ($isAuditorUser) {
                $q->where(function ($sub) use ($assignedMitraIds, $currentUser) {
                    $sub->whereIn('mitra_id', $assignedMitraIds)
                        ->orWhere('uploaded_by', $currentUser->id);
                });
            }
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

        // Compute monthly trends for the last 6 months (Documents, Incoming & Outgoing Letters)
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthLabel = $monthDate->translatedFormat('M');

            $mDocQuery = Document::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month);

            $mInQuery = Letter::where('type', 'incoming')
                ->whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month);

            $mOutQuery = Letter::where('type', 'outgoing')
                ->whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month);

            if ($isMitraUser) {
                $mDocQuery->where(function ($q) use ($currentUser) {
                    if ($currentUser->mitra_id) {
                        $q->where('mitra_id', $currentUser->mitra_id);
                    }
                    $q->orWhere('uploaded_by', $currentUser->id)
                      ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                          $mq->where('created_by', $currentUser->id);
                      });
                });
                $mInQuery->where(function ($q) use ($currentUser) {
                    if ($currentUser->mitra_id) {
                        $q->where('mitra_id', $currentUser->mitra_id);
                    }
                    $q->orWhere('created_by', $currentUser->id);
                });
                $mOutQuery->where(function ($q) use ($currentUser) {
                    if ($currentUser->mitra_id) {
                        $q->where('mitra_id', $currentUser->mitra_id);
                    }
                    $q->orWhere('created_by', $currentUser->id);
                });
            }

            $monthlyTrends[] = [
                'month' => $monthLabel,
                'year' => $monthDate->year,
                'month_num' => $monthDate->month,
                'upload_count' => $mDocQuery->count(),
                'incoming_count' => $mInQuery->count(),
                'outgoing_count' => $mOutQuery->count(),
            ];
        }

        // Financial Overview (DP, Termin, Pelunasan, Operasional) - strictly zeroed out for Auditor & Mitra (Clean Financial Boundary)
        if ($isAuditorUser || $isMitraUser) {
            $financialOverview = [
                'total_dp' => 0.0,
                'total_termin' => 0.0,
                'total_pelunasan' => 0.0,
                'total_operasional' => 0.0,
                'total_revenue' => 0.0,
            ];
        } else {
            $financialOverview = [
                'total_dp' => (float) Receipt::where('receipt_type', 'dp')->sum('amount'),
                'total_termin' => (float) Receipt::where('receipt_type', 'termin')->sum('amount'),
                'total_pelunasan' => (float) Receipt::where('receipt_type', 'pelunasan')->sum('amount'),
                'total_operasional' => (float) Receipt::where('receipt_type', 'operasional')->sum('amount'),
                'total_revenue' => (float) Receipt::sum('amount'),
            ];
        }

        // Correspondence Overview
        $correspondenceOverview = [
            'total_incoming' => $totalIncomingLetters,
            'total_outgoing' => $totalOutgoingLetters,
            'draft_count' => Letter::where('status', 'draft')->count(),
            'final_count' => Letter::where('status', 'final')->count(),
        ];

        // Pending Final Archive Approvals
        $pendingApprovalsCount = Document::where('approval_status', 'pending')->count();
        $pendingApprovalsList = Document::with(['mitra:id,name,company_name', 'category:id,name', 'uploader:id,name'])
            ->where('approval_status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Auditor Specific Overview
        $auditorOverview = $isAuditorUser ? [
            'assigned_clients_count' => count($assignedMitraIds),
            'working_papers_count' => Document::whereIn('mitra_id', $assignedMitraIds)->where('is_working_paper', true)->count(),
            'audit_evidence_count' => Document::whereIn('mitra_id', $assignedMitraIds)->where(function ($q) {
                $q->where('is_working_paper', false)->orWhereNull('is_working_paper');
            })->count(),
            'confirmations_count' => Letter::whereIn('mitra_id', $assignedMitraIds)->where(function ($q) {
                $q->whereNotNull('confirmation_type')->orWhereNotNull('confirmation_status');
            })->count(),
            'pending_confirmations' => Letter::whereIn('mitra_id', $assignedMitraIds)->where('confirmation_status', 'menunggu_jawaban')->count(),
            'exception_confirmations' => Letter::whereIn('mitra_id', $assignedMitraIds)->where('confirmation_status', 'selisih')->count(),
        ] : null;

        return [
            'stats' => [
                'total_documents' => $totalDocuments,
                'total_mitras' => $totalMitras,
                'total_incoming_letters' => $totalIncomingLetters,
                'total_outgoing_letters' => $totalOutgoingLetters,
                'total_receipts' => $totalReceipts,
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
            'auditor_overview' => $auditorOverview,
        ];
    }
}

