<?php

namespace Tests\Unit;

use App\Support\AvatarFocus;
use PHPUnit\Framework\TestCase;

class AvatarFocusTest extends TestCase
{
    public function test_without_focus_the_photo_is_simply_centered(): void
    {
        $this->assertSame('', AvatarFocus::style(null));
        $this->assertSame('', AvatarFocus::style(['x' => 0.5]));
    }

    public function test_portrait_photo_covers_the_circle_and_puts_the_focus_in_the_middle(): void
    {
        // Pas foto 3×4: lebar menutup lingkaran (100%), tinggi 133,3%; wajah di 45% dari atas
        $this->assertSame(
            'width:100.000%;height:133.333%;left:0.000%;top:-10.000%;object-fit:fill',
            AvatarFocus::style(['x' => 0.5, 'y' => 0.45, 'zoom' => 1, 'ratio' => 0.75]),
        );
    }

    public function test_position_never_leaves_a_gap_at_the_edges(): void
    {
        // Fokus di pojok kiri atas → foto berhenti di tepi, tidak bergeser lebih jauh
        $this->assertSame(
            'width:200.000%;height:200.000%;left:0.000%;top:0.000%;object-fit:fill',
            AvatarFocus::style(['x' => 0, 'y' => 0, 'zoom' => 2, 'ratio' => 1]),
        );
        $this->assertSame(
            'width:200.000%;height:200.000%;left:-100.000%;top:-100.000%;object-fit:fill',
            AvatarFocus::style(['x' => 1, 'y' => 1, 'zoom' => 2, 'ratio' => 1]),
        );
    }
}
