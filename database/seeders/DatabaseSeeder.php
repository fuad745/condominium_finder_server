<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Condominium;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent seed data: 10 real Addis Ababa condominium areas
 * (coordinates checked against OpenStreetMap), a few starter blocks,
 * and an admin account. Safe to re-run.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCondominiums();
        $this->seedProjects();
        $this->seedBlocks();
        $this->seedAdmin();
    }

    /** Hexagonal ring around a center — seed stand-in for drawn areas. */
    private function hexPolygon(float $lat, float $lng, int $radiusMeters): array
    {
        $points = [];
        $latPerMeter = 1 / 111320;
        $lngPerMeter = 1 / (111320 * cos(deg2rad($lat)));
        for ($i = 0; $i < 6; $i++) {
            $points[] = [
                round($lat + $radiusMeters * sin($i * M_PI / 3) * $latPerMeter, 7),
                round($lng + $radiusMeters * cos($i * M_PI / 3) * $lngPerMeter, 7),
            ];
        }

        return $points;
    }

    private function seedCondominiums(): void
    {
        $rows = [
            // [id, name, area, lat, lng, footprintRadius]
            [1, 'Koye Feche Condominium',  'Koye Feche',  8.9033000, 38.8280000, 2200],
            [2, 'Tulu Dimtu Condominium',  'Tulu Dimtu',  8.8751000, 38.8211000, 900],
            [3, 'Bole Arabsa Condominium', 'Bole Arabsa', 8.9777000, 38.8877000, 1000],
            [4, 'CMC Condominium',         'CMC',         9.0198000, 38.8476000, 650],
            [5, 'Ayat Condominium',        'Ayat',        9.0212000, 38.8718000, 800],
            [6, 'Summit Condominium',      'Summit',      9.0130000, 38.8480000, 800],
            [7, 'Jemo Condominium',        'Jemo',        8.9600000, 38.7115000, 1100],
            [8, 'Gelan Gura Condominium',  'Gelan',       8.8801000, 38.7681000, 950],
        ];

        foreach ($rows as [$id, $name, $area, $lat, $lng, $radius]) {
            Condominium::query()->firstOrCreate(['id' => $id], [
                'name' => $name,
                'area_name' => $area,
                'lat' => $lat,
                'lng' => $lng,
                'polygon' => $this->hexPolygon($lat, $lng, $radius),
                'status' => 'approved',
            ]);
        }
    }

    private function seedProjects(): void
    {
        $rows = [
            // [id, condoId, name, area, lat, lng, radius, rangeStart, rangeEnd, total]
            [1,  1, 'Koye Feche Project 11', 'Koye Feche',  8.8921000, 38.8408000, 400, 1,   80,  80],
            [2,  1, 'Koye Feche Project 16', 'Koye Feche',  8.9033000, 38.8233000, 450, 400, 520, 121],
            [3,  1, 'Koye Feche Project 18', 'Koye Feche',  8.9145000, 38.8189000, 400, 1,   100, 100],
            [4,  2, 'Tulu Dimtu Project 1',  'Tulu Dimtu',  8.8751000, 38.8211000, 550, 1,   200, 200],
            [5,  3, 'Bole Arabsa Project 1', 'Bole Arabsa', 8.9777000, 38.8877000, 650, 1,   300, 300],
            [6,  4, 'CMC Project 1',         'CMC',         9.0198000, 38.8476000, 400, 1,   60,  60],
            [7,  5, 'Ayat Project 1',        'Ayat',        9.0212000, 38.8718000, 500, 1,   120, 120],
            [8,  6, 'Summit Project 1',      'Summit',      9.0130000, 38.8480000, 500, 1,   100, 100],
            [9,  7, 'Jemo Project 1',        'Jemo',        8.9600000, 38.7115000, 700, 1,   250, 250],
            [10, 8, 'Gelan Gura Project 1',  'Gelan',       8.8801000, 38.7681000, 600, 1,   180, 180],
        ];

        foreach ($rows as [$id, $condoId, $name, $area, $lat, $lng, $radius, $start, $end, $total]) {
            Project::query()->firstOrCreate(['id' => $id], [
                'condominium_id' => $condoId,
                'name' => $name,
                'area_name' => $area,
                'lat' => $lat,
                'lng' => $lng,
                'polygon' => $this->hexPolygon($lat, $lng, $radius),
                'radius_meters' => $radius,
                'width_meters' => $radius * 2,
                'height_meters' => $radius * 2,
                'block_range_start' => $start,
                'block_range_end' => $end,
                'total_blocks' => $total,
                'status' => 'approved',
            ]);
        }
    }

    private function seedBlocks(): void
    {
        $rows = [
            // Block 435 is an OSM-mapped building; the rest are approximate.
            [1, 2, '435', 8.9032800, 38.8233200, 'Mapped on OpenStreetMap',                    3, true],
            [2, 2, '440', 8.9040000, 38.8241000, 'Near Block 435, toward the expressway side', 1, false],
            [3, 2, '456', 8.9025000, 38.8222000, null,                                         0, false],
            [4, 4, '12',  8.8758000, 38.8203000, 'Close to the main gate',                     2, false],
            [5, 5, '78',  8.9784000, 38.8869000, 'Next to the water tank',                     1, false],
        ];

        foreach ($rows as [$id, $projectId, $number, $lat, $lng, $notes, $verified, $isVerified]) {
            Block::query()->firstOrCreate(['id' => $id], [
                'project_id' => $projectId,
                'block_number' => $number,
                'lat' => $lat,
                'lng' => $lng,
                'notes' => $notes,
                'verified_count' => $verified,
                'is_verified' => $isVerified,
                'status' => 'approved',
            ]);
        }
    }

    /**
     * Local SQLite gets a fixed dev admin (admin@local / admin123).
     * On a real server, set ADMIN_EMAIL + ADMIN_PASSWORD in .env before
     * the first boot and that account is created as admin instead.
     */
    private function seedAdmin(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $email = $sqlite ? 'admin@local' : env('ADMIN_EMAIL');
        $password = $sqlite ? 'admin123' : env('ADMIN_PASSWORD');

        if (! is_string($email) || $email === ''
            || ! is_string($password) || $password === '') {
            return;
        }

        $user = User::query()->firstOrCreate(['email' => $email], [
            'display_name' => $sqlite ? 'Local Admin' : 'Admin',
            'auth_provider' => 'email',
        ]);
        if ($user->password_hash === null) {
            $user->forceFill(['password_hash' => Hash::make($password)])->save();
        }
        if ($user->role !== 'admin') {
            $user->forceFill(['role' => 'admin', 'is_trusted' => true])->save();
        }
    }
}
