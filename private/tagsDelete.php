<?php
//
// Description
// -----------
// This function will remove ALL tags from an item.  
//
// Arguments
// ---------
// ciniki:
// module:				The package.module the tag is located in.
// table:				The database table that stores the tags.
// key_name:			The name of the ID field that links to the item the tag is for.
// key_value:			The value for the ID field.
// 
// Returns
// -------
// <rsp stat="ok" />
//
function ciniki_core_tagsDelete($ciniki, $module, $table, $key_name, $key_value) {
	//
	// All arguments are assumed to be un-escaped, and will be passed through dbQuote to
	// ensure they are safe to insert.
	//

	// Required functions
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');

	//
	// Don't worry about autocommit here, it's taken care of in the calling function
	//

	// 
	// Remove all the tags for an item.  This is faster than doing one at a time.
	//
	$strsql = "DELETE FROM $table WHERE $key_name = '" . ciniki_core_dbQuote($ciniki, $key_value) . "' ";
	$rc = ciniki_core_dbDelete($ciniki, $strsql, $module);
	if( $rc['stat'] != 'ok' ) {	
		return $rc;
	}
		
	return array('stat'=>'ok');
}
?>
