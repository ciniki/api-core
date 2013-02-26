<?php
//
// Description
// -----------
//
// Arguments
// ---------
// ciniki:
// business_id:		The ID of the business on the local side to check sync.
// sync_id:			The ID of the sync to check compatibility with.
//
function ciniki_core_syncUnlock($ciniki, $business_id, $sync_id) {

	if( !isset($ciniki['config']['ciniki.core']['sync.lock_dir']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'999', 'msg'=>'No sync lock dir specified'));
	}

	$lockfile = $ciniki['config']['ciniki.core']['sync.lock_dir'] . '/sync-' . $sync_id . '.lck';

	if( !file_exists($lockfile) ) {
		return array('stat'=>'ok');
	}

	if( unlink($lockfile) == false ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'999', 'msg'=>'Unable to set sync lock'));
	}

	return array('stat'=>'ok');
}
?>
