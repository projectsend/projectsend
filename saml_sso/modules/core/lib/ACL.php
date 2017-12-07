<?php

/**
 * Generic library for access control lists.
 *
 * @package SimpleSAMLphp
 */
class sspmod_core_ACL {

	/**
	 * The access control list, as an array.
	 *
	 * @var array
	 */
	private $acl;


	/**
	 * Initializer for this access control list.
	 *
	 * @param array|string $acl  The access control list.
	 */
	public function __construct($acl) {
		assert('is_string($acl) || is_array($acl)');

		if (is_string($acl)) {
			$acl = self::getById($acl);
		}

		foreach ($acl as $rule) {
			if (!is_array($rule)) {
				throw new SimpleSAML_Error_Exception('Invalid rule in access control list: ' . var_export($rule, TRUE));
			}
			if (count($rule) === 0) {
				throw new SimpleSAML_Error_Exception('Empty rule in access control list.');
			}

			$action = array_shift($rule);
			if ($action !== 'allow' && $action !== 'deny') {
				throw new SimpleSAML_Error_Exception('Invalid action in rule in access control list: ' . var_export($action, TRUE));
			}

		}

		$this->acl = $acl;
	}


	/**
	 * Retrieve an access control list with the given id.
	 *
	 * @param string $id  The id of the access control list.
	 * @return array  The access control list array.
	 */
	private static function getById($id) {
		assert('is_string($id)');

		$config = SimpleSAML_Configuration::getOptionalConfig('acl.php');
		if (!$config->hasValue($id)) {
			throw new SimpleSAML_Error_Exception('No ACL with id ' . var_export($id, TRUE) . ' in config/acl.php.');
		}

		return $config->getArray($id);
	}


	/**
	 * Match the attributes against the access control list.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @return boolean  TRUE if the user is allowed to access the resource, FALSE if not.
	 */
	public function allows(array $attributes) {

		foreach ($this->acl as $rule) {
			$action = array_shift($rule);

			if (!self::match($attributes, $rule)) {
				continue;
			}

			if ($action === 'allow') {
				return TRUE;
			} else {
				return FALSE;
			}
		}
	}


	/**
	 * Match the attributes against the given rule.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @param array $rule  The rule we should check.
	 * @return boolean  TRUE if the rule matches, FALSE if not.
	 */
	private static function match(array $attributes, array $rule) {

		$op = array_shift($rule);
		if ($op === NULL) {
			// An empty rule always matches
			return TRUE;
		}

		switch($op) {
		case 'and':
			return self::opAnd($attributes, $rule);
		case 'equals':
			return self::opEquals($attributes, $rule);
		case 'equals-preg':
			return self::opEqualsPreg($attributes, $rule);
		case 'has':
			return self::opHas($attributes, $rule);
		case 'has-preg':
			return self::opHasPreg($attributes, $rule);
		case 'not':
			return !self::match($attributes, $rule);
		case 'or':
			return self::opOr($attributes, $rule);
		default:
			throw new SimpleSAML_Error_Exception('Invalid ACL operation: ' . var_export($op, TRUE));
		}
	}


	/**
	 * 'and' match operator.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @param array $rule  The rule we should check.
	 * @return boolean  TRUE if the rule matches, FALSE if not.
	 */
	private static function opAnd($attributes, $rule) {

		foreach ($rule as $subRule) {
			if (!self::match($attributes, $subRule)) {
				return FALSE;
			}
		}

		// All matches
		return TRUE;
	}


	/**
	 * 'equals' match operator.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @param array $rule  The rule we should check.
	 * @return boolean  TRUE if the rule matches, FALSE if not.
	 */
	private static function opEquals($attributes, $rule) {

		$attributeName = array_shift($rule);

		if (!array_key_exists($attributeName, $attributes)) {
			$attributeValues = array();
		} else {
			$attributeValues = $attributes[$attributeName];
		}

		foreach ($rule as $value) {
			$found = FALSE;
			foreach ($attributeValues as $i => $v) {
				if ($value !== $v) {
					continue;
				}
				unset($attributeValues[$i]);
				$found = TRUE;
				break;
			}
			if (!$found) {
				return FALSE;
			}
		}
		if (!empty($attributeValues)) {
			/* One of the attribute values didn't match. */
			return FALSE;
		}

		/* All the values in the attribute matched one in the rule. */
		return TRUE;
	}


	/**
	 * 'equals-preg' match operator.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @param array $rule  The rule we should check.
	 * @return boolean  TRUE if the rule matches, FALSE if not.
	 */
	private static function opEqualsPreg($attributes, $rule) {

		$attributeName = array_shift($rule);

		if (!array_key_exists($attributeName, $attributes)) {
			$attributeValues = array();
		} else {
			$attributeValues = $attributes[$attributeName];
		}

		foreach ($rule as $pattern) {
			$found = FALSE;
			foreach ($attributeValues as $i => $v) {
				if (!preg_match($pattern, $v)) {
					continue;
				}
				unset($attributeValues[$i]);
				$found = TRUE;
				break;
			}
			if (!$found) {
				return FALSE;
			}
		}

		if (!empty($attributeValues)) {
			/* One of the attribute values didn't match. */
			return FALSE;
		}

		/* All the values in the attribute matched one in the rule. */
		return TRUE;
	}


	/**
	 * 'has' match operator.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @param array $rule  The rule we should check.
	 * @return boolean  TRUE if the rule matches, FALSE if not.
	 */
	private static function opHas($attributes, $rule) {

		$attributeName = array_shift($rule);

		if (!array_key_exists($attributeName, $attributes)) {
			$attributeValues = array();
		} else {
			$attributeValues = $attributes[$attributeName];
		}

		foreach ($rule as $value) {
			if (!in_array($value, $attributeValues, TRUE)) {
				return FALSE;
			}
		}

		/* Found all values in the rule in the attribute. */
		return TRUE;
	}


	/**
	 * 'has-preg' match operator.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @param array $rule  The rule we should check.
	 * @return boolean  TRUE if the rule matches, FALSE if not.
	 */
	private static function opHasPreg($attributes, $rule) {

		$attributeName = array_shift($rule);

		if (!array_key_exists($attributeName, $attributes)) {
			$attributeValues = array();
		} else {
			$attributeValues = $attributes[$attributeName];
		}

		foreach ($rule as $pattern) {
			$matches = preg_grep($pattern, $attributeValues);
			if (count($matches) === 0) {
				return FALSE;
			}
		}

		/* Found all values in the rule in the attribute. */
		return TRUE;
	}


	/**
	 * 'or' match operator.
	 *
	 * @param array $attributes  The attributes of an user.
	 * @param array $rule  The rule we should check.
	 * @return boolean  TRUE if the rule matches, FALSE if not.
	 */
	private static function opOr($attributes, $rule) {

		foreach ($rule as $subRule) {
			if (self::match($attributes, $subRule)) {
				return TRUE;
			}
		}

		/* None matches. */
		return FALSE;
	}

}
