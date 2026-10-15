<?php

/**
 * SPDX-FileCopyrightText: 2026 Tony MageMEGA <https://magemega.com>
 * SPDX-License-Identifier: AFL-3.0
 * @package MM_ItalianFiscalFields
 */

declare(strict_types=1);

namespace MM\ItalianFiscalFields\Tests;

use Mage;
use Maho\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

/**
 * The fiscal fields must be registered exactly once per attribute, on the address
 * forms only, and the data install script must not leave eav_attribute rows with an
 * empty attribute_code behind.
 *
 * An earlier version read the attribute back through eav/config, whose per-store,
 * per-process index is built before the row exists and which returns an empty model
 * (not false) on a miss: the empty model was saved as a second attribute with a NULL
 * attribute_code, plus its customer_eav_attribute and customer_form_attribute rows.
 *
 * Runs against the disposable SQLite store installed by tests/run-with-test-db.php
 * (composer test:db); skipped without it.
 */
final class SetupFiscalAttributesTest extends TestCase
{
    private const FISCAL_FIELDS = ['cod_fisc', 'email_pec', 'cod_sdi'];

    private const ENTITY_TYPE = 'customer_address';

    private const USED_IN_FORMS = [
        'adminhtml_customer_address',
        'customer_address_edit',
        'customer_register_address',
    ];

    private string $mahoRoot = '';

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

    public function testEachFiscalFieldIsRegisteredExactlyOnce(): void
    {
        foreach (self::FISCAL_FIELDS as $code) {
            $rows = $this->attributeRows($code);

            self::assertCount(1, $rows, "{$code}: expected one eav_attribute row");
            self::assertSame($code, $rows[0]['attribute_code'], "{$code}: attribute_code");
            self::assertSame('varchar', $rows[0]['backend_type'], "{$code}: backend_type");
            self::assertSame('text', $rows[0]['frontend_input'], "{$code}: frontend_input");
            self::assertSame(1, (int) $rows[0]['is_visible'], "{$code}: is_visible");
            self::assertNotSame('', trim((string) $rows[0]['frontend_label']), "{$code}: frontend_label");
        }
    }

    public function testEachFiscalFieldIsUsedInTheAddressFormsOnly(): void
    {
        $expected = self::USED_IN_FORMS;
        sort($expected);

        foreach (self::FISCAL_FIELDS as $code) {
            $attributeId = (int) $this->attributeRows($code)[0]['attribute_id'];
            $forms = $this->connection()->fetchCol(
                sprintf(
                    'SELECT fa.form_code FROM %s fa WHERE fa.attribute_id = :attribute_id ORDER BY fa.form_code',
                    $this->table('customer/form_attribute'),
                ),
                ['attribute_id' => $attributeId],
            );

            self::assertSame($expected, $forms, "{$code}: used_in_forms");
        }
    }

    public function testEachFiscalFieldIsOnEveryCustomerAddressAttributeSet(): void
    {
        $adapter = $this->connection();
        $setIds = $adapter->fetchCol(
            sprintf(
                'SELECT eas.attribute_set_id FROM %s eas'
                . ' JOIN %s et ON et.entity_type_id = eas.entity_type_id'
                . ' WHERE et.entity_type_code = :entity_type_code ORDER BY eas.attribute_set_id',
                $this->table('eav/attribute_set'),
                $this->table('eav/entity_type'),
            ),
            ['entity_type_code' => self::ENTITY_TYPE],
        );
        self::assertNotEmpty($setIds, 'customer_address has no attribute set');

        foreach (self::FISCAL_FIELDS as $code) {
            $attributeId = (int) $this->attributeRows($code)[0]['attribute_id'];
            $assigned = $adapter->fetchCol(
                sprintf(
                    'SELECT attribute_set_id FROM %s WHERE attribute_id = :attribute_id ORDER BY attribute_set_id',
                    $this->table('eav/entity_attribute'),
                ),
                ['attribute_id' => $attributeId],
            );

            self::assertSame($setIds, $assigned, "{$code}: attribute sets");
        }
    }

    public function testNoCustomerAddressAttributeIsLeftWithAnEmptyCode(): void
    {
        $orphans = $this->connection()->fetchAll(
            sprintf(
                'SELECT ea.attribute_id, ea.entity_type_id FROM %s ea'
                . ' JOIN %s et ON et.entity_type_id = ea.entity_type_id'
                . " WHERE et.entity_type_code = :entity_type_code AND (ea.attribute_code IS NULL OR ea.attribute_code = '')",
                $this->table('eav/attribute'),
                $this->table('eav/entity_type'),
            ),
            ['entity_type_code' => self::ENTITY_TYPE],
        );

        self::assertSame([], $orphans, 'eav_attribute rows with an empty attribute_code');
    }

    public function testNoFormRowPointsToAnAttributeWithAnEmptyCode(): void
    {
        $orphans = $this->connection()->fetchAll(
            sprintf(
                'SELECT fa.attribute_id, fa.form_code FROM %s fa'
                . ' LEFT JOIN %s ea ON ea.attribute_id = fa.attribute_id'
                . " WHERE ea.attribute_id IS NULL OR ea.attribute_code IS NULL OR ea.attribute_code = ''",
                $this->table('customer/form_attribute'),
                $this->table('eav/attribute'),
            ),
        );

        self::assertSame([], $orphans, 'customer_form_attribute rows without a coded attribute');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attributeRows(string $code): array
    {
        return $this->connection()->fetchAll(
            sprintf(
                'SELECT ea.attribute_id, ea.attribute_code, ea.backend_type, ea.frontend_input, ea.frontend_label, ca.is_visible'
                . ' FROM %s ea'
                . ' JOIN %s et ON et.entity_type_id = ea.entity_type_id'
                . ' JOIN %s ca ON ca.attribute_id = ea.attribute_id'
                . ' WHERE et.entity_type_code = :entity_type_code AND ea.attribute_code = :attribute_code',
                $this->table('eav/attribute'),
                $this->table('eav/entity_type'),
                $this->table('customer/eav_attribute'),
            ),
            ['entity_type_code' => self::ENTITY_TYPE, 'attribute_code' => $code],
        );
    }

    private function table(string $alias): string
    {
        return Mage::getSingleton('core/resource')->getTableName($alias);
    }

    private function connection(): AdapterInterface
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        self::assertNotFalse($connection);

        return $connection;
    }
}
