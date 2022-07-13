<?php

/**
 * Shift4 Payment controller for cancel all partial authorizations
 *
 * @category    Shift4
 * @package     Payment
 * @author    Giedrius
 */

namespace Shift4\Payment\Controller\Payment;

class geti4go extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $api;

    public function __construct(
		\Shift4\Payment\Model\Api $api,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Framework\Json\Helper\Data $json,
		\Magento\Framework\App\Action\Context $context
		)
    {
        $this->api = $api;
        $this->json = $json;
        $this->checkoutSession = $checkoutSession;
        parent::__construct(
            $context
        );
    }

    /**
     * cancel preauthorized amount.
     *
     * @return Int
     */
    public function execute()
    {
        $totals = $this->checkoutSession->getQuote()->getGrandTotal();
        $shipping = $this->checkoutSession->getQuote()->getShippingAddress()->getShippingAmount();

        $this->checkoutSession->getQuote()->reserveOrderId()->save();

		$processedAmount = (float) $this->checkoutSession->getData('processedAmount');

        //if partial authorization before.
        if ($processedAmount > 0) {
            $totals = $totals - $processedAmount;
        }

		$i4go = $this->api->getAccessBlock($totals, $this->checkoutSession->getQuote()->getReservedOrderId());

		$return = [
			'i4go_server' => $i4go['i4go_server'],
			'i4go_accessblock' => $i4go['i4go_accessblock'],
			'i4go_countrycode' => $i4go['i4go_countrycode'],
			'i4go_i4m_url' => $i4go['i4go_i4m_url'],
			'total' => $totals,
			'reservedOrderid' => $this->checkoutSession->getQuote()->getReservedOrderId(),
		];

		return $this->getResponse()->setBody(
            $this->json->jsonEncode($return)
        );
    }
}
