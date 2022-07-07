 require(['jquery', 'jquery/ui'], function($){ 

    var processingMode = $('select[id*="shift4_section_processing_mode"] option:selected').val();


	$('tr[id*="shift4_section_auth_token"] td.value').prepend('<select id="have_auth_token" style="margin-bottom:15px;"><option value="1">Already have Auth Token</option><option value="2">Do not have Auth token</option></select><div id="auth_error_message" style="display:none;font-size: 1.4rem; padding:10px; border:1px solid #f00;">A production Shift4 Merchant ID (MID) is needed to proceed. If a MID is already created, a shift4 configuration sheet with the necessary auth token credentials should have been sent by your Shift4 administrator. Please obtain the auth token and populate it in this field. If your organization has not obtained an e-commerce merchant ID, please contact a Shift4 Channel Sales Manager to obtain the new MID. If you are an existing Shift4 customer, please contact your Shift4 Strategic Account Manager to begin the process. If you are a new Shift4 customer, please use our contact page at <a href="https://www.shift4.com/get-started/" target="_blank">https://www.shift4.com/get-started/</a> to begin the process.</div>');

	$('input[id*="shift4_section_auth_token"]').attr('placeholder', 'XXXXXXXX-XXXX-XXXX-XXXXXXXXXXXXXXXX');

	$('#have_auth_token').change(function() {
		if($(this).val() == 2) {
			$('#auth_error_message').show();
			$('#shift4_exchange_tokens').hide();
			$('input[id*="shift4_section_auth_token"]').hide();
		} else {
			$('#auth_error_message').hide();
			$('#shift4_exchange_tokens').show();
			$('input[id*="shift4_section_auth_token"]').show();
		}

	});

	showAccessToken();
	changeProcessingMode(processingMode);
	$('input[id*="shift4_section_masked_access_token"]').attr('disabled', 'disabled');

	  $('select[id*="shift4_section_processing_mode"]').change(function() {
		changeProcessingMode(this.value);
    });

	$('#access_token_unmask').click(function() {
		$('tr[id*="shift4_section_auth_token"]').show();
		$('tr[id*="shift4_section_masked_access_token"]').hide();
	});

	$('#shift4_cancel_token_exchange').click(function() {
		$('tr[id*="shift4_section_auth_token"]').hide();
		$('tr[id*="shift4_section_masked_access_token"]').show();
	});

	$('#shift4_exchange_tokens').click(function() {

		var authToken = $('input[id*="shift4_section_auth_token"]').val();
        var endPoint = $('input[id*="shift4_section_server_addresses"]').val();

        var errorMsg = '';
        if (authToken == '') {
            errorMsg += 'Auth token ';
            if (endPoint == '') {
                errorMsg += 'and Server Address ';
            }
        }

        if (errorMsg != '') {
              alert('Please enter the ' + errorMsg + 'value for exchange request');
              return;
        }

        new Ajax.Request(exchangeAjaxUrl, {
            method:'post',
            parameters: {
                authToken: authToken,
                endPoint: endPoint
            },
            requestHeaders: {Accept: 'application/json'},
            onSuccess: function(response) {

				var json = response.responseText.evalJSON();

				if (json.error_message == '' || json.error_message == 'undefined') {
					$('tr[id*="shift4_section_auth_token"]').hide();
					$('tr[id*="shift4_section_masked_access_token"]').show();
					$('input[id*="shift4_section_masked_access_token"]').val(json.accessToken);
				} else {
					console.log(json);
					alert(json.error_message);
				}
            },
            onFailure: function(data) {
                alert('An error occurred during token exchange. Please try again or check the shift4.log on the server.');
            }
        });

	});

	function changeProcessingMode(value) {
		 if (value === 'demo') {
			$('textarea[id*="shift4_section_server_addresses"]').removeClass('required-entry');
			$('input[id*="shift4_section_auth_token"]').removeClass('required-entry');
			$('input[id*="shift4_section_masked_access_token"]').removeClass('required-entry');
			$('input[id*="shift4_section_enable_ssl"]').addClass('required-entry');

			$('tr[id*="shift4_section_server_addresses"]').hide();
			$('tr[id*="shift4_section_auth_token"]').hide();
			$('tr[id*="shift4_section_masked_access_token"]').hide();
			$('tr[id*="shift4_section_enable_ssl"]').show();

        } else {

			$('textarea[id*="shift4_section_server_addresses"]').addClass('required-entry');
			$('input[id*="shift4_section_auth_token"]').addClass('required-entry');
			$('input[id*="shift4_section_masked_access_token"]').addClass('required-entry');
			$('input[id*="shift4_section_enable_ssl"]').removeClass('required-entry');

			$('tr[id*="shift4_section_server_addresses"]').show();
			showAccessToken();
			$('tr[id*="shift4_section_enable_ssl"]').hide();

        }
	}

	function showAccessToken() {
		if ($('input[id*="shift4_section_masked_access_token"]').val() == '' || typeof($('input[id*="shift4_section_masked_access_token"]').val()) == 'undefined') {
			$('tr[id*="shift4_section_masked_access_token"]').hide();
			$('tr[id*="shift4_section_auth_token"]').show();
			$('#shift4_cancel_token_exchange').hide();
		} else {
			$('tr[id*="shift4_section_auth_token"]').hide();
			$('tr[id*="shift4_section_masked_access_token"]').show();
			$('#shift4_cancel_token_exchange').show();
		}
	}

    /* arrange reset button */

    $('tr[id*="shift4_section_server_addresses"] > td > button').appendTo('tr[id*="shift4_section_server_addresses"] > td:last');
    $('tr[id*="shift4_section_auth_token"] > td > button').appendTo('tr[id*="shift4_section_auth_token"] > td:last');
    $('tr[id*="shift4_section_masked_access_token"] > td > button').appendTo('tr[id*="shift4_section_masked_access_token"] > td:last');

    /* END arrange reset button */

});
