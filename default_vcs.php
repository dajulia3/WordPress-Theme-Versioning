<?php
include_once 'vcs_interface.php';
include_once 'theme_revision.php';

//Set up the custom post type for the default VCS
add_action( 'init', 'theme_versioning_create_custom_posts' );

function theme_versioning_create_custom_posts() {
	register_post_type( 'theme_versioning_rev', array(
		'labels' => array(
			'name' => __( 'Theme Revision' ),
			'singular_name' => __( 'Theme Revisions' ),
		),
		'public' => false, //WHEN TRUE, ONLY FOR TESTING PURPOSES. In the final deliverable this must be set to false!
		'hierarchical' => false,
		'rewrite' => false,
		'query_var' => false,
		'supports'=> array( 'title','author' )
	) );
}

class Theme_Versioning_Default_VCSAdapter implements Theme_Versioning_VCSAdapter {
	const POST_META_KEY_FILES = 'files';
	const POST_TYPE = 'theme_versioning_rev';

	/**
	 * Atomic version of file_put_contents using temporary files and rename.
	 * This uses temporary files and rename thereby making a version of file_put_contents
	 * which is an atomic operation on linux and newer versions of windows.
	 * @return true on success, false on failure
	 */
	private static function file_put_contents_atomic( $filename, $content ) {
		$file_put_contents_atomic_temp = dirname( __FILE__ ) . '/cache';
		$file_put_contents_atomic_mode = 0777;

		$temp = tempnam( $file_put_contents_atomic_temp, 'temp' );
		if ( ! $f = @fopen( $temp, 'wb' ) ) {
			$temp = $file_put_contents_atomic_temp . '/' . uniqid( 'temp' );
			if ( ! $f = @fopen( $temp, 'wb' ) )
				return false;
		 }

		 fwrite( $f, $content );
		 fclose( $f );

		 if ( ! @rename( $temp, $filename ) ) {
			 @unlink( $filename );
			 @rename( $temp, $filename );
		 }

		 @chmod( $filename, $file_put_contents_atomic_mode );

		 return true;
	}

	/**
	 *
	 * Creates any intermediate directories to the file, then puts the contents in that file.
	 * Uses file_put_contents_atomic which is atomic on linux and possibly newer versions of windows.
	 * returns false on failure, true on success.
	 * @access private
	 */
	private static function file_force_contents( $dir, $contents ){
		$parts = explode( '/', $dir);
		$file = array_pop( $parts) ;
		$dir = '';

		foreach ( $parts as $part ) {
			if ( ! is_dir( $dir .= "/$part" ) )
				mkdir( $dir ); //create tbe intermediate directories
		}

		if ( ! self::file_put_contents_atomic( "$dir/$file", $contents ) )
			return false; //There was an error writing the file

		return true;
	}

	/**
	 * Creates a revision object from the given post object
	 * @access private
	 */
	private function make_revision_from_post( $post ) {
		$files = get_post_meta( $post->ID, self::POST_META_KEY_FILES );
		$files_with_full_paths = array();

		foreach ( $files[0] as $path_relative_to_theme_dir => $contents ) {
			$full_path = trailingslashit( get_theme_root() ) . $path_relative_to_theme_dir; //prepend the theme root and a trailing slash to the path from the post.
			$files_with_full_paths[$full_path] = $contents;
		}

		$user_info = get_userdata( $post->post_author );
		$name = $user_info->user_login;
		$display_date = date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) );
		return new Theme_Versioning_Revision( $post->ID, $post->post_content, $files_with_full_paths, $name, $display_date);
	}

	/**
	 *Helper function to revert the files to their state in a given revision.
	 *This operation is not necessarilly atomic in the current version of the plugin.
	 *@return true on success, false if it fails to revert all of the files.
	 */
	private function revert_helper( $revision_id ) {
		$total_success = true;
		$files = get_post_meta( $revision_id, self::POST_META_KEY_FILES );

		$theme_root= trailingslashit( get_theme_root() );
		//Replace the theme files with the files in the given revision
		foreach ( $files[0] as $file_name=> $file_contents ) {
			//revert each file's contents. If one of the files fails, then $total_success is false
			if ( ! self::file_force_contents( $theme_root . $file_name, $file_contents ) )
				$total_success = false;
		}

		return $total_success;
	}


	/**
	 * Commit changes to all files.
	 *
	 * @access public
	 */
	public function commit_all( $message ) {
		global $user_ID;

		$new_post = array(
			'post_status' => 'publish',
			//'post_date' => date( 'Y-m-d H:i:s' ), //let wp_insert_post handle this
			'post_author' => $user_ID,
			'post_type' => self::POST_TYPE,
			'post_category' => array( 0 ),
			'post_title' => 'All files comitted',
			'post_content' => $message,
			'post_excerpt' => 'All files'
		);

		$post_id = wp_insert_post( $new_post, true );
		$files_meta = array();

		foreach ( theme_versioning_get_theme_file_paths() as $file_path ) {
			$path_relative_to_theme_dir = substr( $file_path, strlen( get_theme_root() ) +1 ); //+1 accounts for the trailing slash
			$file_contents = file_get_contents($file_path);

			//Add slashes because they are stripped from the file contents in add_post_meta
			$files_meta[$path_relative_to_theme_dir] = str_replace( '\\', '\\\\', $file_contents );
		}

		add_post_meta( $post_id, self::POST_META_KEY_FILES, $files_meta );

		return true;
	}


	/**
	 * Commit the changes to the file with name $file_name.
	 *
	 * This method commits the changes only to the single file with the name that was passed in as $file_name
	 * @access public
	 * @param String A string containing the file name to commit.
	 * @return true on success, wp_error with descriptive error message on failure
	 */
	public function commit( $file_name, $message ){
		$files_meta = array();

		//get files from previous revision
		$args = array(
					'numberposts' => 1,
					'post_type' => self::POST_TYPE
					);
		$revision_posts = get_posts( $args );

		if ( ! empty( $revision_posts ) ) {
			$previous_revision_files = get_post_meta( $revision_posts[0]->ID, self::POST_META_KEY_FILES ) ; //get the files array from the previous revision

			foreach ( $previous_revision_files[0] as $path_relative_to_theme_dir=> $file_contents ) {
				//Add slashes because they are stripped in add_post_meta
				$files_meta[$path_relative_to_theme_dir] = str_replace( '\\', '\\\\', $file_contents );
			}
		}

		/**
		 * Add the $file_name parameter's file and its contents as a key-value pair to the
		 * $files_meta array (or replace it with the new content if it was present in the previous revision.
		 */
		$path_relative_to_theme_dir = substr( $file_name, strlen( get_theme_root()) + 1 ); //+1 accounts for the trailing slash
		$file_contents = file_get_contents( $file_name);

		//Add slashes because they are stripped in add_post_meta
		$files_meta[$path_relative_to_theme_dir] = str_replace( '\\', '\\\\', $file_contents );

		//create the post and insert it
		$new_post = array(
			'post_status' => 'publish',
			'post_type' => self::POST_TYPE,
			'post_category' => array(0),
			'post_title' => "Single File Commit: $path_relative_to_theme_dir",
			'post_content' => $message,
			'post_excerpt'=> "Commit of $path_relative_to_theme_dir"
		);
		$post_id = wp_insert_post( $new_post, true );

		//add the post meta
		add_post_meta( $post_id, self::POST_META_KEY_FILES, $files_meta );

		return true;
	}


	/**
	 * Undo all local changes since last commit
	 *
	 * Reverts all files to their state as of the last commit.
	 * Much like an "undo" function to go back to the previous revision.
	 * Returns the post ID on success, false on failure.
	 * @access public
	 */
	public function revert_to_previous() {
		$args = array(
					'numberposts'=> 1,
					'post_type' => self::POST_TYPE
					);
		$revision_posts = get_posts( $args );

		if ( empty( $revision_posts ) )
		 	return new WP_Error( 'no_revisions', 'revert_to_previous() in Theme_Versioning_defaultVCSAdapter: No previous revision exists' );

		if ( ! revert_helper( $revision_posts[0]->ID ) ) {
			$theme_root = trailingslashit( get_theme_root() );
			return new WP_Error( 'error_writing_file', "revert_to_previous() in Theme_Versioning_defaultVCSAdapter: error writing to file '$theme_root$file_name'" );
		}

		return $revision_posts[0]->ID;
	}

	/**
	 * Revert all files back to the state in the given revision
	 *
	 * @param The id of the Revision to revert to.
	 * @return bool returns true on success, WP_Error on failure.
	 */
	public function revert_to( $revision_id ) {
		$post = get_post( $revision_id );

		if ( empty( $post ) || $post->post_type != self::POST_TYPE )
			return new WP_Error( 'no_such_revision', "revert_to() in Theme_Versioning_defaultVCSAdapter: There is no revision with revision_id '$revision_id'" );

		return $this->revert_helper($revision_id);
	}


	/**
	 *
	 * revert the file with name $file_name back to its state
	 * in the given revision
	 * @param String $revision_id The Revision to revert to.
	 * @param String $file_name A string containing the file name to commit.
	 * @return true on success, a WP_Error on failure
	 */
	public function revert_file_to( $revision_id, $file_name ) {
		$post = get_post( $revision_id );

		if ( empty( $post ) || $post->post_type != self::POST_TYPE )
			return new WP_Error( 'no_such_revision', "revert_to() in Theme_Versioning_defaultVCSAdapter: There is no revision with revision_id '$revision_id'" );

		$files = get_post_meta( $revision_id, self::POST_META_KEY_FILES );
		$path_relative_to_theme_dir = substr( $file_name, strlen( get_theme_root() ) +1 ); //+1 accounts for the trailing slash

		//Replace the theme file with the file from the given revision
		$file_contents = $files[0][$path_relative_to_theme_dir];

		if ( ! self::file_force_contents( $file_name, $file_contents ) ) {
			$theme_root = trailingslashit( get_theme_root() );
			return new WP_Error( 'error_writing_file', "revert_file_to(revision_id, file_name) in Theme_Versioning_defaultVCSAdapter: error writing to file '$theme_root$file_name'" );
		}

		return true;
	}


	/**
	 * 
	 * Get the revision specified by $revision_id
	 * 
	 * @param String The revision number or hash of the desired Revision
	 * @return Revision The revision with the given revision number
	 */
	public function get_revision( $revision_id ) {
		$post = get_post( $revision_id );

		if ( empty( $post ) || $post->post_type != self::POST_TYPE )
			return null;

		return $this->make_revision_from_post($post);
	}


	/**
	 *
	 * Returns an array containing up to the number of past revisions specified by num_past_revisons.
	 * If there are fewer than $max_num_past_revisions then all revisions will be returned.
	 * @param int the maximum number of past revisions to return.
	 * @return array An array containing all the revisions, an empty array if there are no revisions
	 */
	public function get_revisions( $max_num_revisions ) {
		$args = array(
					'numberposts' => $max_num_revisions,
					'post_type' => self::POST_TYPE,
					);
		$revision_posts = get_posts( $args );

		if ( empty( $revision_posts ) )
		 	return array();

		$revisons = array();

		for( $i=0; $i < sizeof( $revision_posts ); $i++ )
			$revisions[$i] = $this->make_revision_from_post( $revision_posts[$i] );

		return $revisions;
	}

	
	private $revision_date_place_holder = '';
	/**
	 * Return the specified number of revisions that were committed before the revision specified by $revision_id (in order from most recent to least recent)
	 * @param mixed $revision_id
	 * @param int $num_revisions the number of revisions to return
	 */
	public function get_revisions_before( $revision_id, $num_revisions ) {
		global $revision_date_place_holder;
		$rev= get_post( $revision_id );
		$revision_date_place_holder= $rev->post_date;
		$args = array(
					'numberposts' => $num_revisions,
					'post_type' => self::POST_TYPE,
					'suppress_filters' => false
					);
		
		// Create a new filtering function that will add our where clause to the query
		function filter_where( $where = '' ) {
			
			global $revision_date_place_holder;
			
			// posts that occurred before the revision with ID $revision_id that was passed into the parent function
			
			$where .= " AND post_date < '". $revision_date_place_holder."'";
			//echo "revision date : <<'$revision_date_place_holder'>> "; DEBUG
			$revision_date_place_holder=''; //reset the revision date place holder variable to empty.
			
			return $where;
		}
		
		
		add_filter( 'posts_where', 'filter_where' );
		$additional_revision_posts = get_posts( $args );
		remove_filter( 'posts_where', 'filter_where' ); //we no longer want to filter posts in this way
		
		$loaded_revisions= array();
		
		//Turn the revision posts into revision objects
		foreach( $additional_revision_posts as $revision_post ){ 
			array_push( $loaded_revisions, $this->make_revision_from_post( $revision_post ) );
		}
		
		//echo 'num revisions returned: ', sizeof($additional_revisions); DEBUG
		return $loaded_revisions;
	}
	
	/**
	 * Get name of the VCS Adapter.
	 * 
	 * @return String The readable name for this VCS Adapter
	 */
	public function get_name(){
		return 'Default Adapter';
	}

}
