<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test berjalan di sqlite, sedangkan produksi MySQL yang membatasi nama indeks 64 karakter. Tanpa
 * pemeriksaan ini, migrasi lolos test tetapi gagal saat dijalankan di server.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_names_fit_the_mysql_limit(): void
    {
        $tooLong = collect(Schema::getTables())
            ->flatMap(fn (array $table): array => array_column(Schema::getIndexes($table['name']), 'name'))
            ->filter(fn (string $name): bool => strlen($name) > 64)
            ->values()
            ->all();

        $this->assertSame([], $tooLong);
    }
}
