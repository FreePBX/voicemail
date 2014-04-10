var Voicemail_poll = function(data) {
	if(data.status) {
		var notify = 0;
		if($('#voicemail-badge').html() < data.total) {
			notify = data.total - $('#voicemail-badge').html();
		}
		$('#voicemail-badge').html(data.total);
		$.each( data.boxes, function( extension, messages ) {
			if($('#voicemail-'+extension+'-badge').html() < messages) {
				notify = (messages - $('#voicemail-badge').html()) + notify;
			}
			$('#voicemail-'+extension+'-badge').html(messages);
		});
		var plural = notify > 1 ? 's' : '';
		var voicemailNotification = new Notify('Voicemail', {
			body: 'You Have '+notify+' New Voicemail'+plural,
			icon: 'modules/Voicemail/assets/images/mail.png'
		});
		if(notify > 0) {
			if(!Notify.needsPermission()) {
				voicemailNotification.show();
			}
			//reload the page
			$('.mailbox .folder-list .folder.active a').click();
		}
	}
};
