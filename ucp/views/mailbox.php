<div class="col-md-10">
	<?php if(!empty($message)) { ?>
		<div class="alert alert-<?php echo $message['type']?>"><?php echo $message['message']?></div>
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
</div>
	<table id="voicemail-grid"
				data-url="index.php?quietmode=1&amp;module=voicemail&amp;command=grid&amp;folder=<?php echo $folder?>&amp;ext=<?php echo $ext?>"
				data-cache="false"
				data-state-save="true"
				data-toolbar="#voicemail-toolbar"
				data-state-save-id-table="ucp-voicemail-table"
				data-maintain-selected="true"
				data-show-columns="true"
				data-show-toggle="true"
				data-toggle="table"
				data-pagination="true"
				data-side-pagination="server"
				data-unique-id="msg_id"
				data-show-refresh="true"
				class="table table-hover table-bordered cdr-table">
		<thead>
					<tr class="message-header">
						<th data-checkbox="true"></th>
						<th data-field="origtime" data-formatter="UCP.Modules.Voicemail.dateFormatter"><?php echo _("Date")?></th>
						<th data-field="callerid"><?php echo _("CID")?></th>
						<th data-field="playback" data-formatter="UCP.Modules.Voicemail.playbackFormatter"><?php echo _("Playback")?></th>
						<th data-field="duration" data-formatter="UCP.Modules.Voicemail.durationFormatter"><?php echo _("Duration")?></th>
						<th data-field="controls" data-formatter="UCP.Modules.Voicemail.controlFormatter"><?php echo _("Controls")?></th>
				</tr>
		</thead>
</table>
</div>
