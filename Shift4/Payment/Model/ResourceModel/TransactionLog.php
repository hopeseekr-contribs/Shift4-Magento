<?php

/**
 * Shift4_Payment module dependency
 *
 * @category    Payment
 * @package     Shift4_Payment
 * @author      Chetu
 * @copyright   Shift4 Payment (https://www.shift4.com)
 */

namespace Shift4\Payment\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use \PDO;

class TransactionLog extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    private $dbPdo;

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('shift4_transactions', 'shift4_transaction_id');
    }



    public function getTransactions($from, $to, $filterType, $showOrderStatuses, $orderStatuses = [], $transactionStatuses, $transactionTypes, $countTotal = false, $lmitFrom = 0, $limitTo = 20)
    {
        $orderColumns = [
            'entity_id',
            'base_grand_total',
            'status',
            'customer_firstname',
            'customer_lastname',
            'created_at',
        ];

        $sql = $this->getConnection()->select();

        $sql->from(['s4' => $this->getMainTable()])
                ->joinLeft(["o" => $this->getTable('sales_order')], "s4.order_id = o.increment_id", $orderColumns)
                ->joinLeft(["c" => $this->getTable('customer_entity')], "s4.customer_id = c.entity_id", ['firstname', 'lastname']);


        if ($filterType == 'order_date') {
            $sql->where("o.created_at >='".$this->convertDate($from)." 00:00:00' AND o.created_at <= '".$this->convertDate($to)." 23:59:59'");
            $sql->order(['o.created_at DESC', 's4.transaction_date']);
        } elseif ($filterType == 'shipping_date') {
            $sql->where("o.transaction_date>='".$this->convertDate($from)." 00:00:00' AND o.transaction_date<='".$this->convertDate($to)." 23:59:59'");
        } elseif ($filterType == 'timeout_date') {
            $sql->where("s4.transaction_date >= '".$this->convertDate($from)." 00:00:00' AND s4.transaction_date <= '".$this->convertDate($to)." 23:59:59' AND s4.timed_out = '1'");
            $sql->order('s4.transaction_date DESC');
        } else {
            $sql->where("s4.transaction_date >= '".$this->convertDate($from)." 00:00:00' AND s4.transaction_date <= '".$this->convertDate($to)." 23:59:59'");
            $sql->order('s4.transaction_date DESC');
        }

        if (!empty($transactionTypes)) {
            $transactionTypesString = '';
            foreach ($transactionTypes as $v) {
                if ($v == 'updates') {
                    $transactionTypesString .= "'manualauthorization', 'manualsale',";
                } elseif ($v == 'sale_capture') {
                    $transactionTypesString .= "'sale', 'capture',";
                } else {
                    $transactionTypesString .= "'".$this->escapeString($v)."',";
                }
            }
            $transactionTypesString = substr($transactionTypesString, 0, -1);
            $sql->where("s4.transaction_mode IN (".$transactionTypesString.")");
        }

        if ($showOrderStatuses) {
            if (!empty($orderStatuses)) {
                $orderStatusesString = '';
                foreach ($orderStatuses as $v) {
                    $orderStatusesString .= "'".$this->escapeString($v)."',";
                }
                $orderStatusesString = substr($orderStatusesString, 0, -1);
                $sql->where("o.status IN (".$orderStatusesString.")");
            } else {
                $sql->where("1=2"); //no order status selected and false query
            }
        }

        $transactionStatusesSQL = '';

        if (in_array('success', $transactionStatuses)) {
            $transactionStatusesSQL .= "s4.http_code = '200' OR ";
        } else {
            //$transactionStatusesSQL .= "s4.http_code != '200' OR ";
        }

        if (in_array('error', $transactionStatuses)) {
            $transactionStatusesSQL .= "s4.error != '' OR ";
        } else {
            $transactionStatusesSQL .= "s4.error = '' OR ";
        }

        if (in_array('timedout', $transactionStatuses)) {
            $transactionStatusesSQL .= "s4.timed_out = 1 OR ";
        } else {
            //$transactionStatusesSQL .= "s4.timed_out = 0 OR ";
        }

        if (in_array('voided', $transactionStatuses)) {
            $transactionStatusesSQL .= "s4.voided = 1 OR ";
        } else {
            $transactionStatusesSQL .= "s4.voided = 0 OR ";
        }

        if ($transactionStatusesSQL != '') {
            $transactionStatusesSQL = substr($transactionStatusesSQL, 0, -4);
            $sql->where($transactionStatusesSQL);
        }

        if (!$countTotal) {
            $sql->limit($limitTo, $lmitFrom);
            $transactions = $this->getConnection()->fetchAll($sql);
        } else {
            $transactionsForTotals = $this->getConnection()->fetchAll($sql);
            //count totals
            $totals = [];
            $totals['total_records'] = count($transactionsForTotals);
            foreach ($transactionsForTotals as $k => $v) {

                $amountProcessed = 0;

                $utgResponse = json_decode($v['utg_response']);
                if (json_last_error() == JSON_ERROR_NONE) {
					
					if (!property_exists($utgResponse->result[0], 'amount')) {
						$amountProcessed = 0;
					} else {
						$amountProcessed = (float) $utgResponse->result[0]->amount->total;
					}
                }

                $type = 'other';
                if ($v['transaction_mode'] == 'capture' || $v['transaction_mode'] == 'sale') {
                    $type = 'sale';
                }
                if ($v['transaction_mode'] == 'refund') {
                    $type = 'refund';
                }
                if ($v['transaction_mode'] == 'authorization') {
                    $type = 'authorization';
                }

                if ($v['error'] == '' && $v['voided'] == 0) {
					if (!isset($totals[$v['card_type']])) {
						$totals[$v['card_type']] = [];
					}
					
					if (!isset($totals[$v['card_type']][$type])) {
						$totals[$v['card_type']][$type] = [];
					}
					
					if (!isset($totals[$v['card_type']][$type]['total'])) {
						$totals[$v['card_type']][$type]['total'] = 0;
					}
					
					if (!isset($totals[$v['card_type']][$type]['count'])) {
						$totals[$v['card_type']][$type]['count'] = 0;
					}
					
					if (!isset($totals['totals'])) {
						$totals['totals'] = [];
					}
					
					if (!isset($totals['totals'][$type])) {
						$totals['totals'][$type] = [];
					}
					
					if (!isset($totals['totals'][$type]['total'])) {
						$totals['totals'][$type]['total'] = 0;
					}
					
					if (!isset($totals['totals'][$type]['count'])) {
						$totals['totals'][$type]['count'] = 0;
					}
					
                    $totals[$v['card_type']][$type]['total'] = (float) $totals[$v['card_type']][$type]['total'] + $amountProcessed;
                    $totals[$v['card_type']][$type]['count'] = (int) $totals[$v['card_type']][$type]['count'] + 1;
                    $totals['totals'][$type]['total'] = (float) $totals['totals'][$type]['total'] + $amountProcessed;
                    $totals['totals'][$type]['count'] = (int) $totals['totals'][$type]['count'] + 1;
                } elseif ($v['error'] != '' && $v['voided'] == 0) {
					
					if (!isset($totals['errors'])) {
						$totals['errors'] = [];
					}
					
					if (!isset($totals['errors'][$type])) {
						$totals['errors'][$type] = [];
					}
					
					if (!isset($totals['errors'][$type]['total'])) {
						$totals['errors'][$type]['total'] = 0;
					}
					
					if (!isset($totals['errors'][$type]['count'])) {
						$totals['errors'][$type]['count'] = 0;
					}

                    $totals['errors'][$type]['total'] = (float) $totals['errors'][$type]['total'] + $v['amount'];
                    $totals['errors'][$type]['count'] = (int) $totals['errors'][$type]['count'] + 1;
                }

            }
            $transactions = $totals;
        }

        //echo $sql->__toString(); die();

        return $transactions;
    }

    public function updateTransaction($shift4Invoice, $data)
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            $data,
            ['shift4_invoice = ?' => $shift4Invoice]
        );
    }

    public function getTransaction($transactionId)
    {
        $sql = $this->getConnection()
                    ->select()
                    ->from($this->getMainTable())
                    ->where("shift4_transaction_id=?", (int) $transactionId);

        $transaction = $this->getConnection()->fetchRow($sql);
        return $transaction;
    }

    public function getTransactionsByOrderId($orderId)
    {
        $orderColumns = [
            'entity_id',
            'base_grand_total',
            'status',
            'customer_firstname',
            'customer_lastname',
            'created_at',
        ];

        $sql = $this->getConnection()
                    ->select()
                    ->from(['s4' => $this->getMainTable()])
                    ->joinLeft(["o" => $this->getTable('sales_order')], "s4.order_id = o.increment_id", $orderColumns)
                    ->where("o.entity_id ='". (int) $orderId."'")
                    ->order('s4.transaction_date');

        $transactions = $this->getConnection()->fetchAll($sql);
        return $transactions;
    }

    public function getTransactionsByInvoiceId($invoiceId)
    {
        $orderColumns = [
            'entity_id',
            'base_grand_total',
            'status',
            'customer_firstname',
            'customer_lastname',
            'created_at',
        ];

        $sql = $this->getConnection()
                    ->select()
                    ->from(['s4' => $this->getMainTable()])
                    ->joinLeft(["o" => $this->getTable('sales_order')], "s4.order_id = o.increment_id", $orderColumns)
                    ->where("s4.invoice_id ='". $this->escapeString($invoiceId)."'")
                    ->order('s4.transaction_date');

        $transactions = $this->getConnection()->fetchAll($sql);
        return $transactions;
    }

    private function convertDate($date)
    {
        $date = explode("/", $date);
        if (!isset($date[0]) || !isset($date[1]) || !isset($date[2])) {
            throw new \Exception('Invalid date format.');
        }
        $year = (int) $date[2];
        $month = str_pad((int) $date[0], 2, 0, STR_PAD_LEFT);
        $day = str_pad((int) $date[1], 2, 0, STR_PAD_LEFT);

        return $year.'-'.$month.'-'.$day;
    }

    private function escapeString($string)
    {
        return preg_replace('/[^\w]/', '', $string);
    }

    public function getNextInvoiceId()
    {

        $sql = $this->getConnection()
                    ->select()
                    ->from($this->getTable('sales_invoice'), ['increment_id'])
                    ->order('increment_id DESC')
                    ->limit(1);

        $invoice = $this->getConnection()->fetchRow($sql);
        return str_pad(((int) $invoice['increment_id'] +1), 9, "0", STR_PAD_LEFT);
    }

    public function saveAllTransactions($transactions)
    {
        $this->getConnection()->insertMultiple($this->getMainTable(), $transactions);
    }
}
