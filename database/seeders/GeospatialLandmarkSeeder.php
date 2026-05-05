<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GeospatialLandmark;
use App\Models\Office;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GeospatialLandmarkSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Initializing Geospatial Landmark Synchronization...');
        
        $files = [
            'sheet_Motorway_North.json',
            'sheet_Motorway_Central_1.json',
            'sheet_Motorway_Central_2.json',
            'sheet_M_11.json',
            'sheet_M_14.json',
            'sheet_KKH.json',
            'sheet__North_Zone_.json',
            'sheet__South_Zone_.json',
            'sheet__Central_Zone_.json',
            'sheet___West_Zone_.json',
        ];

        GeospatialLandmark::truncate();

        foreach ($files as $file) {
            $path = base_path($file);
            if (!File::exists($path)) {
                $this->command->warn("File not found: {$file}");
                continue;
            }

            $this->command->info("Processing {$file}...");
            $data = json_decode(File::get($path), true);
            
            if (Str::contains($file, 'Motorway') || Str::contains($file, 'M_') || Str::contains($file, 'KKH')) {
                $this->parseMotorwayStyle($data, $file);
            } else {
                $this->parseHighwayStyle($data, $file);
            }
        }

        $this->command->info('Geospatial Synchronization Complete.');
    }

    private function parseMotorwayStyle(array $data, string $filename)
    {
        $currentBeat = null;
        $roadName = $this->extractRoadFromFilename($filename);
        
        foreach ($data as $row) {
            // Check for Beat identification
            $beatCandidate = null;
            foreach ($row as $key => $val) {
                if (Str::contains((string)$val, 'Beat')) {
                    $beatCandidate = trim(str_replace("\r\n", " ", $val));
                    break;
                }
            }

            if ($beatCandidate) {
                $currentBeat = $beatCandidate;
            }

            if (!$currentBeat) continue;

            // Extract Landmark info
            // Standard Motorway JSON structure:
            // __EMPTY: Location
            // __EMPTY_1: Mile Stone
            // __EMPTY_2: Near By Cities
            // __EMPTY_3: Fuel Station
            
            if (isset($row['__EMPTY']) && !Str::contains($row['__EMPTY'], 'Location')) {
                $this->createLandmark([
                    'road_name' => $roadName,
                    'beat_name' => $currentBeat,
                    'location_name' => $row['__EMPTY'],
                    'km_marker' => $row['__EMPTY_1'] ?? null,
                    'nearby_cities' => $row['__EMPTY_2'] ?? null,
                    'fuel_station' => $row['__EMPTY_3'] ?? null,
                ]);
            }

            // Secondary column set (for double-column layouts)
            if (isset($row['__EMPTY_5']) && Str::contains((string)$row['__EMPTY_5'], 'Beat')) {
                 $currentBeatSecondary = trim(str_replace("\r\n", " ", $row['__EMPTY_5']));
                 // This is a header or context change
            }

            if (isset($row['__EMPTY_6']) && !Str::contains($row['__EMPTY_6'], 'Location')) {
                $this->createLandmark([
                    'road_name' => $roadName,
                    'beat_name' => $currentBeatSecondary ?? $currentBeat,
                    'location_name' => $row['__EMPTY_6'],
                    'km_marker' => $row['__EMPTY_7'] ?? null,
                    'nearby_cities' => $row['__EMPTY_8'] ?? null,
                    'fuel_station' => $row['__EMPTY_9'] ?? null,
                ]);
            }
        }
    }

    private function parseHighwayStyle(array $data, string $filename)
    {
        $roadName = 'N-5'; // Default for most of these
        if (Str::contains($filename, 'West')) $roadName = 'N-25/N-10/N-50';
        
        foreach ($data as $row) {
            $beatInfo = null;
            foreach ($row as $key => $val) {
                // Heuristic: Beats in N-5 often start with a number like "01 Azakhel" or "25 - Dharki"
                if (preg_match('/^\d+[\s-]/', (string)$val)) {
                    $beatInfo = $val;
                    
                    // The other columns in this row are landmarks
                    foreach ($row as $landmarkKey => $landmarkVal) {
                        if ($landmarkKey === $key || $landmarkKey === 'MOTORWAY' || Str::contains($landmarkKey, 'ZONE')) continue;
                        if (!$landmarkVal) continue;

                        $this->createLandmark([
                            'road_name' => $roadName,
                            'beat_name' => $beatInfo,
                            'location_name' => $landmarkVal,
                        ]);
                    }
                }
            }
        }
    }

    private function createLandmark(array $params)
    {
        if (empty($params['location_name']) || strlen($params['location_name']) < 3) return;
        if (Str::contains($params['location_name'], 'Location')) return;

        // Clean up location name (might have KM in it)
        $location = trim(str_replace("\r\n", " ", $params['location_name']));
        $km = $params['km_marker'] ?? null;
        
        // If KM is in location like "1691 PS Chamkani"
        if (!$km && preg_match('/^(\d+)/', $location, $matches)) {
            $km = $matches[1];
        }

        // Try to find office_id
        $officeId = null;
        $beatClean = $this->cleanBeatName($params['beat_name']);
        
        // Match Beat in DB
        $office = Office::where('type', 'beat')
            ->where(function($q) use ($beatClean) {
                $q->where('name', 'LIKE', "%{$beatClean}%")
                  ->orWhere('name', 'LIKE', str_replace('Beat-', 'Beat ', $beatClean));
            })->first();
            
        if ($office) {
            $officeId = $office->id;
        }

        GeospatialLandmark::create([
            'office_id' => $officeId ?? 1, // Fallback to root or first if not found
            'road_name' => $params['road_name'],
            'beat_name' => $beatClean,
            'location_name' => $location,
            'km_marker' => (string)$km,
            'km_numeric' => is_numeric($km) ? (float)$km : null,
            'nearby_cities' => $params['nearby_cities'] ?? null,
            'fuel_station' => $params['fuel_station'] ?? null,
            'agent_prompt' => "Contextual alert: Operative should verify if caller is near " . $location . ($km ? " (KM {$km})" : ""),
            'contact_numbers' => $this->extractContactNumbers($params['beat_name']),
        ]);
    }

    private function extractContactNumbers(string $text): ?string
    {
        preg_match_all('/(0\d{2,4}-?\d{6,8}|03\d{2}\s?-?\d{7})/', str_replace(["\r\n", "\n"], " ", $text), $matches);
        if (!empty($matches[0])) {
            return implode(', ', $matches[0]);
        }
        return null;
    }

    private function extractRoadFromFilename(string $filename): string
    {
        if (Str::contains($filename, 'North')) return 'M-1';
        if (Str::contains($filename, 'Central_1')) return 'M-2 (South)';
        if (Str::contains($filename, 'Central_2')) return 'M-2 (North)';
        if (Str::contains($filename, 'M_11')) return 'M-11';
        if (Str::contains($filename, 'M_14')) return 'M-14';
        if (Str::contains($filename, 'KKH')) return 'N-35 (KKH)';
        return 'Motorway';
    }

    private function cleanBeatName(string $name): string
    {
        // "Beat-01\r\nM-1" -> "Beat-01"
        $name = head(explode("\r\n", $name));
        $name = head(explode(" ", $name));
        return trim($name);
    }
}
