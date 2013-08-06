<?php

class FixImageLocations extends BuildTask {

	protected $title = "Fix File Locations";

	protected $description = "This method is useful when most of your files end up in the 'Upload' folder.  This task will put all the HAS_ONE and HAS_MANY files into the following folders {CLASSNAME}_{FIELDNAME}.  You can run this task safely, as it will only execute with a special GET parameter (i.e. it defaults to run in test-mode only).";

	private $forReal = false;

	private $summaryOnly = false;

	function run($request) {
		if(isset($_GET["forreal"])) {
			$this->forReal = true;
		}
		if(isset($_GET["summaryonly"])) {
			$this->summaryOnly = true;
		}
		else {
			if(!$this->summaryOnly) {
				DB::alteration_message("To see a summary only, add ?summaryonly=1 to your link.", "created");
			}
		}
		$checks = DataObject::get("MetaTagCMSControlFileUse", "\"ConnectionType\" IN ('HAS_ONE') AND \"IsLiveVersion\" = 0 AND \"DataObjectClassName\" <> 'File'");
		if($checks && $checks->count()) {
			foreach($checks as $check) {
				$folderName = $check->DataObjectClassName."_".$check->DataObjectFieldName;
				$objectName = $check->DataObjectClassName;
				$fieldName = $check->DataObjectFieldName."ID";
				$folder = Folder::findOrMake($folderName);
				DB::alteration_message("
					<h2>Moving $objectName . $fieldName to $folderName</h2>
				");
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
										DB::alteration_message("
											We are about to move ".$file->FileName." to assets/".$folderName."/".$file->Name."
										");
										if($this->forReal) {
											$file->ParentID = $folder->ID;
											$file->write();
										}
									}
								}
								else {
									DB::alteration_message("Could not find file referenced by ".$object->getTitle()." (".$object->class.", ".$object->ID.")", "deleted");
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
		if(!$this->forReal) {
			DB::alteration_message("To run this test 'For Real', add ?forreal=1 to your link.", "created");
		}
	}

}
