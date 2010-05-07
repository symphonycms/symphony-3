<?php

	Class StaticXMLDataSource extends DataSource {
		public function __construct(){
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
				'xml' => '<?xml version="1.0" encoding="UTF-8"?>'."\n<data>\n\t\n</data>",
				'root-element' => 'static-xml'
			);
		}

		final public function getExtension(){
			return 'ds_staticxml';
		}

		public function getTemplate(){
			return EXTENSIONS . '/ds_staticxml/templates/datasource.php';
		}
		
	/*-----------------------------------------------------------------------*/
		
		public function prepare(array $data = null) {
			if(!is_null($data)){
				if(isset($data['about']['name'])) $this->about()->name = stripslashes($data['about']['name']);
				if(isset($data['xml'])) $this->parameters()->{'xml'} = stripslashes($data['xml']);
			}
		}
		
		public function view(SymphonyDOMElement $wrapper, MessageStack $errors) {
		
			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$right = $layout->createColumn(Layout::LARGE);
			
			//	Essentials --------------------------------------------------------

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
				
				$fieldset = Widget::Fieldset(__('Content'));
			
				$label = Widget::Label(__('Source XML'));
				$input = Widget::Textarea('fields[xml]', $this->parameters()->{'xml'}, array(
					'rows' => '24',
					'cols' => '50',
					'class' => 'code'
				));
				$label->appendChild($input);
			
				if (isset($errors->{'xml'})) {
					$label = Widget::wrapFormElementWithError($label, $errors->{'xml'});
				}
			
				$fieldset->appendChild($label);
				$right->appendChild($fieldset);
				
				$layout->appendTo($wrapper);
		}

		public function save(MessageStack $errors){
			$xsl_errors = new MessageStack;

			if(strlen(trim($this->parameters()->xml)) == 0){
				$errors->append('xml', __('This is a required field'));
			}

			elseif(!General::validateXML($this->parameters()->xml, $xsl_errors)){

				if(XSLProc::hasErrors()){
					$errors->append('xml', sprintf('XSLT specified is invalid. The following error was returned: "%s near line %s"', $xsl_errors->current()->message, $xsl_errors->current()->line));
				}
				else{
					$errors->append('xml', 'XSLT specified is invalid.');
				}
			}

			return parent::save($errors);
		}
		
	/*-----------------------------------------------------------------------*/

		public function render(Register $ParameterOutput){

			$doc = new XMLDocument;
			$root = $doc->createElement($this->parameters()->{'root-element'});

			try {
				$static = new XMLDocument;
				$node = $static->loadXML($this->parameters()->xml);

				$root->appendChild(
					$doc->importNode($static->documentElement, true)
				);
			}

			catch (FrontendPageNotFoundException $error) {
				FrontendPageNotFoundExceptionHandler::render($error);
			}

			catch (Exception $error) {
				$root->appendChild($doc->createElement(
					'error', General::sanitize($error->getMessage())
				));
			}

			$doc->appendChild($root);

			return $doc;
		}
	}
