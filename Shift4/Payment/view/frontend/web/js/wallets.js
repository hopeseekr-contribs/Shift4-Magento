// wallets.js
//
// I am the js code executed for wallets.

var _wallets_canMakeApplePayments = false;
var _wallets_i4goTrueTokenObj = null;
var _wallet_session = null;

(function($){
	$(".pay-button").hide().addClass("hidden").addClass("pay-hidden");
})(jQuery);

function i4goWalletsInit(owner) {
	_wallets_i4goTrueTokenObj = owner;
	_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Wallets initializing...");
	jQuery(".pay-button").hide().addClass("hidden").addClass("pay-hidden");

	if ((typeof _wallets_i4goTrueTokenObj.walletConfig === "object")) {
		applePayInit(_wallets_i4goTrueTokenObj.walletConfig);
		googlePayInit(_wallets_i4goTrueTokenObj.walletConfig);
		_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Wallets initialized");
	} else {
		var reason = "Wallets not enabled";
		_wallets_i4goTrueTokenObj.settings.debug && remoteLog(reason);
		_wallets_i4goTrueTokenObj.onWalletEnabled("apple-pay", false, reason);
		_wallets_i4goTrueTokenObj.onWalletEnabled("google-pay", false, reason);
	}
}

function postWalletComplete(success) {
	if (_wallet_session != null) {
		_wallet_session.success = success;
		switch (_wallet_session.wallet) {
			case 'apple':
				postApplePayComplete(success);
				break;
			case 'google':
				postGooglePayComplete(success);
				break;
		}
	}
}

function remoteLog(msg, doConsoleLog) {
	if (_wallets_i4goTrueTokenObj !== null && typeof _wallets_i4goTrueTokenObj.walletConfig === "object") {
		_wallets_i4goTrueTokenObj.remoteLog("(Wallets) " + msg);
	}
	if (typeof doConsole == null || doConsoleLog) {
		console.log("wallets - " + msg);
	}
	return true;
}



/*
 **
 ** Apple Pay
 **
 */

function applePayInit(config) {
	try {
		_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Checking for Apple Pay...");
		if ((typeof config === "object") &&
			(typeof config.applePay === "object") &&
			(typeof config.applePay.merchantIdentifier === "string") &&
			config.applePay.merchantIdentifier.length) {
			if (window.ApplePaySession && ApplePaySession.supportsVersion(3) && ApplePaySession.canMakePayments()) {

				if (_wallets_i4goTrueTokenObj.settings.wallet.activeCardRequired) {

					var id = config.applePay.merchantIdentifier;
					if (typeof config.merchant !== "undefined" && typeof config.merchant.verified !== "undefined" && config.merchant.verified) {
						id = config.applePay.partnerInternalMerchantIdentifier;
					}
					var promise = ApplePaySession.canMakePaymentsWithActiveCard(id);
					promise.then(function(canMakePayments) {
						_wallets_i4goTrueTokenObj.settings.debug && remoteLog("applePayInit.canMakePaymentsWithActiveCard(" + id + "): " + canMakePayments);
						_wallets_canMakeApplePayments = canMakePayments;

						/* This is idential to block with activeCardRequired */
						if (canMakePayments) {
							jQuery(".apple-pay-button").on("click", onApplePayClick);
							jQuery(".apple-pay-button").show().removeClass("hidden").removeClass("pay-hidden");
							_wallets_i4goTrueTokenObj.onWalletEnabled("apple-pay", true, "Ready");
						}
					}, function(error) {
						var reason = "applePayInit Error: " + error.message;
						console.error(error);
						remoteLog(reason, false);
						_wallets_i4goTrueTokenObj.onWalletEnabled("apple-pay", false, reason);
					});
				} else {
					/* Similar to above */
					_wallets_canMakeApplePayments = true;
					jQuery(".apple-pay-button").on("click", onApplePayClick);
					jQuery(".apple-pay-button").show().removeClass("hidden").removeClass("pay-hidden");
					_wallets_i4goTrueTokenObj.onWalletEnabled("apple-pay", true, "Ready");
				}
				// end of active card required condition
			} else {
				var reason = "Apple Pay not found";
				_wallets_i4goTrueTokenObj.settings.debug && remoteLog(reason);
				_wallets_i4goTrueTokenObj.onWalletEnabled("apple-pay", false, reason);
			}
		} else {
			var reason = "Apple Pay not configured";
			_wallets_i4goTrueTokenObj.settings.debug && remoteLog(reason);
			_wallets_i4goTrueTokenObj.onWalletEnabled("apple-pay", false, reason);
		}
	} catch (e) {
		var reason = "applePayInit Error: " + e.message;
		console.error(reason, e);
		remoteLog(reason, false);
		_wallets_i4goTrueTokenObj.onWalletEnabled("apple-pay", false, reason);
	}
}

function onApplePayClick(e) {
	try {
		e.preventDefault();
		if (_wallets_canMakeApplePayments) {
			_wallets_i4goTrueTokenObj.clear();
			var applePayPaymentRequest = {
				countryCode: _wallets_i4goTrueTokenObj.walletConfig.countryCode,
				currencyCode: _wallets_i4goTrueTokenObj.walletConfig.currencyCode,
				supportedNetworks: _wallets_i4goTrueTokenObj.walletConfig.applePay.supportedNetworks,
				merchantCapabilities: ['supports3DS'],
				total: {
					label: 'total',
					amount: _wallets_i4goTrueTokenObj.basket.OrderDetails.Amount.toFixed(2)
				},
				/*
				        // OPTIONAL PAYMENT
				        requiredBillingContactFields: [ "email", "name", "phone", "postalAddress" ],
				*/
				requiredShippingContactFields: apGetRequiredShippingContactFields(),
				shippingMethods: apGetDefaultShippingMethods(),
				shippingType: _wallets_i4goTrueTokenObj.settings.wallet.shippingType
			};
			_wallets_i4goTrueTokenObj.settings.debug && remoteLog("onApplePayClick.applePayPaymentRequest: " + JSON.stringify(applePayPaymentRequest));

			var session = new ApplePaySession(3, applePayPaymentRequest);
			session.begin();

			/**
			 * Merchant Validation
			 * We call our merchant session endpoint, passing the URL to use
			 */
			session.onvalidatemerchant = function (event) {
				const validationURL = event.validationURL;
				getApplePaySession(validationURL).then(function(response) {
					session.completeMerchantValidation(response);
					session.onpaymentauthorized = function (event) {
						postApplePayToken(event.payment, session);
					}
					if (_wallets_i4goTrueTokenObj.settings.wallet.shippingOptionRequired) {
						session.onshippingcontactselected = function (event) { apOnShippingContactSelected(event, session); }
						session.onshippingmethodselected = function (event) { apOnShippingMethodSelected(event, session); }
					}
				}, function(error) {
					console.error("onApplePayClick.session.onvalidatemerchant.ERROR:" + error.message, error);
					remoteLog("onApplePayClick.session.onvalidatemerchant.ERROR: " + error.message, false);
				});
			};
		} else {
			alert("Cannot make payment.");
		}
	} catch (e) {
		console.error("onApplePayClick.ERROR:" + e.message, e);
		remoteLog("onApplePayClick.ERROR: " + e.message, false);
	}
}
/**
 * Server to Server call to apple for response used to validate *merchant
 */
function getApplePaySession(validationURL) {
	try {
		return new Promise(function(resolve, reject) {
			const url = "https://" + _wallets_i4goTrueTokenObj.walletConfig.providerDomain + "/index.cfm?fuseaction=ws.applePaySession";
			const data = {
				validationURL: validationURL,
				i4go_accessBlock: _wallets_i4goTrueTokenObj.settings.accessBlock
			};
			_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Sending getApplePaySession: " + JSON.stringify({
				url: url,
				data: data
			}));
			jQuery.post(url, data, function(responseData, status) {
				if (status == "success") {
					resolve(responseData);
				} else {
					_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Failed getApplePaySession: " + JSON.stringify({
						url: url,
						responseData: responseData
					}));
					reject({
						status: status
					});
				}
			})
			.done(function() { })
			.fail(function(jqXHR, textStatus, errorThrown) {
				alert('asf');
				_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Failed getApplePaySession: " + JSON.stringify({
					url: url,
					jqXHR: jqXHR,
					textStatus: textStatus,
					errorThrown: errorThrown
				}));
				reject({
					status: textStatus
				});
			});
		});
	} catch (e) {
		console.error("onApplePayClick.ERROR:" + e.message, e);
		remoteLog("onApplePayClick.ERROR: " + e.message, false);
	}
}

function postApplePayToken(payment, session) {
	_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Sending Apple payment token to i4go frame...");
	Object.assign(payment, {
		shippingIdentifier: currentShippingIdentifier
	}); // this is not contained in the default info from apple - weird
	_wallet_session = {
		wallet: "apple",
		session: session,
		payment: payment
	};
	_wallets_i4goTrueTokenObj.sendApplePayToFrame(payment);
	postWalletComplete(true); // short-circuited confirmation from iframe due to 3DS which may need the iframe dialog for cardholder challenge
}

function postApplePayComplete(success) {
	if (_wallet_session != null && _wallet_session.wallet == "apple") {
		var desc = "failure";
		if (success) {
			desc = "success";
		}
		_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Apple Pay post complete: " + desc);
		if (success) {
			_wallet_session.session.completePayment(_wallet_session.session.STATUS_SUCCESS);
		} else {
			_wallet_session.session.completePayment(_wallet_session.session.STATUS_FAILURE);
		}
		_wallet_session = null;
	}
}

function apGetRequiredShippingContactFields() {
	let requiredShippingContactFields = [];
	if (_wallets_i4goTrueTokenObj.settings.wallet.emailRequired) {
		requiredShippingContactFields.push("email");
	}
	if (typeof _wallets_i4goTrueTokenObj.settings.wallet.nameRequired === "boolean" ? _wallets_i4goTrueTokenObj.settings.wallet.nameRequired : _wallets_i4goTrueTokenObj.settings.wallet.addressRequired) {
		requiredShippingContactFields.push("name");
	}
	if (_wallets_i4goTrueTokenObj.settings.wallet.phoneNumberRequired) {
		requiredShippingContactFields.push("phone");
	}
	if (_wallets_i4goTrueTokenObj.settings.wallet.addressRequired) {
		requiredShippingContactFields.push("postalAddress");
	}
	return requiredShippingContactFields;
}

function apGetDefaultShippingMethods() {
	let shippingMethods = [];
	if (typeof _wallets_i4goTrueTokenObj.settings.wallet.shippingOptions === "object") {
		_wallets_i4goTrueTokenObj.settings.wallet.shippingOptions.forEach(function (item) { shippingMethods.push({
			label: item.label,
			detail: item.description,
			amount: item.amount,
			identifier: item.id
			})
		});
	}
	return shippingMethods;
}

let currentShippingIdentifier = "";

function ap2gpIntermediatePaymentData(callbackTrigger, defaultShippingId, event) {
	let intermediatePaymentData = {
		callbackTrigger: callbackTrigger
	};
	if (typeof event.shippingContact === "object") {
		Object.assign(intermediatePaymentData, {
			shippingAddress: event.shippingContact,
			shippingOptionData: {
				id: defaultShippingId
			}
		});
		currentShippingIdentifier = defaultShippingId;
	};
	if (typeof event.shippingMethod === "object") {
		Object.assign(intermediatePaymentData, {
			shippingOptionData: {
				id: event.shippingMethod.identifier
			}
		});
		currentShippingIdentifier = event.shippingMethod.identifier;
	};
	return intermediatePaymentData;
}

function gp2apPaymentDataRequestUpdate(gpPaymentDataRequestUpdate) {
	let apPaymentDataRequestUpdate = {
		newTotal: {
			type: gpPaymentDataRequestUpdate.newTransactionInfo.totalPriceStatus.toLowerCase(),
			label: gpPaymentDataRequestUpdate.newTransactionInfo.totalPriceLabel,
			amount: gpPaymentDataRequestUpdate.newTransactionInfo.totalPrice
		},
		newLineItems: []
	};
	gpPaymentDataRequestUpdate.newTransactionInfo.displayItems.forEach(function (item) { apPaymentDataRequestUpdate.newLineItems.push({
		type: item.status.toLowerCase(),
		label: item.label,
		amount: item.price
		})
	});
	if (typeof gpPaymentDataRequestUpdate.newShippingOptionParameters === "object") {
		try {
			const regex = /^([\$]?(\d*([.,](?=\d{3}))?\d+)+((?!\2)[.,]\d\d)?|Free)[\s\:\-$]/i;
			let newShippingMethods = [];
			gpPaymentDataRequestUpdate.newShippingOptionParameters.shippingOptions.forEach(function(item) {
				let amount = "0.00";
				if (typeof item.price === "string") {
					amount = item.price;
				} else if (typeof item.price === "number") {
					amount = item.price.toFixed(2);
				}
				let label = jQuery.trim(item.label.replace(regex, ""));
				newShippingMethods.push({
					label: label,
					detail: item.description,
					amount: amount,
					identifier: item.id
				});
			});
			Object.assign(apPaymentDataRequestUpdate, {
				newShippingMethods: newShippingMethods
			});
		} catch (e) {
			console.error("gp2apPaymentDataRequestUpdate.ERROR:" + e.message, e);
			remoteLog("gp2apPaymentDataRequestUpdate.ERROR: " + e.message, false);
		}
	}
	_wallets_i4goTrueTokenObj.settings.debug && remoteLog('gp2apPaymentDataRequestUpdate: {"gpPaymentDataRequestUpdate":' + JSON.stringify(gpPaymentDataRequestUpdate) + ',"apPaymentDataRequestUpdate":' + JSON.stringify(apPaymentDataRequestUpdate) + '}');


	/*
	GP update
	{
	  "newTransactionInfo": {
	    "displayItems": [
	      {
	        "label": "Subtotal",
	        "type": "SUBTOTAL",
	        "price": "44.44"
	      },
	      {
	        "type": "LINE_ITEM",
	        "label": "Shipping Cost",
	        "price": "2.99",
	        "status": "FINAL"
	      }
	    ],
	    "countryCode": "US",
	    "currencyCode": "USD",
	    "totalPriceStatus": "FINAL",
	    "totalPrice": "47.43",
	    "totalPriceLabel": "Total"
	  }

	  "newShippingOptionParameters": {
	    "defaultSelectedOptionId":"shipping-001",
	    "shippingOptions":[
	      {
	        "id":"shipping-001",
	        "label":"Free: Standard shipping",
	        "description":"Free shipping delivered in 5 business days; handling fees still apply."
	      },
	      {
	        "id":"shipping-002",
	        "label":"$2.99: Standard shipping",
	        "description":"Standard shipping delivered in 3 business days."
	      },
	      {
	        "id":"shipping-003",
	        "label":"$10: Express shipping",
	        "description":"Express shipping delivered in 1 business day."
	      },
	      {
	        "id":"shipping-004",
	        "label":"$1299: Hand delivered",
	        "description":"Hand delivered in 10-30 business days."
	      }
	    ]
	  }


	}

	AP update
	{
	  "newTotal": {
	    "type": "final",
	    "label": "Total",
	    "amount": "47.43"
	  },
	  "newLineItems": [
	    {
	      "type": "final",
	      "label": "Subtotal",
	      "amount": "44.44"
	    },
	    {
	      "type": "final",
	      "label": "Shipping Cost",
	      "amount": "2.99"
	    }
	  ]
	}
	*/
	return apPaymentDataRequestUpdate;
}

function apOnPaymentDataChanged(callbackTrigger, defaultShippingId, event, session) {
	_wallets_i4goTrueTokenObj.settings.debug && remoteLog("apOnPaymentDataChanged.begin: " + JSON.stringify(arguments));
	return new Promise(function(resolve, reject) {
		gpOnPaymentDataChanged(ap2gpIntermediatePaymentData(callbackTrigger, defaultShippingId, event), "apple")
			.then(function (paymentDataRequestUpdate)  {
				let result = gp2apPaymentDataRequestUpdate(paymentDataRequestUpdate);
				_wallets_i4goTrueTokenObj.settings.debug && remoteLog("apOnPaymentDataChanged.end: " + JSON.stringify(result));
				resolve(result);
			})
			.catch(function(e) {
				console.error("apOnPaymentDataChanged.ERROR:" + e.message, e);
				remoteLog("apOnPaymentDataChanged.ERROR: " + e.message, false);
				reject(e);
			});
	});
}

function apOnShippingContactSelected(event, session) {
	let shippingContact = event.shippingContact;
	apOnPaymentDataChanged("SHIPPING_ADDRESS", _wallets_i4goTrueTokenObj.settings.wallet.shippingOptions[0].id, event, session)
		.then(function (paymentDataRequestUpdate) { session.completeShippingContactSelection(paymentDataRequestUpdate)} )
		.catch(function(e)  {
			console.error("apOnShippingContactSelected.ERROR:" + e.message, e);
			remoteLog("apOnShippingContactSelected.ERROR: " + e.message, false);
			throw (e);
		});
}

function apOnShippingMethodSelected(event, session) {
	let shippingMethod = event.shippingMethod;
	apOnPaymentDataChanged("SHIPPING_OPTION", shippingMethod.identifier, event, session)
		.then(function (paymentDataRequestUpdate) { session.completeShippingMethodSelection(paymentDataRequestUpdate)} )
		.catch(function (e) {
			console.error("apOnShippingMethodSelected.ERROR:" + e.message, e);
			remoteLog("apOnShippingMethodSelected.ERROR: " + e.message, false);
			throw (e);
		});
}

/*
 **
 ** Google Pay
 **
 */

function googlePayInit(config) {
	try {
		_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Checking for Google Pay...");
		_wallets_i4goTrueTokenObj.settings.debug && console.log("wallet - googlePayInit()", config);
		paymentsClient = null;
		if ((typeof config === "object") &&
			(typeof config.googlePay === "object") &&
			(typeof config.googlePay.authJwt === "string") &&
			config.googlePay.authJwt.length) {

			_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Google Pay configuration found");
			tokenizationSpecification.parameters.gateway = config.googlePay.gateway;
			tokenizationSpecification.parameters.gatewayMerchantId = (typeof config.merchant === "object" && typeof config.merchant.identifier === "string") ? config.merchant.identifier : config.merchantID.toString();
			allowedCardAuthMethods = config.googlePay.allowedAuthMethods;
			allowedCardNetworks = config.googlePay.allowedCardNetworks;
			baseCardPaymentMethod.parameters.allowedAuthMethods = allowedCardAuthMethods;
			baseCardPaymentMethod.parameters.allowedCardNetworks = allowedCardNetworks;
			baseCardPaymentMethod.parameters = Object.assign(baseCardPaymentMethod.parameters, {
				allowedAuthMethods: allowedCardAuthMethods,
				allowedCardNetworks: allowedCardNetworks,
				billingAddressRequired: (typeof _wallets_i4goTrueTokenObj.settings.wallet.nameRequired === "boolean" ? _wallets_i4goTrueTokenObj.settings.wallet.nameRequired : _wallets_i4goTrueTokenObj.settings.wallet.addressRequired),
				billingAddressParameters: {
					format: "MIN",
					phoneNumberRequired: _wallets_i4goTrueTokenObj.settings.wallet.phoneNumberRequired
				}
			});
			cardPaymentMethod = Object.assign({},
				baseCardPaymentMethod, {
					tokenizationSpecification: tokenizationSpecification
				}
			);

			onGooglePayLoaded();

		} else {
			var reason = "Google Pay not configured";
			_wallets_i4goTrueTokenObj.settings.debug && remoteLog(reason);
			_wallets_i4goTrueTokenObj.onWalletEnabled("google-pay", false, reason);
		}
	} catch (e) {

		var reason = "googlePayInit Error: " + e.message;
		console.error(reason, e);
		remoteLog(reason, false);
		_wallets_i4goTrueTokenObj.onWalletEnabled("google-pay", false, reason);

	}
}

/**
 * Define the version of the Google Pay API referenced when creating your
 * configuration
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#PaymentDataRequest|apiVersion in PaymentDataRequest}
 */
const baseRequest = {
	apiVersion: 2,
	apiVersionMinor: 0
};

/**
 * Card networks supported by your site and your gateway
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#CardParameters|CardParameters}
 * @todo confirm card networks supported by your site and gateway
 */
let allowedCardNetworks = ["AMEX", "DISCOVER", "JCB", "MASTERCARD", "VISA"];

/**
 * Card authentication methods supported by your site and your gateway
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#CardParameters|CardParameters}
 * @todo confirm your processor supports Android device tokens for your
 * supported card networks
 */
let allowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];

/**
 * Identify your gateway and your site's gateway merchant identifier
 *
 * The Google Pay API response will return an encrypted payment method capable
 * of being charged by a supported gateway after payer authorization
 *
 * @todo check with your gateway on the parameters to pass
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#gateway|PaymentMethodTokenizationSpecification}
 */
let tokenizationSpecification = {
	type: 'PAYMENT_GATEWAY',
	parameters: {
		'gateway': 'shift4',
		'gatewayMerchantId': ''
	}
};

/**
 * Describe your site's support for the CARD payment method and its required
 * fields
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#CardParameters|CardParameters}
 */
let baseCardPaymentMethod = {
	type: 'CARD',
	parameters: {
		allowedAuthMethods: allowedCardAuthMethods,
		allowedCardNetworks: allowedCardNetworks
	}
};

/**
 * Describe your site's support for the CARD payment method including optional
 * fields
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#CardParameters|CardParameters}
 */
let cardPaymentMethod = Object.assign({},
	baseCardPaymentMethod, {
		tokenizationSpecification: tokenizationSpecification
	}
);

/**
 * An initialized google.payments.api.PaymentsClient object or null if not yet set
 *
 * @see {@link getGooglePaymentsClient}
 */
let paymentsClient = null;

/**
 * Configure your site's support for payment methods supported by the Google Pay
 * API.
 *
 * Each member of allowedPaymentMethods should contain only the required fields,
 * allowing reuse of this base request when determining a viewer's ability
 * to pay and later requesting a supported payment method
 *
 * @returns {object} Google Pay API version, payment methods supported by the site
 */
function getGoogleIsReadyToPayRequest() {
	return Object.assign({},
		baseRequest, {
			allowedPaymentMethods: [baseCardPaymentMethod]
		}
	);
}

/**
 * Configure support for the Google Pay API
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#PaymentDataRequest|PaymentDataRequest}
 * @returns {object} PaymentDataRequest fields
 */
function getGooglePaymentDataRequest() {
	try {
		const paymentDataRequest = Object.assign({}, baseRequest);
		paymentDataRequest.allowedPaymentMethods = [cardPaymentMethod];
		paymentDataRequest.transactionInfo = getGoogleTransactionInfo();
		paymentDataRequest.merchantInfo = {
			merchantId: _wallets_i4goTrueTokenObj.walletConfig.googlePay.merchantId,
			merchantName: _wallets_i4goTrueTokenObj.walletConfig.merchantName.length ? _wallets_i4goTrueTokenObj.walletConfig.merchantName : "Merchant",
			merchantOrigin: _wallets_i4goTrueTokenObj.walletConfig.googlePay.merchantDomain,
			authJwt: _wallets_i4goTrueTokenObj.walletConfig.googlePay.authJwt
		};

		let callbackIntents = ["PAYMENT_AUTHORIZATION"];
		if (_wallets_i4goTrueTokenObj.settings.wallet.shippingOptionRequired) {
			callbackIntents = ["SHIPPING_ADDRESS", "SHIPPING_OPTION", "PAYMENT_AUTHORIZATION"];
		}
		paymentDataRequest.callbackIntents = callbackIntents;
		paymentDataRequest.emailRequired = _wallets_i4goTrueTokenObj.settings.wallet.emailRequired;
		paymentDataRequest.shippingAddressRequired = _wallets_i4goTrueTokenObj.settings.wallet.addressRequired;
		paymentDataRequest.shippingAddressParameters = getGoogleShippingAddressParameters();
		paymentDataRequest.shippingOptionRequired = _wallets_i4goTrueTokenObj.settings.wallet.shippingOptionRequired;
		return paymentDataRequest;
	} catch (e) {
		let errmsg = 'getGooglePaymentDataRequest.ERROR: {"error":"' + e.message + '"}';
		remoteLog(errmsg, false);
		console.error(errmsg);
		throw (e);
	}
}

/**
 * Return an active PaymentsClient or initialize
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/client#PaymentsClient|PaymentsClient constructor}
 * @returns {google.payments.api.PaymentsClient} Google Pay API client
 */
function getGooglePaymentsClient() {
	try {
		if (paymentsClient === null) {
			let paymentDataCallbacks = {
				onPaymentAuthorized: gpOnPaymentAuthorized
			}
			if (_wallets_i4goTrueTokenObj.settings.wallet.shippingOptionRequired) {
				paymentDataCallbacks = {
					onPaymentDataChanged: gpOnPaymentDataChanged,
					onPaymentAuthorized: gpOnPaymentAuthorized
				};
			}
			paymentsClient = new google.payments.api.PaymentsClient({
				environment: _wallets_i4goTrueTokenObj.walletConfig.googlePay.environment,
				merchantInfo: {
					merchantId: _wallets_i4goTrueTokenObj.walletConfig.googlePay.merchantId,
					merchantName: _wallets_i4goTrueTokenObj.walletConfig.merchantName.length ? _wallets_i4goTrueTokenObj.walletConfig.merchantName : "Merchant",
					merchantOrigin: _wallets_i4goTrueTokenObj.walletConfig.googlePay.merchantDomain,
					authJwt: _wallets_i4goTrueTokenObj.walletConfig.googlePay.authJwt
				},
				paymentDataCallbacks: paymentDataCallbacks
			});
		}
		return paymentsClient;
	} catch (e) {
		let errmsg = 'getGooglePaymentsClient.ERROR: {"error":"' + e.message + '"}';
		remoteLog(errmsg, false);
		console.error(errmsg);
		throw (e);
	}
}


function gpOnPaymentAuthorized(paymentData) {
	return new Promise(function(resolve, reject) {
		// handle the response
		processPayment(paymentData)
			.then(function() {
				resolve({
					transactionState: 'SUCCESS'
				});
			})
			.catch(function() {
				resolve({
					transactionState: 'ERROR',
					error: {
						intent: 'PAYMENT_AUTHORIZATION',
						message: 'Invalid payment data',
						reason: 'PAYMENT_DATA_INVALID'
					}
				});
			});

	});
}

/**
 * Handles dynamic buy flow shipping address and shipping options callback intents.
 *
 * @param {object} itermediatePaymentData response from Google Pay API a shipping address or shipping option is selected in the payment sheet.
 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#IntermediatePaymentData|IntermediatePaymentData object reference}
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#PaymentDataRequestUpdate|PaymentDataRequestUpdate}
 * @returns Promise<{object}> Promise of PaymentDataRequestUpdate object to update the payment sheet.
 */
function gpOnPaymentDataChanged(intermediatePaymentData, wallet) {
	return new Promise(function(resolve, reject) {
		try {
			let shippingAddress = intermediatePaymentData.shippingAddress;
			let shippingOptionData = intermediatePaymentData.shippingOptionData;
			let paymentDataRequestUpdate = {};
			if (intermediatePaymentData.callbackTrigger == "INITIALIZE" || intermediatePaymentData.callbackTrigger == "SHIPPING_ADDRESS") {
				paymentDataRequestUpdate.newShippingOptionParameters = getDefaultShippingOptions();
				let selectedShippingOptionId = paymentDataRequestUpdate.newShippingOptionParameters.defaultSelectedOptionId;
				paymentDataRequestUpdate.newTransactionInfo = calculateNewTransactionInfo(selectedShippingOptionId);
			} else if (intermediatePaymentData.callbackTrigger == "SHIPPING_OPTION") {
				paymentDataRequestUpdate.newTransactionInfo = calculateNewTransactionInfo(shippingOptionData.id);
			}
			if (typeof _wallets_i4goTrueTokenObj.settings.onPaymentDataChanged === "function") {
				paymentDataRequestUpdate = _wallets_i4goTrueTokenObj.settings.onPaymentDataChanged(_wallets_i4goTrueTokenObj, intermediatePaymentData, paymentDataRequestUpdate);
			}
			_wallets_i4goTrueTokenObj.settings.debug && remoteLog('gpOnPaymentDataChanged.callout: {"intermediatePaymentData":' + JSON.stringify(intermediatePaymentData) + ',"paymentDataRequestUpdate":' + JSON.stringify(paymentDataRequestUpdate) + '}');
			if (typeof wallet !== "string" || wallet === "google") {
				if (typeof paymentDataRequestUpdate.newShippingOptionParameters === "object") {
					paymentDataRequestUpdate.newShippingOptionParameters.shippingOptions = getGoogleShippingOptions(paymentDataRequestUpdate.newShippingOptionParameters.shippingOptions);
				}
			}
			resolve(paymentDataRequestUpdate);
		} catch (e) {
			let errmsg = 'gpOnPaymentDataChanged.callout.ERROR: {"error":"' + e.message + '","intermediatePaymentData":' + JSON.stringify(intermediatePaymentData) + ',"paymentDataRequestUpdate":' + JSON.stringify(paymentDataRequestUpdate) + '}';
			remoteLog(errmsg, false);
			console.error(errmsg);
			reject(e);
		}
	});
}

/**
 * Helper function to create a new TransactionInfo object.

 * @param string shippingOptionId respresenting the selected shipping option in the payment sheet.
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#TransactionInfo|TransactionInfo}
 * @returns {object} transaction info, suitable for use as transactionInfo property of PaymentDataRequest
 */
function calculateNewTransactionInfo(shippingOptionId) {
	try {
		let newTransactionInfo = getGoogleTransactionInfo();
		if (_wallets_i4goTrueTokenObj.settings.wallet.shippingOptionRequired) {
			let shippingCost = getDefaultShippingCost(shippingOptionId);
			newTransactionInfo.displayItems.push({
				type: "LINE_ITEM",
				label: "Shipping Cost",
				price: shippingCost.toFixed(2),
				status: "FINAL"
			});
			let totalPrice = 0.00;
			newTransactionInfo.displayItems.forEach(function (displayItem) { totalPrice += parseFloat(displayItem.price)} );
			newTransactionInfo.totalPrice = totalPrice.toFixed(2);
		}
		return newTransactionInfo;
	} catch (e) {
		let errmsg = "calculateNewTransactionInfo.ERROR:" + e.message;
		console.error(errmsg);
		remoteLog(errmsg, false);
		throw (e);
	}
}

/**
 * Initialize Google PaymentsClient after Google-hosted JavaScript has loaded
 *
 * Display a Google Pay payment button after confirmation of the viewer's
 * ability to pay.
 */
function onGooglePayLoaded() {
	const paymentsClient = getGooglePaymentsClient();
	paymentsClient.isReadyToPay(getGoogleIsReadyToPayRequest()).then(function(response) {
		_wallets_i4goTrueTokenObj.settings.debug && remoteLog("onGooglePayLoaded.response: " + JSON.stringify(response));
		if (response.result) {
			addGooglePayButton();
			_wallets_i4goTrueTokenObj.onWalletEnabled("google-pay", true, "Ready");
		} else {
			_wallets_i4goTrueTokenObj.onWalletEnabled("google-pay", false, "Google Pay not available");
		}
	}).catch(function(error) {// show error in developer console for debugging
		var reason = "getGoogleIsReadyToPayRequest.ERROR: " + error.message;
		console.error(reason, error);
		remoteLog(reason, false);
		_wallets_i4goTrueTokenObj.onWalletEnabled("google-pay", false, reason);
	});
}

/**
 * Add a Google Pay purchase button alongside an existing checkout button
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#ButtonOptions|Button options}
 * @see {@link https://developers.google.com/pay/api/web/guides/brand-guidelines|Google Pay brand guidelines}
 */
function addGooglePayButton() {
	const paymentsClient = getGooglePaymentsClient();
	let buttonOptions = {
		onClick: onGooglePaymentButtonClicked
	};
	if (typeof _wallets_i4goTrueTokenObj.settings.wallet.buttonColor === "object") {
		Object.assign(buttonOptions, {
			buttonColor: _wallets_i4goTrueTokenObj.settings.wallet.buttonColor
		});
	}
	if (typeof _wallets_i4goTrueTokenObj.settings.wallet.buttonType === "object") {
		Object.assign(buttonOptions, {
			buttonType: _wallets_i4goTrueTokenObj.settings.wallet.buttonType
		});
	}
	if (typeof _wallets_i4goTrueTokenObj.settings.wallet.buttonSizeMode === "object") {
		Object.assign(buttonOptions, {
			buttonSizeMode: _wallets_i4goTrueTokenObj.settings.wallet.buttonSizeMode
		});
	}
	if (typeof _wallets_i4goTrueTokenObj.settings.wallet.buttonRootNode === "object") {
		Object.assign(buttonOptions, {
			buttonRootNode: _wallets_i4goTrueTokenObj.settings.wallet.buttonRootNode
		});
	}
	const button = paymentsClient.createButton(buttonOptions);

	var $buttonObj = jQuery(button).find("button");
	if ($buttonObj.length === 0) {
		$buttonObj = jQuery(button);
	}
	$buttonObj.addClass("pay-button google-pay-button");
	jQuery(".google-pay-button").replaceWith(button);
	jQuery(".google-pay-button").show().removeClass("hidden").removeClass("pay-hidden");
}

/**
 * Provide Google Pay API with a payment amount, currency, and amount status
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#TransactionInfo|TransactionInfo}
 * @returns {object} transaction info, suitable for use as transactionInfo property of PaymentDataRequest
 */
function getGoogleTransactionInfo() {
	try {
		let result = {
			displayItems: [],
			countryCode: _wallets_i4goTrueTokenObj.walletConfig.countryCode,
			currencyCode: _wallets_i4goTrueTokenObj.walletConfig.currencyCode,
			totalPriceStatus: "FINAL",
			totalPrice: _wallets_i4goTrueTokenObj.basket.OrderDetails.Amount.toFixed(2),
			totalPriceLabel: "Total"
		};
		if (typeof _wallets_i4goTrueTokenObj.basket.Cart === "object" && _wallets_i4goTrueTokenObj.basket.Cart.length > 0) {
			_wallets_i4goTrueTokenObj.basket.Cart.forEach(function(item) { result.displayItems.push({
				label: item.Name,
				type: "LINE_ITEM",
				price: item.Price.toFixed(2)
				})
			});
		} else {
			result.displayItems.push({
				label: "Subtotal",
				type: "SUBTOTAL",
				price: _wallets_i4goTrueTokenObj.basket.OrderDetails.Amount.toFixed(2)
			});
		}
		return result;
	} catch (e) {
		let errmsg = "getGoogleTransactionInfo.ERROR:" + e.message;
		console.error(errmsg);
		remoteLog(errmsg, false);
		throw (e);
	}
}

/**
 * Provide a key value store for shippping options.
 */
function getDefaultShippingCost(id) {
	try {
		let amount = 0.00;
		for (let i = 0; i < _wallets_i4goTrueTokenObj.settings.wallet.shippingOptions.length; i++) {
			if (_wallets_i4goTrueTokenObj.settings.wallet.shippingOptions[i].id === id) {
				amount = _wallets_i4goTrueTokenObj.settings.wallet.shippingOptions[i].amount;
				break;
			}
		}
		return amount;
	} catch (e) {
		let errmsg = "getDefaultShippingCost.ERROR:" + e.message;
		console.error(errmsg);
		remoteLog(errmsg, false);
		throw (e);
	}
}

/**
 * Provide Google Pay API with billing address parameters when using dynamic buy flow.
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#BillingAddressParameters|BillingAddressParameters}
 * @returns {object} shipping address details, suitable for use as billingAddressParameters property of PaymentDataRequest
 */
function getGoogleBillingAddressParameters() {
	return {
		format: "MIN"
	};
}

/**
 * Provide Google Pay API with shipping address parameters when using dynamic buy flow.
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#ShippingAddressParameters|ShippingAddressParameters}
 * @returns {object} shipping address details, suitable for use as shippingAddressParameters property of PaymentDataRequest
 */
function getGoogleShippingAddressParameters() {
	return {
		allowedCountryCodes: _wallets_i4goTrueTokenObj.settings.wallet.allowedCountryCodes,
		phoneNumberRequired: _wallets_i4goTrueTokenObj.settings.wallet.phoneNumberRequired
	};
}

/**
 * Provide Google Pay API with shipping options and a default selected shipping option.
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/request-objects#ShippingOptionParameters|ShippingOptionParameters}
 * @returns {object} shipping option parameters, suitable for use as shippingOptionParameters property of PaymentDataRequest
 */
function getGoogleShippingOptions(shippingOptions) {
	let result = [];
	shippingOptions.forEach(function (item) { result.push({
		id: item.id,
		label: item.label,
		description: item.description
		})
	});
	return result;
}

function getDefaultShippingOptions() {
	let shippingOptions = [];
	_wallets_i4goTrueTokenObj.settings.wallet.shippingOptions.forEach(function (item) { shippingOptions.push(item)} );
	/*
	_wallets_i4goTrueTokenObj.settings.wallet.shippingOptions.forEach(item => shippingOptions.push({
	  id: item.id,
	  label: item.label,
	  description: item.description
	}) );
	*/
	return {
		defaultSelectedOptionId: shippingOptions[0].id,
		shippingOptions: shippingOptions
	};
}

/**
 * Provide Google Pay API with a payment data error.
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#PaymentDataError|PaymentDataError}
 * @returns {object} payment data error, suitable for use as error property of PaymentDataRequestUpdate
 */
function getGoogleUnserviceableAddressError() {
	console.warn("getGoogleUnserviceableAddressError() called!")
	return {
		reason: "SHIPPING_ADDRESS_UNSERVICEABLE",
		message: "Cannot ship to the selected address",
		intent: "SHIPPING_ADDRESS"
	};
}

/**
 * Prefetch payment data to improve performance
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/client#prefetchPaymentData|prefetchPaymentData()}
 */
function prefetchGooglePaymentData() {
	const paymentDataRequest = getGooglePaymentDataRequest();
	// transactionInfo must be set but does not affect cache
	paymentDataRequest.transactionInfo = {
		totalPriceStatus: 'NOT_CURRENTLY_KNOWN',
		currencyCode: 'USD'
	};
	const paymentsClient = getGooglePaymentsClient();
	paymentsClient.prefetchPaymentData(paymentDataRequest);
}


/**
 * Show Google Pay payment sheet when Google Pay payment button is clicked
 */
function onGooglePaymentButtonClicked() {

	jQuery('#new_card').show();
	jQuery('#i4go_form').show();
	jQuery('#shift4_place_order .s4placeOrderBlock').hide();

	const paymentDataRequest = getGooglePaymentDataRequest();
	paymentDataRequest.transactionInfo = getGoogleTransactionInfo();

	const paymentsClient = getGooglePaymentsClient();
	paymentsClient.loadPaymentData(paymentDataRequest);

}

/**
 * Process payment data returned by the Google Pay API
 *
 * @param {object} paymentData response from Google Pay API after user approves payment
 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#PaymentData|PaymentData object reference}
 */
function processPayment(paymentData) {
	_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Sending Google payment token to i4go frame...");
	console.log("GP", paymentData);
	_wallet_session = {
		wallet: "google",
		session: null,
		payment: paymentData
	};
	_wallets_i4goTrueTokenObj.sendGooglePayToFrame(paymentData);
	postWalletComplete(true); // short-circuited confirmation from iframe due to 3DS which may need the iframe dialog for cardholder challenge
	return new Promise(function(resolve, reject) {
		var responseCountdown = 45 * 4; // 45 seconds
		var timerHandle = setInterval(function() {
			if (_wallet_session == null || _wallet_session.wallet != "google") {
				// something cleared or overwrote the wallet session
				clearInterval(timerHandle);
				reject({});
				_wallet_session = null;
			} else if (_wallet_session.success == null) {
				if (--responseCountdown <= 0) {
					// timeout
					clearInterval(timerHandle);
					reject({});
					_wallet_session = null;
				}
			} else {
				// complete
				clearInterval(timerHandle);
				if (_wallet_session.success) {
					resolve({});
				} else {
					reject({});
				}
				_wallet_session = null;
			}
		}, 250);
	});
}

function postGooglePayComplete(success) {
	// nothing to do, _wallet_session.success should have already been set
	var desc = "failure";
	if (success) {
		desc = "success";
	}
	_wallets_i4goTrueTokenObj.settings.debug && remoteLog("Google Pay post complete: " + desc);
}

