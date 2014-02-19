$(document).on('pjax:end', function() {
	//stylize();
	//resizeContent();
})
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
			var data = {msg:msg,folder:folder,ext:extension}
			$.post( "index.php?quietmode=1&module=voicemail&command=moveToFolder", data, function( data ) {
				if(data.status) {
					$(this).removeClass("hover");
					var dragSrc = $('.message-list .vm-message[data-msg="'+msg+'"]');
					dragSrc.remove();
					$(".vm-temp").remove();
					var badge = $(event.currentTarget).find('.badge');
					badge.text(Number(badge.text()) + 1);
					var badge = $('.mailbox .folder-list .folder.active').find('.badge');
					badge.text(Number(badge.text()) - 1);
				} else {
					//nothing
				}
			});
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

		$('.filedrop').on('dragover', function (event) {
		    if (event.preventDefault) {
				event.preventDefault(); // Necessary. Allows us to drop.
		    }
			$(this).addClass("hover");
		});
		$('.filedrop').on('dragleave',function (event) {
			$(this).removeClass("hover");
		});
		
	} else {
		// Fallback to a library solution.
		console.log('no drag');

	}
	//clear old binds
	$(document).off('click', '[vm-pjax] a, a[vm-pjax]');
	$(document).on('click', '[vm-pjax] a, a[vm-pjax]', function(event) {
		event.preventDefault()
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
	
    $("#freepbx_player_unavail").jPlayer({
        ready: function(event) {
			$(this).jPlayer( "setMedia", {
				wav: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=wav&ext="+extension,
				oga: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=oga&ext="+extension
			});
        },
        swfPath: "assets/js",
        supplied: supportedMediaFormats,
		warningAlerts: false,
		cssSelectorAncestor: "#freepbx_player_unavail_1"
    });
    $('#unavail input[type="file"]').fileupload({
		url: '?quietmode=1&module=voicemail&command=upload&type=unavail&ext='+extension,
		dropZone: $('#unavail .filedrop'),
        dataType: 'json',
        add: function (e, data) {
			$('#unavail .filedrop span').text('Uploading...');
            data.submit();
        },
        done: function (e, data) {
			if(data.result.status) {
		        $('#unavail .filedrop .pbar').css('width','0%');
				$('#unavail .filedrop span').text('Drag a New Greeting Here')
				$("#freepbx_player_unavail").jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=wav&ext="+extension,
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=oga&ext="+extension
				});
				togglegreeting('unavail',true)
			} else {
				console.log(data.result.message);
			}
        },
		progressall: function (e, data) {
	        var progress = parseInt(data.loaded / data.total * 100, 10);
	        $('#unavail .filedrop .pbar').css('width',progress + '%');
		},
	    drop: function (e, data) {
			$('#unavail .filedrop').removeClass("hover");
	    }
    });
	
    $("#freepbx_player_busy").jPlayer({
        ready: function(event) {
			$(this).jPlayer( "setMedia", {
				wav: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=wav&ext="+extension,
				oga: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=oga&ext="+extension
			});
        },
        swfPath: "assets/js",
        supplied: supportedMediaFormats,
		warningAlerts: false,
		cssSelectorAncestor: "#freepbx_player_busy_1"
    });
    $('#busy input[type="file"]').fileupload({
		url: '?quietmode=1&module=voicemail&command=upload&type=busy&ext='+extension,
		dropZone: $('#busy .filedrop'),
        dataType: 'json',
        add: function (e, data) {
			$('#busy .filedrop span').text('Uploading...');
            data.submit();
        },
        done: function (e, data) {
			if(data.result.status) {
		        $('#busy .filedrop .pbar').css('width','0%');
				$('#busy .filedrop span').text('Drag a New Greeting Here')
				$("#freepbx_player_busy").jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=wav&ext="+extension,
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=oga&ext="+extension
				});
				togglegreeting('busy',true)
			} else {
				console.log(data.result.message);
			}
        },
		progressall: function (e, data) {
	        var progress = parseInt(data.loaded / data.total * 100, 10);
	        $('#busy .filedrop .pbar').css('width',progress + '%');
		},
	    drop: function (e, data) {
			$('#busy .filedrop').removeClass("hover");
	    }
    });
	
    $("#freepbx_player_greet").jPlayer({
        ready: function(event) {
			$(this).jPlayer( "setMedia", {
				wav: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=wav&ext="+extension,
				oga: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=oga&ext="+extension
			});
        },
        swfPath: "assets/js",
        supplied: supportedMediaFormats,
		warningAlerts: false,
		cssSelectorAncestor: "#freepbx_player_greet_1"
    });
    $('#greet input[type="file"]').fileupload({
		url: '?quietmode=1&module=voicemail&command=upload&type=greet&ext='+extension,
		dropZone: $('#greet .filedrop'),
        dataType: 'json',
        add: function (e, data) {
            $('#greet .filedrop span').text('Uploading...');
            data.submit();
        },
        done: function (e, data) {
			if(data.result.status) {
		        $('#greet .filedrop .pbar').css('width','0%');
				$('#greet .filedrop span').text('Drag a New Greeting Here')
				$("#freepbx_player_greet").jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=wav&ext="+extension,
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=oga&ext="+extension
				});
				togglegreeting('greet',true)
			} else {
				console.log(data.result.message);
			}
        },
		progressall: function (e, data) {
	        var progress = parseInt(data.loaded / data.total * 100, 10);
	        $('#greet .filedrop .pbar').css('width',progress + '%');
		},
	    drop: function (e, data) {
	        $.each(data.files, function (index, file) {
	            //alert('Dropped file: ' + file.name);
	        });
			$('#greet .filedrop').removeClass("hover");
			$('#greet .filedrop span').text('Uploading...');
	    }
    });
	
    $("#freepbx_player_temp").jPlayer({
        ready: function(event) {
			$(this).jPlayer( "setMedia", {
				wav: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=wav&ext="+extension,
				oga: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=oga&ext="+extension
			});
        },
        swfPath: "assets/js",
        supplied: supportedMediaFormats,
		warningAlerts: false,
		cssSelectorAncestor: "#freepbx_player_temp_1"
    });
    $('#temp input[type="file"]').fileupload({
		url: '?quietmode=1&module=voicemail&command=upload&type=temp&ext='+extension,
		dropZone: $('#temp .filedrop'),
        dataType: 'json',
        add: function (e, data) {
			$('#temp .filedrop span').text('Uploading...');
            data.submit();
        },
        done: function (e, data) {
			if(data.result.status) {
		        $('#temp .filedrop .pbar').css('width','0%');
				$('#temp .filedrop span').text('Drag a New Greeting Here')
				$("#freepbx_player_temp").jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=wav&ext="+extension,
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=oga&ext="+extension
				});
				togglegreeting('temp',true)
			} else {
				console.log(data.result.message);
			}
        },
		progressall: function (e, data) {
	        var progress = parseInt(data.loaded / data.total * 100, 10);
	        $('#temp .filedrop .pbar').css('width',progress + '%');
		},
	    drop: function (e, data) {
			$('#temp .filedrop').removeClass("hover");
	    }
    });
})

var loaded = null;
function vmplay(msgid) {
	var player = $('#freepbx_player')
	var cid = $('.vm-message[data-msg="'+msgid+'"] .cid').text()
	if(player.data().jPlayer.status.paused && loaded != msgid) {		
		player.jPlayer( "setMedia", {
			wav: "?quietmode=1&module=voicemail&command=listen&msgid="+msgid+"&format=wav&ext="+extension,
			oga: "?quietmode=1&module=voicemail&command=listen&msgid="+msgid+"&format=oga&ext="+extension
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

function vmdel(msgid) {
	if($('.jp-audio').is(':visible') && loaded == msgid) {
		$('.jp-audio').slideUp();
	}
	var data = {msg: msgid, ext: extension};
	$.post( "index.php?quietmode=1&module=voicemail&command=delete", data, function( data ) {
		if(data.status) {
			$('.vm-message[data-msg="'+msgid+'"]').fadeOut('fast');
		} else {
			return false;
		}
	});
}

function addNoMessages() {
	if(!$('.vm-message').length) {
		//add no messages
	}
}

function greetingdelete(type) {
	var data = {msg: type, ext: extension};
	$.post( "index.php?quietmode=1&module=voicemail&command=delete", data, function( data ) {
		if(data.status) {
			togglegreeting(type,false);
		} else {
			return false;
		}
	});
}

function togglegreeting(type,visible) {
	if(visible == true) {
		$('#'+type+' button').fadeIn();
		$('#freepbx_player_'+type+'_1').slideDown();
	} else {
		$('#'+type+' button').fadeOut();
		$('#freepbx_player_'+type+'_1').slideUp();
	}
}

function saveVMSettings() {
	$('#message').fadeOut("slow");
	var data = {ext: extension}
	$('.vmsettings input[type="text"]').each(function( index ) {
		data[$( this ).attr('name')] = $( this ).val()
	});
	$('.vmsettings input[type="checkbox"]').each(function( index ) {
		data[$( this ).attr('name')] = $( this ).is(':checked')
	});
	$.post( "index.php?quietmode=1&module=voicemail&command=savesettings", data, function( data ) {
		if(data.status) {
			$('#message').addClass('alert-success')
			$('#message').text('Saved!')
			$('#message').fadeIn( "slow", function() {
				setTimeout(function() { $('#message').fadeOut("slow"); }, 2000);
			});;
		} else {
			$('#message').addClass('alert-error')
			$('#message').text(data.message)
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