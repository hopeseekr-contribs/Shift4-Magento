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
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
