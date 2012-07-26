<?php
require_once 'newrelic.php';

class Notify_Email {
	public $name;
	public $from_email;
	public $to_email;

	public function __construct(array $config) {
		assert( isset($config['from_email']) );
		assert( isset($config['to_email']) );
		$this->from_email = $config['from_email'];
		$this->to_email = $config['to_email'];
	}

	public function HandleAlerts(array $alerts) {
		$message = sprintf("NewRelic Alerts as of %s\n\n", date('Y-m-d H:i:s'));
		foreach( $alerts AS $k => $v ) {
			$message .= sprintf("\t%s - %s\n", $k, $v);
		}
		$message .= "\n";
		$message .= "Summary:\n";

		$alert_types = array();
		foreach( $alerts AS $value ) {
			if( ! isset($alert_types[$value]) ) {
				$alert_types[$value] = 0;
			}
			$alert_types[$value]++;
		}

		$subject = array();
		foreach( $alert_types AS $alert_name => $alert_count ) {
			$subject[] = sprintf("%d %s", $alert_count, $alert_name);
		}
		$subject = sprintf("NewRelic : %s", implode(',', $subject));
		$message .= implode("\n", $subject);

		$headers = 'From: '.$this->from_email;
		mail($this->to_email, $subject, $message, $headers);
	}
}

class AlertCondition {
	public $app;
	public $name;
	public $metric;
	public $field;
	public $warn;
	public $critical;
	public $time;

	public function __construct($name, array $config) {
		$this->name = $name;

		$this->app = $config['app'];
		$this->metric = $config['metric'];
		$this->field = $config['field'];
		$this->warn = $config['warn'];
		$this->critical = $config['critical'];
		$this->time = $config['time'];
	}

	function Analyze(array $data, DateTime $now) {
		$trigger_time = clone($now);
		$trigger_time->modify(sprintf("-%d seconds", $this->time));

		$triggered = FALSE;
		$status = NULL;
		foreach( $data AS $date_range => $value ) {
			$date_range = explode('|', $date_range);
			$begin = new DateTime($date_range[0]);
			$end = new DateTime($date_range[0]);

			if( $triggered ) {
				if( $value < $this->warn ) {
					return NULL;
				}

				if( $value > $this->critical ) {
					$status = 'critical';
				}
				else if( $value > $this->warn ) {
					$status = 'warn';
				}
			}
			else {
				if( $begin >= $trigger_time ) {
					$triggered = TRUE;
				}
			}
		}
		return $status;
	}
}

$alert_conditions = array();
$alerts = array();
$notifiers = array();
$config = parse_ini_file(__DIR__.'/alerts.ini', TRUE);
$newrelic = NULL;

foreach( $config AS $section_name => $section ) {
	if( $section_name == 'newrelic' ) {
		$newrelic = $section;
		continue;
	}

	if( ! preg_match('@^(?P<type>[a-z]+):(?P<name>.*)$@', $section_name, $matches) ) {
		printf("Error: Unknown section '%s'\n", $section_name);
		continue;
	}

	if( $matches['type'] == 'notify' ) {
		$class_name = 'Notify_' . $matches['name'];
		if( ! class_exists($class_name) ) {
			printf("Error: unknown notification type '%s'\n", $matches['type']);
			continue;
		}
		$class = new $class_name($section);
		$notifiers[] = $class;
	}
	else {
		$alert_conditions[$matches['name']] = new AlertCondition($matches['name'], $section);
	}
}

if( $newrelic == NULL ) {
	printf("Error: no '[newrelic]' configuration section found!\n");
	exit;
}
$api = new NewRelic($newrelic['api_key'], $newrelic['account_id']);

// Retrieve metrics
foreach( $alert_conditions AS $condition_name => $condition ) {
	$end_time = new DateTime();
	$begin_time = clone($end_time);
	$begin_time->modify(sprintf("-%d seconds", $condition->time * 10));
	$data = NewRelic_Metrics2Array($api->getData($condition->app, $begin_time, $end_time, $condition->metric, $condition->field));
	$alert = $condition->Analyze($data, $end_time);
	if( $alert ) {
		$alerts[$condition_name] = $alert;
	}
}

if( count($alerts) ) {
	$has_critical = FALSE;
	foreach( $alerts AS $condition_name => $alert ) {
		if( $alert == 'critical' ) $has_critical = TRUE;
		printf("[%s] = %s\n", $condition_name, $alert);
	}

	if( $has_critical ) {
		foreach( $notifiers AS $notify ) {
			$notify->HandleAlerts($alerts);
		}
	}
}
else {
	echo 'OK';
}