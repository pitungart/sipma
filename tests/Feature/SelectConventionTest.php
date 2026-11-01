<?php

namespace Tests\Feature;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Tests\TestCase;

/**
 * Semua select panel seragam dengan Kewarganegaraan: dropdown kustom, bukan <select> bawaan
 * (diatur global di AppServiceProvider). Kolom cari tetap dipasang per field.
 */
class SelectConventionTest extends TestCase
{
    public function test_selects_and_filters_are_not_native(): void
    {
        $this->assertFalse(Select::make('gender')->options(['f' => 'F', 'm' => 'M'])->isNative());
        $this->assertFalse(Select::make('gender')->isSearchable(), 'short lists have no search box');
        $this->assertFalse(SelectFilter::make('role')->isNative());
        $this->assertFalse(TernaryFilter::make('is_active')->isNative());
    }
}
