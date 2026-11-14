<?php

namespace App\Services\Geo;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Access policy and risk evaluation engine for NHMP 130 Helpline.
 *
 * Implements strict role-based differentiation, CIDR and named user allowances,
 * shadow vs enforce execution, and tamper-evident forensic auditing.
 */
class GeoPolicyEngine
{
    public function __construct(
        private EmergencyOverrideService $overrideService,
        private DeviceFingerprintService $fingerprintService
    ) {}

    /**
     * Evaluate a login attempt against geo, ASN, role, and anomaly policies.
     *
     * @param User $user
     * @param Request $request
     * @param string|null $explicitIp
     * @return array{
     *     decision: string,
     *     action: ?string,
     *     mode: string,
     *     country: ?string,
     *     asn: ?int,
     *     org: ?string,
     *     is_datacenter: bool,
     *     is_vpn_or_proxy: bool,
     *     is_tor: bool,
     *     risk_score: int,
     *     risk_reasons: array<int, string>,
     *     device_hash: string,
     *     device_cookie: ?\Symfony\Component\HttpFoundation\Cookie,
     *     should_block: bool
     * }
     */
    public function evaluate(User $user, Request $request, ?string $explicitIp = null): array
    {
        $ip = $explicitIp ?: (string) $request->ip();
        $mode = (string) config('geo_access.mode', 'shadow');

        // Resolve device token and deterministic hash
        $deviceInfo = $this->fingerprintService->resolveDeviceToken($request);
        $deviceHash = $this->fingerprintService->computeDeviceHash($user->id, $request->userAgent(), $deviceInfo['token']);

        // Default clean envelope
        $result = [
            'decision'        => 'allow',
            'action'          => 'geo_login_allowed',
            'mode'            => $mode,
            'country'         => null,
            'asn'             => null,
            'org'             => null,
            'is_datacenter'   => false,
            'is_vpn_or_proxy' => false,
            'is_tor'          => false,
            'risk_score'      => 0,
            'risk_reasons'    => [],
            'device_hash'     => $deviceHash,
            'device_cookie'   => $deviceInfo['cookie'],
            'should_block'    => false,
        ];

        // 1. Mode OFF -> complete bypass
        if ($mode === 'off') {
            $result['action'] = null; // No audit row when explicitly off
            $result['risk_reasons'][] = 'geo_access_mode_off';
            return $result;
        }

        // 2. Emergency Override Check (Artisan CLI switch or ENV flag)
        if ($this->overrideService->isActive()) {
            $result['decision'] = 'allow';
            $result['action'] = 'geo_login_allowed';
            $result['risk_reasons'][] = 'emergency_override_active';
            $this->auditDecision($user, $request, $result);
            return $result;
        }

        // 3. Global CIDR Allow-List & Private Subnets
        if ($this->matchesCidrList($ip, (array) config('geo_access.cidr_allowlist', []))) {
            $result['decision'] = 'allow';
            $result['action'] = 'geo_login_allowed';
            $result['risk_reasons'][] = 'cidr_allowlist_match';
            $this->auditDecision($user, $request, $result);
            return $result;
        }

        // 4. Perform ultra-fast offline Geo/ASN Lookup
        $geo = GeoLookup::lookup($ip);
        $result['country'] = $geo['country'];
        $result['asn'] = $geo['asn'];
        $result['org'] = $geo['org'];
        $result['is_datacenter'] = $geo['is_datacenter'];
        $result['is_vpn_or_proxy'] = $geo['is_vpn_or_proxy'];
        $result['is_tor'] = $geo['is_tor'];

        // 5. Fail-Open Check on Unknown / Unresolved Lookup
        // Operators must never be locked out due to missing data or parse exceptions.
        if ($geo['source'] === 'fail_open' || $geo['source'] === 'rfc1918') {
            $result['decision'] = 'allow';
            $result['action'] = 'geo_login_allowed';
            $result['risk_reasons'][] = 'geo_lookup_fail_open';
            $this->auditDecision($user, $request, $result);
            return $result;
        }

        // 6. Named User Exception Check
        $userExceptions = (array) config('geo_access.user_exceptions', []);
        $username = strtolower((string) $user->username);
        if (isset($userExceptions[$username])) {
            $ex = $userExceptions[$username];
            $expiresAt = isset($ex['expires_at']) ? Carbon::parse($ex['expires_at']) : null;

            if ($expiresAt && now()->lessThanOrEqualTo($expiresAt)) {
                $allowed = (array) ($ex['allowed_countries'] ?? ['*']);
                if (in_array('*', $allowed, true) || ($geo['country'] !== null && in_array($geo['country'], $allowed, true))) {
                    $result['decision'] = 'allow';
                    $result['action'] = 'geo_login_allowed';
                    $result['risk_reasons'][] = 'user_exception_active:' . ($ex['owner'] ?? 'admin');
                    $this->auditDecision($user, $request, $result);
                    return $result;
                }
            } else {
                $result['risk_reasons'][] = 'user_exception_expired';
                $result['risk_score'] += 15;
            }
        }

        // 7. Role Strictness Configuration
        $roleName = strtolower((string) ($user->role?->name ?? 'default'));
        $isAdminRole = in_array($roleName, ['super_admin', 'operation_admin'], true);

        $roleConfig = config("geo_access.roles.{$roleName}") ?? config('geo_access.roles.default', []);
        $strictPakistanOnly = (bool) ($roleConfig['strict_pakistan_only'] ?? true);
        $blockTor = (bool) ($roleConfig['block_tor'] ?? false);
        $blockVpnProxy = (bool) ($roleConfig['block_vpn_proxy'] ?? false);
        $blockDatacenter = (bool) ($roleConfig['block_datacenter'] ?? false);

        // 8. Fixed CIDR Allow-List for Admin Roles
        if ($isAdminRole) {
            $adminCidrs = (array) config('geo_access.admin_cidr_allowlist', []);
            if (!empty($adminCidrs) && !$this->matchesCidrList($ip, $adminCidrs)) {
                $result['risk_reasons'][] = 'admin_cidr_allowlist_violation';
                $result['risk_score'] += 50;
            }
        }

        // 9. Core Geo / ASN Policy Rules
        $denyTriggered = false;

        // Country validation
        $allowedCountries = (array) config('geo_access.allowed_countries', ['PK']);
        $country = $geo['country'];

        if ($country === null || !in_array($country, $allowedCountries, true)) {
            $countryLabel = $country ?? 'UNKNOWN';
            $result['risk_reasons'][] = "foreign_or_unauthorized_country:{$countryLabel}";
            $result['risk_score'] += 50;

            if ($strictPakistanOnly) {
                $denyTriggered = true;
            }
        }

        // Tor exit node rule
        if ($geo['is_tor']) {
            $result['risk_reasons'][] = 'tor_exit_node_detected';
            $result['risk_score'] += 40;

            if ($blockTor) {
                $denyTriggered = true;
            }
        }

        // VPN / Proxy rule
        if ($geo['is_vpn_or_proxy']) {
            $result['risk_reasons'][] = 'vpn_or_proxy_detected';
            $result['risk_score'] += 30;

            if ($blockVpnProxy) {
                $denyTriggered = true;
            }
        }

        // Hosting / Datacenter ASN rule
        if ($geo['is_datacenter']) {
            $result['risk_reasons'][] = 'datacenter_or_cloud_asn:' . ($geo['asn'] ?? 'unknown');
            $result['risk_score'] += 25;

            if ($blockDatacenter) {
                $denyTriggered = true;
            }
        }

        // 10. Device & Behavioral Anomaly Detections
        $anomalies = $this->fingerprintService->detectAnomalies($user, $geo, $deviceHash);
        foreach ($anomalies as $anomaly) {
            $result['risk_reasons'][] = $anomaly;
            if (str_starts_with($anomaly, 'impossible_travel')) {
                $result['risk_score'] += 40;
            } elseif (str_starts_with($anomaly, 'new_country')) {
                $result['risk_score'] += 20;
            } elseif ($anomaly === 'new_device_or_browser') {
                $result['risk_score'] += 10;
            }
        }

        // 11. Final Decision Formulation
        if ($denyTriggered) {
            if ($mode === 'enforce') {
                $result['decision'] = 'deny';
                $result['action'] = 'geo_login_denied';
                $result['should_block'] = true;
            } else {
                // In shadow mode: NEVER deny. Record what would have happened.
                $result['decision'] = 'shadow_would_deny';
                $result['action'] = 'geo_login_shadow_would_deny';
                $result['should_block'] = false;
            }
        } elseif (!empty($result['risk_reasons'])) {
            $result['decision'] = 'allow_with_alert';
            $result['action'] = 'geo_login_alert';
            $result['should_block'] = false;
        } else {
            $result['decision'] = 'allow';
            $result['action'] = 'geo_login_allowed';
            $result['should_block'] = false;
        }

        // Write forensic hash-chained audit log row
        $this->auditDecision($user, $request, $result);

        return $result;
    }

    /**
     * Write exactly one hash-chained audit row per login decision.
     */
    private function auditDecision(User $user, Request $request, array $result): void
    {
        if ($result['action'] === null) {
            return;
        }

        try {
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => $result['action'],
                'table_name' => 'users',
                'record_id'  => $user->id,
                'old_values' => [],
                'new_values' => [
                    'username'     => $user->username,
                    'role'         => $user->role?->name,
                    'mode'         => $result['mode'],
                    'decision'     => $result['decision'],
                    'country'      => $result['country'],
                    'asn'          => $result['asn'],
                    'org'          => $result['org'],
                    'risk_score'   => $result['risk_score'],
                    'risk_reasons' => $result['risk_reasons'],
                    'device_hash'  => substr($result['device_hash'], 0, 16),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit write must never break the login path for operators
            Log::warning("Failed writing Geo policy audit row [{$result['action']}]: " . $e->getMessage());
        }
    }

    /**
     * Match an IP address against a list of CIDR subnets (IPv4 and IPv6).
     *
     * @param string $ip
     * @param array<int, string> $cidrs
     * @return bool
     */
    public function matchesCidrList(string $ip, array $cidrs): bool
    {
        $normalizedIp = GeoLookup::normalizeIp($ip);
        if ($normalizedIp === null) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            $cidr = trim($cidr);
            if ($cidr === '') {
                continue;
            }

            if ($this->ipMatchesCidr($normalizedIp, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a single IP against a CIDR block.
     */
    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;

        $isIpv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $subnetIsIpv4 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        if ($isIpv4 !== $subnetIsIpv4) {
            return false;
        }

        if ($isIpv4) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false || $mask < 0 || $mask > 32) {
                return false;
            }
            if ($mask === 0) {
                return true;
            }

            $maskBits = -1 << (32 - $mask);
            return ($ipLong & $maskBits) === ($subnetLong & $maskBits);
        }

        // IPv6 matching
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || $mask < 0 || $mask > 128) {
            return false;
        }
        if ($mask === 0) {
            return true;
        }

        $fullBytes = intdiv($mask, 8);
        $remBits = $mask % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remBits > 0) {
            $ipByte = ord($ipBin[$fullBytes]);
            $subnetByte = ord($subnetBin[$fullBytes]);
            $maskByte = (0xff << (8 - $remBits)) & 0xff;
            if (($ipByte & $maskByte) !== ($subnetByte & $maskByte)) {
                return false;
            }
        }

        return true;
    }
}
