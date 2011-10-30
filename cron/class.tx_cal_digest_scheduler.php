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
	// these variables are set by scheduler task configuration
	var $uid;
	var $calendar;
	var $mailRecipient;
	var $mailSender;
	var $digestForecastDays;
	var $langValue;
    
    var $LANG;
    
    /**
     * next function fixes PHP4 issues
     */
	function tx_cal_calendar_scheduler() {
		$this->__construct();
	}

	/**
	* This is the main class for the scheduler. It is called each time an execution
	* is scheduled.
	* \return true iff everything went right
	*/
	public function execute() {
		// setup backend language
		$LANG = t3lib_div::makeInstance('language');
		$LANG->lang = $this->langValue;
		$LANG->includeLLFile('EXT:cal/locallang_tca.php');
		$LANG->includeLLFile('EXT:cal/locallang_db.php');
		$LANG->includeLLFile('EXT:cal/locallang.php');
	
		$content = '';
		
		// get the current date, formatted in the same way as in the cal extension
		$current_timestamp = time()-2*604800;
		$current_date = date("Ymd", $current_timestamp);
		
		// get the date of the day in $digestForecastDays, formatted as above
		$future_timestamp = time()+ ($this->digestForecastDays * 24 * 60 * 60);
		$future_date = date("Ymd", $future_timestamp);
		
		// get all events which are scheduled for the next seven days
		$select = '*';
		$table = 'tx_cal_event';
		$where = 'calendar_id = '.$this->calendar.' and start_date >= '.$current_date.' and end_date <= '.$future_date;             
		$queryresult = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where, $groupBy = '', $orderBy = 'start_date,start_time', $limit = ''); 
		
		// handle every event which was found
		while ($event = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($queryresult)) {
			// get some basic informations
			$event_id = $event['uid'];
			$event_title = $event['title'];
			$event_categories = '';
			$event_lecturer = $event['organizer'];
			
			// get organizer
			if ($event['organizer_id']) {
				$select = '*';
				$table= 'tx_cal_organizer';
				$where = 'uid = '.$event['organizer_id'];
				if ( $query = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where) &&
					$organizer = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($tmp) )
				{
					$event_organizer = $organizer['name'];
				}
			} else {
				$event_organizer = $event['organizer'];
			}
			
			// get location
			if ($event['location_id']) {
				$select = '*';
				$table= 'tx_cal_location';
				$where = 'uid = '.$event['location_id'];
				if( $query = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where) &&
					$location = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($query) )
				{
					$event_location = $location['name'];
				}
			}
			else {
				$event_location=$event['location'];
			}
			
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
			if ($LANG->lang == "de") {
				// array used to add the name of the day to each event, looks nicer than default translations
				$days = array("So", "Mo", "Di", "Mi", "Do", "Fr", "Sa");
				$event_day = date("w", $timestamp);
				$event_day = $days[$event_day];
			} else {
				$event_day = date("D", $timestamp);
			}
				
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
			$result_categories = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where);
			$event_categories = array();
			while ($connection_event_category = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($result_categories)) {
				$select = '*';
				$table = 'tx_cal_category';
				$where = 'uid = '.$connection_event_category['uid_foreign'];
				
				$result_category = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where);
				$category_title = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($result_category);
				$event_categories[] = $category_title['title'];
			}   
			
			// new format
			// TODO this could be changed to a dynamic layout
			$content .= $event_day.", ".$event_date.", ".$event_starttime.": *".$event_title."*\n";
			$content .= "   ".($event_organizer!=''? $event_organizer: $LANG->getLL('cal.pi_flexform.NomenNominandum'));
			$content .= ", ";
			$content .= $LANG->getLL('tx_cal_event.location')." ".($event_location!=''? $event_location: $LANG->getLL('cal.pi_flexform.NomenNominandum'))."\n";
			$content .= "   ".implode(", ",$event_categories)."\n";
			//$content .= "   <LINK>\n\n";	//TODO create link to event
		}
		
		$email = t3lib_div::makeInstance('t3lib_htmlmail'); 
		
		$email->start(); 
		$email->subject = $LANG->getLL('cal.cron.digestMailTitle');
		$email->from_email = $this->mailSender;
// 		$email->from_name = 'Sender';

		$email->setContent();   
		$email->setPlain($content);
		
// 		echo $email->preview();
		
		if ($email->send($this->mailRecipient)) {
			return true;
		} else {
			// if we come here, something went really wrong
			return false;
		}
	}

	/**
	* \see Interface tx_scheduler_AdditionalFieldProvider
	*/
	public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $parentObject) {
		$additionalFields = array();
		
		// option for calendar selection
		if (empty($taskInfo['calendar'])) {
			if($parentObject->CMD == 'edit') {
				$taskInfo['calendar'] = $task->calendar;
			} else {
				$taskInfo['calendar'] = '';
			}
		}
		// Initialize extra field "receiver"
		if (empty($taskInfo['mailRecipient'])) {
			if ($parentObject->CMD == 'add') {
					// In case of new task and if field is empty, set default email address
				$taskInfo['mailRecipient'] = $GLOBALS['BE_USER']->user['email'];

			} elseif ($parentObject->CMD == 'edit') {
					// In case of edit, and editing a test task, set to internal value if not data was submitted already
				$taskInfo['mailRecipient'] = $task->mailRecipient;
			} else {
					// Otherwise set an empty value, as it will not be used anyway
				$taskInfo['mailRecipient'] = '';
			}
		}
		// Initialize extra field "sender"
		if (empty($taskInfo['mailSender'])) {
			if ($parentObject->CMD == 'add') {
					// In case of new task and if field is empty, set default email address
				$taskInfo['mailSender'] = $GLOBALS['BE_USER']->user['email'];

			} elseif ($parentObject->CMD == 'edit') {
					// In case of edit, and editing a test task, set to internal value if not data was submitted already
				$taskInfo['mailSender'] = $task->mailSender;
			} else {
					// Otherwise set an empty value, as it will not be used anyway
				$taskInfo['mailSender'] = '';
			}
		}
		// Inititialize extra field "digestForecastDays"
		if (empty($taskInfo['digestForecastDays'])) {
			if($parentObject->CMD == 'edit') {
				$taskInfo['digestForecastDays'] = $task->digestForecastDays;
			} else {
				$taskInfo['digestForecastDays'] = 7;
			}
		}
		// Inititialize extra field "digestForecastDays"
		if (empty($taskInfo['langValue'])) {
			if($parentObject->CMD == 'edit') {
				$taskInfo['langValue'] = $task->langValue;
			} else {
				$taskInfo['langValue'] = 'default';
			}
		}
		
		// Write the code for calendar selection field
		$fieldIDcalendar = 'tx_scheduler[calendar]';
		$fieldCode = '<select name="'.$fieldIDcalendar.'" id="calendar" >';
		$res = $GLOBALS['TYPO3_DB']->sql_query('SELECT uid, title
												FROM tx_cal_calendar
												WHERE deleted=0 AND hidden=0');
		while ($res && $row = mysql_fetch_assoc($res))
			$fieldCode .= '<option value="'.$row['uid'].'" '.
					(($taskInfo['calendar']==$row['uid'])? ' selected="selected" ':' ').'>'.
					$row['title'].
					'</option>';
		$fieldCode .= '</select>';
		$additionalFields[$fieldIDcalendar] = array(
			'code'     => $fieldCode,
			'label'    => 'Calendar'
		);

		$fieldIDrecipient = 'tx_scheduler[mailRecipient]';
		$fieldCode = '<input name="'.$fieldIDrecipient.'" id="mailRecipient" type="text" size="30" value="'.$taskInfo['mailRecipient'].'" />';
		$additionalFields[$fieldIDrecipient] = array(
			'code'     => $fieldCode,
			'label'    => 'Digest Recipient'
		);
		
		$fieldIDsender = 'tx_scheduler[mailSender]';
		$fieldCode = '<input name="'.$fieldIDsender.'" id="mailSender" type="text" size="30" value="'.$taskInfo['mailSender'].'" />';
		$additionalFields[$fieldIDsender] = array(
			'code'     => $fieldCode,
			'label'    => 'Digest Sender (reply to)'
		);
		
		$fieldIDdigestForecastDays = 'tx_scheduler[digestForecastDays]';
		$fieldCode = '<input name="'.$fieldIDdigestForecastDays.'" id="digestForecastDays" type="text" size="3" value="'.$taskInfo['digestForecastDays'].'" />';
		$additionalFields[$fieldIDdigestForecastDays] = array(
			'code'     => $fieldCode,
			'label'    => 'Forecast Days'
		);
		
		$fieldIDlangValue = 'tx_scheduler[langValue]';
		$fieldCode = '<input name="'.$fieldIDlangValue.'" id="langValue" type="text" size="3" value="'.$taskInfo['langValue'].'" />';
		$additionalFields[$fieldIDlangValue] = array(
			'code'     => $fieldCode,
			'label'    => 'Language (use ID)'
		);
		
		return $additionalFields;
	}

	/**
	* \see Interface tx_scheduler_AdditionalFieldProvider
	*/
	public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $parentObject) {
		$submittedData['calendar'] = intval($submittedData['calendar']);
		$submittedData['mailRecipient'] = $submittedData['mailRecipient'];
		$submittedData['mailSender'] = $submittedData['mailSender'];
		$submittedData['digestForecastDays'] = intval($submittedData['digestForecastDays']);
		$submittedData['langValue'] = $submittedData['langValue'];
		
		return true;
	}

	/**
	* \see Interface tx_scheduler_AdditionalFieldProvider
	*/
	public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
		$task->calendar = $submittedData['calendar'];
		$task->mailRecipient = $submittedData['mailRecipient'];
		$task->mailSender = $submittedData['mailSender'];
		$task->digestForecastDays = $submittedData['digestForecastDays'];
		$task->langValue = $submittedData['langValue'];
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/os_cal/cron/class.tx_cal_digest_scheduler.php'])  {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/os_cal/cron/class.tx_cal_digest_scheduler.php']);
}
?>