define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'shift4',
                component: 'Shift4_Payment/js/view/payment/method-renderer/shift4'
            },
			{
                type: 'shift4_quick',
                component: 'Shift4_Payment/js/view/payment/method-renderer/shift4quick'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
