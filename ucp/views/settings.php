<div class="col-md-10">
	<h3>Voicemail Settings</h3>
	<div class="vmsettings">
		<form id="vmsettings">
			<table class="tablesettings">
				<tr>
					<td>Voicemail Password</td>
					<td><input type="text" name="pwd" value="<?php echo $settings['pwd']?>" autocapitalize="off" autocorrect="off"></td>
				</tr>
				<tr>
					<td>Email Address</td>
					<td><input type="text" name="email" value="<?php echo $settings['email']?>" placeholder="user@domain.tld" autocapitalize="off" autocorrect="off"></td>
				</tr>
				<tr>
					<td>Pager Email Address</td>
					<td><input type="text" name="pager" value="<?php echo $settings['pager']?>" placeholder="user@domain.tld" autocapitalize="off" autocorrect="off"></td>
				</tr>
				<tr>
					<td>Play CID</td>
					<td>
						<div class="onoffswitch">
							<input type="checkbox" name="saycid" class="onoffswitch-checkbox" id="saycid" <?php echo ($settings['options']['saycid'] == 'yes') ? 'checked' : ''?> value="yes">
							<label class="onoffswitch-label" for="saycid">
								<div class="onoffswitch-inner"></div>
								<div class="onoffswitch-switch"></div>
							</label>
						</div>
					</td>
				</tr>
				<tr>
					<td>Play Envelope</td>
					<td>
						<div class="onoffswitch">
							<input type="checkbox" name="envelope" class="onoffswitch-checkbox" id="envelope" <?php echo ($settings['options']['envelope'] == 'yes') ? 'checked' : ''?> value="yes">
							<label class="onoffswitch-label" for="envelope">
								<div class="onoffswitch-inner"></div>
								<div class="onoffswitch-switch"></div>
							</label>
						</div>
					</td>
				</tr>
			</table>
			<div class="center"><button onclick="saveVMSettings();return false;">Save</button></div>
		</form>
	</div>
</div>