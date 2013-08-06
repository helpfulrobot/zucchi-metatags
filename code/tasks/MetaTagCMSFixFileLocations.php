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
	 * only show the summary OR the full details
	 * summaries only is not available for non-test tasks
	 * @var Boolean
	 */
	private $summaryOnly = false;

	function run($request) {
		if(isset($_GET["forreal"])) {
			$this->forReal = true;
		}
		elseif(!$this->forReal) {
			DB::alteration_message("To run this test 'For Real', add ?forreal=1 to your link.", "repaired");
		}
		if(isset($_GET["summaryonly"])) {
			$this->summaryOnly = true;
		}
		elseif(!$this->summaryOnly) {
			DB::alteration_message("To see a summary only, add ?summaryonly=1 to your link.", "repaired");
		}

		//work out the folders to ignore...
		foreach(self::$folders_to_ignore as $folderToIgnoreName) {
			$folderToIgnore = Folder::findOrMake($folderToIgnoreName);
			$this->addListOfIgnoreFoldersArray($folderToIgnore);
		}
		DB::alteration_message("Folders to ignore: ".implode("," , $this->listOfIgnoreFoldersArray), "repaired");

		$checks = DataObject::get("MetaTagCMSControlFileUse", "\"ConnectionType\" IN ('HAS_ONE') AND \"IsLiveVersion\" = 0 AND \"DataObjectClassName\" <> 'File'");
		if($checks && $checks->count()) {
			foreach($checks as $check) {
				$folderName = $check->DataObjectClassName."_".$check->DataObjectFieldName;
				$objectName = $check->DataObjectClassName;
				$fieldName = $check->DataObjectFieldName."ID";
				$folder = Folder::findOrMake($folderName);
				DB::alteration_message(
					"<h3>Moving $objectName . $fieldName to <strong>$folderName</strong></h3>",
					"created"
				);
				if($this->summaryOnly) {
					//do nothing
				}
				else {
					$objects = DataObject::get($objectName, "\"".$fieldName."\" > 0");
					if($objects && $objects->count()) {
						foreach($objects as $object) {
							if($object instanceOf File) {
								//do nothing
							}
							else {
								$file = DataObject::get_by_id("File", $object->$fieldName);
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
											if(isset($this->listOfIgnoreFoldersArray[$file->ParentID]])) {
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
													$file->ParentID = $folder->ID;
													$file->write();
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
					}
					else {
						DB::alteration_message("No objects in $objectName $fieldName.", "deleted");
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
						DB::alteration_message("
							Deleting empty folder: <strong>".$folder->FileName."</strong>.",
							"deleted"
						);
						if($this->forReal) {
							$folder->delete();
						}
					}
					else {
						DB::alteration_message("Leaving referenced folder: <strong>".$folder->FileName."</strong>.", "repaired");
					}
				}
				else {
					DB::alteration_message("Leaving used folder: <strong>".$folder->FileName."</strong>.");
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

}

