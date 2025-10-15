<style>
.delete-folder-btn {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    padding: 0;
    border: none;
    background-color: #dc3545;
    color: white;
    font-size: 6px;
    line-height: 1;
    display: inline-block;
    vertical-align: middle;
}

.delete-folder-btn:hover {
    background-color: #c82333;
    color: white;
}

.delete-folder-btn:focus {
    box-shadow: none;
    outline: none;
}
</style>

<div class="mailbox"
	data-messages="<?php echo $total?>"
	data-inbox="<?php echo isset($folders['INBOX']) && isset($folders['INBOX']['count']) ? $folders['INBOX']['count'] : 0 ?>"
	data-show-download="<?php echo ($showDownload) ? 'true' : 'false'?>"
	data-show-playback="<?php echo ($showPlayback) ? 'true' : 'false'?>"
	data-show-playback="<?php echo ($showPlayback) ? 'true' : 'false'?>"
	data-supported-regular-expression="<?php echo $supportedRegExp?>"
	data-supported-html5="<?php echo $supportedHTML5?>"
	data-mailboxes="<?php echo json_encode($mailboxes)?>"
	>
	<div class="row">
		<div class="col-md-3">
			<div class="folder-list">
				<!-- Folder Actions -->
				<div class="folder-actions mb-2">
					<button type="button" class="btn btn-sm btn-primary create-folder-btn" data-extension="<?php echo htmlentities($extension)?>" title="<?php echo _('Create New Folder')?>">
						<i class="fa fa-plus"></i> <?php echo _('Create Folder')?>
					</button>
					<button type="button" class="btn btn-sm btn-default refresh-folders-btn" data-extension="<?php echo htmlentities($extension)?>" title="<?php echo _('Refresh Folder List')?>">
						<i class="fa fa-refresh"></i> <?php echo _('Refresh')?>
					</button>
				</div>
			                        <table class="table table-hover folder-list-table">
                <thead>
                    <tr class="folder-header">
                        <th class="folder-name-header">Folder</th>
                        <th class="folder-icon-header"><i class="fa fa-inbox" title="Total Messages"></i></th>
                        <th class="folder-icon-header"><i class="fa fa-envelope-open" title="Unread Messages"></i></th>
                        <th class="folder-icon-header"><i class="fa fa-exclamation-triangle" title="High Priority Messages"></i></th>
                        <th class="folder-icon-header"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($folders as $folderKey => $folderData) {
                        $isActive = (isset($folder) && $folderData['key'] == $folder) || (!isset($folder) && $folderKey == 'INBOX');
                        $isSystemFolder = in_array(strtoupper($folderKey), ['INBOX', 'OLD']);
                    ?>
                        <tr class="folder <?php echo $isActive ? 'active' : ''?>" data-name="<?php echo $folderData['label']?>" data-folder="<?php echo $folderKey?>">
                            <td class="folder-name"><?php echo $folderData['label']?></td>
                            <td class="folder-badge"><span class="badge badge-total" title="Total Messages"><?php echo isset($folderData['count']) ? $folderData['count'] : 0?></span></td>
                            <td class="folder-badge"><span class="badge badge-unread" title="Unread Messages"><?php echo isset($folderData['unread']) ? $folderData['unread'] : 0?></span></td>
                            <td class="folder-badge"><span class="badge badge-high" title="High Priority Messages"><?php echo isset($folderData['high_priority']) ? $folderData['high_priority'] : 0?></span></td>
                            <td class="folder-actions">
                                <?php if (!$isSystemFolder): ?>
                                <button type="button" class="btn btn-xs btn-danger delete-folder-btn" 
                                        data-extension="<?php echo htmlentities($extension)?>" 
                                        data-folder="<?php echo htmlentities($folderKey)?>" 
                                        data-folder-name="<?php echo htmlentities($folderData['label'])?>"
                                        title="<?php echo _('Delete Folder')?>">
                                    <i class="fa fa-minus"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php }?>
                </tbody>
            </table>
			</div>
		</div>
		<div class="col-md-9">
			<?php if(!empty($message)) { ?>
				<div class="alert alert-<?php echo $message['type']?>"><?php echo $message['message']?></div>
			<?php } ?>
			<?php 
			// Check if settings exist and the specific option is set before accessing
			if(isset($settings['options']) && isset($settings['options']['delete']) && $settings['options']['delete'] == "yes") {
			?>
				<div class="alert alert-warning"><?php echo _("Voicemail Auto Delete is on. New messages will not show up here.")?></div>
			<?php } ?>
			<div id="voicemail-toolbar-<?php echo $extension?>" class="btn-toolbar" role="toolbar">
				<!-- Bulk Action Toggle Button -->
				<button class="btn btn-default bulk-actions-toggle">
					<i class="fa fa-check-square-o"></i> <span><?php echo _('Bulk Actions')?></span>
				</button>

				<!-- Group for actual bulk action buttons (initially hidden) -->
				<div class="btn-group bulk-actions-group" style="display: none;">
					<button class="btn btn-danger delete-selection" disabled>
						<i class="fa fa-trash-o"></i> <span><?php echo _('Delete Selected')?></span>
					</button>
					<button class="btn btn-default forward-selection" disabled>
						<i class="fa fa-share"></i> <span><?php echo _('Forward Selected')?></span>
					</button>
					<button class="btn btn-default move-selection" disabled>
						<i class="fa fa-arrows"></i> <span><?php echo _('Move Selected')?></span>
					</button>
					<!-- Optional: Add Cancel button here if preferred -->
					<!-- <button class="btn btn-warning bulk-actions-cancel"><i class="fa fa-times"></i> <span><?php echo _('Cancel')?></span></button> -->
				</div>
			</div>
			<table class="voicemail-grid"
				data-url="index.php?quietmode=1&amp;module=voicemail&amp;command=grid&amp;folder=<?php echo htmlentities($folder)?>&amp;ext=<?php echo htmlentities($ext)?>"
				data-cache="false"
				data-toolbar="#voicemail-toolbar-<?php echo $extension?>"
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
				class="table table-hover">
				<thead>
					<tr class="message-header">
					</tr>
				</thead>
			</table>
		</div>
	</div>
</div>


<div id="addtionalcontent">
</div>

<style>
/* Temporary styles until LESS compilation */
.folder-list-table {
    margin-bottom: 0;
    width: 100%;
}

.folder-list-table th {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    padding: 6px 8px;
    font-weight: 600;
    color: #333;
    text-align: left;
    font-size: 12px;
}

.folder-list-table td {
    border: 1px solid #ddd;
    border-top: none;
    padding: 6px 8px;
    vertical-align: middle;
    font-size: 12px;
}

.folder-list-table tr:hover {
    background-color: #f5f5f5;
}

.folder-list-table tr.active {
    background-color: #e3f2fd;
    color: #1976d2;
}

.folder-list-table tr.active td {
    color: #e3f2fd;
}

.folder-list-table .folder-icon-header {
    text-align: center;
    width: 30px;
}

.folder-list-table .folder-icon-header i {
    font-size: 10px;
    color: #666;
}

.folder-list-table .folder-badge {
    text-align: center;
    width: 30px;
}

.folder-list-table .folder-badge .badge {
    font-size: 9px;
    padding: 1px 3px;
    border-radius: 2px;
    white-space: nowrap;
    min-width: 12px;
    text-align: center;
    font-weight: 500;
    display: inline-block !important;
}

.folder-list-table .folder-name {
    font-weight: 500;
    color: inherit;
}

.folder-list-table th:first-child {
    font-weight: bold;
}

.folder-list-table .badge-total {
    background-color: #337ab7;
    color: white;
}

.folder-list-table .badge-unread {
    background-color: #f0ad4e;
    color: white;
}

.folder-list-table .badge-high {
    background-color: #d9534f;
    color: white;
}

/* Force grid table auto-sizing */
.voicemail-grid {
    width: auto !important;
    max-width: none !important;
}

.voicemail-grid .bootstrap-table {
    width: auto !important;
}

.voicemail-grid .bootstrap-table .table {
    width: auto !important;
    table-layout: auto !important;
}

.voicemail-grid .bootstrap-table .table th,
.voicemail-grid .bootstrap-table .table td {
    width: auto !important;
    white-space: nowrap !important;
    max-width: none !important;
}

/* Override any Bootstrap Table width calculations */
.voicemail-grid .bootstrap-table .fixed-table-container {
    width: auto !important;
}

/* Drag over styling for table-based folder list */
.folder-list-table tr.folder.drag-over {
    background-color: #e3f2fd !important;
    border: 2px solid #2196f3 !important;
    border-radius: 3px !important;
    opacity: 1 !important;
}

.folder-list-table tr.folder.drag-over td {
    color: #1976d2 !important;
    font-weight: normal !important;
}
</style>

