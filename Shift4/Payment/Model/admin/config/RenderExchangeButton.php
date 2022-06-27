<?php

/**
 * Render Token exchange button in the system config section
 *
 * @category Shift4
 * @package  Payment
 * @author   Chetu Team
 */

namespace Shift4\Payment\Model\admin\config;

class RenderExchangeButton extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * Method for Set Element
     *
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return String
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        try {
            $this->setElement($element);

            $html = $element->getElementHtml();
            $html .= $this->getAfterElementHtml();

            return $html;
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());
        }
    }

    /**
     * Token exchange function and JavaScript for token exchange
     *
     * @param NULL
     *
     * @return string
     */
    public function getAfterElementHtml()
    {
        $url = $this->getUrl('payment/config/getAccessToken', []);
        $buttonScript = "<script>
		var exchangeAjaxUrl = '".$url."';
        </script> ";

        $html = parent::getAfterElementHtml();
        $url = $this->getBaseUrl() . 'shift4/payment/getAccessToken/';

        $html .= $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
            ->setType('button')
            ->setClass('scalable save')
            ->setLabel('Exchange')
            ->setId('shift4_exchange_tokens')
            ->toHtml();

        $html .= $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
            ->setType('button')
            ->setClass('scalable save cancel')
            ->setLabel('Cancel')
            ->setId('shift4_cancel_token_exchange')
            ->toHtml();

        return $html . $buttonScript;
    }
}
