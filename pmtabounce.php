<?php
/**
 * This file contains the 'pmtabounce' addon.
 *
 * @package Interspire_Addons
 * @subpackage Addons_pmtabounce
 */

/**
 * Make sure the base Interspire_Addons class is defined.
 */
if (!class_exists('Interspire_Addons', false)) {
  require_once(dirname(dirname(__FILE__)) . '/interspire_addons.php');
}

/**
 * Load language definitions in global context.
 */
require_once (dirname(__FILE__) . '/language/language.php');

/**
 * This class implements the addon callbacks.
 *
 * @uses Interspire_Addons
 * @uses Interspire_Addons_Exception
 */
class Addons_pmtabounce extends Interspire_Addons
{
  protected $default_settings = array(
    'directory' => '/var/log/pmta/bounces', 
    'delete_after' => false);
  
  /**
   * Install
   * This is called when the addon is installed in the main application.
   * In this case, it simply sets the default settings and then calls the parent install method to add itself to the database.
   *
   * @uses default_settings
   * @uses Interspire_Addons::Install
   * @uses Interspire_Addons_Exception
   *
   * @throws Throws an Interspire_Addons_Exception if something goes wrong with the install process.
   * @return True Returns true if all goes ok with the install.
   */
  public function Install()
  {
    /* check if language file is loaded */
    if (!defined('LNG_Addon_' . $this->GetId() . '_IsLoaded')) {
      throw new Exception('Language file for ' . $this->GetId() . ' not loaded');
    }

    /* remove old event from persistent storage
    InterspireEvent::listenerUnregister('IEM_CRON_RUNADDONS', 
      array ('Addons_pmtabounce', 'Cron_GetJobs'),
      '{%IEM_ADDONS_PATH%}/pmtabounce/pmtabounce.php');
    */
    
    $this->enabled = true;
    $this->configured = true;
    $this->settings = $this->default_settings;
    
    /* store default settings in database if not done already
    $settings = array_merge($this->default_settings, self::GetSettings()); */
    /* self::SetSettings($this->default_settings); */

    try {
      $status = parent::Install();
    } 
    catch (Interspire_Addons_Exception $e) {
      throw new Exception('Unable to install addon ' . $this->GetId() . ': ' . $e->getMessage());
    }

    /* show message in error log in application */
    trigger_error('Installed ' .  $this->GetId() . ' addon', E_USER_NOTICE);
    trigger_error('Settings: ' . implode(', ', array_keys($this->settings)), E_USER_NOTICE);
    return true;
  }

  /**
   * GetEventListeners
   * The addon uses a few events to place itself in the app and allow it to work.
   *
   * IEM_SETTINGSAPI_LOADSETTINGS
   * Adds new options to the settings for cron
   *
   * IEM_CRON_RUNADDONS
   * Adds itself to the list of addons that can have cron jobs
   *
   * Events are persisted in admin/com/storage/iem_stash_storage.php and must be removed with
   * InterspireEvent::listenerUnregister() to deactivate the event.
   *
   * @return Array Returns an array containing the events to listen to.
   */
  public function GetEventListeners()
  {
    return
      array (
        array (
          'eventname' => 'IEM_SETTINGSAPI_LOADSETTINGS',
          'trigger_details' => array (
            'Addons_pmtabounce',
            'LoadSettings'
          ),
          'trigger_file' => '{%IEM_ADDONS_PATH%}/pmtabounce/pmtabounce.php'
        ),
        array (
          'eventname' => 'IEM_CRON_RUNADDONS',
          'trigger_details' => 'Pmtabounce_Cron_GetJobs',
          'trigger_file' => '{%IEM_ADDONS_PATH%}/pmtabounce/cron.php'
        )
      );
  }
  
  /**
   * LoadSettings
   * Adds new options to the "cron settings" page and settings database table.
   * Sets the "last run time" for the job to -1 which means "hasn't run".
   *
   * Adds a new settings entry called "CRON_PMTABOUNCE" to the settings table.
   * Also adds the following times to the "run job every" dropdown box:
   * - 1 minute
   * - 2, 5, 10, 15, 20, 30 minutes
   *
   * @param EventData_IEM_SETTINGSAPI_LOADSETTINGS $data The current settings data which is passed in by reference (is an object).
   *
   * @uses EventData_IEM_SETTINGSAPI_LOADSETTINGS
   */
  public static function LoadSettings(EventData_IEM_SETTINGSAPI_LOADSETTINGS $data)
  {
    $data->data->Schedule['pmtabounce'] = array (
      'lastrun' => -1,
    );

    $data->data->pmtabounce_options = array (
      '0'    => 'disabled',
      '1'    => '1_minute',
      '5'    => '5_minutes',
      '15'   => '15_minutes',
      '60'   => '1_hour',
      '240'  => '4_hours',
      '720'  => '12_hours',
      '1440' => '1_day',
    );

    $data->data->Areas[] = 'CRON_PMTABOUNCE';
  }

  /**
   * Configure
   * This method is called when the addon needs to be configured.
   * It uses the templates/settings.tpl file to show its current settings and display the settings form.
   *
   * @uses settings
   * @uses template_system
   * @uses InterspireTemplate::Assign
   * @uses InterspireTemplate::ParseTemplate
   *
   * @return String Returns the settings form with the current settings pre-filled.
   */
  public function Configure()
  {
    /* load settings from database 
    $settings = array_merge($this->default_settings, self::GetSettings());
    */
    foreach ($this->settings as $k => $v) {
      $this->template_system->Assign($k, $v);
    }

    $this->template_system->Assign('SettingsUrl', $this->settings_url, false);
    $this->template_system->Assign('ApplicationUrl', $this->application_url, false);

    return $this->template_system->ParseTemplate('settings', true);
  }

  /**
   * SaveSettings
   * This is called when the settings form is submitted.
   * It checks if any values were posted.
   * It then checks against the settings it should find (from default_settings) to make sure you're not trying to sneak any extra settings in there
   *
   * If no form was posted or if you post invalid options, this will return false (which then displays an error message).
   *
   * @see Configure
   * @uses default_settings
   * @uses db
   *
   * @return Boolean Returns false if an invalid settings form is posted or if
   */
  public function SaveSettings()
  {
    /* reject unexpected parameters */
    $settings = array_intersect_key($_POST, $this->default_settings);
    if (empty($settings)) {
      return false;
    }

    /* set to false if checkbox unset */
    if (!isset($settings['delete_after'])) {
      $settings['delete_after'] = false;
    }

    return self::SetSettings($settings);
  }

  /**
   * GetSettings
   * Retrieves the saved settings from the database.
   *
   * @see Configure
   * @uses db
   *
   * @return Array The saved settings.
   */
  public static function GetSettings()
  {
    $db = IEM::getDatabase();
    if (!$db) {
      trigger_error("Could not get database handle", E_USER_ERROR);
      return array();
    }

    $id = str_replace('Addons_', '', __CLASS__);
    $data = $db->FetchOne("SELECT settings FROM [|PREFIX|]addons WHERE addon_id='{$id}'");
    if (!$data) {
      trigger_error("Could not fetch settings from database", E_USER_ERROR);
      return array();
    }
    $settings = unserialize($data);
    return $settings;
  }

  /**
   * SetSettings
   * Saves the settings to the database.
   *
   * @uses db
   *
   * @param Array $settings The settings to save for this addon.
   *
   * @return Boolean True if saved successfully, otherwise false.
   */
  public static function SetSettings($settings)
  {
    $db = IEM::getDatabase();
    if (!$db) {
      trigger_error("Could not get database handle", E_USER_ERROR);
      return false;
    }

    $id = str_replace('Addons_', '', __CLASS__);
    $result = $db->Query("UPDATE [|PREFIX|]addons SET configured=1, settings='" . $db->Quote(serialize($settings)) . "' WHERE addon_id='{$id}'");
    if (!$result) {
      trigger_error("Could not update settings to database", E_USER_ERROR);
    }
    return (bool)$result;
  }
  
}
