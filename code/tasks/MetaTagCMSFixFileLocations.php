<?php

class FixImageLocations ends BuildTask {

	private $forReal = false;

	function run($request) {
		if(isset($_GET["forreal"])) {
			$this->forReal = true;
		}
		$checks = DataObject::get("MetaTagCMSControlFileUse", "\"ConnectionType\" IN ('HAS_ONE', 'HAS_MANY') AND \"IsLiveVersion\" = 0");
		if($checks && $checks->count()) {
			foreach($checks as $check) {
				$folderName = $check->DataObjectClassName."_".$check->DataObjectFieldName;
				$folder = Folder::findOrMake($folderName);
				$objects = DataObject::get("DataObjectClassName", "\"DataObjectFieldName\" > 0");
				foreach($objects as $object) {
					$file = DataObject::get_by_id("File", $object->DataObjectFieldName);
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
						DB::alteration_message("Could not find file referenced by ".$object->getTitle()." (".$object->class.", ".$object->ID.")", "deleted")
					}
				}
			}
		}
		if($this->forReal) {
			DB::alteration_message("To run this tet 'For Real', add ?forreal=1 to your link.", "created")
		}
	}

}
