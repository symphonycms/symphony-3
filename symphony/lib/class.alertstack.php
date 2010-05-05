<?php

	class AlertStack implements Iterator {
		const SUCCESS = 'success';
		const NOTICE = 'notice';
		const ERROR = 'error';
		
		protected $alerts = array();
		
		public function append($message, $type = AlertStack::NOTICE, $info = null) {
			if (!is_null($info)) {
				if (
					!is_string($info)
					and !$info instanceof Exception
					and !$info instanceof DOMElement
					and !$info instanceof MessageStack
				) {
					throw new Exception(__('Expecting a string or an instance of Exception, DOMElement or MessageStack.'));
				}
			}
			
			$this->alerts[] = (object)array(
				'message'	=> $message,
				'type'		=> $type,
				'info'		=> $info
			);
		}
		
		public function appendTo(SymphonyDOMElement $parent) {
			$document = $parent->ownerDocument;
			
			$list = $document->createElement('ol');
			$list->setAttribute('id', 'alerts');
			
			foreach ($this as $alert) {
				$item = $document->createElement('li');
				$item->setAttribute('class', $alert->type);
				
				$message = $document->createElement('div');
				$message->setAttribute('class', 'message');
				$fragment = $document->createDocumentFragment();
				$fragment->appendXML($alert->message);
				$message->appendChild($fragment);
				$item->appendChild($message);
				
				if ($alert->info instanceof DOMElement) {
					$info = $document->createElement('div', $alert->info);
				}
				
				else if ($alert->info instanceof Exception) {
					$info = $document->createElement('div');
					$header = $document->createElement('h3');
					$header->setValue($alert->info->getMessage());
					$info->appendChild($header);
					
					$paragraph = $document->createElement('p');
					$fragment = $document->createDocumentFragment();
					$fragment->appendXML(__(
						'An error occurred in <code>%s</code> around line <code>%d</code>',
						array(
							$alert->info->getFile(),
							$alert->info->getLine()
						)
					));
					$paragraph->appendChild($fragment);
					$info->appendChild($paragraph);
					
					$ul = $document->createElement('ol');
					$ul->setAttribute('class', 'trace-list');
					
					foreach ($alert->info->getTrace() as $trace) {
						$li = $document->createElement('li');
						$code = $document->createElement('code');
						$strong = $document->createElement('strong');
						
						$called = array();
						
						if (isset($trace['class']) and !empty($trace['class'])) {
							$called[] = $trace['class'];
						}
						
						if (isset($trace['type']) and !empty($trace['type'])) {
							$called[] = $trace['type'];
						}
						
						if (isset($trace['function']) and !empty($trace['function'])) {
							$called[] = $trace['function'];
						}
						
						$code->setValue($trace['file'] . ':' . $trace['line']);
						$strong->setValue(implode('', $called));
						
						$code->appendChild($strong);
						$li->appendChild($code);
						$ul->appendChild($li);
					}
					
					$info->appendChild($ul);
				}
				
				else if ($alert->info instanceof MessageStack) {
					$info = $document->createElement('div');
					$alert->info->appendTo($info);
				}
				
				else if (is_string($alert->info)) {
					$fragment = $document->createDocumentFragment();
					$fragment->appendXML($alert->info);
					$info = $document->createElement('div', $fragment);
				}
				
				if (isset($info)) {
					$info->setAttribute('class', 'info');
					$item->appendChild($info);
				}
				
				$list->appendChild($item);
			}
			
			$parent->appendChild($list);
		}
		
		protected function appendMessageStack(SymphonyDOMElement $wrapper, MessageStack $messages) {
			
		}
		
	    public function rewind(){
	        reset($this->alerts);
	    }
		
	    public function current(){
	        return current($this->alerts);
	    }
		
	    public function key(){
	        return key($this->alerts);
	    }
		
	    public function next(){
	        return next($this->alerts);
	    }
		
	    public function valid(){
	        return ($this->current() !== false);
	    }
		
		public function length(){
			return count($this->alerts);
		}
		
		public function flush(){
			$this->alerts = array();
		}
	}