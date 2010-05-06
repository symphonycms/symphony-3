<?php

	require_once LIB . '/class.entry.php';
	require_once LIB . '/class.event.php';

	Class SectionsEvent extends Event {
		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element' => null,
				'section' => null,
				'filters' => array(),
				'overrides' => array(),
				'defaults' => array(),
				'output-id-on-save' => false
			);
		}

		final public function type(){
			return 'event_sections';
		}

		public function template(){
			return EXTENSIONS . '/event_sections/templates/template.event.php';
		}
		
		public function trigger(Register $ParameterOutput){

			$postdata = General::getPostData();

			if(!isset($postdata['action'][$this->parameters()->{'root-element'}])) return NULL;

			$result = new XMLDocument;
			$result->appendChild($result->createElement($this->parameters()->{'root-element'}));

			$root = $result->documentElement;

			if(isset($postdata['id'])){
				$entry = Entry::loadFromID($postdata['id']);
				$type = 'edit';
			}
			else{
				$entry = new Entry;
				$entry->section = $this->parameters()->{'section'};
				$entry->user_id = Frontend::instance()->User->id;
				$type = 'create';
			}

			if(isset($postdata['fields']) && is_array($postdata['fields']) && !empty($postdata['fields'])){
				$entry->setFieldDataFromFormArray($postdata['fields']);
			}

			$root->setAttribute('type', $type);

			###
			# Delegate: EntryPreCreate
			# Description: Just prior to creation of an Entry. Entry object provided
			ExtensionManager::instance()->notifyMembers(
				'EntryPreCreate', '/frontend/',
				array('entry' => &$entry)
			);

			$errors = new MessageStack;
			$status = Entry::save($entry, $errors);
			
			if($status == Entry::STATUS_OK){
				###
				# Delegate: EntryPostCreate
				# Description: Creation of an Entry. New Entry object is provided.
				ExtensionManager::instance()->notifyMembers(
					'EntryPostCreate', '/frontend/',
					array('entry' => $entry)
				);

				if($this->parameters()->{'output-id-on-save'} == true){
					$ParameterOutput->{sprintf('event-%s-id', $this->parameters()->{'root-element'})} = $entry->id;
				}

				$root->setAttribute('result', 'success');

				$root->appendChild($result->createElement(
					'message',
					__("Entry %s successfully.", array(($type == 'edit' ? __('edited') : __('created'))))
				));

			}
			else{
				$root->setAttribute('result', 'error');
				$root->appendChild($result->createElement(
					'message', __('Entry encountered errors when saving.')
				));
				
				if(!isset($postdata['fields']) || !is_array($postdata['fields'])) {
					$postdata['fields'] = array();
				}
				
				$element = $result->createElement('values');
				$this->appendValues($element, $postdata['fields']);
				$root->appendChild($element);
				
				$element = $result->createElement('errors');
				$this->appendMessages($element, $errors);
				$root->appendChild($element);
			}

			return $result;
		}
		
		protected function appendValues(DOMElement $wrapper, array $values) {
			$document = $wrapper->ownerDocument;
			
			foreach ($values as $key => $value) {
				if (is_numeric($key)) {
					$element = $document->createElement('item');
				}
				
				else {
					$element = $document->createElement($key);
				}
				
				if (is_array($value) and !empty($value)) {
					$this->appendValues($element, $value);
				}
				
				else {
					$element->setValue((string)$value);
				}
				
				$wrapper->appendChild($element);
			}
		}
		
		protected function appendMessages(DOMElement $wrapper, MessageStack $messages) {
			$document = $wrapper->ownerDocument;
			
			foreach ($messages as $key => $value) {
				if (is_numeric($key)) {
					$element = $document->createElement('item');
				}
				
				else {
					$element = $document->createElement($key);
				}
				
				if ($value instanceof $messages and $value->valid()) {
					$this->appendMessages($element, $value);
				}
				
				else if ($value instanceof STDClass) {
					$element->setValue($value->message);
					$element->setAttribute('type', $value->code);
				}
				
				else {
					continue;
				}
				
				$wrapper->appendChild($element);
			}
		}
	}
	
?>