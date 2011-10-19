<?php
//
// Description
// -----------
// This function will open an existing session.
//
// Info
// ----
// Status: 			beta
//
// Arguments
// ---------
//
//
function ciniki_core_sessionOpen(&$ciniki) {

	if( !isset($ciniki['session']) || !is_array($ciniki['session']) ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'21', 'msg'=>'Internal configuration error', 'pmsg'=>'$ciniki["session"] not set'));
	}

	if( !isset($ciniki['request']['auth_token']) 
		|| !isset($ciniki['request']['api_key']) 
		|| $ciniki['request']['api_key'] == ''
		) {
		return array('stat'=>'fail', 'err'=>array('code'=>'22', 'msg'=>'Internal configuration error', 'pmsg'=>'auth_token and/or api_key empty'));
	}

	if( $ciniki['request']['auth_token'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'23', 'msg'=>'No auth_token specified'));
	}

	//
	// Check if a session is already started based on the auth_token
	// and api_key.
	//
	// A combination of the api_key and auth_token are used, so somebody
	// would have to guess an api_key and auth_token of somebody logged
	// in using that api_key, ie from the same application.  Adds an
	// extra layer of security for session.
	//

	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	$strsql = "SELECT auth_token, api_key, user_id, date_added, "
		. "(UNIX_TIMESTAMP(UTC_TIMESTAMP()) - UNIX_TIMESTAMP(last_saved)) as session_length, timeout, "
		. "session_data "
		. "FROM core_session_data "
		. "WHERE auth_token = '" . ciniki_core_dbQuote($ciniki, $ciniki['request']['auth_token']) . "' "
		. "AND api_key = '" . ciniki_core_dbQuote($ciniki, $ciniki['request']['api_key']) . "' "
		. "";

	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'core', 'auth');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	if( $rc['num_rows'] != 1 ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'37', 'msg'=>'Session expired'));
	}

	//
	// Check expiry
	//
	if( $rc['auth']['session_length'] > $rc['auth']['timeout'] ) {
		require_once($ciniki['config']['core']['modules_dir'] . '/core/private/sessionEnd.php');
		ciniki_core_sessionEnd($ciniki);
		return array('stat'=>'fail', 'err'=>array('code'=>'27', 'msg'=>'Session expired'));
	}

	//
	// Unserialize the session data
	//
	$ciniki['session'] = unserialize($rc['auth']['session_data']);
	
	//
	// Check session variables for security.  If the values in the session
	// do not match the values passed from the client, then it could be a potential
	// security problem.
	// Reset the session variable before returning.
	//
	if( $ciniki['session']['api_key'] != $ciniki['request']['api_key'] ) {
		$ciniki['session'] = array();
		return array('stat'=>'fail', 'err'=>array('code'=>'24', 'msg'=>'Access Denied', 'pmsg'=>'Security Problem: Request and session api_key do not match, possible security problem.'));
	} 
	elseif( $ciniki['session']['auth_token'] != $ciniki['request']['auth_token'] ) {
		$ciniki['session'] = array();
		return array('stat'=>'fail', 'err'=>array('code'=>'25', 'msg'=>'Access Denied', 'pmsg'=>'Security Problem: Request and session auth_token do not match, possible security problem.'));
	} 
	elseif( $ciniki['session']['user']['id'] != $rc['auth']['user_id'] ) {
		$ciniki['session'] = array();
		return array('stat'=>'fail', 'err'=>array('code'=>'26', 'msg'=>'Access Denied', 'pmsg'=>'Security Problem: The user_id in the session data does not match the user_id assigned to session in the database.'));
	}

	//
	// Update session time, so timeout occurs from last action
	//

	//
	// If we get to this point, then the session was loaded successfully
	// and verified.
	//
	return array('stat'=>'ok');	
}
?>
