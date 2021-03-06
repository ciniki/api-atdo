<?php
//
// Description
// -----------
// This function will search the atdo module for appointments or tasks and return 
// them in the calendar/appointment format.
//
// Arguments
// ---------
// ciniki:
// tnid:         The ID of the tenant to search for appointments.
// args:                The arguments passed to the calling public method ciniki.calendars.search.
//
// Returns
// -------
//
function ciniki_atdo_hooks_appointmentSearch($ciniki, $tnid, $args) {

    if( !isset($args['start_needle']) || $args['start_needle'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.atdo.1', 'msg'=>'No search specified'));
    }

    //
    // Get the module settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc =  ciniki_core_dbDetailsQuery($ciniki, 'ciniki_atdo_settings', 'tnid', $args['tnid'], 'ciniki.atdo', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $settings = $rc['settings'];

    //
    // Load timezone info
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Load datetime formats
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    $datetime_format = ciniki_users_datetimeFormat($ciniki, 'php');

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    $strsql = "SELECT ciniki_atdos.id, type, subject, location, priority,  "
        . "appointment_date AS start_ts, "
        . "appointment_date AS date, "
        . "appointment_date AS start_date, "
        . "appointment_date AS time, "
        . "appointment_date AS 12hour, "
//      . "UNIX_TIMESTAMP(appointment_date) AS start_ts, "
//      . "DATE_FORMAT(appointment_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') AS start_date, "
//      . "DATE_FORMAT(appointment_date, '%Y-%m-%d') AS date, "
//      . "DATE_FORMAT(appointment_date, '%H:%i') AS time, "
//      . "DATE_FORMAT(appointment_date, '%l:%i') AS 12hour, "
        . "ciniki_atdos.status, "
        . "appointment_duration as duration, '#ffdddd' AS colour, 'ciniki.atdo' AS 'module' "
        . "FROM ciniki_atdos "
        . "LEFT JOIN ciniki_atdo_users AS u1 ON (ciniki_atdos.id = u1.atdo_id AND u1.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
//      . "LEFT JOIN ciniki_atdo_followups ON (ciniki_atdos.id = ciniki_atdo_followups.atdo_id "
//          . "AND (ciniki_atdo_followups.content LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
//          . "OR ciniki_atdo_followups.content LIKE ' %" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' ))"
        . "WHERE ciniki_atdos.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        // Search items with an appointment date or due date
        . "AND (ciniki_atdos.appointment_date != 0 OR ciniki_atdos.due_date != 0) "
        . "AND (type = 1 OR type = 2) "
        . "";
// Search for all tasks, even when closed
    if( isset($args['full']) && $args['full'] == 'yes' ) {
        $strsql .= "AND ciniki_atdos.status <= 60 ";
    } else {
        $strsql .= "AND ciniki_atdos.status = 1 ";
    }
    $strsql .= "AND (subject LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . " OR DATE_FORMAT(appointment_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "') LIKE '%" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . ") "
        . "";
    // Check for public/private atdos, and if private make sure user created or is assigned
    $strsql .= "AND ((ciniki_atdos.perm_flags&0x01) = 0 "  // Public to tenant
            // created by the user requesting the list
            . "OR ((ciniki_atdos.perm_flags&0x01) = 1 AND ciniki_atdos.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "') "
            // Assigned to the user requesting the list
            . "OR ((ciniki_atdos.perm_flags&0x01) = 1 AND (u1.perms&0x04) = 0x04) "
            . ") "
        . "";
    if( isset($args['date']) && $args['date'] != '' ) {
        $strsql .= "ORDER BY ABS(DATEDIFF(DATE(ciniki_atdos.appointment_date), DATE('" . ciniki_core_dbQuote($ciniki, $args['date']) . "'))), subject ";
    } else {
        $strsql .= "ORDER BY ABS(DATEDIFF(DATE(ciniki_atdos.appointment_date), DATE(NOW()))), subject ";
    }
    if( isset($args['limit']) && is_numeric($args['limit']) && $args['limit'] > 0 ) {
        $strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";   // is_numeric verified
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.atdo', array(
        array('container'=>'appointments', 'fname'=>'id', 
            'fields'=>array('id', 'module', 'start_ts', 'start_date', 'date', 'time', '12hour', 'duration', 'colour', 'type', 
                'subject', 'priority', 'status'),
            'utctotz'=>array('start_ts'=>array('timezone'=>$intl_timezone, 'format'=>'U'),
                'start_date'=>array('timezone'=>$intl_timezone, 'format'=>$datetime_format),
                'date'=>array('timezone'=>$intl_timezone, 'format'=>'Y-m-d'),
                'time'=>array('timezone'=>$intl_timezone, 'format'=>'H:i'),
                '12hour'=>array('timezone'=>$intl_timezone, 'format'=>'g:i'),
            )),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Apply colours if they have been configured
    //
    if( isset($rc['appointments']) && isset($settings['tasks.status.60']) ) {
        foreach($rc['appointments'] as $appointment_num => $appointment) {
            if( $appointment['type'] == 1 ) {
                $rc['appointments'][$appointment_num]['colour'] = $settings['appointments.status.1'];
            }
            elseif( $appointment['type'] == 2 ) {
                if( $appointment['status'] == 60 ) {
                    $rc['appointments'][$appointment_num]['colour'] = $settings['tasks.status.60'];
                } else {
                    $rc['appointments'][$appointment_num]['colour'] = $settings['tasks.priority.' . $appointment['priority']];
                }
                
            }
        }
    }
    return $rc;
}
?>
