<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

/**
 * PHP→JS bridge for the customer address edit form
 * (customer/address/edit.phtml), rendered on the customer_address_form handle.
 */
class MM_ItalianFiscalFields_Block_Config_Customer_Address_Config extends MM_ItalianFiscalFields_Block_Config_Abstract
{
    #[\Override]
    public function getForms(): array
    {
        return [
            [
                'formId' => 'form-validate',
                'countryId' => 'country',
                'companyId' => 'company',
                'vatId' => 'vat_id',
                'prefix' => '',
            ],
        ];
    }
}
