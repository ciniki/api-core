<?php
//
// Description
// -----------
// This function will create a comma delimited list of integers
// for use in WHERE variable IN (list) sql statements.  This ensures
// that everything is safe and properly escaped.
//
// Info
// ----
// Status: 			beta
//
// Arguments
// ---------
// user_id: 		The user making the request
//
function ciniki_core_dbQuoteIDs($ciniki, $arr) {

	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbConnect.php');

	$rc = ciniki_core_dbConnect($ciniki, 'core');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	$str = '';
	$comma = '';
	foreach($arr as $i) {
		if( is_int($i) ) {
			$str .= $comma . mysql_real_escape_string($i);
			$comma = ',';
		}
	}

	return $str;
}
?>
