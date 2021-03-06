<?php
//
// Description
// -----------
// This function will update the history elements.
//
// Arguments
// ---------
//
function ciniki_core_syncObjectUpdateHistory(&$ciniki, &$sync, $tnid, $o, $table_key, $remote_history, $local_history) {

    //
    // All transaction locking should be taken care of by a calling function
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');

    //
    // Go through each item from the remote server history, and check if it exists or should be added.
    // The history might already exist and generate a dupliate error key, even when it doesn't exist in local_history.
    //
    $history_table = $o['history_table'];
    foreach($remote_history as $uuid => $history) {
        //
        // Check if remote_history is valid history
        //
        if( !is_array($history) || !isset($history['user']) ) {
            ciniki_core_syncLog($ciniki, 0, 'Bad history (' . serialize($remote_history) . ')', null);
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.305', 'msg'=>'Invalid history information'));
        }

        if( !isset($local_history[$uuid]) 
            || ($local_history[$uuid]['user'] == '' && $remote_history[$uuid]['user'] != '') 
//          || ($local_history[$uuid]['table_key'] == '' && $table_key != '') 
            ) {
            //
            // Check for the user_uuid in the maps for this sync, otherwise query
            //
            if( !isset($local_history[$uuid]) || $local_history[$uuid]['user'] == '' ) {
                //FIXME: Convert to user_lookup
                
                if( $remote_history[$uuid]['user'] == '' ) {
                    //
                    // If the history is screwed up, then user may be blank
                    //
                    $user_id = 0;
                }
                elseif( isset($sync['uuidmaps']['ciniki_users'][$history['user']]) ) {
                    $user_id = $sync['uuidmaps']['ciniki_users'][$history['user']];
                } else {
                    $strsql = "SELECT id "
                        . "FROM ciniki_users "
                        . "WHERE uuid = '" . ciniki_core_dbQuote($ciniki, $history['user']) . "' "
                        . "";
                    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.users', 'user');
                    if( $rc['stat'] != 'ok' ) {
                        return $rc;
                    }
                    if( !isset($rc['user']) ) {
                        //
                        // Add to the local database
                        //
                        ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'sync', 'user_update');
                        $rc = ciniki_users_user_update($ciniki, $sync, $tnid, array('uuid'=>$history['user']));
                        if( $rc['stat'] != 'ok' ) {
                            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.306', 'msg'=>'Unable to add user', 'err'=>$rc['err']));
                        }
                        $user_id = $rc['id'];
                    } else {
                        $user_id = $rc['user']['id'];
                    }
                }
            }
            //
            // Add the history
            //
            if( !isset($local_history[$uuid]) ) {
                //
                // Check if the table_field is a field that reverences an ID, and needs to be converted from a UUID
                //
                if( isset($o['fields'][$history['table_field']]) && isset($o['fields'][$history['table_field']]['oref']) && $history['new_value'] != '0' ) {
                    //
                    // The object already exists in this database, load the object reference name from there
                    //
                    $oref_field_name = $o['fields'][$history['table_field']]['oref'];
                    $strsql = "SELECT " . $oref_field_name . " "
                        . "FROM " . $o['table'] . " "
                        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                        . "AND id = '" . ciniki_core_dbQuote($ciniki, $table_key) . "' "
                        . "";
                    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, $o['pmod'], 'object');
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.307', 'msg'=>'Unable to find object ' . $o['pmod'] . '(' . $table_key . ')', 'err'=>$rc['err']));
                    }
                    if( !isset($rc['object']) ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.308', 'msg'=>'Unable to find object ' . $o['pmod'] . '(' . $table_key . ')'));
                    }
                    $ref = $rc['object'][$oref_field_name];
                    
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncObjectLoad');
                    $rc = ciniki_core_syncObjectLoad($ciniki, $sync, $tnid, $ref, array());
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.309', 'msg'=>'Unable to load object ' . $ref, 'err'=>$rc['err']));
                    }
                    $ref_o = $rc['object'];

                    //
                    // Lookup the object
                    //
                    if( !isset($ref_o['type']) || $ref_o['type'] != 'settings' ) {
                        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncObjectLookup');
                        $rc = ciniki_core_syncObjectLookup($ciniki, $sync, $tnid, $ref_o, 
                            array('remote_uuid'=>$history['new_value']));
                        if( $rc['stat'] != 'ok' ) {
                            ciniki_core_syncLog($ciniki, 0, "Unable to locate local new value for " . $ref_o['pmod'] . '.' . $ref_o['oname'] . '(' . $history['new_value'] . ')', $rc['err']);

                            $history['new_value'] = '';
                        } else {
                            $history['new_value'] = $rc['id'];
                        }
                    }
                }
                elseif( isset($o['fields'][$history['table_field']]) && isset($o['fields'][$history['table_field']]['ref']) && $history['new_value'] != '0' ) {
                    $ref = $o['fields'][$history['table_field']]['ref'];
//                  ciniki_core_syncLog($ciniki, 5, "Checking ref $ref(" . $history['new_value'] . ")", null);

                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncObjectLoad');
                    $rc = ciniki_core_syncObjectLoad($ciniki, $sync, $tnid, $ref, array());
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.310', 'msg'=>'Unable to load object ' . $ref, 'err'=>$rc['err']));
                    }
                    $ref_o = $rc['object'];

                    //
                    // Lookup the object
                    //
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncObjectLookup');
                    $rc = ciniki_core_syncObjectLookup($ciniki, $sync, $tnid, $ref_o, 
                        array('remote_uuid'=>$history['new_value']));
                    if( $rc['stat'] != 'ok' ) {
                        ciniki_core_syncLog($ciniki, 0, "Unable to locate local new value for " . $o['oname'] . '(' . $history['new_value'] . ')', $rc['err']);

                        $history['new_value'] = '';
                    } else {
                        $history['new_value'] = $rc['id'];
                    }
                }

                //
                // Translate setting refs
                //
                if( isset($o['refs']) && isset($o['refs'][$table_key]) 
                    && $history['table_field'] == 'detail_value'
                    && isset($o['refs'][$table_key]['ref']) && $history['new_value'] != '0' ) {
                    $ref = $o['refs'][$table_key]['ref'];
//                  ciniki_core_syncLog($ciniki, 5, "Checking ref $ref(" . $history['new_value'] . ")", null);

                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncObjectLoad');
                    $rc = ciniki_core_syncObjectLoad($ciniki, $sync, $tnid, $ref, array());
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.311', 'msg'=>'Unable to load object ' . $ref, 'err'=>$rc['err']));
                    }
                    $ref_o = $rc['object'];

                    //
                    // Lookup the object
                    //
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncObjectLookup');
                    $rc = ciniki_core_syncObjectLookup($ciniki, $sync, $tnid, $ref_o, 
                        array('remote_uuid'=>$history['new_value']));
                    if( $rc['stat'] != 'ok' ) {
                        ciniki_core_syncLog($ciniki, 0, "Unable to locate local new value for " . $history['table_name'] . '(' . $history['new_value'] . ')', $rc['err']);

                        $history['new_value'] = '';
                    } else {
                        $history['new_value'] = $rc['id'];
                    }
                }

                $strsql = "INSERT INTO $history_table (uuid, tnid, user_id, "    
                    . "session, action, table_name, table_key, table_field, "
                    . "new_value, log_date) VALUES ("
                    . "'" . ciniki_core_dbQuote($ciniki, $uuid) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $tnid) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $user_id) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $history['session']) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $history['action']) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $o['table']) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $table_key) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $history['table_field']) . "', "
                    . "'" . ciniki_core_dbQuote($ciniki, $history['new_value']) . "', "
                    . "FROM_UNIXTIME('" . ciniki_core_dbQuote($ciniki, $history['log_date']) . "') "
                    . ")";
                $rc = ciniki_core_dbInsert($ciniki, $strsql, $o['pmod']);
                if( $rc['stat'] != 'ok' && $rc['err']['code'] != 'ciniki.core.73' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.312', 'msg'=>'Unable to add history'));
                }
            } elseif( $user_id > 0 ) {
                //
                // Update history user_id
                //
                $strsql = "UPDATE $history_table SET user_id = '" . ciniki_core_dbQuote($ciniki, $user_id) . "' ";
//              if( $local_history['table_key'] == '' && $table_key != '' ) {
//                  $strsql .= ", table_key = '" . ciniki_core_dbQuote($ciniki, $table_key) . "' "
//                      . "";
//              }
                $strsql .= "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                    . "AND uuid = '" . ciniki_core_dbQuote($ciniki, $uuid) . "' "
                    . "";
                $rc = ciniki_core_dbUpdate($ciniki, $strsql, $o['pmod']);
                if( $rc['stat'] != 'ok' && $rc['err']['code'] != 'ciniki.core.73' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.core.313', 'msg'=>'Unable to update history'));
                }
            }

        }
    }

    return array('stat'=>'ok');
}
?>
