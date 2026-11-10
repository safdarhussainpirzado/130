<?php

namespace App\Services\Support;

use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\SupportRequestAction;
use App\Models\User;
use App\Models\UserAssignmentHistory;
use App\Models\UserScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationalTransferService
{
    public function __construct(
        private StepUpAuthenticationService $stepUpAuthService
    ) {}

    /**
     * Create and submit a new Organizational Transfer Request.
     * Supports:
     * - Direct self-submission by personnel.
     * - Supervisory initiation by an authorized Incharge/Admin on behalf of a subordinate.
     *
     * @param User $applicant
     * @param int $destinationOfficeId
     * @param string $reason
     * @param string $password
     * @param string|null $totpCode
     * @param User|null $initiator
     * @return SupportRequest
     * @throws ValidationException
     */
    public function submitTransferRequest(
        User $applicant,
        int $destinationOfficeId,
        string $reason,
        string $password,
        ?string $totpCode = null,
        ?User $initiator = null
    ): SupportRequest {
        $actor = $initiator ?? $applicant;

        // 1. Step-Up Authentication for the submitting actor
        $authResult = $this->stepUpAuthService->verify(
            $actor,
            $password,
            $totpCode,
            'transfer_submission'
        );

        if (!$authResult['success']) {
            throw ValidationException::withMessages([
                'password' => [$authResult['error']],
            ]);
        }

        // 2. Prevent Duplicate Pending Transfer Requests for this applicant
        $hasPending = SupportRequest::where('requester_id', $applicant->id)
            ->where('request_type', SupportRequest::TYPE_ORG_TRANSFER)
            ->pending()
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'destination_office_id' => ['This personnel already has an active organizational transfer request in progress.'],
            ]);
        }

        // 3. Validate Destination Office
        $destOffice = Office::where('id', $destinationOfficeId)
            ->where('is_active', true)
            ->first();

        if (!$destOffice) {
            throw ValidationException::withMessages([
                'destination_office_id' => ['The requested destination office is inactive or does not exist.'],
            ]);
        }

        // 4. Resolve Applicant's Current Primary Office
        $currentScope = $applicant->activeScopes()
            ->whereNotNull('office_id')
            ->latest('id')
            ->first();

        $sourceOfficeId = $currentScope?->office_id;

        if ($sourceOfficeId && $sourceOfficeId === $destinationOfficeId) {
            throw ValidationException::withMessages([
                'destination_office_id' => ['Destination unit cannot be identical to current assignment.'],
            ]);
        }

        $sourceOffice = $sourceOfficeId ? Office::with('parent.parent')->find($sourceOfficeId) : null;
        $destOffice->load('parent.parent');

        // 5. If initiated by supervisor on behalf of applicant, verify supervisor has authority over source
        if ($initiator && $initiator->id !== $applicant->id) {
            if ($sourceOfficeId && !$this->doesUserHaveAuthorityOverOffice($initiator, (int) $sourceOfficeId)) {
                throw ValidationException::withMessages([
                    'initiator' => ['You are not authorized to initiate transfers for personnel outside your operational jurisdiction.'],
                ]);
            }
        }

        // 6. Build Multi-Tier Approval Pipeline based on Tree Hierarchy
        $pipeline = ($sourceOffice && $destOffice)
            ? $this->buildTransferApprovalPipeline($sourceOffice, $destOffice)
            : [];

        $initialStatus = !empty($pipeline)
            ? $pipeline[0]['status']
            : SupportRequest::STATUS_PENDING_SOURCE_APPROVAL;

        return DB::transaction(function () use (
            $applicant,
            $actor,
            $sourceOffice,
            $sourceOfficeId,
            $destOffice,
            $reason,
            $pipeline,
            $initialStatus,
            $authResult
        ) {
            $request = SupportRequest::create([
                'requester_id'          => $applicant->id,
                'request_type'          => SupportRequest::TYPE_ORG_TRANSFER,
                'category'              => $destOffice->type . '_transfer',
                'subject'               => "Transfer Request to {$destOffice->name} (" . ucfirst($destOffice->type) . ")",
                'description'           => $reason,
                'current_status'        => $initialStatus,
                'priority'              => SupportRequest::PRIORITY_NORMAL,
                'source_office_id'      => $sourceOfficeId,
                'destination_office_id' => $destOffice->id,
                'metadata'              => [
                    'source_office_type'      => $sourceOffice?->type,
                    'destination_office_type' => $destOffice->type,
                    'applicant_role'          => $applicant->role?->name,
                    'pipeline'                => $pipeline,
                    'current_step_index'      => 0,
                    'initiated_by_id'         => $actor->id,
                    'is_supervisory'          => ($actor->id !== $applicant->id),
                ],
                'submitted_at'          => now(),
            ]);

            $firstStepTitle = !empty($pipeline) ? $pipeline[0]['title'] : 'Initial Review';
            $remarks = ($actor->id === $applicant->id)
                ? "Transfer request submitted by applicant. Reason: {$reason}"
                : "Transfer initiated by supervisor [{$actor->username}]. Reason: {$reason}";

            SupportRequestAction::create([
                'support_request_id'    => $request->id,
                'actor_id'              => $actor->id,
                'action_type'           => SupportRequestAction::ACTION_SUBMITTED,
                'previous_status'       => null,
                'new_status'            => $initialStatus,
                'authentication_method' => $authResult['auth_method'],
                'remarks'               => "{$remarks} — Queued for {$firstStepTitle}",
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            return $request;
        });
    }

    /**
     * Process an active approval step in the hierarchical transfer pipeline.
     * Enforces:
     * - Exact step authorization & jurisdiction check.
     * - Anti-self-approval rule.
     * - Step-up authentication.
     * - Immediate rejection termination if rejected.
     * - Advancement to next tier or atomic assignment completion if approved.
     *
     * @param SupportRequest $supportRequest
     * @param User $actor
     * @param bool $approved
     * @param string $remarks
     * @param string $password
     * @param string|null $totpCode
     * @return SupportRequest
     * @throws ValidationException
     */
    public function processPipelineStep(
        SupportRequest $supportRequest,
        User $actor,
        bool $approved,
        string $remarks,
        string $password,
        ?string $totpCode = null
    ): SupportRequest {
        // Enforce anti-self-approval rule
        if ($supportRequest->requester_id === $actor->id) {
            throw ValidationException::withMessages([
                'actor' => ['Applicants cannot verify or approve their own transfer requests.'],
            ]);
        }

        // Status Guard
        if (!$supportRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['This transfer request is no longer awaiting verification.'],
            ]);
        }

        // Authority Guard
        if (!$this->canUserActOnCurrentStep($actor, $supportRequest)) {
            throw ValidationException::withMessages([
                'actor' => ['You are not authorized to perform actions at the current stage of this transfer.'],
            ]);
        }

        // Step-Up Authentication
        $authResult = $this->stepUpAuthService->verify(
            $actor,
            $password,
            $totpCode,
            'transfer_pipeline_verification'
        );

        if (!$authResult['success']) {
            throw ValidationException::withMessages([
                'password' => [$authResult['error']],
            ]);
        }

        return DB::transaction(function () use ($supportRequest, $actor, $approved, $remarks, $authResult) {
            /** @var SupportRequest $lockedRequest */
            $lockedRequest = SupportRequest::where('id', $supportRequest->id)->lockForUpdate()->firstOrFail();

            $pipeline = $lockedRequest->metadata['pipeline'] ?? [];
            $stepIndex = (int) ($lockedRequest->metadata['current_step_index'] ?? 0);

            // Lazy reconstruct pipeline for legacy records if missing
            if (empty($pipeline) || !isset($pipeline[$stepIndex])) {
                $src = Office::with('parent.parent')->find($lockedRequest->source_office_id);
                $dest = Office::with('parent.parent')->find($lockedRequest->destination_office_id);
                if ($src && $dest) {
                    $pipeline = $this->buildTransferApprovalPipeline($src, $dest);
                    $stepIndex = 0;
                } else {
                    throw new \RuntimeException('Unable to resolve transfer pipeline.');
                }
            }

            $currentStep = $pipeline[$stepIndex];
            $prevStatus = $lockedRequest->current_status;

            if (!$approved) {
                // Rejection: terminates immediately
                $lockedRequest->current_status = SupportRequest::STATUS_REJECTED;
                $lockedRequest->completed_at = now();
                $meta = $lockedRequest->metadata ?? [];
                $meta['rejected_at_step'] = $currentStep['step_key'];
                $meta['rejection_reason'] = $remarks;
                $lockedRequest->metadata = $meta;
                $lockedRequest->save();

                SupportRequestAction::create([
                    'support_request_id'    => $lockedRequest->id,
                    'actor_id'              => $actor->id,
                    'action_type'           => SupportRequestAction::ACTION_TRANSFER_REJECTED,
                    'previous_status'       => $prevStatus,
                    'new_status'            => SupportRequest::STATUS_REJECTED,
                    'authentication_method' => $authResult['auth_method'],
                    'remarks'               => "Rejected at [{$currentStep['title']}]. Remarks: {$remarks}",
                    'ip_address'            => request()->ip(),
                    'user_agent'            => request()->userAgent(),
                ]);

                return $lockedRequest;
            }

            // If Approved:
            $isLastStep = ($stepIndex === count($pipeline) - 1);

            if ($isLastStep) {
                // Final Step: Atomic Assignment Update & Role Sync
                $applicant = User::where('id', $lockedRequest->requester_id)->lockForUpdate()->firstOrFail();
                $destOffice = Office::where('id', $lockedRequest->destination_office_id)->firstOrFail();

                $oldOfficeId = $lockedRequest->source_office_id;
                $newOfficeId = $destOffice->id;

                // Update or Create active UserScope
                $existingScope = UserScope::where('user_id', $applicant->id)
                    ->where('is_active', true)
                    ->first();

                if ($existingScope) {
                    $existingScope->update([
                        'office_id'    => $newOfficeId,
                        'label'        => ucfirst($destOffice->type) . ': ' . $destOffice->name,
                        'access_level' => 'read_write',
                    ]);
                } else {
                    UserScope::create([
                        'user_id'      => $applicant->id,
                        'office_id'    => $newOfficeId,
                        'access_level' => 'read_write',
                        'label'        => ucfirst($destOffice->type) . ': ' . $destOffice->name,
                        'is_active'    => true,
                    ]);
                }

                // Synchronize Applicant Role to match destination operational unit type
                $targetRoleName = match ($destOffice->type) {
                    'beat'        => 'beat_operator',
                    'sector'      => 'sector_admin',
                    'zone'        => 'zone_admin',
                    'call_center' => 'agent',
                    default       => null,
                };

                if ($targetRoleName) {
                    $targetRole = Role::where('name', $targetRoleName)->first();
                    if ($targetRole && (int) $applicant->role_id !== (int) $targetRole->id) {
                        $oldRoleId = $applicant->role_id;
                        $applicant->role_id = $targetRole->id;
                        $applicant->save();

                        AuditLog::create([
                            'user_id'    => $actor->id,
                            'action'     => 'USER_ROLE_SYNCHRONIZED_ON_TRANSFER',
                            'table_name' => 'users',
                            'record_id'  => $applicant->id,
                            'old_values' => ['role_id' => $oldRoleId],
                            'new_values' => ['role_id' => $targetRole->id, 'role_name' => $targetRoleName],
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                        ]);
                    }
                }

                // Record User Assignment History
                UserAssignmentHistory::create([
                    'user_id'             => $applicant->id,
                    'previous_office_id'  => $oldOfficeId,
                    'new_office_id'       => $newOfficeId,
                    'transfer_request_id' => $lockedRequest->id,
                    'changed_by'          => $actor->id,
                    'reason'              => "Completed transfer [REQ-{$lockedRequest->public_id}] to {$destOffice->name}",
                    'effective_at'        => now(),
                ]);

                // Audit Log
                AuditLog::create([
                    'user_id'    => $actor->id,
                    'action'     => 'USER_ORGANIZATIONAL_TRANSFER_COMPLETED',
                    'table_name' => 'user_scopes',
                    'record_id'  => $applicant->id,
                    'old_values' => ['office_id' => $oldOfficeId],
                    'new_values' => [
                        'office_id'          => $newOfficeId,
                        'office_name'        => $destOffice->name,
                        'role_updated'       => $targetRoleName,
                        'transfer_public_id' => $lockedRequest->public_id,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $lockedRequest->current_status = SupportRequest::STATUS_COMPLETED;
                $lockedRequest->completed_at = now();
                $meta = $lockedRequest->metadata ?? [];
                $meta['current_step_index'] = $stepIndex;
                $meta['completed_pipeline'] = true;
                $lockedRequest->metadata = $meta;
                $lockedRequest->save();

                SupportRequestAction::create([
                    'support_request_id'    => $lockedRequest->id,
                    'actor_id'              => $actor->id,
                    'action_type'           => SupportRequestAction::ACTION_DESTINATION_CONFIRMED,
                    'previous_status'       => $prevStatus,
                    'new_status'            => SupportRequest::STATUS_COMPLETED,
                    'authentication_method' => $authResult['auth_method'],
                    'remarks'               => "Final Arrival Confirmed: {$remarks}",
                    'ip_address'            => request()->ip(),
                    'user_agent'            => request()->userAgent(),
                ]);
            } else {
                // Intermediate Step Approved: Advance to next step in pipeline
                $nextStepIndex = $stepIndex + 1;
                $nextStep = $pipeline[$nextStepIndex];

                $meta = $lockedRequest->metadata ?? [];
                $meta['current_step_index'] = $nextStepIndex;
                $lockedRequest->metadata = $meta;
                $lockedRequest->current_status = $nextStep['status'];
                $lockedRequest->save();

                $actionType = match ($currentStep['action_category']) {
                    'departure'   => SupportRequestAction::ACTION_SOURCE_APPROVED,
                    'endorsement' => SupportRequestAction::ACTION_SOURCE_ZONE_APPROVED,
                    'clearance'   => SupportRequestAction::ACTION_DEST_SECTOR_APPROVED,
                    default       => SupportRequestAction::ACTION_SOURCE_APPROVED,
                };

                SupportRequestAction::create([
                    'support_request_id'    => $lockedRequest->id,
                    'actor_id'              => $actor->id,
                    'action_type'           => $actionType,
                    'previous_status'       => $prevStatus,
                    'new_status'            => $nextStep['status'],
                    'authentication_method' => $authResult['auth_method'],
                    'remarks'               => "[{$currentStep['title']}] Approved: {$remarks}",
                    'ip_address'            => request()->ip(),
                    'user_agent'            => request()->userAgent(),
                ]);
            }

            return $lockedRequest;
        });
    }

    /**
     * Backward-compatible alias for Source Verification.
     */
    public function processSourceVerification(
        SupportRequest $supportRequest,
        User $approver,
        bool $approved,
        string $remarks,
        string $password,
        ?string $totpCode = null
    ): SupportRequest {
        return $this->processPipelineStep($supportRequest, $approver, $approved, $remarks, $password, $totpCode);
    }

    /**
     * Backward-compatible alias for Destination Arrival Confirmation.
     */
    public function processDestinationArrival(
        SupportRequest $supportRequest,
        User $confirmer,
        bool $confirmed,
        string $remarks,
        string $password,
        ?string $totpCode = null
    ): SupportRequest {
        return $this->processPipelineStep($supportRequest, $confirmer, $confirmed, $remarks, $password, $totpCode);
    }

    /**
     * Cancel an eligible pending transfer request by applicant or authorized administrator.
     */
    public function cancelTransferRequest(SupportRequest $supportRequest, User $user, string $reason): SupportRequest
    {
        $canCancel = ($supportRequest->requester_id === $user->id)
            || in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true);

        if (!$canCancel) {
            throw ValidationException::withMessages([
                'unauthorized' => ['You are not authorized to cancel this transfer request.'],
            ]);
        }

        if (!$supportRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending requests can be cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($supportRequest, $user, $reason) {
            /** @var SupportRequest $lockedRequest */
            $lockedRequest = SupportRequest::where('id', $supportRequest->id)->lockForUpdate()->firstOrFail();

            $prevStatus = $lockedRequest->current_status;
            $lockedRequest->current_status = SupportRequest::STATUS_CANCELLED;
            $lockedRequest->completed_at = now();
            $lockedRequest->save();

            SupportRequestAction::create([
                'support_request_id'    => $lockedRequest->id,
                'actor_id'              => $user->id,
                'action_type'           => SupportRequestAction::ACTION_CANCELLED,
                'previous_status'       => $prevStatus,
                'new_status'            => SupportRequest::STATUS_CANCELLED,
                'authentication_method' => 'session',
                'remarks'               => "Cancelled by {$user->username}. Reason: {$reason}",
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
            ]);

            return $lockedRequest;
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Approval Pipeline Tree Computation Engine                         */
    /* ------------------------------------------------------------------ */

    /**
     * Build the multi-tier approval pipeline according to NHMP organizational tree:
     * - Intra-Sector: Source Sector Departure -> Destination Beat Arrival.
     * - Intra-Zone: Source Sector Departure -> Zone Endorsement -> Destination Sector Clearance -> Destination Beat Arrival.
     * - Inter-Zone: Source Sector Departure -> Source Zone Clearance -> National HQ Endorsement -> Destination Zone Clearance -> Destination Sector Clearance -> Destination Beat Arrival.
     * - Higher Staff: Dynamically traverses from staff level up to Lowest Common Ancestor.
     *
     * @param Office $sourceOffice
     * @param Office $destOffice
     * @return array<int, array<string, mixed>>
     */
    public function buildTransferApprovalPipeline(Office $sourceOffice, Office $destOffice): array
    {
        // Resolve source hierarchy
        $srcBeat = $sourceOffice->type === 'beat' ? $sourceOffice : null;
        $srcSector = $sourceOffice->type === 'beat' ? $sourceOffice->parent : ($sourceOffice->type === 'sector' ? $sourceOffice : null);
        $srcZone = $srcSector ? $srcSector->parent : ($sourceOffice->type === 'zone' ? $sourceOffice : null);

        // Resolve destination hierarchy
        $destBeat = $destOffice->type === 'beat' ? $destOffice : null;
        $destSector = $destOffice->type === 'beat' ? $destOffice->parent : ($destOffice->type === 'sector' ? $destOffice : null);
        $destZone = $destSector ? $destSector->parent : ($destOffice->type === 'zone' ? $destOffice : null);

        $steps = [];

        // Case 1: Source is Beat (Operator at a Beat level)
        if ($srcBeat && $srcSector) {
            $isSameSector = $destSector && (int) $srcSector->id === (int) $destSector->id;
            $isSameZone   = $srcZone && $destZone && (int) $srcZone->id === (int) $destZone->id;

            // Step 0: Source Sector Departure is ALWAYS required for Beat operators
            $steps[] = [
                'step_key'        => 'source_sector_departure',
                'status'          => SupportRequest::STATUS_PENDING_SOURCE_APPROVAL,
                'office_id'       => $srcSector->id,
                'office_name'     => $srcSector->name,
                'office_type'     => 'sector',
                'required_role'   => 'sector_admin',
                'title'           => 'Source Sector Departure Approval',
                'label'           => "{$srcSector->name} Sector Incharge",
                'action_category' => 'departure',
            ];

            // If Inter-Zone (different zones)
            if (!$isSameZone) {
                $steps[] = [
                    'step_key'        => 'source_zone_clearance',
                    'status'          => SupportRequest::STATUS_PENDING_SOURCE_ZONE_APPROVAL,
                    'office_id'       => $srcZone?->id,
                    'office_name'     => $srcZone?->name ?? 'Source Zone',
                    'office_type'     => 'zone',
                    'required_role'   => 'zone_admin',
                    'title'           => 'Source Zone Clearance',
                    'label'           => ($srcZone?->name ?? 'Source Zone') . ' Incharge',
                    'action_category' => 'endorsement',
                ];
                $steps[] = [
                    'step_key'        => 'national_operations_review',
                    'status'          => SupportRequest::STATUS_PENDING_NATIONAL_REVIEW,
                    'office_id'       => null,
                    'office_name'     => 'National HQ',
                    'office_type'     => 'national',
                    'required_role'   => 'operation_admin',
                    'title'           => 'National Operations Review',
                    'label'           => 'HQ Operations Admin',
                    'action_category' => 'endorsement',
                ];
                $steps[] = [
                    'step_key'        => 'dest_zone_clearance',
                    'status'          => SupportRequest::STATUS_PENDING_DESTINATION_ZONE_APPROVAL,
                    'office_id'       => $destZone?->id,
                    'office_name'     => $destZone?->name ?? 'Destination Zone',
                    'office_type'     => 'zone',
                    'required_role'   => 'zone_admin',
                    'title'           => 'Destination Zone Clearance',
                    'label'           => ($destZone?->name ?? 'Destination Zone') . ' Incharge',
                    'action_category' => 'clearance',
                ];
            } elseif (!$isSameSector) {
                // Same Zone, different Sectors: Zone Endorsement
                $steps[] = [
                    'step_key'        => 'common_zone_endorsement',
                    'status'          => SupportRequest::STATUS_PENDING_SOURCE_ZONE_APPROVAL,
                    'office_id'       => $srcZone->id,
                    'office_name'     => $srcZone->name,
                    'office_type'     => 'zone',
                    'required_role'   => 'zone_admin',
                    'title'           => 'Zone Transfer Endorsement',
                    'label'           => "{$srcZone->name} Zone Incharge",
                    'action_category' => 'endorsement',
                ];
            }

            // Destination Sector Clearance (if destination is in a different sector)
            if (!$isSameSector && $destSector) {
                $steps[] = [
                    'step_key'        => 'dest_sector_clearance',
                    'status'          => SupportRequest::STATUS_PENDING_DESTINATION_SECTOR_APPROVAL,
                    'office_id'       => $destSector->id,
                    'office_name'     => $destSector->name,
                    'office_type'     => 'sector',
                    'required_role'   => 'sector_admin',
                    'title'           => 'Destination Sector Clearance',
                    'label'           => "{$destSector->name} Sector Incharge",
                    'action_category' => 'clearance',
                ];
            }

            // Final Destination Arrival Confirmation
            if ($destBeat) {
                $steps[] = [
                    'step_key'        => 'dest_beat_arrival',
                    'status'          => SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION,
                    'office_id'       => $destBeat->id,
                    'office_name'     => $destBeat->name,
                    'office_type'     => 'beat',
                    'required_role'   => 'beat_operator',
                    'title'           => 'Destination Beat Arrival Confirmation',
                    'label'           => "{$destBeat->name} Wireless Operator",
                    'action_category' => 'arrival',
                ];
            } else {
                $steps[] = [
                    'step_key'        => 'dest_office_arrival',
                    'status'          => SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION,
                    'office_id'       => $destOffice->id,
                    'office_name'     => $destOffice->name,
                    'office_type'     => $destOffice->type,
                    'required_role'   => $destOffice->type === 'sector' ? 'sector_admin' : 'zone_admin',
                    'title'           => 'Destination Arrival Confirmation',
                    'label'           => "{$destOffice->name} Incharge",
                    'action_category' => 'arrival',
                ];
            }

            return $steps;
        }

        // Sector-to-Sector Staff Transfers
        if ($srcSector && $destSector) {
            if ($srcZone && $destZone && (int) $srcZone->id === (int) $destZone->id) {
                // Intra-Zone Sector staff
                $steps[] = [
                    'step_key'        => 'common_zone_endorsement',
                    'status'          => SupportRequest::STATUS_PENDING_SOURCE_ZONE_APPROVAL,
                    'office_id'       => $srcZone->id,
                    'office_name'     => $srcZone->name,
                    'office_type'     => 'zone',
                    'required_role'   => 'zone_admin',
                    'title'           => 'Zone Transfer Endorsement',
                    'label'           => "{$srcZone->name} Zone Incharge",
                    'action_category' => 'endorsement',
                ];
                $steps[] = [
                    'step_key'        => 'dest_sector_arrival',
                    'status'          => SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION,
                    'office_id'       => $destSector->id,
                    'office_name'     => $destSector->name,
                    'office_type'     => 'sector',
                    'required_role'   => 'sector_admin',
                    'title'           => 'Destination Sector Arrival Confirmation',
                    'label'           => "{$destSector->name} Sector Incharge",
                    'action_category' => 'arrival',
                ];
                return $steps;
            } else {
                // Inter-Zone Sector staff
                $steps[] = [
                    'step_key'        => 'source_zone_clearance',
                    'status'          => SupportRequest::STATUS_PENDING_SOURCE_ZONE_APPROVAL,
                    'office_id'       => $srcZone?->id,
                    'office_name'     => $srcZone?->name ?? 'Source Zone',
                    'office_type'     => 'zone',
                    'required_role'   => 'zone_admin',
                    'title'           => 'Source Zone Clearance',
                    'label'           => ($srcZone?->name ?? 'Source Zone') . ' Incharge',
                    'action_category' => 'departure',
                ];
                $steps[] = [
                    'step_key'        => 'national_operations_review',
                    'status'          => SupportRequest::STATUS_PENDING_NATIONAL_REVIEW,
                    'office_id'       => null,
                    'office_name'     => 'National HQ',
                    'office_type'     => 'national',
                    'required_role'   => 'operation_admin',
                    'title'           => 'National Operations Review',
                    'label'           => 'HQ Operations Admin',
                    'action_category' => 'endorsement',
                ];
                $steps[] = [
                    'step_key'        => 'dest_zone_clearance',
                    'status'          => SupportRequest::STATUS_PENDING_DESTINATION_ZONE_APPROVAL,
                    'office_id'       => $destZone?->id,
                    'office_name'     => $destZone?->name ?? 'Destination Zone',
                    'office_type'     => 'zone',
                    'required_role'   => 'zone_admin',
                    'title'           => 'Destination Zone Clearance',
                    'label'           => ($destZone?->name ?? 'Destination Zone') . ' Incharge',
                    'action_category' => 'clearance',
                ];
                $steps[] = [
                    'step_key'        => 'dest_sector_arrival',
                    'status'          => SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION,
                    'office_id'       => $destSector->id,
                    'office_name'     => $destSector->name,
                    'office_type'     => 'sector',
                    'required_role'   => 'sector_admin',
                    'title'           => 'Destination Sector Arrival Confirmation',
                    'label'           => "{$destSector->name} Sector Incharge",
                    'action_category' => 'arrival',
                ];
                return $steps;
            }
        }

        // Fallback default: Source Departure -> Destination Arrival
        $steps[] = [
            'step_key'        => 'source_departure',
            'status'          => SupportRequest::STATUS_PENDING_SOURCE_APPROVAL,
            'office_id'       => $sourceOffice->id,
            'office_name'     => $sourceOffice->name,
            'office_type'     => $sourceOffice->type,
            'required_role'   => $sourceOffice->type === 'beat' ? 'sector_admin' : 'zone_admin',
            'title'           => 'Source Departure Clearance',
            'label'           => "{$sourceOffice->name} Incharge",
            'action_category' => 'departure',
        ];
        $steps[] = [
            'step_key'        => 'dest_arrival',
            'status'          => SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION,
            'office_id'       => $destOffice->id,
            'office_name'     => $destOffice->name,
            'office_type'     => $destOffice->type,
            'required_role'   => $destOffice->type === 'beat' ? 'beat_operator' : 'sector_admin',
            'title'           => 'Destination Arrival Confirmation',
            'label'           => "{$destOffice->name} Incharge",
            'action_category' => 'arrival',
        ];
        return $steps;
    }

    /* ------------------------------------------------------------------ */
    /*  Workflow Permission & Stage Evaluators                            */
    /* ------------------------------------------------------------------ */

    /**
     * Determine whether the given user has authority to act on the CURRENT step of the support request.
     * Strictly enforces the NHMP organizational tier: Only the designated related office and role
     * can verify and approve each pipeline step.
     */
    public function canUserActOnCurrentStep(User $user, SupportRequest $supportRequest): bool
    {
        // 1. Anti-self-approval rule: Applicant can never approve own request
        if ((int) $supportRequest->requester_id === (int) $user->id) {
            return false;
        }

        // 2. Request must be pending
        if (!$supportRequest->isPending()) {
            return false;
        }

        // 3. Super Admin always has system recovery / emergency override rights
        if ($user->role?->name === Role::SUPER_ADMIN) {
            return true;
        }

        // 4. Non-transfer requests (account block, password recovery, MFA)
        if (!$supportRequest->isTransfer()) {
            if ($user->role?->name === 'operation_admin') {
                return true;
            }
            $reqUserScope = $supportRequest->requester?->activeScopes()->first();
            if ($reqUserScope && $reqUserScope->office_id) {
                return $this->doesUserHaveAuthorityOverOffice($user, (int) $reqUserScope->office_id);
            }
            return false;
        }

        // 5. Transfer requests: evaluate current active pipeline step
        $currentStep = $this->getCurrentStepDetails($supportRequest);
        if (!$currentStep) {
            return false;
        }

        $stepOfficeType = $currentStep['office_type'] ?? null;
        $stepOfficeId   = isset($currentStep['office_id']) ? (int) $currentStep['office_id'] : null;
        $userRole       = $user->role?->name;

        // National HQ Step: ONLY operation_admin (and super_admin)
        if ($stepOfficeType === 'national' || $stepOfficeId === null) {
            return in_array($userRole, [Role::SUPER_ADMIN, 'operation_admin'], true);
        }

        // Strict Hierarchy Rule 1: operation_admin CANNOT approve field steps (Zone, Sector, Beat)
        if ($userRole === 'operation_admin') {
            return false;
        }

        // Strict Hierarchy Rule 2: zone_admin CANNOT approve Sector or Beat steps
        if ($userRole === 'zone_admin' && in_array($stepOfficeType, ['sector', 'beat'], true)) {
            return false;
        }

        // Strict Hierarchy Rule 3: sector_admin CANNOT approve Zone or Beat steps
        if ($userRole === 'sector_admin' && in_array($stepOfficeType, ['zone', 'beat'], true)) {
            return false;
        }

        // Strict Hierarchy Rule 4: beat_operator CANNOT approve Zone or Sector steps
        if ($userRole === 'beat_operator' && in_array($stepOfficeType, ['zone', 'sector'], true)) {
            return false;
        }

        // Strict Hierarchy Rule 5: Designated Office matching
        if ($stepOfficeType === 'zone') {
            return $userRole === 'zone_admin' && $this->isUserDirectlyAssignedToOffice($user, $stepOfficeId);
        }

        if ($stepOfficeType === 'sector') {
            return $userRole === 'sector_admin' && $this->isUserDirectlyAssignedToOffice($user, $stepOfficeId);
        }

        if ($stepOfficeType === 'beat') {
            return $userRole === 'beat_operator' && $this->isUserDirectlyAssignedToOffice($user, $stepOfficeId);
        }

        return false;
    }

    /**
     * Check whether a user is directly assigned to the exact office.
     */
    public function isUserDirectlyAssignedToOffice(User $user, int $officeId): bool
    {
        $scopes = $user->relationLoaded('activeScopes') ? $user->activeScopes : $user->activeScopes()->get();
        return $scopes->contains(function ($scope) use ($officeId) {
            return (int) $scope->office_id === $officeId;
        });
    }

    /**
     * Check whether a user's active scopes cover the target office (direct match or ancestor).
     */
    public function doesUserHaveAuthorityOverOffice(User $user, int $targetOfficeId): bool
    {
        if (in_array($user->role?->name, [Role::SUPER_ADMIN, 'operation_admin'], true)) {
            return true;
        }

        foreach ($user->activeScopes as $scope) {
            if (!$scope->office_id) {
                return true; // Unrestricted scope
            }
            if ((int) $scope->office_id === $targetOfficeId) {
                return true;
            }
            // Check descendants (e.g. Zone covers Sector and Beat, Sector covers Beat)
            $descendants = Office::getDescendantIdsFor((int) $scope->office_id, true);
            if (in_array($targetOfficeId, $descendants, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get details of the currently active step in the transfer pipeline.
     *
     * @param SupportRequest $supportRequest
     * @return array<string, mixed>|null
     */
    public function getCurrentStepDetails(SupportRequest $supportRequest): ?array
    {
        if (!$supportRequest->isTransfer() || !$supportRequest->isPending()) {
            return null;
        }

        $pipeline = $supportRequest->metadata['pipeline'] ?? null;
        if (empty($pipeline) || !is_array($pipeline)) {
            $src = Office::with('parent.parent')->find($supportRequest->source_office_id);
            $dest = Office::with('parent.parent')->find($supportRequest->destination_office_id);
            if ($src && $dest) {
                $pipeline = $this->buildTransferApprovalPipeline($src, $dest);
                $meta = $supportRequest->metadata ?? [];
                $meta['pipeline'] = $pipeline;
                $meta['current_step_index'] = 0;
                $supportRequest->metadata = $meta;
                $supportRequest->save();
            } else {
                return null;
            }
        }

        $stepIndex = (int) ($supportRequest->metadata['current_step_index'] ?? 0);
        if (!isset($pipeline[$stepIndex])) {
            return null;
        }

        $step = $pipeline[$stepIndex];
        $step['is_last_step'] = ($stepIndex === count($pipeline) - 1);
        $step['total_steps']  = count($pipeline);
        $step['step_number']  = $stepIndex + 1;
        return $step;
    }

    /**
     * Get human-readable badge text of who the request is currently awaiting.
     */
    public function getNextActorLabel(SupportRequest $supportRequest): string
    {
        if (!$supportRequest->isTransfer() || !$supportRequest->isPending()) {
            return $supportRequest->getStatusLabel();
        }

        $step = $this->getCurrentStepDetails($supportRequest);
        if ($step && !empty($step['label'])) {
            return 'Awaiting ' . $step['label'];
        }

        return $supportRequest->getStatusLabel();
    }

    public function canUserApproveSource(User $user, SupportRequest $supportRequest): bool
    {
        return $this->canUserActOnCurrentStep($user, $supportRequest);
    }

    public function canUserConfirmDestination(User $user, SupportRequest $supportRequest): bool
    {
        return $this->canUserActOnCurrentStep($user, $supportRequest);
    }
}
