<?php

namespace Shift4\Payment\Exception;

use Magento\Framework\Phrase;

class PartialPaymentException extends \Magento\Framework\Exception\LocalizedException
{
    /**
     * @var \Magento\Framework\Phrase
     */
    protected $phrase;
    
    public function __construct($partialAuthData, Phrase $phrase, \Exception $cause = null, $code = 0)
    {
        $this->phrase = $phrase;
        
        $partialPaymentData = $partialAuthData['preauthInvoiceId'].';'.
        $partialAuthData['cardCount'].';'.
        $partialAuthData['cardType'].';'.
        $partialAuthData['preauthCardNumber'].';'.
        $partialAuthData['preauthProcessedAmount'].';'.
        $partialAuthData['preauthAuthCode'].';'.
        $partialAuthData['remainingAmount'];

        
        parent::__construct(__('Partial payment: '. $partialPaymentData .'|'. $phrase->render()), $cause, (int)$code);
    }
}
