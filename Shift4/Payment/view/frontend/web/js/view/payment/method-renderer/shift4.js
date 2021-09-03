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
		'wallets_js',
		'i4goTrueToken',
    ],
    function ($, Component, placeOrderAction, additionalValidators, redirectOnSuccessAction, url, __, customer, quote) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Shift4_Payment/payment/' + window.checkoutConfig.payment.shift4_custom_data.template
            },
			i4goInitialized: false,
			i4goTrueToken: false,
			i4goExpMonth: 0,
			i4goExpYear: false,
			i4goType: false,
			partialPayment: false,
			saveCard: 1,
			useHsaFsa: 0,

            context: function() {
                return this;
            },

            getCode: function() {
                return 'shift4';
            },

			getData: function () {
				var self = this;
                return {
                    'method': self.getCode(),
                    'additional_data': {
						'i4goTrueToken': self.i4goTrueToken,
						'save_card': self.saveCard,
						'use_hsa_fsa': self.useHsaFsa,
						'i4go_exp_month': self.i4goExpMonth,
						'i4go_exp_year': self.i4goExpYear,
						'i4go_type': self.i4goType,
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
				
				self.i4goInitialized = setInterval(function () {
						self.loadi4go();
					}, 100);
					
					window.cancelAllPayments = function () {
						self.cancelAllPayments(null);
					};
			},

			loadi4go: function() {
				var self = this;
				if ($('#i4go_form') && $('#i4go_form').length > 0) {
					try {
						$('#i4go_form').i4goTrueToken({
							server: window.checkoutConfig.payment.shift4_custom_data.i4go_server,
							accessBlock: window.checkoutConfig.payment.shift4_custom_data.i4go_accessblock,
							language: 'en',
							self: document.location,
							template: "shift4shop",
							i4goInfo: {"visible": false},
							submitButton: { label: window.checkoutConfig.payment.shift4_custom_data.submit_label },
							encryptedOnlySwipe: window.checkoutConfig.payment.shift4_custom_data.support_swipe,
                            gcDisablesExpiration: window.checkoutConfig.payment.shift4_custom_data.disable_expiration_date_for_gc,
                            gcDisablesCVV2Code: window.checkoutConfig.payment.shift4_custom_data.disable_cvv_for_gc,
							cardType: {"visible": true},
							url: window.checkoutConfig.payment.shift4_custom_data.i4go_server_url,
							frameContainer: 'i4go_form', // Only used if frameName does not exist
							frameName: "", // Auto-assigned if left empty
							frameAutoResize: true,
							frameClasses: "",
							formAutoSubmitOnSuccess: false,
							formAutoSubmitOnFailure: false,
							onSuccess: function (form, data) {
								if (data.i4go_response == 'SUCCESS' && data.i4go_responsecode == 1) {
									self.i4goTrueToken = data.i4go_uniqueid;
									self.i4goExpMonth = data.i4go_expirationmonth;
									self.i4goExpYear = data.i4go_expirationyear;
									self.i4goType = data.i4go_cardtype;
									self.placeOrder();
								} else {
									//errors
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
							cssRules: ["body{font-family:'Trebuchet MS', Arial, Helvetica, sans-serif;background-color: '#aaa'; borderLeft: '5px solid #ccc'}#container{margin-left:8px;padding-left:0px;margin-right:0px;padding-right:0px}label{display:none;}.row{margin-right:0;margin-left:0;}.col-4,.col-3{padding-left:0px;padding-right:10px;flex:0;}.col-1,.col-md-8{padding-left:0;}.form-group{margin-bottom:0;}.form-control{max-width: 100%; width: 273px;height: 30px; margin-bottom: 10px; background: #ffffff none repeat scroll 0 0;border: 1px solid silver; border-radius: 2px; font-size: 15px;}#i4go_expirationMonth {width: 80px;}#cvv2Code{width:60px;}#i4go_expirationYear {width: 90px;}.addcardform{height:auto;}#i4go_cvv2Code {width: 80px;}#i4go_cardNumber {width: 255px;}.btn-secure {background: #1979c3; border: 0 none;color: #ffffff;display: inline-block;font-family: 'RalewayHelvetica Neue',Verdana,Arial,sans-serif;font-size: 13px;font-weight: normal;line-height: 19px;padding: 7px 15px;text-align: center;text-transform: uppercase;vertical-align: middle;white-space: nowrap;}.btn-secure:hover {background-color: #006bb4; color: #ffffff;outline: medium none; cursor: pointer;}"]
						});
					}
					catch(err) {
						self.addMessage(err.message, 'error');
					}
					
					//Load partial payments
					$.each(window.checkoutConfig.payment.shift4_custom_data.partial_payments, function (key, value) {
						self.addPartialPayment(value);
					});
					
					//disable messages
					if ($('#' + self.getCode()).is(':checked')) {
						self.custoMessages(true);
					}
					
					//save cards
					if (window.checkoutConfig.payment.shift4_custom_data.saved_cards_enabled) {
						
						$('#save_card_for_future_use').show();
						$('#shift4_place_order').show();
						$('#shift4_place_order .s4placeorderbutton span').html(window.checkoutConfig.payment.shift4_custom_data.submit_label);

						if (window.checkoutConfig.payment.shift4_custom_data.saved_cards != '') {
							$('#my_saved_cards').html(window.checkoutConfig.payment.shift4_custom_data.saved_cards);
							$('#my_saved_cards').show();
							
							
							if (window.checkoutConfig.payment.shift4_custom_data.default_card != 'new') {
								$('#shift4_place_order .s4placeOrderBlock').show();
								$('#new_card').hide();
								self.i4goTrueToken = window.checkoutConfig.payment.shift4_custom_data.default_card;
							} else {
								$('#shift4_place_order .s4placeOrderBlock').hide();
								$('#new_card').show();
							}
						} else {
							$('#shift4_place_order').hide();
							$('#new_card').show();
						}
						
						$('#my_saved_cards').change(function() {
							if ($(this).val() == 'new') {
								$('#shift4_place_order .s4placeOrderBlock').hide();
								$('#new_card').show();
							} else if ($(this).val() == 'wallets') {
								$('#shift4_place_order .s4placeOrderBlock').hide();
								$('#new_card').hide();
							} else {
								self.i4goTrueToken = $(this).val();
								$('#shift4_place_order .s4placeOrderBlock').show();
								$('#new_card').hide();
							}
						});
						
						//save card for future use checkbox
						$('#save_card').change(function() {
							if ($(this).prop('checked')) {
								self.saveCard = 1;
							} else {
								self.saveCard = 0;
							}
						});
					} else {
						$('#shift4_place_order').hide();
						$('#new_card').show();
					}
					
					
					
					clearInterval(this.i4goInitialized);
				} else {
					//fail
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
								if (response.responseJSON.message.substr(0, 17) == 'Partial payment: ') {
									
									var sep = response.responseJSON.message.indexOf('|');
									
									var paymentData = response.responseJSON.message.substr(17, sep-17);
									var message = response.responseJSON.message.substr(sep+1);
									paymentData = paymentData.split(';');
									self.addMessage(message, 'warning');
									
									if (confirm(__('The available amount on your card has been authorized for use, but it is insufficient to complete your purchase. To complete your purchase, click OK and enter an additional card. To cancel your purchase, click Cancel'))) {

										self.addPartialPayment(paymentData);

									} else {
										self.cancelPayment(paymentData[0]);
										$('.s4-message.message-warning').remove();
									}
									self.isPlaceOrderActionAllowed(true);
									$('#i4go_form iframe').attr('src', $('#i4go_form iframe').attr('src'));
									
								} else {
									self.addMessage(response.responseJSON.message, 'error');
									self.isPlaceOrderActionAllowed(true);
									$('#i4go_form iframe').attr('src', $('#i4go_form iframe').attr('src'));
									return false;
								}
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
			
			cancelAllPayments: function(e) {
				var self = this;
				if (confirm(__("Are you sure you want to cancel your payment? Click OK to cancel your payment and release the amount on hold. Click Cancel to enter another credit card and continue with your payment."))) {
					$('.s4messages').html('');
					var canelUrl = url.build('shift4/payment/cancelAllPayments');
					$.ajax({
						method: "POST",
						url: canelUrl,
						dataType: "json",
						showLoader: true,
						data: {}
					})
					.done(function (response) {
						if (response == '1') {
								$('#partial_payments').html('');
								$('#preauth_cancel').html('');
								$('#display_left_amount').html('');
								$('#customerbalance-placer').show();
								
								if (window.checkoutConfig.payment.shift4_custom_data.healthcareTotalAmount > 0) {
									$('#use_hsa_fsa_block').show();
								}

								self.addMessage(__("All partial authorizations voided."), 'success');
								self.partialPayment = false;
							} else {
								self.addMessage(response, 'error');
							}
							
						});
					return true;
				} else {
					if (e) { e.preventDefault(); }
					return false;
				}				
            },
			cancelPayment: function(shift4invoice) {
					var self = this;
					var canelUrl = url.build('shift4/payment/cancelPayment');
					$.ajax({
						method: "POST",
						url: canelUrl,
						dataType: "json",
						showLoader: true,
						data: {shift4invoice: shift4invoice}
					})
					.done(function (response) {
						if (response == '1') {
								//success
							} else {
								self.addMessage(response, 'error');
							}
							
						});
					return true;				
            },
			
			addPartialPayment: function(resp) {
				var self = this;
				$('#partial_payments').append('<div id="preauthorized_section_' + resp[1] + '" style="margin-top:1.5em;"><div id="preauth_card_type_' + resp[1] + '" style="clear:both;"></div><div id="preauth_card_number_' + resp[1] + '" style="clear:both;"></div><div id="preauth_processed_amount_' + resp[1] + '" style="clear:both;"></div><div id="preauth_invoice_id_' + resp[1] + '" style="clear:both;"></div><div id="preauth_auth_code_' + resp[1] + '" style="clear:both;"></div><div id="preauth_receipt_text_' + resp[1] + '" style="clear:both;"></div></div>');
												
				$("#preauthorized_section_" + resp[1] + " #preauth_card_type_" + resp[1]).append('<span style="width:200px; float:left;"><strong>' + __('Card Type:') + '</strong></span>' + resp[2]);

				$("#preauthorized_section_" + resp[1] + " #preauth_card_number_" + resp[1]).append('<span style="width:200px; float:left;"><strong>' + __('Card Number:') + '</strong></span>' + resp[3]);

				$("#preauthorized_section_" + resp[1] + " #preauth_processed_amount_" + resp[1]).append('<span style="width:200px; float:left;"><strong>' + __('Processed Amount') + '</strong></span>$' + parseFloat(resp[4]).toFixed(2));

				$("#preauthorized_section_" + resp[1] + " #preauth_invoice_id_" + resp[1]).append('<span style="width:200px; float:left;"><strong>' + __('Shift4 Invoice ID') + '</strong></span><span class="s4Invoice">' + resp[0] + '</span>');

				$("#preauthorized_section_" + resp[1] + " #preauth_auth_code_" + resp[1]).append('<span style="width:200px; float:left;"><strong>' + __('Authorization Code:') + '</strong></span>' + resp[5]);

				var payment_word = __('payment');
				if (resp[1] > 1)  { payment_word = __('payments'); }
												
				$("#preauth_cancel").html('<div class="release-amounts"><button class="button" type="button" id="payment-button-cancel" autocomplete="off" onclick="window.cancelAllPayments(null);"><span><span>Cancel</span></span></button><span>  ' + __('To cancel your %1, click Cancel.').replace('%1', payment_word) + '</span></div>');

				$("#display_left_amount").html("<strong>" + __('Amount Remaining: $%1').replace('%1', parseFloat(resp[6]).toFixed(2))  + "</strong>");

				self.partialPayment = true;

				$(document).on('click', ".payment-method", function () {
					if ($('#' + self.getCode()).is(':checked')) {
						return true;
					} else {
						if (self.partialPayment) {
							alert(__("You can not use another payment method for remaining amount."));
							$('#' + self.getCode()).prop('checked', true).trigger('click');
							return false;
						}
					}
				});

				$('#customerbalance-placer').hide();
				
				$(document).on('click', "a", function (e) {
					if (self.partialPayment) {
						self.cancelAllPayments(e);
					}
				});
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