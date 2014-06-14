$(document).ready(function() {
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
				$("#action_feedback").html("<span class=\"text-success\">You have successfully submitted to this assignment. (Refresh the page to update quota information.)</span>");
				$("#history_body").prepend(xhr.responseJSON.new_record_data);
			} else {
				$("#action_feedback").html("<span class=\"text-danger\">" + xhr.responseJSON.error_description + "</span>");
			}
		}
	});
	
});
