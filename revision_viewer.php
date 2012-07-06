<?php
include_once 'vcs_interface.php';
include_once theme_versioning_get_vcs_include_path( theme_versioning_get_vcs( ) );
include_once 'theme_revision.php';

//Make sure people can't even try to view the page if they're not on the theme editor page.
global $pagenow;
if ( 'theme-editor.php' === $pagenow ):
	


wp_enqueue_script( 'jquery' );

$vcs = theme_versioning_get_vcs();
$revisions = $vcs->get_revisions( '15' );
if ( !empty( $revisions ) ):

?>

<div id="revision_viewer_dialog" class="wrap">
	<div id="main_viewer_container">
		<?php screen_icon(); ?>
		<h2 class="page_title"><?php _e( 'View Theme Revisions', 'template_versioning' ); ?></h2>
	
		<div id="upper_revision_viewer_container" class="postbox">
			<div class="handle_wraps">
				<h3 class="hndle"><?php _e( 'Choose a Revision', 'template_versioning' ); ?><div class="handlediv" title="Click to toggle">+<br /></div></h3>
				<a class="togbox"></a>
			</div>
			<div id="revision_selector_container" class="inside">
				<div id="revision_table_container">
					<div id="revision_table_header_container">
					<table id="revision_form_header_table" class="widefat post-revisions">
						<thead id = "revision_form_header">
							<tr>
								<th scope="col" class="revision_form_cell"><?php _e( 'Revision ID', 'template_versioning' ); ?></th>
								<th scope="col" class="revision_form_cell"><?php _e( 'User', 'template_versioning' ); ?></th>
								<th scope="col" class="revision_form_cell"><?php _e( 'Time', 'template_versioning' ); ?></th>
								<th scope="col" class="revision_form_cell" id="revision_form_commit_msg_header"><?php _e( 'Commit Message', 'template_versioning' ); ?></th>
							</tr>
						</thead>
						</table>
						</div>
						<div id="revision_table_body_container">
						<table class="widefat post-revisions" id="revision_selector_table">
						<tbody id="revision_form_body" >
						<?php
							foreach ( $revisions as $revision ) {
								$commit_msg = $revision->get_commit_message();
								$rev_id = $revision->get_revision_id();
								$user = $revision->get_user();
								$time_stamp = $revision->get_time_stamp();
								$display_msg = strlen( $commit_msg ) > 60 ? substr( $commit_msg, 0, 57 ) . '...' : $commit_msg;
								
								echo '<tr class="alternate revision_form_row" id ="',$rev_id, '">';
									echo '<td class="revision_form_cell revision_form_id">',$rev_id,'</td>';
									echo '<td class="revision_form_cell revision_form_user">',$user,"</td>";
									echo '<td class="revision_form_cell revision_form_time_stamp">',$time_stamp,'</td>';
									echo '<td class="revision_form_cell revision_form_commit_msg">',$display_msg, '</td>';
								echo '</tr>';
								
							}
		
							if($revisions):
							 $latest_revision = $revisions[0];
						?>
						</tbody>
					</table>
					</div>
				</div>
				<div id="view_revision_button_container">
					<button id="view_revision_button" class="button">View Revision</button>
				</div>
			</div>
		</div><!-- #upper_revision_viewer_container.postbox -->
	
		<div  id="lower_revision_viewer_container">
			<h3 class="hndle">
			<?php /* translators: This is immediately followed by the revision id*/ _e( 'Currently Viewing Revision ', 'template_versioning' ); ?>
			<span id="revision_number_holder"><?php echo $latest_revision->get_revision_id(); ?></span>
			</h3>
			<div class="inner">
				<div id="file_selector_container">
					 <form name="file_select_form" action="" method="get">
			 			<?php
						$current_files= $latest_revision->files;
			 	 		?>
						<label for="file_selector"><?php _e('File: '); ?> </label>
						<select id="file_selector" name="file_name" >
						<?php
							foreach( $current_files as $file_name => $file_contents ) {
								echo '<option value="' . $file_name . '" ';
								echo '>' . basename( $file_name ) . '</option>'; //only display the basename, not the full path as per ocean90's recommendation.
							}
						?>
						</select>
					</form>
				</div>
				<textarea id="file_container" class="stuffbox shaded_background" readonly="readonly">
					<?php
						if ( ! isset( $_GET['file_name'] ) || ! isset( $current_files[$_GET['file_name']] ) )
							echo esc_textarea(current( $current_files ) );
						else
							echo esc_textarea($current_files[$_GET['file_name']] );
					?>
				</textarea>
			</div><!-- .inner -->
		</div><!-- #lower_revision_viewer_container.postbox -->
		<div id="revert_buttons_container">
			<button id="revert_current_file" class="button-primary"><?php _e( 'Revert Current File', 'template_versioning' ); ?></button>
			<button id="revert_all_files" class="button"><?php _e( 'Revert All Files', 'template_versioning' ); ?></button>
		</div>
		<div id ="close_button_container">
			<a id="close_revision_viewer"><?php _e( 'Return to Theme Editor', 'template_versioning' ); ?></a>
		</div>
	</div><!-- #main_viewer_container -->
</div><!--#revision_viewer_dialog -->
<?php 
endif;
	endif;
	endif; ?>