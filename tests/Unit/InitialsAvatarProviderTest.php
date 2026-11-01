<?php

namespace Tests\Unit;

use App\Filament\Support\InitialsAvatarProvider;
use PHPUnit\Framework\TestCase;

class InitialsAvatarProviderTest extends TestCase
{
    public function test_initials_take_the_first_two_words(): void
    {
        $this->assertSame('SL', InitialsAvatarProvider::initialsOf('sophie laurent dubois'));
        $this->assertSame('A', InitialsAvatarProvider::initialsOf('  Ayu '));
    }

    public function test_hue_is_stable_per_id_and_spread_across_ids(): void
    {
        $id = 'a31117c7-0239-4a5f-8122-b7651fb395f5';
        $this->assertSame(InitialsAvatarProvider::hueOf($id), InitialsAvatarProvider::hueOf($id));

        $hues = collect(range(1, 50))->map(fn (int $i): int => InitialsAvatarProvider::hueOf($i));
        $this->assertTrue($hues->every(fn (int $h): bool => $h >= 0 && $h < 360));
        $this->assertGreaterThan(40, $hues->unique()->count());
    }
}
