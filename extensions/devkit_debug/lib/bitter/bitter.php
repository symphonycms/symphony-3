<?php
/*----------------------------------------------------------------------------*/
	
	if (!defined('BITTER_LANGUAGE_PATH')) {
		define('BITTER_LANGUAGE_PATH', './languages');
	}
	
	if (!defined('BITTER_FORMAT_PATH')) {
		define('BITTER_FORMAT_PATH', './formats');
	}
	
	if (!defined('BITTER_CACHE_PATH')) {
		define('BITTER_CACHE_PATH', './caches');
	}
	
/*----------------------------------------------------------------------------*/
	
	class Bitter {
		static public function encode($subject) {
			return str_replace(
				array('&', '<', '>'),
				array('&amp;', '&lt;', '&gt;'),
				$subject
			);
		}
		
		static public function capture($expression, $flags = '') {
			return new BitterMatch($expression, $flags, BitterMatch::CAPTURE);
		}
		
		static public function start($expression, $flags = '') {
			return new BitterMatch($expression, $flags, BitterMatch::START);
		}
		
		static public function stop($expression, $flags = '') {
			return new BitterMatch($expression, $flags, BitterMatch::STOP);
		}
		
		static public function id($name) {
			return new BitterId($name);
		}
		
		static public function tag($value) {
			return new BitterTag($value);
		}
		
		static public function rule(BitterId $id) {
			$values = func_get_args();
			$rule = new BitterRule();
			
			foreach ($values as $index => $value) if ($index) {
				if ($value instanceof BitterId) {
					$rule->appendBitterRule($value);
				}
				
				else if ($value instanceof BitterTag) {
					$rule->setBitterTag($value);
				}
				
				else if ($value instanceof BitterMatch) {
					if ($value->role() == BitterMatch::START) {
						$rule->setStart($value);
					}
					
					else if ($value->role() == BitterMatch::STOP) {
						$rule->setStop($value);
					}
					
					else {
						$rule->setCapture($value);
					}
				}
			}
			
			return $id->set($rule);
		}
		
	/*------------------------------------------------------------------------*/
		
		protected $cache = true;
		protected $cache_file = null;
		protected $cache_time = 0;
		protected $refresh_time = 0;
		protected $language = null;
		protected $language_name = null;
		protected $language_file = null;
		protected $language_path = null;
		protected $language_time = 0;
		protected $format = null;
		protected $format_name = null;
		protected $format_file = null;
		protected $format_path = null;
		protected $format_time = 0;
		
		public function __construct($cache = true) {
			$this->cache = $cache;
		}
		
		public function loadLanguage($language) {
			$this->language_name = basename($language);
			$this->language = Bitter::id($this->language_name);
			$this->language_file = BITTER_LANGUAGE_PATH . '/' . $this->language_name . '.php';
			
			if (is_readable($this->language_file)) {
				$this->language_time = (integer)filemtime($this->language_file);
				
				include_once($this->language_file);
				
				return true;
			}
			
			return false;
		}
		
		public function loadFormat($format) {
			$this->format_name = basename($format);
			$this->format_file = BITTER_FORMAT_PATH . '/' . $this->format_name . '.php';
			
			if (is_readable($this->format_file)) {
				$this->format_time = (integer)filemtime($this->format_file);
				
				$this->format = include_once($this->format_file);
				
				return true;
			}
			
			return false;
		}
		
		public function process($source) {
			if (!$this->language instanceof BitterId or !$this->language->exists()) {
				throw new Exception("Unable to find language '{$this->language_name}'.");
			}
			
			if (!$this->format instanceof BitterFormat) {
				throw new Exception("Unable to find format '{$this->format_name}'.");
			}
			
			// Sanitise newline formats:
			$source = trim(preg_replace('/(\r\n|\r|\n)/i', "\n", $source));
			
			$this->cache_time = 0;
			$this->cache_file = BITTER_CACHE_PATH . '/' . implode('-', array(
				md5($this->language_name),
				md5($this->format_name),
				md5($source)
			));
			
			if (is_readable($this->cache_file) and is_file($this->cache_file)) {
				$this->cache_time = (integer)filemtime($this->cache_file);
				$this->refresh_time = max($this->language_time, $this->format_time);
			}
			
			// Use cache:
			if ($this->cache) {
				// Cache is stale:
				if ($this->cache_time <= $this->refresh_time) {
					$language = $this->language->get();
					$format = $this->format;
					
					$output = $language->process($source)->output;
					$output = $format->process($output);
					
					file_put_contents($this->cache_file, $output);
				}
				
				// Cache is fresh:
				else {
					$output = file_get_contents($this->cache_file);
				}
			}
			
			else {
				$language = $this->language->get();
				$format = $this->format;
				
				$output = $language->process($source)->output;
				$output = $format->process($output);
			}
			
			return $output;
		}
	}
	
/*----------------------------------------------------------------------------*/	
	
	class BitterFormat {
		public function process($source) {
			return $source;
		}
	}
	
/*----------------------------------------------------------------------------*/	
	
	class BitterId {
		static protected $ids = array();
		
		static protected function _exists(BitterId $id) {
			return array_key_exists((string)$id, self::$ids);
		}
		
		static protected function _get(BitterId $id) {
			if (self::_exists($id)) {
				return self::$ids[(string)$id];
			}
			
			throw new Exception("Unable to find instance of '{$id}'.");
		}
		
		static protected function _set(BitterId $id, $data) {
			self::$ids[(string)$id] = $data;
			
			return $id;
		}
		
	/*------------------------------------------------------------------------*/
		
		protected $name;
		
		public function __construct($name) {
			$this->name = $name;
		}
		
		public function __toString() {
			return $this->name;
		}
		
		public function exists() {
			return self::_exists($this);
		}
		
		public function get() {
			return self::_get($this);
		}
		
		public function set($data) {
			return self::_set($this, $data);
		}
	}
	
/*----------------------------------------------------------------------------*/
	
	class BitterMatch {
		const CAPTURE = 1;
		const START = 2;
		const STOP = 3;
		
		protected $expression = null;
		protected $flags = null;
		protected $role = null;
		protected $offset = 0;
		
		public function __construct($expression, $flags = '', $role = self::CAPTURE) {
			$this->expression = $expression;
			$this->flags = $flags;
			$this->role = $role;
			
			if (@preg_match($this, '') === false) {
				$error = (object)error_get_last();
				
				throw new Exception("Invalid expression: {$error->message}");
			}
		}
		
		public function __toString() {
			return sprintf(
				'/%s/%s', addcslashes($this->expression, '/'), $this->flags
			);
		}
		
		public function role() {
			return $this->role;
		}
		
		public function test($subject) {
			return (boolean)preg_match($this, $subject, $matches, 0, $this->offset);
		}
		
		public function match($subject) {
			preg_match($this, $subject, $match, 0, $this->offset);
			
			if (empty($match)) return false;
			
			return $match[0];
		}
		
		public function position($subject) {
			$matches = array();
			$position = -1;
			
			if (preg_match($this, $subject, $matches, PREG_OFFSET_CAPTURE, $this->offset)) {
				$position = $matches[0][1];
			}
			
			return $position;
		}
	}
	
/*----------------------------------------------------------------------------*/	
	
	class BitterTag {
		protected $values = array();
		
		public function __construct($value) {
			$this->values = preg_split('/\s+/', $value);
		}
		
		public function __toString() {
			return implode(' ', $this->values);
		}
		
		public function open() {
			return sprintf(
				'<span class="%s">',
				implode(' ', $this->values)
			);
		}
		
		public function close() {
			return '</span>';
		}
		
		public function wrap($subject) {
			return $this->open() . $subject . $this->close();
		}
	}
	
/*----------------------------------------------------------------------------*/	
	
	class BitterRule {
		protected $rules = array();
		protected $disabled = array();
		
		protected $capture = null;
		protected $start = null;
		protected $stop = null;
		protected $tag = null;
		
		protected $position = -1;
		protected $enabled = true;
		
		public function disable(BitterId $id) {
			$this->disabled[] = $id;
		}
		
		public function disabled(BitterId $id) {
			return in_array($id, $this->disabled);
		}
		
		public function process($context, $root = false) {
			$output = ''; $count = 0;
			$this->disabled = array();
			
			while (strlen($context)) {
				$rule = $this->getRule($context);
				
				if ($count++ > 1000000) {
					throw new Exception('Processing failed, recursion limit reached.');
				}
				
				if ($rule instanceof BitterRule) {
					$state = $rule->getState($context);
					
					$output .= $state->before;
					$output .= $state->value;
					$context = $state->context;
					continue;
				}
				
				break;
			}
			
			if ($this->stop instanceof BitterMatch) {
				$position = $this->stop->position($context);
				
				if ($position >= 0) {
					$match = $this->stop->match($context);
					$before = substr($context, 0, $position);
					$after = substr($context, $position + strlen($match));
					
					$output .= Bitter::encode($before);
					$output .= Bitter::tag('stop')->wrap(Bitter::encode($match));
					$context = $after;
					
				} else {
					$output .= Bitter::encode($context);
					$context = '';
				}
				
			} else {
				$output .= Bitter::encode($context);
				$context = '';
			}
			
			return (object)array(
				'output'	=> $output,
				'context'	=> $context
			);
		}
		
		protected function getRule($context) {
			$result_position = strlen($context);
			$result_rule = null;
			
			if ($this->stop instanceof BitterMatch) {
				$position = $this->stop->position($context);
				
				if ($position >= 0 and $position < $result_position) {
					$result_position = $position;
				}
			}
			
			foreach ($this->rules as $id) {
				if ($this->disabled($id)) continue;
				
				$rule = $id->get();
				$position = $rule->getPosition($context);
				
				if ($position == -1) {
					$this->disable($id);
					continue;
				}
				
				if ($position < $result_position) {
					$result_rule = $rule;
					$result_position = $position;
				}
				
				if ($position == 0) break;
			}
			
			return $result_rule;
		}
		
		public function getPosition($context) {
			$this->position = -1;
			
			if ($this->capture instanceof BitterMatch) {
				$this->position = $this->capture->position($context);
			}
			
			else if ($this->start instanceof BitterMatch) {
				$this->position = $this->start->position($context);
			}
			
			return $this->position;
		}
		
		public function getState($context) {
			$before = substr($context, 0, $this->position);
			$output = '';
			
			if ($this->capture instanceof BitterMatch) {
				$match = $this->capture->match($context);
				$context = substr($context, $this->position + strlen($match));
				
				if (!empty($this->rules)) {
					$output .= $this->process($match)->output;
				}
				
				else {
					$output .= Bitter::encode($match);
				}
			}
			
			else if ($this->start instanceof BitterMatch) {
				$match = $this->start->match($context);
				$context = substr($context, $this->position + strlen($match));
				
				$result = $this->process($context);
				
				$output .= Bitter::tag('start')->wrap(Bitter::encode($match));
				$output .= $result->output;
				$context = $result->context;
			}
			
			if ($this->tag instanceof BitterTag) {
				$output = $this->tag->wrap($output);
			}
			
			return (object)array(
				'before'	=> Bitter::encode($before),
				'value'		=> $output,
				'context'	=> $context
			);
		}
		
		public function appendBitterRule(BitterId $rule) {
			$this->rules[] = $rule;
		}
		
		public function setBitterTag(BitterTag $tag) {
			$this->tag = $tag;
		}
		
		public function setCapture(BitterMatch $match) {
			$this->capture = $match;
		}
		
		public function setStart(BitterMatch $match) {
			$this->start = $match;
		}
		
		public function setStop(BitterMatch $match) {
			$this->stop = $match;
		}
	}
	
/*----------------------------------------------------------------------------*/
?>
