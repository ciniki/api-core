<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
function ciniki_core_dropboxParseRTFToText($ciniki, $business_id, $client, $path) {

	//
	// Get the file contents
	//
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_SSLVERSION, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $client->getAccessToken()));
	if( $path[0] != '/' ) { $path = '/' . $path; }
	curl_setopt($ch, CURLOPT_URL, "https://api-content.dropbox.com/1/files/auto$path");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
	$file_contents = curl_exec($ch);
	if( $file_contents === false ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2282', 'msg'=>'Unable to get file'));
	}
	curl_close($ch);

	//
	// Parse the RTF content
	//
    if( isset($ciniki['config']['ciniki.core']['unrtf']) ) {
        $unrtf = $ciniki['config']['ciniki.core']['unrtf'];

        $tmp_filename = '/tmp';
        if( isset($ciniki['config']['ciniki.core']['tmp_dir']) && $ciniki['config']['ciniki.core']['tmp_dir'] != '' ) {
            $tmp_filename = $ciniki['config']['ciniki.core']['tmp_dir'];
        }
        $tmp_filename .= '/' . preg_replace('/\//', '_', $path);

        file_put_content($tmp_filename, $file_contents);
        $rc = exec("$unrtf --html $tmp_filename", $output);
        if( isset($output) && count($output) > 0 ) {
            $text = '';
            foreach($output as $line) {
                $line = preg_replace('/<br>/', "\n", $line);
                $line = preg_replace('/&ldquo;/', '"', $line);
                $line = preg_replace('/&rdquo;/', '"', $line);
                $line = preg_replace('/&quot;/', '"', $line);
                $line = preg_replace('/&nbsp;/', '', $line);
                $line = strip_tags($line);
                if( $line != '' ) {
                    $text .= $line;
                }
            }
            $file_contents = $text;
            unline($tmp_filename);
        }
    }

	return array('stat'=>'ok', 'content'=>$file_contents);
}
?>
