<?php

namespace App\Http\Controllers\Api\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AuditorAssignment;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Mitra;
use App\Models\User;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditorAssignmentController extends Controller
{
    use ApiResponse;

    /**
     * List auditor assignments with role-based scoping.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $query = AuditorAssignment::with([
            'auditor:id,name,email',
            'mitra:id,name,company_name,code,address,phone,email',
            'assigner:id,name',
        ]);

        // If logged in user is Auditor, strictly scope to their own assignments
        if ($user->isAuditor() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            $query->where('auditor_id', $user->id);
        } else {
            // Optional filters for Admin / Owner
            if ($request->filled('auditor_id')) {
                $query->where('auditor_id', $request->get('auditor_id'));
            }
            if ($request->filled('mitra_id')) {
                $query->where('mitra_id', $request->get('mitra_id'));
            }
            if ($request->filled('tahun_buku')) {
                $query->where('tahun_buku', $request->get('tahun_buku'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->get('status'));
            }
        }

        $assignments = $query->orderByDesc('tahun_buku')->orderByDesc('created_at')->get();

        return $this->success($assignments, 'Daftar penugasan tim audit berhasil diambil.');
    }

    /**
     * Get summary of assigned clients with audit evidence, working papers (KKP), and confirmation counts.
     */
    public function myClients(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $auditorId = $user->id;
        if ($request->filled('auditor_id') && $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            $auditorId = (int) $request->get('auditor_id');
        }

        $assignments = AuditorAssignment::with(['mitra'])
            ->where('auditor_id', $auditorId)
            ->where('status', 'active')
            ->get();

        $clientData = $assignments->map(function ($assignment) {
            $mitra = $assignment->mitra;
            if (! $mitra) return null;

            // Count audit evidence documents for this client
            $buktiAuditCount = Document::where('mitra_id', $mitra->id)
                ->where(function ($q) {
                    $q->where('is_working_paper', false)
                      ->orWhereNull('is_working_paper');
                })
                ->count();

            // Count working papers (KKP) for this client
            $kkpCount = Document::where('mitra_id', $mitra->id)
                ->where('is_working_paper', true)
                ->count();

            // Count confirmation letters for this client
            $confirmationLettersCount = Letter::where('mitra_id', $mitra->id)
                ->where(function ($q) {
                    $q->whereNotNull('confirmation_type')
                      ->orWhereNotNull('confirmation_status');
                })
                ->count();

            return [
                'assignment_id' => $assignment->id,
                'mitra_id' => $mitra->id,
                'mitra_code' => $mitra->code,
                'client_name' => $mitra->company_name ?: $mitra->name,
                'pic_name' => $mitra->name,
                'email' => $mitra->email,
                'phone' => $mitra->phone,
                'address' => $mitra->address,
                'tahun_buku' => $assignment->tahun_buku,
                'role_in_team' => $assignment->role_in_team,
                'status' => $assignment->status,
                'notes' => $assignment->notes,
                'bukti_audit_count' => $buktiAuditCount,
                'kkp_count' => $kkpCount,
                'confirmation_letters_count' => $confirmationLettersCount,
            ];
        })->filter()->values();

        return $this->success($clientData, 'Data klien penugasan audit berhasil diambil.');
    }

    /**
     * Assign an auditor to a client engagement for an audit year (Owner / Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->error('Akses ditolak. Hanya Pimpinan / Admin yang berwenang menugaskan tim auditor.', 403);
        }

        $validated = $request->validate([
            'auditor_id' => 'required|exists:users,id',
            'mitra_id' => 'required|exists:mitras,id',
            'tahun_buku' => 'required|string|max:10',
            'role_in_team' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,completed,inactive',
            'notes' => 'nullable|string',
        ]);

        $assignment = AuditorAssignment::updateOrCreate(
            [
                'auditor_id' => $validated['auditor_id'],
                'mitra_id' => $validated['mitra_id'],
                'tahun_buku' => $validated['tahun_buku'],
            ],
            [
                'role_in_team' => $validated['role_in_team'] ?? 'Junior Auditor',
                'status' => $validated['status'] ?? 'active',
                'assigned_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return $this->success($assignment->load(['auditor', 'mitra', 'assigner']), 'Penugasan audit berhasil disimpan.', 201);
    }

    /**
     * Update an auditor assignment (Owner / Admin only).
     */
    public function update(Request $request, AuditorAssignment $auditorAssignment): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->error('Akses ditolak. Hanya Pimpinan / Admin yang berwenang mengubah penugasan tim auditor.', 403);
        }

        $validated = $request->validate([
            'role_in_team' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,completed,inactive',
            'notes' => 'nullable|string',
        ]);

        $auditorAssignment->update($validated);

        return $this->success($auditorAssignment->load(['auditor', 'mitra', 'assigner']), 'Penugasan audit berhasil diperbarui.');
    }

    /**
     * Delete an auditor assignment (Owner / Admin only).
     */
    public function destroy(Request $request, AuditorAssignment $auditorAssignment): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->error('Akses ditolak. Hanya Pimpinan / Admin yang berwenang membatalkan penugasan tim auditor.', 403);
        }

        $auditorAssignment->delete();

        return $this->success(null, 'Penugasan tim audit berhasil dihapus.');
    }
}
