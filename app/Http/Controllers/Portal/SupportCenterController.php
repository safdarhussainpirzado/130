<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Support\AccountSecuritySupportService;
use App\Services\Support\OrganizationalTransferService;
use App\Services\Support\StepUpAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupportCenterController extends Controller
{
    public function __construct(
        private OrganizationalTransferService $transferService,
        private AccountSecuritySupportService $securitySupportService,
        private StepUpAuthenticationService $stepUpAuthService
    ) {}

    /**
     * Display User Self-Service Support Portal.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $statusFilter = $request->input('status');
        $typeFilter = $request->input('type');

        $query = SupportRequest::with(['sourceOffice', 'destinationOffice', 'assignedApprover'])
            ->where('requester_id', $user->id);

        if ($statusFilter) {
            $query->where('current_status', $statusFilter);
        }

        if ($typeFilter) {
            $query->where('request_type', $typeFilter);
        }

        $requests = $query->latest('id')->paginate(12)->withQueryString();

        // KPI Counts for applicant
        $counts = [
            'total'     => SupportRequest::where('requester_id', $user->id)->count(),
            'pending'   => SupportRequest::where('requester_id', $user->id)->pending()->count(),
            'completed' => SupportRequest::where('requester_id', $user->id)->completed()->count(),
            'rejected'  => SupportRequest::where('requester_id', $user->id)->rejected()->count(),
        ];

        return view('portal.support.index', [
            'user'         => $user,
            'requests'     => $requests,
            'counts'       => $counts,
            'statusFilter' => $statusFilter,
            'typeFilter'   => $typeFilter,
        ]);
    }

    /**
     * Show form to submit a new support request.
     */
    public function create(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $defaultType = $request->input('type', SupportRequest::TYPE_ORG_TRANSFER);

        // Fetch active Zones with their Sectors and Beats for 3-tier transfer destination selector
        $zones = Office::where('type', 'zone')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('type', 'sector')
                  ->where('is_active', true)
                  ->orderBy('name')
                  ->with(['children' => function ($b) {
                      $b->where('type', 'beat')
                        ->where('is_active', true)
                        ->orderBy('name');
                  }]);
            }])
            ->orderBy('name')
            ->get();

        // Also fetch active Sectors and Beats directly as fallback
        $sectors = Office::where('type', 'sector')
            ->where('is_active', true)
            ->with(['children' => fn($q) => $q->where('type', 'beat')->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $currentScope = $user->activeScopes()->with('office')->latest('id')->first();
        $user->load('designation');
        $designations = \App\Models\Designation::where('is_active', true)->orderBy('name')->get(['id', 'name', 'bps', 'short_code']);

        return view('portal.support.create', [
            'user'         => $user,
            'defaultType'  => $defaultType,
            'zones'        => $zones,
            'sectors'      => $sectors,
            'currentScope' => $currentScope,
            'designations' => $designations,
        ]);
    }

    /**
     * Store an incoming user support request.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $type = $request->input('request_type');

        if ($type === SupportRequest::TYPE_ORG_TRANSFER) {
            $request->validate([
                'destination_office_id' => ['required', 'integer', 'exists:offices,id'],
                'reason'                => ['required', 'string', 'min:5', 'max:1000'],
                'password'              => ['required', 'string'],
                'totp_code'             => ['nullable', 'string', 'size:6'],
            ]);

            try {
                $supportRequest = $this->transferService->submitTransferRequest(
                    $user,
                    (int) $request->input('destination_office_id'),
                    $request->input('reason'),
                    $request->input('password'),
                    $request->input('totp_code')
                );

                \Illuminate\Support\Facades\Cache::forget('support_pending_badge_count');

                return redirect()->route('portal.support.show', $supportRequest)
                    ->with('success', "Transfer request [ID: {$supportRequest->public_id}] has been submitted successfully and routed for verification.");
            } catch (ValidationException $e) {
                return back()->withInput()->withErrors($e->errors());
            }
        }

        if ($type === SupportRequest::TYPE_MFA_SUPPORT) {
            $request->validate([
                'category'    => ['required', 'string', 'in:lost_device,enrollment_assistance,reset_request,other'],
                'description' => ['required', 'string', 'min:10', 'max:1000'],
                'password'    => ['required', 'string'],
                'totp_code'   => ['nullable', 'string', 'size:6'],
            ]);

            $authResult = $this->stepUpAuthService->verify(
                $user,
                $request->input('password'),
                $request->input('totp_code'),
                'mfa_support_submission'
            );

            if (!$authResult['success']) {
                throw ValidationException::withMessages([
                    'password' => [$authResult['error'] ?? 'Current password or authentication code is incorrect.'],
                ]);
            }

            $supportRequest = $this->securitySupportService->submitMfaSupportRequest(
                $user,
                $request->input('category'),
                $request->input('description')
            );

            \Illuminate\Support\Facades\Cache::forget('support_pending_badge_count');

            return redirect()->route('portal.support.show', $supportRequest)
                ->with('success', "MFA support request [ID: {$supportRequest->public_id}] submitted to Operation Managers.");
        }

        if ($type === SupportRequest::TYPE_OTHER_ACCOUNT) {
            $request->validate([
                'category'           => ['required', 'string', 'in:full_name,email,phone,designation,cnic,username_correction,account_info,login_problems,permissions,activation,other'],
                'subject'            => ['required', 'string', 'min:3', 'max:150'],
                'description'        => ['required', 'string', 'min:5', 'max:1000'],
                'new_full_name'      => ['nullable', 'string', 'max:150'],
                'new_email'          => ['nullable', 'email', 'max:150'],
                'new_phone'          => ['nullable', 'string', 'max:30'],
                'new_designation_id' => ['nullable', 'integer', 'exists:designations,id'],
                'new_cnic'           => ['nullable', 'string', 'max:25'],
                'password'           => ['required', 'string'],
                'totp_code'          => ['nullable', 'string', 'size:6'],
            ]);

            $authResult = $this->stepUpAuthService->verify(
                $user,
                $request->input('password'),
                $request->input('totp_code'),
                'general_support_submission'
            );

            if (!$authResult['success']) {
                throw ValidationException::withMessages([
                    'password' => [$authResult['error'] ?? 'Current password or authentication code is incorrect.'],
                ]);
            }

            $cat = $request->input('category');
            $desigId = $request->input('new_designation_id');
            $newDesigModel = $desigId ? \App\Models\Designation::find($desigId) : null;

            $profileChanges = [
                'target_field'         => $cat,
                'current_full_name'    => $user->full_name,
                'new_full_name'        => $request->input('new_full_name'),
                'current_email'        => $user->email,
                'new_email'            => $request->input('new_email'),
                'current_phone'        => $user->mobile_no,
                'new_phone'            => $request->input('new_phone'),
                'current_designation'  => $user->designation?->name,
                'new_designation_id'   => $desigId,
                'new_designation_name' => $newDesigModel?->name,
                'current_cnic'         => $user->cnic,
                'new_cnic'             => $request->input('new_cnic'),
            ];

            $supportRequest = $this->securitySupportService->submitGeneralAccountIssue(
                $user,
                $cat,
                $request->input('subject'),
                $request->input('description'),
                ['profile_changes' => $profileChanges]
            );

            \Illuminate\Support\Facades\Cache::forget('support_pending_badge_count');

            return redirect()->route('portal.support.show', $supportRequest)
                ->with('success', "Support request [ID: {$supportRequest->public_id}] submitted successfully.");
        }

        if ($type === SupportRequest::TYPE_ACCOUNT_BLOCK || $type === 'account_block') {
            $request->validate([
                'reason'    => ['required', 'string', 'min:5', 'max:500'],
                'password'  => ['required', 'string'],
                'totp_code' => ['nullable', 'string', 'size:6'],
            ]);

            try {
                $this->securitySupportService->lockAccount(
                    $user,
                    $request->input('reason'),
                    $request->input('password'),
                    $request->input('totp_code')
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('status', 'Your account has been immediately locked and all sessions terminated. Please contact an authorized Operation Manager to verify identity and unlock.');
            } catch (ValidationException $e) {
                return back()->withInput()->withErrors($e->errors());
            }
        }

        return back()->withErrors(['request_type' => 'Unsupported support request type selected.']);
    }

    /**
     * Display a specific support request with full audit timeline.
     */
    public function show(SupportRequest $supportRequest): View
    {
        $this->authorize('view', $supportRequest);

        $supportRequest->load([
            'requester.role',
            'sourceOffice.parent',
            'destinationOffice.parent',
            'actions.actor.role',
            'assignedApprover',
            'recoveryTokens',
        ]);

        return view('portal.support.show', [
            'request' => $supportRequest,
        ]);
    }

    /**
     * Cancel an eligible pending request.
     */
    public function cancel(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('cancel', $supportRequest);

        $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $this->transferService->cancelTransferRequest(
                $supportRequest,
                $user,
                $request->input('cancellation_reason')
            );

            return redirect()->route('portal.support.show', $supportRequest)
                ->with('info', "Support request [ID: {$supportRequest->public_id}] has been cancelled.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Self-service emergency account lock ("Lock My Account").
     */
    public function lockAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'reason'    => ['required', 'string', 'min:5', 'max:500'],
            'password'  => ['required', 'string'],
            'totp_code' => ['nullable', 'string', 'size:6'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $this->securitySupportService->lockAccount(
                $user,
                $request->input('reason'),
                $request->input('password'),
                $request->input('totp_code')
            );

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Your account has been immediately locked and all sessions terminated. Please contact an authorized Operation Manager to verify identity and unlock.');
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
    }
}
