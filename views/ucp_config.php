<script>
$('.extension-checkbox').change(function(event){
	var ext = $(this).data('extension');
	var name = $(this).data('name');
	if($(this).is(':checked')) {
		$('#vm-ext-list').append('<div class="vm-extensions" data-extension="'+ext+'"><label><input type="checkbox" name="ucp|voicemail[]" value="'+ext+'" checked> '+name+' &lt;'+ext+'&gt;</label><br /></div>');
	} else {
		$('.vm-extensions[data-extension="'+ext+'"]').remove();
	}
});
</script>
<table>
	<tr>
    	<td><a href="#" class="info"><?php echo _("Allowed Voicemail")?>:<span><?php echo _("These are the assigned and active extensions which will show up for this user to control and edit in UCP")?></span></a></td>
		<td>
			<div id="vm-ext-list" class="extensions-list">
			<?php foreach($fpbxusers as $fpbxuser) {?>
				<div class="vm-extensions" data-extension="<?php echo $fpbxuser['ext']?>"><label><input type="checkbox" name="ucp|voicemail[]" value="<?php echo $fpbxuser['ext']?>" <?php echo $fpbxuser['selected'] ? 'checked' : '' ?>> <?php echo $fpbxuser['data']['name']?> &lt;<?php echo $fpbxuser['ext']?>&gt;</label><br /></div>
			<?php } ?>
			</div>
		</td>
	</tr>
</table>