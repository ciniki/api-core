<?php
//
// Description
// -----------
// This function checks all the database tables to see if they need upgraded, and runs the upgrade.
//
// *alert* When the database is split between database installs, this file will need to be modified.
//
// Arguments
// ---------
// ciniki:
//
// Returns
// -------
//  <tables>
//      <table_name name='users' />
//  </tables>
//
function ciniki_core_dbUpgradeTables(&$ciniki) {
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetTables');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpgradeTable');

    $rc = ciniki_core_dbGetTables($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $tables = $rc['tables'];

    // FIXME: If in multiple databases, this script will need to be updated.

    $strsql = "SHOW TABLE STATUS";
    $rc = ciniki_core_dbHashIDQuery($ciniki, $strsql, 'ciniki.core', 'tables', 'Name');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( isset($rc['tables']) ) {
        foreach($rc['tables'] as $table_name => $table) {
            if( isset($tables[$table_name]) ) {
                if( preg_match('/(v[0-9]+\.[0-9]+)([^0-9]|$)/i', $table['Comment'], $matches) ) {
                    $tables[$table_name]['database_version'] = $matches[1];
                }
            }
        }
    }

    foreach($tables as $table_name => $table) {
        $schema = file_get_contents($ciniki['config']['ciniki.core']['root_dir'] . '/' . $table['package'] . '-mods/' . $table['module'] . "/db/$table_name.schema");
        if( preg_match('/comment=\'(v[0-9]+\.[0-9]+)\'/i', $schema, $matches) ) {
            $new_version = $matches[1];
            if( $new_version != $tables[$table_name]['database_version'] ) {
                $rc = ciniki_core_dbUpgradeTable($ciniki, $tables[$table_name]['package'], $tables[$table_name]['module'], $table_name, 
                    $tables[$table_name]['database_version'], $new_version);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
            }
        }
    }

    return array('stat'=>'ok');
}
?>
