<?php

namespace Tests\Unit;

use App\Support\MutationGroundApplicability;
use PHPUnit\Framework\TestCase;

class MutationGroundApplicabilityTest extends TestCase
{
    public function test_blue_blocks_orange_face_and_pale_headed(): void
    {
        $orange = MutationGroundApplicability::message(
            'Blue',
            'Blue',
            'Psittacin',
            'Orange Face',
            'Facial psittacin becomes orange.',
        );
        $pale = MutationGroundApplicability::message(
            'Blue',
            'Cobalt',
            'Incomplete dominant',
            'Pale Headed',
            'Alters facial psittacin. One Ph reduces the red mask.',
        );
        $paleDf = MutationGroundApplicability::message(
            'Blue',
            'Mauve',
            'Incomplete dominant',
            'Pale Headed DF',
            'Stronger facial effect than one Ph.',
        );

        $this->assertStringContainsString('Orange Face is not a visible mutation on Blue', (string) $orange);
        $this->assertStringContainsString('Pale Headed is not a visible mutation on Cobalt', (string) $pale);
        $this->assertStringContainsString('Mauve', (string) $paleDf);
    }

    public function test_ino_on_blue_and_orange_face_on_other_grounds_stay_available(): void
    {
        $this->assertNull(MutationGroundApplicability::message(
            'Blue',
            'Blue',
            'Sex-linked ino locus',
            'SL Ino',
            'Albino is this same allele on a blue ground, not a separate mutation.',
        ));
        $this->assertNull(MutationGroundApplicability::message(
            'Green',
            'Green',
            'Psittacin',
            'Orange Face',
            'Facial psittacin becomes orange.',
        ));
        $this->assertNull(MutationGroundApplicability::message(
            'Aqua',
            'Aqua',
            'Psittacin',
            'Orange Face',
            'Facial psittacin becomes orange.',
        ));
        $this->assertNull(MutationGroundApplicability::message(
            'Turquoise',
            'Turquoise',
            'Psittacin',
            'Orange Face',
            'Facial psittacin becomes orange.',
        ));
    }

    public function test_ino_covers_violet_and_pallid_does_not(): void
    {
        $slIno = (object) ['name' => 'SL Ino'];
        $nslIno = (object) ['name' => 'NSL Ino'];
        $violet = (object) ['name' => 'Violet'];
        $doubleViolet = (object) ['name' => 'Double Violet'];
        $pallid = (object) ['name' => 'Pallid'];

        $this->assertStringContainsString(
            'Violet is not a visible mutation with SL Ino',
            (string) MutationGroundApplicability::firstForMutations(null, [$slIno, $violet]),
        );
        $this->assertStringContainsString(
            'Double Violet is not a visible mutation with NSL Ino',
            (string) MutationGroundApplicability::firstForMutations(null, [$nslIno, $doubleViolet]),
        );
        $this->assertNull(MutationGroundApplicability::firstForMutations(null, [$pallid, $violet]));
    }
}
