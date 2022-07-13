<?php

namespace Shift4\Payment\Block\Checkout;

class Overview extends \Magento\Multishipping\Block\Checkout\Overview
{
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Multishipping\Model\Checkout\Type\Multishipping $multishipping,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Model\Quote\TotalsReader $totalsReader,
        \Magento\Framework\App\Request\Http $request,
        \Shift4\Payment\Model\SavedCards $savedCards,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $multishipping,
            $taxHelper,
            $priceCurrency,
            $totalsCollector,
            $totalsReader,
            $data
        );
        $this->request = $request;
        $this->savedCards = $savedCards;
        $this->checkoutSession = $checkoutSession;

		//workaround MG-90
		if (
			$this->getCheckoutData()->getAddressErrors() &&
			!empty($this->getCheckoutData()->getAddressErrors()) && 
			$this->checkoutSession->getData('shift4PostReloaded') != 1
		) {
			$shift4Post = $this->request->getParam('shift4');
			if (!empty($shift4Post) &&
				isset($shift4Post['trueToken']) &&
				isset($shift4Post['cardtype']) &&
				ctype_alnum($shift4Post['trueToken']) &&
				ctype_alnum($shift4Post['cardtype'])
			) {
				$this->checkoutSession->setData('shift4Post', $shift4Post);
			}
			$this->checkoutSession->setData('shift4PostReloaded', 1);

			header("refresh: 0;");
		}
		
		$this->checkoutSession->setData('shift4PostReloaded', 0);

    }

    /**
     * @return string
     */
    public function getPaymentHtml()
    {
        $shift4Post = $this->request->getParam('shift4');
		
		if (empty($shift4Post)) {
			$shift4Post = $this->checkoutSession->getData('shift4Post');
		}

        if (!empty($shift4Post) &&
            isset($shift4Post['trueToken']) &&
            isset($shift4Post['cardtype']) &&
            ctype_alnum($shift4Post['trueToken']) &&
            ctype_alnum($shift4Post['cardtype'])
        ) {
            $additionalHtml = '
			<input type="hidden" name="shift4truetoken" value="'. $shift4Post['trueToken'] .'">
			<strong>'. __('Card Type:') .'</strong> '. $shift4Post['cardtype'] . '<br>
			<strong>'. __('Card Number:') .'</strong> xxxx-'
                . $this->savedCards->getLast4FromToken($shift4Post['trueToken']);
        } else {
            $additionalHtml = '<span style="color:#ff0000">'. __('Error getting i4go TrueToken')
                . '</span> <a href="'.$this->getEditBillingUrl() .'">'. __('Please Back to Billing Information.')
                . '</a>';
        }
        return parent::getPaymentHtml() . $additionalHtml;
    }
}
