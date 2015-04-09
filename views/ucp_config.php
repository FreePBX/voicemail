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
						<select data-placeholder="Extensions" id="ucp_voicemail" class="form-control chosenmultiselect" name="ucp_voicemail[]" multiple="multiple">
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
