<?php

/**
 * Set boolean value based on whether other values match regexes.
 *
 * @author Yørn Åne de Jong <yorinad at stud.ntnu.no>
 */
class sspmod_role_Auth_Process_Role extends SimpleSAML_Auth_ProcessingFilter {

	/**
	 * The rules to evaluate. This variable is a mixed one-to-three-dimensional string array in the following configuration:
	 * 1st index: role is the name of the role which gets added if the ACL matches.
	 * 2nd index: is the name of an attribute whose value should match an enclosing expectedValue.
	 * 3rd index: index number, key value not used
	 * the value is always an expected value. depending on the depth of the array and $this->regex,
	 * the value is a literal string, regular expression or name of another, earlier defined, role.
	 * See #Rules in README.md for more information about this variable.
	 * 
	 * @var (string|string[]|(string|string[])[string])[string]
	 */
	protected $roleRules = array();

	/**
	 * Flag to turn regular expression pattern matching on or off.
	 *
	 * @var bool
	 */
	protected $regex = FALSE;

	/**
	 * Attribute name for role attribute.
	 * This is the attribute that is created and populated with the appropriate roles.
	 *
	 * @var string[]
	 */
	protected $roleAttribute = 'roles';


	/**
	 * Initialize this filter.
	 * Validate configuration parameters.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');
		assert('array_key_exists("roleRules", $config)');
		assert('is_array($config["roleRules"])');

		$this->roleRules = $config['roleRules'];
		$this->regex = isset($config['regex']) && $config['regex'];
		if (isset($config['roleAttribute']))
			$this->roleAttribute = $config['roleAttribute'];
	}


	/**
	 * Evaluate the rules on an attribute set, 
	 * and modify said attribute set by adding a role attribute.
	 *
	 * @param array &$request  The current request
	 */
	public function process(&$request) {
		assert('is_array($request)');
		assert('array_key_exists("Attributes", $request)');
		assert('is_array($request["Attributes"])');

		$attributes =& $request['Attributes'];

		if (!isset($attributes[$this->roleAttribute]))
			$attributes[$this->roleAttribute] = array();

		foreach($this->roleRules as $role => $rules) {
			if (in_array($role, $attributes[$this->roleAttribute]))
				continue; // Role already assigned
			if (!is_array($rules))
				$rules = array($rules);
			foreach($rules as $attributeName => $acceptedValues) /*1*/ {
				if (!is_array($acceptedValues))
					$acceptedValues = array($acceptedValues);
				if (is_integer($attributeName)) // Unnamed key, PHP converts this to an integer
					$attributeName = $this->roleAttribute;
				if (!isset($attributes[$attributeName]))
					continue; // The attribute doesn't exist; comparison is useless
				foreach($acceptedValues as $acceptedValue) /*2*/ {
					if ($this->regex) {
						foreach($attributes[$attributeName] as $actualValue) /*3*/ {
							if (preg_match($acceptedValue, $actualValue)) {
								$attributes[$this->roleAttribute][] = $role;
								break 3;
							}
						}
					} elseif (in_array($acceptedValue, $attributes[$attributeName])) {
						$attributes[$this->roleAttribute][] = $role;
						break 2;
					}
				}
				
			}
		}
	}
}

