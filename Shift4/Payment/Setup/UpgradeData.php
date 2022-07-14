<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Shift4\Payment\Setup;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;

/**
 * Upgrade Data script
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{

    /**
     * EAV setup factory
     *
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * Init
     *
     * @param CategorySetupFactory $categorySetupFactory
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $maskCC = function ($number, $digits) {
            return preg_replace('/[0-9A-Z]/', 'X', substr($number, 0, $digits * -1)) . substr($number, $digits * -1);
        };

        $installer = $setup;

        $installer->startSetup();

        $connection = $installer->getConnection();
        $connection->addColumn(
            $installer->getTable('sales_order_payment'),
            'shift4_additional_information',
            [
                'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length'   => '64k',
                'nullable' => true,
                'comment'  => 'Shift4 Additional Information'
            ]
        );

        $installer->endSetup();

        if (version_compare($context->getVersion(), '1.1.18', '<')) {
            // Remove any old data first.
            $select = $connection
                ->select()
                ->from('core_config_data')
                ->where('path', 'payment/shift4/masked_access_token');

            $connection->deleteFromSelect($select, 'core_config_data');

            // Grab the access token and mask it.
            $accessTokenSelect = $connection->select()
                ->from('core_config_data', 'value')
                ->where('path = ?', 'payment/shift4/live_access_token');
            $maskedAccessToken = $maskCC($connection->fetchOne($accessTokenSelect), 6);

            // Insert the masked access token.
            $connection->insert('core_config_data', [
                'scope'    => 'default',
                'scope_id' => 0,
                'path'     => 'payment/shift4/masked_access_token',
                'value'    => $maskedAccessToken,
            ]);
        }
    }
}
