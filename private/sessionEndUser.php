<?php
//
// Description
// -----------
// This function will remove any sessions for a user_id.  If a sysadmin
// or other user is removed from the database, this function should
// be called to remove any open sessions for the deleted user.
//
// Arguments
// ---------
// ciniki:
// user_id:         The user to end the session for.
//
function ciniki_core_sessionEndUser($ciniki, $user_id) {

    //
    // Remove the session from the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');

    $strsql = "DELETE FROM ciniki_core_session_data WHERE user_id = '" . ciniki_core_dbQuote($ciniki, $user_id) . "'";
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.core');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    return array('stat'=>'ok');
}
?>
