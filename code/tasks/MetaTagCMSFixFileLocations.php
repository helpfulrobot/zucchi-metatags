<?php

class FixImageLocations extends BuildTask {

	protected $title = "Fix File Locations";

	protected $description = "This method is useful when most of your files end up in the 'Upload' folder.  This task will put all the HAS_ONE and HAS_MANY files into the following folders {CLASSNAME}_{FIELDNAME}.  You can run this task safely, as it will only execute with a special GET parameter (i.e. it defaults to run in test-mode only).";

	private $forReal = false;

	function run($request) {
		if(isset($_GET["forreal"])) {
			$this->forReal = true;
		}
		$checks = DataObject::get("MetaTagCMSControlFileUse", "\"ConnectionType\" IN ('HAS_ONE', 'HAS_MANY') AND \"IsLiveVersion\" = 0");
		if($checks && $checks->count()) {
			foreach($checks as $check) {
				$folderName = $check->DataObjectClassName."_".$check->DataObjectFieldName;
				$fieldName = $check->DataObjectFieldName."ID";
				$folder = Folder::findOrMake($folderName);
				$objects = DataObject::get($check->DataObjectClassName, "\"".$fieldName."\" > 0");
				foreach($objects as $object) {
					$file = DataObject::get_by_id("File", $object->$fieldName);
					if($file) {
						DB::alteration_message("
							We are about to move ".$file->FileName." to assets/".$folderName."/".$file->Name."
						");
						if($this->forReal) {
							$file->ParentID = $folder->ID;
							$file->write();
							DB::aleration_message("Done", "created");
						}
						else {
							DB::aleration_message("Test Only", "edited");
						}
					}
					else {
						DB::alteration_message("Could not find file referenced by ".$object->getTitle()." (".$object->class.", ".$object->ID.")", "deleted");
					}
				}
			}
		}
		if($this->forReal) {
			DB::alteration_message("To run this tet 'For Real', add ?forreal=1 to your link.", "created");
		}
	}

}
