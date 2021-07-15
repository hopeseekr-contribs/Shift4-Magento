<?php
/**
 * Shift4_Payment Magento payment component
 * php version 7
 *
 * @category  Shift4
 * @package   Shift4_Payment
 * @author    Shift4 <info@shift4.com>
 * @copyright 2021 Shift4 (https://www.shift4.com)
 * @license   http://opensource.org/licenses/osl-3.0.php OSL 3.0
 * @link      https://www.shift4.com
 */

namespace Shift4\Payment\Block;

class Form extends \Magento\Payment\Block\Form
{

    protected $_template = 'Shift4_Payment::cc-form.phtml';

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Shift4\Payment\Model\Api $shift4api,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Backend\Model\Session\Quote $sessionQuote,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Shift4\Payment\Helper\SavedCards $savedCardsHelper,
        \Magento\Catalog\Model\Product $productModel,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->sessionQuote = $sessionQuote;
        $this->productModel = $productModel;
        $this->shift4api = $shift4api;
        $this->authSession = $authSession;
        $this->checkoutSession = $checkoutSession;
        $this->savedCardsHelper = $savedCardsHelper;

        if ($this->authSession->isLoggedIn()) {
            $this->session = $authSession;
        } else {
            $this->session = $checkoutSession;
        }

        parent::__construct(
            $context,
            $data
        );
    }

    public function getCancelUrl()
    {
        return $this->getUrl('payment/CancelShift4Payments');
    }

    public function getHsaFsa()
    {
        $hsaFsaInfo = [
            'healthcareProducts' => [],
            'healthcareTotalAmount' => 0,
            'healthcareTotalAmountWithTax' => 0,
            'healthcareTotalAmountLeft' => 0,
            'healthcareTax' => 0
        ];

        $valueToTest = $this->scopeConfig
            ->getValue('payment/shift4/support_hsafsa', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($valueToTest == 1) {
            $healthcareProducts = [];
            if ($this->authSession->isLoggedIn()) {
                $products = $this->sessionQuote->getQuote()->getAllItems();
            } else {
                $products = $this->checkoutSession->getQuote()->getAllVisibleItems();
            }
            $healthcareTotalAmount = $healthcareTotalAmountWithTax = $healthcareTax = 0;
            foreach ($products as $product) {
                $attribute = $product->getProduct()->getResource()->getAttribute('iias_type');
                if (!$attribute) {
                    //skip if product has no attribute. On Magento 2.4 will crash on line
                    // "$attribute->getFrontend()" if that line is removed.
                    continue;
                }

                $productObject = $this->productModel->load($product->getProduct()->getId());
                $hsaFsa = $attribute->getFrontend()->getValue($productObject);
                if ($hsaFsa) {

                    $price = $product->getPrice();
                    $priceInclTax = $product->getPriceInclTax();
                    $qty = $product->getQty();

                    $healthcareProducts[] = [
                        'name' => $product->getProduct()->getName(),
                        'id' => $product->getProduct()->getId(),
                        'price' => $price,
                        'price_without_tax' => $product->getProduct()
                            ->getPriceInfo()->getPrice('final_price')->getAmount()->getBaseAmount(),
                        'quantity' => $qty,
                        'iias_type' => $attribute->getFrontend()->getValue($productObject),
                        'finalprice' => $priceInclTax,
                    ];
                    $healthcareTotalAmount += $qty * $price;
                    $healthcareTotalAmountWithTax += $qty * $priceInclTax;
                }
            }

            $processedAmountHsaFsa = (float) $this->session->getData('processedAmountHsaFsa');

            $hsaFsaInfo['healthcareProducts'] = $healthcareProducts;
            $hsaFsaInfo['healthcareTotalAmount'] = $healthcareTotalAmount;
            $hsaFsaInfo['healthcareTotalAmountWithTax'] = $healthcareTotalAmountWithTax;
            $hsaFsaInfo['healthcareTotalAmountLeft'] = $healthcareTotalAmountWithTax - $processedAmountHsaFsa;
            $healthcareTax = $healthcareTotalAmountWithTax - $healthcareTotalAmount;
            $hsaFsaInfo['healthcareTax'] = $healthcareTax;
        }

        return $hsaFsaInfo;
    }

    public function getPartialPayments()
    {

        $authorizedCardsData = (array) $this->session->getData('authorizedCardsData');

        foreach ($authorizedCardsData as $k => $v) {
            if ($v['voided']) {
                unset($authorizedCardsData[$k]);
            }
        }

        return $authorizedCardsData;
    }

    public function getAccessBlock()
    {
		
		if (!$this->authSession->isLoggedIn()) {
			$totals = $this->checkoutSession->getQuote()->getGrandTotal();
			$this->checkoutSession->getQuote()->reserveOrderId()->save();

			$i4goOptions = $this->shift4api->getAccessBlock(
				$totals,
				$this->checkoutSession->getQuote()->getReservedOrderId()
			);
		} else {
			$i4goOptions = $this->shift4api->getAccessBlock();
		}

        $i4goOptions['support_swipe'] = (
            $this->scopeConfig
                ->getValue(
                    'payment/shift4/support_swipe',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )? 'true' : 'false'
        );
        $i4goOptions['disable_expiration_date_for_gc'] = (
            $this->scopeConfig
                ->getValue(
                    'payment/shift4/disable_expiration_date_for_gc',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )? 'true' : 'false'
        );
        $i4goOptions['disable_cvv_for_gc'] = (
            $this->scopeConfig
                ->getValue(
                    'payment/shift4/disable_cvv_for_gc',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )? 'true' : 'false'
        );

        return $i4goOptions;
    }

    private function _getOrderCustomerId()
    {
        $customerId = 0;

        if ($this->authSession->isLoggedIn()) {
            $customerId = $this->sessionQuote->getCustomerId();
        } else {
            $customerId = $this->checkoutSession->getQuote()->getBillingAddress()->getCustomerId();
        }

        return $customerId;
    }

    public function getSavedCards()
    {
        $savedCardsData = $this->savedCardsHelper->getSavedCardsHTML($this->_getOrderCustomerId());
        return $savedCardsData;
    }
}
