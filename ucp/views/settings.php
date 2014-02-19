<div class="col-md-10">
	<h3>Voicemail Settings</h3>
	<div class="vmsettings">
		<div id="message" class="alert" style="display:none;"></div>
		<form role="form">
			<div class="form-group">
				<label for="pwd">Voicemail Password</label>
				<input name="pwd" type="text" class="form-control" id="pwd" value="<?php echo $settings['pwd']?>" autocapitalize="off" autocorrect="off">
			</div>
			<div class="form-group">
				<label for="email">Email Address</label>
				<input name="email" type="email" class="form-control" id="email" value="<?php echo $settings['email']?>" placeholder="user@domain.tld" autocapitalize="off" autocorrect="off">
			</div>
			<div class="form-group">
				<label for="pager">Pager Email Address</label>
				<input name="pager" type="email" class="form-control" id="pager" value="<?php echo $settings['pager']?>" placeholder="user@domain.tld" autocapitalize="off" autocorrect="off">
			</div>
			<div class="checkbox-row">
				<label class="playcid">
					Play CID
					<div class="onoffswitch">
						<input type="checkbox" name="saycid" class="onoffswitch-checkbox" id="saycid" <?php echo ($settings['options']['saycid'] == 'yes') ? 'checked' : ''?> value="yes">
						<label class="onoffswitch-label" for="saycid">
							<div class="onoffswitch-inner"></div>
							<div class="onoffswitch-switch"></div>
						</label>
					</div>
				</label>
				<label class="envelope">
					Play Envelope
					<div class="onoffswitch">
						<input type="checkbox" name="envelope" class="onoffswitch-checkbox" id="envelope" <?php echo ($settings['options']['envelope'] == 'yes') ? 'checked' : ''?> value="yes">
						<label class="onoffswitch-label" for="envelope">
							<div class="onoffswitch-inner"></div>
							<div class="onoffswitch-switch"></div>
						</label>
					</div>
				</label>
			</div>
			<div class="center"><button onclick="saveVMSettings();return false;">Save</button></div>
		</form>
	</div>
</div>