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
                	
			if (marked > 0) load_content_dom(event.path);
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
					break;
				case '/admin/announcements':
					load_announcements_panel();
					break;
			}
			$("#loading").addClass("fadeOut");
		},
		url: ajax_url
	});
}

function load_announcements_panel() {
	$('.date').datetimepicker();
	$('.announcement-form').ajaxForm({
		dataType: 'json',
		complete: function(xhr) {
			defaultAJAXCompletionHandler(xhr);
			if (xhr.status == 200 && !xhr.responseJSON.error) {
				load_content_dom('/admin/announcements');
			}
		}
	});
	$('.announcement-form input[value="Delete"]').click(function(e){
		var item = $(e.target);
		var container = item.closest('form.announcement-form').parent();
		$.ajax({
			type: 'POST',
			url: '/admin/announcement/del',
			data: {'guid': item.attr('data-guid')}
		}).done(function(data){
			if (data.status && data.status == 'success') {
				container.toggle(500);
				displayAlert('success', data.message, function(){
					$('body .alert-top').addClass('animated');
					$('body .alert-top').addClass('fadeOut');
				}, 5000);
			} else {
				displayAlert('danger', data.error_description + ' <em>(' + data.error + ')</em>', function(){
					$('body .alert-top').addClass('animated');
					$('body .alert-top').addClass('fadeOut');
				}, 5000);
			}
		});
	});
}

function load_users_panel() {

	$('.selectpicker').selectpicker();

	$('#roles-form').ajaxForm({
		dataType: 'json',
		complete: function(xhr) {
			defaultAJAXCompletionHandler(xhr);
			if (xhr.status == 200 && !xhr.responseJSON.error) load_content_dom('/admin/users');
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
			if (xhr.status == 200 && !xhr.responseJSON.error) $('#search-submit').click();
			defaultAJAXCompletionHandler(xhr);
		}
	});
	
	$('#add-user-form').ajaxForm({
		dataType: 'json',
		beforeSubmit: function(formData, jqForm) {
			var role_selector = $('#add-user-form #role-selector');
			var role_styler = role_selector.parent().find('button[data-id="role-selector"]');
			role_styler.removeClass('btn-warning');
			if (role_selector.val() == "") {
				role_styler.addClass('btn-warning');
				displayAlert('warning', 'Please choose a role from the dropdown list.', function(){}, 0);
				return false;
			}
		},
		complete: defaultAJAXCompletionHandler
	});
}

function load_assignments_panel() {
	$('.date').datetimepicker();
	$('.selectpicker').selectpicker();
	
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
			$('.has-error').removeClass('has-error');
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
			if (xhr.status == 200) {
				if (xhr.responseJSON.error) {
					r.html("<span class=\"text-danger\">" +  xhr.responseJSON.error_description+ " (error: " + xhr.responseJSON.error + ")</span>");
					if (xhr.responseJSON.error == 'invalid_script_path') {
						var s = jqForm.find('[name="grader_script"]');
						s.parent().parent().addClass('has-error');
						s.focus();
					} else if (xhr.responseJSON.error == 'invalid_tar_path') {
						var s = jqForm.find('[name="grader_tar"]');
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
	
	$('#get-gradebook-form').submit(function(){
		var query_string = $('#get-gradebook-form select').fieldSerialize();
		window.open('/admin/gradebook?' + query_string, 'GradeBookWindow', 400, 400);
		return false;
	});
}

function load_submissions_panel() {
	$('.date').datetimepicker();
	$('.selectpicker').selectpicker();
	$('#search-submission-form').ajaxForm({
		dataType: 'json',
		beforeSubmit: function(formData, jqForm) {
			$('#submission-record-form #response').html('');
		},
		complete: function(xhr, status, jqForm) {
			if (status == 'success') {
				$('#search-submission-form #response').html('<span class="text-success">Found ' + xhr.responseJSON.count + ' record(s).</span>');
				$('#submissions-table-body').html(xhr.responseJSON.message);
			}
		}
	});
	
	$('#submission-record-form').ajaxForm({
		dataType: 'json',
		beforeSubmit: function(formData, jqForm) {
			var action_selector = $('#submission-record-form #action-selector');
			var action_selector_styler = action_selector.parent().find('button[data-id="action-selector"]');
			action_selector_styler.removeClass('btn-danger');
			// action_selector_styler.addClass('btn-default');
			if (action_selector.val() == '') {
				action_selector_styler.addClass('btn-danger');
				// action_selector_styler.addClass('btn-default');
				return false;
			}
		},
		complete: function(xhr, status, jqForm) {
			console.log(xhr);
			if (xhr.status == 200) {
				if (xhr.responseJSON.error) {
					$('#submission-record-form #response').html('<span class="text-danger">' + xhr.responseJSON.error_description + '</span>');
				} else {
					$('#submission-record-form #response').html('<span class="text-success">' + xhr.responseJSON.message + '</span>');
				}
			}
		}
	});
	
}

function defaultAJAXCompletionHandler(xhr) {
	if (xhr.status == 200) {
		if (xhr.responseJSON.error) {
			displayAlert('danger', xhr.responseJSON.error_description + ' <em>(' + xhr.responseJSON.error + ')</em>', function(){
			}, 0);
		} else {
			displayAlert('success', xhr.responseJSON.message, function(){
				$('body .alert-top').addClass('animated');
				$('body .alert-top').addClass('fadeOut');
			}, 5000);
		}
	} else {
		displayAlert('danger', xhr.responseText, function(){}, 0);
	}
}

function displayAlert(level, msg, callback, timer) {
	$('body .alert-top').remove();
	$('body').append('<div class="alert alert-top alert-' + level + '"><a href="#" class="close" data-dismiss="alert">&times;</a>' + msg + '</div>');
	if (callback) setTimeout(callback, timer);
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
