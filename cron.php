<?php
/**
 * This file is part of Postmastery "pmtabounce" addon. 
 *
 * @package Interspire_Addons
 * @subpackage Addons_pmtabounce
 */

/**
 * This should never get called outside of the cron system.
 * IEM_CRON_JOB is defined in the main cron/addons.php file,
 * so if it's not available then someone is doing something strange.
 */
if (!defined('IEM_CRON_JOB')) {
  die("You cannot access this file directly.");
}

/**
 * Pmtabounce_Cron_GetJobs
 * Get the jobs to run from the addons.
 * Run from the cron scheduler script.
 * It expects an array of data in return containing:
 *
 * <code>
 * Array
 * (
 *  'addonid' => 'my_addon_id',
 *  'file' => '/full/path/to/file',
 * )
 * </code>
 *
 * If the process functions require any id's they need to be supplied in a 'jobids' array like this:
 * <code>
 * Array
 * (
 *  'addonid' => 'my_addon_id',
 *  'file' => '/full/path/to/file',
 *  'jobids' => array (
 *    1,
 *    2,
 *    3
 *  ),
 * )
 * </code>
 *
 * Id's are not required to be supplied as some types of addons won't need them - eg a 'backup' addon.
 *
 * They also don't need to be "job" id's from the "jobs" table, they can be anything (they just come under 
 * the "jobids" array).
 * They could be newsletter id's (for example to create pdf's for), or list id's, or subscriber id's.
 * It's up to the addon itself to work out what they are and what to do with them.
 * At this stage, we don't care what sort of id's they are or what they reference.
 *
 * The file must contain a class called 'Jobs_Cron_API_addonid' so we have a consistent class to call.
 *
 * Inside that class, it needs a 'ProcessJobs' method which takes all of the id's from here to process.
 * From there, the ProcessJobs method can do whatever it likes with those id's.
 * If it throws an exception, it will be caught here and just displayed in output
 * which should be captured via cron/scheduled tasks and emailed somewhere.
 *
 * @param EventData_IEM_CRON_RUNADDONS $data The current list of cron tasks that need to be processed. This 
 * function just adds it's own data to the end.
 *
 * @return Void The data is passed in by reference, so this doesn't return anything.
 *
 * @uses EventData_IEM_CRON_RUNADDONS
 */
function Pmtabounce_Cron_GetJobs(EventData_IEM_CRON_RUNADDONS $data)
{
  /* NOTE: this function is called every minute,
     but jobs are only passed to ProcessJobs() if the cron schedule period is passed */
  $data->jobs_to_run[] = array (
    'addonid' => 'pmtabounce',
    'file' => __FILE__,
    'jobs' => array("dummy-job")
  );
}

/**
 * Called from admin/cron/addons.php
 */
class Jobs_Cron_API_pmtabounce
{
  protected $settings = array();
  protected $subscribers = null;
  protected $lists = null;
  protected $stats = null;
  
  /**
   * Called when cron schedule period is passed
   */
  public function ProcessJobs($jobs = array())
  {
    /* retrieve addon settings from database */
    $this->settings = Addons_pmtabounce::GetSettings();
    if (!isset($this->settings['directory'])) {
      trigger_error("Unable to retrieve addon settings", E_USER_ERROR);
      return;
    }

    trigger_error("Looking for accounting files in " . $this->settings['directory'], E_USER_NOTICE);
    $files = $this->GetAcctFiles($this->settings['directory']);
    if (empty($files)) {
      return;
    }
    trigger_error("Found " . count($files) . " files", E_USER_NOTICE);
    
    /* instantiate api objects */
    require_once (SENDSTUDIO_FUNCTION_DIRECTORY . '/sendstudio_functions.php');
    $sendstudio = new Sendstudio_Functions();
    $this->subscribers = $sendstudio->GetApi('Subscribers');
    $this->lists = $sendstudio->GetApi('Lists');
    $this->stats = $sendstudio->GetApi('Stats');
    
    foreach ($files as $filename) {
      trigger_error("Processing " . $filename, E_USER_NOTICE);
      $this->ProcessFile($filename);
    }
  }
  
  /**
   * Get pending accounting files
   *
   * @param String $directory The directory to scan
   * @return 
   *
   * @access private
   */
  protected function GetAcctFiles($directory)
  {
    $files = array();
    if (($dh = @opendir($directory)) !== false) {
      while (($file = readdir($dh)) !== false) {
        $path = "{$directory}/{$file}";
        if (is_file($path) && is_readable($path)) {
          /* check if file is not written to 
          if (($fh = @fopen($path, "a+") !== false) {
            fclose($fh); 
            ...
          } */
          if (preg_match("/\.csv\$/", $path)) {
            /* rename file to prevent being picked up again
            if (@rename($path, $path . ".pending")) {
              $files[] = $path . ".pending";
            }
            else {
              trigger_error("Could not rename file: {$path}", E_USER_ERROR);
            }
            */
            $files[] = $path;
          }
        }
      }
      closedir($dh);
    }
    // sort by filename
    natsort($files);
    return $files;
  }

  protected function ProcessFile($filename) 
  {
    $fh = @fopen($filename, "r");
    if (!$fh) {
      trigger_error("Could not open file: {$filename}", E_USER_ERROR);
      return false;
    }
    
    $result = $this->ParseFile($fh);

    fclose($fh);
    
    if ($result) {
      if ($this->settings['delete_after']) {
        if (!@unlink($filename)) {
          trigger_error("Could not delete file: {$filename}", E_USER_ERROR);
          return false;
        }
      }
      else {
        // $newname = str_replace(".pending", ".done", $filename);
        $newname = $filename . ".done";
        if (!@rename($filename, $newname)) {
          trigger_error("Could not rename file: {$filename}", E_USER_ERROR);
          return false;
        }
      }
    }  // else leave filename unchanged
    return true; // ? $result
  }
  
  protected function ParseFile($fh)
  {
    $fields = fgetcsv($fh, 1000, ',');
    if (!$fields) {
      // sometimes caused by empty accounting files without header
      trigger_error("Error reading CSV header (empty file?)", E_USER_NOTICE);
      return false;
    }
    
    /* required fields for all accounting records */
    
    if ($fields[0] != 'type') {
      trigger_error("No type column found in CSV", E_USER_NOTICE);
      return false;
    }
    
    $time_logged = array_search('timeLogged', $fields); // 2013–07–01 13:32:03+0200
    if (!isset($time_logged)) {
      trigger_error("No timeLogged column found in CSV", E_USER_NOTICE);
      return false;
    }
    
    $rcpt = array_search('rcpt', $fields); // pietje@hotmail.com
    if (!isset($rcpt)) {
      trigger_error("No rcpt column found in CSV", E_USER_NOTICE);
      return false;
    }
  
    /* required fields for (remote) bounce records */
    
    $dsn_diag    = array_search('dsnDiag',   $fields);   // 550 Requested action not taken: mailbox unavailable
    $bounce_cat  = array_search('bounceCat', $fields);   // bad-mailbox
    
    /* one or more list ids separated by commas */
    $lid = array_search('header_X-Mailer-LID', $fields);
    if (!$lid) {
      $lid = array_search('header_X-List-ID', $fields);
    }

    /* statid */
    $sid = array_search('header_X-Mailer-SID', $fields);
    if (!$sid) {
      $sid = array_search('header_X-Mailing-ID', $fields); 
    }

    while (($values = fgetcsv($fh, 1000, ',')) !== false) {
      if ($values[0] == 'b' || $values[0] == 'rb') {
        if (isset($dsn_diag) && isset($bounce_cat) && isset($lid) && isset($sid)) {
          $record = array(
            'timeLogged'   => $values[$time_logged],
            'rcpt'         => $values[$rcpt],
            'dsnDiag'      => $values[$dsn_diag],
            'bounceCat'    => $values[$bounce_cat],
            'X-Mailer-LID' => $values[$lid],
            'X-Mailer-SID' => $values[$sid]
          );
          if (isset($record['bounceCat']) && isset($record['X-Mailer-LID']) && isset($record['X-Mailer-SID'])) {
              trigger_error('Processing bounce record: ' . implode(', ', array_values($record)), E_USER_NOTICE);
              $this->ProcessRecord($record);
          } else {
              trigger_error('Skipping bounce record with empty value(s): ' . implode(', ', array_values($record)), E_USER_NOTICE);
          }
        }
        else {
          trigger_error("One or more fields not found in CSV with bounce records (dsnDiag, bounceCat, header_X-Mailer-LID, header_X-Mailer-SID)", E_USER_NOTICE);
          return false;  // skip remainder of file
        }
      }
    }
    return true;
  }
  
  protected function ProcessRecord($record)
  {
    // unix timestamp
    $bounce_time   = $record['timeLogged'];
    
    // email address
    $bounce_email  = $record['rcpt'];
    
    // bounce type
    $bounce_type   = 'ignore';
    if (preg_match('/bad-mailbox|inactive-mailbox|bad-domain/', $record['bounceCat'])) {
      $bounce_type = 'hard';
    }
    elseif (preg_match('/quota-issues|no-answer-from-host|relaying-issues|routing-errors/', $record['bounceCat'])) {
      $bounce_type = 'soft';
    }
    else {
      $bounce_type = 'ignore';
    }
    
    // bounce rule, translated by LNG_Bounce_Rule_xxx, see language.php
    $bounce_rule  = preg_replace('/-/', '_', $record['bounceCat']);
    
    // bounce message, could be missing, only saved for rule 'blockedcontent'
    $bounce_message = $record['dsnDiag'];
    
    // list ids
    $bounce_listids = Array();
    if (strpos($record['X-Mailer-LID'], ',') !== false) {
      $bounce_listids = explode(',', str_replace(' ', '', $record['X-Mailer-LID']));
    } 
    else {
      $bounce_listids = Array($record['X-Mailer-LID']);
    }

    // stat id
    $bounce_statid = $record['X-Mailer-SID'];  //abs(intval($record['X-Mailer-SID']))

    $subscriber_info = $this->subscribers->IsSubscriberOnList($bounce_email, $bounce_listids, 0, false, true, true);
    if ($subscriber_info) {
      $subscriber_id = $subscriber_info['subscriberid'];
      $bounce_listid = $subscriber_info['listid'];
      $already_bounced = $this->subscribers->AlreadyBounced($subscriber_id, $bounce_statid, $bounce_listid);
      if (!$already_bounced) {
        // register bounce for statistics
        $result = $this->stats->RecordBounceInfo($subscriber_id, $bounce_statid, $bounce_type);
        if (!$result) {
          trigger_error('Could not register bounce for statistics ' . $bounce_statid, E_USER_ERROR);
        }
        if ($bounce_type == 'hard' || $bounce_type == 'soft') {
          // register bounce for subscriber
          $disabled = $this->subscribers->RecordBounceInfo($subscriber_id, $bounce_statid, 
            $bounce_listid, $bounce_type, $bounce_rule, $bounce_message, $bounce_time);
          if ($disabled) {
            trigger_error('Subscriber ' . $subscriber_id . ' was disabled', E_USER_NOTICE);
          }
          else {
            trigger_error('Register bounce for subscriber ' . $subscriber_id, E_USER_NOTICE);
          }
        }
      }
      else {
        trigger_error('Subscriber ' . $subscriber_id . ', statistics ' . $bounce_statid . ' already bounced ', E_USER_NOTICE);
      }
    }
    else {
      trigger_error('Could not find subscriber: ' . $bounce_email . ' in lists ' . implode(', ', array_values($bounce_listids)), E_USER_NOTICE);
    }
  }
}
