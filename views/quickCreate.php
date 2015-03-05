<div class="form-group">
	<label for="vm"><?php echo _('Enable Voicemail')?> <i class="fa fa-question-circle fpbx-help-icon" data-for="vm"></i></label><br/>
	<span class="radioset">
		<input type="radio" name="vm" id="vm_on" value="yes">
		<label for="vm_on"><?php echo _('Yes')?></label>
		<input type="radio" name="vm" id="vm_off" value="yes" checked>
		<label for="vm_off"><?php echo _('No')?></label>
	</span>
	<span id="vm-help" class="help-block fpbx-help-block"><?php echo _('Whether to enable voicemail for this extension')?></span>
</div>
<div class="form-group">
	<label for="vmpwd"><?php echo _('Voicemail Password')?> <i class="fa fa-question-circle fpbx-help-icon" data-for="vmpwd"></i></label><br/>
	<div class="input-group">
		<input type="password" name="vmpwd" class="form-control" id="vmpwd" disabled>
		<span class="input-group-btn">
			<button data-id="vmpwd" class="btn btn-default toggle-password" type="button" disabled><i class="fa fa-eye fa-2x" style="margin-top:-4px;"></i></button>
		</span>
	</div>
	<span id="vmpwd-help" class="help-block fpbx-help-block"><?php echo _('This is the password used to access the Voicemail system.<br><br>This password can only contain numbers.<br><br>A user can change the password you enter here after logging into the Voicemail system (*98) with a phone.')?></span>
</div>
<script>
	$("#vm_on").click(function() {
		$("#vmpwd").prop("disabled",false);
		$(".toggle-password[data-id=vmpwd]").prop("disabled",false);
	});
	$("#vm_off").click(function() {
		$("#vmpwd").prop("disabled",true);
		$(".toggle-password[data-id=vmpwd]").prop("disabled",true);
	});
</script>
