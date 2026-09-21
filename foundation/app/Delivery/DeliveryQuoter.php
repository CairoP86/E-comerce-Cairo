<?php

namespace App\Delivery;

use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use App\Models\DeliveryZoneCanton;

/**
 * Flat rates per zone with a free-delivery threshold (owner decision, 2026-09-19). No courier or
 * external API is called. The free threshold is measured against the product subtotal, before delivery.
 */
class DeliveryQuoter
{
    public function quote(string $cantonCode, int $subtotalMinor, string $currency): DeliveryQuote
    {
        if ($currency !== 'CRC') {
            throw new DeliveryUnavailable('Por ahora solo calculamos envíos para pedidos en colones. Escríbenos para coordinar una compra en otra moneda.');
        }
        $zone = DeliveryZoneCanton::find($cantonCode)?->zone;
        if (! $zone) {
            throw new DeliveryUnavailable('Todavía no tenemos una tarifa de envío para ese cantón. Escríbenos y la coordinamos contigo.');
        }
        $set = $this->activeSet();
        $rate = DeliveryRate::where('delivery_rate_set_id', $set->id)->where('zone', $zone)->first();
        if (! $rate || $rate->currency !== 'CRC') {
            throw new DeliveryUnavailable('No pudimos calcular el envío para tu destino. Inténtalo de nuevo más tarde.');
        }
        // Free when the zone has no fee at all, or when the product subtotal reaches its threshold.
        $free = $rate->flat_minor === 0 || ($rate->free_from_minor !== null && $subtotalMinor >= $rate->free_from_minor);

        return new DeliveryQuote(
            zone: $zone,
            amountMinor: $free ? 0 : $rate->flat_minor,
            free: $free,
            freeFromMinor: $rate->free_from_minor,
            missingForFreeMinor: $free || $rate->free_from_minor === null ? null : $rate->free_from_minor - $subtotalMinor,
            currency: 'CRC',
            rateSetId: $set->id,
            rateSetVersion: $set->version,
        );
    }

    /** The newest rate set already in force. Scheduled future sets do not apply yet. */
    public function activeSet(): DeliveryRateSet
    {
        $set = DeliveryRateSet::where('effective_from', '<=', now())->orderByDesc('effective_from')->orderByDesc('id')->first();
        if (! $set) {
            throw new DeliveryUnavailable('El cálculo de envío no está configurado. Avísanos antes de continuar con tu pedido.');
        }

        return $set;
    }
}
