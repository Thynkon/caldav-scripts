<?php

use Sabre\VObject;

define('CWD', realpath(dirname(__FILE__) . '/../'));
require_once(CWD . '/config/config.php');

include 'autoload.php';
require_once('SimpleCalDAVClient.php');

function moveTasks($client = null, $filter_xml = null, $days = null) {
	if ($client === null || $filter_xml === null || $days === null) {
		echo "Missing options!!!\n";
		exit;
	}

	$todos = $client->getCustomReport($filter_xml);
	foreach ($todos as $todo) {
		$data = null;
		$href = null;
		$etag = null;
		$cdo = null;

		$data = $todo->getData();
		$href = $todo->getHref();
		$etag = $todo->getEtag();

		$vcalendar = VObject\Reader::read($data);

		$due_date = $vcalendar->VTODO->DUE;
		$date = new DateTime($due_date);
		$date->modify(sprintf('+%d day', $days));
		$vcalendar->VTODO->DUE = $date;

		$last_mod = new DateTime("now", new \DateTimeZone("UTC"));
		$vcalendar->VTODO->{'LAST-MODIFIED'} = $last_mod;

		$calendar_data = $vcalendar->serialize();
		try {
			$cdo = $client->change($href, $calendar_data, $etag);
		} catch (Exception $e) {
			echo $e->__toString();
			return false;
		}
	}

	return true;
}

function parseOptions(&$url, &$user, &$password, &$calendar_id, &$days) {
	$shortopts = "";
	//$shortopts .= "r"; // These options do not accept values
	$shortopts .= "p:u:c:U:";  // Required value
	$shortopts .= "d::"; // Optional value

	$longopts  = array(
	    //"required:",     // Required value
	    //"days::",    // Optional value
	    //"option",        // No value
	    //"opt",           // No value
	);

	$options = getopt($shortopts, $longopts);
	if ($options === false) {
		return false;
	}

	if (array_key_exists("p", $options)) {
	    $password = $options['p'];
	}
	if (array_key_exists("u", $options)) {
	    $user = $options['u'];
	}
	if (array_key_exists("c", $options)) {
	    $calendar_id = $options['c'];
	}
	if (array_key_exists("U", $options)) {
	    $url = $options['U'];
	}

	if (array_key_exists("d", $options)) {
	    $days = $options['d'];
	}

	return true;
}

function main() {
	$url = null;
	$user = null;
	$password = null;
	$calendar_id = null;
	$days = 1;

	if (!parseOptions($url, $user, $password, $calendar_id, $days)) {
		echo "Failed to parse options!!!\n";
		return false;
	}

	$client = new SimpleCalDAVClient();

	// Custom calendar object filter
	$filter = new CalDAVFilter("VTODO");
	$filter->mustInclude("SUMMARY"); // Should include a SUMMARY
	$filter->mustInclude("STATUS"); // Should include a STATUS
	$filter->mustIncludeMatchSubstr("STATUS", "COMPLETED", TRUE); // STATUS SHOULD NOT CONTAIN 'COMPLETED'
	$filter->mustInclude("DUE"); // Should include a SUMMARY
	$filter_xml = $filter->toXML();

	try {
		$client->connect($url, $user, $password);

		$arrayOfCalendars = $client->findCalendars(); // Returns an array of all accessible calendars on the server.

		// if calendar name was passed from command line
		if ($calendar_id !== null) {
			$client->setCalendar($arrayOfCalendars[$calendar_id]);
			if (!moveTasks($client, $filter_xml, $days)) {
				echo "Failed to move tasks!!!\n";
				return false;
			}
		} else {
			foreach ($arrayOfCalendars as $key => $value) {
				$client->setCalendar($value);
				if (!moveTasks($client, $filter_xml, $days)) {
					echo "Failed to move tasks!!!\n";
					return false;
				}
			}
		}
	} catch (Exception $e) {
		echo $e->__toString();
		return false;
	}

	return true;
}

if (!main()) {
	exit(-1);
}

exit(0);

?>
