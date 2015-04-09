jQuery(function($){

	// Declare Object
	var seom_changes = [];

	/**
	 * Add the TinyMCE inline editors
	 *
	 * @since    1.0.0
	 */
	tinymce.init({
		selector: "div.editable, span.editable",
		inline: true,
		toolbar: false,
		menubar: false,
		valid_elements: "",
		browser_spellcheck: true,
		forced_root_block: false,
		setup: function(editor) {

			// Set vars
			var seom_field = $("#" + editor.id),
				seom_field_parent = seom_field.closest("tr"),
				content_id = seom_field_parent.data("id"),
				content_type = seom_field_parent.data("type"),
				content_section = seom_field_parent.data("section"),
				entry_type = seom_field.data("type"),
				placeholder = seom_field.data("placeholder");

			// If there is a placeholder value set
			if (typeof placeholder !== "undefined" && placeholder !== false) {
				var is_default = false;
				editor.on("init", function() {

					// Get the current content
					var cont = $.trim(editor.getContent({format : "text"}));

					// If it"s empty and we have a placeholder set the value
					if (cont.length === 0) {
						editor.setContent(placeholder);
						cont = placeholder;
						seom_field.addClass("is_placeholder");
					}

					// Convert to plain text and compare strings
					is_default = (cont == placeholder);

					// Nothing to do
					if (!is_default) {
						return;
					}
				})

				// Remove placeholder content
				.on("focus", function() {
					var cont = editor.getContent({format : "text"})
					is_default = (cont == placeholder);
					if (is_default) {
						editor.setContent("");
						seom_field.removeClass("is_placeholder");
					}
				})

				// Add placeholder content if
				.on("blur", function() {
					if (editor.getContent().length === 0) {
						editor.setContent(placeholder);
						seom_field.addClass("is_placeholder");
					}
				});
			}

			// Add on blur event to add field content to data object for saving
			editor.on("blur", function(e) {

				// If there are no change in the content from the value OR If the field contains the placeholder text do nothing
				if(seom_field.data("value") == editor.getContent() || seom_field.data("placeholder") == editor.getContent()) {
					unset_change(content_section, content_type, content_id, entry_type); // Unset the changes
					return; // Do Nothing Else
				}
				else {
					// Add the change type and value to the seom_changes object
					set_change(content_section, content_type, content_id, entry_type, editor.getContent());
				}

				// For Dev
				//console.log("onblur callback fired");
				//console.log(seom_changes);
			});
		}
	});

	/**
	 * Remove items from the seom_changes object
	 *
	 * @since	1.0.0
	 * @var		string		content_section		Content Section
	 * @var		string		content_type		content type
	 * @var		int		content_id		content id
	 * @var		string		entry_type		field being updated
	 */
	var unset_change = function( content_section, content_type, content_id, entry_type ) {

		var other_fields = 0;
		$.each(seom_changes, function(index, data) {
			if(typeof data !== "undefined") {
				if(data.section == content_section && data.type == content_type && data.id == content_id && data.field == entry_type) {
					delete seom_changes[index];
				}
				else if(data.section == content_section && data.type == content_type && data.id == content_id) {
					other_fields++;
				}
			}
		});

		if(other_fields === 0) {
			// Remove class from table row when change is unset
			$("#" + content_type + "-" + content_id).removeClass("row-changed");
		}

		// For Dev
		//console.log('unset_change fired');
		//console.log(seom_changes);
	};

	/**
	 * Remove items from the seom_changes object
	 *
	 * @since	1.0.0
	 * @var		string		content_section		Content Section
	 * @var		string		content_type		content type
	 * @var		int		content_id		content id
	 * @var		string		entry_type		field being updated
	 * @var		string		data		content being changed
	 */
	var set_change = function( content_section, content_type, content_id, entry_type, field_data ) {

		var updated_data = false;
		$.each(seom_changes, function(index, data) {
			if(typeof data !== "undefined" && data.section == content_section && data.type == content_type && data.id == content_id && data.field == entry_type) {
				seom_changes[index].data = field_data;
			}
		});

		if(!updated_data) {
			seom_changes.push({
				section: content_section,
				type: content_type,
				id: content_id,
				field: entry_type,
				data: field_data
			});
		}
		// Add class to table row when an SEO item is changed
		var thisrow = $('#' + content_type + "-" + content_id);
		if(!thisrow.hasClass("row-changed")) {
			thisrow.addClass("row-changed");
		}
	};

	/**
	 * Make the AJAX request to autosave any changes made to the SEO with the inline editors
	 *
	 * @since    1.0.0
	 */
	var seo_save = function() {

		$(".seom-save").attr("disabled", true); // Disable the save button
		$("#message").removeClass("updated error").empty(); // Set message back to default state

		var data = {
			action: "save_changes",
			adminpage: adminpage,
			seom_nonce: seom_obj.seom_nonce,
			seom_data: seom_changes
		};

		$.post( ajaxurl, data, function ( response ) {

			// Set the response to a JS object
			response = jQuery.parseJSON(response);

			// Display Error or Success message. Status Codes: 0 = Fail, 1 = Success
			if(!response.status){

				$("#message").addClass("error").append("<p>" + response.message + "</p>");

			}
			else {

				var error_message = false;

				$.each(response.changes, function( index, change ){ // Loop through updated content

					if(change.status) {
						// Remove save items from the seom_changes object
						unset_change(change["section"], change["type"], change["id"], change["field"], change["section"]);
						$('#' + change["type"] + '-' + change["id"] + ' [data-type="' + change["field"] + '"]').data("value", '"' + change["data"] + '"');
					}
					else {
						error_flag = true; // Flag there was an error saving to the post
						error_message += "</li>" + change["type"] + " " + change["id"] + " was not saved.</li>"; // Added error to message
					}

				});

				if(error_message) {
					$('#message').addClass('error').append("<p>There where some errors saving some of the SEO changes.</p><ul>" + error_message + "</ul>");
				}
				else {
					$('#message').addClass('updated').append( "<p>All of the SEO changes have been saved.</p>" );
				}

			}

			setTimeout(function() {
				$('.seom-save').attr('disabled', false); // Re-enable the save button after 2 seconds
			}, 2000);

		});

	};
	$('.seom-save').click(seo_save); // Call the seo_autosave function when the "Save Changes" button is clicked
	//$(document).on( 'heartbeat-tick', seo_save ); // Call the seo_save function when WordPress call the heartbeat-tick used for autosave on a 15 sec interval

	/**
	 * Calculate Lengths on Title & Descriptions
	 *
	 * @since    1.0.0
	 */
	var calculate_length = function() {

		// Collect length and set vars
		var length = $.trim($(this).text()).length,
			class_attr = 'good_length',
			titles_attr = 'Good Length';

		if( $(this).data("type") == "title" ) {

			if(length < 12) {
				class_attr = "too_short";
				titles_attr = "Title is too short";
			} else if(length > 70) {
				class_attr = "too_long";
				titles_attr = "Title is too long";
			}

			$(this).nextAll('.title-length').removeClass( "too_long too_short good_length" ).addClass(class_attr).attr('title',titles_attr).text(length);

		} else {

			if(length < 30) {
				class_attr = "too_short";
				titles_attr = "Description is too short";
			} else if(length > 200) {
				class_attr = "too_long";
				titles_attr = "Description is too long";
			}

			$(this).nextAll('.desc-length').removeClass( "too_long too_short good_length" ).addClass(class_attr).attr('title',titles_attr).text(length);
		}
	};
	$('.seom-title, .seom-desc').each(calculate_length); // Call Function on page load for all titles and descriptions
	$('.seom-title, .seom-desc').keyup(calculate_length); // Call it on every change of a title or description

	// Test for development
	//~ console.log('SEOM JS is Running');
});
