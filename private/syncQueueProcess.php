<?php
//
// Description
// -----------
// This function will execute the queue requests for the sync.
//
// Arguments
// ---------
// ciniki:
// business_id:		The ID of the business on the local side to check sync.
//
function ciniki_core_syncQueueProcess(&$ciniki, $business_id) {
	//
	// Get the list of push syncs for this business, 
	// and then execute all the queue process on each sync.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'syncCheckVersions');

//	return array('stat'=>'ok');
	$strsql = "SELECT ciniki_business_syncs.id, ciniki_businesses.uuid AS local_uuid, ciniki_business_syncs.flags, local_private_key, "
		. "remote_name, remote_uuid, remote_url, remote_public_key, UNIX_TIMESTAMP(last_sync) AS last_sync "
		. "FROM ciniki_businesses, ciniki_business_syncs "
		. "WHERE ciniki_businesses.id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND ciniki_businesses.id = ciniki_business_syncs.business_id "
		. "AND (ciniki_business_syncs.flags&0x01) = 0x01 "	// Push syncs only
		. "AND ciniki_business_syncs.status = 10 "		// Active syncs only
		. "";
	$rc = ciniki_core_dbHashIDQuery($ciniki, $strsql, 'ciniki.businesses', 'syncs', 'id');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	if( isset($rc['syncs']) ) {
		foreach($rc['syncs'] as $sync_id => $sync) {
			$sync['type'] = 'business';
			//
			// Check the versions
			//
			$rc = ciniki_core_syncCheckVersions($ciniki, $business_id, $sync_id);
			if( $rc['stat'] != 'ok' ) {
				// Skip this sync
				error_log("ERR: Unable to check sync versions for $sync_id");
				continue;	
			}

			foreach($ciniki['syncqueue'] as $queue_item) {	
				//
				// Check if this is a sync we are to ignore, because the request came from that sync
				//
//				error_log("Sync: $sync_id, " . $queue_item['args']['ignore_sync_id']);
				if( isset($queue_item['args']['ignore_sync_id']) && $queue_item['args']['ignore_sync_id'] == $sync_id ) {
					continue;
				}
				$method_filename = $ciniki['config']['ciniki.core']['root_dir'] . preg_replace('/^(.*)\.(.*)\.(.*)$/','/\1-api/\2/private/\3.php', $queue_item['method']);
				$method_function = preg_replace('/^(.*)\.(.*)\.(.*)$/','\1_\2_\3', $queue_item['method']);
				if( file_exists($method_filename) ) {
					require_once($method_filename);
					if( is_callable($method_function) ) {
						error_log("SYNC-INFO: [$business_id] " . $queue_item['method'] . '(' . serialize($queue_item['args']) . ')');
						$rc = $method_function($ciniki, $sync, $business_id, $queue_item['args']);
						if( $rc['stat'] != 'ok' ) {
							error_log('SYNC-ERR: ' . $queue_item['method'] . '(' . serialize($queue_item['args']) . ') - (' . serialize($rc['err']) . ')');
							continue;
						}
					} else {
						error_log('SYNC-ERR: Not executable ' . $queue_item['method'] . '(' . serialize($queue_item['args']) . ')');
						continue;
					}
				} else {
					error_log('SYNC-ERR: Doesn\'t exist' . $queue_item['method'] . '(' . serialize($queue_item['args']) . ')');
					continue;
				}
			}
		}
	}

	return array('stat'=>'ok');
}
?>