<div class="col-md-10">
	<?php if(!empty($message)) { ?>
		<div class="alert alert-<?php echo $message['type']?>"><?php echo $message['message']?></div>
	<?php } ?>
	<?php if($settings['options']['delete'] == "yes") {?>
		<div class="alert alert-warning"><?php echo _("Voicemail Auto Delete is on. New messages will not show up here.")?></div>
	<?php } ?>
	<div id="voicemail-toolbar">
		<button id="delete-selection" class="btn btn-danger" disabled>
			<i class="glyphicon glyphicon-remove"></i> <span><?php echo _('Delete')?></span>
		</button>
		<button id="forward-selection" class="btn btn-default" disabled>
			<i class="fa fa-share"></i> <span><?php echo _('Forward')?></span>
		</button>
		<button id="move-selection" class="btn btn-default" disabled>
			<i class="fa fa-arrows"></i> <span><?php echo _('Move')?></span>
		</button>
		<div style="display: inline-block;vertical-align: bottom;">
			<div class="onoffswitch">
				<input type="checkbox" class="onoffswitch-checkbox" id="vm-refresh" value="yes" <?php echo !isset($_COOKIE['vm-refresh']) || !empty($_COOKIE['vm-refresh']) ? "checked" : ""?>>
				<label class="onoffswitch-label" for="vm-refresh">
					<div class="onoffswitch-inner"></div>
					<div class="onoffswitch-switch"></div>
				</label>
			</div>
		</div>
		<div style="display: inline-block;vertical-align: bottom;">
			<label for="vm-refresh" class="help"><?php echo _('Auto-Refresh')?></label>
		</div>
	</div>
	<table id="voicemail-grid"
				data-url="index.php?quietmode=1&amp;module=voicemail&amp;command=grid&amp;folder=<?php echo htmlentities($folder)?>&amp;ext=<?php echo htmlentities($ext)?>"
				data-cache="false"
				data-toolbar="#voicemail-toolbar"
				data-cookie="true"
				data-cookie-id-table="ucp-voicemail-table-<?php echo $folder?>"
				data-maintain-selected="true"
				data-show-columns="true"
				data-show-toggle="true"
				data-toggle="table"
				data-sort-order="desc"
				data-sort-name="origtime"
				data-pagination="true"
				data-side-pagination="server"
				data-unique-id="msg_id"
				data-show-refresh="true"
				data-silent-sort="false"
				data-mobile-responsive="true"
				data-check-on-init="true"
				data-min-width="992"
				class="table table-hover">
		<thead>
					<tr class="message-header">
						<th data-checkbox="true"></th>
						<th data-field="origtime" data-sortable="true" data-formatter="UCP.Modules.Voicemail.dateFormatter"><?php echo _("Date")?></th>
						<th data-field="callerid" data-sortable="true"><?php echo _("CID")?></th>
						<?php if($showPlayback) { ?>
							<th data-field="playback" data-formatter="UCP.Modules.Voicemail.playbackFormatter"><?php echo _("Playback")?></th>
						<?php } ?>
						<th data-field="duration" data-sortable="true" data-formatter="UCP.Modules.Voicemail.durationFormatter"><?php echo _("Duration")?></th>
						<th data-field="controls" data-formatter="UCP.Modules.Voicemail.controlFormatter"><?php echo _("Controls")?></th>
				</tr>
		</thead>
</table>
</div>
