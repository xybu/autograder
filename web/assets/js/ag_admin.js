$(document).ready(function() {
	
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
	if (ajax_url == "/") ajax_url = "/admin/status";
	console.log(ajax_url);
	$("#loading").removeClass("fadeOut");
	$("#loading").addClass("slideInRight");
	$.ajax({
		cache: false,
		complete: function(event) {
			console.log(event);
			$("#content").html(event.responseText);
			$('body').on('hidden.bs.modal', '.modal', function () {
				$(this).removeData('bs.modal');
			});
			switch (ajax_url) {
				case '/admin/users':
					load_users_panel();
					break;
			}
			$("#loading").removeClass("slideInRight");
			$("#loading").addClass("fadeOut");
		},
		url: ajax_url
	});
}

function load_users_panel() {

	$('.selectpicker').selectpicker();

	$('#roles-form').ajaxForm({
		dataType: 'json',
		beforeSubmit: function(formData, jqForm) {
			$('#roles-form-response').html('');
		},
		complete: function(xhr) {
			if (xhr.status == 200) {
				if (xhr.responseJSON.error) {
					$('#roles-form-response').html("<span class=\"text-danger\">" +  xhr.responseJSON.error_description+ " (error: " + xhr.responseJSON.error + ")</span>");
				} else {
					load_content_dom('/admin/users');
				}
			} else {
				$('#roles-form-response').text(xhr.responseText);
			}
		}
	});
	
	$('#search-user-form').ajaxForm({
		dataType: 'json',
		complete: function(xhr) {
			if (xhr.responseJSON.error) {
				$('#users-table-body').html('<tr><td colspan="4"><span class="text-warning text-center">' + xhr.responseJSON.error_description + '</span></td></tr>');
			} else {
				$('#users-table-body').html(xhr.responseJSON.message);
			}
		}
	});
	
	var action_selector = $('#action-selector');
	$('#role-selector').selectpicker('hide');
	action_selector.change(function(event){
		var submit_button = $('#submit_button');
		$('#role-selector').selectpicker('hide');
		$('#email_form').addClass('hide');
		submit_button.removeAttr('disabled');
		submit_button.removeClass('btn-default');
		submit_button.addClass('btn-success');
		switch (action_selector.val()) {
			case 'update':
				break;
			case 'delete':
				break;
			case 'send_email':
				$('#email_form').removeClass('hide');
				break;
			case 'change_role':
				$('#role-selector').selectpicker('show');
				break
			case 'reset_password':
				break;
			default:
				submit_button.removeClass('btn-success');
				submit_button.addClass('btn-default');
				submit_button.attr('disabled', 'disabled');
				break;
		}
	});
	
	$('#update-user-form').ajaxForm({
		dataType: 'json',
		complete: function(xhr) {
			if (xhr.status == 200) {
				if (xhr.responseJSON.error) {
					$('#update-user-response').html("<span class=\"text-danger\">" +  xhr.responseJSON.error_description+ " (error: " + xhr.responseJSON.error + ")</span>");
				} else {
					$('#update-user-response').html("<span class=\"text-success\">" +  xhr.responseJSON.message + '</span>');
					$('#search-submit').click();
				}
			} else {
				$('#update-user-response').text(xhr.responseText);
			}
		}
	});
	
	$('#add-user-form').ajaxForm({
		dataType: 'json',
		beforeSubmit: function(formData, jqForm) {
			if ($('#add-user-form #role-selector').val() == "") {
				$('#add-user-response').html("<span class=\"text-warning\">Please choose a role from the drop-down list.</span>");
				return false;
			}
			$('#add-user-response').html('');
		},
		complete: function(xhr) {
			if (xhr.status == 200) {
				if (xhr.responseJSON.error) {
					$('#add-user-response').html("<span class=\"text-danger\">" +  xhr.responseJSON.error_description+ " (error: " + xhr.responseJSON.error + ")</span>");
				} else {
					$('#add-user-response').html("<span class=\"text-success\">" +  xhr.responseJSON.message + "</span>");
				}
			} else {
				$('#add-user-response').text(xhr.responseText);
			}
		}
	});
}

function selectAll(ref, parent_id) {
	$('#' + parent_id + " tr td:first-child input").each(function(name, obj){
		obj.checked = ref.checked;
	});
}