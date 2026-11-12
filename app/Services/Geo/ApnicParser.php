<?php

namespace App\Services\Geo;

class ApnicParser
{
    /**
     * Parse APNIC delegation file content and extract Pakistan allocations.
     *
     * @param string $content
     * @return array{
     *     ipv4_binary: string,
     *     ipv4_ranges: array<int, array{start: int, end: int, start_ip: string, end_ip: string}>,
     *     ipv6_binary: string,
     *     ipv6_ranges: array<int, array{start_ip: string, end_ip: string, prefix: int}>,
     *     asns: array<int, string>,
     *     stats: array<string, int>
     * }
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\r?\n/', $content);
        $ipv4List = [];
        $ipv6List = [];
        $asnList = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // APNIC record format: registry|cc|type|start|value|date|status[|extensions]
            $parts = explode('|', $line);
            if (count($parts) < 7) {
                continue;
            }

            [$registry, $cc, $type, $start, $value, $date, $status] = $parts;

            // Only extract Pakistan allocations/assignments
            if (strtoupper($cc) !== 'PK') {
                continue;
            }

            if (!in_array(strtolower($status), ['allocated', 'assigned'], true)) {
                continue;
            }

            if ($type === 'ipv4') {
                $count = (int) $value;
                $startLong = ip2long($start);
                if ($startLong !== false && $count > 0) {
                    $startUint = (int) sprintf('%u', $startLong);
                    $endUint = $startUint + $count - 1;
                    $ipv4List[] = [
                        'start' => $startUint,
                        'end' => $endUint,
                        'start_ip' => $start,
                        'end_ip' => long2ip($endUint),
                    ];
                }
            } elseif ($type === 'ipv6') {
                $prefix = (int) $value;
                $startBin = @inet_pton($start);
                if ($startBin !== false && strlen($startBin) === 16 && $prefix >= 0 && $prefix <= 128) {
                    $endBin = $this->calculateIpv6EndBinary($startBin, $prefix);
                    $ipv6List[] = [
                        'start_bin' => $startBin,
                        'end_bin' => $endBin,
                        'start_ip' => $start,
                        'end_ip' => inet_ntop($endBin),
                        'prefix' => $prefix,
                    ];
                }
            } elseif ($type === 'asn') {
                $asn = (int) $start;
                $count = (int) $value;
                for ($i = 0; $i < $count; $i++) {
                    $asnList[$asn + $i] = 'PK';
                }
            }
        }

        // Sort and merge IPv4 ranges
        usort($ipv4List, fn ($a, $b) => $a['start'] <=> $b['start']);
        $mergedIpv4 = $this->mergeIpv4Ranges($ipv4List);

        // Sort and merge IPv6 ranges
        usort($ipv6List, fn ($a, $b) => strcmp($a['start_bin'], $b['start_bin']));
        $mergedIpv6 = $this->mergeIpv6Ranges($ipv6List);

        // Build packed binary representations
        $ipv4Binary = '';
        foreach ($mergedIpv4 as $range) {
            $ipv4Binary .= pack('NN', $range['start'], $range['end']);
        }

        $ipv6Binary = '';
        foreach ($mergedIpv6 as $range) {
            $ipv6Binary .= $range['start_bin'] . $range['end_bin'];
        }

        ksort($asnList);

        return [
            'ipv4_binary' => $ipv4Binary,
            'ipv4_ranges' => $mergedIpv4,
            'ipv6_binary' => $ipv6Binary,
            'ipv6_ranges' => $mergedIpv6,
            'asns' => $asnList,
            'stats' => [
                'ipv4_ranges_count' => count($mergedIpv4),
                'ipv6_ranges_count' => count($mergedIpv6),
                'asns_count' => count($asnList),
            ],
        ];
    }

    /**
     * Merge adjacent and overlapping IPv4 ranges.
     *
     * @param array<int, array{start: int, end: int, start_ip: string, end_ip: string}> $ranges
     * @return array<int, array{start: int, end: int, start_ip: string, end_ip: string}>
     */
    private function mergeIpv4Ranges(array $ranges): array
    {
        if (empty($ranges)) {
            return [];
        }

        $merged = [];
        $current = $ranges[0];

        for ($i = 1, $n = count($ranges); $i < $n; $i++) {
            $next = $ranges[$i];

            if ($next['start'] <= ($current['end'] + 1)) {
                // Overlapping or strictly adjacent
                if ($next['end'] > $current['end']) {
                    $current['end'] = $next['end'];
                    $current['end_ip'] = long2ip($next['end']);
                }
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;
        return $merged;
    }

    /**
     * Merge adjacent and overlapping IPv6 ranges.
     *
     * @param array<int, array{start_bin: string, end_bin: string, start_ip: string, end_ip: string, prefix: int}> $ranges
     * @return array<int, array{start_bin: string, end_bin: string, start_ip: string, end_ip: string, prefix: int}>
     */
    private function mergeIpv6Ranges(array $ranges): array
    {
        if (empty($ranges)) {
            return [];
        }

        $merged = [];
        $current = $ranges[0];

        for ($i = 1, $n = count($ranges); $i < $n; $i++) {
            $next = $ranges[$i];

            // If next start is <= current end
            if (strcmp($next['start_bin'], $current['end_bin']) <= 0) {
                if (strcmp($next['end_bin'], $current['end_bin']) > 0) {
                    $current['end_bin'] = $next['end_bin'];
                    $current['end_ip'] = inet_ntop($next['end_bin']);
                }
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;
        return $merged;
    }

    /**
     * Calculate 16-byte end binary for an IPv6 CIDR prefix.
     */
    public function calculateIpv6EndBinary(string $startBin, int $prefix): string
    {
        $endBin = $startBin;
        $fullBytes = intdiv($prefix, 8);
        $remBits = $prefix % 8;

        if ($remBits > 0 && $fullBytes < 16) {
            $mask = (1 << (8 - $remBits)) - 1;
            $endBin[$fullBytes] = chr(ord($startBin[$fullBytes]) | $mask);
            $fullBytes++;
        }

        for ($i = $fullBytes; $i < 16; $i++) {
            $endBin[$i] = "\xff";
        }

        return $endBin;
    }
}
