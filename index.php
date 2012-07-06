<?php
/*
Plugin Name: Theme Versioning
Plugin URI: http://gsoc2011.wordpress.com/template-versioning/
Description: Adds revisions for theme files and the ability to roll back changes made to theme files.
Version: .04
Author: David Julia
Author URI: http://davidjulia.wordpress.com
License: GPL2
*/

include_once 'default_vcs.php';//include the Theme_Versioning_DefaultVCS class
include_once 'theme_revision.php';
/**
 * Get the UI mode. Determines if the UI is in basic or advanced mode.
 * @return String 'basic' if the UI is currently set to basic mode, 'advanced' if set to advanced mode.
 */
function theme_versioning_get_ui_mode() {
	$settings = get_option( 'theme_versioning_settings' );

	return $settings['ui_mode'];
}
/**
 * Get the current VCSAdapter.
 * @return Theme_Versioning_VCSAdapter on success, false if no adapter is set
 */
function theme_versioning_get_vcs() {
	//For this version. Will need to loop through compare classnames to the option
	$settings = get_option( 'theme_versioning_settings' );
	$selected_adapter_class_name = $settings['selected_adapter'];
	$registered_adapters = get_option( 'theme_versioning_adapters' );
	if($registered_adapters)
	{
		foreach ( $registered_adapters as $adapter_option ) {
			if ( get_class( $adapter_option ) == $selected_adapter_class_name )
				$adapter = $adapter_option;
		}
	}
	if(!$adapter) $adapter = new Theme_Versioning_Default_VCSAdapter();
	return $adapter;
}

/**
 * Get the path to the file where the current vcs is declared so that it can be included in other script files programmatically.
 * @return string the path to the file where the current vcs is declared.
 */
function theme_versioning_get_vcs_include_path( $vcs_adapter ) {
	
	$reflector = new ReflectionClass($vcs_adapter);
	return $reflector->getFileName();
}

function theme_versioning_get_current_vcs_include_path( )
{
	$vcs_adapter = theme_versioning_get_vcs( );
	
	$reflector = new ReflectionClass($vcs_adapter);
	return $reflector->getFileName();
}
/**
 * Set the vcs_adapter to be used by the Theme Versioning plugin.
 * @param Theme_Versioning_VCSAdapter the adapter to be used by the plugin.
 * @return bool true on success, false on failure
 */
function theme_versioning_set_vcs($vcs_adapter) {
	$settings = get_option('theme_versioning_settings');
	$settings['selected_adapter']= get_class( $vcs_adapter );
	return update_option( 'theme_versioning_selected_adapter_class', $settings);
}

/**
 * Get the paths to all the theme files editable via theme-editor.php.
 * Provided for use in the VCSAdapter's commitAll() function
 * @return array an array containing the paths to the current theme's files (both stylesheet and template files).
 */
function theme_versioning_get_theme_file_paths() {
		$themes = get_themes();

		if ( empty( $theme ) )
			$theme = get_current_theme();
		else
			$theme = stripslashes( $theme );

		if ( ! isset( $themes[$theme] ) )
			wp_die( __( 'The requested theme does not exist.', 'template_versioning' ) );

		$allowed_files = array_merge( $themes[$theme]['Stylesheet Files'], $themes[$theme]['Template Files'] );

		return $allowed_files;
}

/**
 * Get the current file being viewed/edited if on theme-editor.php.
 * @return mixed The string full file name/path of the current file being viewed/edited if on theme-editor.php. false otherwise
 */
function theme_versioning_get_current_editor_file() {
	global $pagenow;

	if ( 'theme-editor.php' != $pagenow ) {
		return false;
	} else {
		$themes = get_themes();

		if ( isset( $_GET['file'] ) && ( stripslashes( $_GET['theme'] ) != get_current_theme() ) ) //On a theme file for a different theme
		{
			return false;
		}
		
		/*Set up vars to be checked below*/
		if(isset($_GET['dir'])) $dir = $_GET['dir'];
		else $dir = null;
		
		$theme = get_current_theme();

		if(isset($_GET['file']))$file = $_GET['file'];
		else $file =null;
		
		$allowed_files = array_merge( $themes[$theme]['Stylesheet Files'], $themes[$theme]['Template Files'] );

		//Determine the current editor file based on the defined terms above
		if ( empty( $file ) ) {
			if ( false !== array_search( $themes[$theme]['Stylesheet Dir'] . '/style.css', $allowed_files ) )
				$file = $themes[$theme]['Stylesheet Dir'] . '/style.css';
			else
				$file = $allowed_files[0];
		} else {
			$file = stripslashes( $file );
			if ( 'theme' == $dir )
				$file = $themes[$theme]['Template Dir'] . '/' . basename( $file );
			elseif ( 'style' == $dir)
				$file = $themes[$theme]['Stylesheet Dir'] . '/' . basename( $file );
		}
		
		return $file;
	}
}

/**
 * Check to see if the theme editor page is on the current theme.
 * @return boolean true if currently on the theme editor page and viewing the current theme. flase otherwise.
 */
function theme_versioning_is_editing_current_theme(){
	global $pagenow;
	
	return  'theme-editor.php' == $pagenow && ( ! isset( $_POST['theme'] ) || get_current_theme() == $_POST['theme']  );
}

/**
 * 
 * Adds the theme versioning UI if and only if we are viewing/editing the current theme in theme-editor.php
 */
function theme_versioning_add_ui(){
	global $pagenow;

	//Display the revision viewer UI only if they are on theme-editor.php and trying to edit the current theme.
	if (theme_versioning_is_editing_current_theme( ) ) {
		wp_enqueue_script( 'theme_versioning_viewer', plugin_dir_url( __FILE__ ) . 'js/theme_versioning_viewer.js', array( 'jquery'), '1.0', true );

		$params = array(
			'revision_viewer_url'=> plugin_dir_url( __FILE__ ) . 'revision_viewer.php',
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( 'theme_versioning_ajax_by_david_julia' ),
			'ui_mode' => theme_versioning_get_ui_mode(),
			'current_file' => theme_versioning_get_current_editor_file(),
			
			//Localization params below:
			'open_viewer' =>__( 'Open Revision Viewer', 'template_versioning' ),
			'commit_current' => __( 'Commit Current File', 'template_versioning' ),
			'commit_all' => __( 'Commit All Files', 'template_versioning' ), 			
			'error_loading_revs' => __( 'Error loading revisions. Refresh the page and try again.', 'template_versioning' )
		);
		
		wp_localize_script( 'theme_versioning_viewer', 'parameters', $params ); //pass $params to the viewer javascript file
	}
}

/**
 * Include the style sheets for the revision viewer on the theme-editor.php page.
 */
function theme_versioning_print_styles(){
	global $pagenow;

	if ( 'theme-editor.php' == $pagenow) {
		wp_register_style( 'theme_versioning_styles', plugin_dir_url( __FILE__ ) . 'css/styles.css' );
		wp_enqueue_style( 'theme_versioning_styles' );
	}
}

/*
 * Begin Settings functions
 */

//Register the default vcs. This is the same way in which external plugins will register themselves.
register_activation_hook(__FILE__, 'theme_versioning_register_default_vcs');

/**
 * Register the Default VCS
 */
function theme_versioning_register_default_vcs(){
	$vcs_adapters = get_option( 'theme_versioning_adapters' );

	if ( ! $vcs_adapters ) {
		$selected_adapter = theme_versioning_get_vcs();
		if ( ! $selected_adapter ) { //This part is unnecessary in external adapter functions. Segment may be unnecessary here... must delete and test it.
			$selected_adapter = new Theme_Versioning_Default_VCSAdapter();
			theme_versioning_set_vcs( $selected_adapter );
		}

		theme_versioning_register_adapter( new Theme_Versioning_Default_VCSAdapter() );
		theme_versioning_set_vcs( new Theme_Versioning_Default_VCSAdapter() );
		$vcs = theme_versioning_get_vcs();
	//	$vcs->commit_all('Initial Commit of all files');
	}
}

/**
 * The VCSAdapter plugins will have to register themeselves by calling this function.
 */
function theme_versioning_register_adapter( Theme_Versioning_VCSAdapter $vcs_adapter ) {
	$reflector = new ReflectionClass( $vcs_adapter );

	/*
	 * When someone tries to register an adapter,
	 * make sure that the class they are trying to register
	 * actually implements the Theme_Versioning_VCSAdapter Interface
	 */
	if ( ! $reflector->implementsInterface( 'Theme_Versioning_VCSAdapter' ) )
		return new WP_Error( 'does_not_implement_vcs_interface', __( 'The VCS Adapter Plugin you are trying to use did not pass in an object that implements the Theme_Versioning_VCSAdapter interface', 'template_versioning' ) );

	$vcs_adapters= get_option( 'theme_versioning_adapters' );

	if ( $vcs_adapters ) {
		//check to make sure the adapter trying to be registered has a unique name.
		foreach ( $vcs_adapters as $adapter ) {
			if ( get_class( $adapter ) == get_class( $vcs_adapter ) )
				return new WP_Error( 'duplicate_adapter_name', __( 'There is already a VCS Adapter with that name registered.', 'template_versioning' ) );
		}
	} else {
		$vcs_adapters = array(); //if there isn't an option vcs_adapters set already, initialize to a new array
	}

	array_push( $vcs_adapters, $vcs_adapter ); //push the vcs_adapter that is trying to register itself

	update_option( 'theme_versioning_adapters', $vcs_adapters ); //update the actual theme_versioning_settings option in the database
}

/**
 * Setup the settings for the plugin.
 */
function theme_versioning_settings_init() {
	register_setting( 'theme_versioning_settings', 'theme_versioning_settings' );
	register_setting( 'theme_versioning_settings', 'theme_versioning_ui_mode' );

	$selected_adapter = theme_versioning_get_vcs();
	if ( ! isset( $selected_adapter ) || ! $selected_adapter ) {
		$selected_adapter = new Theme_Versioning_Default_VCSAdapter();
		theme_versioning_set_vcs( $selected_adapter );
	}

	$settings = get_option( 'theme_versioning_settings' );
	if ( ! isset( $settings['ui_mode'] ) ) {
		$settings['ui_mode'] = 'basic'; //This sets the default mode for the UI to basic
		update_option( 'theme_versioning_settings', $settings );
	}

	add_settings_section(
			'theme_versioning_adapter_section',
			__( 'Adapter Settings', 'template_versioning' ),
			'theme_versioning_adapter_settings_section_text',
			'theme_versioning_settings'
	);
	add_settings_field(
			'theme_versioning_settings',
			__( 'Select a VCS: ', 'template_versioning' ),
			'theme_versioning_make_adapter_settings_field',
			'theme_versioning_settings',
			'theme_versioning_adapter_section'
	);

	add_settings_section(
			'theme_versioning_interface_section',
			__( 'Interface Settings', 'template_versioning' ),
			'theme_versioning_interface_settings_section_text',
			'theme_versioning_settings'
	);
	add_settings_field(
			'theme_versioning_ui_mode_field',
			__( 'Choose an interface: ', 'template_versioning' ),
			'theme_versioning_make_ui_mode_settings_field',
			'theme_versioning_settings',
			'theme_versioning_interface_section'
	);
}

/**
 * Action hook callback function to add the settings page to the admin dashboard settings menu
 */
function theme_versioning_add_settings_page() {
	add_options_page(
			__( 'Theme Versioning Settings Page', 'template_versioning' ),
			__( 'Theme Versioning', 'template_versioning' ),
			'administrator',
			'theme_versioning_settings',
			'theme_versioning_make_settings_page'
	);
}

/**
 * Callback function used to create the Theme Versioning Settings Page
 */
function theme_versioning_make_settings_page() {
	echo '
	<div class="wrap">';
		screen_icon();
		echo '<h2>';
		 _e( 'Theme Versioning Settings', 'template_versioning' );
		echo '</h2><form action="options.php" method="post">';
			settings_fields( 'theme_versioning_settings' );
			do_settings_sections( 'theme_versioning_settings' );
			echo'<p class="submit">
				<input name="Submit" type="submit" class="button-primary" value="'; esc_attr_e( 'Save Changes', 'template_versioning' ); echo'" />
			</p>
		</form>
	</div>';
}


/**
 * Callback function used to create the "adapter settings" section text
 */
function theme_versioning_adapter_settings_section_text() {
	
	echo '<p>';
		_e( '
		Here you can select which of the installed VCS Adapter plugins you wish to use. If you have not installed any other adapter plugins, your
		only option will be the default vcs which works without any configuration.
		', 'template_versioning' );
	echo '</p>';

	echo '<p>';
		_e( 'Select the VCS Adapter that you wish the Theme Versioning Plugin to use from the below list of installed adapters.', 'template_versioning' );
	echo '</p>';
}

/**
 * Callback function used to create the fields in the adapter settings section.
 * This function outputs the select input used to choose an adapter
 */
function theme_versioning_make_adapter_settings_field() {
	$adapter_options = get_option( 'theme_versioning_adapters' );
	$settings = get_option( 'theme_versioning_settings' );
	$selected_adapter = $settings['selected_adapter'];

	$output = '<select id="select_vcs_adapter_dropdown" name="theme_versioning_settings[selected_adapter]">';
	foreach ( $adapter_options as $adapter ) {
		$adapter_class_name = get_class( $adapter );

		//Get the adapter's script file name and include it so we can use its get_name method.
		$reflector = new ReflectionClass( $adapter );
		include_once $reflector->getFileName();

		$output .= '<option value="' . $adapter_class_name . '" ' . selected( $selected_adapter, $adapter_class_name, false ) . '>' . $adapter->get_name() . '</option>';
	}
	$output .= '</select>';
	
	echo $output;
}

/**
 * Callback function to output the Interface Settings Text and Field
 */
function theme_versioning_interface_settings_section_text() {
	echo '<p>',__( 'Select the interface that you wish the theme versioning plugin to use.', 'template_versioning' ), '</p>';
	echo '<p>';
		_e( 'The basic interface is recommended for people who are inexperienced or forgetful users.
		It automatically keeps track of (commits) all changes made each time you save a file.
		If you have never used an actual version control system and are unfamiliar with the terms "commit" or "changeset" then this is probably
		the option you want to use.', 'template_versioning' );
	echo '</p>';
	echo '<p>';
		_e( 'The advanced interface allows the user to choose when to commit changes instead of automatically committing every time a file
		is saved. It allows you to commit changes to all files or just a single file.', 'template_versioning' );
	echo '</p>';
}

/**
 * 
 * Callback function used to create the UI Mode Settings text and field.
 */
function theme_versioning_make_ui_mode_settings_field() {
	$settings = get_option( 'theme_versioning_settings', 'template_versioning' );
	$current_ui_mode = $settings['ui_mode'];
	echo '
	<label for="ui_basic_radio_option">';  _e( 'Basic', 'template_versioning' ); echo'</label>
	<input id="ui_basic_radio_option" type="radio" value="basic" name="theme_versioning_settings[ui_mode]"'; 
	checked( $current_ui_mode, 'basic' );
	echo ' />';

	echo '<label for="ui_advanced_radio_option">';
	 _e( 'Advanced', 'template_versioning' );
	echo '</label>';
	echo '<input id="ui_advanced_radio_option" type="radio" value="advanced" name ="theme_versioning_settings[ui_mode]"';
	checked( $current_ui_mode, 'advanced' );
	echo '/>';
}


/*
 * End Settings Functions
 */

/**
 * This function triggers the commits in the Basic UI
 */
function theme_versioning_commit_trigger() {
	//IF: We are editing the current theme. The theme has just been updated (but not yet written to the actual file)
	//and the user has selected the basic ui. THEN: Commit all the files.
	
	if ( theme_versioning_is_editing_current_theme() && ! empty( $_GET['a'] ) && 'basic' == theme_versioning_get_ui_mode() ) {
		$file_name = $_GET['file']; //the path to the file that was just updated (from theme-editor.php)
		$file_contents = file_get_contents( $file_name );

		$vcs = theme_versioning_get_vcs();
		$vcs->commit_all( __( 'Committing all files', 'template_versioning' ) );
		
	}
}

/**
 * Includes the revision viewer on the theme-editor.php page 
 */
function theme_versioning_add_revision_viewer_html() {
	global $pagenow;
	//if we're on the theme-editor page
	if ( 'theme-editor.php' == $pagenow)
		include_once 'revision_viewer.php';
}


/*
 * AJAX Functions
 */

/*
 * Revision Viewer AJAX
 */

/**
 * AJAX-Triggered action to revert a particular file using the VCSAdapter's revert_file_to( $revision_id, $file_name ) method.
 * 
 * On Success: Echoes "Successfully reverted <basename of file> to revision <revision_id>".
 * On Failure: Echoes "Failed to revert <basename of file> to revison <revision_id>"
 * AJAX POST parameters this function requires: 
 * revision_id : the revision the file should be reverted to.
 * file_name :  the file name of the file to be reverted.
 */
function theme_versioning_ajax_revert_file_to() {
	$revision_id = $_POST['revision_id'];
	$file_name = $_POST['file_name'];
	
	if ( isset( $revision_id ) && isset( $file_name ) ) {
		$result = theme_versioning_get_vcs()->revert_file_to( $revision_id, $file_name );
		if ( is_wp_error( $result ) )
			echo $result->get_error_message();
		else /* translators: successfully reverted is followed by the file name then the translated text for ' to revision ' then the revision number */
			echo __( 'Successfully reverted ', 'template_versioning' ),basename($file_name),__( ' to revision ', 'template_versioning' ),$revision_id;
	} else {
		/* translators: file name comes right after 'Failed to revert', and is then followed by translated text ' to revision'*/
		echo __( 'Failed to revert ', 'template_versioning' ),basename($file_name), __( ' to revison ', 'template_versioning' ), $revision_id;
	}

	die();
}

/**
 * AJAX-Triggered action to revert all files to a given revision using the VCSAdapter's revert_to( $revision_id ) method.
 * 
 * On Success: Echoes "Successfully reverted all files to revision <revision_id>".
 * On Failure: Echoes "Failed to revert to revision <revision_id>"
 * AJAX POST parameters this function requires: 
 * revision_id : the revision the file should be reverted to.
 */
function theme_versioning_ajax_revert_to() {
	check_ajax_referer( 'theme_versioning_ajax_by_david_julia', 'security' );
	$revision_id = $_POST['revision_id'];
	
	if ( isset( $revision_id ) ) {
		$result = theme_versioning_get_vcs()->revert_to( $revision_id );
		
		if ( is_wp_error( $result ) ) echo $result->get_error_message();
		/* translators: this is followed by the revision id */
		else echo __( 'Successfully reverted all files to revision ', 'template_versioning' ),$revision_id;
	} 
	else { /* translators: this is followed by the revision_id */
		echo __( 'Failed to revert to revision', 'template_versioning' ), $revision_id;
	}

	die();
}

/**
 * AJAX-Triggered action to revert all files to a given revision using the VCSAdapter's revert_to( $revision_id ) method.
 * 
 * Echoes the json encoded string containing revision's file names.
 * AJAX POST parameters this function requires: 
 * revision_id : the id of the revision whose file names are desired.
 */
function theme_versioning_ajax_get_revision_file_names() {
	check_ajax_referer( 'theme_versioning_ajax_by_david_julia', 'security' );
	$revision_id = $_POST['revision_id'];

	if ( isset( $revision_id ) ) {
		$vcs = theme_versioning_get_vcs();
		include_once theme_versioning_get_vcs_include_path( $vcs );

		$revision = $vcs->get_revision( $revision_id );
		$files = $revision->get_files();
		$response= json_encode( array_keys( $files ) ); //send the file paths as a json object

		echo $response;
		die();
	}
}

/**
 * AJAX-Triggered action to revert all files to a given revision using the VCSAdapter's revert_to( $revision_id ) method.
 * 
 * Echoes the text-area-safe string containing the requested file's contents.
 * AJAX POST parameters this function requires: 
 * revision_id : the revision id
 * file_name : the name of the file
 */
function theme_versioning_ajax_get_revision_file_contents() {
	check_ajax_referer( 'theme_versioning_ajax_by_david_julia', 'security' );
	$revision_id = $_POST['revision_id'];
	$file_name = $_POST['file_name'];

	$vcs = theme_versioning_get_vcs();
	$revision = $vcs->get_revision( $revision_id );
	$file_contents = $revision->get_file_contents( $file_name );

	if ( isset( $revision_id ) && isset( $file_name ) ) {
		 $file_display_contents = esc_textarea( $file_contents );

		echo $file_display_contents;
		die();
	}
}

/*
 * Advanced Mode UI AJAX
 */

/**
 * AJAX-Triggered action to revert all files to a given revision using the VCSAdapter's revert_to( $revision_id ) method.
 * 
 * On Success, When committing all files: Echoes "Successfully committed all files."
 * On Failure, When committing all files: Echoes "Could not commit files." and an error code.
 * 
 * On Success, When committing one file: Echoes "Successfully committed <file's basename>"
 * On Failure, When committing one file: Echoes "Could not commit <file's basename>." and an error code.
 *
 * To commit commit all files, set the following AJAX POST parameter:
 * commit_all : if set to 'true', the function will commit all of the theme files by calling the VCSAdapter's commit_all method.
 *
 * To commit one particular file, set the following AJAX POST parameters: 
 * file_name : the file name of the file to be committed, if this is not set, the function will commit the current theme-editor file
 * 
 */
function theme_versioning_ajax_commit() {
	check_ajax_referer( 'theme_versioning_ajax_by_david_julia', 'security' );
	$vcs = theme_versioning_get_vcs();

	if ( isset( $_POST['commit_all'] ) && $_POST['commit_all'] == 'true' ) { //committing changes to all files
		$result = $vcs->commit_all( __('Committing all files', 'template_versioning' ) );

		if ( ! is_wp_error( $result ) )
			_e( 'Successfully committed all files.', 'template_versioning' );
		else
			echo __('Could not commit files.', 'template_versioning' ),'\n' , $result->get_error_message( $result->get_error_code() );

	} else { //committing changes to the current file only
		if(isset($_POST['file_name']))$file_name=$_POST['file_name'];
		else die ('No file name set');
		
		$base_name = basename( $file_name );
		/* translators: this is followed by the file's name */
		$result = $vcs->commit( $file_name, __( 'Committing changes to file ', 'template_versioning' ) . $base_name );

		if ( ! is_wp_error( $result ) ) /* translators: this is followed by the file's name */
			echo __( 'Successfully committed file ', 'template_versioning' ),$base_name;
		else /*translators: this is followed by the file's name */
			echo __( 'Could not commit file ', 'template_versioning' ),$base_name,'\n' , $result->get_error_message( $result->get_error_code() );
	}

	die();
}

/**
 * AJAX-Triggered action to retrieve additional revisions' information to be presented through the revision viewer.
 * 
 * Echoes the text-area-safe string containing the requested file's contents.
 * 
 * AJAX POST parameters this function requires if specifying start revision id: 
 * start_rev_id : the revision id of the last loaded revision currently available in the viewer.
 * num_revisions : the number of revisions to load in this request
 * 
 * AJAX POST parameter this function requires when requesting only the latest revision:
 * latest_revision_only: if set to 'true', only returns the latest revision. No other parameters need be set aside from security.
 */
function theme_versioning_ajax_load_revisions() {
	check_ajax_referer( 'theme_versioning_ajax_by_david_julia', 'security' );
	
	$vcs = theme_versioning_get_vcs();
	
	if(isset($_POST['latest_revision_only']) && $_POST['latest_revision_only']==='true')
	{
		$loaded_revisions=$vcs->get_revisions(1);
		if ( is_wp_error( $loaded_revisions ) ) {
			__e( 'Unable to load more revisions', 'template_versioning' );
			die();
		}
	}
	//If the required POST parameters were not set, echo an error message.
	else if(!isset( $_POST['start_rev_id']) && !isset($_POST['num_revisions']))
	{
		 _e( 'Error with request to load more revisions.\nRequired POST parameters were not set.', 'template_versioning' );
		die();
	}
	else {
		$start_rev_id = $_POST['start_rev_id'];
		$num_revisions = $_POST['num_revisions'];
		$loaded_revisions = $vcs->get_revisions_before( $start_rev_id, $num_revisions );
	
		if ( is_wp_error( $loaded_revisions ) ) {
			__e( 'Unable to load more revisions', 'template_versioning' );
			die();
		}
	}
	
	$output_arr= array();
	//Encode the relevant fields to be displayed in the revision viewer into the output array
	//We don't need the overhead of sending the file contents.
	foreach ( $loaded_revisions as $revision ) {
		$revision_info = array(
							'commit_msg' => $revision->get_commit_message(),
							'id' => $revision->get_revision_id(),
							'time_stamp' => $revision->get_time_stamp(),
							'user' => $revision->get_user()
		);
		
		array_push( $output_arr, $revision_info );
	}
	
	echo json_encode( $output_arr );
	die();
}

/*
 * Add all of the Actions
 */

/*AJAX Actions Section */
add_action( 'wp_ajax_theme_versioning_ajax_revert_file_to', 'theme_versioning_ajax_revert_file_to' );
add_action( 'wp_ajax_theme_versioning_ajax_revert_to', 'theme_versioning_ajax_revert_to' );
add_action( 'wp_ajax_theme_versioning_ajax_get_revision_file_names', 'theme_versioning_ajax_get_revision_file_names' );
add_action( 'wp_ajax_theme_versioning_ajax_get_revision_file_contents', 'theme_versioning_ajax_get_revision_file_contents' );
add_action( 'wp_ajax_theme_versioning_ajax_commit', 'theme_versioning_ajax_commit' );
add_action( 'wp_ajax_theme_versioning_ajax_load_revisions', 'theme_versioning_ajax_load_revisions' );

/*All Other Actions Section*/
add_action( 'admin_footer', 'theme_versioning_commit_trigger' );
add_action( 'admin_footer', 'theme_versioning_add_revision_viewer_html' );
add_action( 'admin_print_styles-theme-editor.php', 'theme_versioning_print_styles' );
add_action( 'admin_print_scripts-theme-editor.php', 'theme_versioning_add_ui' );
add_action( 'admin_init', 'theme_versioning_settings_init' );
add_action( 'admin_menu', 'theme_versioning_add_settings_page' );

/**
 * Uninstall Hook 
 */
register_uninstall_hook( __FILE__, 'theme_versioning_uninstall' );
 
/**
 * Uninstall Hook Callback. Deletes options in database.
 */
function theme_versioning_uninstall() {
	delete_option('theme_versioning_settings');
	delete_option('theme_versioning_adapters');
	delete_option('theme_versioning_selected_adapter_class');
}
