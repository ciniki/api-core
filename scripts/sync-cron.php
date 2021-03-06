<?php
//
// Description
// -----------
// This script should be executed from cron every 5 minutes to run
// an incremental sync on all tenants.
// 

//
// Initialize Moss by including the ciniki_api.php
//
global $ciniki_root;
$ciniki_root = dirname(__FILE__);
if( !file_exists($ciniki_root . '/ciniki-api.ini') ) {
    $ciniki_root = dirname(dirname(dirname(dirname(__FILE__))));
}
// loadMethod is required by all function to ensure the functions are dynamically loaded
require_once($ciniki_root . '/ciniki-mods/core/private/loadMethod.php');
require_once($ciniki_root . '/ciniki-mods/core/private/init.php');
//require_once($ciniki_root . '/ciniki-mods/cron/private/execCronMethod.php');
//require_once($ciniki_root . '/ciniki-mods/cron/private/getExecutionList.php');

$rc = ciniki_core_init($ciniki_root, 'rest');
if( $rc['stat'] != 'ok' ) {
    error_log("unable to initialize core");
    exit(1);
}

//
// Setup the $ciniki variable to hold all things ciniki.  
//
$ciniki = $rc['ciniki'];

ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncCronList');
ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncTenant');
ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncUpgradeSystem');

//
// Check first the code is up to date
//
if( isset($ciniki['config']['ciniki.core']['sync.code.url']) 
    && $ciniki['config']['ciniki.core']['sync.code.url'] != '' ) {
    $rc = ciniki_core_syncUpgradeSystem($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
}

//
// Get list of cron jobs
//
$rc = ciniki_core_syncCronList($ciniki);
if( $rc['stat'] != 'ok' ) {
    error_log("SYNC-ERR: unable to get cron list");
    exit(1);
}

if( !isset($rc['syncs']) ) {
    error_log('No syncs');
    exit(0);
}

$syncs = $rc['syncs'];
$cur_time = $rc['cur_time'];
$cur_date = date_create('@' . $cur_time);
$cur_hour = date_format($cur_date, 'G');
$sync_full_hour = 6;
if( isset($ciniki['config']['ciniki.core']['sync.full.hour']) ) {
    $sync_full_hour = $ciniki['config']['ciniki.core']['sync.full.hour'];
}
$sync_partial_hour = 3;
if( isset($ciniki['config']['ciniki.core']['sync.partial.hour']) ) {
    $sync_partial_hour = $ciniki['config']['ciniki.core']['sync.partial.hour'];
}
$cmd = $ciniki['config']['ciniki.core']['php'] . " " . dirname(__FILE__) . "/sync-run.php ";
foreach($rc['syncs'] as $sid => $sync) {
    //
    // For a copy of the script to handle each sync
    //
    // if time since last full > 150 hours, and time is currently 3 am, run full
    if( $cur_hour == $sync_full_hour && $sync['full_age'] > 540000 ) {
        exec($cmd . " " . $sync['tnid'] . " " . $sync['id'] . " full >> " . $ciniki['config']['ciniki.core']['sync.log_dir'] . "/sync_" . $sync['sitename'] . '_' . $sync['id'] . ".log 2>&1 &");
    } 
    // if time since last partial > 23 hours, and time is currently 3 am, run parital
    elseif( $cur_hour == $sync_partial_hour && $sync['partial_age'] > 82800 ) {
        exec($cmd . " " . $sync['tnid'] . " " . $sync['id'] . " partial >> " . $ciniki['config']['ciniki.core']['sync.log_dir'] . "/sync_" . $sync['sitename'] . '_' . $sync['id'] . ".log 2>&1 &");
    }
    // Default to a incremental sync
    else {
        exec($cmd . " " . $sync['tnid'] . " " . $sync['id'] . " incremental >> " . $ciniki['config']['ciniki.core']['sync.log_dir'] . "/sync_" . $sync['sitename'] . '_' . $sync['id'] . ".log 2>&1 &");
    }
    // Sleep for 1 seconds between each exec
    sleep(1);
}

exit(0);
?>
