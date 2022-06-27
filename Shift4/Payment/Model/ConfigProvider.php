<?php

/**
 * Copyright � 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Shift4\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Shift4\Payment\Helper\SavedCards as SavedCards;

class ConfigProvider implements ConfigProviderInterface
{
     /**
      * @var \Magento\Framework\App\Config\ScopeConfigInterface
      */
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Shift4\Payment\Model\Api $api,
        \Shift4\Payment\Helper\SavedCards $savedCardsHelper,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->configWriter = $configWriter;
        $this->savedCardsHelper = $savedCardsHelper;
        $this->api = $api;
        $this->customerSession = $customerSession;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        
        $savedCardsData = ['html' => '', 'default' => 'new'];

        $healthcareProducts = (array) $this->checkoutSession->getData('healthcareProducts');

        $processedAmountHsaFsa = (float) $this->checkoutSession->getData('processedAmountHsaFsa');
        $healthcareTotalAmount = (float) $this->checkoutSession->getData('healthcareTotalAmountWithTax');

        $healthcareTotalAmount = $healthcareTotalAmount - $processedAmountHsaFsa;

        $authorizedCardsData = (array) $this->checkoutSession->getData('authorizedCardsData');

        foreach ($authorizedCardsData as $k => $v) {
            if ($v['voided']) {
                unset($authorizedCardsData[$k]);
            }
        }

        $guestUserData = [];
        if ($this->checkoutSession->getData('guestUserData')) {
            $guestUserData = $this->checkoutSession->getData('guestUserData');
            $guestUserData['processedAmount'] = $this->checkoutSession->getData('processedAmount');
            $guestUserData['guestEmail'] = $this->checkoutSession->getData('guestEmail');
        }

        $saved_cards_enabled = 0;

        $customerId = (int) $this->checkoutSession->getQuote()->getBillingAddress()->getCustomerId();

        if ($this->customerSession->isLoggedIn() && $this->scopeConfig->getValue(
            'payment/shift4/enable_saved_cards',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )
        ) {
            $saved_cards_enabled = 1;
            $savedCardsData = $this->savedCardsHelper->getSavedCardsHTML(
                $this->checkoutSession->getQuote()->getBillingAddress()->getCustomerId()
            );
        }

        $enableGPay = $this->scopeConfig->getValue(
            'payment/shift4/enable_google_pay',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $enableAPay = $this->scopeConfig->getValue(
            'payment/shift4/enable_apple_pay',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $template = 'default';
        
        if (!$enableGPay && !$enableAPay) {
            $template = 'no_quicpayments';
        } else {
            if (!$enableGPay) {
                $template = 'no_gp';
            }

            if (!$enableAPay) {
                $template = 'no_ap';
            }
        }

        return [
            'guest_user_data' => $guestUserData,
            'payment' => [
                'shift4_custom_data' => [
                    'support_swipe' => (
                        $this->scopeConfig->getValue(
                            'payment/shift4/support_swipe',
                            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                        ) ? true : false),
                    'submit_label' => $this->scopeConfig->getValue(
                        'payment/shift4/submit_label', \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                    'disable_expiration_date_for_gc' => ($this->scopeConfig->getValue(
                        'payment/shift4/disable_expiration_date_for_gc',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ) ? true : false
        ),
                    'disable_cvv_for_gc' => ($this->scopeConfig->getValue(
                        'payment/shift4/disable_cvv_for_gc',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ) ? true : false
        ),
                    'partial_payments' => $authorizedCardsData,
                    'saved_cards' => $savedCardsData['html'],
                    'saved_cards_enabled' => $saved_cards_enabled,
                    'healthcareProducts' => $healthcareProducts,
                    'healthcareTotalAmount' => $healthcareTotalAmount,
                    'default_card' => $savedCardsData['default'],
                    'processedAmountHsaFsa' => $processedAmountHsaFsa,
        'enableGPay' => $enableGPay,
        'enableAPay' => $enableAPay,
        'template' => $template
                ]
            ]
        ];
    }
}
