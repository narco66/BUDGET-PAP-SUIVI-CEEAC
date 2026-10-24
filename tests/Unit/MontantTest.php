<?php

namespace Tests\Unit;

use App\Support\Montant;
use PHPUnit\Framework\TestCase;

class MontantTest extends TestCase
{
    public function test_serialise_une_chaine_a_deux_decimales(): void
    {
        $this->assertSame('1250000.50', Montant::api(1250000.5));
        $this->assertSame('0.00', Montant::api(null));
        $this->assertSame('10.000', Montant::api(10, 3));
    }
}
