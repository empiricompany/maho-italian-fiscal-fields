<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: AFL-3.0
 */

/**
 * Exposes the Italian fiscal fields (cod_fisc, email_pec, cod_sdi) on the
 * API v2 (REST + GraphQL) DTOs through the documented `api_*_dto_build`
 * events, filling the open `extensions` bag of the address DTOs.
 *
 * @see https://mahocommerce.com/api/v2/extending/
 */
class MM_ItalianFiscalFields_Model_Api_Observer
{
    /**
     * Order DTOs: /api/rest/v2/orders/{id}, GraphQL `order`.
     *
     * In collection (listing) mode the billing address is built from joined
     * `billing_*` columns and there is no address model to read from: the
     * core uses the same heuristic to skip heavy per-row loads.
     */
    #[Maho\Config\Observer('api_order_dto_build', area: 'api')]
    public function addFiscalFieldsToOrder(\Maho\Event\Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $dto = $observer->getEvent()->getDto();

        if ($order->getData('billing_telephone') !== null) {
            return; // batch/listing order: no address models available
        }

        $this->fill($dto->billingAddress ?? null, $order->getBillingAddress());
        $this->fill($dto->shippingAddress ?? null, $order->getShippingAddress());
    }

    /**
     * Customer DTOs: /api/rest/v2/customers/{id}, GraphQL `customer`.
     */
    #[Maho\Config\Observer('api_customer_dto_build', area: 'api')]
    public function addFiscalFieldsToCustomer(\Maho\Event\Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        $dto = $observer->getEvent()->getDto();

        $addressesById = [];
        foreach ($customer->getAddresses() as $address) {
            $addressesById[(int) $address->getId()] = $address;
        }

        foreach ($dto->addresses as $addressDto) {
            $this->fill($addressDto, $addressesById[(int) $addressDto->id] ?? null);
        }
    }

    /**
     * @param \Maho\DataObject|false|null $address (getShippingAddress() returns false for virtual orders)
     */
    private function fill(?\Maho\ApiPlatform\Resource $dto, \Maho\DataObject|false|null $address): void
    {
        if ($dto === null || !$address instanceof \Maho\DataObject || $address->getId() === null) {
            return;
        }

        $dto->extensions['italianFiscalFields'] = [
            'codFisc' => $address->getData('cod_fisc'),
            'emailPec' => $address->getData('email_pec'),
            'codSdi' => $address->getData('cod_sdi'),
        ];
    }
}
