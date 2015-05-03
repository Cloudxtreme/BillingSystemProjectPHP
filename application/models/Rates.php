<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Rates model class to pull data from database for plan collection
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class RatesModel extends TabledateModel {

	protected $showprefix;

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->rates;
		parent::__construct($params);
		$this->search_key = "key";
		if (isset($params['showprefix'])) {
			$this->showprefix = $params['showprefix'];
			if ($this->size > 50 && $this->showprefix) {
				$this->size = 50;
			}
		} else {
			$this->showprefix = false;
		}
	}

	/**
	 * method to convert plans ref into their name
	 * triggered before present the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $entity
	 * 
	 * @return type
	 * @todo move to model
	 */
	public function getItem($id) {

		$entity = parent::getItem($id);

		if (isset($entity['rates'])) {
			$raw_data = $entity->getRawData();
			foreach ($raw_data['rates'] as &$rate) {
				if (isset($rate['plans'])) {
					foreach ($rate['plans'] as &$plan) {
						$data = $this->collection->getRef($plan);
						if ($data instanceof Mongodloid_Entity) {
							$plan = $data->get('name');
						}
					}
				}
			}
			$entity->setRawData($raw_data);
		}

		return $entity;
	}

	/**
	 * method to convert plans names into their refs
	 * triggered before save the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $data
	 * 
	 * @return void
	 * @todo move to model
	 */
	public function update($data) {
		if (isset($data['rates'])) {
			$plansColl = Billrun_Factory::db()->plansCollection();
			$currentDate = new MongoDate();
			$rates = $data['rates'];
			//convert plans
			foreach ($rates as &$rate) {
				if (isset($rate['plans'])) {
					$sourcePlans = (array) $rate['plans']; // this is array of strings (retreive from client)
					$newRefPlans = array(); // this will be the new array of DBRefs
					unset($rate['plans']);
					foreach ($sourcePlans as &$plan) {
						if (MongoDBRef::isRef($plan)) {
							$newRefPlans[] = $plan;
						} else {
							$planEntity = $plansColl->query('name', $plan)
											->lessEq('from', $currentDate)
											->greaterEq('to', $currentDate)
											->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->current();
							$newRefPlans[] = $planEntity->createRef($plansColl);
						}
					}
					$rate['plans'] = $newRefPlans;
				}
			}
			$data['rates'] = $rates;
		}

		return parent::update($data);
	}

	public function getTableColumns() {
		if ($this->showprefix) {
			$columns = array(
				'key' => 'Key',
				'prefix' => 'Prefix',
				'from' => 'From',
				'to' => 'To',
				'_id' => 'Id',
			);
		} else {
			$columns = array(
				'key' => 'Key',
				't' => 'Type',
				'tprice' => 'Price',
				'tduration' => 'Interval',
				'taccess' => 'Access',
				'from' => 'From',
				'to' => 'To',
				'_id' => 'Id',
			);
		}
		if (!empty($this->extra_columns)) {
			$extra_columns = array_intersect_key($this->getExtraColumns(), array_fill_keys($this->extra_columns, ""));
			$columns = array_merge($columns, $extra_columns);
		}
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'key' => 'Key',
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function getFilterFields() {
		$filter_fields = array(
//			'usage' => array(
//				'key' => 'rates.$',
//				'db_key' => 'rates.$',
//				'input_type' => 'multiselect',
//				'comparison' => 'exists',
//				'display' => 'Usage',
//				'values' => array('All', 'Call', 'SMS', 'Data'),
//				'default' => array('All'),
//			),
			'key' => array(
				'key' => 'key',
				'db_key' => 'key',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Key',
				'default' => '',
				'case_type' => 'upper',
			),
			'prefix' => array(
				'key' => 'prefix',
				'db_key' => 'params.prefix',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Prefix',
				'default' => '',
			),
			'showprefix' => array(
				'key' => 'showprefix',
				'db_key' => 'nofilter',
				'input_type' => 'boolean',
				'display' => 'Show prefix',
				'default' => $this->showprefix ? 'on' : '',
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
//			array(
//				'usage' => array(
//					'width' => 2,
//				),
//			),
			array(
				'key' => array(
					'width' => 2,
				),
			),
			array(
				'prefix' => array(
					'width' => 2,
				),
			),
		);
		$post_filter_field = array(
			array(
				'showprefix' => array(
					'width' => 2,
				),
			),
		);
		return array_merge($filter_field_order, parent::getFilterFieldsOrder(), $post_filter_field);
	}

	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData($filter_query = array(), $fields = false) {
		$cursor = $this->getRates($filter_query);
		$this->_count = $cursor->count();
		$resource = $cursor->sort($this->sort)->skip($this->offset())->limit($this->size);
		$ret = array();
		foreach ($resource as $item) {
			if ($fields) {
				foreach ($fields as $field) {
					$row[$field] = $item->get($field);
				}
				if (isset($row['rates'])) {
					// convert plan ref to plan name
					foreach ($row['rates'] as &$rate) {
						if (isset($rate['plans'])) {
							$plans = array();
							foreach ($rate['plans'] as $plan) {
								$plan_id = $plan['$id'];
								$plans[] = Billrun_Factory::plan(array('id' => $plan_id))->getName();
							}
							$rate['plans'] = $plans;
						}
					}
				}
				$ret[] = $row;
			} else if ($item->get('rates') && !$this->showprefix) {
				foreach ($item->get('rates') as $key => $rate) {
					if (is_array($rate)) {
						$added_columns = array(
							't' => $key,
							'tprice' => $rate['rate'][0]['price'],
							'taccess' => isset($rate['access']) ? $rate['access'] : 0,
						);
						if (strpos($key, 'call') !== FALSE) {
							$added_columns['tduration'] = Billrun_Util::durationFormat($rate['rate'][0]['interval']);
						} else if ($key == 'data') {
							$added_columns['tduration'] = Billrun_Util::byteFormat($rate['rate'][0]['interval'], '', 0, true);
						} else {
							$added_columns['tduration'] = $rate['rate'][0]['interval'];
						}
						$ret[] = new Mongodloid_Entity(array_merge($item->getRawData(), $added_columns, $rate));
					}
				}
			} else if ($this->showprefix && (isset($filter_query['$and'][0]['key']) || isset($filter_query['$and'][0]['params.prefix']))) {
				foreach ($item->get('params.prefix') as $prefix) {
					$item_raw_data = $item->getRawData();
					unset($item_raw_data['params']['prefix']); // to prevent high memory usage
					$entity = new Mongodloid_Entity(array_merge($item_raw_data, array('prefix' => $prefix)));
					$ret[] = $entity;
				}
			} else {
				$ret[] = $item;
			}
		}
		return $ret;
	}

	public function getRates($filter_query) {
		return $this->collection->query($filter_query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
	}

	public function getFutureRateKeys($by_keys = array()) {
		$base_match = array(
			'$match' => array(
				'from' => array(
					'$gt' => new MongoDate(),
				),
			),
		);
		if ($by_keys) {
			$base_match['$match']['key']['$in'] = $by_keys;
		}

		$group = array(
			'$group' => array(
				'_id' => '$key',
			),
		);
		$project = array(
			'$project' => array(
				'_id' => 0,
				'key' => '$_id',
			),
		);
		$future_rates = $this->collection->aggregate($base_match, $group, $project);
		$future_keys = array();
		foreach ($future_rates as $rate) {
			$future_keys[] = $rate['key'];
		}
		return $future_keys;
	}

	public function getActiveRates($by_keys = array()) {
		$base_match = array(
			'$match' => array(
				'from' => array(
					'$lt' => new MongoDate(),
				),
				'to' => array(
					'$gt' => new MongoDate(),
				),
			),
		);
		if ($by_keys) {
			$base_match['$match']['key']['$in'] = $by_keys;
		}

		$group = array(
			'$group' => array(
				'_id' => '$key',
				'count' => array(
					'$sum' => 1,
				),
				'oid' => array(
					'$first' => '$_id',
				)
			),
		);
		$project = array(
			'$project' => array(
				'_id' => 0,
				'count' => 1,
				'oid' => 1,
			),
		);
		$having = array(
			'$match' => array(
				'count' => 1,
			),
		);
		$active_rates = $this->collection->aggregate($base_match, $group, $project, $having);
		if (!$active_rates) {
			return $active_rates;
		}
		foreach ($active_rates as $rate) {
			$active_oids[] = $rate['oid'];
		}
		$query = array(
			'_id' => array(
				'$in' => $active_oids,
			),
		);
		$rates = $this->collection->query($query);
		return $rates;
	}

	/**
	 * 
	 * @param string $usage_type
	 * @return string
	 */
	public function getUnit($usage_type) {
		switch ($usage_type) {
			case 'call':
			case 'incoming_call':
				$unit = 'seconds';
				break;
			case 'data':
				$unit = 'bytes';
				break;
			case 'sms':
			case 'mms':
				$unit = 'counter';
				break;
			default:
				$unit = 'seconds';
				break;
		}
		return $unit;
	}

	/**
	 * 
	 * @param array $rules
	 */
	public function getRateArrayByRules($rules) {
		ksort($rules);
		$rate_arr = array();
		$rule_end = 0;
		foreach ($rules as $rule) {
			$rate['price'] = floatval($rule['price']);
			$rate['interval'] = intval($rule['interval']);
			$rate['to'] = $rule_end = intval($rule['times'] == 0 ? pow(2, 31) - 1 : $rule_end + $rule['times'] * $rule['interval']);
			$rate_arr[] = $rate;
		}
		return $rate_arr;
	}

	/**
	 * Get rules array by db rate
	 * @param Mongodloid_Entity $rate
	 * @return array
	 */
	public function getRulesByRate($rate, $showprefix = false) {
		$first_rule = true;
		$rule['key'] = $rate['key'];
		$rule['from_date'] = date('Y-m-d H:i:s', $rate['from']->sec);
		foreach ($rate['rates'] as $usage_type => $usage_type_rate) {
			$rule['usage_type'] = $usage_type;
			$rule['category'] = $usage_type_rate['category'];
			$rule['access_price'] = isset($usage_type_rate['access']) ? $usage_type_rate['access'] : 0;
			$rule_counter = 1;
			foreach ($usage_type_rate['rate'] as $rate_rule) {
				$rule['rule'] = $rule_counter;
				$rule['interval'] = $rate_rule['interval'];
				$rule['price'] = $rate_rule['price'];
				$rule['times'] = intval($rate_rule['to'] / $rate_rule['interval']);
				$rule_counter++;
				if ($showprefix) {
					if ($first_rule) {
						$rule['prefix'] = '"' . implode(',', $rate['params']['prefix']) . '"';
						$first_rule = false;
					} else {
						$rule['prefix'] = '';
					}
				}
				$rules[] = $rule;
			}
		}
		return $rules;
		//sort by header?
	}

	/**
	 * 
	 * @return aray
	 */
	public function getPricesListFileHeader($showprefix = false) {
		if ($showprefix) {
			return array('key', 'usage_type', 'category', 'rule', 'access_price', 'interval', 'price', 'times', 'from_date', 'prefix');
		} else {
			return array('key', 'usage_type', 'category', 'rule', 'access_price', 'interval', 'price', 'times', 'from_date');
		}
	}

	public function getRateByVLR($vlr) {
		$prefixes = Billrun_Util::getPrefixes($vlr);
		$match = array('$match' => array(
				'params.serving_networks' => array(
					'$exists' => true,
				),
				'kt_prefixes' => array(
					'$in' => $prefixes,
				),
			),);
		$unwind = array(
			'$unwind' => '$kt_prefixes',
		);
		$sort = array(
			'$sort' => array(
				'kt_prefixes' => -1,
			),
		);
		$limit = array(
			'$limit' => 1,
		);
		$rate = $this->collection->aggregate(array($match, $unwind, $sort, $limit));
		if ($rate) {
			return $rate[0];
		} else {
			return NULL;
		}
	}
	
	/**
	 * method to fetch plan reference by plan name
	 * 
	 * @param string $plan
	 * @param MongoDate $currentDate the affective date
	 * 
	 * @return MongoDBRef
	 */
	public function getPlan($plan, $currentDate = null) {
		if (is_null($currentDate)) {
			$currentDate = new MongoDate();
		}
		$plansColl = Billrun_Factory::db()->plansCollection();
		$planEntity = $plansColl->query('name', $plan)
						->lessEq('from', $currentDate)
						->greaterEq('to', $currentDate)
						->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->current();
		return $planEntity->createRef($plansColl);
	}

}
