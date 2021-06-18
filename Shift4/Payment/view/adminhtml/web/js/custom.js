jQuery(document).ready(function () {

    var processingMode = jQuery('select[id*="shift4_section_processing_mode"] option:selected').val();

	showAccessToken();
	changeProcessingMode(processingMode);
	jQuery('input[id*="shift4_section_masked_access_token"]').attr('disabled', 'disabled');
	
	  jQuery('select[id*="shift4_section_processing_mode"]').change(function() {
		changeProcessingMode(this.value);
    });
	
	jQuery('#access_token_unmask').click(function() {
		jQuery('tr[id*="shift4_section_auth_token"]').show();
		jQuery('tr[id*="shift4_section_masked_access_token"]').hide();
	});
	
	jQuery('#shift4_cancel_token_exchange').click(function() {
		jQuery('tr[id*="shift4_section_auth_token"]').hide();
		jQuery('tr[id*="shift4_section_masked_access_token"]').show();
	});
	
	jQuery('#shift4_exchange_tokens').click(function() {
		
		var authToken = jQuery('input[id*="shift4_section_auth_token"]').val();
        var endPoint = jQuery('textarea[id*="shift4_section_server_addresses"]').val();

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
					jQuery('tr[id*="shift4_section_auth_token"]').hide();
					jQuery('tr[id*="shift4_section_masked_access_token"]').show();
					jQuery('input[id*="shift4_section_masked_access_token"]').val(json.accessToken);
				} else {
					console.log(json);
					alert(json.error_message);
				}				
            },
            onFailure: function() {
                alert('An error occurred during token exchange. Please try again');
            }
        });	
		
	});
	
	function changeProcessingMode(value) {
		 if (value === 'demo') {
			jQuery('textarea[id*="shift4_section_server_addresses"]').removeClass('required-entry');
			jQuery('input[id*="shift4_section_auth_token"]').removeClass('required-entry');
			jQuery('input[id*="shift4_section_masked_access_token"]').removeClass('required-entry');
			jQuery('input[id*="shift4_section_enable_ssl"]').addClass('required-entry');
		
			jQuery('tr[id*="shift4_section_server_addresses"]').hide();
			jQuery('tr[id*="shift4_section_auth_token"]').hide();
			jQuery('tr[id*="shift4_section_masked_access_token"]').hide();
			jQuery('tr[id*="shift4_section_enable_ssl"]').show();
            
        } else {

			jQuery('textarea[id*="shift4_section_server_addresses"]').addClass('required-entry');
			jQuery('input[id*="shift4_section_auth_token"]').addClass('required-entry');
			jQuery('input[id*="shift4_section_masked_access_token"]').addClass('required-entry');
			jQuery('input[id*="shift4_section_enable_ssl"]').removeClass('required-entry');
			
			jQuery('tr[id*="shift4_section_server_addresses"]').show();
			showAccessToken();
			jQuery('tr[id*="shift4_section_enable_ssl"]').hide();
           
        }
	}

	function showAccessToken() {
		if (jQuery('input[id*="shift4_section_masked_access_token"]').val() == '' || typeof(jQuery('input[id*="shift4_section_masked_access_token"]').val()) == 'undefined') {
			jQuery('tr[id*="shift4_section_masked_access_token"]').hide();
			jQuery('tr[id*="shift4_section_auth_token"]').show();
			jQuery('#shift4_cancel_token_exchange').hide();
		} else {
			jQuery('tr[id*="shift4_section_auth_token"]').hide();
			jQuery('tr[id*="shift4_section_masked_access_token"]').show();
			jQuery('#shift4_cancel_token_exchange').show();
		}
	}
	
    /* arrange reset button */

    jQuery('tr[id*="shift4_section_server_addresses"] > td > button').appendTo('tr[id*="shift4_section_server_addresses"] > td:last');
    jQuery('tr[id*="shift4_section_auth_token"] > td > button').appendTo('tr[id*="shift4_section_auth_token"] > td:last');
    jQuery('tr[id*="shift4_section_masked_access_token"] > td > button').appendTo('tr[id*="shift4_section_masked_access_token"] > td:last');

    /* END arrange reset button */

});
