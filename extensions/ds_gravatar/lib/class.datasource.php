<?php
	
	require_once LIB . '/class.datasource.php';
	
	class GravatarDataSource extends DataSource {
		public function __construct(){
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'root-element'	=> 'static-xml',
				'addresses'		=> '',
				'default'		=> '',
				'size'			=> '',
				'rating'		=> ''
			);
		}
		
		public function getType() {
			return 'GravatarDataSource';
		}
		
		public function getTemplate() {
			return EXTENSIONS . '/ds_gravatar/templates/template.datasource.php';
		}
		
		public function prepareSourceColumnValue() {
			return Widget::TableData(Widget::Anchor(
				__('Gravatar'), 'http://www.gravatar.com'
			));
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function prepare(array $data = null) {
			if (!is_null($data)) {
				if (isset($data['about']['name'])) {
					$this->about()->name = $data['about']['name'];
				}
				
				if (isset($data['addresses'])) {
					$this->parameters()->{'addresses'} = $data['addresses'];
				}
				
				if (isset($data['default'])) {
					$this->parameters()->{'default'} = $data['default'];
				}
				
				if (isset($data['size'])) {
					$this->parameters()->{'size'} = $data['size'];
				}
				
				if (isset($data['rating'])) {
					$this->parameters()->{'rating'} = $data['rating'];
				}
			}
		}
		
		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
			$page = $wrapper->ownerDocument;
			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);
			
			$fieldset = Widget::Fieldset(__('Essentials'));
			
			// Name:
			$label = Widget::Label(__('Name'));
			$input = Widget::Input('fields[about][name]', General::sanitize($this->about()->name));
			$label->appendChild($input);
			
			if (isset($errors->{'about::name'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'about::name'});
			}
			
			$fieldset->appendChild($label);
			$left->appendChild($fieldset);
			
			$fieldset = Widget::Fieldset(__('Gravatar'), '<code>{$param}</code> or <code>Value</code>');
			
			$label = Widget::Label(__('Email Addresses'));
			$input = Widget::Input('fields[addresses]', $this->parameters()->{'addresses'});
			$label->appendChild($input);
			
			if (isset($errors->{'addresses'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'addresses'});
			}
			
			$help = $page->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Enter email addresses separated by commas.'));
			
			$fieldset->appendChild($label);
			$fieldset->appendChild($help);
			
			$label = Widget::Label(__('Default Image'));
			$input = Widget::Input('fields[default]', $this->parameters()->{'default'});
			$label->appendChild($input);
			
			if (isset($errors->{'default'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'default'});
			}
			
			$help = $page->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('An image to use as a fallback, either identicon, monsterid, wavatar, 404 or a URL to your own image.'));
			
			$fieldset->appendChild($label);
			$fieldset->appendChild($help);
			
			$size = $page->createElement('div');
			$label = Widget::Label(__('Size'));
			$input = Widget::Input('fields[size]', $this->parameters()->{'size'});
			$label->appendChild($input);
			
			if (isset($errors->{'size'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'size'});
			}
			
			$help = $page->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Images sized from 1 to 512 pixels. Defaults to 80 pixels.'));
			
			$size->appendChild($label);
			$size->appendChild($help);
			
			$rating = $page->createElement('div');
			$label = Widget::Label(__('Rating'));
			$input = Widget::Input('fields[rating]', $this->parameters()->{'rating'});
			$label->appendChild($input);
			
			if (isset($errors->{'rating'})) {
				$label = Widget::wrapFormElementWithError($label, $errors->{'rating'});
			}
			
			$help = $page->createElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Specify a content rating of g, pg, r, or x. Defaults to g.'));
			
			$rating->appendChild($label);
			$rating->appendChild($help);
			
			$fieldset->appendChild(Widget::Group(
				$size, $rating
			));
			
			$right->appendChild($fieldset);
			$layout->appendTo($wrapper);
		}
		
		public function save(MessageStack $errors) {
			$xsl_errors = new MessageStack;
			
			if (strlen(trim($this->parameters()->{'addresses'})) == 0){
				$errors->append('addresses', __('This is a required field'));
			}
			
			return parent::save($errors);
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function render(Register $parameter_output) {
			$document = new XMLDocument;
			$root = $document->createElement(
				$this->parameters()->{'root-element'}
			);
			$addresses = DataSource::replaceParametersInString(
				trim($this->parameters()->{'addresses'}), $parameter_output
			);
			$addresses = preg_split('%,\s*%', $addresses);
			$params = array(
				's'	=> $this->parameters()->{'size'},
				'r'	=> $this->parameters()->{'rating'},
				'd'	=> $this->parameters()->{'default'}
			);
			
			foreach ($params as $key => $value) {
				$value = DataSource::replaceParametersInString($value, $parameter_output);
				
				if ($key == 's') $value = (integer)$value;
				
				$params[$key] = $value;
			}
			
			if ($params['s'] < 1 or $params['s'] > 512) {
				unset($params['s']);
			}
			
			if (!in_array($params['r'], array('g', 'pg', 'r', 'x'))) {
				unset($params['r']);
			}
			
			if (is_null($params['d'])) {
				unset($params['d']);
			}
			
			foreach ($addresses as $address) {
				$address = trim($address);
				
				if (empty($address)) continue;
				
				$hash = md5($address);
				$url = new URLWriter('http://www.gravatar.com/avatar/' . $hash, $params);
				
				$element = $document->createElement('avatar');
				$element->setAttribute('email', $address);
				$element->setAttribute('url', (string)$url);
				$root->appendChild($element);
			}
			
			$document->appendChild($root);
			
			return $document;
		}
	}
