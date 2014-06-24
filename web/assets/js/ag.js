var current_path;

$(document).ready(function() {
	
	if ($("#redirect_hash").length) {
		$("#redirect_hash").val(window.location.hash);
	}
	
	if ($("#forgot_password_form").length) {
		$("#forgot_password_form").ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm) {
				// no need to check if user_id is empty since it is required.
				$("#help_text").html("<p class=\"text-info\">Requesting...</p>")
			},
			complete: function(xhr) {
				if (xhr.status == 200) {
					if (xhr.responseJSON.error) {
						$("#help_text").html("<p class=\"text-danger\">" + xhr.responseJSON.error_description + " (" + xhr.responseJSON.error + ")</p>");
					} else {
						$("#help_text").html("<p class=\"text-success\">" + xhr.responseJSON.message + "</p>");
					}
				} else {
					$("#help_text").html("<p class=\"text-danger\">An error occurred performing the request. If it persists please contact admin.</p>");
				}
			}
		});
	}
	
	if ($("#sidebar").length) {
		var init = true, state = window.history.pushState !== undefined;
		$.address.init(function(event) {
			// init jQuery address plugin
			console.log(event);
		}).change(function(event) {
			
			console.log(event);
			
			var names = $.map(event.pathNames, function(n) {
				return n;
			}).concat(event.parameters.id ? event.parameters.id.split('.') : []);
			var links = names.slice();
                	
                	console.log(names);
                	console.log(links);
                	
                	// process the navigation link
                	var marked = 0;
			$('#sidebar a').each(function() {
				if ($(this).attr('href') == event.path) {
					$(this).addClass('active').focus();
					++marked;
				} else {
					$(this).removeClass('active');
				}
			});
                	
                	if (marked > 0)
	                	load_content_dom(event.path);
	                else {
	                	// the uri is not loadable
	                	$('#content').html('<p class="bg-danger notification-bar">The URI requested is not available. Please select one from the sidebar.</p>');
	                }
		});
		$('#sidebar a').address();
	}
	
});

function load_content_dom(ajax_url) {
	current_path = ajax_url;
	if (ajax_url == "/") ajax_url = "/announcements/html";
	$.ajax({
		cache: false,
		complete: function(event) {
			console.log(event);
			$("#content").html(event.responseText);
			if (ajax_url.indexOf("/assignment/") > -1) {
				init_assignment_detail_page();
			}
		},
		url: ajax_url
	});
}

function init_assignment_detail_page() {

	$('input[type=file]').bootstrapFileInput();
	$('.file-inputs').bootstrapFileInput();
	
	$("#submit_form").ajaxForm({
		url: "/submissions/post",
		dataType: 'json',
		beforeSerialize: function() {
			console.log($("#sourceFileSelector").attr("files"));
			if (!$("#sourceFileSelector").val()) {
				$("#action_feedback").html("<span class=\"text-danger\">Please choose a file to submit.</span>");
				return false;
			}/* else if (file_dom.attr("files")[0].size > 102400){
				prompt_dom.html("<span class=\"text-warning\">File must be an image of size no more than 100KiB.</span>").removeClass("hidden");
				return false;
			}*/
		},
		beforeSend: function() {
			$("#action_feedback").html("(Uploading...)");
		},
		uploadProgress: function(event, position, total, percentComplete) {
			var percentVal = percentComplete + '%';
			$("#action_feedback").html("(Uploading " + percentVal + ")");
		},
		success: function() {
			$("#action_feedback").html("(Uploaded.)");
		},
		complete: function(xhr) {
			if (xhr.responseJSON.error == "success") {
				var str = 'You have successfully submitted the file.';
				if (xhr.responseJSON.more_status == 'error')
					str += ' However, system failed to add the grading task to queue; will retry later.';
				alert(str + ' Click "OK" to refresh the page.');
				load_content_dom(current_path);
			} else {
				$("#action_feedback").html("<span class=\"text-danger\">" + xhr.responseJSON.error_description + "</span>");
			}
		}
	});
	
	$('body').on('hidden.bs.modal', '.modal', function () {
		$(this).removeData('bs.modal');
	});
}