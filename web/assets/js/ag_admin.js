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
	if (ajax_url == "/") ajax_url = "/admin/status";
	$.ajax({
		cache: false,
		complete: function(event) {
			console.log(event);
			$("#content").html(event.responseText);
			$('body').on('hidden.bs.modal', '.modal', function () {
				$(this).removeData('bs.modal');
			});
		},
		url: ajax_url
	});
}