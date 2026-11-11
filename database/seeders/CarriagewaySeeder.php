<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CarriagewaySeeder extends Seeder
{
    public function run(): void
    {
        $carriageways = [
            ['type' => 'Motorway', 'road' => 'M1', 'road_short' => 'M-1', 'road_name' => 'Motorway M-1', 'road_from' => 'Islamabad', 'road_to' => 'Peshawar', 'total_km' => 155.00],
            ['type' => 'Motorway', 'road' => 'M2', 'road_short' => 'M-2', 'road_name' => 'Motorway M-2', 'road_from' => 'Lahore', 'road_to' => 'Islamabad', 'total_km' => 367.00],
            ['type' => 'Highway', 'road' => 'N5', 'road_short' => 'N-5', 'road_name' => 'National Highway N-5 (Grand Trunk Road)', 'road_from' => 'Karachi', 'road_to' => 'Peshawar', 'total_km' => 1691.00],
            ['type' => 'Motorway', 'road' => 'E35', 'road_short' => 'E-35', 'road_name' => 'Hazara Motorway', 'road_from' => 'Hassan Abdal', 'road_to' => 'Thakot', 'total_km' => 182.00],
            ['type' => 'Motorway', 'road' => 'M14', 'road_short' => 'M-14', 'road_name' => 'Hakla-D.I Khan Motorway', 'road_from' => 'Hakla', 'road_to' => 'D.I Khan', 'total_km' => 285.00],
            ['type' => 'Motorway', 'road' => 'M4', 'road_short' => 'M-4', 'road_name' => 'Faisalabad-Multan Motorway', 'road_from' => 'Pindi Bhattian', 'road_to' => 'Multan', 'total_km' => 309.00],
            ['type' => 'Motorway', 'road' => 'M5', 'road_short' => 'M-5', 'road_name' => 'Multan-Sukkur Motorway', 'road_from' => 'Multan', 'road_to' => 'Sukkur', 'total_km' => 392.00],
            ['type' => 'Motorway', 'road' => 'E75', 'road_short' => 'E-75', 'road_name' => 'Islamabad-Murree Expressway (E-75)', 'road_from' => 'Islamabad', 'road_to' => 'Murree', 'total_km' => 42.00],
            ['type' => 'Highway', 'road' => 'N75', 'road_short' => 'N-75', 'road_name' => 'Islamabad-Murree Dual Carriageway (N-75 / IMDC)', 'road_from' => 'Islamabad', 'road_to' => 'Murree', 'total_km' => 42.00],
            ['type' => 'Highway', 'road' => 'N25', 'road_short' => 'N-25', 'road_name' => 'National Highway N-25', 'road_from' => 'Karachi', 'road_to' => 'Chaman', 'total_km' => 813.00],
            ['type' => 'Highway', 'road' => 'N65', 'road_short' => 'N-65', 'road_name' => 'National Highway N-65', 'road_from' => 'Sukkur', 'road_to' => 'Quetta', 'total_km' => 385.00],
            ['type' => 'Highway', 'road' => 'N70', 'road_short' => 'N-70', 'road_name' => 'National Highway N-70', 'road_from' => 'Multan', 'road_to' => 'Qila Saifullah', 'total_km' => 447.00],
            ['type' => 'Highway', 'road' => 'N50', 'road_short' => 'N-50', 'road_name' => 'National Highway N-50', 'road_from' => 'Kuchlak', 'road_to' => 'D.I Khan', 'total_km' => 531.00],
            ['type' => 'Strategic Route', 'road' => 'S1', 'road_short' => 'S-1', 'road_name' => 'Strategic Route S-1', 'road_from' => 'Burhan', 'road_to' => 'Thakot', 'total_km' => 174.00],
            ['type' => 'Motorway', 'road' => 'M3', 'road_short' => 'M-3', 'road_name' => 'Motorway M-3', 'road_from' => 'Lahore', 'road_to' => 'Abdul Hakim', 'total_km' => 230.00],
            ['type' => 'Highway', 'road' => 'N55', 'road_short' => 'N-55', 'road_name' => 'Indus Highway N-55', 'road_from' => 'Jamshoro (Kotri)', 'road_to' => 'Ratodero', 'total_km' => 328.00],
            ['type' => 'Motorway', 'road' => 'M9', 'road_short' => 'M-9', 'road_name' => 'Motorway M-9', 'road_from' => 'Karachi', 'road_to' => 'Hyderabad', 'total_km' => 136.00],
            ['type' => 'Highway', 'road' => 'N-10', 'road_short' => 'N-10', 'road_name' => 'Makran Coastal Highway N-10', 'road_from' => 'Zero Point (Liari)', 'road_to' => 'Gwadar', 'total_km' => 653.00],
            ['type' => 'Motorway', 'road' => 'M11', 'road_short' => 'M-11', 'road_name' => 'Lahore-Sialkot Motorway', 'road_from' => 'Lahore', 'road_to' => 'Sialkot', 'total_km' => 103.00],
            ['type' => 'Motorway', 'road' => 'M16', 'road_short' => 'M-16', 'road_name' => 'Swat Expressway', 'road_from' => 'Colonel Sher Khan', 'road_to' => 'Chakdara', 'total_km' => 160.00],
            ['type' => 'Motorway', 'road' => 'LEW', 'road_short' => 'LEW', 'road_name' => 'Lyari Expressway', 'road_from' => 'Mauripur (Karachi Port)', 'road_to' => 'Sohrab Goth (M-9)', 'total_km' => 16.50],
            ['type' => 'Motorway', 'road' => 'LEBP', 'road_short' => 'LEBP', 'road_name' => 'Lahore Eastern Bypass', 'road_from' => 'Kala Shah Kaku (M-11)', 'road_to' => 'Mehmood Booti (Ring Road)', 'total_km' => 16.50],
        ];

        foreach ($carriageways as $data) {
            DB::table('carriageways')->updateOrInsert(
                ['type' => $data['type'], 'road' => $data['road']],
                array_merge($data, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        $count = count($carriageways);
        $this->command->info("Seeded $count unique carriageways.");
    }
}
