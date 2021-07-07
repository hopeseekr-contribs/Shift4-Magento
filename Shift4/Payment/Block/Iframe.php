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

use Magento\Framework\View\Element\Template;
use Shift4\Payment\Model\Api;

class Iframe extends Template
{

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $shift4api;
    protected $locale;
    protected $currencyFactory;

    /**
     * Return the list of customer's stored cards
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection|null
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Api $shift4api,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        array $data = []
    ) {
        $this->shift4api = $shift4api;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        parent::__construct(
            $context,
            $data
        );

        $this->scopeConfig = $scopeConfig;
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
        $currency = $this->currencyFactory->create()->load($currencyCode);
        $this->_symbol = $currency->getCurrencySymbol();
        $this->locale = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getAccessBlock()
    {
        $i4goOptions = $this->shift4api->getAccessBlock();

        $i4goOptions['support_swipe'] = (
            $this->scopeConfig->getValue(
                'payment/shift4/support_swipe',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) ? 'true' : 'false'
        );

        $i4goOptions['disable_expiration_date_for_gc'] = (
            $this->scopeConfig->getValue(
                'payment/shift4/disable_expiration_date_for_gc',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) ? 'true' : 'false'
        );

        $i4goOptions['disable_cvv_for_gc'] = (
            $this->scopeConfig->getValue(
                'payment/shift4/disable_cvv_for_gc',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) ? 'true' : 'false'
        );

        return $i4goOptions;
    }
}
