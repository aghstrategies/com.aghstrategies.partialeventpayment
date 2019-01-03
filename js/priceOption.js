CRM.$(function ($) {
  //Moves custom setting above buttons on "Edit Price Field" form
  $('#installments').insertAfter('.crm-price-field-form-block-is_active');

  function checkInstall() {
    if ($('#install_check').is(":checked")) {
  		$("#installment-option").show();
  	} else {
  		$("#installment-option").hide();
  	}
  };

	$('#installments tr').insertAfter('.crm-price-option-form-block-is_default');

  checkInstall();

	$('#install_check').change(function(){
    checkInstall();
	});

});
