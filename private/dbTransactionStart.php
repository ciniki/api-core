<?php
//
// Description
// -----------
// This function will start a new transaction, or turn off autocommit for the database.
//
// *note* All transaction control should be managed by the 
// public API method not any of the private ones.
//
// *alert* Currently all tables are in the same database,
// and there's no independence between Commit.  If you commit
// for one module, it will commit for all.  The $module variable
// is for the future.
//
// Info
// ----
// Status: 			beta
//
// Arguments
// ---------
// module:			The module to start the transaction for.
//
function ciniki_core_dbTransactionStart($ciniki, $module) {

	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbConnect.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuery.php');

	$rc = ciniki_core_dbConnect($ciniki, $module);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return ciniki_core_dbQuery($ciniki, "START TRANSACTION", $module);
}
?>
