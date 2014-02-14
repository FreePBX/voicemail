$(function() {
	if (Modernizr.draganddrop) {
		// Browser supports HTML5 DnD.
		
		enable_drags();

		$('.mailbox .folder-list .folder').on('drop', function (event) {
			if (event.stopPropagation) {
				event.stopPropagation(); // Stops some browsers from redirecting.
			}
		    if (event.preventDefault) {
				event.preventDefault(); // Necessary. Allows us to drop.
		    }
			//do teh folder move stuff here
			var msg = event.originalEvent.dataTransfer.getData("msg");
			var folder = $(event.currentTarget).data('folder');
			var data = {"msg":msg,"folder":folder}
			voicemail_ajax('moveToFolder',data)
			if(true) {
				$(this).removeClass("hover");
				var dragSrc = $('.message-list .vm-message[data-msg="'+msg+'"]');
				dragSrc.remove();
				$(".vm-temp").remove();
				var badge = $(event.currentTarget).find('.badge');
				badge.text(Number(badge.text()) + 1);
				var badge = $('.mailbox .folder-list .folder.active').find('.badge');
				badge.text(Number(badge.text()) - 1);
			}
		});
		$('.mailbox .folder-list .folder').on('dragover', function (event) {
		    if (event.preventDefault) {
				event.preventDefault(); // Necessary. Allows us to drop.
		    }
			$(this).addClass("hover");
		});
		$('.mailbox .folder-list .folder').on('dragenter', function (event) {
			$(this).addClass("hover");
		});
		$('.mailbox .folder-list .folder').on('dragleave', function (event) {
			$(this).removeClass("hover");
		});
	} else {
		// Fallback to a library solution.
		console.log('no drag')
	}
	//clear old binds
	$(document).off('click', '[vm-pjax] a, a[vm-pjax]');
	$(document).on('click', '[vm-pjax] a, a[vm-pjax]', function(event) {
		var container = $('#dashboard-content')
		$.pjax.click(event, {container: container})
		enable_drags();
	})
	
    $("#freepbx_player").jPlayer({
        ready: function(event) {

        },
        swfPath: "assets/js",
        supplied: supportedMediaFormats,
		warningAlerts: false,
		cssSelectorAncestor: "#freepbx_player_1"
    });
	
	$("#freepbx_player").bind($.jPlayer.event.play, function(event) { // Add a listener to report the time play began
		$('.vm-message[data-msg="'+loaded+'"] .subplay').css('background-position', '24px 0px');
	});

	$("#freepbx_player").bind($.jPlayer.event.pause, function(event) { // Add a listener to report the time play began
		$('.vm-message[data-msg="'+loaded+'"] .subplay').css('background-position', '0px 0px');
	});
})
var voicemail = function() {
	
}
var loaded = null;
function vmplay(msgid) {
	var player = $('#freepbx_player')
	var cid = $('.vm-message[data-msg="'+msgid+'"] .cid').text()
	if(player.data().jPlayer.status.paused && loaded != msgid) {		
		player.jPlayer( "setMedia", {
			wav: "http://freepbxdev1.schmoozecom.net/ucp/index.php?quietmode=1&module=voicemail&command=listen&msgid="+msgid+"&format=wav&ext="+extension,
			oga: "http://freepbxdev1.schmoozecom.net/ucp/index.php?quietmode=1&module=voicemail&command=listen&msgid="+msgid+"&format=ogg&ext="+extension
		});
		loaded = msgid;
		if($('.jp-audio').is(':hidden')) {
			$("#title-text").text(cid);
			$('.jp-audio').slideDown(function(event){
				player.jPlayer("play")
				$('.vm-message[data-msg="'+msgid+'"] .subplay').css('background-position', '24px 0px');
			})
		} else {
			player.jPlayer("play", 0)
			$('.vm-message[data-msg="'+msgid+'"] .subplay').css('background-position', '24px 0px');
			$("#title-text").text(cid);
		}
	} else if(player.data().jPlayer.status.paused && loaded == msgid) {
		player.jPlayer("play")
		$('.vm-message[data-msg="'+msgid+'"] .subplay').css('background-position', '24px 0px');
		$("#title-text").text(cid);
	} else {
		player.jPlayer("pause")
		$('.vm-message[data-msg="'+msgid+'"] .subplay').css('background-position', '0px 0px');
	}
}

function voicemail_ajax(command,data) {
	$.post( "index.php?quietmode=1&module=voicemail&command="+command, data, function( data ) {
		if(data.status) {
			return true;
		} else {
			return false;
		}
	});
}

function enable_drags() {
	$('.mailbox .vm-message').on('drop', function (event) {
	});
	$('.mailbox .vm-message').on('dragstart', function (event) {
		$(this).fadeTo( "fast" , 0.5);
		event.originalEvent.dataTransfer.effectAllowed = 'move';
	    event.originalEvent.dataTransfer.setData('msg', $(this).data("msg"));
	});
	$('.mailbox .vm-message').on('dragend', function (event) {
		$(".vm-temp").remove();
	    $(this).fadeTo( "fast" , 0.99);
	});
	$('.mailbox .vm-message').on('dragenter', function (event) {
		$(".vm-temp").remove();
		$(this).before( '<tr class="vm-temp" data-msg="h"><td colspan="7">&nbsp;</td></tr>' );
		$('.vm-temp').on('dragover', function (event) {
		    if (event.preventDefault) {
				event.preventDefault(); // Necessary. Allows us to drop.
		    }
		});
		$('.vm-temp').on('drop', function (event) {
			if (event.stopPropagation) {
				event.stopPropagation(); // Stops some browsers from redirecting.
			}
			if(true) {
				var msg = event.originalEvent.dataTransfer.getData("msg");
				var dragSrc = $('.message-list .vm-message[data-msg="'+msg+'"]');
				$(this).replaceWith('<tr class="vm-message" data-msg="'+msg+'" draggable="true">'+dragSrc.html()+'</tr>');
				dragSrc.remove();
				enable_drags();
			}
		});
	})
}