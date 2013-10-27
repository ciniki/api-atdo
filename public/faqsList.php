<?php
//
// Description
// ===========
// This function will return a list of notes assigned to the user and/or the business.
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// Returns
// -------
// <notes>
// 		<note id="1" subject="Task subject" assigned="yes" private="yes" due_date=""/>
// </notes>
//
function ciniki_atdo_faqsList($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Status'), 
        'limit'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Limit'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'atdo', 'private', 'checkAccess');
    $rc = ciniki_atdo_checkAccess($ciniki, $args['business_id'], 'ciniki.atdo.faqsList'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
	$date_format = ciniki_users_dateFormat($ciniki);

	$strsql = "SELECT ciniki_atdos.id, subject, "
		. "IF(ciniki_atdos.category='', 'Uncategorized', ciniki_atdos.category) AS name, "
	//	. "IF((ciniki_atdos.appointment_flags&0x01)=1, 'yes', 'no') AS allday, "
	//	. "IF((ciniki_atdos.perm_flags&0x01)=1, 'yes', 'no') AS private, "
		. "IF(ciniki_atdos.status=1, 'open', 'closed') AS status "
	//	. "priority, "
	//	. "IF((u1.perms&0x04)=4, 'yes', 'no') AS assigned, "
	//	. "DATE_FORMAT(start_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') AS start_date, "
	//	. "duration, "
	//	. "IFNULL(DATE_FORMAT(ciniki_atdos.due_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "'), '') AS due_date, "
	//	. "IF((ciniki_atdos.due_flags&0x01)=1, '', IF(ciniki_atdos.due_date=0, '', DATE_FORMAT(ciniki_atdos.due_date, '%l:%i %p'))) AS due_time, "
	//	. "u2.user_id AS assigned_user_ids, "
	//	. "IFNULL(u3.display_name, '') AS assigned_users "
		. "FROM ciniki_atdos "
	//	. "LEFT JOIN ciniki_atdo_users AS u1 ON (ciniki_atdos.id = u1.atdo_id AND u1.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
	//	. "LEFT JOIN ciniki_atdo_users AS u2 ON (ciniki_atdos.id = u2.atdo_id && (u2.perms&0x04) = 4) "
	//	. "LEFT JOIN ciniki_users AS u3 ON (u2.user_id = u3.id) "
		. "WHERE ciniki_atdos.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND type = 4 "
		. "";
	if( isset($args['status']) ) {
		switch($args['status']) {
			case 'Open':
			case 'open': $strsql .= "AND ciniki_atdos.status = 1 ";
				break;
			case 'Closed':
			case 'closed': $strsql .= "AND ciniki_atdos.status = 60 ";
				break;
		}
	}
	// Check for public/private notes, and if private make sure user created or is assigned
//	$strsql .= "AND ((perm_flags&0x01) = 0 "  // Public to business
//			// created by the user requesting the list
//			. "OR ((perm_flags&0x01) = 1 AND ciniki_atdos.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
//			// Assigned to the user requesting the list
//			. "OR ((perm_flags&0x01) = 1 AND (u1.perms&0x04) = 0x04) "
//			. ") "
//		. "ORDER BY assigned DESC, priority DESC , due_date DESC, ciniki_atdos.id, u3.display_name "
//		. "";
	$strsql .= "ORDER BY category, subject "
		. "";
	if( isset($args['limit']) && $args['limit'] != '' && $args['limit'] > 0 ) {
		$strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";
	}
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryTree');
	$rc = ciniki_core_dbHashQueryTree($ciniki, $strsql, 'ciniki.atdo', array(
		array('container'=>'categories', 'fname'=>'name', 'name'=>'category',
			'fields'=>array('name')),
		array('container'=>'faqs', 'fname'=>'id', 'name'=>'faq',
			'fields'=>array('id', 'subject')),
		));
	// error_log($strsql);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['categories']) ) {
		return array('stat'=>'ok', 'categories'=>array());
	}
	return array('stat'=>'ok', 'categories'=>$rc['categories']);
}
?>
