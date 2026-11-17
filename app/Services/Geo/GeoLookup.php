<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Log;

/**
 * High-performance, offline Geo/ASN lookup service for NHMP 130 Helpline.
 *
 * Implements in-memory binary search over compiled APNIC Pakistan IPv4/IPv6 ranges,
 * Tor exit nodes, datacenter/hosting ASNs, and optional MaxMind GeoLite2 MMDB databases.
 * Guarantees zero blocking per-request I/O, strict in-memory worker safety, and fail-open resilience.
 */
class GeoLookup
{
    private static bool $isWarmedUp = false;
    private static ?string $pkIpv4Binary = null;
    private static int $pkIpv4Count = 0;
    private static ?string $pkIpv6Binary = null;
    private static int $pkIpv6Count = 0;
    private static ?string $vpnIpv4Binary = null;
    private static int $vpnIpv4Count = 0;
    private static array $torExits = [];
    private static array $datacenterAsns = [];
    private static array $pkAsns = [];
    private static ?MmdbReader $countryMmdb = null;
    private static ?MmdbReader $asnMmdb = null;
    private static ?MmdbReader $cityMmdb = null;
    private static ?string $customStorageDir = null;

    /**
     * Default curated hosting/cloud/datacenter ASNs.
     */
    public const DEFAULT_DATACENTER_ASNS = [
        16509  => 'Amazon AWS',
        14618  => 'Amazon AWS',
        15169  => 'Google Cloud',
        396982 => 'Google Cloud',
        8075   => 'Microsoft Azure',
        14061  => 'DigitalOcean',
        24940  => 'Hetzner Online',
        63949  => 'Linode / Akamai',
        16276  => 'OVH SAS',
        31898  => 'Oracle Cloud',
        45102  => 'Alibaba Cloud',
        20473  => 'Choopa / Vultr',
        51167  => 'Contabo GmbH',
        60068  => 'Datacamp / CDN77',
        13335  => 'Cloudflare',
        54113  => 'Fastly',
        46562  => 'Total Server Solutions',
        12876  => 'Scaleway',
        197540 => 'Netcup GmbH',
        49505  => 'Selectel',
        132203 => 'Tencent Cloud',
        36351  => 'SoftLayer / IBM',
        209    => 'CenturyLink / Lumen',
        3257   => 'GTT Communications',
        2914   => 'NTT Communications',
        6453   => 'Tata Communications',
        6939   => 'Hurricane Electric',
        9009   => 'M247 Ltd',
        46652  => 'Standard Proxies / VPN',
        39351  => '31173 Services / Mullvad',
        62240  => 'Clouvider',
        397373 => 'Fly.io',
        61120  => 'IPXO LLC',
        49981  => 'WorldStream B.V.',
        50673  => 'Serverius Holding B.V.',
        200000 => 'Leaseweb',
        28753  => 'Leaseweb Deutschland',
    ];

    /**
     * Warm up all datasets into memory once per RoadRunner worker lifecycle.
     */
    public static function hasCityData(): bool
    {
        return self::$cityMmdb !== null;
    }

    public static function warmup(?string $storageDir = null): void
    {
        if ($storageDir !== null) {
            self::$customStorageDir = $storageDir;
        }

        $dir = self::getStorageDir();

        try {
            // 1. Pakistan IPv4 binary table
            $v4File = $dir . '/pk_ipv4.bin';
            if (file_exists($v4File) && is_readable($v4File)) {
                self::$pkIpv4Binary = (string) file_get_contents($v4File);
                self::$pkIpv4Count = (int) (strlen(self::$pkIpv4Binary) / 8);
            } else {
                self::$pkIpv4Binary = '';
                self::$pkIpv4Count = 0;
            }

            // 2. Pakistan IPv6 binary table
            $v6File = $dir . '/pk_ipv6.bin';
            if (file_exists($v6File) && is_readable($v6File)) {
                self::$pkIpv6Binary = (string) file_get_contents($v6File);
                self::$pkIpv6Count = (int) (strlen(self::$pkIpv6Binary) / 32);
            } else {
                self::$pkIpv6Binary = '';
                self::$pkIpv6Count = 0;
            }

            // 3. VPN / Proxy IPv4 binary table
            $vpnFile = $dir . '/vpn_proxies.bin';
            if (file_exists($vpnFile) && is_readable($vpnFile)) {
                self::$vpnIpv4Binary = (string) file_get_contents($vpnFile);
                self::$vpnIpv4Count = (int) (strlen(self::$vpnIpv4Binary) / 8);
            } else {
                self::$vpnIpv4Binary = '';
                self::$vpnIpv4Count = 0;
            }

            // 4. Tor exit nodes map (hash set)
            $torFile = $dir . '/tor_exits.json';
            if (file_exists($torFile) && is_readable($torFile)) {
                $decoded = json_decode((string) file_get_contents($torFile), true);
                self::$torExits = is_array($decoded) ? $decoded : [];
            } else {
                self::$torExits = [];
            }

            // 5. Curated datacenter ASNs
            $dcFile = $dir . '/datacenter_asns.json';
            if (file_exists($dcFile) && is_readable($dcFile)) {
                $decoded = json_decode((string) file_get_contents($dcFile), true);
                self::$datacenterAsns = is_array($decoded) ? ($decoded + self::DEFAULT_DATACENTER_ASNS) : self::DEFAULT_DATACENTER_ASNS;
            } else {
                self::$datacenterAsns = self::DEFAULT_DATACENTER_ASNS;
            }

            // 6. Pakistan ASNs
            $asnPkFile = $dir . '/pk_asns.json';
            if (file_exists($asnPkFile) && is_readable($asnPkFile)) {
                $decoded = json_decode((string) file_get_contents($asnPkFile), true);
                self::$pkAsns = is_array($decoded) ? $decoded : [];
            } else {
                self::$pkAsns = [];
            }

            // 7. Optional MaxMind GeoLite2 databases
            $countryMmdbFile = $dir . '/GeoLite2-Country.mmdb';
            if (file_exists($countryMmdbFile) && is_readable($countryMmdbFile)) {
                $reader = new MmdbReader($countryMmdbFile);
                self::$countryMmdb = $reader->isValid() ? $reader : null;
            } else {
                self::$countryMmdb = null;
            }

            self::$asnMmdb = null;
            foreach (['GeoLite2-ASN.mmdb', 'dbip-asn-lite.mmdb'] as $name) {
                $f = $dir . '/' . $name;
                if (self::$asnMmdb === null && file_exists($f) && is_readable($f)) {
                    $reader = new MmdbReader($f);
                    self::$asnMmdb = $reader->isValid() ? $reader : null;
                }
            }

            // City-level database (MaxMind GeoLite2-City or DB-IP City Lite): city, region, coordinates, country
            self::$cityMmdb = null;
            foreach (['GeoLite2-City.mmdb', 'dbip-city-lite.mmdb'] as $name) {
                $f = $dir . '/' . $name;
                if (self::$cityMmdb === null && file_exists($f) && is_readable($f)) {
                    $reader = new MmdbReader($f);
                    self::$cityMmdb = $reader->isValid() ? $reader : null;
                }
            }

            self::$isWarmedUp = true;
        } catch (\Throwable $e) {
            // Fail open on warmup error
            Log::warning('GeoLookup warmup encountered error (failing open): ' . $e->getMessage());
            self::$isWarmedUp = true;
        }
    }

    /**
     * Perform an ultra-low latency (< 1ms) offline Geo/ASN lookup.
     *
     * @param string $ip
     * @return array{
     *     country: ?string,
     *     asn: ?int,
     *     org: ?string,
     *     is_datacenter: bool,
     *     is_vpn_or_proxy: bool,
     *     is_tor: bool,
     *     source: string
     * }
     */
    public static function lookup(string $ip): array
    {
        // Fail-open default envelope
        $defaultResult = [
            'country'         => null,
            'asn'             => null,
            'org'             => null,
            'is_datacenter'   => false,
            'is_vpn_or_proxy' => false,
            'is_tor'          => false,
            'source'          => 'fail_open',
        ];

        try {
            $normalized = self::normalizeIp($ip);
            if ($normalized === null) {
                return [
                    'country'         => null,
                    'asn'             => null,
                    'org'             => null,
                    'is_datacenter'   => false,
                    'is_vpn_or_proxy' => false,
                    'is_tor'          => false,
                    'source'          => 'invalid_ip',
                ];
            }

            if (!self::$isWarmedUp) {
                self::warmup();
            }

            // If geo datasets are missing or corrupt, fail open immediately
            if (empty(self::$pkIpv4Binary) && self::$countryMmdb === null && self::$cityMmdb === null) {
                return [
                    'country'         => null,
                    'asn'             => null,
                    'org'             => null,
                    'is_datacenter'   => false,
                    'is_vpn_or_proxy' => false,
                    'is_tor'          => false,
                    'source'          => 'fail_open',
                ];
            }

            // Check RFC 1918 / Loopback / Private IPs
            if (self::isPrivateOrLoopback($normalized)) {
                return [
                    'country'         => null,
                    'asn'             => null,
                    'org'             => 'Private Network',
                    'is_datacenter'   => false,
                    'is_vpn_or_proxy' => false,
                    'is_tor'          => false,
                    'source'          => 'rfc1918',
                ];
            }

            $isTor = isset(self::$torExits[$normalized]);
            $isVpnOrProxy = $isTor;
            $isDatacenter = false;
            $country = null;
            $asn = null;
            $org = null;
            $source = 'unknown';
            $city = null;
            $region = null;
            $latitude = null;
            $longitude = null;

            // 0. City-level lookup (city, region, coordinates, country)
            if (self::$cityMmdb !== null) {
                $record = self::$cityMmdb->lookup($normalized);
                if (is_array($record)) {
                    if (isset($record['country']['iso_code'])) {
                        $country = strtoupper((string) $record['country']['iso_code']);
                        $source = 'city_db';
                    }
                    $city = isset($record['city']['names']['en']) ? (string) $record['city']['names']['en'] : null;
                    $region = isset($record['subdivisions'][0]['names']['en']) ? (string) $record['subdivisions'][0]['names']['en'] : null;
                    if (isset($record['location']['latitude'], $record['location']['longitude'])) {
                        $latitude = round((float) $record['location']['latitude'], 6);
                        $longitude = round((float) $record['location']['longitude'], 6);
                    }
                }
            }

            // 1. MaxMind GeoLite2 MMDB lookups if available
            if ($country === null && self::$countryMmdb !== null) {
                $record = self::$countryMmdb->lookup($normalized);
                if (is_array($record) && isset($record['country']['iso_code'])) {
                    $country = strtoupper((string) $record['country']['iso_code']);
                    $source = 'maxmind';
                }
            }

            if (self::$asnMmdb !== null) {
                $record = self::$asnMmdb->lookup($normalized);
                if (is_array($record)) {
                    if (isset($record['autonomous_system_number'])) {
                        $asn = (int) $record['autonomous_system_number'];
                    }
                    if (isset($record['autonomous_system_organization'])) {
                        $org = (string) $record['autonomous_system_organization'];
                    }
                }
            }

            // 2. APNIC Fallback for Pakistan IPv4 / IPv6
            $isIpv4 = filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

            if ($country === null) {
                if ($isIpv4) {
                    if (self::matchesPkIpv4($normalized)) {
                        $country = 'PK';
                        $source = 'apnic';
                    }
                } else {
                    if (self::matchesPkIpv6($normalized)) {
                        $country = 'PK';
                        $source = 'apnic';
                    }
                }
            }

            // 3. Datacenter / Cloud / Hosting ASN detection
            if ($asn !== null && isset(self::$datacenterAsns[$asn])) {
                $isDatacenter = true;
                $isVpnOrProxy = true;
                if ($org === null) {
                    $org = self::$datacenterAsns[$asn];
                }
            }

            // 4. Known VPN/Proxy ranges detection
            if (!$isVpnOrProxy && $isIpv4 && self::matchesVpnIpv4($normalized)) {
                $isVpnOrProxy = true;
                $isDatacenter = true;
            }

            // Check if ASN is verified Pakistani from APNIC
            if ($country === null && $asn !== null && isset(self::$pkAsns[$asn])) {
                $country = 'PK';
                if ($source === 'unknown') {
                    $source = 'apnic_asn';
                }
            }

            return [
                'country'         => $country,
                'asn'             => $asn,
                'org'             => $org,
                'is_datacenter'   => $isDatacenter,
                'is_vpn_or_proxy' => $isVpnOrProxy,
                'is_tor'          => $isTor,
                'source'          => $source,
                'city'            => $city,
                'region'          => $region,
                'latitude'        => $latitude,
                'longitude'       => $longitude,
            ];
        } catch (\Throwable $e) {
            Log::warning("GeoLookup failure for IP [{$ip}] (failing open): " . $e->getMessage());
            return $defaultResult;
        }
    }

    /**
     * Binary search over sorted Pakistan IPv4 ranges (packed 8-byte uint32 tuples).
     */
    public static function matchesPkIpv4(string $ip): bool
    {
        if (empty(self::$pkIpv4Binary) || self::$pkIpv4Count <= 0) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        $target = (int) sprintf('%u', $long);
        $low = 0;
        $high = self::$pkIpv4Count - 1;

        while ($low <= $high) {
            $mid = ($low + $high) >> 1;
            $chunk = substr(self::$pkIpv4Binary, $mid * 8, 8);
            $unpacked = unpack('Nstart/Nend', $chunk);
            $start = $unpacked['start'];
            $end = $unpacked['end'];

            if ($target >= $start && $target <= $end) {
                return true;
            }

            if ($target < $start) {
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return false;
    }

    /**
     * Binary search over sorted Pakistan IPv6 ranges (packed 32-byte binary tuples).
     */
    public static function matchesPkIpv6(string $ip): bool
    {
        if (empty(self::$pkIpv6Binary) || self::$pkIpv6Count <= 0) {
            return false;
        }

        $targetBin = @inet_pton($ip);
        if ($targetBin === false || strlen($targetBin) !== 16) {
            return false;
        }

        $low = 0;
        $high = self::$pkIpv6Count - 1;

        while ($low <= $high) {
            $mid = ($low + $high) >> 1;
            $start = substr(self::$pkIpv6Binary, $mid * 32, 16);
            $end = substr(self::$pkIpv6Binary, ($mid * 32) + 16, 16);

            if (strcmp($targetBin, $start) >= 0 && strcmp($targetBin, $end) <= 0) {
                return true;
            }

            if (strcmp($targetBin, $start) < 0) {
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return false;
    }

    /**
     * Binary search over known VPN IPv4 ranges.
     */
    public static function matchesVpnIpv4(string $ip): bool
    {
        if (empty(self::$vpnIpv4Binary) || self::$vpnIpv4Count <= 0) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        $target = (int) sprintf('%u', $long);
        $low = 0;
        $high = self::$vpnIpv4Count - 1;

        while ($low <= $high) {
            $mid = ($low + $high) >> 1;
            $chunk = substr(self::$vpnIpv4Binary, $mid * 8, 8);
            $unpacked = unpack('Nstart/Nend', $chunk);
            $start = $unpacked['start'];
            $end = $unpacked['end'];

            if ($target >= $start && $target <= $end) {
                return true;
            }

            if ($target < $start) {
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return false;
    }

    /**
     * Normalize IP string and strip IPv4-mapped IPv6 prefixes (::ffff:x.x.x.x).
     */
    public static function normalizeIp(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return null;
        }

        if (str_starts_with(strtolower($ip), '::ffff:')) {
            $v4part = substr($ip, 7);
            if (filter_var($v4part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $v4part;
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return null;
    }

    /**
     * Check if an address is loopback, RFC1918 private, link-local, or documentation.
     */
    public static function isPrivateOrLoopback(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Reset in-memory state for testing isolation.
     */
    public static function resetForTesting(): void
    {
        self::$isWarmedUp = false;
        self::$pkIpv4Binary = null;
        self::$pkIpv4Count = 0;
        self::$pkIpv6Binary = null;
        self::$pkIpv6Count = 0;
        self::$vpnIpv4Binary = null;
        self::$vpnIpv4Count = 0;
        self::$torExits = [];
        self::$datacenterAsns = self::DEFAULT_DATACENTER_ASNS;
        self::$pkAsns = [];
        self::$countryMmdb = null;
        self::$asnMmdb = null;
        self::$cityMmdb = null;
        self::$customStorageDir = null;
    }

    /**
     * Set explicit datasets in memory (useful for unit testing).
     */
    public static function setMockData(
        ?string $pkIpv4Bin = null,
        ?string $pkIpv6Bin = null,
        array $torExits = [],
        array $datacenterAsns = [],
        ?string $vpnIpv4Bin = null
    ): void {
        self::$isWarmedUp = true;
        self::$pkIpv4Binary = $pkIpv4Bin ?? '';
        self::$pkIpv4Count = strlen(self::$pkIpv4Binary) / 8;
        self::$pkIpv6Binary = $pkIpv6Bin ?? '';
        self::$pkIpv6Count = strlen(self::$pkIpv6Binary) / 32;
        self::$torExits = $torExits;
        self::$datacenterAsns = $datacenterAsns + self::DEFAULT_DATACENTER_ASNS;
        self::$vpnIpv4Binary = $vpnIpv4Bin ?? '';
        self::$vpnIpv4Count = strlen(self::$vpnIpv4Binary) / 8;
    }

    private static function getStorageDir(): string
    {
        if (self::$customStorageDir !== null) {
            return self::$customStorageDir;
        }

        if (function_exists('config') && config('geo_access.storage_path')) {
            return (string) config('geo_access.storage_path');
        }

        return storage_path('app/geo');
    }
}
