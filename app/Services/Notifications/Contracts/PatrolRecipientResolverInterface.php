<?php

namespace App\Services\Notifications\Contracts;

use App\Models\Call;

/**
 * Extension point for future field patrol push notifications.
 *
 * NOTE: Per Claude Handoff Section 1 & Section 6, this interface is defined
 * as an architectural contract ONLY and is strictly NOT wired into the
 * shipping desk/CRM push service. The desk shipping branch contains zero
 * patrol officer queries or commuter mobile endpoints.
 */
interface PatrolRecipientResolverInterface
{
    /**
     * Resolve eligible active field patrol devices for an incident.
     *
     * @return array<int, string>
     */
    public function resolvePatrolTokensForCall(Call $call): array;
}
