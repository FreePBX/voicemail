var VoicemailC = UCPMC.extend({
	init: function() {
		this.loaded = null;
		this.recording = false;
		this.recorder = null;
		this.recordTimer = null;
		this.startTime = null;
		this.soundBlobs = {};
		this.placeholders = [];
	},
	getInfo: function() {
		return { name: _("Voicemail") };
	},
	settingsDisplay: function() {
		var $this = this;
		$("#ddial, #vmx-p1_enable").change(function() {
			$this.findmeFollowState();
		});
		this.findmeFollowState();
		$("#module-Voicemail form .input-group").each(function( index ) {
			$(this).find("input[type=\"text\"]").prop("disabled", !$(this).find("input[type=\"checkbox\"]").is(":checked"));
		});
		$("#module-Voicemail input[type=\"text\"]").change(function() {
			$(this).blur(function() {
				$this.saveVmXSettings($(this).prop("name"), $(this).val());
				$(this).off("blur");
			});
		});
		$("#module-Voicemail .input-group input[type=\"checkbox\"]").change(function() {
			var el = $(this).data("el");
			if (!$(this).is(":checked")) {
				$("#" + el).prop("disabled", true);
				$("#" + el).prop("placeholder", $("#" + el).data("ph"));
				if ($("#" + el).val() !== "") {
					$("#" + el).val("");
					$this.saveVmXSettings($("#" + el).prop("name"), "");
				}
			} else {
				$("#" + el).prop("placeholder", "");
				$("#" + el).prop("disabled", false);
			}
		});

		$("#module-Voicemail .dests input[type=\"checkbox\"]").change(function() {
			$this.saveVmXSettings($(this).prop("name"), $(this).is(":checked"));
		});
	},
	settingsHide: function() {
		$("#module-Voicemail input[type=\"text\"], #module-Findmefollow textarea").off("change");
		$("#module-Voicemail input[type=\"checkbox\"]").off("change");
		$("#ddial, #vmx-p1_enable").off("change");
	},
	findmeFollowState: function() {
		if (!$("#vmx-p1_enable").is(":checked") && !$("#ddial").is(":checked")) {
			$("#vmxerror").text(_("Find me Follow me is disabled when VmX locator option 1 is disabled as well!")).addClass("alert-danger").fadeIn("fast");
		} else {
			$("#vmxerror").fadeOut("fast");
		}
	},
	saveVmXSettings: function(key, value) {
		var data = { ext: ext, settings: { key: key, value: value } };
		$.post( "index.php?quietmode=1&module=voicemail&command=vmxsettings", data, function( data ) {
			if (data.status) {
				$("#vmxmessage").text(data.message).addClass("alert-" + data.alert).fadeIn("fast", function() {
					$(this).delay(5000).fadeOut("fast", function() {
						$(".masonry-container").packery();
					});
				});
				$(".masonry-container").packery();
			} else {
				return false;
			}
		});
	},
	poll: function(data) {
		if (data.status) {
			var notify = 0,
			voicemailNotification = {};
			if ($("#voicemail-badge").html() < data.total) {
				notify = data.total - $("#voicemail-badge").html();
			}
			$("#voicemail-badge").html(data.total);
			$.each( data.boxes, function( extension, messages ) {
				if ($("#voicemail-" + extension + "-badge").html() < messages) {
					notify = (messages - $("#voicemail-badge").html()) + notify;
				}
				$("#voicemail-" + extension + "-badge").html(messages);
			});
			voicemailNotification = new Notify("Voicemail", {
				body: sprintf(_("You Have %s New Voicemail"), notify),
				icon: "modules/Voicemail/assets/images/mail.png"
			});
			if (notify > 0) {
				if (UCP.notify) {
					voicemailNotification.show();
				}
				//reload the page
				$(".mailbox .folder-list .folder.active a").click();
			}
		}
	},
	display: function(event) {
		var $this = this;
		$this.init();
		//If browser doesnt support get user media requests then just hide it from the display
		if (!Modernizr.getusermedia) {
			$(".jp-record-wrapper").hide();
			$(".record-greeting-btn").hide();
		} else {
			$(".jp-record-wrapper").show();
			$(".jp-stop-wrapper").hide();
			$(".record-greeting-btn").show();
		}

		$(".vm-message a.play").click(function() {
			var id = $(this).data("id");
			$this.playVoicemail(id);
		});
		$(".vm-message a.delete").click(function() {
			var id = $(this).data("id");
			$this.deleteVoicemail(id);
		});
		$(".recording-controls .save").click(function() {
			var id = $(this).data("id");
			$this.saveRecording(id);
		});
		$(".recording-controls .delete").click(function() {
			var id = $(this).data("id");
			$this.deleteRecording(id);
		});
		$(".file-controls .record, .jp-record").click(function() {
			var id = $(this).data("id");
			$this.recordGreeting(id);
		});
		$(".file-controls .delete").click(function() {
			var id = $(this).data("id");
			$this.deleteGreeting(id);
		});
		//Nothing on this page will really work without drag and drop at this point
		if (Modernizr.draganddrop) {
			/* MailBox Binds */
			$this.enableDrags();

			//Bind to the mailbox folders, listen for a drop
			$(".mailbox .folder-list .folder").on("drop", function(event) {
				if (event.stopPropagation) {
					event.stopPropagation(); // Stops some browsers from redirecting.
				}
				if (event.preventDefault) {
					event.preventDefault(); // Necessary. Allows us to drop.
				}
				var msg = event.originalEvent.dataTransfer.getData("msg"),
				folder = $(event.currentTarget).data("folder"),
				data = { msg:msg, folder:folder, ext:extension };
				$.post( "index.php?quietmode=1&module=voicemail&command=moveToFolder", data, function( data ) {
					if (data.status) {
						$(this).removeClass("hover");
						var dragSrc = $(".message-list .vm-message[data-msg=\"" + msg + "\"]"),
						badge = null;
						dragSrc.remove();
						$(".vm-temp").remove();
						badge = $(event.currentTarget).find(".badge");
						badge.text(Number(badge.text()) + 1);

						badge = $(".mailbox .folder-list .folder.active").find(".badge");
						badge.text(Number(badge.text()) - 1);
						$("#freepbx_player_" + msg).jPlayer("pause");
						$("#vm_playback_" + msg).remove();
					} else {
						//nothing
					}
				});
			});

			//Mailbox drag over event
			$(".mailbox .folder-list .folder").on("dragover", function(event) {
				if (event.preventDefault) {
					event.preventDefault(); // Necessary. Allows us to drop.
				}
				//Just do a hover image
				$(this).addClass("hover");
			});

			//Mailbox drag enter, entering a drag event
			$(".mailbox .folder-list .folder").on("dragenter", function(event) {
				//Add hover class
				$(this).addClass("hover");
			});

			//Mailbox drag leave, leaving a drag element
			$(".mailbox .folder-list .folder").on("dragleave", function(event) {
				//remove hover class
				$(this).removeClass("hover");
			});
			/** END MAILBOX BINDS **/

			/** START GREETING BINDS **/
			//Bind to drag start for the html5 audio element
			$(".greeting-control .jp-audio").on("dragstart", function(event) {
				event.originalEvent.dataTransfer.effectAllowed = "move";
				event.originalEvent.dataTransfer.setData("type", $(this).data("type"));
				$(this).fadeTo( "fast", 0.5);
			});
			$(".greeting-control .jp-audio").on("dragend", function(event) {
				$(this).fadeTo( "fast", 1.0);
			});

			//Bind to the file drop, we are already bound from the jquery file handler
			//but we bind again to pick up "copy" events, to which file drop will ignore
			$(".filedrop").on("drop", function(event) {
				//Make sure there are no files coming from the desktop
				if (event.originalEvent.dataTransfer.files.length === 0) {
					if (event.stopPropagation) {
						event.stopPropagation(); // Stops some browsers from redirecting.
					}
					if (event.preventDefault) {
						event.preventDefault(); // Necessary. Allows us to drop.
					}
					//remove the hover event
					$(this).removeClass("hover");

					//get our type
					var target = $(this).data("type"),
					//ger the incoming type
					source = event.originalEvent.dataTransfer.getData("type");
					//dont allow other things to be dragged to this, just ignore them
					if (source === "") {
						alert(_("Not a valid Draggable Object"));
						return false;
					}
					//prevent dragging onto self, useless copying
					if (source == target) {
						alert(_("Dragging to yourself is not allowed"));
						return false;
					}

					//Send copy ajax
					var data = { ext: extension, source: source, target: target },
					message = $(this).find(".message");
					message.text(_("Copying..."));
					$.post( "index.php?quietmode=1&module=voicemail&command=copy", data, function( data ) {
						if (data.status) {
							$("#freepbx_player_" + target).jPlayer( "setMedia", {
								wav: "?quietmode=1&module=voicemail&command=listen&msgid=" + target + "&format=wav&ext=" + extension,
								oga: "?quietmode=1&module=voicemail&command=listen&msgid=" + target + "&format=oga&ext=" + extension
							});
							message.text(_("Drag a New Greeting Here"));
							$this.toggleGreeting(target, true);
						} else {
							return false;
						}
					});
				}
			});

			$(".filedrop").on("dragover", function(event) {
				if (event.preventDefault) {
					event.preventDefault(); // Necessary. Allows us to drop.
				}
				$(this).addClass("hover");
			});
			$(".filedrop").on("dragleave", function(event) {
				$(this).removeClass("hover");
			});
			/** END GREETING BINDS **/
		} else {
			// Fallback to a library solution?
			alert(_("You have No Drag/Drop Support!"));

		}
		//clear old binds
		$(document).off("click", "[vm-pjax] a, a[vm-pjax]");
		//then rebind!
		if ($.support.pjax) {
			$(document).on("click", "[vm-pjax] a, a[vm-pjax]", function(event) {
				var container = $("#dashboard-content");
				$.pjax.click(event, { container: container });
				$this.enableDrags();
			});
		}

		/* END MESSAGE PLAYER BINDS */

		/* GREETING PLAYER BINDS */
		$("#freepbx_player_unavail").jPlayer({
			ready: function(event) {
				$(this).jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=wav&ext=" + extension,
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=oga&ext=" + extension
				});
			},
			swfPath: "assets/js",
			supplied: supportedMediaFormats,
			warningAlerts: false,
			cssSelectorAncestor: "#freepbx_player_unavail_1"
		});
		$("#unavail input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=unavail&ext=" + extension,
			dropZone: $("#unavail .filedrop"),
			dataType: "json",
			add: function(e, data) {
				$("#unavail .filedrop .message").text(_("Uploading..."));
				data.submit();
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#unavail .filedrop .pbar").css("width", "0%");
					$("#unavail .filedrop .message").text(_("Drag a New Greeting Here"));
					$("#freepbx_player_unavail").jPlayer( "setMedia", {
						wav: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
						oga: "?quietmode=1&module=voicemail&command=listen&msgid=unavail&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
					});
					$this.toggleGreeting("unavail", true);
				} else {
					console.log(data.result.message);
				}
			},
			progressall: function(e, data) {
				var progress = parseInt(data.loaded / data.total * 100, 10);
				$("#unavail .filedrop .pbar").css("width", progress + "%");
			},
			drop: function(e, data) {
				$("#unavail .filedrop").removeClass("hover");
			}
		});

		$("#freepbx_player_busy").jPlayer({
			ready: function(event) {
				$(this).jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=wav&ext=" + extension + "&rand=" + $this.generateRandom() + "&rand=" + $this.generateRandom(),
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=oga&ext=" + extension + "&rand=" + $this.generateRandom() + "&rand=" + $this.generateRandom()
				});
			},
			swfPath: "assets/js",
			supplied: supportedMediaFormats,
			warningAlerts: false,
			cssSelectorAncestor: "#freepbx_player_busy_1"
		});
		$("#busy input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=busy&ext=" + extension,
			dropZone: $("#busy .filedrop"),
			dataType: "json",
			add: function(e, data) {
				$("#busy .filedrop .message").text(_("Uploading..."));
				data.submit();
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#busy .filedrop .pbar").css("width", "0%");
					$("#busy .filedrop .message").text(_("Drag a New Greeting Here"));
					$("#freepbx_player_busy").jPlayer( "setMedia", {
						wav: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
						oga: "?quietmode=1&module=voicemail&command=listen&msgid=busy&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
					});
					$this.toggleGreeting("busy", true);
				} else {
					console.log(data.result.message);
				}
			},
			progressall: function(e, data) {
				var progress = parseInt(data.loaded / data.total * 100, 10);
				$("#busy .filedrop .pbar").css("width", progress + "%");
			},
			drop: function(e, data) {
				$("#busy .filedrop").removeClass("hover");
			}
		});

		$("#freepbx_player_greet").jPlayer({
			ready: function(event) {
				$(this).jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
				});
			},
			swfPath: "assets/js",
			supplied: supportedMediaFormats,
			warningAlerts: false,
			cssSelectorAncestor: "#freepbx_player_greet_1"
		});
		$("#greet input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=greet&ext=" + extension,
			dropZone: $("#greet .filedrop"),
			dataType: "json",
			add: function(e, data) {
				$("#greet .filedrop .message").text(_("Uploading..."));
				data.submit();
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#greet .filedrop .pbar").css("width", "0%");
					$("#greet .filedrop .message").text("Drag a New Greeting Here");
					$("#freepbx_player_greet").jPlayer( "setMedia", {
						wav: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
						oga: "?quietmode=1&module=voicemail&command=listen&msgid=greet&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
					});
					$this.toggleGreeting("greet", true);
				} else {
					console.log(data.result.message);
				}
			},
			progressall: function(e, data) {
				var progress = parseInt(data.loaded / data.total * 100, 10);
				$("#greet .filedrop .pbar").css("width", progress + "%");
			},
			drop: function(e, data) {
				$.each(data.files, function(index, file) {
					//alert('Dropped file: ' + file.name);
				});
				$("#greet .filedrop").removeClass("hover");
				$("#greet .filedrop .message").text(_("Uploading..."));
			}
		});

		$("#freepbx_player_temp").jPlayer({
			ready: function(event) {
				$(this).jPlayer( "setMedia", {
					wav: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
					oga: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
				});
			},
			swfPath: "assets/js",
			supplied: supportedMediaFormats,
			warningAlerts: false,
			cssSelectorAncestor: "#freepbx_player_temp_1"
		});
		$("#temp input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=temp&ext=" + extension,
			dropZone: $("#temp .filedrop"),
			dataType: "json",
			add: function(e, data) {
				$("#temp .filedrop .message").text(_("Uploading..."));
				data.submit();
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#temp .filedrop .pbar").css("width", "0%");
					$("#temp .filedrop .message").text(_("Drag a New Greeting Here"));
					$("#freepbx_player_temp").jPlayer( "setMedia", {
						wav: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
						oga: "?quietmode=1&module=voicemail&command=listen&msgid=temp&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
					});
					$this.toggleGreeting("temp", true);
				} else {
					console.log(data.result.message);
				}
			},
			progressall: function(e, data) {
				var progress = parseInt(data.loaded / data.total * 100, 10);
				$("#temp .filedrop .pbar").css("width", progress + "%");
			},
			drop: function(e, data) {
				$("#temp .filedrop").removeClass("hover");
			}
		});
		/* END GREETING PLAYER BINDS */

		/* Settings changes binds */
		$(".vmsettings input[type!=\"checkbox\"]").change(function() {
			$(this).blur(function() {
				$this.saveVMSettings();
				$(this).off("blur");
			});
		});
		$(".vmsettings input[type=\"checkbox\"]").change(function() {
			$this.saveVMSettings();
		});
		/* end settings changes binds */
	},
	hide: function(event) {
		$(".vm-message a.play").off("click");
		$(".vm-message a.delete").off("click");
	},
	//Added a "No Voicemail Messages Message"
	CheckNoMessages: function() {
		if (!$(".vm-message").length) {
			//TODO: add no messages
		}
	},
	//Delete a voicemail greeting
	deleteGreeting: function(type) {
		var $this = this, data = { msg: type, ext: extension };
		$.post( "index.php?quietmode=1&module=voicemail&command=delete", data, function( data ) {
			if (data.status) {
				$this.toggleGreeting(type, false);
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
			} else {
				return false;
			}
		});
	},
	//Used to play a voicemail message
	playVoicemail: function(msgid) {
		var player = $("#freepbx_player_" + msgid),
				cid = $(".vm-message[data-msg=\"" + msgid + "\"] .cid").text(),
				container = $("#vm_playback_" + msgid),
				icon = $(".vm-message[data-msg=\"" + msgid + "\"] .play i");

		/* MESSAGE PLAYER BINDS */
		player.jPlayer({
			ready: function(event) {},
			swfPath: "assets/js",
			supplied: supportedMediaFormats, //this is dynamic from the page
			warningAlerts: false,
			cssSelectorAncestor: "#freepbx_container_" + msgid
		}).bind($.jPlayer.event.loadstart, function(event) {
			$("#freepbx_container_" + msgid + " .jp-message-window").show();
			$("#freepbx_container_" + msgid + " .jp-message-window .message").css("color","");
			$("#freepbx_container_" + msgid + " .jp-seek-bar").css("background", 'url("modules/Cdr/assets/images/jplayer.blue.monday.seeking.gif") 0 0 repeat-x');
		});
		//play binds

		player.bind($.jPlayer.event.play, function(event) {
			icon.removeClass("fa-play").addClass("fa-pause");
		});

		player.bind($.jPlayer.event.pause, function(event) {
			icon.removeClass("fa-pause").addClass("fa-play");
		});

		player.bind($.jPlayer.event.canplay, function(event) {
			$(".jp-message-window").fadeOut("fast");
			$("#freepbx_container_" + msgid + " .jp-seek-bar").css("background","");
		});

		player.bind($.jPlayer.event.error, function(event) {
			$("#freepbx_container_" + msgid + " .jp-message-window").show();
			$("#freepbx_container_" + msgid + " .message").text(event.jPlayer.error.message).css("color","red");
			$("#freepbx_container_" + msgid + " .jp-seek-bar").css("background","");
		});

		if (this.loaded !== null && this.loaded != msgid) {
			$("#freepbx_player_" + this.loaded).jPlayer("pause");
			$("#vm_playback_" + this.loaded).slideUp("fast");
		}

		this.loaded = msgid;

		if (player.data().jPlayer.status.waitForLoad) {
			player.jPlayer( "setMedia", {
				wav: "?quietmode=1&module=voicemail&command=listen&msgid=" + msgid + "&format=wav&ext=" + extension,
				oga: "?quietmode=1&module=voicemail&command=listen&msgid=" + msgid + "&format=oga&ext=" + extension
			});
			if (container.is(":hidden")) {
				$("#freepbx_container_" + msgid + " .title-text").text(cid);
				container.slideDown(function(event) {
					player.jPlayer("play");
					icon.removeClass("fa-play").addClass("fa-pause");
				});
			} else {
				player.jPlayer("play", 0);
				icon.removeClass("fa-play").addClass("fa-pause");
				$("#freepbx_container_" + msgid + " .title-text").text(cid);
			}
		} else {
			if (player.data().jPlayer.status.paused) {
				if (container.is(":hidden")) {
					$("#freepbx_container_" + msgid + " .title-text").text(cid);
					container.slideDown(function(event) {
						player.jPlayer("play", 0);
						icon.removeClass("fa-play").addClass("fa-pause");
					});
				} else {
					player.jPlayer("play");
				}
			} else {
				player.jPlayer("pause");
			}
		}
	},
	//Used to delete a voicemail message
	deleteVoicemail: function(msgid) {
		var $this = this,
				player = $("#freepbx_player"),
				data = { msg: msgid, ext: extension };
		if (confirm(_("Are you sure you wish to delete this voicemail?"))) {
			if ($(".jp-audio").is(":visible") && $this.loaded == msgid) {
				$(".jp-audio").slideUp();
				player.jPlayer("pause");
			}
			$.post( "index.php?quietmode=1&module=voicemail&command=delete", data, function( data ) {
				if (data.status) {
					$(".vm-message[data-msg=\"" + msgid + "\"]").fadeOut("fast", function() {
						$this.CheckNoMessages();
					});
					var num = $(".mailbox .folder-list .folder.active .badge").text();
					$(".mailbox .folder-list .folder.active .badge").text(num - 1);
					num = $("#voicemail-badge").text();
					$("#voicemail-badge").text(num - 1);
				} else {
					return false;
				}
			});
		}
	},
	//Toggle the html5 player for greeting
	toggleGreeting: function(type, visible) {
		if (visible === true) {
			$("#" + type + " button").fadeIn();
			$("#freepbx_player_" + type + "_1").slideDown();
		} else {
			$("#" + type + " button").fadeOut();
			$("#freepbx_player_" + type + "_1").slideUp();
		}
	},
	//Save Voicemail Settings
	saveVMSettings: function() {
		$("#message").fadeOut("slow");
		var data = { ext: extension };
		$(".vmsettings input[type!=\"checkbox\"]").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).val();
		});
		$(".vmsettings input[type=\"checkbox\"]").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).is(":checked");
		});
		$.post( "?quietmode=1&module=voicemail&command=savesettings", data, function( data ) {
			if (data.status) {
				$("#message").addClass("alert-success");
				$("#message").text("Saved!");
				$("#message").fadeIn( "slow", function() {
					setTimeout(function() { $("#message").fadeOut("slow"); }, 2000);
				});
			} else {
				$("#message").addClass("alert-error");
				$("#message").text(data.message);
				return false;
			}
		});
	},
	//Enables all draggable elements
	enableDrags: function() {
		$(".mailbox .vm-message").on("drop", function(event) {
		});
		$(".mailbox .vm-message").on("dragstart", function(event) {
			$(this).fadeTo( "fast", 0.5);
			event.originalEvent.dataTransfer.effectAllowed = "move";
			event.originalEvent.dataTransfer.setData("msg", $(this).data("msg"));
		});
		$(".mailbox .vm-message").on("dragend", function(event) {
			$(".vm-temp").remove();
			$(this).fadeTo( "fast", 1.0);
		});
		$(".mailbox .vm-message").on("dragenter", function(event) {
		});
	},
	recordGreeting: function(type) {
		var $this = this;
		if (!Modernizr.getusermedia) {
			alert(_("Direct Media Recording is Unsupported in your Broswer!"));
			return false;
		}
		counter = $("#" + type + " .jp-current-time");
		title = $("#" + type + " .title-text");
		filec = $("#" + type + " .file-controls");
		recc = $("#" + type + " .recording-controls");
		if ($this.recording) {
			clearInterval($this.recordTimer);
			title.text("Recorded Message");
			$this.recorder.stop();
			$this.recorder.exportWAV(function(blob) {
				$this.soundBlobs[type] = blob;
				var url = (window.URL || window.webkitURL).createObjectURL(blob);
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				$("#freepbx_player_" + type).jPlayer( "setMedia", {
					wav: url
				});
			});
			$this.recording = false;
		} else {
			window.AudioContext = window.AudioContext || window.webkitAudioContext;

			var context = new AudioContext();

			var gUM = Modernizr.prefixed("getUserMedia", navigator);
			gUM({ audio: true }, function(stream) {
				var mediaStreamSource = context.createMediaStreamSource(stream);
				$this.recorder = new Recorder(mediaStreamSource,{ workerPath: "assets/js/recorderWorker.js" });
				$this.recorder.record();
				$this.startTime = new Date();
				$this.recordTimer = setInterval(function () {
					var mil = (new Date() - $this.startTime);
					var temp = (mil / 1000);
					var min = ("0" + Math.floor((temp %= 3600) / 60)).slice(-2);
					var sec = ("0" + Math.round(temp % 60)).slice(-2);
					counter.text(min + ":" + sec);
				}, 1000);
				title.text(_("Recording..."));
				$this.recording = true;
				filec.hide();
				recc.show();
				$("#" + type + " .jp-audio").slideDown();
			}, function(e) {
				alert(_("Your Browser Blocked The Recording, Please check your settings"));
				$this.recording = false;
			});
		}
	},
	saveRecording: function(type) {
		var $this = this;
		title = $("#" + type + " .title-text");
		if ($this.recording) {
			alert(_("Stop the Recording First before trying to save"));
			return false;
		}
		if ((typeof($this.soundBlobs[type]) !== "undefined") && $this.soundBlobs[type] !== null) {
			$("#" + type + " .filedrop .message").text(_("Uploading..."));
			var data = new FormData();
			data.append("file", $this.soundBlobs[type]);
			$.ajax({
				type: "POST",
				url: "index.php?quietmode=1&module=voicemail&command=record&type=" + type + "&ext=" + extension,
				xhr: function()
				{
					var xhr = new window.XMLHttpRequest();
					//Upload progress
					xhr.upload.addEventListener("progress", function(evt) {
						if (evt.lengthComputable) {
							var percentComplete = evt.loaded / evt.total,
							progress = Math.round(percentComplete * 100);
							$("#" + type + " .filedrop .pbar").css("width", progress + "%");
						}
					}, false);
					return xhr;
				},
				data: data,
				processData: false,
				contentType: false,
				success: function(data) {
					$("#" + type + " .filedrop .message").text("Drag a New Greeting Here");
					$("#" + type + " .filedrop .pbar").css("width", "0%");
					$this.soundBlobs[type] = null;
					filec.show();
					recc.hide();
					$("#freepbx_player_" + type).jPlayer( "clearMedia" );
					$("#freepbx_player_" + type).jPlayer( "setMedia", {
						wav: "?quietmode=1&module=voicemail&command=listen&msgid=" + type + "&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
						oga: "?quietmode=1&module=voicemail&command=listen&msgid=" + type + "&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
					});
					title.text(title.data("title"));
				},
				error: function() {
					//error
				}
			});
		}
	},
	deleteRecording: function(type) {
		var $this = this;
		if ($this.recording) {
			alert(_("Stop the Recording First before trying to delete"));
			return false;
		}
		if ((typeof($this.soundBlobs[type]) !== "undefined") && $this.soundBlobs[type] !== null) {
			$this.soundBlobs[type] = null;
			filec.show();
			recc.hide();
			$("#freepbx_player_" + type).jPlayer( "clearMedia" );
			$("#freepbx_player_" + type).jPlayer( "setMedia", {
				wav: "?quietmode=1&module=voicemail&command=listen&msgid=" + type + "&format=wav&ext=" + extension + "&rand=" + $this.generateRandom(),
				oga: "?quietmode=1&module=voicemail&command=listen&msgid=" + type + "&format=oga&ext=" + extension + "&rand=" + $this.generateRandom()
			});
			title.text(title.data("title"));
		} else {
			alert("There is nothing to delete");
		}
	},
	//This function is here solely because firefox caches media downloads so we have to force it to not do that
	generateRandom: function() {
		return Math.round(new Date().getTime() / 1000);
	}
});
