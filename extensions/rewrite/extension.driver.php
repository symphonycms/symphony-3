<?php
	
	class Extension_Rewrite extends Extension {
		static $url = null;
		static $headers = array();
		
		public function about() {
			return (object)array(
				'name'			=> 'Rewrite',
				'version'		=> '1.0.2',
				'release-date'	=> '2010-04-06',
				'author'		=> (object)array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				)
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendPreInitialise',
					'callback'	=> 'frontendPreInitialise'
				)
			);
		}
		
		public function frontendPreInitialise($context) {
			$conf = Symphony::Configuration()->{'rewrite'}();
			$url = $_SERVER['REQUEST_URI'];
			
			if (strpos($url, __SYM_COOKIE_PATH__) == 0) {
				$url = substr($url, strlen(__SYM_COOKIE_PATH__));
			}
			
			$url = trim($url, '/');
			
			foreach ($conf->rules as $rule) {
				$match = $rule->match;
				$replace = $rule->replace;
				$case = $rule->{'case-sensetive'} == 'yes';
				
				// Escape slashes:
				$match = str_replace('/', '\/', $match);
				
				// Add regexp flags:
				$match = '/' . $match . '/' . ($case ? '' : 'i');
				
				if (preg_match($match, $url, $matches)) {
					$bits = preg_split('/(\\\[0-9]+)/', $replace, 0, PREG_SPLIT_DELIM_CAPTURE);
					$redirect = '';
					
					foreach ($bits as $bit) {
						if (preg_match('/^\\\[0-9]+$/', $bit)) {
							$index = (integer)trim($bit, '\\');
							
							if (isset($matches[$index])) {
								$redirect .= $matches[$index];
							}
							
							continue;
						}
						
						$redirect .= $bit;
					}
					
					break;
				}
			}
			
			// Found a new URL:
			if (isset($redirect)) {
				$url = parse_url($redirect);
				$symphony_page = trim($url['path'], '/');
				$query_string = $url['query'] . '&symphony-page=' . $symphony_page;
				$query_array = $this->parseQueryString($query_string);
				
				$context['page'] = '/' . $symphony_page;
				$context['view'] = View::loadFromURL($context['page']);
				$_SERVER['QUERY_STRING'] = $query_string;
				$_GET = $query_array;
			}
		}
		
		protected function parseQueryString($query) {
			$query  = html_entity_decode($query);
			$query  = explode('&', $query);
			$result  = array();
			
			foreach ($query as $query_part) {
				$bits = explode('=', $query_part, 2);
				
				if ($bits[0] == '') continue;
				
				if (isset($bits[1])) {
					$result[$bits[0]] = $bits[1];
				}
				
				else {
					$result[$bits[0]] = null;
				}
			}
			
			return $result;
		}
	}
	
	return 'Extension_Rewrite';
	
?>