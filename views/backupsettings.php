<input type = 'hidden' name = "voicemail_settings" id = "voicemail_settings">
<table id = "voicemail_backup_settings" class="table table-striped" data-toggle="table" data-id-field="extension">
    <thead>
        <tr>
            <th data-field = 'extension' data-formatter="extenFormatter"><?php echo _('Mailbox');?></th>
            <th data-field = 'egreetings' data-formatter="vmGreetFormatter" data-title-tooltip = "<?php echo _("Should we exclude greetings for this extension ?")?>"><?php echo _('Exclude Greetings');?></th>
            <th data-field = 'emessages' data-formatter="vmMsgsFormatter" data-title-tooltip = "<?php echo _("Should we exclude messages for this extension ?") ?>"><?php echo _('Exclude Messages');?></th>
            <th data-field = 'rpassword' data-formatter="vmRpassFormatter" data-title-tooltip = "<?php echo _("Should we reset the password for this extension ?") ?>"><?php echo _('Regenerate Password');?></th>
        </tr>
    </thead>
    <tbody>

    </tbody>
</table>
<script>
$(function () {
    $('#voicemail_backup_settings').bootstrapTable({
        data: <?php echo json_encode($settings) ?>
    });
});
function extenFormatter(val, row, index){
    return `${row['name']} (${val})`;
}

function vmGreetFormatter(val, row, index){
    var checked = val?'CHECKED':'';
    return `<input type="checkbox" ${checked} name = "voicemail_egreetings_${row['extension']}" class="vmbuinput" value="true"/>`;
}
function vmMsgsFormatter(val, row, index){
    var checked = val?'CHECKED':'';
    return `<input type="checkbox" ${checked} name = "voicemail_emessages_${row['extension']}"class="vmbuinput" value="true"/>`;
}
function vmRpassFormatter(val, row, index){
    var checked = val?'CHECKED':'';
    return `<input type="checkbox" ${checked} name = "voicemail_rpassword_${row['extension']}" class="vmbuinput" value="true"/>`;
}

</script>