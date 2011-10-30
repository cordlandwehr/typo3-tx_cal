<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2010-2011  Andreas Cord-Landwehr <cola@uni-paderborn.de>
 * (c) 2010-2011  Max Drees <maxdrees@campus.uni-paderborn.de>
 * (c) 2010-2011  HEINZ NIXDORF INSTITUTE, University of Paderborn
 *
 * You can redistribute this file and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation;
 * either version 2 of the License, or (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This file is distributed in the hope that it will be useful for ministry,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the file!
 ***************************************************************/

require_once(t3lib_extMgm::extPath('cal').'controller/class.tx_cal_functions.php');
require_once(PATH_t3lib.'class.t3lib_htmlmail.php');    

class tx_cal_digest_scheduler 
	extends tx_scheduler_Task 
	implements tx_scheduler_AdditionalFieldProvider
{
	var $uid;
	var $calendar;
    
    /**
     * next function (can) fixe PHP4 issue if present
     */
	function tx_cal_calendar_scheduler() {
		$this->__construct();
	}

	/**
	* This is the main class for the scheduler. It is called each time an execution
	* is schedulled.
	* \return true iff everything went right
	*/
	public function execute() {
		$content = '';
		
		// get the current date, formatted in the same way as in the cal extension
		$current_timestamp = time()-2*604800;
		$current_date = date("Ymd", $current_timestamp);
		
		// get the date of the day in seven days, formatted as above
		$future_timestamp = time()+604800; // 604800 = 7 * 24 * 60 * 60
		$future_date = date("Ymd", $future_timestamp);
		
		// array used to add the name of the day to each event
		//FIXME add proper localization
		$days = array("So", "Mo", "Di", "Mi", "Do", "Fr", "Sa");
	
		// get all events which are scheduled for the next seven days
		$select = '*';
		$table = 'tx_cal_event';
		$where = 'calendar_id = '.$this->calender.' and start_date >= '.$current_date.' and end_date <= '.$future_date;             
		$queryresult = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where, $groupBy = '', $orderBy = 'start_date,start_time', $limit = ''); 
		
		// handle every event which was found
		while ($event = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($queryresult)) {
			// get some basic informations
			$event_id = $event['uid'];
			$event_title = $event['title'];
			$event_categories = '';
			$event_lecturer = $event['organizer'];
			
			$select = '*';
			$table= 'tx_cal_organizer';
			$where = 'uid = '.$event['organizer_id'];
            
			$tmp = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select,$table,$where);           
			$tmp2 = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($tmp);
			$event_group = $tmp2['name'];
			
			$select = '*';
			$table= 'tx_cal_location';
			$where = 'uid = '.$event['location_id'];
			
			$tmp = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select,$table,$where);           
			$tmp2 = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($tmp);
			$event_location = $tmp2['name'];
			
			
						
			$event_date = $event['start_date'];
			$event_starttime = $event['start_time'];
			$event_endtime = $event['end_time'];
			
			
			// seperate day, month and year of the date
			$event_date_year = substr($event_date, 0, 4);
			$event_date_month = substr($event_date, 4, 2);
			$event_date_day = substr($event_date, 6, 2);
			
			// transform the date into a timestamp
			$timestamp = mktime(0, 0, 0, $event_date_month, $event_date_day, $event_date_year);
			
			// get the name of the weekday of the event
			$event_day = date("w", $timestamp);
			$event_day = $days[$event_day];
			
			// update the date of the event such that it has the format dd.mm, e.g. 06.10
			$event_date = $event_date_day.".".$event_date_month.".";            
			
			// update the times of the event such they have the format hh:mm, eg. 08:43
			$event_starttime = date("H:i", $event_starttime);
			
			if ($event_endtime != "0") {
				$event_endtime = date("H:i", $event_endtime);
				$event_endtime = " - ".$event_endtime;
			} else {
				$event_endtime = "";
			}
			
			
			

			$select = '*';
			$table = 'tx_cal_event_category_mm';
			$where = 'uid_local = '.$event_id;
			
			$result_categories = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select,$table,$where);                                 
			
			while ($connection_event_category = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($result_categories)) {
				$select = '*';
				$table = 'tx_cal_category';
				$where = 'uid = '.$connection_event_category['uid_foreign'];
				
				$result_category = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select,$table,$where);
				$category_title = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($result_category);
				$event_categories = $category_title['title'];
			}   
			
			// new format
			//FIXME add dynamic layouts
			$content .= $event_day.", ".$event_date.", ".$event_starttime.": *".$event_title."*\n";
			$content .= "   Vortragender ".$event_lecturer.", Raum ".$event_location."\n";
			$content .= "   ".$event_categories."\n";
			$content .= "   <LINK>\n\n";
			
			/* old format           
			$content = $content.$event_title."\n";          
			$content = $content.$event_categories."\n";
			$content = $content.$event_lecturer.", ".$event_group."\n";         
			$content = $content.$event_day.", ".$event_date.", ".$event_starttime.$event_endtime.", ".$event_location."\n\n";
			*/
		}
		
		$email = t3lib_div::makeInstance('t3lib_htmlmail'); 
		
		$email->start();
		$email->setRecipient('maxdrees@mail.upb.de');   
		$email->subject = 'Oberseminare für diese Woche';
		$email->from_email = 'absender@upb.de';
		$email->from_name = 'Absender';
		
		
		$email->setContent();   
		$email->setPlain($content);
//         $email->send(); //FIXME reactivate

		echo "--- TEST DATA OUTPUT ---";
		echo($content);
		die();
		
		return true;
		
		// if we come here, something went really wrong
		return false;
	}

	/**
	* \see Interface tx_scheduler_AdditionalFieldProvider
	*/
	public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $parentObject) {
		if (empty($taskInfo['calendar'])) {
			if($parentObject->CMD == 'edit') {
				$taskInfo['calendar'] = $task->calendar;
			} else {
				$taskInfo['calendar'] = '';
			}
		}

		// Write the code for the field
		$fieldCode = '<select name="tx_scheduler[calendar]" id="calendar" >';
		$res = $GLOBALS['TYPO3_DB']->sql_query('SELECT uid, title
												FROM tx_cal_calendar
												WHERE deleted=0 AND hidden=0');
		while ($res && $row = mysql_fetch_assoc($res))
			$fieldCode .= '<option value="'.$row['uid'].'" '.
					(($taskInfo['calendar']==$row['uid'])? ' selected="selected" ':' ').'>'.
					$row['title'].
					'</option>';
		$fieldCode .= '</select>';
		
		$additionalFields = array();
		$additionalFields[$fieldID] = array(
			'code'     => $fieldCode,
			'label'    => 'Calendar'
		);

		return $additionalFields;
	}

	/**
	* \see Interface tx_scheduler_AdditionalFieldProvider
	*/
	public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $parentObject) {
		$submittedData['calendar'] = intval($submittedData['calendar']);
		return true;
	}

	/**
	* \see Interface tx_scheduler_AdditionalFieldProvider
	*/
	public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
		$task->calendar = $submittedData['calendar'];
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/os_cal/cron/class.tx_cal_digest_scheduler.php'])  {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/os_cal/cron/class.tx_cal_digest_scheduler.php']);
}
?>