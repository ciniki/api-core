<?php
//
// Description
// -----------
// This method retrieves the history elements for a field.  The users display_name is 
// attached to each record as user_display_name.
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant to get the changes for.
// module:          The name of the module for the transaction, which should include the 
//                  package in dot notation.  Example: ciniki.artcatalog
//
//
function ciniki_core_dbGetChangeLog(&$ciniki, $tnid, $table_name, $table_key, $table_field, $module) {
    //
    // Open a connection to the database if one doesn't exist.  The
    // dbConnect function will return an open connection if one 
    // exists, otherwise open a new one
    //
    $rc = ciniki_core_dbConnect($ciniki, 'ciniki.core');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    $dh = $rc['dh'];

    //
    // Get the history log from ciniki_core_change_logs table.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteList');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbParseAge');

    $date_format = ciniki_users_datetimeFormat($ciniki);
    $strsql = "SELECT user_id, DATE_FORMAT(log_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as date, "
        . "CAST(UNIX_TIMESTAMP(UTC_TIMESTAMP())-UNIX_TIMESTAMP(log_date) as DECIMAL(12,0)) as age, "
        . "table_key, "
        . "new_value as value "
        . " FROM ciniki_core_change_logs "
        . " WHERE tnid ='" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . " AND table_name = '" . ciniki_core_dbQuote($ciniki, $table_name) . "' ";
    if( is_array($table_key) ) {
        $strsql .= " AND table_key IN (" . ciniki_core_dbQuoteList($ciniki, $table_key) . ") ";
    } else {
        $strsql .= " AND table_key = '" . ciniki_core_dbQuote($ciniki, $table_key) . "' ";
    }
    $strsql .= " AND table_field = '" . ciniki_core_dbQuote($ciniki, $table_field) . "' "
        . " ORDER BY log_date DESC "
        . "";
    $result = mysqli_query($dh, $strsql);
    if( $result == false ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.41', 'msg'=>'Database Error', 'pmsg'=>mysqli_error($dh)));
    }

    //
    // Check if any rows returned from the query
    //
    if( mysqli_num_rows($result) <= 0 ) {
        return array('stat'=>'ok', 'history'=>array());
    }

    $rsp = array('stat'=>'ok', 'history'=>array());
    $user_ids = array();
    $num_history = 0;
    while( $row = mysqli_fetch_assoc($result) ) {
        $rsp['history'][$num_history] = array('action'=>array('user_id'=>$row['user_id'], 'date'=>$row['date'], 'value'=>$row['value']));
        if( is_array($table_key) ) {
            $rsp['history'][$num_history]['action']['key'] = $row['table_key'];
        }
        if( $row['user_id'] > 0 ) {
            array_push($user_ids, $row['user_id']);
        }
        $rsp['history'][$num_history]['action']['age'] = ciniki_core_dbParseAge($ciniki, $row['age']);
        $num_history++;
    }

    mysqli_free_result($result);

    //
    // If there was no history, or user ids, then skip the user lookup and return
    //
    if( $num_history < 1 || count($user_ids) < 1 ) {
        return $rsp;
    }

    //
    // Get the list of users
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'userListByID');
    $rc = ciniki_users_userListByID($ciniki, 'users', array_unique($user_ids), 'display_name');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.42', 'msg'=>'Unable to merge user information', 'err'=>$rc['err']));
    }
    $users = $rc['users'];

    //
    // Merge user list information into array
    //
    foreach($rsp['history'] as $k => $v) {
        if( isset($v['action']) && isset($v['action']['user_id']) && $v['action']['user_id'] > 0 
            && isset($users[$v['action']['user_id']]) && isset($users[$v['action']['user_id']]['display_name']) ) {
            $rsp['history'][$k]['action']['user_display_name'] = $users[$v['action']['user_id']]['display_name'];
        }
    }

    return $rsp;
}
?>
