<?php
/**
 * 
 * This file contains the interface for the VCS Adapters
 * @author David Julia
 *
 */
/**
 * The interface for the VCS Adapter Plugins
 * 
 * The VCSAdapter interface defines the interface to
 * be implemented by the VCS Adapter plugins.
 * The methods contained within this class represent common
 * version control system commands. The underlying implementation
 * may vary greatly from VCS to VCS.
 */
interface Theme_Versioning_VCSAdapter
{
	/**
	 * Commit changes to all files.
	 * 
	 * @access public
	 * @return mixed true on success, WP_Error with a descriptive error message on failure.
	 */
	public function commit_all($message);
	
	/**
	 * Commit the changes to the file with name $file_name.
	 * 
	 * This method commits the changes only to the single file with the name that was passed in as $file_name
	 * @access public
	 * @param String A string containing the file name to commit.
	 * @return mixed true on success, WP_Error with a descriptive error message on failure.
	 */
	public function commit($file_name, $message);
	
	/**
	 * Revert all files back to the state in the given revision
	 *
	 * @param The id of the Revision to revert to.
	 * @return bool returns true on success, WP_Error on failure.
	 */
	public function revert_to($revision_id);
	
	/**
	 * 
	 * revert the file with name $file_name back to its state
	 * in the given revision
	 * @param String $revision_id The Revision to revert to.
	 * @param String $file_name A string containing the file name to commit.
	 * @return true on success, a WP_Error on failure
	 */
	public function revert_file_to($revision_id, $file_name);
	
	/**
	 * 
	 * Get the revision specified by $revision_id
	 * 
	 * @param String The revision number or hash of the desired Revision
	 * @return Revision The revision with the given revision number
	 */
	public function get_revision($revision_id);
	
	/**
	 *
	 * Returns an array containing up to the number of past revisions specified by num_past_revisons.
	 * If there are fewer than $max_num_past_revisions then all revisions will be returned.
	 * @param int the maximum number of past revisions to return.
	 * @return array An array containing all the revisions, an empty array if there are no revisions
	 */
	public function get_revisions($max_num_revisions);
	
	/**
	 * Return the specified number of revisions that were committed before the revision specified by $revision_id (in order from most recent to least recent)
	 * @param mixed $revision_id
	 * @param int $num_revisions the number of revisions to return
	 */
	public function get_revisions_before($revision_id, $num_revisions);
	
	/**
	 * Get name of the VCS Adapter.
	 * 
	 * @return String The readable name for this VCS Adapter
	 */
	public function get_name();
}