<?php
/**
 * File containing the revision class
 * @author David Julia
 */

class Theme_Versioning_Revision{
	
	public $commit_msg="";
	public $files;
	public $id;
	public $time_stamp;
	public $user;
	
	/**
	 * Constructor for Theme_Versioning_Revision
	 * @param $revision_id the identifier of the revision
	 * @param $commit_message string a string containing the commit message.
	 * @param $file_array array A file array in the form file_name => file_contents containing all the files in the revision
	 * @param $timestamp The timestamp of the revision
	 */
	public function __construct($revision_id, $commit_message, array $files_array, $user , $timestamp)
	{
		$this->files=$files_array;
		$this->id=$revision_id;
		$this->commit_msg=$commit_message;
		$this->user = $user;
		$this->time_stamp=$timestamp;
	}
	
	/**
	 * @return a revision identifier. This is not necessarily an int, but could be a string hash or some other arbitrary identifier.
	 */
	public function get_revision_id()
	{
		return $this->id;
	}
	
	/**
	 * @return string containing the commit message
	 */
	public function get_commit_message()
	{
		return $this->commit_msg;
	}
	
	/**
	 * Accessor method for the Revision's files
	 * @return array A file array in the form file_name => file_contents containing all the files in the revision
	 */
	public function get_files() 
	{
		return $this->files;
	}
	
	/**
	 * 	Get the contents of a specific file within the revision as a string.
	 *	@return a string containing the contents of a given file in the revision null if there is no such file in the revision
	 */
	public function get_file_contents($file_name) //was called getFileFromRevision in project description
	{
		return $this->files[$file_name];
	}
	
	/**
	 * Get the author of the revision as a string. For the default vcs this is the WordPress user who committed the revision,
	 * for other VCS it can be the VCS user who made the revision.
	 * @return string A string containing the author who committed this revision.
	 */
	public function get_user()
	{
		return $this->user;
	}
	
	/**
	 * 
	 * Get the timestamp for the revision
	 * @return string a string containing the timestamp for the revision
	 */
	public function get_time_stamp()
	{
		return $this->time_stamp;
	}
	
}