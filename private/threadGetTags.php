<?php
//
// Description
// -----------
// This function will return 
//
// Info
// ----
// Status: beta
//
// Arguments
// ---------
// ciniki:
// tnid:         The id of the tenant to get the tags for.
// module:              The package.module the thread is located in.
// prefix:              The table prefix used in the query.
// container:           The name of the xml container to hold the return values.
// row_name:            The name for the xml item inside the container, one for each row.
// no_row_err:          If no rows are returned in the query, what should be returned.  This must be in the format of array('stat'=>'ok')
// options:             An associated array for options.
//                      
//                      state - select only tags where the thread is this state.
//                      source - select only tags where the thread is from this source.
// 
// Returns
// -------
// <$container_name>
//      <$row_name field=value ... />
// </$container_name>
//
function ciniki_core_threadGetTags($ciniki, $tnid, $module, $prefix, $container, $row_name, $no_row_err, $options) {

    $strsql = "SELECT DISTINCT tag FROM " . ciniki_core_dbQuote($ciniki, "{$prefix}s") . ", " . ciniki_core_dbQuote($ciniki, "{$prefix}_tags") . " "
        . "WHERE " . ciniki_core_dbQuote($ciniki, "{$prefix}s.tnid") . " = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' ";

    //
    // Check if there was state specified
    //
    if( is_array($options) && isset($options['state']) && $options['state'] != '' ) {
        $strsql .= "AND " . ciniki_core_dbQuote($ciniki, "{$prefix}s.state") . " = '" . ciniki_core_dbQuote($ciniki, $options['state']) . "' ";
    } 

    //
    // Check if there was source specified
    //
    if( is_array($options) && isset($options['source']) && $options['source'] != '' ) {
        $strsql .= "AND " . ciniki_core_dbQuote($ciniki, "{$prefix}s.state") . " = '" . ciniki_core_dbQuote($ciniki, $options['source']) . "' ";
    }

    // 
    // Connect the main to the tags table
    // eg: AND bugs.id = bug_tags.bug_id
    //
    $strsql .= "AND " . ciniki_core_dbQuote($ciniki, "{$prefix}s.id") . " = " . ciniki_core_dbQuote($ciniki, "{$prefix}_tags") . "." . ciniki_core_dbQuote($ciniki, "{$prefix}_id") . " ";

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbRspQuery');
    return ciniki_core_dbRspQuery($ciniki, $strsql, $module, $container, $row_name, $no_row_err);
}
?>
