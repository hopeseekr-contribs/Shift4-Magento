<?php

namespace Shift4\Payment\Block\Checkout;

use Magento\Tests\NamingConvention\true\string;

class Results extends \Magento\Multishipping\Block\Checkout\Results
{
    /** @var \Magento\Checkout\Model\Session */
    private $checkoutSession;

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
     * Gets the Payment method for the last order.
     *
     * @return string Method.
     */
    private function getPaymentMethod()
    {
        return $this->checkoutSession
            ->getLastRealOrder()
            ->getPayment()
            ->getMethod();
    }

    /**
     * @param  string $name
     * @return string
     */
    public function getPaymentHtml($name)
    {
        $lastPaymentMethod = $this->getPaymentMethod();
        if ($lastPaymentMethod === 'shift4')
        {
            $html = parent::getPaymentHtml($name);
            $html = str_replace('multishipping/checkout/overview', 'multishipping/checkout/billing', $html);
            $html = str_replace('Review page in Checkout', 'Billing page', $html);
        }

        return $html;
    }
}
