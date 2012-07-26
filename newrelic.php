<?php

class NewRelic_Error extends Exception {}

function NewRelic_Date(DateTime $date) {
	return $date->format('Y-m-d') . 'T' . $date->format('H:i:s') . 'Z';
}

function NewRelic_Metrics2Array(SimpleXMLElement $result) {
	$return = array();
	foreach( $result->metric AS $metric ) {
		$begin = (string)$metric['begin'];
		$end = (string)$metric['end'];
		$field = (string)$metric->field;
		$return[$begin . '|' . $end] = $field;
	}	
	return $return;
}

class NewRelic {
	private $apiKey;
	private $accountID;

	const RPM_URL = 'https://rpm.newrelic.com';
	const API_URL = 'https://api.newrelic.com';

	public function __construct($apiKey, $accountID) {
		$this->apiKey = $apiKey;
		$this->accountID = (int) $accountID;
	}

	public function getApplications() {
		return $this->apiCall(self::RPM_URL.'/accounts/'.$this->accountID.'/applications.xml');
	}

	public function getSummary($appID) {
		return $this->apiCall(self::RPM_URL.'/accounts/'.$this->accountID.'/applications/'.$appID.'/threshold_values.xml');
	}

	public function listMetrics($appID) {
		return $this->apiCall(self::API_URL.'/api/v1/applications/'.$appID.'/metrics.xml');
	}

	public function getData($appID, DateTime $begin, DateTime $end, $metrics, $field, $summary = FALSE) {
		$data = array(
			'metrics' => $metrics,
			'begin' => NewRelic_Date($begin),
			'end' => NewRelic_Date($end),
			);
		if( $field ) {
			$data['field'] = $field;
		}
		if( $summary ) {
			$data['summary'] = 1;
		}
		return $this->apiCall(self::API_URL.'/api/v1/accounts/'.$this->accountID.'/applications/'.$appID.'/data.xml', 'get', $data);
	}

	public function sendDeployment($appID, $user, $description, $changelog, $revision) {

		$data = array(
			'deployment[application_id]' => $appID,
			'deployment[description]' => $description,
			'deployment[changelog]' => $changelog,
			'deployment[user]' => $user,
			'deployment[revision]' => $revision,
		);
		return $this->apiCall('deployments.xml', 'post', $data);
	}

	private function apiCall($url, $method = 'get', array $data = array()) {
		$method = strtolower($method);
		if ($method != 'get' && $method != 'post') {
			throw new IllegalArgumentException();
		}

		$ch = curl_init();
		if ($method == 'post') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		else {
			if( count($data) ) {
				$url .= '?' . http_build_query($data);
			}
		}

		// set authentication header
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('x-api-key: '.$this->apiKey));
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$output = curl_exec($ch);
		curl_close($ch);

		if( empty($output) ) {
			throw new NewRelic_Error("Empty response");			
		}

		return simplexml_load_string($output);
	}	
}
