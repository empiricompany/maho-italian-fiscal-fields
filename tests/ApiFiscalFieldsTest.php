<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: AFL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

namespace MM\ItalianFiscalFields\Tests;

use Mage;
use Mage_Core_Model_App_Area;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the Italian fiscal fields (cod_fisc, email_pec, cod_sdi)
 * are exposed across every Maho API layer.
 *
 * The suite runs against the disposable SQLite store installed by
 * tests/run-with-test-db.php (composer test:db), which also seeds the
 * fixtures discovered here. Without that sandbox (plain "composer test",
 * e.g. in CI without a database) the whole class skips.
 *
 * Baseline expectations before the API implementation (see
 * plans/italian-fiscal-fields-api-implementation.md): the legacy v1 checks
 * and the api2 attribute check pass, the wsdl/wsi and API v2 extension
 * checks fail, and the standalone-address check documents a core gap.
 */
final class ApiFiscalFieldsTest extends TestCase
{
    private const FISCAL_FIELDS = ['cod_fisc', 'email_pec', 'cod_sdi'];

    private const WSDL_COMPLEX_TYPES = ['salesOrderAddressEntity', 'customerAddressEntityItem', 'customerAddressEntityCreate'];

    private static bool $moduleApiAutoloaderRegistered = false;

    private string $mahoRoot;

    private bool $bootstrapped = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mahoRoot = dirname(__DIR__);
        if (!is_file($this->mahoRoot . '/app/etc/local.xml')) {
            $this->markTestSkipped('Test database not installed: run "composer test:db" (tests/run-with-test-db.php)');
        }

        Mage::setRoot($this->mahoRoot);
        Mage::app('admin');
        // Register the api event area so #[Maho\Config\Observer(..., area: 'api')]
        // observers fire for the in-process dispatches below.
        Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_API, Mage_Core_Model_App_Area::PART_EVENTS);
        // The wsdl config is cached under the config cache: start from a clean slate.
        Mage::app()->getCache()->clean();
        $this->registerModuleApiAutoloader();
        $this->bootstrapped = true;
    }

    protected function tearDown(): void
    {
        if ($this->bootstrapped) {
            Mage::reset();
            restore_error_handler();
            restore_exception_handler();
        }

        parent::tearDown();
    }

    /**
     * The API Platform kernel registers a PSR-4 autoloader for the module
     * Api/ namespaces on every request (Kernel::boot()). Do the same here so
     * the Mage\<Module>\Api DTO classes are loadable in-process.
     */
    private function registerModuleApiAutoloader(): void
    {
        if (self::$moduleApiAutoloaderRegistered) {
            return;
        }
        $namespaces = \Maho\ApiPlatform\Discovery\ModuleApiDiscovery::discover()['namespaces'];
        spl_autoload_register(function (string $class) use ($namespaces): void {
            foreach ($namespaces as $prefix => $baseDir) {
                $prefixLen = strlen($prefix);
                if (strncmp($prefix, $class, $prefixLen) === 0) {
                    $file = $baseDir . '/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
                    if (file_exists($file)) {
                        require $file;
                        return;
                    }
                }
            }
        });
        self::$moduleApiAutoloaderRegistered = true;
    }

    /**
     * @return array{order_id: int, increment_id: string, expected: array<string, string>}
     */
    private function findSeededOrder(): array
    {
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $row = $read->fetchRow(
            "SELECT a.parent_id AS order_id, o.increment_id
            FROM {$resource->getTableName('sales/order_address')} a
            JOIN {$resource->getTableName('sales/order')} o ON o.entity_id = a.parent_id
            WHERE a.address_type = 'billing' AND a.cod_fisc IS NOT NULL AND a.cod_fisc <> ''
            ORDER BY a.entity_id DESC
            LIMIT 1",
        );
        self::assertIsArray($row, 'no order with fiscal fields found: did the seed run?');

        $order = Mage::getModel('sales/order')->load((int) $row['order_id']);
        $expected = [];
        foreach (self::FISCAL_FIELDS as $field) {
            $expected[$field] = (string) ($order->getBillingAddress()->getData($field) ?? '');
        }
        self::assertNotSame('', $expected['cod_fisc'], 'seeded order has an empty cod_fisc');

        return ['order_id' => (int) $row['order_id'], 'increment_id' => (string) $row['increment_id'], 'expected' => $expected];
    }

    /**
     * @return array{address_id: int, customer_id: int, expected: array<string, string>}
     */
    private function findSeededCustomerAddress(): array
    {
        $address = Mage::getModel('customer/address')
            ->getCollection()
            ->addAttributeToSelect(self::FISCAL_FIELDS)
            ->addAttributeToFilter('cod_fisc', ['notnull' => true])
            ->getFirstItem();
        self::assertGreaterThan(0, (int) $address->getId(), 'no customer address with fiscal fields found: did the seed run?');

        $expected = [];
        foreach (self::FISCAL_FIELDS as $field) {
            $expected[$field] = (string) ($address->getData($field) ?? '');
        }

        return ['address_id' => (int) $address->getId(), 'customer_id' => (int) $address->getCustomerId(), 'expected' => $expected];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $expected
     */
    private function assertFiscalFieldsMatch(array $payload, array $expected, string $context): void
    {
        foreach (self::FISCAL_FIELDS as $field) {
            self::assertArrayHasKey($field, $payload, "{$context}: {$field} missing");
            self::assertSame($expected[$field], (string) $payload[$field], "{$context}: {$field} mismatch");
        }
    }

    /**
     * A1: legacy v1 data layer, sales_order.info (SOAP v1 / XML-RPC / JSON-RPC).
     */
    public function testLegacyV1SalesOrderInfoExposesFiscalFields(): void
    {
        $seed = $this->findSeededOrder();
        $info = Mage::getModel('sales/order_api')->info($seed['increment_id']);

        self::assertArrayHasKey('billing_address', $info);
        $this->assertFiscalFieldsMatch($info['billing_address'], $seed['expected'], 'sales_order.info billing_address');
    }

    /**
     * A2: legacy v1 data layer, customer_address.info.
     */
    public function testLegacyV1CustomerAddressInfoExposesFiscalFields(): void
    {
        $seed = $this->findSeededCustomerAddress();
        $info = Mage::getModel('customer/address_api')->info($seed['address_id']);

        $this->assertFiscalFieldsMatch($info, $seed['expected'], 'customer_address.info');
    }

    /**
     * A3: legacy v1 write path, customer_address.update (original values restored).
     */
    public function testLegacyV1CustomerAddressUpdatePersistsFiscalFields(): void
    {
        $seed = $this->findSeededCustomerAddress();
        $addressApi = Mage::getModel('customer/address_api');

        try {
            $addressApi->update($seed['address_id'], [
                'cod_fisc' => 'TESTAPI0000000',
                'email_pec' => 'api-test@example-pec.it',
                'cod_sdi' => 'TESTSDI',
            ]);
            $reloaded = Mage::getModel('customer/address')->load($seed['address_id']);
            $this->assertFiscalFieldsMatch($reloaded->getData(), [
                'cod_fisc' => 'TESTAPI0000000',
                'email_pec' => 'api-test@example-pec.it',
                'cod_sdi' => 'TESTSDI',
            ], 'customer_address.update write');
        } finally {
            $addressApi->update($seed['address_id'], $seed['expected']);
            $restored = Mage::getModel('customer/address')->load($seed['address_id']);
            $this->assertFiscalFieldsMatch($restored->getData(), $seed['expected'], 'customer_address.update restore');
        }
    }

    /**
     * A4: SOAP v2 wsdl (non-WSI): fiscal elements merged into the complex types.
     */
    public function testSoapV2WsdlComplexTypesContainFiscalElements(): void
    {
        $this->assertComplexTypesContainFiscalElements(false);
    }

    /**
     * A5: WSI compliance mode: fiscal elements merged into the wsi complex types.
     */
    public function testWsiComplexTypesContainFiscalElements(): void
    {
        $this->assertComplexTypesContainFiscalElements(true);
    }

    private function assertComplexTypesContainFiscalElements(bool $wsi): void
    {
        Mage::getConfig()->setNode('default/api/config/compliance_wsi', $wsi ? '1' : '0');
        Mage::app()->removeCache(Mage::helper('api')->getCacheId());

        $wsdlConfig = Mage::getModel('api/wsdl_config');
        $wsdlConfig->setHandler('sales_order_api_v2');
        $wsdlConfig->init();
        $root = $wsdlConfig->getNode();
        self::assertInstanceOf(\SimpleXMLElement::class, $root, 'wsdl config returned no xml');

        foreach (self::WSDL_COMPLEX_TYPES as $complexType) {
            $nodes = $root->xpath("//*[local-name()='complexType'][@name='{$complexType}']");
            self::assertIsArray($nodes, "{$complexType}: complex type not found in " . ($wsi ? 'wsi.xml' : 'wsdl.xml'));
            self::assertNotEmpty($nodes, "{$complexType}: complex type not found in " . ($wsi ? 'wsi.xml' : 'wsdl.xml'));

            $names = [];
            foreach ($nodes[0]->xpath(".//*[local-name()='element']") ?: [] as $element) {
                $names[] = (string) $element['name'];
            }
            foreach (self::FISCAL_FIELDS as $field) {
                self::assertContains($field, $names, "{$complexType}: {$field} element missing");
            }
        }
    }

    /**
     * A6: legacy REST api2 customer_address: EAV attributes visible and not
     * excluded (api2 calls getEavAttributes($onlyVisible) with excludeSystem
     * left at false, so is_system is irrelevant: only visibility and the
     * api2.xml exclude lists gate the attribute).
     */
    public function testApi2CustomerAddressAttributesAreExposed(): void
    {
        $attributesByCode = [];
        foreach (Mage::getSingleton('eav/config')->getEntityType('customer_address')->getAttributeCollection() as $attribute) {
            $attributesByCode[$attribute->getAttributeCode()] = $attribute;
        }

        foreach (self::FISCAL_FIELDS as $field) {
            self::assertArrayHasKey($field, $attributesByCode, "{$field}: EAV attribute not found");
            self::assertTrue((bool) $attributesByCode[$field]->getIsVisible(), "{$field}: is_visible=0 hides it from the api2 customer role");
        }

        $excluded = $this->configNodeKeys(Mage::getConfig()->getNode('api2/resources/customer_address/exclude_attributes'));
        foreach (self::FISCAL_FIELDS as $field) {
            self::assertNotContains($field, $excluded, "{$field}: excluded in api2.xml");
        }
    }

    /**
     * @return list<string>
     */
    private function configNodeKeys(mixed $node): array
    {
        $keys = [];
        if ($node instanceof \SimpleXMLElement) {
            foreach ($node->children() as $key => $child) {
                $keys[] = (string) $key;
                $keys = [...$keys, ...$this->configNodeKeys($child)];
            }
        }
        return $keys;
    }

    /**
     * A7: API v2 order DTO: the api_order_dto_build observer fills
     * billingAddress->extensions with the fiscal fields.
     */
    public function testApiOrderDtoBuildEventFillsExtensions(): void
    {
        $seed = $this->findSeededOrder();
        $order = Mage::getModel('sales/order')->load($seed['order_id']);

        $orderDto = new \Mage\Sales\Api\Order();
        $orderDto->billingAddress = \Mage\Customer\Api\Address::fromOrderAddress($order->getBillingAddress());
        $shipping = $order->getShippingAddress();
        if ($shipping && $shipping->getId()) {
            $orderDto->shippingAddress = \Mage\Customer\Api\Address::fromOrderAddress($shipping);
        }
        Mage::dispatchEvent('api_order_dto_build', ['order' => $order, 'dto' => $orderDto]);

        $extension = $orderDto->billingAddress->extensions['italianFiscalFields'] ?? null;
        self::assertIsArray($extension, 'billingAddress->extensions.italianFiscalFields not populated');
        foreach (self::FISCAL_FIELDS as $field) {
            $camel = $this->camelCase($field);
            self::assertSame($seed['expected'][$field], (string) ($extension[$camel] ?? ''), "billingAddress extension {$camel} mismatch");
        }
    }

    /**
     * A8: API v2 customer DTO: the api_customer_dto_build observer fills
     * addresses[*]->extensions with the fiscal fields.
     */
    public function testApiCustomerDtoBuildEventFillsExtensions(): void
    {
        $seed = $this->findSeededCustomerAddress();
        $customer = Mage::getModel('customer/customer')->load($seed['customer_id']);

        $customerDto = new \Mage\Customer\Api\Customer();
        foreach ($customer->getAddresses() as $address) {
            $customerDto->addresses[] = \Mage\Customer\Api\Address::fromCustomerAddress($address, $customer);
        }
        Mage::dispatchEvent('api_customer_dto_build', ['customer' => $customer, 'dto' => $customerDto]);

        $target = null;
        foreach ($customerDto->addresses as $addressDto) {
            if ((int) $addressDto->id === $seed['address_id']) {
                $target = $addressDto;
                break;
            }
        }
        self::assertNotNull($target, 'seeded address not found in the customer DTO');

        $extension = $target->extensions['italianFiscalFields'] ?? null;
        self::assertIsArray($extension, 'addresses[*]->extensions.italianFiscalFields not populated');
        foreach (self::FISCAL_FIELDS as $field) {
            $camel = $this->camelCase($field);
            self::assertSame($seed['expected'][$field], (string) ($extension[$camel] ?? ''), "address extension {$camel} mismatch");
        }
    }

    /**
     * A9: API v2 standalone address DTO. Documents a known core gap:
     * AddressProvider::mapToDto() dispatches no api_address_dto_build event,
     * so /addresses/{id}, /addresses, /customers/me/addresses and the
     * GraphQL address queries cannot be extended by third-party modules.
     * Invert this assertion once the upstream PR lands.
     */
    public function testStandaloneAddressDtoHasNoExtensionHookYet(): void
    {
        $seed = $this->findSeededCustomerAddress();
        $customer = Mage::getModel('customer/customer')->load($seed['customer_id']);

        $addressDto = \Mage\Customer\Api\Address::fromCustomerAddress(
            Mage::getModel('customer/address')->load($seed['address_id']),
            $customer,
        );

        self::assertArrayNotHasKey(
            'italianFiscalFields',
            $addressDto->extensions,
            'extensions got populated: the core api_address_dto_build event must have landed, invert this test',
        );
    }

    private function camelCase(string $snake): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snake))));
    }
}
