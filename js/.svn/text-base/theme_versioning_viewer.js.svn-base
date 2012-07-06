/**
 * Adds the buttons for the interface to the theme editor page
 */
jQuery(document).ready(function($) {
	var making_revision_request = false; //boolean to keep track of whether or not we're currently making an ajax request
	
	//Add the button to the main window
	var toInsert = '<span class="button theme_versioning_unselectable" id="open_rev_viewer_button" style="margin-left:10px;margin-right:30px;">'+ parameters.open_viewer +'</span>';

	if( parameters.ui_mode == 'advanced' ) {
		toInsert += '<span id="theme_versioning_advanced_ui_container" style="display:inline-block;"><button class="button" id="commit_file_button">'+ parameters.commit_current +'</button>';
		toInsert += '<button class="button" id="commit_all_button" style="margin-left:10px;margin-top:10px;">'+ parameters.commit_all +'</button></span>';
	}

	$( '#submit' ).after( toInsert );

	var body_div = $( '#wpbody-content' );
	var body_div_height = body_div.css( 'height' );
	var wrap_height = $( '#wpwrap' ).css( 'height' );
	var rev_viewer_dialog= $( '#revision_viewer_dialog' );

	body_div.wrapInner( '<div id="wpbody_hider" />' );


	$( '#open_rev_viewer_button' ).click( function() {
		$( '#wpbody_hider' ).hide();

		rev_viewer_dialog.appendTo( body_div ).css( 'height', wrap_height ).show( 'slide' );
		return false;
	});

	var ajax_url=parameters.ajax_url;

	/**
	 * Script to add the advanced UI mode functionality
	 */
	$( 'button#commit_all_button' ).click( function() {
		$.post( ajax_url,
				   {
				      action: 'theme_versioning_ajax_commit',
				      security: parameters.ajax_nonce,
				      commit_all: 'true'
				   },
				   function( response ){
					   process_commit_ajax_response( response );
				   }
				);
		return false;
	});

	$( 'button#commit_file_button').click( function() {
		$.post( ajax_url,
					{
						action: 'theme_versioning_ajax_commit',
						file_name: parameters.current_file,
						security: parameters.ajax_nonce
					},
					function( response ){
						process_commit_ajax_response( response );
					}
		);
		return false;
	});

	function add_committed_revision_to_viewer()
	{
			//Now try to add the new revision to the revision viewer
		var first_rev = $( '#revision_table_body_container tr:first' );
		var first_rev_id = first_rev.find('td.revision_form_id').text();
		
		making_revision_request=true;
		$.post(ajax_url, 
			{
				action: 'theme_versioning_ajax_load_revisions',
				security: parameters.ajax_nonce,
				latest_revision_only: 'true'
			},
			function(response){
				try{
					making_revision_request=false;
					revision_response = $.parseJSON(response);
					var revision = revision_response[0];
					if(revision.id>first_rev_id)
					{
						first_rev.before
						(
								'<tr class="alternate revision_form_row" id ="'+revision.id+'">'+
								'<td class="revision_form_cell revision_form_id" >'+revision.id+'</td>'+
								'<td class="revision_form_cell revision_form_user">'+revision.user+"</td>"+
								'<td class="revision_form_cell revision_form_time_stamp">'+revision.time_stamp+'</td>'+
								'<td class="revision_form_cell revision_form_commit_msg">'+revision.commit_msg+ '</td>'+
								'</tr>'
						);
						//highlight the new row
						if( highlighted_revision_div) highlighted_revision_div.css( 'background', 'white' );
						highlighted_revision_div = $('.revision_form_row').first();
						update_revision_highlight();
						view_selected_revision();//set it to be the currently viewed revision when they open the revision viewer
					}
					
					
				}
				catch(exception)
				{
					//do nothing... we're just trying to load the committed revision- maybe the commit failed
				}
			}
		);
	}
	
	function process_commit_ajax_response( response ) {
		alert( response );
		add_committed_revision_to_viewer();
	}

	/**
	 * Script to send ajax requests upon clicking either of the revert buttons
	 */
	var selected_file_name = $( '#file_selector' ).val();
	
	function process_revert_ajax_response( response ) {
		alert( response ); //alert the user as to whether it was a success or failure, passing along error info
		window.location.reload(); /*refresh the parent window (theme-editor.php) so that it reflects the new changes*/
	}

	function show_selected_file_contents() {
		selected_file_name = $( 'select#file_selector' ).val(); //update selected_file_name
		$.post( ajax_url,
				{
					action: 'theme_versioning_ajax_get_revision_file_contents',
					revision_id: current_revision_id,
					file_name: selected_file_name,
					security: parameters.ajax_nonce
				},
				function( response ){
					process_view_file_ajax_response(response);
				}
		);
		return false;
	}

	function process_view_file_ajax_response( response ) {
		$( '#file_container' ).html( response );
	}

	function process_view_revision_ajax_response( response ) {
		$( '#revision_number_holder' ).html( get_highlighted_revision_id() );
		var file_names = $.parseJSON( response );

		function basename( path ) {
			var last_slash_index=path.lastIndexOf( '/' );
			if( last_slash_index == -1 )
				last_slash_index = path.lastIndexOf( '\\' );

			var fileName = path.substring( last_slash_index + 1 );
			return fileName;
		}

		var file_selector_new_html = '';

		for ( var i in file_names )  {
			file_selector_new_html += '<option value="' + file_names[i]
									+'">' + basename( file_names[i] )
									+'</option>';
		}

		$('select#file_selector').html( file_selector_new_html );

		//if the currently selected file exists in the new revision, select it by default
		$( 'select#file_selector' ).val(selected_file_name);
		show_selected_file_contents();
	}

	/*
	 * Below are the event listeners for the buttons and the select
	 */
	$( '#revert_current_file' ).click( function() {
		$.post( ajax_url,
					{
						action: 'theme_versioning_ajax_revert_file_to',
						security: parameters.ajax_nonce,
						revision_id: current_revision_id, file_name: selected_file_name
					},
					function(response){
						process_revert_ajax_response(response);
					}
		);
		return false;
	});

	$('#revert_all_files').click(function(){
		$.post( ajax_url,
				   {
				      action: 'theme_versioning_ajax_revert_to',
				      security: parameters.ajax_nonce,
				       revision_id: current_revision_id
				   },
				   function(response){
					   process_revert_ajax_response(response);
				   }
				);
		return false;
	});

	
	/*Script for Selecting a revision*/
	 var highlighted_revision_div = $('.revision_form_row').first();//by default select the first revision (latest)
	 var current_revision_id = get_highlighted_revision_id();//by default current revision is the first revision id
	 
	 update_revision_highlight();
	 
	 //Function to highlight the currently selected revision div
	 function update_revision_highlight(){
		//set the newly selected div's background color to the #f3f3f3 grey 
		 highlighted_revision_div.css( 'background', '#f3f3f3' );
	 }

	 function get_highlighted_revision_id(){
		return highlighted_revision_div.find( '.revision_form_cell.revision_form_id' ).text();
	 }
	 
	 //Must use live binding since we will be adding div elements
	 $( '.revision_form_row' ).live( 'click', function(){
	   	//set the previously selected revision_div's backgrounds to white
    	if( highlighted_revision_div) highlighted_revision_div.css( 'background', 'white' );
	
    	//update the selected revision div
	    highlighted_revision_div = $( this );
	    
    	//set the newly selected div's background color to the #f3f3f3 grey 
	    update_revision_highlight();

	   });
	    
	 function view_selected_revision()
	 {
		 current_revision_id= get_highlighted_revision_id();
			$.post( ajax_url,
					   {
					      action: 'theme_versioning_ajax_get_revision_file_names',
					      security: parameters.ajax_nonce,
					       revision_id: get_highlighted_revision_id()
					   },
					   function(response){
						   process_view_revision_ajax_response(response);
					   }
					);
			return false;
	 }
	$('#view_revision_button').click(view_selected_revision);

	
	/*update file selector*/
	$( '#file_selector' ).change( function() {
		show_selected_file_contents();
	});
	
	function load_more_revision_data_if_necessary() 
	{
	
		var rev_selector_div = $( '#revision_table_body_container' );
		var scroll_top = rev_selector_div.scrollTop();
		var scroll_bottom = scroll_top + rev_selector_div.height();

		var last_element_offset = rev_selector_div.find('tr:last').offset().top -rev_selector_div.find('tr').first().offset().top;
		var element_height = rev_selector_div.find('td:first').height();
		var num_rows = rev_selector_div.find('tr').length;
		
		var trigger_load_point = .75*num_rows*element_height;
		
		var last_loaded_rev_id = rev_selector_div.find('tr:last td.revision_form_cell.revision_form_id').text();
		
		//If we're not already making an ajax request and we've already scrolled 3/4 of the way there, load content!
		if(!making_revision_request && scroll_bottom>trigger_load_point )	{
			making_revision_request =true; //we are about to make an ajax request

			$.post( ajax_url,
					   {
					      action: 'theme_versioning_ajax_load_revisions',
					      security: parameters.ajax_nonce,
					      start_rev_id: last_loaded_rev_id,//the last revision 
					      num_revisions: 30
					   },
					   function(response){
						   process_load_more_revisions_ajax_response(response);
					   }
					);
		}
		
	}
	
	function process_load_more_revisions_ajax_response( response ){
		if( response == 'Unable to load more revisions' )
			return;
		else
		{
			//Try to parse the JSON
			try{
				var loaded_revisions = $.parseJSON( response );
				
				for( i in loaded_revisions ){
					var previous_last_row = $( '#revision_table_body_container tr:last' );
					var revision = loaded_revisions[i];
					//Insert the HTML for the option after the other options
					previous_last_row.after
					(
							'<tr class="alternate revision_form_row" id ="'+revision.id+'">'+
							'<td class="revision_form_cell revision_form_id" >'+revision.id+'</td>'+
							'<td class="revision_form_cell revision_form_user">'+revision.user+"</td>"+
							'<td class="revision_form_cell revision_form_time_stamp">'+revision.time_stamp+'</td>'+
							'<td class="revision_form_cell revision_form_commit_msg">'+revision.commit_msg+ '</td>'+
							'</tr>'
					);
				}
			}
			catch(exception) //If we can't parse the JSON, alert the user that there is a problem
			{
				alert( parameters.error_loading_revs );
			}
		}
		
		making_revision_request=false; //the request has completed, reset the variable for next time
	}
	
	//Event to trigger ajax loading of more revisions
	$( '#revision_table_body_container' ).scroll(function(event){
		event.preventDefault();
		load_more_revision_data_if_necessary();
		return false;
	});

	$( '#close_revision_viewer' ).click( function(event) {
		//event.preventDefault();
		$( '#revision_viewer_dialog' ).hide();
		$( '#wpbody_hider' ).show( 'slow' );
	});

	//Hide the contents of the postbox when the handle is clicked
    $( '.postbox h3' ).click( function() {
    	
    	var outer_container = $( $( this ).parent().parent().get(0) );
        outer_container.toggleClass( 'closed' );
        
    });
    
   
   
});

