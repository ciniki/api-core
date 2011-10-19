<?php
//
// Description
// -----------
// This function will 
//
// Info
// ----
// Status: beta
//
// Arguments
// ---------
// business_id:			The business to attach the thread to.
// state:				The opening state of the thread.
// subject:				The subject for the thread.
// source:				The source of the thread.
// source_link:			The link back to the source object.
// 
// Returns
// -------
//
function ciniki_core_userAgentFind($ciniki, $user_agent) {
	//
	// All arguments are assumed to be un-escaped, and will be passed through dbQuote to
	// ensure they are safe to insert.
	//

	// Required functions
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');

	$strsql = "SELECT type_status, size, flags, "
		. "engine, engine_version, os, os_version, "
		. "browser, browser_version, device, device_version, device_manufacturer "
		. "FROM core_user_agents "
		. "WHERE user_agent = '" . ciniki_core_dbQuote($ciniki, $user_agent) . "' "
		. "";
	
	return ciniki_core_dbHashQuery($ciniki, $strsql, 'core', 'device');
}
?>
