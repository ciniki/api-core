<?php
//
// Description
// -----------
// This function will update a list of tags.
//
// Arguments
// ---------
// ciniki:
// module:				The package.module the tag is located in.
// table:				The database table that stores the tags.
// key_name:			The name of the ID field that links to the item the tag is for.
// key_value:			The value for the ID field.
// type:				The type of the tag. 
//
//						0 - unknown
//						1 - List
//						2 - Category **future**
//
// list:				The array of tag names to add.
// 
// Returns
// -------
// <rsp stat="ok" />
//
function ciniki_core_tagsUpdate($ciniki, $module, $table, $key_name, $key_value, $type, $list) {
	//
	// All arguments are assumed to be un-escaped, and will be passed through dbQuote to
	// ensure they are safe to insert.
	//

	// Required functions
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');

	//
	// Don't worry about autocommit here, it's taken care of in the calling function
	//

	//
	// Get the existing list of tags for the item
	//
	$strsql = "SELECT id, $key_name, tag_type AS type, tag_name AS name "
		. "FROM $table "
		. "WHERE $key_name = '" . ciniki_core_dbQuote($ciniki, $key_value) . "' "
		. "AND tag_type = '" . ciniki_core_dbQuote($ciniki, $type) . "' "
		. "";
	$rc = ciniki_core_dbHashIDQuery($ciniki, $strsql, $module, 'tags', 'name');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['tags']) || $rc['num_rows'] == 0 ) {
		$dbtags = array();
	} else {
		$dbtags = $rc['tags'];
	}

	//
	// Delete tags no longer used
	//
	foreach($dbtags as $tag_name => $tag) {
		if( !in_array($tag_name, $list, true) ) {
			//
			// The tag does not exist in the new list, so it should be deleted.
			//
			$strsql = "DELETE FROM $table WHERE id = '" . ciniki_core_dbQuote($ciniki, $tag['id']) . "' ";
			$rc = ciniki_core_dbDelete($ciniki, $strsql, $module);
			if( $rc['stat'] != 'ok' ) {	
				return $rc;
			}
		}
	}

	//
	// Add new tags lists
	//
	foreach($list as $tag) {
		if( $tag != '' && !in_array($tag, $dbtags) ) {
			// 
			// Setup the SQL statement to insert the new thread
			//
			$strsql = "INSERT INTO $table ($key_name, tag_type, tag_name, date_added, last_updated) VALUES ("
				. "'" . ciniki_core_dbQuote($ciniki, $key_value) . "', "
				. "'" . ciniki_core_dbQuote($ciniki, $type) . "', "
				. "'" . ciniki_core_dbQuote($ciniki, $tag) . "', "
				. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
			$rc = ciniki_core_dbInsert($ciniki, $strsql, $module);
			// 
			// Only return the error if it was not a duplicate key problem.  Duplicate key error
			// just means the tag name is already assigned to the item.
			//
			if( $rc['stat'] != 'ok' && $rc['err']['code'] != '73' ) {
				return $rc;
			}
		}
	}

	return array('stat'=>'ok');
}
?>