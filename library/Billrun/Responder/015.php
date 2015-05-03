<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing 013 Responder file processor
 *
 * @package  Billing
 * @since    0.5
 * TODO ! ACTUALLY IMPLEMENT!! THERESCURRENTLY NO SPEC'S FOR THIS ! TODO
 */
class Billrun_Responder_015 extends Billrun_Responder_Base_Ilds {

	public function __construct(array $params = array()) {
		parent::__construct($params);
		self::$type = '015';

		$this->data_structure = array(
			'record_type' => '%1s',
			'call_type' => '%2s',
			'caller_phone_no' => '%-10s',
			'called_no' => '%-28s',
			'call_start_dt' => '%14s',
			'chrgbl_call_dur' => '%06s',
			'rate_code' => '%1s',
			'call_charge_sign' => '%1s',
			'call_charge' => '%11s',
			'charge_code' => '%2s',
			'record_status' => '%02s',
		);

		$this->header_structure = array(
			'record_type' => '%1s',
			'file_type' => '%15s',
			'receiving_company_id' => '%10s',
			'sending_company_id' => '%10s',
			'sequence_no' => '%06s',
			'file_creation_date' => '%14s',
			'file_received_date' => '%14s',
			//'file_status' => '%02s',
		);

		$this->trailer_structure = array(
			'record_type' => '%1s',
			'file_type' => '%-15s',
			'receiving_company_id' => '%-10s',
			'sending_company_id' => '%-10s',
			'sequence_no' => '%6s',
			'file_creation_date' => '%14s',
			'total_phone_number' => '%15s',
			'total_charge_sign' => '%1s',
			'total_charge' => '%15s',
			'total_rec_no' => '%6s',
		);
	}

	protected function updateHeader($line, $logLine) {
		$line = parent::updateHeader($line, $logLine);
		$line = $this->switchNamesInLine("HLT", "NTV", $line);
		$line.="00"; //TODO add problem detection.
		return $line;
	}

	protected function updateLine($dbLine, $logLine) {
		$dbLine['record_status'] = '00';
		return parent::updateLine($dbLine, $logLine);
	}

	protected function updateTrailer($logLine) {
		$line = parent::updateTrailer($logLine);
		$line.= sprintf("%06s", $this->linesErrors);
		return $line;
	}

	protected function processLineErrors($dbLine) {
		if (!isset($dbLine['billrun']) || !$dbLine['billrun']) {
			$dbLine['record_status'] = '02';
		}
		return $dbLine;
	}

	protected function getResponseFilename($receivedFilename, $logLine) {
		return preg_replace("/_CDR_/i", "_CDR_R_", $receivedFilename);
	}

}
