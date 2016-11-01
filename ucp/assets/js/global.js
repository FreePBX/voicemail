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
		var self = this;
		$("#ddial, #vmx-p1_enable, #vmx-state").change(function() {
			self.findmeFollowState();
		});
		this.findmeFollowState();
		$("#module-Voicemail form .input-group").each(function( index ) {
			$(this).find("input[type=\"text\"]").prop("disabled", !$(this).find("input[type=\"checkbox\"]").is(":checked"));
		});
		$("#module-Voicemail input[type=\"text\"]").change(function() {
			$(this).blur(function() {
				self.saveVmXSettings($(this).prop("name"), $(this).val());
				$(this).off("blur");
			});
		});
		$("#module-Voicemail #vmx-state").change(function() {
			self.saveVmXSettings("vmx-state", $(this).is(":checked"));
		});
		$("#module-Voicemail .input-group input[type=\"checkbox\"]").change(function() {
			var el = $(this).data("el");
			if (!$(this).is(":checked")) {
				$("#" + el).prop("disabled", true);
				$("#" + el).prop("placeholder", $("#" + el).data("ph"));
				if ($("#" + el).val() !== "") {
					$("#" + el).val("");
					self.saveVmXSettings($("#" + el).prop("name"), "");
				}
			} else {
				$("#" + el).prop("placeholder", "");
				$("#" + el).prop("disabled", false);
			}
		});

		$("#module-Voicemail .dests input[type=\"checkbox\"]").change(function() {
			self.saveVmXSettings($(this).prop("name"), $(this).is(":checked"));
		});
	},
	settingsHide: function() {
		$("#module-Voicemail input[type=\"text\"], #module-Findmefollow textarea").off("change");
		$("#module-Voicemail input[type=\"checkbox\"]").off("change");
		$("#ddial, #vmx-p1_enable, #vmx-state").off("change");
	},
	findmeFollowState: function() {
		if (!$("#vmx-p1_enable").is(":checked") && $("#ddial").is(":checked") && $("#vmx-state").is(":checked")) {
			$("#vmxerror").text(_("Find me Follow me is enabled when VmX locator option 1 is disabled. This means VmX Locator will be skipped, instead going directly to Find Me/Follow Me")).addClass("alert-danger").fadeIn("fast");
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
		if (typeof this.extension == "undefined") {
			return;
		}

		if (data.status) {
			var notify = 0,
			voicemailNotification = {};
			if (parseInt($("#voicemail-badge").text()) < data.total) {
				notify = data.total - parseInt($("#voicemail-badge").text());
			}
			$("#voicemail-badge").text(data.total);
			$.each( data.boxes, function( extension, messages ) {
				if(typeof extension === "undefined") {
					return false;
				}
				$("#voicemail-" + extension + "-badge").text(messages);
			});
			voicemailNotification = new Notify("Voicemail", {
				body: sprintf(_("You Have %s New Voicemail"), notify),
				icon: "modules/Voicemail/assets/images/mail.png"
			});
			if (notify > 0) {
				if (UCP.notify) {
					voicemailNotification.show();
				}
				this.refreshFolderCount();
				if(typeof Cookies.get('vm-refresh') === "undefined" || Cookies.get('vm-refresh') == 1) {
					$('#voicemail-grid').bootstrapTable('refresh',{silent: true});
				}
			}
		}
	},
	displayWidgetSettings: function(widget_id, dashboard_id) {
		self = this;
		/* Settings changes binds */
		$("li[data-rawname='voicemail'] .widget-settings-content input[type!='checkbox']").change(function() {
			$(this).blur(function() {
				self.saveVMSettings();
				$(this).off("blur");
			});
		});
		$("li[data-rawname='voicemail'] .widget-settings-content input[type='checkbox']").change(function() {
			self.saveVMSettings();
		});
		/* end settings changes binds */
	},
	displayWidget: function(widget_id, dashboard_id) {
		var self = this;
		self.init();
		self.extension = extension;
		//If browser doesnt support get user media requests then just hide it from the display
		if (!Modernizr.getusermedia) {
			$(".jp-record-wrapper").hide();
			$(".record-greeting-btn").hide();
		} else {
			$(".jp-record-wrapper").show();
			$(".jp-stop-wrapper").hide();
			$(".record-greeting-btn").show();
		}

		$('#voicemail-grid').bootstrapTable();
		$("#vm-refresh").change(function() {
			Cookies.remove('vm-refresh', {path: ''});
			if($(this).is(":checked")) {
				Cookies.set('vm-refresh', 1);
			} else {
				Cookies.set('vm-refresh', 0);
			}
		});
		if(typeof Cookies.get('vm-refresh') === "undefined" || Cookies.get('vm-refresh') == 1) {
			$("#vm-refresh").prop("checked",true);
		} else {
			$("#vm-refresh").prop("checked",false);
		}

		if($.url().param("view") == "greetings") {
			self.bindPlayers(Modernizr.getusermedia);
		}

		$('#voicemail-grid').on("post-body.bs.table", function () {
			self.bindPlayers();
			$("#voicemail-grid a.listen").click(function() {
				var id = $(this).data("id"), select = null;
				$.each(mailboxes, function(i,v) {
					select = select + "<option value='"+v+"'>"+v+"</option>";
				});
				UCP.showDialog(_("Listen to Voicemail"),
					_("On") + ":</label><select class=\"form-control\" id=\"VMto\">"+select+"</select><button class=\"btn btn-default\" id=\"listenVM\" style=\"margin-left: 72px;\">" + _("Listen") + "</button>",
					145,
					250,
					function() {
						$("#listenVM").click(function() {
							var recpt = $("#VMto").val();
							self.listenVoicemail(id,recpt);
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								var recpt = $("#VMto").val();
								self.listenVoicemail(id,recpt);
							}
						});
					}
				);
			});
			$("#voicemail-grid .clickable").click(function(e) {
				var text = $(this).text();
				if (UCP.validMethod("Contactmanager", "showActionDialog")) {
					UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
				}
			});
			$("#voicemail-grid a.forward").click(function() {
				var id = $(this).data("id");
				UCP.showDialog(_("Forward Voicemail"),
					_("To")+":</label><select class=\"form-control Fill\" id=\"VMto\"></select><button class=\"btn btn-default\" id=\"forwardVM\" style=\"margin-left: 72px;\">" + _("Forward") + "</button>",
					145,
					250,
					function() {
						$("#VMto").tokenize({
							newElements: false,
							maxElements: 1,
							datas: "index.php?quietmode=1&module=voicemail&command=forwards&ext="+extension
						});
						$("#forwardVM").click(function() {
							setTimeout(function() {
								var recpt = $("#VMto").val()[0];
								self.forwardVoicemail(id,recpt, function(data) {
									if(data.status) {
										alert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										UCP.closeDialog();
									}
								});
							}, 50);
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								setTimeout(function() {
									var recpt = $("#VMto").val()[0];
									self.forwardVoicemail(id,recpt, function(data) {
										if(data.status) {
											alert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
											UCP.closeDialog();
										}
									});
								}, 50);
							}
						});
					}
				);
			});
			$("#voicemail-grid a.delete").click(function() {
				var id = $(this).data("id");
				if (confirm(_("Are you sure you wish to delete this voicemail?"))) {
					self.deleteVoicemail(id, function(data) {
						if(data.status) {
							$('#voicemail-grid').bootstrapTable('remove', {field: "msg_id", values: [String(id)]});
						}
					});
				}
			});
		});
		$('#voicemail-grid').on("check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table", function () {
			var sel = $(this).bootstrapTable('getAllSelections'),
					dis = true;
			if(sel.length) {
				dis = false;
			}
			$("#delete-selection").prop("disabled",dis);
			$("#forward-selection").prop("disabled",dis);
			$("#move-selection").prop("disabled",dis);
		});

		$(".folder").click(function() {
			$(".folder").removeClass("active");
			$(this).addClass("active");
			folder = $(this).data("folder");
			$('#voicemail-grid').bootstrapTable('refresh', {url: 'index.php?quietmode=1&module=voicemail&command=grid&folder='+folder+'&ext='+extension});
		});

		$("#move-selection").click(function() {
			var opts = '', cur = (typeof $.url().param("folder") !== "undefined") ? $.url().param("folder") : "INBOX", sel = $('#voicemail-grid').bootstrapTable('getAllSelections');
			$.each($(".folder-list .folder"), function(i, v){
				var folder = $(v).data("folder");
				if(folder != cur) {
					opts += '<option>'+$(v).data("name")+'</option>';
				}
			});
			UCP.showDialog(_("Move Voicemail"),
				_("To")+":</label><select class=\"form-control\" id=\"VMmove\">"+opts+"</select><button class=\"btn btn-default\" id=\"moveVM\" style=\"margin-left: 72px;\">" + _("Move") + "</button>",
				145,
				250,
				function() {
					var total = sel.length, processed = 0;
					$("#moveVM").click(function() {
						$.each(sel, function(i, v){
							self.moveVoicemail(v.msg_id, $("#VMmove").val(), extension, function(data) {
								if(data.status) {
									$('#voicemail-grid').bootstrapTable('remove', {field: "msg_id", values: [String(v.msg_id)]});
								}
								processed++;
								if(processed == total) {
									UCP.closeDialog();
								}
							});
						});
					});
					$("#VMmove").keypress(function(event) {
						if (event.keyCode == 13) {
							$.each(sel, function(i, v){
								self.moveVoicemail(v.msg_id, $("#VMmove").val(), extension, function(data) {
									if(data.status) {
										$('#voicemail-grid').bootstrapTable('remove', {field: "msg_id", values: [String(v.msg_id)]});
									}
									processed++;
									if(processed == total) {
										UCP.closeDialog();
									}
								});
							});
						}
					});
				}
			);
		});
		$("#delete-selection").click(function() {
			if (confirm(_("Are you sure you wish to delete these voicemails?"))) {
				var sel = $('#voicemail-grid').bootstrapTable('getAllSelections');
				$.each(sel, function(i, v){
					self.deleteVoicemail(v.msg_id, function(data) {
						if(data.status) {
							$('#voicemail-grid').bootstrapTable('remove', {field: "msg_id", values: [String(v.msg_id)]});
						}
					});
				});
				//$('#voicemail-grid').bootstrapTable('refresh');
				$("#delete-selection").prop("disabled",true);
			}
		});
		$("#forward-selection").click(function() {
			var sel = $('#voicemail-grid').bootstrapTable('getAllSelections');
			UCP.showDialog(_("Forward Voicemail"),
				_("To")+":</label><select class=\"form-control Fill\" id=\"VMto\"></select><button class=\"btn btn-default\" id=\"forwardVM\" style=\"margin-left: 72px;\">" + _("Forward") + "</button>",
				145,
				250,
				function() {
					$("#VMto").tokenize({
						newElements: false,
						maxElements: 1,
						datas: "index.php?quietmode=1&module=voicemail&command=forwards&ext="+extension
					});
					$("#forwardVM").click(function() {
						setTimeout(function() {
							var recpt = $("#VMto").val()[0];
							$.each(sel, function(i, v){
								self.forwardVoicemail(v.msg_id,recpt, function(data) {
									if(data.status) {
										alert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$('#voicemail-grid').bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							});
						}, 50);
					});
					$("#VMto").keypress(function(event) {
						if (event.keyCode == 13) {
							setTimeout(function() {
								var recpt = $("#VMto").val()[0];
								$.each(sel, function(i, v){
									self.forwardVoicemail(v.msg_id,recpt, function(data) {
										if(data.status) {
											alert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
											$('#voicemail-grid').bootstrapTable('uncheckAll');
											UCP.closeDialog();
										}
									});
								});
							}, 50);
						}
					});
				}
			);
			$("#forward-selection").prop("disabled",true);
			$('#voicemail-grid').bootstrapTable('uncheckAll');
		});


		$(".clickable").click(function(e) {
			var text = $(this).text();
			if (UCP.validMethod("Contactmanager", "showActionDialog")) {
				UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
			}
		});
		$(".recording-controls .save").click(function() {
			var id = $(this).data("id");
			self.saveRecording(id);
		});
		$(".recording-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteRecording(id);
		});
		$(".file-controls .record, .jp-record").click(function() {
			var id = $(this).data("id");
			self.recordGreeting(id);
		});
		$(".file-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteGreeting(id);
		});
		//Nothing on this page will really work without drag and drop at this point
		if (true) {
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
						var dragSrc = $(".message-list #voicemail-grid[data-msg=\"" + msg + "\"]"),
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
			$(".greeting-control .jp-audio-freepbx").on("dragstart", function(event) {
				event.originalEvent.dataTransfer.effectAllowed = "move";
				event.originalEvent.dataTransfer.setData("type", $(this).data("type"));
				$(this).fadeTo( "fast", 0.5);
			});
			$(".greeting-control .jp-audio-freepbx").on("dragend", function(event) {
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
							$("#freepbx_player_" + target).removeClass("greet-hidden");
							message.text(message.data("message"));
							self.toggleGreeting(target, true);
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
			//alert(_("You have No Drag/Drop Support!"));

		}
		//clear old binds
		$(document).off("click", "[vm-pjax] a, a[vm-pjax]");
		//then rebind!
		if ($.support.pjax) {
			$(document).on("click", "[vm-pjax] a, a[vm-pjax]", function(event) {
				var container = $("#dashboard-content");
				$.pjax.click(event, { container: container });
			});
		}

		/* END MESSAGE PLAYER BINDS */

		/* GREETING PLAYER BINDS */
		$("#unavail input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=unavail&ext=" + extension,
			dropZone: $("#unavail .filedrop"),
			dataType: "json",
			add: function(e, data) {
				//TODO: Need to check all supported formats
				var sup = "\.("+supportedRegExp+")$",
						patt = new RegExp(sup),
						submit = true;
				$.each(data.files, function(k, v) {
					if(!patt.test(v.name)) {
						submit = false;
						alert(_("Unsupported file type"));
						return false;
					}
				});
				if(submit) {
					$("#unavail .filedrop .message").text(_("Uploading..."));
					data.submit();
				}
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#unavail .filedrop .pbar").css("width", "0%");
					$("#unavail .filedrop .message").text($("#unavail .filedrop .message").data("message"));
					$("#freepbx_player_unavail").removeClass("greet-hidden");
					self.toggleGreeting("unavail", true);
				} else {
					console.warn(data.result.message);
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
		$("#busy input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=busy&ext=" + extension,
			dropZone: $("#busy .filedrop"),
			dataType: "json",
			add: function(e, data) {
				//TODO: Need to check all supported formats
				var sup = "\.("+supportedRegExp+")$",
						patt = new RegExp(sup),
						submit = true;
				$.each(data.files, function(k, v) {
					if(!patt.test(v.name)) {
						submit = false;
						alert(_("Unsupported file type"));
						return false;
					}
				});
				if(submit) {
					$("#busy .filedrop .message").text(_("Uploading..."));
					data.submit();
				}
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#busy .filedrop .pbar").css("width", "0%");
					$("#busy .filedrop .message").text($("#busy .filedrop .message").data("message"));
					$("#freepbx_player_busy").removeClass("greet-hidden");
					self.toggleGreeting("busy", true);
				} else {
					console.warn(data.result.message);
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
		$("#greet input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=greet&ext=" + extension,
			dropZone: $("#greet .filedrop"),
			dataType: "json",
			add: function(e, data) {
				//TODO: Need to check all supported formats
				var sup = "\.("+supportedRegExp+")$",
						patt = new RegExp(sup),
						submit = true;
				$.each(data.files, function(k, v) {
					if(!patt.test(v.name)) {
						submit = false;
						alert(_("Unsupported file type"));
						return false;
					}
				});
				if(submit) {
					$("#greet .filedrop .message").text(_("Uploading..."));
					data.submit();
				}
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#greet .filedrop .pbar").css("width", "0%");
					$("#greet .filedrop .message").text($("#greet .filedrop .message").data("message"));
					$("#freepbx_player_greet").removeClass("greet-hidden");
					self.toggleGreeting("greet", true);
				} else {
					console.warn(data.result.message);
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

		$("#temp input[type=\"file\"]").fileupload({
			url: "?quietmode=1&module=voicemail&command=upload&type=temp&ext=" + extension,
			dropZone: $("#temp .filedrop"),
			dataType: "json",
			add: function(e, data) {
				//TODO: Need to check all supported formats
				var sup = "\.("+supportedRegExp+")$",
						patt = new RegExp(sup),
						submit = true;
				$.each(data.files, function(k, v) {
					if(!patt.test(v.name)) {
						submit = false;
						alert(_("Unsupported file type"));
						return false;
					}
				});
				if(submit) {
					$("#temp .filedrop .message").text(_("Uploading..."));
					data.submit();
				}
			},
			done: function(e, data) {
				if (data.result.status) {
					$("#temp .filedrop .pbar").css("width", "0%");
					$("#temp .filedrop .message").text($("#temp .filedrop .message").data("message"));
					$("#freepbx_player_temp").removeClass("greet-hidden");
					self.toggleGreeting("temp", true);
				} else {
					console.warn(data.result.message);
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
	},
	hide: function(event) {
		$("#voicemail-grid a.play").off("click");
		$("#voicemail-grid a.delete").off("click");
	},
	//Delete a voicemail greeting
	deleteGreeting: function(type) {
		var self = this, data = { msg: type, ext: extension };
		$.post( "index.php?quietmode=1&module=voicemail&command=delete", data, function( data ) {
			if (data.status) {
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				self.toggleGreeting(type, false);
			} else {
				return false;
			}
		});
	},
	refreshFolderCount: function() {
		var data = {
			ext: extension
		};
		$.post( "index.php?quietmode=1&module=voicemail&command=refreshfoldercount", data, function( data ) {
			if(data.status) {
				$.each(data.folders, function(i,v) {
					$(".mailbox .folder-list .folder[data-name='"+v.folder+"'] .badge").text(v.count);
				});
			}
		});
	},
	moveVoicemail: function(msgid, folder, extension, callback) {
		var data = {
			msg: msgid,
			folder: folder,
			ext: extension
		},
		self = this;
		$.post( "index.php?quietmode=1&module=voicemail&command=moveToFolder", data, function(data) {
			self.refreshFolderCount();
			if(typeof callback === "function") {
				callback(data);
			}
		});
	},
	forwardVoicemail: function(msgid, recpt, callback) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( "index.php?quietmode=1&module=voicemail&command=forward&ext="+extension, data, function(data) {
			if(typeof callback === "function") {
				callback(data);
			}
		});
	},
	//Used to delete a voicemail message
	deleteVoicemail: function(msgid, callback) {
		var data = {
			msg: msgid,
			ext: extension
		},
		self = this;

		$.post( "index.php?quietmode=1&module=voicemail&command=delete", data, function( data ) {
			self.refreshFolderCount();
			if(typeof callback === "function") {
				callback(data);
			}
		});
	},
	//Toggle the html5 player for greeting
	toggleGreeting: function(type, visible) {
		if (visible === true) {
			$("#" + type + " button.delete").show();
			$("#jp_container_" + type).removeClass("greet-hidden");
			$("#freepbx_player_"+ type).jPlayer( "clearMedia" );
		} else {
			$("#" + type + " button.delete").hide();
			$("#jp_container_" + type).addClass("greet-hidden");
		}
	},
	//Save Voicemail Settings
	saveVMSettings: function() {
		$("#message").fadeOut("slow");
		var data = { ext: extension };
		$("li[data-rawname='voicemail'] .widget-settings-content input[type!='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).val();
		});
		$("li[data-rawname='voicemail'] .widget-settings-content input[type='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).is(":checked");
		});
		$.post( "?quietmode=1&module=voicemail&command=savesettings", data, function( data ) {
			if (data.status) {
				$("#message").addClass("alert-success");
				$("#message").text(_("Your settings have been saved"));
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
	recordGreeting: function(type) {
		var self = this;
		if (!Modernizr.getusermedia) {
			alert(_("Direct Media Recording is Unsupported in your Broswer!"));
			return false;
		}
		counter = $("#jp_container_" + type + " .jp-current-time");
		title = $("#jp_container_" + type + " .title-text");
		filec = $("#" + type + " .file-controls");
		recc = $("#" + type + " .recording-controls");
		var controls = $("#jp_container_" + type + " .jp-controls");
		controls.toggleClass("recording");
		if (self.recording) {
			clearInterval(self.recordTimer);
			title.text(_("Recorded Message"));
			self.recorder.stop();
			self.recorder.exportWAV(function(blob) {
				self.soundBlobs[type] = blob;
				var url = (window.URL || window.webkitURL).createObjectURL(blob);
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				$("#freepbx_player_" + type).jPlayer( "setMedia", {
					wav: url
				});
			});
			self.recording = false;
			recc.show();
			filec.hide();
		} else {
			window.AudioContext = window.AudioContext || window.webkitAudioContext;

			var context = new AudioContext();

			var gUM = Modernizr.prefixed("getUserMedia", navigator);
			gUM({ audio: true }, function(stream) {
				var mediaStreamSource = context.createMediaStreamSource(stream);
				self.recorder = new Recorder(mediaStreamSource,{ workerPath: "assets/js/recorderWorker.js" });
				self.recorder.record();
				self.startTime = new Date();
				self.recordTimer = setInterval(function () {
					var mil = (new Date() - self.startTime);
					var temp = (mil / 1000);
					var min = ("0" + Math.floor((temp %= 3600) / 60)).slice(-2);
					var sec = ("0" + Math.round(temp % 60)).slice(-2);
					counter.text(min + ":" + sec);
				}, 1000);
				title.text(_("Recording..."));
				self.recording = true;
				$("#jp_container_" + type).removeClass("greet-hidden");
				recc.hide();
				filec.show();
			}, function(e) {
				alert(_("Your Browser Blocked The Recording, Please check your settings"));
				self.recording = false;
			});
		}
	},
	saveRecording: function(type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
				title = $("#" + type + " .title-text");
		if (self.recording) {
			alert(_("Stop the Recording First before trying to save"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			$("#" + type + " .filedrop .message").text(_("Uploading..."));
			var data = new FormData();
			data.append("file", self.soundBlobs[type]);
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
					$("#" + type + " .filedrop .message").text($("#" + type + " .filedrop .message").data("message"));
					$("#" + type + " .filedrop .pbar").css("width", "0%");
					self.soundBlobs[type] = null;
					$("#freepbx_player_" + type).jPlayer("supplied",supportedHTML5);
					$("#freepbx_player_" + type).jPlayer( "clearMedia" );
					title.text(title.data("title"));
					filec.show();
					recc.hide();
				},
				error: function() {
					//error
					filec.show();
					recc.hide();
				}
			});
		}
	},
	deleteRecording: function(type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
		if (self.recording) {
			alert(_("Stop the Recording First before trying to delete"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			self.soundBlobs[type] = null;
			$("#freepbx_player_" + type).jPlayer("supplied",supportedHTML5);
			$("#freepbx_player_" + type).jPlayer( "clearMedia" );
			title.text(title.data("title"));
			filec.show();
			recc.hide();
			self.toggleGreeting(type, false);
		} else {
			alert(_("There is nothing to delete"));
		}
	},
	//This function is here solely because firefox caches media downloads so we have to force it to not do that
	generateRandom: function() {
		return Math.round(new Date().getTime() / 1000);
	},
	dateFormatter: function(value, row, index) {
		return UCP.dateFormatter(value);
	},
	listenVoicemail: function(msgid, recpt) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( "index.php?quietmode=1&module=voicemail&command=callme&ext="+extension, data, function( data ) {
			UCP.closeDialog();
		});
	},
	playbackFormatter: function (value, row, index) {
		if(showPlayback === 0 || row.duration === 0) {
			return '';
		}
		return '<div id="jquery_jplayer_'+row.msg_id+'" class="jp-jplayer" data-container="#jp_container_'+row.msg_id+'" data-id="'+row.msg_id+'"></div><div id="jp_container_'+row.msg_id+'" data-player="jquery_jplayer_'+row.msg_id+'" class="jp-audio-freepbx" role="application" aria-label="media player">'+
			'<div class="jp-type-single">'+
				'<div class="jp-gui jp-interface">'+
					'<div class="jp-controls">'+
						'<i class="fa fa-play jp-play"></i>'+
						'<i class="fa fa-undo jp-restart"></i>'+
					'</div>'+
					'<div class="jp-progress">'+
						'<div class="jp-seek-bar progress">'+
							'<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>'+
							'<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>'+
							'<div class="jp-play-bar progress-bar"></div>'+
							'<div class="jp-play-bar">'+
								'<div class="jp-ball"></div>'+
							'</div>'+
							'<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>'+
						'</div>'+
					'</div>'+
					'<div class="jp-volume-controls">'+
						'<i class="fa fa-volume-up jp-mute"></i>'+
						'<i class="fa fa-volume-off jp-unmute"></i>'+
					'</div>'+
				'</div>'+
				'<div class="jp-no-solution">'+
					'<span>Update Required</span>'+
					sprintf(_("You are missing support for playback in this browser. To fully support HTML5 browser playback you will need to install programs that can not be distributed with the PBX. If you'd like to install the binaries needed for these conversions click <a href='%s'>here</a>"),"http://wiki.freepbx.org/display/FOP/Installing+Media+Conversion+Libraries")+
				'</div>'+
			'</div>'+
		'</div>';
	},
	durationFormatter: function (value, row, index) {
		return sprintf(_("%s seconds"),value);
	},
	controlFormatter: function (value, row, index) {
		var html = '<a class="listen" alt="'+_('Listen on your handset')+'" data-id="'+row.msg_id+'"><i class="fa fa-phone"></i></a>'+
						'<a class="forward" alt="'+_('Forward')+'" data-id="'+row.msg_id+'"><i class="fa fa-share"></i></a>';

		if(showDownload === 1) {
			html += '<a class="download" alt="'+_('Download')+'" href="?quietmode=1&amp;module=voicemail&amp;command=download&amp;msgid='+row.msg_id+'&amp;ext='+extension+'"><i class="fa fa-cloud-download"></i></a>';
		}

		html += '<a class="delete" alt="'+_('Delete')+'" data-id="'+row.msg_id+'"><i class="fa fa-trash-o"></i></a>';

		return html;
	},
	bindPlayers: function(getusermedia) {
		var soundBlob = typeof getusermedia !== "undefined" ? getusermedia : false, self = this;
		if(soundBlob) {
			supportedHTML5 = supportedHTML5.split("wav");
			if(supportedHTML5.indexOf("wav") === -1) {
				supportedHTML5.push("wav");
			}
			supportedHTML5 = supportedHTML5.join(",");
		}
		$(".jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					msg_id = $(this).data("id");
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							self.recordGreeting(type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: "index.php?quietmode=1",
								data: {module: "voicemail", command: "gethtml5", msg_id: msg_id, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.warn(event);
										});
										player.one($.jPlayer.event.canplay, function(event) {
											$(container).removeClass("jp-state-loading");
											player.jPlayer("play");
										});
										player.jPlayer( "setMedia", data.files);
									} else {
										alert(data.message);
										$(container).removeClass("jp-state-loading");
									}
								}
							});
						}
					});
					var self = this;
					$(container).find(".jp-restart").click(function() {
						if($(self).data("jPlayer").status.paused) {
							$(self).jPlayer("pause",0);
						} else {
							$(self).jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				autoBlur: false,
				keyEnabled: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$('.jp-play-bar').mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	}
});
