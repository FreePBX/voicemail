<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="voicemailenable"><?php echo _("Enable Voicemail Access") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="voicemailenable"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="voicemail_enable" id="voicemail_enable_yes" value="yes" <?php echo $enable ? 'checked' : ''?>>
						<label for="voicemail_enable_yes"><?php echo _("Yes")?></label>
						<input type="radio" name="voicemail_enable" id="voicemail_enable_no" value="no" <?php echo (!is_null($enable) && !$enable) ? 'checked' : ''?>>
						<label for="voicemail_enable_no"><?php echo _("No")?></label>
						<?php if($mode == "user") {?>
							<input type="radio" id="voicemail_enable_inherit" name="voicemail_enable" value='inherit' <?php echo is_null($enable) ? 'checked' : ''?>>
							<label for="voicemail_enable_inherit"><?php echo _('Inherit')?></label>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="voicemailenable-help" class="help-block fpbx-help-block"><?php echo _("Enable the voicemail Access in UCP for this user")?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="ucp_voicemail"><?php echo _("Allowed Voicemail")?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="ucp_voicemail"></i>
					</div>
					<div class="col-md-9">
						<select data-placeholder="Extensions" id="ucp_voicemail" class="form-control chosenmultiselect ucp-voicemail" name="ucp_voicemail[]" multiple="multiple" <?php echo (!is_null($enable) && !$enable) ? "disabled" : ""?>>
							<?php foreach($ausers as $key => $value) {?>
								<option value="<?php echo $key?>" <?php echo in_array($key,$vmassigned) ? 'selected' : '' ?>><?php echo $value?></option>
							<?php } ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="ucp_voicemail-help" class="help-block fpbx-help-block"><?php echo _("These are the assigned and active extensions which will show up for this user to control and edit in UCP")?></span>
		</div>
	</div>
</div>
<script>
	$("input[name=voicemail_enable]").change(function() {
		if($(this).val() == "yes" || $(this).val() == "inherit") {
			$(".ucp-voicemail").prop("disabled",false).trigger("chosen:updated");;
		} else {
			$(".ucp-voicemail").prop("disabled",true).trigger("chosen:updated");;
		}
	});
</script>
