<?php

namespace App\Console\Commands;

use App\Services\Geo\ApnicParser;
use App\Services\Geo\GeoLookup;
use App\Services\Geo\MmdbReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class GeoRefreshCommand extends Command
{
    protected $signature = 'geo:refresh
                            {--maxmind-key= : Optional MaxMind license key to download GeoLite2 Country & ASN}
                            {--offline : Skip network requests and compile using bundled fallback datasets}
                            {--target-dir= : Custom output directory for the compiled geo files}';

    protected $description = 'Refresh offline Pakistan geo data, Tor exit nodes, VPN lists, and datacenter ASNs';

    public function handle(ApnicParser $apnicParser): int
    {
        $this->info('Starting NHMP 130 Helpline Geo & ASN Data Refresh...');

        $targetDir = (string) ($this->option('target-dir') ?: config('geo_access.storage_path', storage_path('app/geo')));

        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true, true);
        }

        $isOffline = (bool) $this->option('offline');
        $maxmindKey = (string) ($this->option('maxmind-key') ?: env('MAXMIND_LICENSE_KEY', ''));

        $summary = [];

        // 1. APNIC Delegation Data (Pakistan IPv4 & IPv6 ranges)
        $this->line('Fetching APNIC delegation data...');
        $apnicContent = null;
        if (!$isOffline) {
            $apnicContent = $this->fetchUrl('https://ftp.apnic.net/stats/apnic/delegated-apnic-latest', 30);
        }

        if (empty($apnicContent)) {
            $this->warn('Could not fetch remote APNIC file (or running offline). Using bundled Pakistan seed data.');
            $apnicContent = $this->getBundledApnicSeed();
        }

        $parsedApnic = $apnicParser->parse($apnicContent);

        File::put($targetDir . '/pk_ipv4.bin', $parsedApnic['ipv4_binary']);
        File::put($targetDir . '/pk_ipv6.bin', $parsedApnic['ipv6_binary']);
        File::put($targetDir . '/pk_asns.json', json_encode($parsedApnic['asns'], JSON_PRETTY_PRINT));

        $summary[] = ['Dataset' => 'Pakistan IPv4 Ranges (APNIC)', 'Records' => $parsedApnic['stats']['ipv4_ranges_count'], 'File' => 'pk_ipv4.bin'];
        $summary[] = ['Dataset' => 'Pakistan IPv6 Ranges (APNIC)', 'Records' => $parsedApnic['stats']['ipv6_ranges_count'], 'File' => 'pk_ipv6.bin'];
        $summary[] = ['Dataset' => 'Pakistan ASNs (APNIC)', 'Records' => $parsedApnic['stats']['asns_count'], 'File' => 'pk_asns.json'];

        // 2. Tor Bulk Exit Node List
        $this->line('Fetching Tor exit nodes...');
        $torContent = null;
        if (!$isOffline) {
            $torContent = $this->fetchUrl('https://check.torproject.org/torbulkexitlist', 15);
        }

        if (empty($torContent)) {
            $this->warn('Could not fetch Tor exit list. Using fallback seed.');
            $torContent = $this->getBundledTorSeed();
        }

        $torIps = [];
        $lines = preg_split('/\r?\n/', $torContent);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && filter_var($line, FILTER_VALIDATE_IP)) {
                $torIps[$line] = 1;
            }
        }

        File::put($targetDir . '/tor_exits.json', json_encode($torIps, JSON_PRETTY_PRINT));
        File::put($targetDir . '/tor_exits.txt', implode("\n", array_keys($torIps)));
        $summary[] = ['Dataset' => 'Tor Exit Nodes', 'Records' => count($torIps), 'File' => 'tor_exits.json'];

        // 3. Known Public VPN / Proxy IP Ranges
        $this->line('Fetching VPN / Proxy IP feeds...');
        $vpnContent = null;
        if (!$isOffline) {
            $vpnContent = $this->fetchUrl('https://raw.githubusercontent.com/X4BNet/lists_vpn/main/ipv4.txt', 20);
        }

        if (empty($vpnContent)) {
            $this->warn('Could not fetch VPN list. Using fallback seed.');
            $vpnContent = $this->getBundledVpnSeed();
        }

        $vpnRanges = $this->parseCidrListToBinary($vpnContent);
        File::put($targetDir . '/vpn_proxies.bin', $vpnRanges['binary']);
        $summary[] = ['Dataset' => 'Public VPN/Proxy Ranges', 'Records' => $vpnRanges['count'], 'File' => 'vpn_proxies.bin'];

        // 4. Curated Datacenter ASNs
        $dcAsns = GeoLookup::DEFAULT_DATACENTER_ASNS;
        File::put($targetDir . '/datacenter_asns.json', json_encode($dcAsns, JSON_PRETTY_PRINT));
        $summary[] = ['Dataset' => 'Datacenter / Cloud ASNs', 'Records' => count($dcAsns), 'File' => 'datacenter_asns.json'];

        // 5. MaxMind GeoLite2 databases (optional)
        if (!empty($maxmindKey)) {
            $this->info('Downloading MaxMind GeoLite2 Country & ASN using supplied license key...');
            $this->downloadMaxMind('GeoLite2-Country', $maxmindKey, $targetDir);
            $this->downloadMaxMind('GeoLite2-ASN', $maxmindKey, $targetDir);
        }

        // Validate local MMDB files if present
        $countryMmdb = $targetDir . '/GeoLite2-Country.mmdb';
        $asnMmdb = $targetDir . '/GeoLite2-ASN.mmdb';
        if (File::exists($countryMmdb)) {
            $reader = new MmdbReader($countryMmdb);
            $summary[] = ['Dataset' => 'MaxMind GeoLite2 Country', 'Records' => $reader->isValid() ? 'Valid MMDB' : 'Invalid', 'File' => 'GeoLite2-Country.mmdb'];
        }
        if (File::exists($asnMmdb)) {
            $reader = new MmdbReader($asnMmdb);
            $summary[] = ['Dataset' => 'MaxMind GeoLite2 ASN', 'Records' => $reader->isValid() ? 'Valid MMDB' : 'Invalid', 'File' => 'GeoLite2-ASN.mmdb'];
        }

        // 6. Write Metadata
        $metadata = [
            'updated_at' => now()->toIso8601String(),
            'version' => '1.0.0',
            'datasets' => $summary,
            'node' => gethostname(),
        ];
        File::put($targetDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

        // Re-warm GeoLookup in memory
        GeoLookup::resetForTesting();
        GeoLookup::warmup($targetDir);

        $this->newLine();
        $this->table(['Dataset', 'Records', 'File'], $summary);
        $this->info("Geo refresh complete. Files successfully written to [{$targetDir}].");

        return SymfonyCommand::SUCCESS;
    }

    private function fetchUrl(string $url, int $timeout = 15): ?string
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'user_agent' => 'NHMP-130-GeoUpdater/1.0',
                    'follow_location' => 1,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $content = @file_get_contents($url, false, $ctx);
            if ($content !== false && strlen($content) > 0) {
                return $content;
            }
        } catch (\Throwable $e) {
            // Silently fall back
        }

        return null;
    }

    private function parseCidrListToBinary(string $content): array
    {
        $lines = preg_split('/\r?\n/', $content);
        $ranges = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (str_contains($line, '/')) {
                [$net, $mask] = explode('/', $line);
                $mask = (int) $mask;
                $netLong = ip2long($net);
                if ($netLong !== false && $mask >= 0 && $mask <= 32) {
                    $netUint = (int) sprintf('%u', $netLong);
                    $wildcard = (1 << (32 - $mask)) - 1;
                    $start = $netUint & ~$wildcard;
                    $end = $start | $wildcard;
                    $ranges[] = ['start' => $start, 'end' => $end];
                }
            } else {
                $long = ip2long($line);
                if ($long !== false) {
                    $uint = (int) sprintf('%u', $long);
                    $ranges[] = ['start' => $uint, 'end' => $uint];
                }
            }
        }

        usort($ranges, fn ($a, $b) => $a['start'] <=> $b['start']);

        // Merge adjacent / overlapping
        $merged = [];
        if (!empty($ranges)) {
            $curr = $ranges[0];
            for ($i = 1, $n = count($ranges); $i < $n; $i++) {
                if ($ranges[$i]['start'] <= ($curr['end'] + 1)) {
                    if ($ranges[$i]['end'] > $curr['end']) {
                        $curr['end'] = $ranges[$i]['end'];
                    }
                } else {
                    $merged[] = $curr;
                    $curr = $ranges[$i];
                }
            }
            $merged[] = $curr;
        }

        $bin = '';
        foreach ($merged as $r) {
            $bin .= pack('NN', $r['start'], $r['end']);
        }

        return [
            'binary' => $bin,
            'count' => count($merged),
        ];
    }

    private function downloadMaxMind(string $edition, string $licenseKey, string $targetDir): void
    {
        $url = "https://download.maxmind.com/app/geoip_download?edition_id={$edition}&license_key={$licenseKey}&suffix=tar.gz";
        $tarGzContent = $this->fetchUrl($url, 60);

        if (empty($tarGzContent)) {
            $this->error("Failed to download MaxMind edition [{$edition}]. Check license key.");
            return;
        }

        $tmpTarGz = tempnam(sys_get_temp_dir(), 'mmdb_');
        file_put_contents($tmpTarGz, $tarGzContent);

        try {
            $phar = new \PharData($tmpTarGz);
            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                if (str_ends_with($file->getFilename(), '.mmdb')) {
                    copy($file->getPathname(), $targetDir . "/{$edition}.mmdb");
                    $this->info("Successfully extracted [{$edition}.mmdb]");
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->error("Could not extract MaxMind tar.gz: " . $e->getMessage());
        } finally {
            @unlink($tmpTarGz);
        }
    }

    private function getBundledApnicSeed(): string
    {
        // Major Pakistani ISP IP Allocations (PTCL, Nayatel, Jazz, Telenor, Zong, Ufone, NTC, etc.)
        return <<<'APNIC'
apnic|PK|asn|24499|1|20021021|allocated
apnic|PK|asn|9541|1|19990601|allocated
apnic|PK|asn|17557|1|20010105|allocated
apnic|PK|asn|38264|1|20060920|allocated
apnic|PK|ipv4|39.32.0.0|524288|20080611|allocated
apnic|PK|ipv4|110.36.0.0|131072|20100416|allocated
apnic|PK|ipv4|111.68.96.0|8192|20090408|allocated
apnic|PK|ipv4|111.92.128.0|32768|20090812|allocated
apnic|PK|ipv4|111.119.160.0|16384|20090901|allocated
apnic|PK|ipv4|115.186.0.0|32768|20100122|allocated
apnic|PK|ipv4|119.152.0.0|262144|20080208|allocated
apnic|PK|ipv4|119.160.0.0|65536|20080227|allocated
apnic|PK|ipv4|124.29.192.0|32768|20040825|allocated
apnic|PK|ipv4|175.107.0.0|65536|20100204|allocated
apnic|PK|ipv4|175.139.128.0|32768|20100427|allocated
apnic|PK|ipv4|182.180.0.0|262144|20101014|allocated
apnic|PK|ipv4|202.83.160.0|8192|20010815|allocated
apnic|PK|ipv4|202.125.130.0|1024|20010913|allocated
apnic|PK|ipv4|202.141.240.0|2048|20030219|allocated
apnic|PK|ipv4|203.124.32.0|8192|20000412|allocated
apnic|PK|ipv4|203.135.0.0|32768|20000623|allocated
apnic|PK|ipv4|203.175.64.0|8192|20010419|allocated
apnic|PK|ipv4|203.215.160.0|16384|20010821|allocated
apnic|PK|ipv4|221.120.192.0|16384|20041115|allocated
apnic|PK|ipv6|2400:adc0::|32|20110307|allocated
apnic|PK|ipv6|2402:8100::|32|20110825|allocated
apnic|PK|ipv6|2404:3100::|32|20120220|allocated
APNIC;
    }

    private function getBundledTorSeed(): string
    {
        return <<<'TOR'
185.220.101.5
185.220.101.6
185.220.101.7
109.70.100.25
198.51.100.99
TOR;
    }

    private function getBundledVpnSeed(): string
    {
        return <<<'VPN'
198.51.100.0/24
203.0.113.0/24
VPN;
    }
}
