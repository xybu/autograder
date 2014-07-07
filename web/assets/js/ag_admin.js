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
	$("#loading").removeClass("fadeOut");
	// $("#loading").addClass("slideInRight");
	if (ajax_url == "/") ajax_url = "/admin/status";
	$.ajax({
		cache: false,
		complete: function(event) {
			$("#content").html(event.responseText);
			$('body').on('hidden.bs.modal', '.modal', function () {
				$(this).removeData('bs.modal');
			});
			switch (ajax_url) {
				case '/admin/users':
					load_users_panel();
					break;
				case '/admin/assignments':
					load_assignments_panel();
					break;
				case '/admin/submissions':
					load_submissions_panel();
					break
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

function load_assignments_panel() {
	$('.date').datetimepicker();
	
	$('.panel-heading').each(function(i, item){makeToggleable(item)});
	$('.form-toggle').each(function(i, item){
		item = $(item);
		item.find('#delete').click(function(e){
			$.ajax({
				type: 'POST',
				url: '/admin/assignments/del',
				data: {'id': $(this).attr('data-target')}	
			}).done(function(data){
				item.parent().remove();
			});
		});
	});
	
	
	
	$('.assignment-item-form').ajaxForm({
		dataType: 'json',
		beforeSubmit: function(formData, jqForm) {
			$('.has-error').each(function(i, item){$(item).removeClass('has-error')});
			var id = jqForm.find('[name="id"]');
			var start = jqForm.find('[name="start"]');
			var close = jqForm.find('[name="close"]');
			if (typeof id.val() === 'undefined' || hasWhiteSpace(id.val())) {
				id.parent().parent().addClass('has-error');
				id.tooltip();
				id.focus();
				return false;
			}
			if (!moment(start.val()).isValid()) {
				start.parent().parent().parent().addClass('has-error');
				start.focus();
				return false
			}
			if (!moment(close.val()).isValid() || !moment(close.val()).isAfter(start.val())) {
				close.parent().parent().parent().addClass('has-error');
				close.focus();
				return false
			}
		},
		complete: function(xhr, status, jqForm) {
			var r = jqForm.find('#response');
			if (status == 'success') {
				if (xhr.responseJSON.error) {
					r.html("<span class=\"text-danger\">" +  xhr.responseJSON.error_description+ " (error: " + xhr.responseJSON.error + ")</span>");
					if (xhr.responseJSON.error == 'invalid_script_path') {
						var s = jqForm.find('[name="grader_script"]');
						s.parent().parent().addClass('has-error');
						s.focus();
					}
				} else {
					if (jqForm.find('[name="internal"]').val() == 'new') {
						$('#newAssignmentModal').modal('hide');
						setTimeout("load_content_dom('/admin/assignments')", 500);
					} else r.html("<span class=\"text-success\">" +  xhr.responseJSON.message+ "</span>");
				}
			} else {
				r.text(xhr.responseText);
			}
		}
	});
}

function load_submissions_panel() {
	$('.date').datetimepicker();
	$('.selectpicker').selectpicker();
}

function makeToggleable(item) {
	item = $(item);
	item.on('click', function(e){
		var t = $(this).parent().find('.form-toggle');
		if (t.hasClass('hide')) {
			item.find('i').removeClass('fa-angle-double-down');
			item.find('i').addClass('fa-angle-double-up');
				t.removeClass('hide');
		} else {
			item.find('i').removeClass('fa-angle-double-up');
			item.find('i').addClass('fa-angle-double-down');
			t.addClass('hide');
		}
	});
	return item;
}

function selectAll(ref, parent_id) {
	$('#' + parent_id + " tr td:first-child input").each(function(name, obj){
		obj.checked = ref.checked;
	});
}

function hasWhiteSpace(s) {
	return /\s/g.test(s);
}