define([
        'jquery',
		'Magento_Payment/js/view/payment/iframe',
		'Magento_Checkout/js/action/place-order',
		'Magento_Checkout/js/model/payment/additional-validators',
		'Magento_Checkout/js/action/redirect-on-success',
		'mage/url',
		'mage/translate',
		'Magento_Customer/js/model/customer',
		'Magento_Checkout/js/model/quote',
		'Gpay',		
		'wallets_js'		
    ],
    function ($, Component, placeOrderAction, additionalValidators, redirectOnSuccessAction, url, __, customer, quote) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Shift4_Payment/payment/' + window.checkoutConfig.payment.shift4_custom_data.quickPayTemplate
            },
			partialPayment: false,
			saveCard: 0,
			useHsaFsa: 0,
			loaded: false,

            context: function() {
                return this;
            },

            getCode: function() {
                return 'shift4_quick';
            },
			
			getData: function () {
				var self = this;
                return {
                    'method': self.getCode(),
                    'additional_data': {
						'i4goTrueToken': self.i4goTrueToken,
						'save_card': self.saveCard
					}
                };
            },
			
            isActive: function() {
                return true;
            },
			
			initialize: function() {
				this._super();
				var self = this;
				var $ = jQuery.noConflict();
				
				if (parseInt(window.checkoutConfig.payment.shift4_custom_data.quickPayEnable) == 0) {
					console.log('rmoving');
					self.loaded = setInterval(function () {
						self.hideQuickPayment();
					}, 100);					
				} else {
					console.log('ok');
				}
			
				//custom error messages
				$(document).on("click", '.s4-message', function() {
					$(this).fadeOut('fast');
				});

				
				$(document).on('click', ".payment-method", function () {
                    if ($('#' + self.getCode()).is(':checked')) {
                        self.custoMessages(true);
                    } else {
                      	self.custoMessages(false);
                    }
                });
				
				
				
				$('.messages').hide();
			},
			
			hideQuickPayment: function () {
				if ($('#shift4_quickpay') && $('#shift4_quickpay').length > 0) {
					
					$('#shift4_quickpay').remove();
					clearInterval(this.loaded);
				}
			},
			
			placeOrder: function (data, event) {
								var self = this;

				if (event) {
					event.preventDefault();
				}
				
				$('.s4messages').html('');

				if (this.validate()) {
					this.isPlaceOrderActionAllowed(false);
					
					this.getPlaceOrderDeferredObject()
						.fail(
							function (response) {
								self.addMessage(response.responseJSON.message, 'error');
								self.isPlaceOrderActionAllowed(true);
								$('#i4go_form iframe').attr('src', $('#i4go_form iframe').attr('src'));
								return false;
							}
						).done(
							function (response) {
								var resp = JSON.parse(response);
								self.afterPlaceOrder();
								redirectOnSuccessAction.execute();
							}
						);

					return true;
				}
				return false;
			},
			
			addMessage: function(text, type) {
				$('.s4messages').append('<div role="alert" class="message message-'+type+' '+type+' s4-message"><div>'+text+'</div></div>');
			},
			
			custoMessages: function(enable) {
				if (enable) {
					$('.messages').html('');
				} else {
					$('.messages').html('<!-- ko foreach: messageContainer.getErrorMessages() --><!--/ko--><!-- ko foreach: messageContainer.getSuccessMessages() --><!--/ko-->');
				}
			}
        });
    }
);