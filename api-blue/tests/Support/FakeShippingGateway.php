<?php

namespace Tests\Support;

use App\Interfaces\ShippingGatewayInterface;

/**
 * Checkout sekarang menanyakan ongkir ke gateway, jadi test tidak boleh
 * bergantung pada Komerce yang sungguhan. Harga default sengaja 15000 agar
 * cocok dengan angka yang sudah dipakai test-test checkout yang ada.
 */
class FakeShippingGateway implements ShippingGatewayInterface
{
    public function __construct(private int $cost = 15000) {}

    public function searchDestinations(string $keyword): array
    {
        return [];
    }

    public function calculateCosts(
        int $originId,
        int $destinationId,
        int $weightGrams,
        ?string $destinationCityName = null
    ): array {
        return [
            [
                'shipping_name' => 'JNE',
                'service_name' => 'REG',
                'shipping_cost_net' => $this->cost,
            ],
        ];
    }
}
