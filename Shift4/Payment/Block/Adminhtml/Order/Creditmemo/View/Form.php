<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Shift4\Payment\Block\Adminhtml\Order\Creditmemo\View;

use Shift4\Payment\Helper\PaymentInfo as PaymentInfo;

class Form extends \Magento\Sales\Block\Adminhtml\Order\Creditmemo\View\Form
{
    public function getChildHtml($alias = '', $useCache = true)
    {
        if ($alias != 'order_payment') {
            return parent::getChildHtml($alias, $useCache);
        } else {
            
            $order = $this->getOrder();
            $payment = $order->getPayment();
            $paymentInfo = new PaymentInfo();
            
            $returnHtml = $paymentInfo->generatePaymentInformationTable($payment, $this->getSource()->getInvoice());
            return $returnHtml;
        }
    }
}
