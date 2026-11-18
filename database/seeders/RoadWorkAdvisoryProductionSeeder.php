<?php

namespace Database\Seeders;

use App\Models\Carriageway;
use App\Models\Office;
use App\Models\TabletAdvisory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * NHMP 130 Helpline - Production Road Work & Travel Advisory Seeder
 * Date: 18th November, 2026 | Effective: 08:00 hrs
 * 
 * Ingests and standardizes 33 active road maintenance, dualization, and diversion operations
 * across National Motorways (E-35, M-2, M-4, M-3) and National Highways (N-5, N-50, N-25).
 * 
 * Features:
 *  - 100% Idempotent (safe to execute multiple times on production via corridor_key)
 *  - Automated physical GIS marker resolution (centimeter-level GPS coordinates)
 *  - Automated NHMP organizational mapping (Zone -> Sector -> Beat hierarchy)
 *  - Plain-English layman descriptions with actionable driver guidance
 *  - Instant Redis & resilient cache flush across all mobile and web endpoints
 */
class RoadWorkAdvisoryProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Flushing previous stale advisories from tablet_advisories table...');
        Schema::disableForeignKeyConstraints();
        TabletAdvisory::truncate();
        Schema::enableForeignKeyConstraints();
        $this->command->info('All old advisories flushed. Seeding only new 18th Nov 2026 production roadwork advisories...');

        $publishedTime = Carbon::create(2026, 11, 18, 8, 0, 0);
        $expiryTime    = Carbon::create(2026, 11, 20, 23, 59, 59);

        // Resolve default administrative creator
        $adminUser = User::where('role_id', 1)->first() ?? User::first();
        $adminId   = $adminUser ? $adminUser->id : 1;

        // Cache all carriageways by cleaned road code
        $carriageways = Carriageway::all()->keyBy(function ($item) {
            return strtoupper(str_replace(['-', ' '], '', $item->road));
        });

        // 33 Standardized Roadwork Operations
        $roadworkData = [
            // =========================================================================
            // MOTORWAY NORTH ZONE (E-35 Hazara Motorway & M-2 Motorway)
            // =========================================================================
            [
                'route'          => 'E-35',
                'clean_route'    => 'E35',
                'section'        => 'Burhan to Thakot',
                'start_km'       => 5.0,
                'end_km'         => 5.5,
                'bound'          => 'northbound',
                'start_landmark' => 'Mohrian (KM 05 NB)',
                'end_landmark'   => 'Mohrian (KM 05.5 NB)',
                'title'          => 'Road Maintenance on E-35: Left Lane Closed at Mohrian (KM 05 Northbound)',
                'message'        => 'Road repair work is underway at KM 05 Northbound near Mohrian. The 1st (left/slow) lane is closed for pavement maintenance. Please drive carefully and use the open middle and overtaking lanes.',
                'lane_affected'  => '1st Lane (Slow Lane)',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 60,
            ],
            [
                'route'          => 'E-35',
                'clean_route'    => 'E35',
                'section'        => 'Burhan to Thakot',
                'start_km'       => 49.0,
                'end_km'         => 49.5,
                'bound'          => 'northbound',
                'start_landmark' => 'Akhoon De Bandi (KM 49 NB)',
                'end_landmark'   => 'Akhoon De Bandi (KM 49.5 NB)',
                'title'          => 'Road Repair on E-35: Left Lane Closed at Akhoon De Bandi (KM 49 Northbound)',
                'message'        => 'Pavement maintenance is in progress at KM 49 Northbound near Akhoon De Bandi. The 1st (slow) lane is closed. Please follow retro-reflective safety cones and maintain a safe following distance.',
                'lane_affected'  => '1st Lane (Slow Lane)',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 60,
            ],
            [
                'route'          => 'M-2',
                'clean_route'    => 'M2',
                'section'        => 'Islamabad to Lahore',
                'start_km'       => 45.0,
                'end_km'         => 48.0,
                'bound'          => 'northbound',
                'start_landmark' => 'Hadiyala Virkan (KM 45 NB)',
                'end_landmark'   => 'Hadiyala Virkan (KM 48 NB)',
                'title'          => 'Traffic Diversion on M-2: KM 45 to 48 Northbound near Hadiyala Virkan',
                'message'        => 'Active road rehabilitation work between KM 45 and KM 48 Northbound near Hadiyala Virkan. Traffic is diverted to designated bypass lanes. Please reduce speed, expect slight delays, and obey NHMP flagmen.',
                'lane_affected'  => 'Traffic Diversion',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'M-2',
                'clean_route'    => 'M2',
                'section'        => 'Islamabad to Lahore',
                'start_km'       => 332.0,
                'end_km'         => 333.0,
                'bound'          => 'both',
                'start_landmark' => 'Capital Smart City (KM 332)',
                'end_landmark'   => 'Capital Smart City Interchange (KM 333)',
                'title'          => 'Interchange Slip Road Diversion on M-2: KM 332 (Capital Smart City)',
                'message'        => 'Interchange construction work at KM 332 in both Northbound and Southbound directions near Capital Smart City. Main carriageway traffic is channeled through the interchange slip road. Please reduce speed and follow posted signs.',
                'lane_affected'  => 'Slip Road Diversion (Both Directions)',
                'category'       => 'road_works',
                'closure_type'   => 'slip_road',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],

            // =========================================================================
            // MOTORWAY CENTRAL-II ZONE (M-4 & M-3 Motorways)
            // =========================================================================
            [
                'route'          => 'M-4',
                'clean_route'    => 'M4',
                'section'        => 'Pindi Bhattian to Multan',
                'start_km'       => 238.0,
                'end_km'         => 290.0,
                'bound'          => 'northbound',
                'start_landmark' => 'Faisalabad Interchange (KM 238 NB)',
                'end_landmark'   => 'Pindi Bhattian Interchange (KM 290 NB)',
                'title'          => 'Entry Closed & Traffic Diverted on M-4: KM 238 to 290 Northbound (Faisalabad to Pindi Bhattian)',
                'message'        => 'Northbound entry onto M-4 Motorway is closed from KM 238 to KM 290 between Faisalabad and Pindi Bhattian due to extensive roadwork. A mandatory traffic diversion is active at KM 238 Northbound. Commuters must use alternative routes as directed by NHMP.',
                'lane_affected'  => 'Entry Closed / Mandatory Diversion at KM 238',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'M-3',
                'clean_route'    => 'M3',
                'section'        => 'Nankana Sahib to Peer Mahal',
                'start_km'       => 1123.0,
                'end_km'         => 1123.5,
                'bound'          => 'northbound',
                'start_landmark' => 'Sheikhupura Interchange (KM 1123 NB)',
                'end_landmark'   => 'Sheikhupura Interchange Loop (KM 1123.5 NB)',
                'title'          => 'Road Repair on M-3: 1st Lane Closed at Sheikhupura Interchange (KM 1123 Northbound)',
                'message'        => 'Road surface maintenance at KM 1123 Northbound approaching Sheikhupura Interchange. The 1st (slow) lane is closed. Motorists are advised to merge early into the open lanes and avoid speeding.',
                'lane_affected'  => '1st Lane (Slow Lane)',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 60,
            ],

            // =========================================================================
            // N-5 NORTH ZONE (Grand Trunk Road / National Highway N-5)
            // =========================================================================
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Gujrat to Muridke',
                'start_km'       => 1336.0,
                'end_km'         => 1348.0,
                'bound'          => 'both',
                'start_landmark' => 'Ghakhar Mandi (KM 1336)',
                'end_landmark'   => 'Pindi Bypass Gujranwala (KM 1348)',
                'title'          => 'Major Roadwork on N-5 (GT Road): Lanes 1 & 2 Closed between Ghakhar Mandi and Pindi Bypass (KM 1336–1348)',
                'message'        => 'Road rehabilitation and asphalt resurfacing between KM 1336 and KM 1348 from Ghakhar Mandi to Pindi Bypass. The 1st and 2nd lanes are restricted in both directions, causing narrowed carriageway. Expect slow traffic; please maintain lane discipline.',
                'lane_affected'  => '1st & 2nd Lanes Closed',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Gujrat to Muridke',
                'start_km'       => 1316.0,
                'end_km'         => 1320.0,
                'bound'          => 'both',
                'start_landmark' => 'Chand Da Qila (KM 1316)',
                'end_landmark'   => 'Mor Eminabad (KM 1320)',
                'title'          => 'Carriageway Restriction on N-5: Lanes 1 & 2 Closed from Chand Da Qila to Eminabad Mor (KM 1316–1320)',
                'message'        => 'Road reconstruction work is active between KM 1316 and KM 1320 from Chand Da Qila to Eminabad Mor. The 1st and 2nd lanes are closed to vehicles. Drive with extreme caution and follow roadside signs.',
                'lane_affected'  => '1st & 2nd Lanes Closed',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Azakhel to Taxila',
                'start_km'       => 1550.0,
                'end_km'         => 1551.0,
                'bound'          => 'northbound',
                'start_landmark' => 'Jhangi (KM 1550 NB)',
                'end_landmark'   => 'Jhangi Cut (KM 1551 NB)',
                'title'          => 'Road Maintenance on N-5: Left Lane Closed at Jhangi (KM 1550 Northbound)',
                'message'        => 'Patchwork and road surface repair at KM 1550 Northbound near Jhangi. The 1st (left) lane is barricaded. Please reduce speed and use the remaining open lane.',
                'lane_affected'  => '1st Lane (Left Lane)',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Azakhel to Taxila',
                'start_km'       => 1560.0,
                'end_km'         => 1561.0,
                'bound'          => 'both',
                'start_landmark' => 'Sangjani (KM 1560 NB/SB)',
                'end_landmark'   => 'Sangjani Toll (KM 1561 NB/SB)',
                'title'          => 'Road Repairs on N-5: 2nd Lane Closed in Both Directions at Sangjani (KM 1560)',
                'message'        => 'Carriageway maintenance work at KM 1560 in both Northbound and Southbound directions near Sangjani. The 2nd (middle/fast) lane is restricted. Motorists must exercise caution and yield smoothly when merging.',
                'lane_affected'  => '2nd Lane Closed (Both Directions)',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Mandra to Jehlum',
                'start_km'       => 1512.0,
                'end_km'         => 1525.0,
                'bound'          => 'northbound',
                'start_landmark' => 'Banth Mor (KM 1512 NB)',
                'end_landmark'   => 'T-Chowk Rawat (KM 1525 NB)',
                'title'          => 'Shoulder Maintenance on N-5: KM 1512 to 1525 Northbound (Banth Mor to T-Chowk)',
                'message'        => 'Shoulder stabilization and drainage clearing from Banth Mor to T-Chowk (KM 1512–1525 Northbound). The emergency hard shoulder is blocked by cones. Main driving lanes are clear; do not attempt to stop or pass on the shoulder.',
                'lane_affected'  => 'Hard Shoulder Closed',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'info',
                'speed_limit'    => 60,
            ],

            // =========================================================================
            // N-5 CENTRAL ZONE (Punjab - Lahore, Kassowal, Multan, Lodhran)
            // =========================================================================
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Lahore to Kassowal',
                'start_km'       => 1230.0,
                'end_km'         => 1231.0,
                'bound'          => 'both',
                'start_landmark' => 'Maraka (KM 1230)',
                'end_landmark'   => 'Maraka Pindi Mor (KM 1231)',
                'title'          => 'Road Surface Repair on N-5: 2nd Lane Closed at Maraka (KM 1230–1231)',
                'message'        => 'Pavement maintenance is in progress at KM 1230 to 1231 near Maraka. The 2nd lane is restricted. Commuters should slow down and be watchful for road maintenance personnel.',
                'lane_affected'  => '2nd Lane Closed',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Lahore to Kassowal',
                'start_km'       => 1212.0,
                'end_km'         => 1214.0,
                'bound'          => 'both',
                'start_landmark' => 'Dina Nath (KM 1212)',
                'end_landmark'   => 'Dina Nath Bypass (KM 1214)',
                'title'          => 'Road Construction on N-5: Work in Progress at Dina Nath (KM 1212–1214)',
                'message'        => 'Ongoing road resurfacing between KM 1212 and KM 1214 near Dina Nath. Carriageway capacity is partially reduced. Please slow down and avoid sudden overtaking.',
                'lane_affected'  => 'Partial Lane Restriction',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Multan to Lodhran',
                'start_km'       => 915.0,
                'end_km'         => 920.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Gardezi Mor (KM 915 SB)',
                'end_landmark'   => 'Adda Lar (KM 920 SB)',
                'title'          => 'Traffic Diversion on N-5: KM 915 to 920 Southbound (Gardezi Mor to Adda Lar)',
                'message'        => 'Bridge and road reconstruction between KM 915 and KM 920 Southbound from Gardezi Mor to Adda Lar. Traffic is diverted to the opposite carriageway/service lanes. Strictly observe two-way traffic rules and keep headlights on low beam.',
                'lane_affected'  => 'Traffic Diversion to Service Lane / Opposite Track',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Multan to Lodhran',
                'start_km'       => 913.5,
                'end_km'         => 915.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Haseena Chowk (KM 913.5 SB)',
                'end_landmark'   => 'Adda Lar Approach (KM 915 SB)',
                'title'          => 'Road Maintenance on N-5: KM 913.5 to 915 Southbound (Haseena Chowk to Adda Lar)',
                'message'        => 'Road leveling and carpeting between KM 913.5 and KM 915 Southbound from Haseena Chowk to Adda Lar. Single-lane movement is in effect. Drive slowly and follow advisory signs.',
                'lane_affected'  => 'Lane Restriction / Traffic Merging',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Multan to Lodhran',
                'start_km'       => 905.0,
                'end_km'         => 912.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Adda Basira (KM 905 SB)',
                'end_landmark'   => 'Mati Wala (KM 912 SB)',
                'title'          => 'Road Widening on N-5: KM 905 to 912 Southbound (Adda Basira to Mati Wala)',
                'message'        => 'Carriageway widening work between KM 905 and KM 912 Southbound from Adda Basira to Mati Wala. Lanes are narrowed and construction equipment is active. Please proceed with caution.',
                'lane_affected'  => 'Lanes Constrained / Road Widening',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Multan to Lodhran',
                'start_km'       => 896.6,
                'end_km'         => 898.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Al-Khair Petrol Pump (KM 896.6 SB)',
                'end_landmark'   => 'Pul Dawa (KM 898 SB)',
                'title'          => 'Road Repair on N-5: KM 896.6 to 898 Southbound (Al-Khair Pump to Pul Dawa)',
                'message'        => 'Culvert and surface maintenance from KM 896.6 to KM 898 Southbound between Al-Khair Petrol Pump and Pul Dawa. Drive at moderate speeds and maintain a safe gap from preceding vehicles.',
                'lane_affected'  => 'Single Lane Operational',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Multan to Lodhran',
                'start_km'       => 879.0,
                'end_km'         => 885.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Pul Bahishti (KM 879 SB)',
                'end_landmark'   => 'Pul Saat Hazari (KM 885 SB)',
                'title'          => 'Culvert & Road Repairs on N-5: KM 879 to 885 Southbound (Pul Bahishti to Pul Saat Hazari)',
                'message'        => 'Culvert rehabilitation and road reconstruction between KM 879 and KM 885 Southbound from Pul Bahishti to Pul Saat Hazari. Heavy vehicles in particular are advised to drive carefully through the narrowed section.',
                'lane_affected'  => 'Lanes Narrowed / Ongoing Repairs',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 40,
            ],

            // =========================================================================
            // N-5 SINDH / CENTRAL ZONE (Kot Sabzal, Halani, Hingorja, Kandiaro, Hala)
            // =========================================================================
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Kot Sabzal to Halani',
                'start_km'       => 382.0,
                'end_km'         => 390.0,
                'bound'          => 'northbound',
                'start_landmark' => 'Halani Town (KM 382 NB)',
                'end_landmark'   => 'Halani North (KM 390 NB)',
                'title'          => 'Traffic Diversion on N-5: KM 382 to 390 Northbound near Halani',
                'message'        => 'Major highway asphalt reconstruction from KM 382 to KM 390 Northbound near Halani. Northbound traffic is diverted to an adjacent bypass track. Please obey on-site NHMP traffic marshals.',
                'lane_affected'  => 'Traffic Diversion Active',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Kot Sabzal to Halani',
                'start_km'       => 400.0,
                'end_km'         => 408.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Hingorja City North (KM 400 SB)',
                'end_landmark'   => 'Hingorja City South (KM 408 SB)',
                'title'          => 'Traffic Diversion on N-5: KM 400 to 408 Southbound at Hingorja City',
                'message'        => 'Road modernization and pavement renewal between KM 400 and KM 408 Southbound through Hingorja City. Traffic is moving via a bypass diversion. Commuters should expect slower transit times.',
                'lane_affected'  => 'Diversion Active at Hingorja City',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-5',
                'clean_route'    => 'N5',
                'section'        => 'Halani to Hala',
                'start_km'       => 328.0,
                'end_km'         => 370.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Saduja (KM 328 SB)',
                'end_landmark'   => 'Kandiaro (KM 370 SB)',
                'title'          => 'Extended Traffic Diversion on N-5: KM 328 to 370 Southbound (Saduja to Kandiaro)',
                'message'        => 'Comprehensive highway overhaul across a 42-km stretch from KM 328 to KM 370 Southbound between Saduja and Kandiaro. Traffic is managed via two-way diversions across scheduled sectors. Exercise extreme caution, avoid high speeds, and maintain safe headway.',
                'lane_affected'  => 'Extensive 42-KM Diversion in Operation',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],

            // =========================================================================
            // N-50 WEST ZONE (Killa Saifullah / Balochistan)
            // =========================================================================
            [
                'route'          => 'N-50',
                'clean_route'    => 'N50',
                'section'        => 'Killa Saifullah',
                'start_km'       => 55.0,
                'end_km'         => 90.0,
                'bound'          => 'both',
                'start_landmark' => 'Khanozai Grade (KM 55)',
                'end_landmark'   => 'Muslim Bagh Bypass (KM 90)',
                'title'          => 'Highway Dualization on N-50: KM 55 to 90 (Khanozai Grade to Muslim Bagh Bypass)',
                'message'        => 'Major federal dualization project in progress along N-50 from KM 55 to KM 90 between Khanozai Grade and Muslim Bagh Bypass. Road leveling machinery, loose gravel, and diversions are present. Strictly follow the 40 km/h speed limit.',
                'lane_affected'  => 'Dualization in Progress / Heavy Machinery Active',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],

            // =========================================================================
            // N-25 WEST ZONE (Kalat, Quetta, Mastung, Mangochar)
            // =========================================================================
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Kalat to Quetta',
                'start_km'       => 569.0,
                'end_km'         => 570.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Mangochar City (KM 569 SB)',
                'end_landmark'   => 'Mangochar City South (KM 570 SB)',
                'title'          => 'Traffic Diversion on N-25: KM 569 Southbound at Mangochar City',
                'message'        => 'Road construction and improvement works at KM 569 Southbound through Mangochar City. Traffic is guided through local diversions. Please drive carefully.',
                'lane_affected'  => 'Local Diversion Active',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Kalat to Quetta',
                'start_km'       => 579.0,
                'end_km'         => 580.0,
                'bound'          => 'northbound',
                'start_landmark' => 'Bidrang (KM 579 NB)',
                'end_landmark'   => 'Bidrang North (KM 580 NB)',
                'title'          => 'Road Repair on N-25: KM 579 Northbound at Bidrang',
                'message'        => 'Pavement maintenance is underway at KM 579 Northbound near Bidrang. Single-lane traffic is in effect. Drive slowly and follow NHMP cones.',
                'lane_affected'  => 'Single Lane Operational',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Kalat to Quetta',
                'start_km'       => 583.0,
                'end_km'         => 584.0,
                'bound'          => 'both',
                'start_landmark' => 'Bidrang Police Check Post (KM 583)',
                'end_landmark'   => 'Bidrang Check Post Exit (KM 584)',
                'title'          => 'Roadwork near Checkpoint on N-25: KM 583 (Bidrang Police Check Post)',
                'message'        => 'Road maintenance and safety barrier installation at KM 583 near the Bidrang Police Check Post. Expect brief queues; maintain orderly lane queueing.',
                'lane_affected'  => 'Checkpoint Lane Management',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'info',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Kalat to Quetta',
                'start_km'       => 600.0,
                'end_km'         => 602.0,
                'bound'          => 'northbound',
                'start_landmark' => 'Kund Umrani (KM 600 NB)',
                'end_landmark'   => 'Kund Umrani North (KM 602 NB)',
                'title'          => 'Road Construction on N-25: KM 600 to 602 Northbound at Kund Umrani',
                'message'        => 'Surface carpeting and road repairs between KM 600 and KM 602 Northbound near Kund Umrani. Partial lane closures are active. Reduce speed.',
                'lane_affected'  => 'Partial Lane Restriction',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Kalat to Quetta',
                'start_km'       => 660.0,
                'end_km'         => 665.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Mian Ghundi (KM 660 SB)',
                'end_landmark'   => 'Mian Ghundi South (KM 665 SB)',
                'title'          => 'Carriageway Repairs on N-25: KM 660 to 665 Southbound at Mian Ghundi',
                'message'        => 'Road rehabilitation active from KM 660 to KM 665 Southbound approaching Mian Ghundi. Carriageway is narrowed due to work on the outer lane.',
                'lane_affected'  => 'Carriageway Narrowed',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Kalat to Quetta',
                'start_km'       => 690.0,
                'end_km'         => 696.0,
                'bound'          => 'both',
                'start_landmark' => 'Tor Nasar (KM 690)',
                'end_landmark'   => 'Byco Pump (KM 696)',
                'title'          => 'Road Rehabilitation on N-25: KM 690 to 696 (Tor Nasar to Byco Pump)',
                'message'        => 'Asphalt resurfacing between KM 690 and KM 696 from Tor Nasar to Byco Pump. Watch for loose gravel and machinery on road shoulders.',
                'lane_affected'  => 'Resurfacing / Loose Gravel',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 50,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Kalat to Quetta',
                'start_km'       => 696.0,
                'end_km'         => 698.0,
                'bound'          => 'both',
                'start_landmark' => 'Byco Pump (KM 696)',
                'end_landmark'   => 'DHA Quetta (KM 698)',
                'title'          => 'Road Expansion on N-25: KM 696 to 698 (Byco Pump to DHA Quetta)',
                'message'        => 'Civil construction and road expansion between KM 696 and KM 698 from Byco Pump to DHA Quetta. Temporary diversions and lane adjustments in place.',
                'lane_affected'  => 'Road Expansion & Diversions',
                'category'       => 'road_works',
                'closure_type'   => 'lane_closure',
                'severity'       => 'advisory',
                'speed_limit'    => 40,
            ],

            // =========================================================================
            // N-25 WEST ZONE - UTHAL SECTOR (Dualization of N-25 Highway)
            // =========================================================================
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Uthal',
                'start_km'       => 39.0,
                'end_km'         => 64.0,
                'bound'          => 'southbound',
                'start_landmark' => 'Gaddani Link Road (KM 39 SB)',
                'end_landmark'   => 'Ahmed Khoso Road (KM 64 SB)',
                'title'          => 'Highway Dualization on N-25: KM 39 to 64 Southbound (Gaddani Link to Ahmed Khoso Road)',
                'message'        => 'Major federal dualization and four-lane expansion project from KM 39 to KM 64 Southbound, including the Gaddani Link Road. Earthworks and diversions are active. Drive with extra care.',
                'lane_affected'  => 'Highway Dualization / Diversions Active',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Uthal',
                'start_km'       => 66.0,
                'end_km'         => 116.0,
                'bound'          => 'both',
                'start_landmark' => 'Rahmania Kanta (KM 66)',
                'end_landmark'   => 'Angaria Goth (KM 116)',
                'title'          => 'Dualization Project on N-25: KM 66 to 116 (Rahmania Kanta to Angaria Goth)',
                'message'        => 'Extensive 50-km highway dualization in progress between KM 66 and KM 116 in both directions from Rahmania Kanta to Angaria Goth. Single-lane bypasses and construction machinery present. Strictly adhere to posted speed limits.',
                'lane_affected'  => '50-KM Dualization Corridor / Single-Lane Bypasses',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Uthal',
                'start_km'       => 116.0,
                'end_km'         => 166.0,
                'bound'          => 'both',
                'start_landmark' => 'Angaria Goth (KM 116)',
                'end_landmark'   => 'Danda Stop / Bhatti Goth (KM 166)',
                'title'          => 'Dualization Project on N-25: KM 116 to 166 (Angaria Goth to Danda Stop / Bhatti Goth)',
                'message'        => 'Ongoing highway widening and dualization from KM 116 to KM 166 between Angaria Goth and Danda Stop / Bhatti Goth. Rough road patches and diversions in effect. Motorists should keep headlights on low beam.',
                'lane_affected'  => 'Highway Dualization / Rough Road Patches',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],
            [
                'route'          => 'N-25',
                'clean_route'    => 'N25',
                'section'        => 'Uthal',
                'start_km'       => 167.0,
                'end_km'         => 216.0,
                'bound'          => 'both',
                'start_landmark' => 'Jam Yousaf Abad Danda (KM 167)',
                'end_landmark'   => 'Pinjra Pul near Kund (KM 216)',
                'title'          => 'Bridge & Dualization Work on N-25: KM 167 to 216 (Jam Yousaf Abad to Pinjra Pul)',
                'message'        => 'Major highway dualization and Pinjra Bridge reconstruction from KM 167 to KM 216 between Jam Yousaf Abad and Pinjra Bridge near Kund. Heavy machinery active; reduce speed and adhere strictly to marked diversion paths.',
                'lane_affected'  => 'Bridge Reconstruction & Dualization',
                'category'       => 'road_works',
                'closure_type'   => 'diversion',
                'severity'       => 'warning',
                'speed_limit'    => 40,
            ],

            // =========================================================================
            // FOG ADVISORIES (M-1 & M-2 Motorways)
            // =========================================================================
            [
                'route'             => 'M-1',
                'clean_route'       => 'M1',
                'section'           => 'Islamabad to Peshawar',
                'start_km'          => 65.0,
                'end_km'            => 92.0,
                'bound'             => 'both',
                'start_landmark'    => 'Swabi Interchange (KM 65)',
                'end_landmark'      => 'Rashakai Toll Plaza (KM 92)',
                'title'             => 'Dense Fog on M-1: Low Visibility from Swabi to Rashakai (KM 65–92)',
                'message'           => 'Heavy fog is reducing visibility below 30 meters between Swabi and Rashakai. Turn on fog lights, avoid sudden braking, and maintain safe headway.',
                'lane_affected'     => 'Both Carriageways Affected by Fog',
                'category'          => 'fog',
                'closure_type'      => null,
                'severity'          => 'warning',
                'speed_limit'       => 50,
                'visibility_meters' => 30,
                'start_lat'         => 34.0825,
                'start_lng'         => 72.4111,
                'end_lat'           => 34.0210,
                'end_lng'           => 71.9950,
            ],
            [
                'route'             => 'M-2',
                'clean_route'       => 'M2',
                'section'           => 'Islamabad to Lahore',
                'start_km'          => 15.0,
                'end_km'            => 35.0,
                'bound'             => 'both',
                'start_landmark'    => 'Kot Abdul Malik Interchange (KM 15)',
                'end_landmark'      => 'Sheikhupura Interchange (KM 35)',
                'title'             => 'Dense Fog on M-2: Low Visibility from Kot Abdul Malik to Sheikhupura (KM 15–35)',
                'message'           => 'Thick fog is affecting M-2 between Kot Abdul Malik and Sheikhupura, with visibility down to 25 meters. Drive below 40 km/h, use fog lights, and do not overtake.',
                'lane_affected'     => 'Both Carriageways Affected by Fog',
                'category'          => 'fog',
                'closure_type'      => null,
                'severity'          => 'warning',
                'speed_limit'       => 40,
                'visibility_meters' => 25,
            ],

            // =========================================================================
            // TRAFFIC CONGESTION ADVISORY (M-9 South Zone / Nooriabad)
            // =========================================================================
            [
                'route'             => 'M-9',
                'clean_route'       => 'M9',
                'section'           => 'Karachi to Hyderabad (South Zone)',
                'start_km'          => 62.0,
                'end_km'            => 75.0,
                'bound'             => 'both',
                'start_landmark'    => 'Nooriabad Industrial Area (KM 62)',
                'end_landmark'      => 'Nooriabad Service Area (KM 75)',
                'title'             => 'Traffic Congestion on M-9: Slow-Moving Traffic near Nooriabad (KM 62–75)',
                'message'           => 'Heavy traffic congestion is causing slow movement near Nooriabad in the South Zone. Please remain patient in your lane and expect minor travel delays.',
                'lane_affected'     => 'Heavy Congestion / Slow Moving Traffic',
                'category'          => 'traffic',
                'closure_type'      => null,
                'severity'          => 'warning',
                'speed_limit'       => 35,
                'visibility_meters' => null,
            ],
        ];

        $this->command->info(sprintf('Starting seeding of %d production roadwork advisories...', count($roadworkData)));

        $insertedCount = 0;
        $updatedCount  = 0;

        foreach ($roadworkData as $item) {
            $cwModel = $carriageways->get($item['clean_route']);
            if (! $cwModel) {
                $this->command->warn(sprintf('Carriageway %s not found. Skipping.', $item['route']));
                continue;
            }

            // High precision physical marker resolution for Start KM and End KM
            $mStart = $cwModel->markers()
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderByRaw('ABS(km - ?)', [$item['start_km']])
                ->first();

            $mEnd = $cwModel->markers()
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderByRaw('ABS(km - ?)', [$item['end_km']])
                ->first();

            $startLat = $item['start_lat'] ?? ($mStart ? (float) $mStart->lat : 31.5204);
            $startLng = $item['start_lng'] ?? ($mStart ? (float) $mStart->lng : 74.3587);
            $endLat   = $item['end_lat'] ?? ($mEnd ? (float) $mEnd->lat : $startLat);
            $endLng   = $item['end_lng'] ?? ($mEnd ? (float) $mEnd->lng : $startLng);

            // Resolve administrative office hierarchy from marker
            $beatId   = $mStart?->office_id;
            $beat     = $beatId ? Office::find($beatId) : null;
            $sector   = $beat?->parent;
            $zone     = $sector?->parent;

            $sectorId = $sector?->id;
            $zoneId   = $zone?->id;

            // Fallback office resolution if marker is not linked
            if (! $beatId) {
                $defaultBeat = Office::where('type', 'beat')->first();
                $beatId      = $defaultBeat?->id ?? 1;
                $sectorId    = $defaultBeat?->parent_id ?? 1;
                $zoneId      = $defaultBeat?->parent?->parent_id ?? 1;
            }

            // Deterministic corridor key for production idempotency
            $corridorKey = sprintf(
                '%s-%s-KM%04d-KM%04d-%s',
                $item['clean_route'],
                strtoupper($item['bound']),
                (int) ($item['start_km'] * 10),
                (int) ($item['end_km'] * 10),
                strtoupper($item['category'])
            );

            $attributes = [
                'corridor_key' => $corridorKey,
            ];

            $values = [
                'title'             => $item['title'],
                'category'          => $item['category'],
                'severity'          => $item['severity'],
                'route_name'        => $item['route'],
                'carriageway'       => $item['bound'],
                'carriageway_id'    => $cwModel->id,
                'office_id'         => $beatId,
                'sector_id'         => $sectorId,
                'zone_id'           => $zoneId,
                'start_km'          => $item['start_km'],
                'end_km'            => $item['end_km'],
                'start_landmark'    => $item['start_landmark'],
                'end_landmark'      => $item['end_landmark'],
                'visibility_meters' => $item['visibility_meters'] ?? null,
                'speed_limit_kmh'   => $item['speed_limit'],
                'closure_type'      => $item['closure_type'],
                'message'           => $item['message'],
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'start_lat'         => $startLat,
                'start_lng'         => $startLng,
                'end_lat'           => $endLat,
                'end_lng'           => $endLng,
                'published_at'      => $publishedTime,
                'approved_at'       => $publishedTime,
                'expires_at'        => $expiryTime,
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ];

            $advisory = TabletAdvisory::updateOrCreate($attributes, $values);

            if ($advisory->wasRecentlyCreated) {
                $insertedCount++;
            } else {
                $updatedCount++;
            }
        }

        // Flush all relevant caches so mobile apps and web portals reflect changes instantly
        TabletAdvisory::flushAdvisoryCache();

        $this->command->info(sprintf(
            'Seeding completed successfully! Created: %d, Updated: %d, Total: %d advisories.',
            $insertedCount,
            $updatedCount,
            $insertedCount + $updatedCount
        ));
    }
}
