<?php

class MetaTagCMSFixImageLocations extends BuildTask {

	public static function my_link(){
		return "/dev/tasks/MetaTagCMSFixImageLocations/";
	}

	protected $title = "Fix File Locations";

	protected $description = "This method is useful when most of your files end up in the 'Upload' folder.  This task will put all the HAS_ONE and HAS_MANY files into the following folders {CLASSNAME}_{FIELDNAME}.  You can run this task safely, as it will only execute with a special GET parameter (i.e. it defaults to run in test-mode only).";

	/**
	 * Names of folders to ignore
	 * @var Array
	 */
	private static $folders_to_ignore = array();
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
	 * do one attachment type for real?
	 * @var Boolean
	 */
	private $doOne = false;

	/**
	 * clean up folder?
	 * This deletes the empty folders
	 * @var Boolean
	 */
	private $cleanupFolder = 0;

	/**
	 * You can choose to show the images for one relation
	 * @var Boolean
	 */
	private $showMoreDetails = "";

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
		if(isset($_GET["summaryonly"])) {
			$this->summaryOnly = $_GET["summaryonly"];
			DB::alteration_message("Prefer <a href=\"".$this->linkWithGetParameter("all", 1)."\">all details</a>?<hr />", "repaired");
		}
		if(isset($_GET["doone"])) {
			$this->forReal = 1;
			$this->doOne = urldecode($_GET["doone"]);
		}
		if(isset($_GET["cleanupfolder"])) {
			$this->cleanupFolder = intval($_GET["cleanupfolder"]);
		}
		if(isset($_GET["showmoredetails"])) {
			$this->showMoreDetails = urldecode($_GET["showmoredetails"]);
		}

		//work out the folders to ignore...
		foreach(self::$folders_to_ignore as $folderToIgnoreName) {
			$folderToIgnore = Folder::findOrMake($folderToIgnoreName);
			$this->addListOfIgnoreFoldersArray($folderToIgnore);
		}
		if(count($this->listOfIgnoreFoldersArray)) {
			DB::alteration_message("Files in the following Folders will be ignored: <br />&nbsp; &nbsp; &nbsp; - ".implode("<br />&nbsp; &nbsp; &nbsp; - " , $this->listOfIgnoreFoldersArray)."<hr />", "repaired");
		}
		if(!$this->cleanupFolder) {
			if(!$this->forReal) {
				DB::alteration_message("Apply <a href=\"".$this->linkWithGetParameter("forreal", 1)."\">all suggested changes</a>? CAREFUL!<hr />", "deleted");
			}
			if(!$this->summaryOnly) {
				DB::alteration_message("Prefer a <a href=\"".$this->linkWithGetParameter("summaryonly", 1)."\">summary only</a>?<hr />", "repaired");
			}
			$checks = DataObject::get("MetaTagCMSControlFileUse", "\"ConnectionType\" IN ('HAS_ONE') AND \"IsLiveVersion\" = 0 AND \"DataObjectClassName\" <> 'File'");
			if($checks && $checks->count()) {
				foreach($checks as $check) {
					$folderName = $check->DataObjectClassName."_".$check->DataObjectFieldName;
					if((!$this->doOne || ($this->doOne == $folderName)) && (!$this->showMoreDetails || ($this->showMoreDetails == $folderName))) {
						$objectName = $check->DataObjectClassName;
						$fieldName = $check->DataObjectFieldName."ID";
						$fileClassName = $check->FileClassName;
						$folder = null;
						unset($folderSummary);
						$folderSummary = array();
						DB::alteration_message(
							"<hr /><h3>All files attached to $objectName . $fieldName should go in: <span style=\"color: green;\">$folderName</span></h3>"
						);
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
										$fileFolderLocations = str_replace($file->Name, "", $file->Filename);
										if(!isset($folderSummary[$fileFolderLocations])) {
											$folderSummary[$fileFolderLocations] = 0;
										}
										$folderSummary[$fileFolderLocations]++;
										if($this->summaryOnly) {
											//do nothing
										}
										else {
											if(!$folder){
												$folder = Folder::findOrMake($folderName);
											}
											if($file != $object && $this->showMoreDetails) {
												$fileDetails = "
												 <hr />{$file->Filename} is linked to: <strong>{$object->Title} ({$object->class}, {$object->ID})</strong>";
												$file->Error = "";
												if(!$file->exists()) {
													$file->Error .= " Could not be found in database: ".$file->class.", ".$file->ID;
												}
												if(!file_exists($file->getFullPath())) {
													$file->Error .= " Physical file could not be found: ".$file->getFullPath();
												}
												if($file instanceOf Image) {
													$fileDetails .= $file->renderWith("MetaTagCMSImageDetails");
												}
												else {
													$fileDetails .= "<div style=\"color: red\">$file->Error</div>";
												}
												DB::alteration_message($fileDetails."<hr />");
											}
											if($file->ParentID == $folder->ID) {
												DB::alteration_message(
													"OK ... ". $file->Filename,
													"created"
												);
											}
											else {
												if(isset($this->listOfIgnoreFoldersArray[$file->ParentID])) {
													DB::alteration_message(
														"NOT MOVING (folder to be ignored): <br />/".$file->Filename." to <br />/assets/".$folderName."/".$file->Name."",
														"repaired"
													);
												}
												else {
													DB::alteration_message(
														"MOVING: <br />/".$file->Filename." to <br />/assets/".$folderName."/".$file->Name."",
														"created"
													);
													if($file->exists()) {
														if(file_exists($file->getFullPath())) {
															$newLocation = $folder->getFullPath()."/".$file->Name;
															if(file_exists($newLocation)) {
																DB::alteration_message(
																	"ERROR: can not move the file as it already exists in the new location ".$newLocation." ",
																	"deleted"
																);
															}
															else {
																if($this->forReal) {
																	$file->ParentID = $folder->ID;
																	$file->write();
																	DB::alteration_message(
																		"--- Move completed ---",
																		"created"
																	);
																}
															}
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
															"ERROR: file not saved yet /".$file->Filename." ",
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
						if(count($folderSummary)) {
							DB::alteration_message("<br /><br /><br /><br /><br /><br /><br /><br />");
							DB::alteration_message("---------------------------------------");
							DB::alteration_message("Current situation for $folderName:");
							DB::alteration_message("---------------------------------------");
							foreach($folderSummary as $folderCountLocation => $folderCount) {
								if("assets/".$folderName."/" == $folderCountLocation) {
									DB::alteration_message(" ... $folderCount x $folderCountLocation (already moved)", "created");
								}
								else {
									DB::alteration_message(" ... $folderCount x $folderCountLocation");
								}
							}
							if(!$this->showMoreDetails) {
								DB::alteration_message("---------------------------------------");
								DB::alteration_message("<a href=\"".$this->linkWithGetParameter("showmoredetails", urlencode($folderName))."\">Show More Details?</a>");
							}
							if(!$this->forReal) {
								DB::alteration_message("---------------------------------------");
								DB::alteration_message("<a href=\"".$this->linkWithGetParameter("doone", $folderName)."\">Move all files to: <span style=\"color: green;\">$folderName</span>?</a>");
							}
						}
					}
				}
			}
			else {
				DB::alteration_message("Could not find any checks, please run /dev/build/", "deleted");
			}
		}
		else {
			DB::alteration_message("We are now showing folders only; <a href=\"".$this->linkWithGetParameter("all",1)."\">view all</a><hr />", "restored");
		}
		if($this->forReal || $this->showMoreDetails) {
			//do nothing;
		}
		else {
			DB::alteration_message("<br /><br /><br /><br /><br /><br /><br /><br />");
			DB::alteration_message("---------------------------------------");
			DB::alteration_message("---------------------------------------");
			DB::alteration_message("CLEANING FOLDERS");
			DB::alteration_message("---------------------------------------");
			DB::alteration_message("---------------------------------------");
			$folders = DataObject::get("Folder");
			$hasEmptyFolders = false;
			if($folders && $folders->count()) {
				foreach($folders as $folder) {
					if(!MetaTagCMSControlFileUse::file_usage_count($folder, true)) {
						$hasEmptyFolders = true;
						if(file_exists($folder->getFullPath())) {
							if(($this->cleanupFolder != $folder->ID) && ($this->cleanupFolder != -1) ) {
								DB::alteration_message("found an empty folder: <strong>".$folder->Filename."</strong>; <a href=\"".$this->linkWithGetParameter("cleanupfolder", $folder->ID)."\">delete now</a>?", "restored");
							}
							if(($this->cleanupFolder == $folder->ID) || $this->cleanupFolder == -1) {
								DB::alteration_message("
									Deleting empty folder: <strong>".$folder->Filename."</strong>",
									"deleted"
								);
								$folder->delete();
							}
						}
						else {
							DB::alteration_message("Could not find this phyiscal folder - it is empty can be deleted: ".$folder->getFullPath(), "deleted");
						}
					}
				}
			}
			else {
				DB::alteration_message("Could not find any folders. There might be something wrong!", "deleted");
			}
			if(!$hasEmptyFolders) {
				DB::alteration_message("There are no empty folders!", "created");
			}
			else {
				DB::alteration_message("Delete <a href=\"".$this->linkWithGetParameter("cleanupfolder", -1)."\">all empty folders</a>?", "deleted");
			}

		}

	}

	private function addListOfIgnoreFoldersArray(Folder $folderToIgnore) {
		$this->listOfIgnoreFoldersArray[$folderToIgnore->ID] = $folderToIgnore->Filename;
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

