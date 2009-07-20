<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Array and variable validation.
 *
 * @package    Core
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Kohana_Validate extends ArrayObject {

	public static $messages = array(
		'default'      => ':field value is invalid',
		'not_empty'    => ':field must not be empty',
		'matches'      => ':field must be the same as :params',
		'regex'        => ':field does not match the required format',
		'exact_length' => ':field must be exactly :params characters long',
		'min_length'   => ':field must be at least :params characters long',
		'max_length'   => ':field must be less than :params characters long',
		'in_array'     => ':field must be of the these options: :params',
	);

	/**
	 * Creates a new Validation instance.
	 *
	 * @param   array   array to use for validation
	 * @return  object
	 */
	public static function factory(array $array)
	{
		return new Validate($array);
	}

	/**
	 * Checks if a field is not empty.
	 *
	 * @return  boolean
	 */
	public static function not_empty($value)
	{
		return ($value === '0' OR ! empty($value));
	}

	/**
	 * Checks a field against a regular expression.
	 *
	 * @param   string  value
	 * @param   string  regular expression to match (including delimiters)
	 * @return  boolean
	 */
	public static function regex($value, $expression)
	{
		return (bool) preg_match($expression, (string) $value);
	}

	/**
	 * Checks that a field is long enough.
	 *
	 * @param   string   value
	 * @param   integer  minimum length required
	 * @return  boolean
	 */
	public static function min_length($value, $length)
	{
		return UTF8::strlen($value) >= $length;
	}

	/**
	 * Checks that a field is short enough.
	 *
	 * @param   string   value
	 * @param   integer  maximum length required
	 * @return  boolean
	 */
	public static function max_length($value, $length)
	{
		return UTF8::strlen($value) <= $length;
	}

	/**
	 * Checks that a field is exactly the right length.
	 *
	 * @param   string   value
	 * @param   integer  exact length required
	 * @return  boolean
	 */
	public static function exact_length($value, $length)
	{
		return UTF8::strlen($value) === $length;
	}

	/**
	 * Check an email address for correct format.
	 *
	 * @link  http://www.iamcal.com/publish/articles/php/parsing_email/
	 * @link  http://www.w3.org/Protocols/rfc822/
	 *
	 * @param   string   email address
	 * @param   boolean  strict RFC compatibility
	 * @return  boolean
	 */
	public static function email($email, $strict = FALSE)
	{
		if ($strict === TRUE)
		{
			$qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
			$dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
			$atom  = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
			$pair  = '\\x5c[\\x00-\\x7f]';

			$domain_literal = "\\x5b($dtext|$pair)*\\x5d";
			$quoted_string  = "\\x22($qtext|$pair)*\\x22";
			$sub_domain     = "($atom|$domain_literal)";
			$word           = "($atom|$quoted_string)";
			$domain         = "$sub_domain(\\x2e$sub_domain)*";
			$local_part     = "$word(\\x2e$word)*";

			$expression     = "/^$local_part\\x40$domain$/D";
		}
		else
		{
			$expression = '/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD';
		}

		return (bool) preg_match($expression, (string) $email);
	}

	/**
	 * Validate the domain of an email address by checking if the domain has a
	 * valid MX record.
	 *
	 * @link  http://php.net/checkdnsrr  not added to Windows until PHP 5.3.0
	 *
	 * @param   string   email address
	 * @return  boolean
	 */
	public static function email_domain($email)
	{
		// Check if the email domain has a valid MX record
		return (bool) checkdnsrr(preg_replace('/^[^@]+@/', '', $email), 'MX');
	}

	/**
	 * Validate a URL.
	 *
	 * @param   string   URL
	 * @return  boolean
	 */
	public static function url($url)
	{
		return (bool) filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED);
	}

	/**
	 * Validate an IP.
	 *
	 * @param   string   IP address
	 * @param   boolean  allow private IP networks
	 * @return  boolean
	 */
	public static function ip($ip, $allow_private = TRUE)
	{
		// Do not allow reserved addresses
		$flags = FILTER_FLAG_NO_RES_RANGE;

		if ($allow_private === FALSE)
		{
			// Do not allow private or reserved addresses
			$flags = $flags | FILTER_FLAG_NO_PRIV_RANGE;
		}

		return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
	}

	/**
	 * Validates a credit card number using the Luhn (mod10) formula.
	 *
	 * @link http://en.wikipedia.org/wiki/Luhn_algorithm
	 *
	 * @param   integer       credit card number
	 * @param   string|array  card type, or an array of card types
	 * @return  boolean
	 */
	public static function credit_card($number, $type = NULL)
	{
		// Remove all non-digit characters from the number
		if (($number = preg_replace('/\D+/', '', $number)) === '')
			return FALSE;

		if ($type == NULL)
		{
			// Use the default type
			$type = 'default';
		}
		elseif (is_array($type))
		{
			foreach ($type as $t)
			{
				// Test each type for validity
				if (Validate::credit_card($number, $t))
					return TRUE;
			}

			return FALSE;
		}

		$cards = Kohana::config('credit_cards');

		// Check card type
		$type = strtolower($type);

		if ( ! isset($cards[$type]))
			return FALSE;

		// Check card number length
		$length = strlen($number);

		// Validate the card length by the card type
		if ( ! in_array($length, preg_split('/\D+/', $cards[$type]['length'])))
			return FALSE;

		// Check card number prefix
		if ( ! preg_match('/^'.$cards[$type]['prefix'].'/', $number))
			return FALSE;

		// No Luhn check required
		if ($cards[$type]['luhn'] == FALSE)
			return TRUE;

		// Checksum of the card number
		$checksum = 0;

		for ($i = $length - 1; $i >= 0; $i -= 2)
		{
			// Add up every 2nd digit, starting from the right
			$checksum += substr($number, $i, 1);
		}

		for ($i = $length - 2; $i >= 0; $i -= 2)
		{
			// Add up every 2nd digit doubled, starting from the right
			$double = substr($number, $i, 1) * 2;

			// Subtract 9 from the double where value is greater than 10
			$checksum += ($double >= 10) ? $double - 9 : $double;
		}

		// If the checksum is a multiple of 10, the number is valid
		return ($checksum % 10 === 0);
	}

	/**
	 * Checks if a phone number is valid.
	 *
	 * @param   string   phone number to check
	 * @return  boolean
	 */
	public static function phone($number, $lengths = NULL)
	{
		if ( ! is_array($lengths))
		{
			$lengths = array(7,10,11);
		}

		// Remove all non-digit characters from the number
		$number = preg_replace('/\D+/', '', $number);

		// Check if the number is within range
		return in_array(strlen($number), $lengths);
	}

	/**
	 * Tests if a string is a valid date string.
	 *
	 * @param   string   date to check
	 * @return  boolean
	 */
	public static function date($str)
	{
		return (strtotime($str) !== FALSE);
	}

	/**
	 * Checks whether a string consists of alphabetical characters only.
	 *
	 * @param   string   input string
	 * @param   boolean  trigger UTF-8 compatibility
	 * @return  boolean
	 */
	public static function alpha($str, $utf8 = FALSE)
	{
		$str = (string) $str;

		if ($utf8 === TRUE)
		{
			return (bool) preg_match('/^\pL++$/uD', $str);
		}
		else
		{
			return ctype_alpha($str);
		}
	}

	/**
	 * Checks whether a string consists of alphabetical characters and numbers only.
	 *
	 * @param   string   input string
	 * @param   boolean  trigger UTF-8 compatibility
	 * @return  boolean
	 */
	public static function alpha_numeric($str, $utf8 = FALSE)
	{
		if ($utf8 === TRUE)
		{
			return (bool) preg_match('/^[\pL\pN]++$/uD', $str);
		}
		else
		{
			return ctype_alnum($str);
		}
	}

	/**
	 * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
	 *
	 * @param   string   input string
	 * @param   boolean  trigger UTF-8 compatibility
	 * @return  boolean
	 */
	public static function alpha_dash($str, $utf8 = FALSE)
	{
		if ($utf8 === TRUE)
		{
			$regex = '/^[-\pL\pN_]++$/uD';
		}
		else
		{
			$regex = '/^[-a-z0-9_]++$/iD';
		}

		return (bool) preg_match($regex, $str);
	}

	/**
	 * Checks whether a string consists of digits only (no dots or dashes).
	 *
	 * @param   string   input string
	 * @param   boolean  trigger UTF-8 compatibility
	 * @return  boolean
	 */
	public static function digit($str, $utf8 = FALSE)
	{
		if ($utf8 === TRUE)
		{
			return (bool) preg_match('/^\pN++$/uD', $str);
		}
		else
		{
			return ctype_digit($str);
		}
	}

	/**
	 * Checks whether a string is a valid number (negative and decimal numbers allowed).
	 *
	 * Uses {@link http://www.php.net/manual/en/function.localeconv.php locale conversion}
	 * to allow decimal point to be locale specific.
	 *
	 * @param   string   input string
	 * @return  boolean
	 */
	public static function numeric($str)
	{
		// Get the decimal point for the current locale
		list($decimal) = array_values(localeconv());

		return (bool) preg_match('/^-?[0-9'.$decimal.']++$/D', (string) $str);
	}

	/**
	 * Tests if a number is within a range.
	 *
	 * @param   string   number to check
	 * @param   integer  minimum value
	 * @param   integer  maximum value
	 * @return  boolean
	 */
	public static function range($number, $min, $max)
	{
		return ($number >= $min AND $number <= $max);
	}

	/**
	 * Checks if a string is a proper decimal format. The format array can be
	 * used to specify a decimal length, or a number and decimal length, eg:
	 * array(2) would force the number to have 2 decimal places, array(4,2)
	 * would force the number to have 4 digits and 2 decimal places.
	 *
	 * @param   string   number to check
	 * @param   integer  number of decimal places
	 * @return  boolean
	 */
	public static function decimal($str, $places = 2)
	{
		// Get the decimal point for the current locale
		list($decimal) = array_values(localeconv());

		return (bool) preg_match('/^[0-9]+'.preg_quote($decimal).'[0-9]{'.(int) $places.'}$/', $str);
	}

	/**
	 * Checks if a string is a proper hexadecimal HTML color value. The validation
	 * is quite flexible as it does not require an initial "#" and also allows for
	 * the short notation using only three instead of six hexadecimal characters.
	 *
	 * @param   string   input string
	 * @return  boolean
	 */
	public static function color($str)
	{
		return (bool) preg_match('/^#?+[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $str);
	}

	/**
	 * @var  array  filters
	 */
	protected $filters = array();

	/**
	 * @var  array  rules
	 */
	protected $rules = array();

	/**
	 * @var  array  callbacks
	 */
	protected $callbacks = array();

	/**
	 * @var  array  field labels
	 */
	protected $labels = array();

	/**
	 * Sets the unique "any field" key and creates an ArrayObject from the
	 * passed array.
	 *
	 * @param   array   array to validate
	 * @return  void
	 */
	public function __construct(array $array)
	{
		parent::__construct($array, ArrayObject::STD_PROP_LIST);
	}

	/**
	 * Returns the array representation of the current object.
	 *
	 * @return  array
	 */
	public function as_array()
	{
		return $this->getArrayCopy();
	}

	/**
	 * Sets or overwrites the label name for a field.
	 *
	 * @param   string  field name
	 * @param   string  label
	 * @return  $this;
	 */
	public function label($field, $label)
	{
		// Set the label for this field
		$this->labels[$field] = $label;

		return $this;
	}

	/**
	 * Overwrites or appends filters to a field. Each filter will be executed once.
	 * All rules must be valid callbacks.
	 *
	 *     // Run trim() on all fields
	 *     $validation->filter(TRUE, 'trim');
	 *
	 * @param   string  field name
	 * @param   mixed   valid PHP callback
	 * @param   array   extra parameters for the callback
	 * @return  $this
	 */
	public function filter($field, $filter, array $params = NULL)
	{
		if ($field !== TRUE AND ! isset($this->labels[$field]))
		{
			// Set the field label to the field name
			$this->labels[$field] = preg_replace('/[^\pL]+/u', ' ', $field);
		}

		// Store the filter and params for this rule
		$this->filters[$field][$filter] = (array) $params;

		return $this;
	}


	/**
	 * Overwrites or appends rules to a field. Each rule will be executed once.
	 * All rules must be string names of functions method names.
	 *
	 *     // The "username" must not be empty and have a minimum length of 4
	 *     $validation->rule('username', 'not_empty')
	 *                ->rule('username', 'min_length', array(4));
	 *
	 * @param   string  field name
	 * @param   string  function or static method name
	 * @param   array   extra parameters for the callback
	 * @return  $this
	 */
	public function rule($field, $rule, array $params = NULL)
	{
		if ($field !== TRUE AND ! isset($this->labels[$field]))
		{
			// Set the field label to the field name
			$this->labels[$field] = preg_replace('/[^\pL]+/u', ' ', $field);
		}

		// Store the rule and params for this rule
		$this->rules[$field][$rule] = (array) $params;

		return $this;
	}

	/**
	 * Add rules using an array.
	 *
	 * @param   string  field name
	 * @param   array   list of functions or static method name
	 * @return  $this
	 */
	public function rule_set($field, array $rules)
	{
		foreach ($rules as $rule => $params)
		{
			$this->rule($field, $rule, $params);
		}

		return $this;
	}

	/**
	 * Adds a callback to a field. Each callback will be executed only once.
	 * No extra parameters can be passed as the format for callbacks is
	 * predefined as (Validate $array, $field, array $errors).
	 *
	 *     // The "username" must be checked with a custom method
	 *     $validation->callback('username', array($this, 'check_username'));
	 *
	 * To add a callback to every field already set, use TRUE for the field name.
	 *
	 * @param   string  field name
	 * @param   mixed   callback to add
	 * @return  $this
	 */
	public function callback($field, $callback)
	{
		if ( ! isset($this->callbacks[$field]))
		{
			// Create the list for this field
			$this->callbacks[$field] = array();
		}

		if ($field !== TRUE AND ! isset($this->labels[$field]))
		{
			// Set the field label to the field name
			$this->labels[$field] = preg_replace('/[^\pL]+/u', ' ', $field);
		}

		if ( ! in_array($callback, $this->callbacks[$field], TRUE))
		{
			// Store the callback
			$this->callbacks[$field][] = $callback;
		}

		return $this;
	}

	/**
	 * Executes all validation filters, rules, and callbacks. Errors will be
	 * stored in the variable passed by reference.
	 *
	 *     $validation->check($errors);
	 *
	 * @param   array    error list
	 * @return  boolean
	 */
	public function check( & $errors)
	{
		if (Kohana::$profiling === TRUE)
		{
			// Start a new benchmark
			$benchmark = Profiler::start('Validation', __FUNCTION__);
		}

		// New data set
		$data = array();

		// Assume nothing has been submitted
		$submitted = FALSE;

		// Get a list of the expected fields
		$expected = array_keys($this->labels);

		// Import the filters, rules, and callbacks locally
		$filters   = $this->filters;
		$rules     = $this->rules;
		$callbacks = $this->callbacks;

		foreach ($expected as $field)
		{
			if (isset($this[$field]))
			{
				// Some data has been submitted, continue validation
				$submitted = TRUE;

				// Use the submitted value
				$data[$field] = $this[$field];
			}
			else
			{
				// No data exists for this field
				$data[$field] = NULL;
			}

			if (isset($filters[TRUE]))
			{
				if ( ! isset($filters[$field]))
				{
					// Initialize the filters for this field
					$filters[$field] = array();
				}

				// Append the filters
				$filters[$field] += $filters[TRUE];
			}

			if (isset($rules[TRUE]))
			{
				if ( ! isset($rules[$field]))
				{
					// Initialize the rules for this field
					$rules[$field] = array();
				}

				// Append the rules
				$rules[$field] += $rules[TRUE];
			}

			if (isset($callbacks[TRUE]))
			{
				if ( ! isset($callbacks[$field]))
				{
					// Initialize the callbacks for this field
					$callbacks[$field] = array();
				}

				// Append the callbacks
				$callbacks[$field] += $callbacks[TRUE];
			}
		}

		// Overload the current array with the new one
		$this->exchangeArray($data);

		// Make sure that the errors are an array
		$errors = (array) $errors;

		if ($submitted === FALSE)
		{
			// Because no data was submitted, validation will not be forced
			return FALSE;
		}

		// Remove the filters, rules, and callbacks that apply to every field
		unset($filters[TRUE], $rules[TRUE], $callbacks[TRUE]);

		// Execute the filters

		foreach ($filters as $field => $set)
		{
			// Skip empty fields
			if ($this[$field] === NULL OR $this[$field] === '') continue;

			// Get the field value
			$value = $this[$field];

			foreach ($set as $filter => $params)
			{
				// Add the field value to the parameters
				array_unshift($params, $value);

				if (strpos($filter, '::') === FALSE)
				{
					// Use a function call
					$function = new ReflectionFunction($filter);

					// Call $function($this[$field], $param, ...) with Reflection
					$value = $function->invokeArgs($params);
				}
				else
				{
					// Split the class and method of the rule
					list($class, $method) = explode('::', $filter, 2);

					// Use a static method call
					$method = new ReflectionMethod($class, $method);

					// Call $Class::$method($this[$field], $param, ...) with Reflection
					$value = $method->invokeArgs(NULL, $params);
				}
			}

			// Set the filtered value
			$this[$field] = $value;
		}

		// Execute the rules

		foreach ($rules as $field => $set)
		{
			// Get the field value
			$value = $this[$field];

			foreach ($set as $rule => $params)
			{
				// Skip all rules except for "not_empty" with empty fields
				if ($rule !== 'not_empty' AND ($value === '' OR $value === NULL)) continue;

				// Add the field value to the parameters
				array_unshift($params, $value);

				if (method_exists($this, $rule))
				{
					// Use a method in this object
					$method = new ReflectionMethod($this, $rule);

					if ($method->isStatic())
					{
						// Call static::$rule($this[$field], $param, ...) with Reflection
						$passed = $method->invokeArgs(NULL, $params);
					}
					else
					{
						// Do not use Reflection here, the method may be protected
						$passed = call_user_func_array(array($this, $rule), $params);
					}
				}
				elseif (strpos($rule, '::') === FALSE)
				{
					// Use a function call
					$function = new ReflectionFunction($rule);

					// Call $function($this[$field], $param, ...) with Reflection
					$passed = $function->invokeArgs($params);
				}
				else
				{
					// Split the class and method of the rule
					list($class, $method) = explode('::', $rule, 2);

					// Use a static method call
					$method = new ReflectionMethod($class, $method);

					// Call $Class::$method($this[$field], $param, ...) with Reflection
					$passed = $method->invokeArgs(NULL, $params);
				}

				if ($passed === FALSE)
				{
					if ( ! isset(Validate::$messages[$rule]))
					{
						// Use the default message, no custom message exists
						$rule = 'default';
					}

					// Make a text list of the parameters without the value
					$params = implode(', ', array_slice($params, 1));

					// Add the field error using i18n
					$errors[$field] = __(Validate::$messages[$rule], array(':field'  => __($this->labels[$field]), ':params' => $params));

					// This field has an error, stop executing rules
					break;
				}
			}
		}

		// Execute the callbacks

		foreach ($callbacks as $field => $set)
		{
			foreach ($set as $callback)
			{
				// Skip any field that already has an error
				if (isset($errors[$field])) continue;

				if (is_string($callback) AND strpos($callback, '::') !== FALSE)
				{
					// Make the static callback into an array
					$callback = explode('::', $callback, 2);
				}

				if (is_array($callback))
				{
					// Separate the object and method
					list ($object, $method) = $callback;

					// Use a method in the given object
					$method = new ReflectionMethod($object, $method);

					if ( ! is_object($object))
					{
						// The object must be NULL for static calls
						$object = NULL;
					}

					// Call $object->$method($this, $field, $errors) with Reflection
					$errors = $method->invoke($object, $this, $field, $errors);
				}
				else
				{
					// Use a function call
					$function = new ReflectionFunction($callback);

					// Call $function($this, $field, $errors) with Reflection
					$errors = $function->invoke($this, $field, $errors);
				}
			}
		}

		if (isset($benchmark))
		{
			// Stop benchmarking
			Profiler::stop($benchmark);
		}

		return empty($errors);
	}

	/**
	 * Checks if a field matches the value of another field.
	 *
	 * @param   string   field value
	 * @param   string   field name to match
	 * @return  boolean
	 */
	protected function matches($value, $match)
	{
		return ($value === $this[$match]);
	}

} // End Validation
