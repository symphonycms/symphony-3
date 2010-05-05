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
				foreach($errors as $element_name => $e){
					$element = $result->createElement($element_name);
					foreach($e as $field => $obj){
						$error = $result->createElement('error', $obj->message);
						$error->setAttribute('type', $obj->code);
						$error->setAttribute('field', $field);
						$element->appendChild($error);
					}
					$root->appendChild($element);
				}
			}

			return $result;
		}
	}
	
?>