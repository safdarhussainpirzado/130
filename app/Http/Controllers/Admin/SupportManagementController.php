<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Support\AccountSecuritySupportService;
use App\Services\Support\OrganizationalTransferService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupportManagementController extends Controller
{
    public function __construct(
        private OrganizationalTransferService $transferService,
        private AccountSecuritySupportService $securitySupportService
    ) {}

    /**
     * Operations Support Dashboard redirects to Unified Support Center.
     */
    public function dashboard(Request $request): RedirectResponse
    {
        return redirect()->route('admin.support.index');
    }

    /**
     * Unified Support Center: Master Registry, 3D Status KPIs, & Single-Page Action Modals.
     * Enforces strictly scoped visibility: Non-national users only see requests and KPIs
     * within their assigned operational unit (Sector, Zone, or Beat), while National users see all.
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', SupportRequest::class);

        /** @var User $currentUser */
        $currentUser = Auth::user();
        [$isNational, $scopedOfficeIds, $applyScope] = $this->resolveJurisdictionScope($currentUser);

        $kpis = $this->calculateKpis($applyScope);
        $perPage = (int) $request->input('perPage', 15);
        if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $query = $this->buildFilteredQuery($request, $applyScope);
        $requests = $query->latest('id')->paginate($perPage)->withQueryString();

        [$zones, $sectors, $beats] = $this->getScopedOffices($isNational, $scopedOfficeIds);
        $isManager = $isNational;

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'    => true,
                'data'       => $this->formatRequestsForJson($requests, $currentUser, $isManager),
                'kpis'       => $kpis,
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'last_page'    => $requests->lastPage(),
                    'per_page'     => $requests->perPage(),
                    'total'        => $requests->total(),
                    'from'         => $requests->firstItem() ?? 0,
                    'to'           => $requests->lastItem() ?? 0,
                ],
            ]);
        }

        return view('admin.support.index', [
            'requests'               => $requests,
            'kpis'                   => $kpis,
            'currentUser'            => $currentUser,
            'isManager'              => $isManager,
            'filterKey'              => $request->input('filter'),
            'typeFilter'             => $request->input('type'),
            'statusFilter'           => $request->input('status'),
            'priorityFilter'         => $request->input('priority'),
            'search'                 => trim((string) $request->input('search')),
            'perPage'                => $perPage,
            'zoneId'                 => $request->input('zone_id'),
            'sectorId'               => $request->input('sector_id'),
            'beatId'                 => $request->input('beat_id'),
            'zones'                  => $zones,
            'sectors'                => $sectors,
            'beats'                  => $beats,
            'transferService'        => $this->transferService,
        ]);
    }

    /**
     * Resolve jurisdiction scope, allowed office IDs, and query closure.
     *
     * @return array{0: bool, 1: Collection<int, int>, 2: \Closure}
     */
    private function resolveJurisdictionScope(?User $currentUser): array
    {
        $isNational = $currentUser ? in_array($currentUser->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true) : true;

        $scopedOfficeIds = collect();
        if ($currentUser && !$isNational) {
            foreach ($currentUser->activeScopes as $scope) {
                if ($scope->office_id) {
                    $scopedOfficeIds = $scopedOfficeIds->merge(Office::getDescendantIdsFor((int) $scope->office_id, true));
                }
            }
            $scopedOfficeIds = $scopedOfficeIds->unique()->values();
        }

        $applyScope = function (Builder $q) use ($isNational, $scopedOfficeIds, $currentUser) {
            if ($isNational || !$currentUser) {
                return;
            }
            $q->where(function (Builder $sub) use ($scopedOfficeIds, $currentUser) {
                // 1. Transfer requests where source or destination is within user's allowed offices
                $sub->where(function (Builder $tq) use ($scopedOfficeIds) {
                    $tq->where('request_type', SupportRequest::TYPE_ORG_TRANSFER)
                       ->where(function (Builder $oq) use ($scopedOfficeIds) {
                           $oq->whereIn('source_office_id', $scopedOfficeIds)
                              ->orWhereIn('destination_office_id', $scopedOfficeIds);
                       });
                })
                // 2. OR Any support request submitted by the user themselves
                ->orWhere('requester_id', $currentUser->id)
                // 3. OR Account security requests for personnel in user's jurisdiction
                ->orWhere(function (Builder $aq) use ($scopedOfficeIds) {
                    $aq->whereIn('request_type', [
                        SupportRequest::TYPE_PASSWORD_RECOVERY,
                        SupportRequest::TYPE_MFA_SUPPORT,
                        SupportRequest::TYPE_ACCOUNT_BLOCK,
                    ])->whereHas('requester.activeScopes', function (Builder $sq) use ($scopedOfficeIds) {
                        $sq->whereIn('office_id', $scopedOfficeIds);
                    });
                });
            });
        };

        return [$isNational, $scopedOfficeIds, $applyScope];
    }

    /**
     * Compute 10 enterprise KPI counters scoped to user jurisdiction.
     *
     * @param \Closure $applyScope
     * @return array<string, int>
     */
    private function calculateKpis(\Closure $applyScope): array
    {
        $baseKpiQuery = SupportRequest::query()->where($applyScope);

        return [
            'total'                => (clone $baseKpiQuery)->count(),
            'pending'              => (clone $baseKpiQuery)->pending()->count(),
            'awaiting_source'      => (clone $baseKpiQuery)->byStatus(SupportRequest::STATUS_PENDING_SOURCE_APPROVAL)->count(),
            'awaiting_destination' => (clone $baseKpiQuery)->whereIn('current_status', [
                SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION,
                SupportRequest::STATUS_PENDING_DESTINATION_SECTOR_APPROVAL,
                SupportRequest::STATUS_PENDING_DESTINATION_ZONE_APPROVAL,
            ])->count(),
            'completed'            => (clone $baseKpiQuery)->completed()->count(),
            'rejected'             => (clone $baseKpiQuery)->rejected()->count(),
            'account_blocking'     => (clone $baseKpiQuery)->byType(SupportRequest::TYPE_ACCOUNT_BLOCK)->count(),
            'password_recovery'    => (clone $baseKpiQuery)->byType(SupportRequest::TYPE_PASSWORD_RECOVERY)->count(),
            'mfa_requests'         => (clone $baseKpiQuery)->byType(SupportRequest::TYPE_MFA_SUPPORT)->count(),
            'transfer_requests'    => (clone $baseKpiQuery)->byType(SupportRequest::TYPE_ORG_TRANSFER)->count(),
        ];
    }

    /**
     * Build filtered Eloquent query based on request parameters.
     */
    private function buildFilteredQuery(Request $request, \Closure $applyScope): Builder
    {
        $filterKey      = $request->input('filter');
        $typeFilter     = $request->input('type');
        $statusFilter   = $request->input('status');
        $priorityFilter = $request->input('priority');
        $search         = trim((string) $request->input('search'));
        $zoneId         = $request->input('zone_id');
        $sectorId       = $request->input('sector_id');
        $beatId         = $request->input('beat_id');

        $query = SupportRequest::query()
            ->where($applyScope)
            ->with([
                'requester.role', 
                'sourceOffice.parent.parent', 
                'destinationOffice.parent.parent', 
                'assignedApprover',
                'actions.actor.role'
            ]);

        // Status Card 3D Filter Key Mapping
        if ($filterKey && $filterKey !== 'all') {
            if ($filterKey === 'pending') {
                $query->pending();
            } elseif ($filterKey === 'awaiting_source') {
                $query->where('current_status', SupportRequest::STATUS_PENDING_SOURCE_APPROVAL);
            } elseif ($filterKey === 'awaiting_destination') {
                $query->whereIn('current_status', [
                    SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION,
                    SupportRequest::STATUS_PENDING_DESTINATION_SECTOR_APPROVAL,
                    SupportRequest::STATUS_PENDING_DESTINATION_ZONE_APPROVAL,
                ]);
            } elseif ($filterKey === 'completed') {
                $query->where('current_status', SupportRequest::STATUS_COMPLETED);
            } elseif ($filterKey === 'rejected') {
                $query->where('current_status', SupportRequest::STATUS_REJECTED);
            } elseif ($filterKey === 'account_block') {
                $query->where('request_type', SupportRequest::TYPE_ACCOUNT_BLOCK);
            } elseif ($filterKey === 'password_recovery') {
                $query->where('request_type', SupportRequest::TYPE_PASSWORD_RECOVERY);
            } elseif ($filterKey === 'mfa_support') {
                $query->where('request_type', SupportRequest::TYPE_MFA_SUPPORT);
            } elseif ($filterKey === 'org_transfer') {
                $query->where('request_type', SupportRequest::TYPE_ORG_TRANSFER);
            }
        }

        // Type filtering
        if ($typeFilter && $typeFilter !== 'all') {
            $query->where('request_type', $typeFilter);
        }

        // Status filtering
        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('current_status', $statusFilter);
        }

        // Priority filtering
        if ($priorityFilter) {
            $query->where('priority', $priorityFilter);
        }

        // Office / Organizational Hierarchy filtering
        if ($beatId) {
            $query->where(function (Builder $q) use ($beatId) {
                $q->where('source_office_id', $beatId)
                  ->orWhere('destination_office_id', $beatId);
            });
        } elseif ($sectorId) {
            $descendantIds = Office::getDescendantIdsFor((int) $sectorId);
            $query->where(function (Builder $q) use ($descendantIds) {
                $q->whereIn('source_office_id', $descendantIds)
                  ->orWhereIn('destination_office_id', $descendantIds);
            });
        } elseif ($zoneId) {
            $descendantIds = Office::getDescendantIdsFor((int) $zoneId);
            $query->where(function (Builder $q) use ($descendantIds) {
                $q->whereIn('source_office_id', $descendantIds)
                  ->orWhereIn('destination_office_id', $descendantIds);
            });
        }

        // Text Search (UUID, applicant username, full name, subject)
        if (!empty($search)) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('public_id', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhereHas('requester', function (Builder $uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    /**
     * Get jurisdictional office hierarchy lists for dropdowns.
     *
     * @param bool $isNational
     * @param Collection<int, int> $scopedOfficeIds
     * @return array{0: Collection, 1: Collection, 2: Collection}
     */
    private function getScopedOffices(bool $isNational, Collection $scopedOfficeIds): array
    {
        if ($isNational) {
            $zones = Office::where('type', 'zone')->where('is_active', true)->orderBy('name')->get();
            $sectors = Office::where('type', 'sector')->where('is_active', true)->orderBy('name')->get();
            $beats = Office::where('type', 'beat')->where('is_active', true)->orderBy('name')->get();
        } else {
            $sectors = Office::where('type', 'sector')->whereIn('id', $scopedOfficeIds)->where('is_active', true)->orderBy('name')->get();
            $beats = Office::where('type', 'beat')->whereIn('id', $scopedOfficeIds)->where('is_active', true)->orderBy('name')->get();
            
            $zoneIds = Office::where('type', 'zone')->whereIn('id', $scopedOfficeIds)->pluck('id');
            if ($zoneIds->isEmpty() && $sectors->isNotEmpty()) {
                $zoneIds = $sectors->pluck('parent_id')->filter()->unique();
            }
            $zones = Office::where('type', 'zone')->whereIn('id', $zoneIds)->where('is_active', true)->orderBy('name')->get();
        }

        return [$zones, $sectors, $beats];
    }

    /**
     * Format paginated requests for AJAX/JSON SPA consumption.
     */
    private function formatRequestsForJson(LengthAwarePaginator $requests, User $currentUser, bool $isManager): Collection
    {
        return $requests->getCollection()->map(function (SupportRequest $req) use ($currentUser, $isManager) {
            $canAct = $this->transferService->canUserActOnCurrentStep($currentUser, $req);
            $stepDetails = $this->transferService->getCurrentStepDetails($req);
            $actionCategory = $stepDetails['action_category'] ?? null;
            $nextActorLabel = $this->transferService->getNextActorLabel($req);

            return [
                'id'                     => $req->id,
                'public_id'              => $req->public_id,
                'subject'                => $req->subject,
                'description'            => $req->description,
                'request_type'           => $req->request_type,
                'type_label'             => $req->getTypeLabel(),
                'type_badge'             => $req->getTypeBadgeClass(),
                'current_status'         => $req->current_status,
                'status_label'           => $req->getStatusLabel(),
                'status_badge'           => $req->getStatusBadgeClass(),
                'priority'               => $req->priority,
                'priority_badge'         => $req->getPriorityBadgeClass(),
                'submitted_at'           => $req->submitted_at?->format('M d, Y H:i:s') ?? 'N/A',
                'applicant_name'         => $req->requester?->full_name ?? 'Unknown Officer',
                'applicant_username'     => $req->requester?->username ?? 'N/A',
                'applicant_email'        => $req->requester?->email ?? 'N/A',
                'applicant_phone'        => $req->requester?->mobile_no ?? $req->requester?->phone ?? 'N/A',
                'applicant_cnic'         => $req->requester?->cnic ?? 'N/A',
                'applicant_role'         => $req->requester?->role?->display_name ?? 'Standard User',
                'source_office'          => $req->sourceOffice?->name ?? 'National / Unassigned',
                'source_parent'          => $req->sourceOffice?->parent?->name ?? '',
                'destination_office'     => $req->destinationOffice?->name ?? 'N/A',
                'destination_parent'     => $req->destinationOffice?->parent?->name ?? '',
                'reason'                 => $req->description ?? '',
                'metadata'               => $req->metadata ?? [],
                'actions'                => $req->actions->map(fn($a) => [
                    'type'    => $a->getActionLabel(),
                    'badge'   => $a->getActionBadgeClass(),
                    'actor'   => ($a->actor?->full_name ?? 'System') . ' (' . ($a->actor?->username ?? 'Automated') . ')',
                    'role'    => $a->actor?->getRoleTitle() ?? 'System',
                    'remarks' => $a->remarks,
                    'date'    => $a->created_at->format('M d, Y H:i:s'),
                    'auth'    => $a->getAuthMethodLabel(),
                    'ip'      => $a->ip_address,
                ])->values()->all(),
                'can_act'                => $canAct,
                'action_category'        => $actionCategory,
                'next_actor_label'       => $nextActorLabel,
                'current_step'           => $stepDetails,
                'can_approve_source'     => $canAct && in_array($actionCategory, ['departure', 'endorsement'], true),
                'can_confirm_dest'       => $canAct && in_array($actionCategory, ['clearance', 'arrival'], true),
                'can_manage'             => $isManager && in_array($req->current_status, [
                    SupportRequest::STATUS_SUBMITTED,
                    SupportRequest::STATUS_PENDING_MANAGER_REVIEW
                ], true),
            ];
        });
    }

    /**
     * Detailed View of Single Support Request.
     */
    public function show(SupportRequest $supportRequest): View
    {
        $this->authorize('view', $supportRequest);

        /** @var User $currentUser */
        $currentUser = Auth::user();

        $supportRequest->load([
            'requester.role',
            'sourceOffice.parent.parent',
            'destinationOffice.parent.parent',
            'actions.actor.role',
            'assignedApprover',
            'recoveryTokens',
            'assignmentHistories.newOffice',
        ]);

        $canAct = $this->transferService->canUserActOnCurrentStep($currentUser, $supportRequest);
        $stepDetails = $this->transferService->getCurrentStepDetails($supportRequest);
        $actionCategory = $stepDetails['action_category'] ?? null;
        $isManager = in_array($currentUser->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true);

        return view('admin.support.show', [
            'request'               => $supportRequest,
            'currentUser'           => $currentUser,
            'canAct'                => $canAct,
            'currentStep'           => $stepDetails,
            'canApproveSource'      => $canAct && in_array($actionCategory, ['departure', 'endorsement'], true),
            'canConfirmDestination' => $canAct && in_array($actionCategory, ['clearance', 'arrival'], true),
            'isManager'             => $isManager,
        ]);
    }

    /**
     * Stage 1: Approve Source Sector/Zone Verification or Endorsement.
     */
    public function approveSource(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('approveSource', $supportRequest);

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $isPrivileged = $currentUser->isPrivileged() || in_array($currentUser->role?->name, ['super_admin', 'operation_admin'], true);

        $request->validate([
            'remarks'   => ['required', 'string', 'min:5', 'max:500'],
            'password'  => $isPrivileged ? ['nullable', 'string'] : ['required', 'string'],
            'totp_code' => ['nullable', 'string', 'size:6'],
        ]);

        try {
            $this->transferService->processPipelineStep(
                $supportRequest,
                $currentUser,
                true,
                $request->input('remarks'),
                (string) $request->input('password'),
                $request->input('totp_code')
            );

            return redirect()->route('admin.support.index')
                ->with('success', "Transfer stage action approved successfully for request [{$supportRequest->public_id}].");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Stage 2: Confirm Destination Arrival & Atomic Assignment Update.
     */
    public function confirmDestination(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('confirmDestination', $supportRequest);

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $isPrivileged = $currentUser->isPrivileged() || in_array($currentUser->role?->name, ['super_admin', 'operation_admin'], true);

        $request->validate([
            'remarks'   => ['required', 'string', 'min:5', 'max:500'],
            'password'  => $isPrivileged ? ['nullable', 'string'] : ['required', 'string'],
            'totp_code' => ['nullable', 'string', 'size:6'],
        ]);

        try {
            $this->transferService->processPipelineStep(
                $supportRequest,
                $currentUser,
                true,
                $request->input('remarks'),
                (string) $request->input('password'),
                $request->input('totp_code')
            );

            return redirect()->route('admin.support.index')
                ->with('success', "Destination arrival confirmed! Organizational assignment has been automatically updated in active registry.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Reject a support request (at any tier in the approval tree).
     */
    public function reject(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('view', $supportRequest);

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $isPrivileged = $currentUser->isPrivileged() || in_array($currentUser->role?->name, ['super_admin', 'operation_admin'], true);

        $request->validate([
            'remarks'   => ['required', 'string', 'min:3', 'max:500'],
            'password'  => $isPrivileged ? ['nullable', 'string'] : ['required', 'string'],
            'totp_code' => ['nullable', 'string', 'size:6'],
        ]);

        $remarks = (string) $request->input('remarks');

        try {
            if ($supportRequest->isTransfer()) {
                $this->transferService->processPipelineStep(
                    $supportRequest,
                    $currentUser,
                    false,
                    $remarks,
                    (string) $request->input('password'),
                    $request->input('totp_code')
                );
            } else {
                $this->securitySupportService->resolveGeneralRequest(
                    $supportRequest,
                    $currentUser,
                    $remarks,
                    false
                );
            }

            return redirect()->route('admin.support.index')
                ->with('warning', "Support request [ID: {$supportRequest->public_id}] has been rejected.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Unlock an emergency-locked user account after identity verification.
     */
    public function unlockAccount(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('manage', SupportRequest::class);

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $isPrivileged = $currentUser->isPrivileged() || in_array($currentUser->role?->name, ['super_admin', 'operation_admin'], true);

        $request->validate([
            'remarks'   => ['required', 'string', 'min:5', 'max:500'],
            'password'  => $isPrivileged ? ['nullable', 'string'] : ['required', 'string'],
            'totp_code' => ['nullable', 'string', 'size:6'],
        ]);

        try {
            $this->securitySupportService->unlockAccount(
                $supportRequest,
                $currentUser,
                $request->input('remarks'),
                (string) $request->input('password'),
                $request->input('totp_code')
            );

            return redirect()->route('admin.support.index')
                ->with('success', "Target user account has been successfully unlocked and restored to active duty.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Issue secure password recovery token to verified applicant.
     */
    public function issuePasswordToken(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('manage', SupportRequest::class);

        /** @var User $currentUser */
        $currentUser = Auth::user();
        $isPrivileged = $currentUser->isPrivileged() || in_array($currentUser->role?->name, ['super_admin', 'operation_admin'], true);

        $request->validate([
            'remarks'   => ['required', 'string', 'min:5', 'max:500'],
            'password'  => $isPrivileged ? ['nullable', 'string'] : ['required', 'string'],
            'totp_code' => ['nullable', 'string', 'size:6'],
        ]);

        try {
            $result = $this->securitySupportService->approvePasswordRecovery(
                $supportRequest,
                $currentUser,
                $request->input('remarks'),
                (string) $request->input('password'),
                $request->input('totp_code')
            );

            return redirect()->route('admin.support.index')
                ->with('status_token', $result['plain_token'])
                ->with('reset_url', $result['reset_url'])
                ->with('success', "Password recovery authorized! Generated secure single-use recovery token.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Resolve general account issues.
     */
    public function resolveGeneral(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('manage', SupportRequest::class);

        $request->validate([
            'remarks'  => ['required', 'string', 'min:5', 'max:500'],
            'approved' => ['required', 'boolean'],
        ]);

        /** @var User $currentUser */
        $currentUser = Auth::user();

        $this->securitySupportService->resolveGeneralRequest(
            $supportRequest,
            $currentUser,
            $request->input('remarks'),
            (bool) $request->input('approved')
        );

        return redirect()->route('admin.support.index')
            ->with('success', "Support request resolution recorded.");
    }

    /**
     * Support Analytics & SLA Performance Report redirects to Unified Support Center.
     */
    public function analytics(Request $request): RedirectResponse
    {
        return redirect()->route('admin.support.index');
    }

    /**
     * Return live pending support requests count and latest request details for real-time sidebar badge and notifications.
     * Accurately scoped to the logged-in user's jurisdiction.
     */
    public function pendingCount(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        [, , $applyScope] = $this->resolveJurisdictionScope($currentUser);

        $count = SupportRequest::pending()->where($applyScope)->count();
        $latest = SupportRequest::pending()->where($applyScope)->latest('id')->first(['id', 'public_id', 'subject', 'request_type']);

        return response()->json([
            'count'     => $count,
            'latest_id' => $latest?->id,
            'public_id' => $latest?->public_id,
            'subject'   => $latest?->subject,
            'type'      => $latest?->request_type,
        ]);
    }
}
