<?php

/**
 * Render reset button for the server addresses
 *
 * @category Shift4
 * @package  Payment
 * @author   Chetu Team
 */

namespace Shift4\Payment\Model\admin\config;

class RenderResetButton extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * Method for Set Element
     *
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return String
     */
    protected $_server_addresses = 'https://utgapi.shift4test.com/api/rest/v1/';

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        try {
            $this->setElement($element);

            $html = $element->getElementHtml();
            $html .= $this->getAfterElementHtml();

            return $html;
        } catch (Exception $e) {
            $this->_logger->critical($e->getMessage());
        }
    }

    /**
     * Add the required Script for the reset button
     *
     * @return string
     */
    public function getAfterElementHtml()
    {
        $enteredUrl = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/shift4/server_addresses');
        $buttonScript = "<script>
                        var entered_url = '" . $enteredUrl . "';
                        var live_url = 'https://utg.shift4api.net/api/rest/v1/';
                        var demo_url = 'https://utgapi.shift4test.com/api/rest/v1/';
                        var server_url = '';
                        if(entered_url != live_url){
                            server_url = entered_url;
                        } else {
                            server_url = live_url;
                        }
			function resetServerAddresses() {
				var confirmText = 'If you confirm this reset, the Server Addresses field will be populated with the default server address: https://utg.shift4api.net/api/rest/v1. Are you sure you want to reset the Server Addresses field? In addition, for this reset to take effect, you must click Save Config on the Payment Methods page.';
				if (confirm(confirmText)) {
					$('payment_us_shift4_section_server_addresses').setValue(live_url);
				}
			}
		</script>";
        $html = parent::getAfterElementHtml();

        $html .= $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
            ->setType('button')
            ->setClass('scalable save')
            ->setLabel('Reset')
            ->setId('shift4_reset_server_address')
            ->setOnClick("javascript:resetServerAddresses()")
            ->toHtml();

        return $html . $buttonScript;
    }
}
