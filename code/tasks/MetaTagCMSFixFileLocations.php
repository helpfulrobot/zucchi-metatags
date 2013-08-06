<?php

class MetaTagCMSFixImageLocations extends BuildTask {

	protected $title = "Fix File Locations";

	protected $description = "This method is useful when most of your files end up in the 'Upload' folder.  This task will put all the HAS_ONE and HAS_MANY files into the following folders {CLASSNAME}_{FIELDNAME}.  You can run this task safely, as it will only execute with a special GET parameter (i.e. it defaults to run in test-mode only).";

	/**
	 * Names of folders to ignore
	 * @var Array
	 */
	private static $folders_to_ignore = array("media");
		public static function set_folders_to_ignore($a){self::$folders_to_ignore = $a;}

	/**
	 * automatically includes any child folders
	 * @var array
	 */
	private $listOfIgnoreFoldersArray = array();

	/**
	 * is this task running 'for real' or as test only?
	 * @var Boolean
	 */
	private $forReal = false;

	/**
	 * is this task running 'for real' or as test only?
	 * @var Boolean
	 */
	private $doOne = false;

	/**
	 * only show the summary OR the full details
	 * summaries only is not available for non-test tasks
	 * @var Boolean
	 */
	private $summaryOnly = false;

	function run($request) {
		if(isset($_GET["forreal"])) {
			$this->forReal = $_GET["forreal"];
		}
		elseif(!$this->forReal) {
			DB::alteration_message("Apply <a href=\"".$this->linkWithGetParameter("forreal", 1)."\">all suggested changes</a>? CAREFUL!<hr />", "deleted");
		}
		if(isset($_GET["summaryonly"])) {
			$this->summaryOnly = $_GET["summaryonly"];
			DB::alteration_message("Prefer a <a href=\"".$this->linkWithGetParameter("all", 1)."\">full details</a>?<hr />", "repaired");
		}
		elseif(!$this->summaryOnly) {
			DB::alteration_message("Prefer a <a href=\"".$this->linkWithGetParameter("summaryonly", 1)."\">summary only</a>?<hr />", "repaired");
		}
		if(isset($_GET["doone"])) {
			$this->forReal = 1;
			$this->doOne = urldecode($_GET["doone"]);
		}

		//work out the folders to ignore...
		foreach(self::$folders_to_ignore as $folderToIgnoreName) {
			$folderToIgnore = Folder::findOrMake($folderToIgnoreName);
			$this->addListOfIgnoreFoldersArray($folderToIgnore);
		}
		DB::alteration_message("Files in the following Folders will be ignored: <br />&nbsp; &nbsp; &nbsp; - ".implode("<br />&nbsp; &nbsp; &nbsp; - " , $this->listOfIgnoreFoldersArray)."<hr />", "repaired");

		$checks = DataObject::get("MetaTagCMSControlFileUse", "\"ConnectionType\" IN ('HAS_ONE') AND \"IsLiveVersion\" = 0 AND \"DataObjectClassName\" <> 'File'");
		if($checks && $checks->count()) {
			foreach($checks as $check) {
				$folderName = $check->DataObjectClassName."_".$check->DataObjectFieldName;
				if(!$this->doOne || $this->doOne == $folderName) {
					$objectName = $check->DataObjectClassName;
					$fieldName = $check->DataObjectFieldName."ID";
					$fileClassName = $check->FileClassName;
					$folder = Folder::findOrMake($folderName);
					DB::alteration_message(
						"<hr /><h3>All files attached to $objectName . $fieldName can be moved to <a href=\"".$this->linkWithGetParameter("doone", $folderName)."\">$folderName</a></h3>",
						"created"
					);
					if($this->summaryOnly) {
						//do nothing
					}
					else {
						$objects = null;
						if($check->FileIsFile) {
							$objects = DataObject::get($objectName, "\"".$fieldName."\" > 0");
						}
						elseif($check->DataObjectIsFile) {
							//$fieldName = $check->DataObjectClassName."ID";
							$objects = DataObject::get($objectName, "\"".$fieldName."\" > 0");
						}
						if($objects && $objects->count()) {
							foreach($objects as $object) {
								if($object instanceOf File) {
									$file = $object;//do nothing
								}
								else {
									$file = DataObject::get_by_id("File", $object->$fieldName);
								}
								if($file) {
									if($file instanceOf Folder) {
										//do nothing
									}
									else {
										if($file->ParentID == $folder->ID) {
											DB::alteration_message(
												"file OK",
												"created"
											);
										}
										else {
											if(isset($this->listOfIgnoreFoldersArray[$file->ParentID])) {
												DB::alteration_message(
													"NOT MOVING (folder to be ignored): <br />/".$file->FileName." to <br />/assets/".$folderName."/".$file->Name."",
													"repaired"
												);
											}
											else {
												DB::alteration_message(
													"MOVING: <br />/".$file->FileName." to <br />/assets/".$folderName."/".$file->Name."",
													"created"
												);
												if($this->forReal) {
													if($file->exists()) {
														if(file_exists($file->getFullPath())) {
															$file->ParentID = $folder->ID;
															$file->write();
														}
														else {
															DB::alteration_message(
																"ERROR: phyiscal file could not be found: ".$file->getFullPath()." ",
																"deleted"
															);
														}
													}
													else {
														DB::alteration_message(
															"ERROR: file not found in database: /".$file->FileName." ",
															"deleted"
														);
													}
												}
											}
										}
									}
								}
								else {
									DB::alteration_message(
										"Could not find file referenced by ".$object->getTitle()." (".$object->class.", ".$object->ID.")",
										"deleted"
									);
								}
							}
						}
						else {
							DB::alteration_message("No objects in $objectName $fieldName.", "deleted");
						}
					}
				}
			}
		}
		else {
			DB::alteration_message("Could not find any checks, please run /dev/build/", "deleted");
		}
		DB::alteration_message("---------------------------------------");
		DB::alteration_message("---------------------------------------");
		DB::alteration_message("CLEANING FOLDERS");
		DB::alteration_message("---------------------------------------");
		DB::alteration_message("---------------------------------------");
		$folders = DataObject::get("Folder");
		if($folders && $folders->count()) {
			foreach($folders as $folder) {
				if(!DataObject::get_one("File", "ParentID = ".$folder->ID)) {
					if(MetaTagCMSControlFileUse::file_usage_count($folder, true)) {
						if(file_exists($folder->getFullPath())) {
							if($this->cleanupFolders) {
								DB::alteration_message("
									Deleting empty folder: <strong>".$folder->FileName."</strong>",
									"deleted"
								);
								$folder->delete();
							}
						}
						else {
							DB::alteration_message("Could not find this phyiscal folder: ".$folder->getFullPath(), "deleted");
						}
					}
					else {
						DB::alteration_message("Leaving referenced folder: <strong>".$folder->FileName."</strong>", "repaired");
					}
				}
				else {
					DB::alteration_message("Leaving used folder: <strong>".$folder->FileName."</strong>");
				}
			}
		}
		else {
			DB::alteration_message("Could not find any folders. There might be something wrong!", "deleted");
		}

	}

	private function addListOfIgnoreFoldersArray(Folder $folderToIgnore) {
		$this->listOfIgnoreFoldersArray[$folderToIgnore->ID] = $folderToIgnore->FileName;
		$childFolders = DataObject::get("Folder", "ParentID = ".$folderToIgnore->ID);
		if($childFolders && $childFolders->count()) {
			foreach($childFolders as $childFolder) {
				$this->addListOfIgnoreFoldersArray($childFolder);
			}
		}
	}

	private function linkWithGetParameter($var, $value) {
		return "/dev/tasks/MetaTagCMSFixImageLocations?$var=".urlencode($value);
	}

}

