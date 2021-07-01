<?php

namespace Shift4\Payment\Model;

use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\ChangeQuoteControlInterface;
use Shift4\Payment\Exception\PartialPaymentException;

class Shift4Quick extends \Shift4\Payment\Model\Shift4
{
    const CODE = 'shift4_quick';
    const MODULE_NAME = 'Shift4_Payment';

    protected $_code = self::CODE;

}
