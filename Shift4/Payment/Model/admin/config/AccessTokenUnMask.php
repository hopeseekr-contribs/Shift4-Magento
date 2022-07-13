<?php

/*
 * Set the Access Token text box read only
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */

namespace Shift4\Payment\Model\admin\config;

class AccessTokenUnMask extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * Method for Set Element
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return String
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setElement($element);

        $html = $element->getElementHtml();
        $html .= $this->getAfterElementHtml();

        return $html;
    }

    /**
     * Add the required Script for the reset button
     *
     * @return string
     */
    public function getAfterElementHtml()
    {
        $buttonScript = "<script>
            function addNew() {
                if(confirm('Are you sure ?')){
                    $$(\"label[for='payment_us_shift4_section_shift4_auth_token']\").first().update('Auth Token*');
                    $('row_payment_us_shift4_section_shift4_auth_token').show();
                    $('payment_us_shift4_section_shift4_auth_token').setValue('');
                    $('row_payment_us_shift4_section_shift4_masked_access_token').hide();
                    jQuery('#row_payment_us_shift4_section_shift4_auth_token label').css('font-weight','bold');
                    jQuery('#payment_us_shift4_section_shift4_auth_token').css({'transition':'all 2s ease'});
                    setTimeout( function(){
                        jQuery('#payment_us_shift4_section_shift4_auth_token').css({'border':'1px dashed #FF2828','box-shadow':'0 0 1px 0 red inset'});
                    },200);
                    $('payment_us_shift4_section_shift4_auth_token').addClassName('required-entry');
                    jQuery('#payment_us_shift4_section_shift4_auth_token').focus();
                }
            }
            jQuery('#payment_us_shift4_section_shift4_auth_token').keyup(function(){
                jQuery('#payment_us_shift4_section_shift4_auth_token').css({'border':'1px solid #aaa'});
                setTimeout(function () {
                    jQuery('#payment_us_shift4_section_shift4_auth_token').css({'border':'1px solid #aaa','box-shadow':'0 0 0 0 #fff inset','transition':'all 2s ease'});
                }, 1000);
            });
        </script>
        <style>#payment_us_shift4_shift4_payment td button:last-child { margin: 0 0 0 10px;}</style>";
        $html = parent::getAfterElementHtml();

        $html .= $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
                ->setType('button')
                ->setId('access_token_unmask')
                ->setLabel('New Token')
                ->setOnClick("javascript:addNew()")
                ->toHtml();

        return $html . $buttonScript;
    }
}
