<?php

	Class Widget{

		protected static $Symphony = false;

		public static function init(XMLDocument $doc) {
			self::$Symphony = $doc;
		}
		
		public static function Group(SymphonyDOMElement $element) {
			$group = Widget::$Symphony->createElement('div');
			$group->setAttribute('class', 'group');
			
			foreach (func_get_args() as $node) {
				if (!($node instanceof DOMNode)) continue;
				
				$group->appendChild($node);
			}
			
			return $group;
		}

		## Forms
		## First take at a generic fieldset builder for the new form layout
		public static function Fieldset($value=null, $help=null, array $attributes=NULL){
			$fieldset = Widget::$Symphony->createElement('fieldset', null, $attributes);

			if(!is_null($value)){
				$fieldset->appendChild(
					Widget::$Symphony->createElement('h3',$value)
				);
			}
			
			if(!is_null($help)){
				$para = Widget::$Symphony->createElement('p');
				$para->setAttribute('class', 'help');
				
				$frag = Widget::$Symphony->createDocumentFragment();
				$frag->appendXML($help);
				
				$para->appendChild($frag);
				$fieldset->appendChild($para);
			}

			return $fieldset;
		}

		public static function Form($action, $method, array $attributes=NULL){
			$form = Widget::$Symphony->createElement('form', null, $attributes);
			$form->setAttribute('action', $action);
			$form->setAttribute('method', $method);

			return $form;
		}

		public static function Label($name=null, DOMNode $child=null, array $attributes=NULL){
			$label = Widget::$Symphony->createElement('label', $name, $attributes);

			if(!is_null($child)) $label->appendChild($child);

			return $label;
		}

		public static function Input($name, $value=null, $type='text', array $attributes=NULL){
			$obj = Widget::$Symphony->createElement('input', null, $attributes);
			$obj->setAttribute('name', $name);

			if($type) $obj->setAttribute('type', $type);

			if(strlen($value) != 0) $obj->setAttribute('value', $value);

			return $obj;
		}
		
		public static function Submit($name, $value, array $attributes=NULL) {
			$obj = Widget::$Symphony->createElement('button', null, $attributes);
			$obj->setAttribute('name', $name);
			$obj->setAttribute('type', 'submit');
			$obj->appendChild(
				Widget::$Symphony->createElement('span', $value)
			);
			
			return $obj;
		}
		
		public static function Textarea($name, $value=null, array $attributes=NULL){
			$obj = Widget::$Symphony->createElement('textarea', $value, $attributes);

			$obj->appendChild(Widget::$Symphony->createTextNode(''));

			$obj->setAttribute('name', $name);

			return $obj;
		}

		public static function Select($name, $options, array $attributes=NULL){
			$obj = Widget::$Symphony->createElement('select');
			
			$attributes['name'] = $name;
			
			if(!is_null($attributes)){
				$obj->setAttributeArray($attributes);
			}

			$obj->appendChild(Widget::$Symphony->createTextNode(''));

			if(!is_array($options) || empty($options)){
				if(is_null($attributes) || !isset($attributes['disabled'])){
					$obj->setAttribute('disabled', 'disabled');
				}
				return $obj;
			}

			foreach($options as $o){

				## Opt Group
				if(isset($o['label'])){

					$optgroup = Widget::$Symphony->createElement('optgroup');
					$optgroup->setAttribute('label', $o['label']);

					foreach($o['options'] as $opt){
						$optgroup->appendChild(
							Widget::__SelectBuildOption($opt)
						);
					}

					$obj->appendChild($optgroup);
				}

				## Non-Opt group
				else $obj->appendChild(Widget::__SelectBuildOption($o));

			}

			return $obj;
		}

		private static function __SelectBuildOption($option){
			@list($value, $selected, $desc, $class, $id, $attr) = $option;
			if(!$desc) $desc = $value;

			$obj = Widget::$Symphony->createElement('option', "$desc");
			$obj->appendChild(Widget::$Symphony->createTextNode(''));
			$obj->setAttribute('value', (string)$value);

			if(!empty($class)) $obj->setAttribute('class', $class);
			if(!empty($id)) $obj->setAttribute('id', $id);
			if($selected) $obj->setAttribute('selected', 'selected');

			if(!empty($attr)) $obj->setAttributeArray($attr);

			return $obj;

		}

		##	Tables
		public static function Table($head=null, $foot=null, $body=null, array $attributes=NULL){
 			$table = Widget::$Symphony->createElement('table');

			if(!is_null($attributes)){
				$table->setAttributeArray($attributes);
			}

			if($head) $table->appendChild($head);
			if($foot) $table->appendChild($foot);
			if($body) $table->appendChild($body);

			return $table;
		}

		## Provided with an array of colum names, this will return an XML object
		public static function TableHead($columns){
			$thead = Widget::$Symphony->createElement('thead');
			$tr = Widget::$Symphony->createElement('tr');

			foreach($columns as $col){
				$th = Widget::$Symphony->createElement('th');

				$value = $scope = null;

				$value = $col[0];
				if(isset($col[1])) $scope = $col[1];
				if(isset($col[2])) $th->setAttributeArray($col[2]);

				$th->setValue($value);

				if($scope && $scope != '') $th->setAttribute('scope', $scope);

				$tr->appendChild($th);
			}

			$thead->appendChild($tr);

			return $thead;
		}

		public static function TableBody($rows, array $attributes=NULL){
			$tbody = Widget::$Symphony->createElement('tbody');

			if(!is_null($attributes)){
				$tbody->setAttributeArray($attributes);
			}

			foreach($rows as $r) $tbody->appendChild($r);

			return $tbody;
		}

		public static function TableRow($data, array $attributes=NULL){
			$tr = Widget::$Symphony->createElement('tr');
			
			if(!is_null($attributes)){
				$tr->setAttributeArray($attributes);
			}

			foreach($data as $d) $tr->appendChild($d);

			return $tr;
		}

		public static function TableData($value = null, array $attributes=NULL){
			$td = Widget::$Symphony->createElement('td');
			$td->setValue($value);

			if(!is_null($attributes)){
				$td->setAttributeArray($attributes);
			}

			return $td;
		}

		## Misc
		public static function Anchor($value, $href, array $attributes=NULL){
			$a = Widget::$Symphony->createElement('a', $value, $attributes);
			$a->setAttribute('href', $href);

			return $a;
		}

		public static function Acronym($value, array $attributes=NULL, $text = null){
			$doc = Widget::$Symphony->createDocumentFragment();
			$doc->appendChild(
				Widget::$Symphony->createElement('acronym', $value, $attributes)
			);

			if(!is_null($text)) {
				$doc->appendChild(
					new DOMText($text)
				);
			}

			return $doc;
		}

		public static function wrapFormElementWithError($element, $message=null){
			$div = Widget::$Symphony->createElement('div');
			$div->setAttributeArray(array('id' => 'error', 'class' => 'invalid'));
			$div->appendChild($element);

			if(!is_null($message)){
				$div->appendChild(
					Widget::$Symphony->createElement('p', $message)
				);
			}

			return $div;

		}

	}

