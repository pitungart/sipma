<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi master negara dari database/data/countries.php (ISO 3166-1 alpha-2, nama EN & ID).
 * Aman dijalankan berulang: nama diperbarui, kolom is_active tidak ditimpa.
 */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = collect(require database_path('data/countries.php'))
            ->map(fn (array $names, string $code): array => [
                'code' => $code,
                'name_en' => $names['name_en'],
                'name_id' => $names['name_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values();

        DB::transaction(function () use ($rows): void {
            $rows->chunk(100)->each(function ($chunk): void {
                Country::query()->upsert($chunk->all(), ['code'], ['name_en', 'name_id', 'updated_at']);
            });
        });

        $this->command?->info("Master negara: {$rows->count()} baris.");
    }
}
