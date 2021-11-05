/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

var config = {
	paths: {
		"i4goTrueToken": "https://i4m.shift4test.com/js/jquery.i4goTrueToken",
		"Gpay": "https://pay.google.com/gp/p/js/pay",
		wallets_js: "Shift4_Payment/js/wallets"
	},
	shim: {
		jquery: {
            exports: '$'
        },
        'i4goTrueToken': {
            deps: ['jquery']
        },
        wallets_js: {
            deps: ['jquery']
        },
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Shift4_Payment/js/view/shipping': true
            }
        }
    },
    map: {
        '*': {
            'Magento_Checkout/js/view/shipping': 'Shift4_Payment/js/view/shipping'
        }
    }
};