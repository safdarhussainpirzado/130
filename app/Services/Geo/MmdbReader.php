<?php

namespace App\Services\Geo;

/**
 * Pure PHP lightweight MaxMind DB (.mmdb) Reader.
 *
 * Implements the official MaxMind DB binary format without requiring
 * external C extensions (php-maxminddb) or heavy third-party composer packages.
 * Fails safely on any file format or decoding anomalies.
 */
class MmdbReader
{
    private const METADATA_MARKER = "\xab\xcd\xefMaxMind.com";

    private string $filePath;
    private $handle = null;
    private int $fileSize = 0;
    private int $nodeCount = 0;
    private int $recordSize = 0;
    private int $nodeByteSize = 0;
    private int $searchTreeSize = 0;
    private int $dataSectionOffset = 0;
    private int $ipVersion = 4;
    private string $databaseType = '';
    private bool $isValid = false;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->open();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getDatabaseType(): string
    {
        return $this->databaseType;
    }

    private function open(): void
    {
        if (!file_exists($this->filePath) || !is_readable($this->filePath)) {
            $this->isValid = false;
            return;
        }

        $size = filesize($this->filePath);
        if ($size === false || $size < 128) {
            $this->isValid = false;
            return;
        }

        $this->fileSize = $size;
        $h = @fopen($this->filePath, 'rb');
        if (!$h) {
            $this->isValid = false;
            return;
        }

        $this->handle = $h;

        try {
            $this->parseMetadata();
            $this->isValid = true;
        } catch (\Throwable $e) {
            $this->close();
            $this->isValid = false;
        }
    }

    private function close(): void
    {
        if ($this->handle) {
            @fclose($this->handle);
            $this->handle = null;
        }
        $this->isValid = false;
    }

    private function parseMetadata(): void
    {
        // Metadata is near the end of the file (last 128KB max)
        $readSize = min(131072, $this->fileSize);
        fseek($this->handle, $this->fileSize - $readSize);
        $tail = fread($this->handle, $readSize);

        $pos = strrpos($tail, self::METADATA_MARKER);
        if ($pos === false) {
            throw new \RuntimeException('Missing MMDB metadata marker');
        }

        $metadataOffset = ($this->fileSize - $readSize) + $pos + strlen(self::METADATA_MARKER);
        $offset = $metadataOffset;
        $metadata = $this->decodeData($offset);

        if (!is_array($metadata)) {
            throw new \RuntimeException('Invalid MMDB metadata structure');
        }

        $this->nodeCount = (int) ($metadata['node_count'] ?? 0);
        $this->recordSize = (int) ($metadata['record_size'] ?? 0);
        $this->ipVersion = (int) ($metadata['ip_version'] ?? 4);
        $this->databaseType = (string) ($metadata['database_type'] ?? '');

        if ($this->nodeCount <= 0 || !in_array($this->recordSize, [24, 28, 32], true)) {
            throw new \RuntimeException('Unsupported record size or node count in MMDB');
        }

        $this->nodeByteSize = (int) ($this->recordSize * 2 / 8);
        $this->searchTreeSize = $this->nodeCount * $this->nodeByteSize;
        $this->dataSectionOffset = $this->searchTreeSize + 16;
    }

    /**
     * Find raw data for an IP address.
     *
     * @param string $ip
     * @return mixed
     */
    public function lookup(string $ip): mixed
    {
        if (!$this->isValid || !$this->handle) {
            return null;
        }

        $rawBin = @inet_pton($ip);
        if ($rawBin === false) {
            return null;
        }

        // If IPv4 in an IPv6 tree, pad with 96 zero bits (::/96)
        if (strlen($rawBin) === 4 && $this->ipVersion === 6) {
            $rawBin = str_repeat("\x00", 12) . $rawBin;
        } elseif (strlen($rawBin) === 16 && $this->ipVersion === 4) {
            return null;
        }

        $bitCount = strlen($rawBin) * 8;
        $node = 0;

        for ($i = 0; $i < $bitCount; $i++) {
            $byte = ord($rawBin[(int) ($i / 8)]);
            $bit = ($byte >> (7 - ($i % 8))) & 1;

            $node = $this->readRecord($node, $bit);

            if ($node >= $this->nodeCount) {
                // Leaf or empty node reached
                break;
            }
        }

        if ($node <= $this->nodeCount) {
            return null; // Not found
        }

        // Pointer into data section
        $offset = $this->dataSectionOffset + ($node - $this->nodeCount - 16);
        return $this->decodeData($offset);
    }

    private function readRecord(int $nodeNumber, int $bit): int
    {
        $nodeOffset = $nodeNumber * $this->nodeByteSize;
        fseek($this->handle, $nodeOffset);
        $bytes = fread($this->handle, $this->nodeByteSize);

        if ($this->recordSize === 24) {
            // 6 bytes: left = bytes[0..2], right = bytes[3..5]
            if ($bit === 0) {
                return (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]);
            }
            return (ord($bytes[3]) << 16) | (ord($bytes[4]) << 8) | ord($bytes[5]);
        }

        if ($this->recordSize === 28) {
            // 7 bytes: byte 3 split between left and right
            if ($bit === 0) {
                return ((ord($bytes[3]) & 0xf0) << 20) | (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]);
            }
            return ((ord($bytes[3]) & 0x0f) << 24) | (ord($bytes[4]) << 16) | (ord($bytes[5]) << 8) | ord($bytes[6]);
        }

        if ($this->recordSize === 32) {
            // 8 bytes: left = bytes[0..3], right = bytes[4..7]
            if ($bit === 0) {
                $unpacked = unpack('N', substr($bytes, 0, 4));
                return (int) $unpacked[1];
            }
            $unpacked = unpack('N', substr($bytes, 4, 4));
            return (int) $unpacked[1];
        }

        throw new \RuntimeException('Unsupported MMDB record size');
    }

    private function decodeData(int &$offset): mixed
    {
        fseek($this->handle, $offset);
        $headerByte = ord(fread($this->handle, 1));
        $offset++;

        $type = $headerByte >> 5;
        $size = $headerByte & 0x1f;

        if ($type === 0) {
            // Extended type
            $extType = ord(fread($this->handle, 1));
            $offset++;
            $type = $extType + 7;
        }

        if ($type === 1) {
            // Pointer
            $ptrSize = ($size >> 3) & 0x03;
            $low = $size & 0x07;

            if ($ptrSize === 0) {
                $b = ord(fread($this->handle, 1));
                $offset += 1;
                $targetOffset = $this->dataSectionOffset + (($low << 8) | $b);
            } elseif ($ptrSize === 1) {
                $bytes = fread($this->handle, 2);
                $offset += 2;
                $targetOffset = $this->dataSectionOffset + 2048 + (($low << 16) | (ord($bytes[0]) << 8) | ord($bytes[1]));
            } elseif ($ptrSize === 2) {
                $bytes = fread($this->handle, 3);
                $offset += 3;
                $targetOffset = $this->dataSectionOffset + 526336 + (($low << 24) | (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]));
            } else {
                $bytes = fread($this->handle, 4);
                $offset += 4;
                $unpacked = unpack('N', $bytes);
                $targetOffset = $this->dataSectionOffset + $unpacked[1];
            }

            $savedOffset = $targetOffset;
            return $this->decodeData($savedOffset);
        }

        // Calculate size
        if ($size === 29) {
            $size = 29 + ord(fread($this->handle, 1));
            $offset += 1;
        } elseif ($size === 30) {
            $bytes = fread($this->handle, 2);
            $offset += 2;
            $size = 285 + ((ord($bytes[0]) << 8) | ord($bytes[1]));
        } elseif ($size === 31) {
            $bytes = fread($this->handle, 3);
            $offset += 3;
            $size = 65821 + ((ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]));
        }

        switch ($type) {
            case 2: // utf8_string
                $str = $size > 0 ? fread($this->handle, $size) : '';
                $offset += $size;
                return $str;

            case 3: // double
                $bytes = fread($this->handle, 8);
                $offset += 8;
                $unpacked = unpack('E', $bytes);
                return $unpacked[1] ?? 0.0;

            case 4: // bytes
                $bytes = $size > 0 ? fread($this->handle, $size) : '';
                $offset += $size;
                return $bytes;

            case 5: // uint16
            case 6: // uint32
            case 9: // uint64
            case 10: // uint128
                $val = 0;
                if ($size > 0) {
                    $raw = fread($this->handle, $size);
                    $offset += $size;
                    for ($i = 0; $i < $size; $i++) {
                        $val = ($val << 8) | ord($raw[$i]);
                    }
                }
                return $val;

            case 7: // map
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    $key = $this->decodeData($offset);
                    $val = $this->decodeData($offset);
                    if (is_scalar($key)) {
                        $map[(string) $key] = $val;
                    }
                }
                return $map;

            case 8: // int32
                $val = 0;
                if ($size > 0) {
                    $raw = fread($this->handle, $size);
                    $offset += $size;
                    for ($i = 0; $i < $size; $i++) {
                        $val = ($val << 8) | ord($raw[$i]);
                    }
                    if ($size < 4 && ($val & (1 << ($size * 8 - 1)))) {
                        $val -= (1 << ($size * 8));
                    }
                }
                return $val;

            case 11: // array
                $arr = [];
                for ($i = 0; $i < $size; $i++) {
                    $arr[] = $this->decodeData($offset);
                }
                return $arr;

            case 14: // boolean
                return $size !== 0;

            case 15: // float
                $bytes = fread($this->handle, 4);
                $offset += 4;
                $unpacked = unpack('G', $bytes);
                return $unpacked[1] ?? 0.0;

            default:
                return null;
        }
    }
}
