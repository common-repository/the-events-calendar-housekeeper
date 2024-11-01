<?php
/*
Plugin Name: The Events Calendar Housekeeper
Description: Adds tools to keep your events under control. This version targets Events Calendar PRO 3.x.
Version: 1.2.0
Author: Barry Hughes
Author URI: http://freshlybakedwebsites.net
Text Domain: events-housekeeper
License: GPLv3 or later

	The Events Calendar Housekeeper - clean-up utility for The Events Calendar
	Copyright (C) 2012 Barry Hughes

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


/**
 * Sets up configurable garbage collection for expired events.
 */
class ECHousekeeper {
	/**
	 * Container for the ECHousekeeper instance.
	 *
	 * @var mixed (object | null)
	 */
	protected static $object = null;

	/**
	 * Holds the date string representing our cut-off date for expired events.
	 *
	 * @var string
	 */
	protected $expiryDate = '0000-00-00';

	/**
	 * Indicates if plugin prerequisites have been met or not.
	 *
	 * @var bool
	 */
	protected $preflightCheck = false;

	/**
	 * The maximum number of events to tackle in a single clean up operation. This can be adjusted directly (obtain the
	 * current object via the instance() method) by using an appropriate action and priority (ie, early during init).
	 *
	 * @var int
	 */
	public $cleanupBatchSize = 100;

	/**
	 * The plugin directory.
	 *
	 * @var string
	 */
	public $dir = '';

	/**
	 * The plugin URL.
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Setting keys.
	 */
	const SCHEDULE = 'ECHousekeeperSchedule';
	const SETTINGS = 'ECHousekeeperSettings';


	/**
	 * Creates a new instance of ECHousekeeper, which can then be accessed via the instance() method.
	 *
	 * @static
	 */
	public static function initialize() {
		$class = __CLASS__;

		if (self::$object === null)
			self::$object = new $class();
	}


	/**
	 * Initializes and sets up actions to conduct prerequisite checks, integrate with The Events Calendar settings pages and run scheduled tasks.
	 *
	 * @return ECHousekeeper
	 */
	protected function __construct() {
		$this->selfLocate();
		add_action('plugins_loaded', array($this, 'preflightChecks'));
		add_action('init', array($this, 'start'));
		add_action(self::SCHEDULE, array($this, 'doCollection'));
	}


	/**
	 * Determine the plugin's location on the file system and as a URL.
	 */
	protected function selfLocate() {
		$this->dir = defined('__DIR__') ? __DIR__ : dirname(__FILE__);
		$this->url = plugin_dir_url(__FILE__);
	}


	/**
	 * We require WP 3.4.2 and any requirements inherited by virtue of that, plus The Events Calendar 2.0.9 or later.
	 *
	 * Sets the $preflightCheck property to true if we are all good here.
	 */
	public function preflightChecks() {
		global $wp_version;
		$missingRequirements = false;

		if (version_compare($wp_version, '3.4.2') < 0) $missingRequirements = true;
		if (class_exists('TribeEvents') === false) $missingRequirements = true;
		if (version_compare(TribeEvents::VERSION, '2.0.9') < 0) $missingRequirements = true;
		if ($missingRequirements === false) $this->preflightCheck = true;
	}


	/**
	 * Checks that the prerequisites were met then hooks into The Events Calendar to display and save settings.
	 */
	public function start() {
		if ($this->preflightCheck === false) return;

		add_action('tribe_settings_do_tabs', array($this, 'registerSettingsTab'), 10);
		add_action('tribe_settings_after_save_housekeeper', array($this, 'updateSettings'));
	}


	/**
	 * Regsiter our settings tab.
	 */
	public function registerSettingsTab() {
		$tabConfig = $this->loadConfigArray('settingstab');
		new TribeSettingsTab('housekeeper', __('Housekeeping', 'events-housekeeper'), $tabConfig);
	}


	/**
	 * Builds an alert message to indicate if expired events are sitting in the database.
	 *
	 * @return string
	 */
	public function expiredItemsAlert() {
		$criteriaCount = (int) $this->expiredItemsCount(true, true);
		$totalCount = (int) $this->expiredItemsCount(false, true);

		$message = sprintf(_n('1 event currently meets the expiry criteria.',
			'%d events currently meet the expiry criteria.', $criteriaCount, 'events-housekeeper'), $criteriaCount);

		$message = "<strong> $message </strong>";
		return $message;
	}


	/**
	 * Counts the number of expired events in the database. Setting $meetingCriteria to true means only events falling
	 * fowl of the current expiry criteria will be counted; setting $allInstances to true means individual recurring
	 * instances will be counted - not just 'primary' events.
	 *
	 * @param bool $meetingCriteria
	 * @param bool $allInstances
	 * @return int
	 */
	protected function expiredItemsCount($meetingCriteria, $allInstances) {
		global $wpdb;
		$cutoff = $meetingCriteria ? $this->criteriaCutOffDate() : date('Y-m-d');
		$include = $allInstances ? '' : 'DISTINCT';

		$expiredEvents = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT($include post_id)
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_EventStartDate'
			AND meta_value < '%s';
		", $cutoff));

		return (int) $expiredEvents;
	}


	/**
	 * Save any setting changes made via the Housekeeping tab.
	 */
	public function updateSettings() {
		$settings = (array) get_option(self::SETTINGS);

		if (isset($settings['enableGarbageCollection']) and $settings['enableGarbageCollection'] === false)
			wp_clear_scheduled_hook(self::SCHEDULE);
		else $this->enableCollection();
	}


	/**
	 * (Re-)establish our scheduled task - it will normally run almost immediately after this, except in the case of
	 * busy/slow systems.
	 */
	protected function enableCollection() {
		// Clear then reschedule to ensure a collection happens quickly after a change of settings
		if (wp_next_scheduled(self::SCHEDULE) !== false)
			wp_clear_scheduled_hook(self::SCHEDULE);

		// Next cleanup 10secs from now then daily. The 10sec buffer prevents confusion - otherwise the Housekeeper
		// settings tab typically reloads part way through a clean-up and looks as if it has only done half a job
		wp_schedule_event(time() + 10, 'daily', self::SCHEDULE);
	}


	/**
	 * Carry out the actual collection, interpret the criteria specified in the settings tab and do the clean up.
	 */
	public function doCollection() {
		$this->expiryDate = $this->criteriaCutOffDate();
		$this->cleanUpAllBefore();
	}


	protected function criteriaCutOffDate() {
		$settings = (array) get_option(self::SETTINGS);
		$settings = array_merge(array('expiryCriteria' => 'allExpired'), $settings);

		switch ($settings['expiryCriteria']) {
			case 'oneWeek': $criteria = strtotime('-1 week'); break;
			case 'twoWeeks': $criteria = strtotime('-2 weeks'); break;
			case 'oneMonth': $criteria = strtotime('-1 month'); break;
			case 'sixMonths': $criteria = strtotime('-6 months'); break;
			case 'allExpired': default: $criteria = time(); break;
		}

		return date('Y-m-d', $criteria);
	}


	/**
	 * Cleans up expired events/event instances, with special handling for recurring events.
	 */
	protected function cleanUpAllBefore() {
		foreach ($this->listExpiredPostIDs() as $id)
			if ($this->isRecurringEvent($id)) $this->killRecurringInstances($id);
			else wp_delete_post($id, true);
	}


	/**
	 * Tries to determine if the event has recurring instances.
	 *
	 * This can be seen in the database where there is A) an _EventRecurrence meta entry and B) multiple _EventStartDate
	 * entries. For expediency, we test only for A.
	 *
	 * @param $id
	 * @return bool
	 */
	protected function isRecurringEvent($id) {
		global $wpdb;

		$recurrence = $wpdb->get_var($wpdb->prepare("
			SELECT meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_EventRecurrence'
		    AND LENGTH(meta_value) > 0
		    AND post_id = '%d'
		    LIMIT 1;
		", $id));

		if ($recurrence === null) return false;
		else return true;
	}


	/**
	 * Builds a list of (event) post IDs where the (initial) event has ended
	 * sometime before todays date.
	 *
	 * @return array
	 */
	protected function listExpiredPostIDs() {
		global $wpdb;

		$idList = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT post_id
			FROM {$wpdb->prefix}postmeta
			WHERE meta_key = '_EventStartDate'
			AND meta_value < '%s'
			LIMIT %d;
		", $this->expiryDate, $this->cleanupBatchSize));

		return (array) $idList;
	}


	/**
	 * Intelligently removes recurring instances of an event, where those instances have expired. The initial
	 * _EventEndDate is also adjusted to compensate for this.
	 *
	 * @param $id
	 */
	protected function killRecurringInstances($id) {
		global $wpdb;

		$eventInterval = $this->determineEventInterval($id);

		$wpdb->query($wpdb->prepare("
			DELETE FROM {$wpdb->postmeta}
			WHERE post_id = '%d'
			AND meta_key = '_EventStartDate'
			AND meta_value < '{$this->expiryDate}'
		", $id));

		$this->reestablishEventInterval($id, $eventInterval);
	}


	/**
	 * Calculates the length of time for the event (could be hours, all day or days).
	 *
	 * @param $id
	 * @return int
	 */
	protected function determineEventInterval($id) {
		$start = strtotime($this->findStartDate($id));
		$end = strtotime($this->findEndDate($id));
		return $end - $start;
	}


	/**
	 * Adjusts the _EventEndDate so that it is the correct interval ahead of the oldest surviving _EventStartDate.
	 *
	 * @param $id
	 * @param $interval
	 */
	protected function reestablishEventInterval($id, $interval) {
		global $wpdb;

		$currentStart = strtotime($this->findStartDate($id));
		$newEndTime = date('Y-m-d H:i:s', $currentStart + $interval);

		// End time in the past? Kill the entire post
		if ($newEndTime < date('Y-m-d H:i:s'))
			wp_delete_post($id, true);

		// Otherwise adjust the end date
		else $wpdb->query($wpdb->prepare("
			UPDATE {$wpdb->postmeta}
			SET meta_value = '%s'
			WHERE meta_key = '_EventEndDate'
			AND post_id = '%d';
		", $newEndTime, $id));
	}


	/**
	 * Returns the earliest _EventStartDate.
	 *
	 * @param $id
	 * @return mixed
	 */
	protected function findStartDate($id) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("
			SELECT MIN(meta_value)
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_EventStartDate'
			AND post_id = '%d';
		", $id));
	}


	/**
	 * Returns the _EventEndDate.
	 *
	 * @param $id
	 * @return mixed
	 */
	protected function findEndDate($id) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("
			SELECT MIN(meta_value)
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_EventEndDate'
			AND post_id = '%d';
		", $id));
	}


	/**
	 * Loads the specified config array, allowing it to be pulled directly into the scope of the calling method.
	 *
	 * @param $config
	 * @return array|mixed
	 */
	protected function loadConfigArray($config) {
		$path = $this->dir."/config/$config.php";
		if (file_exists($path)) return include $path;
		return array();
	}


	/**
	 * Provides access to the current ECHousekeeper instance, if created.
	 *
	 * @static
	 * @return mixed (bool | ECHousekeeper)
	 */
	public static function instance() {
		if (self::$object !== null) return self::$object;
		else return false;
	}
}


// Start up the housekeeper!
ECHousekeeper::initialize();