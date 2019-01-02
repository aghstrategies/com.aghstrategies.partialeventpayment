{* template block that contains the new field *}
<table id="installments" class="form-layout">
	<tr class="crm-section price_set-section">
	  <td class="label">{$form.install_check.label}</td>
	  <td>{$form.install_check.html}</td>
	</tr>
	<tr id="installment-option">
		<td class="label">{$form.priceFieldSelect.label}</td>
		<td>{$form.priceFieldSelect.html}</td>
	</tr>
</table>
<script type="text/javascript">
{literal}
function checkInstall(){
  if ($('#install_check').is(":checked")){
		$("#installment-option").show();
	} else {
		$("#installment-option").hide();
	}
}
	$('#installments tr').insertAfter('.crm-price-option-form-block-is_default');
	checkInstall();
	$('#install_check').change(function(){
    checkInstall();
	});
	{/literal}
</script>
