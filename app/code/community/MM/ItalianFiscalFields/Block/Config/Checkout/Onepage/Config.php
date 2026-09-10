<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

/**
 * PHP→JS bridge for the onepage/onestep checkout billing form
 * (checkout/onepage/billing.phtml), rendered on the checkout_onepage_index
 * handle.
 */
class MM_ItalianFiscalFields_Block_Config_Checkout_Onepage_Config extends MM_ItalianFiscalFields_Block_Config_Abstract
{
    #[\Override]
    public function getForms(): array
    {
        return [
            [
                'formId' => 'co-billing-form',
                'countryId' => 'billing:country_id',
                'companyId' => 'billing:company',
                'vatId' => 'billing:vat_id',
                'prefix' => 'billing',
            ],
        ];
    }
}
