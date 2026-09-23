<?php

declare(strict_types=1);

use App\Services\Concerns\GuardsOutstandingQuantity;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->harness = new class
    {
        use GuardsOutstandingQuantity;

        public function check(object $item, int $incomingQty, string $verb = 'receive', int $itemId = 1): void
        {
            $this->assertWithinOutstanding($item, $incomingQty, $verb, $itemId);
        }
    };
});

function outstandingStub(int $outstanding): object
{
    return new class($outstanding)
    {
        public function __construct(private int $outstanding) {}

        public function outstandingBaseQty(): int
        {
            return $this->outstanding;
        }
    };
}

describe('GuardsOutstandingQuantity', function () {

    it('throws_when_incoming_exceeds_outstanding', function () {
        $item = outstandingStub(70);

        expect(fn () => $this->harness->check($item, 71, 'receive', 5))
            ->toThrow(Exception::class, 'only 70 units remain outstanding');
    });

    it('allows_incoming_equal_to_outstanding', function () {
        $item = outstandingStub(70);

        $this->harness->check($item, 70, 'receive', 5);

        expect(true)->toBeTrue();
    });

});
