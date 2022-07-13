<?php
namespace Shift4\Payment\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;

class I4goIframe
{
    
    public \Shift4\Payment\Model\Api $shift4api;
    public $api;
    protected $scopeConfig;
    
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Shift4\Payment\Model\Api $shift4api
    ) {
        
        $this->scopeConfig = $scopeConfig;
        $this->shift4api = $shift4api;
    }

    public function getI4goIframeJsCode($onSuccess, $block = '#i4go_form')
    {
        
        $i4go = $this->api->getAccessBlock();

        $i4go_server = $i4go['i4go_server'];
        $i4go_accessblock = $i4go['i4go_accessblock'];
        $i4go_countrycode = $i4go['i4go_countrycode'];
        $i4go_i4m_url = $i4go['i4go_i4m_url'];
        
        return '
		$("#'.$block.'").i4goTrueToken({
			server: "'. $i4go_server .'",
			accessBlock: "' . $i4go_accessblock .'",
			language: "' . $i4go_countrycode .'",
			self: document.location,
			template: "plain",
			i4goInfo: {visible: true},
			encryptedOnlySwipe: '. $this->scopeConfig->getValue('payment/shift4/support_swipe', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) .',
			gcDisablesExpiration: '. $this->scopeConfig->getValue('payment/shift4/disable_expiration_date_for_gc', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) .',
			gcDisablesCVV2Code: '. $this->scopeConfig->getValue('payment/shift4/disable_cvv_for_gc', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) .',
			url: "' . $i4go_i4m_url . '",
			frameContainer: "'. $block .'", 
			frameName: "",
			frameAutoResize: true,
			frameClasses: "",
			formAutoSubmitOnSuccess: false,
			formAutoSubmitOnFailure: false,
			onSuccess: function (form, data) {
				if (data.i4go_response == "SUCCESS" && data.i4go_responsecode == 1) {
					'.$onSuccess.'
				}
			},
			onFailure: function (form, data) {
				//
			},
			onComplete: function (form, data) {
				//
			},
			acceptedPayments: "AX,DC,GC,JC,MC,NS,VS",
			formPaymentResponse: "i4go_response",
			formPaymentResponseCode: "i4go_responsecode",
			formPaymentResponseText: "i4go_responsetext",
			formPaymentMaskedCard: "i4go_maskedcard",
			formPaymentToken: "i4go_uniqueid",
			formPaymentExpMonth: "i4go_expirationmonth",
			formPaymentExpYear: "i4go_expirationyear",
			formPaymentType: "i4go_cardtype",
			payments: [
				{type: "VS", name: "Visa"},
				{type: "MC", name: "MasterCard"},
				{type: "AX", name: "American Express"},
				{type: "DC", name: "Diners Club"},
				{type: "NS", name: "Discover"},
				{type: "JC", name: "JCB"},
				{type: "GC", name: "Gift Card"}
			],
			cssRules: ["body{font-family:\'Trebuchet MS\', Arial, Helvetica, sans-serif;background-color:\'#aaa\'; borderLeft:\'5px solid #ccc\'}label{color:#636363;font-size: 13px;font-weight: 600;}.form-control{max-width: 100%; width:273px;height: 30px;padding:0 8px; margin-bottom: 10px; background: #fff none repeat scroll 0 0;border: 1px solid silver; border-radius: 2px; font-size: 15px;}#i4go_expirationMonth {width: 125px;}#i4go_expirationYear {width: 105px;}.addcardform {height:auto;}#i4go_cvv2Code {width: 105px;}#i4go_cardNumber {width: 255px;}.btn-secure {background: #1979c3; border: 0 none;color: #ffffff;display: inline-block;font-family:\'RalewayHelvetica Neue\',Verdana,Arial,sans-serif;font-size: 13px;font-weight: normal;line-height: 19px;padding: 7px 15px;text-align: center;text-transform: uppercase;vertical-align: middle;white-space: nowrap;}.btn-secure:hover {background-color: #006bb4; color: #ffffff;outline: medium none; cursor: pointer;}"]
		});
		';
    }
}
