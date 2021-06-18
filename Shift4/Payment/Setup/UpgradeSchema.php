<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Shift4\Payment\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        /**
         * Create table 'shift4_saved_cards'
         */
        $setup->startSetup();
        $connection = $setup->getConnection();

        $customTableSavedCards = $connection->newTable($setup->getTable('shift4_saved_cards'))->addColumn('saved_card_id', Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true], 'Saved card Id')->addColumn('customer_id', Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false], 'Customer Id')->addColumn('cc_type', Table::TYPE_TEXT, 2, ['nullable' => false], 'CC Type')->addColumn('last_four', Table::TYPE_TEXT, 4, ['nullable' => false], 'CC last4')->addColumn('cc_exp_month', Table::TYPE_SMALLINT, 2, ['nullable' => false], 'CC exp month')->addColumn('cc_exp_year', Table::TYPE_TEXT, 4, ['nullable' => false], 'cc exp year')->addColumn('date', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT], 'Date')->addColumn('token', Table::TYPE_TEXT, 16, ['nullable' => false], 'Card token')->addColumn('is_default', Table::TYPE_SMALLINT, 1, ['nullable' => false], 'Is this card default')->setComment('Shift4 saved Cards');

        $setup->getConnection()->createTable($customTableSavedCards);

        /**
         * Create table 'shift4_transactions'
         */
        $customTableTransactions = $connection->newTable($setup->getTable('shift4_transactions'))->addColumn('shift4_transaction_id', Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true], 'Transaction ID')->addColumn('amount', Table::TYPE_DECIMAL, '12,4', ['unsigned' => true, 'nullable' => false], 'Amount')->addColumn('card_type', Table::TYPE_TEXT, 3, ['unsigned' => true], 'Card type')->addColumn('card_number', Table::TYPE_TEXT, 20, ['unsigned' => true], 'Card Number')->addColumn('shift4_invoice', Table::TYPE_TEXT, 10, ['nullable' => false], 'Shift4 Invoice Id')->addColumn('customer_id', Table::TYPE_INTEGER, null, ['nullable' => false, 'unsigned' => true], 'Customer ID')->addColumn('order_id', Table::TYPE_TEXT, 12, ['nullable' => false, 'unsigned' => true], 'Customer ID')->addColumn('invoice_id', Table::TYPE_TEXT, 12, ['nullable' => false, 'unsigned' => true], 'Customer ID')->addColumn('transaction_mode', Table::TYPE_TEXT, 30, ['nullable' => false], 'Transaction mode')->addColumn('transaction_date', Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Table::TIMESTAMP_INIT], 'Transaction Date')->addColumn('voided', Table::TYPE_BOOLEAN, 1, ['nullable' => false, 'unsigned' => true, 'default' => 0], 'Voided')->addColumn('error', Table::TYPE_TEXT, 255, ['nullable' => false, 'unsigned' => true, 'default' => 0], 'Error')->addColumn('timed_out', Table::TYPE_BOOLEAN, 1, ['nullable' => false, 'unsigned' => true, 'default' => 0], 'Timed out')->addColumn('request_header', Table::TYPE_TEXT, 65536, ['nullable' => false, 'default' => ''], 'Request Header')->addColumn('http_code', Table::TYPE_TEXT, 5, ['nullable' => false, 'default' => ''], 'Http code')->addColumn('utg_request', Table::TYPE_TEXT, 65536, ['nullable' => false], 'UTG request')->addColumn('utg_response', Table::TYPE_TEXT, 65536, ['nullable' => false], 'UTG response')->setComment('Shift4 Transactions Log');

        $setup->getConnection()->createTable($customTableTransactions);
        
        $oldSavedCardsTableName = $setup->getTable('shift4_customer_stored');
        $newSavedCardsTableName = $setup->getTable('shift4_saved_cards');
        
        $tableExists = $setup->getConnection()->fetchAll("SHOW TABLES LIKE '".$oldSavedCardsTableName."'");

        if ($tableExists && !empty($tableExists)) {
            
            $sql = $setup->getConnection()->select()->from($oldSavedCardsTableName);
            $oldSavedCards = $setup->getConnection()->fetchAll($sql);
            $insertData = [];

            foreach ($oldSavedCards as $oldCard) {
                $insertData[] = [
                    'customer_id' => $oldCard['customer_id'],
                    'cc_type' => $oldCard['cc_type'],
                    'last_four' => $oldCard['cc_last'],
                    'cc_exp_month' => $oldCard['cc_exp_month'],
                    'cc_exp_year' => $oldCard['cc_exp_year'],
                    'date' => $oldCard['date'],
                    'token' => $oldCard['card_token'],
                    'is_default' => 0
                ];
            }
            if (!empty($insertData)) {
                $setup->getConnection()->insertMultiple($newSavedCardsTableName, $insertData);
            }
            $setup->getConnection()->query("ALTER TABLE ".$oldSavedCardsTableName." RENAME ".$oldSavedCardsTableName.'_backup');
        }


        $setup->endSetup();
    }
}
