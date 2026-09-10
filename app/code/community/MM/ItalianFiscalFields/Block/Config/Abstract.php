<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

/**
 * Base block for the PHP→JS bridge of address-fields.js.
 *
 * Emits a single inline <script> exposing window.MM_ItalianFiscalFieldsConfig, which
 * carries both the module settings with any already-saved fiscal values and
 * the target form(s) of the page. Concrete blocks only declare their forms
 * via getForms(); no template is involved.
 */
abstract class MM_ItalianFiscalFields_Block_Config_Abstract extends Mage_Core_Block_Text
{
    #[\Override]
    protected function _toHtml(): string
    {
        $data = $this->getFiscalConfig();
        $data['forms'] = $this->getForms();

        return '<script>window.MM_ItalianFiscalFieldsConfig = ' . Mage::helper('core')->jsonEncode($data) . ';</script>' . "\n";
    }

    /**
     * Module settings and any already-saved fiscal values.
     *
     * @return array<string, mixed>
     */
    public function getFiscalConfig(): array
    {
        /** @var MM_ItalianFiscalFields_Helper_Data $helper */
        $helper = Mage::helper('italianfiscalfields');
        $address = $this->getCurrentAddress();

        return [
            'codfisc_private_enabled' => $helper->showCodiceFiscaleForPrivate(),
            'default_customer_type' => $helper->getDefaultCustomerType(),
            'cod_fisc' => $address ? (string) $address->getCodFisc() : '',
            'email_pec' => $address ? (string) $address->getEmailPec() : '',
            'cod_sdi' => $address ? (string) $address->getCodSdi() : '',
            'vat_id' => $address ? (string) $address->getVatId() : '',
        ];
    }

    /**
     * Target forms of address-fields.js on the current page.
     *
     * @return array<int, array<string, string>>
     */
    abstract public function getForms(): array;

    /**
     * Resolve the address whose fiscal fields we may need to pre-fill.
     * Customer address edit form registers "current_address"; the onepage
     * checkout exposes the quote billing address.
     */
    private function getCurrentAddress(): ?Varien_Object
    {
        /** @var Mage_Customer_Model_Address|null $address */
        $address = Mage::registry('current_address');
        if ($address && $address->getId()) {
            return $address;
        }

        /** @var Mage_Sales_Model_Quote_Address $quoteAddress */
        $quoteAddress = Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress();
        if ($quoteAddress && $quoteAddress->getId()) {
            return $quoteAddress;
        }

        return null;
    }
}
