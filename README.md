# Role module for simpleSamlPhp
In an organisation where there are many internal Service Providers, it may become a hassle to keep track of the access control list for each one. Typically, each SP will define roles based on the existence or value of one or more attributes. Keeping configurations of all SPs up to date with policy proves difficult.

This module allows you to aggregate attributes into simple roles which can be uniform between all SPs. By using simple discretionary access control (available in many frameworks and easy to write yourself), these roles can be used to allow users access to either part of or the entire application. 

## Use case example
A typical example would be a SP which can deny access to a user, allow user access or allow admin access. This module can then be configured to set an attribute depending on the group memberships of a user, for example *System Administrator* has *admin* rights and *Finance* has *access* rights. It is possible to make *admin* infer *access* so no double rules need to be defined for different roles. By only implementing code like ```if ($roles->admin)``` and ```if ($roles->access)``` in your SP, code readability improves and you get central access control through the SPs configuration in the IdP.

# Installation
Copy/move the directory ```role``` to the ```modules``` folder in your simpleSamlPhp installation. Where to configure the module is up to you, but I would recommend you to configure it "*On the IdP: Specific for only one remote SP in* ```saml20-sp-remote``` *or* ```shib13-sp-remote```" ([source](http://simplesamlphp.org/docs/stable/simplesamlphp-authproc#section_1), [permalink](http://simplesamlphp.org/docs/1.11/simplesamlphp-authproc#section_1)). The IdP in question would of course be the local IdP in your organisation. This way, you have one configuration point in your organisation for all SPs in said organisation.

See the link for information on how to configure. You will need to add something like this to the file referenced in the link:
```php
20 => array(
	'class' => 'role:Role',
	'roleRules' => array(
		/* Rules go here */
	),
),
```

# Configuration
## Config flags
Use these flags to change the behaviour of the module:
```php
20 => array(
	'class' => 'role:Role', // Required.
	
	 // Set rules, explained in the next paragraph.
	'roleRules' => array(/* Rules go here */),
	
	// Evaluate expected values as regular expressions. Defaults to FALSE.
	'regex' => FALSE,
	
	// The name of the attribute to hold the roles, defaults to "roles".
	'roleAttribute' => 'roles',
),
```

## Rules
The structure of a rule is as follows: ```rule[roleName]``` where ```rule``` is either ```acceptedValue[attributeName][]```, ```acceptedValue[attributeName]```, ```acceptedValue[]``` or ```acceptedValue```, or a mix of these.

```AttributeName``` and ```acceptedValue``` should be string values.

A pseudo-code definition would be ```(parentRole|parentRole[]|(acceptedValue|acceptedValue[])[attributeName])[role]```.

A list of accepted entries:

* ```[role][attributeName][] = expectedValue```
* ```[role][attributeName] = expectedValue```
* ```[role][] = parentRole```
* ```[role] = parentRole```

### Explanation of the variable names
* **role** is the name of the role which gets added if the rule matches.
* **attributeName** is the name of an attribute whose value should match an enclosing *expectedValue*.    
By omitting this field and using one or more *roleName*s of an earlier defined role as the *expectedValue*, you can stack roles.
* **expectedValue** is a list of accepted values for *attributeName*.

### Rule examples
The names ```admin``` and ```access``` used in these examples are completely arbitrary. You may use any name you please. The examples assume the following attributes to be in place to begin with:

* user
	* *johndoe*
* memberOf
	* *CN=users,OU=company,DC=example,DC=org*
	* *CN=finance,OU=company,DC=example,DC=org*
	* *CN=hr,OU=company,DC=example,DC=org*
	* *CN=management,OU=company,DC=example,DC=org*
	* *CN=it,OU=company,DC=example,DC=org*

#### Simple example
Allow access to anyone in the ```users``` group.
```php
'roleRules' => array(
	'access' => array(
		'group' => 'CN=users,OU=company,DC=example,DC=org',
	),
),
```
#### Different access levels
Allow access to anyone in the ```finance```, ```hr``` or ```management``` groups and grant admin to anyone in the ```admin``` group.
```php
'roleRules' => array(
	'admin' => array(
		'group' => 'CN=it,OU=company,DC=example,DC=org', // one value, array not required
	),
	'access' => array(
		'group' => array(
			'CN=finance,OU=company,DC=example,DC=org',
			'CN=hr,OU=company,DC=example,DC=org',
			'CN=management,OU=company,DC=example,DC=org',
		),
	),
),
```
*Note that an admin not in any of the other groups will not have the* ```access``` *role. Depending on the SP, this can be a problem. The next example solves this.*

#### Stacking
Like the previous example, but with role *stacking*; having the ```admin``` role automatically gives you the ```access``` role.
```php
'roleRules' => array(
	'admin' => array(
		'group' => 'CN=it,OU=company,DC=example,DC=org', // one value, array not required
	),
	'access' => array( // Inclusion in the access role happens when
		'group' => array( // you're in one of these groups
			'CN=finance,OU=company,DC=example,DC=org',
			'CN=hr,OU=company,DC=example,DC=org',
			'CN=management,OU=company,DC=example,DC=org',
		),
		'admin', // or you've been granted the admin role before
	),
),
```
Note that this method won't work if you reverse the definition for ```admin``` and ```access```, because then ```admin``` would not be defined when evaluating ```access```.

##### Different ways of stacking
Let's add a ```moderator``` role. The following code snippets do the same:
```php
'access' => array(
	'admin',
	'moderator'
),
```
```php
'access' => array(
	array('admin'),
	'moderator'
),
```
```php
'access' => array(
	array('admin'),
	array('moderator'),
),
```
```php
'access' => array(
	array(
		'admin',
		'moderator'
	),
),
```
These snippets are the same because a single value without a key counts as stacking. Whether there are multiple single values inside a role, or multiple single values inside a rule doesn't matter for the end result. 

#### Regular expressions
Rules with regular expressions work just as normal rules, with the only exception that the *acceptedValue*s are interpreted as regular expression patterns and whether or not to add a role is determined by the regular expression pattern matching the attribute value. See the [PHP documentation for preg_match](http://php.net/preg_match) for more information. An *acceptedValue* is a ```$pattern``` and a value of an attribute is a ```$subject```.