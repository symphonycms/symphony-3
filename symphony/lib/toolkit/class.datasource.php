<?php
/*
	Class DatasourceException extends Exception {}

	Class DatasourceFilterIterator extends FilterIterator{
		public function __construct(){
			parent::__construct(new DirectoryIterator(DATASOURCES));
		}

		public function accept(){
			if($this->isDir() == false && preg_match('/\.php$/i', $this->getFilename())){
				return true;
			}
			return false;
		}
	}

	Class DatasourceIterator implements Iterator{

		private $_iterator;
		private $_length;
		private $_position;

		public function __construct($path=NULL, $recurse=true){
			$this->_iterator = new DatasourceFilterIterator;
			$this->_length = $this->_position = 0;
			foreach($this->_iterator as $f){
				$this->_length++;
			}
			$this->_iterator->getInnerIterator()->rewind();
		}

		public function current(){
			return Datasource::loadFromPath($this->_iterator->current()->getPathname());
		}

		public function innerIterator(){
			return $this->_iterator;
		}

		public function next(){
			$this->_position++;
			$this->_iterator->next();
		}

		public function key(){
			return $this->_iterator->key();
		}

		public function valid(){
			return $this->_iterator->valid();
		}

		public function rewind(){
			$this->_position = 0;
			$this->_iterator->rewind();
		}

		public function position(){
			return $this->_position;
		}

		public function length(){
			return $this->_length;
		}

	}
*/


	Class DataSourceException extends Exception {}

	Class DataSourceFilterIterator extends FilterIterator{
		public function __construct($path){
			parent::__construct(new DirectoryIterator($path));
		}

		public function accept(){
			if($this->isDir() == false && preg_match('/^.+\.php$/i', $this->getFilename())){
				return true;
			}
			return false;
		}
	}

	Class DataSourceIterator implements Iterator{

		private $position;
		private $datasources;

		public function __construct(){

			$this->datasources = array();
			$this->position = 0;

			foreach(new DataSourceFilterIterator(DATASOURCES) as $file){
				$this->datasources[] = $file->getPathname();
			}

			foreach(new DirectoryIterator(EXTENSIONS) as $dir){
				if(!$dir->isDir() || $dir->isDot() || !is_dir($dir->getPathname() . '/data-sources')) continue;

				foreach(new DataSourceFilterIterator($dir->getPathname() . '/data-sources') as $file){
					$this->datasources[] = $file->getPathname();
				}
			}

		}

		public function length(){
			return count($this->datasources);
		}

		public function rewind(){
			$this->position = 0;
		}

		public function current(){
			return $this->datasources[$this->position]; //Datasource::loadFromPath($this->datasources[$this->position]);
		}

		public function key(){
			return $this->position;
		}

		public function next(){
			++$this->position;
		}

		public function valid(){
			return isset($this->datasources[$this->position]);
		}
	}



	##Interface for datasouce objects
	Abstract Class DataSource{

		const FILTER_AND = 1;
		const FILTER_OR = 2;

		protected $_about;
		protected $_parameters;

		//protected $_env; // DELETE?
		//protected $_Parent; // DELETE?
		protected $_param_output_only; // DELETE?
		protected $_dependencies;
		protected $_force_empty_result; // DELETE?

		protected static $_loaded;

		// Abstract function
		abstract public function render(Register &$ParameterOutput);

		public static function getHandleFromFilename($filename){
			return preg_replace('/.php$/i', NULL, $filename);
		}

		public function &about(){
			return $this->_about;
		}

		public function &parameters(){
			return $this->_parameters;
		}

		public static function load($pathname){
			if(!is_array(self::$_loaded)){
				self::$_loaded = array();
			}

			if(!is_file($pathname)){
		        throw new DataSourceException(
					__('Could not find Data Source <code>%s</code>. If the Data Source was provided by an Extension, ensure that it is installed, and enabled.', array(basename($pathname)))
				);
			}

			if(!isset(self::$_loaded[$pathname])){
				self::$_loaded[$pathname] = require($pathname);
			}

			$obj = new self::$_loaded[$pathname];
			$obj->parameters()->pathname = $pathname;

			return $obj;

		}

		public static function loadFromHandle($name){
			return self::load(self::__find($name) . "/{$name}.php");
		}

		protected static function __find($name){

		    if(is_file(DATASOURCES . "/{$name}.php")) return DATASOURCES;
		    else{

				$extensions = ExtensionManager::instance()->listInstalledHandles();

				if(is_array($extensions) && !empty($extensions)){
					foreach($extensions as $e){
						if(is_file(EXTENSIONS . "/{$e}/data-sources/{$name}.php")) return EXTENSIONS . "/{$e}/data-sources";
					}
				}
	    	}

		    return false;
	    }

		protected static function __getHandleFromFilename($filename){
			return preg_replace(array('/^data./i', '/.php$/i'), '', $filename);
		}

        protected static function __getClassName($name){
	        return 'datasource' . $name;
        }

        protected static function __getClassPath($name){
	        return self::__find($name);
        }

        protected static function __getDriverPath($name){
	        return self::__getClassPath($name) . "/data.{$name}.php";
        }

		## This function is required in order to edit it in the data source editor page.
		## Overload this function, and return false if you do not want the Data Source editor
		## loading your Data Source
		public function allowEditorToParse(){
			return true;
		}

		## This function is required in order to identify what type of data source this is for
		## use in the data source editor. It must remain intact. Do not overload this function in
		## custom data sources.
		public function getSource(){
			return NULL;
		}

		public function type(){
			return NULL;
		}

		public function template(){
			return NULL;
		}

		public function __get($name){
			if($name == 'handle'){
				return Lang::createFilename($this->about()->name);
			}
		}

		public function save(MessageStack &$errors){
			$editing = (isset($this->parameters()->{'root-element'}))
						? $this->parameters()->{'root-element'}
						: false;

			// About info:
			if (!isset($this->about()->name) || empty($this->about()->name)) {
				$errors->append('about::name', __('This is a required field'));
			}

			try {
				$existing = self::loadFromHandle($this->handle);
			}
			catch (DataSourceException $e) {
				//	Datasource not found, continue!
			}

			if($existing instanceof Datasource && $editing != $this->handle) {
				throw new DataSourceException(__('A Datasource with the name <code>%s</code> already exists', array($this->about()->name)));
			}

			// Save type:
			if ($errors->length() <= 0) {
				$user = Administration::instance()->User;

				if (!file_exists($this->template())) {
					$errors->append('write', __("Unable to find Data Source Type template '%s'.", array($this->template())));
					throw new DataSourceException(__("Unable to find Data Source Type template '%s'.", array($this->template())));
				}

				$this->parameters()->{'root-element'} = $this->handle;
				$classname = Lang::createHandle(ucwords($this->about()->name), '_', false, true, array('/[^a-zA-Z0-9_\x7f-\xff]/' => NULL), true);
				$pathname = DATASOURCES . "/" . $this->handle . ".php";

				$data = array(
					$classname,
					// About info:
					var_export($this->about()->name, true),
					var_export($user->getFullName(), true),
					var_export(URL, true),
					var_export($user->get('email'), true),
					var_export('1.0', true),
					var_export(DateTimeObj::getGMT('c'), true),
				);

				foreach ($this->parameters() as $value) {
					$data[] = trim(General::var_export($value, true, (is_array($value) ? 5 : 0)));
				}

				if(General::writeFile(
					$pathname,
					vsprintf(file_get_contents($this->template()), $data),
					Symphony::Configuration()->core()->symphony->{'file-write-mode'}
				)){
					if($editing != $this->handle) General::deleteFile(DATASOURCES . '/' . $editing . '.php');

					return $pathname;
				}

				$errors->append('write', __('Failed to write datasource "%s" to disk.', array($filename)));
			}

			throw new DataSourceException('Errors were encountered whilst attempting to save.');
		}

		public function delete($handle){
			/*
				TODO:
				Upon deletion of the event, views need to be updated to remove
				it's associated with the event
			*/
			$datasource = Datasource::loadFromHandle($handle);

			if(!$datasource->allowEditorToParse()) {
				throw new DataSourceException(__('Datasource cannot be deleted, the Editor does not have permission.'));
			}

			return General::deleteFile(DATASOURCES . "/{$handle}.php");
		}


		public function getDependencies(){
			return $this->_dependencies;
		}
/*
		public function __construct($env=NULL, $process_params=true){
			$this->_Parent = Symphony::Parent();
			$this->_force_empty_result = false;
			$this->_dependencies = array();

			if(isset($this->dsParamPARAMOUTPUT) && !is_array($this->dsParamPARAMOUTPUT)){
				$this->dsParamPARAMOUTPUT = array($this->dsParamPARAMOUTPUT);
			}

			if($process_params){
				$this->processParameters($env);
			}
		}

		public function processParameters($env=NULL){

			if($env) $this->_env = $env;

			if((isset($this->_env) && is_array($this->_env)) && is_array($this->dsParamFILTERS) && !empty($this->dsParamFILTERS)){
				foreach($this->dsParamFILTERS as $key => $value){
					$value = stripslashes($value);
					$new_value = $this->__processParametersInString($value, $this->_env);

					if(strlen(trim($new_value)) == 0) unset($this->dsParamFILTERS[$key]);
					else $this->dsParamFILTERS[$key] = $new_value;

				}
			}

			if(isset($this->dsParamORDER)) $this->dsParamORDER = $this->__processParametersInString($this->dsParamORDER, $this->_env);

			if(isset($this->dsParamSORT)) $this->dsParamSORT = $this->__processParametersInString($this->dsParamSORT, $this->_env);

			if(isset($this->dsParamSTARTPAGE)) {
				$this->dsParamSTARTPAGE = $this->__processParametersInString($this->dsParamSTARTPAGE, $this->_env);
				if ($this->dsParamSTARTPAGE == '') $this->dsParamSTARTPAGE = '1';
			}

			if(isset($this->dsParamLIMIT)) $this->dsParamLIMIT = $this->__processParametersInString($this->dsParamLIMIT, $this->_env);

			if(isset($this->dsParamREQUIREDPARAM) && $this->__processParametersInString($this->dsParamREQUIREDPARAM, $this->_env, false) == '') {
				$this->_force_empty_result = true; // don't output any XML
				$this->dsParamPARAMOUTPUT = NULL; // don't output any parameters
				$this->dsParamINCLUDEDELEMENTS = NULL; // don't query any fields in this section
			}

			$this->_param_output_only = ((!is_array($this->dsParamINCLUDEDELEMENTS) || empty($this->dsParamINCLUDEDELEMENTS)) && !isset($this->dsParamGROUP));

			if($this->dsParamREDIRECTONEMPTY == 'yes' && $this->_force_empty_result){
				throw new FrontendPageNotFoundException;
			}

		}

		// THIS FUNCTION WILL BE REMOVED IN THE NEXT
		// VERSION, PLEASE THROW AN EXCEPTION INSTEAD
		protected function __redirectToErrorPage(){
			throw new FrontendPageNotFoundException;
		}
*/
		public function emptyXMLSet(DOMElement $root){
			if(is_null($root)) {
				throw new DataSourceException('No valid DOMDocument present');
			}
			else {
				$root->appendChild(
					$root->ownerDocument->createElement('error', __('No Record found.'))
				);
			}
		}

		protected function __appendIncludedElements(&$wrapper, $fields){
			if(!isset($this->parameters()->{'included-elements'}) || !is_array($this->parameters()->{'included-elements'}) || empty($this->parameters()->{'included-elements'})) return;

			foreach($this->parameters()->{'included-elements'} as $index) {

				if(!is_object($fields[$index])){
					trigger_error(__('%s is not a valid object. Failed to append to XML.', array($index)), E_USER_WARNING);
					continue;
				}
				$wrapper->appendChild($fields[$index]);
			}
		}

		protected function __determineFilterType($value){
			return (false === strpos($value, '+') ? Datasource::FILTER_OR : Datasource::FILTER_AND);
		}
/*
		protected function __noRecordsFound(){
			return Symphony::Parent()->Page->createElement('error', __('No records found.'));
		}

		//	TODO: Rewrite __processParametersInString.
		protected function __processParametersInString($value, $env, $includeParenthesis=true, $escape=false){
			if(trim($value) == '') return NULL;

			if(!$includeParenthesis) $value = '{'.$value.'}';

			if(preg_match_all('@{([^}]+)}@i', $value, $matches, PREG_SET_ORDER)){

				foreach($matches as $match){

					list($source, $cleaned) = $match;

					$replacement = NULL;

					$bits = preg_split('/:/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

					foreach($bits as $param){

						if($param{0} != '$'){
							$replacement = $param;
							break;
						}

						$param = trim($param, '$');

						$replacement = $this->__findParameterInEnv($param, $env);

						if(is_array($replacement)){
							$replacement = array_map(array('Datasource', 'escapeCommas'), $replacement);
							if(count($replacement) > 1) $replacement = implode(',', $replacement);
							else $replacement = end($replacement);
						}

						if(!empty($replacement)) break;

					}

					if($escape == true) $replacement = urlencode($replacement);
					$value = str_replace($source, $replacement, $value);

				}
			}

			return $value;
		}
*/

		/*
		**	Given a string that may contain params in form of {$param}
		**	resolve the tokens with their values
		**
		**	This checks both the Frontend Parameters and Datasource
		**	Registers.
		*/
		public function replaceStringWithParameter($string, Register $DataSourceParameterOutput = null) {
			if(trim($string) == '') return null;

			if(preg_match_all('@{([^}]+)}@i', $string, $matches, PREG_SET_ORDER)){
				foreach($matches as $match){
					list($source, $cleaned) = $match;

					$replacement = NULL;

					$bits = preg_split('/:/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

					foreach($bits as $param) {
						if($param{0} != '$') {
							$replacement = $param;
							break;
						}

						$param = trim($param, '$');

						$replacement = $this->resolveParameter($param, $DataSourceParameterOutput);

						if(is_array($replacement)){
							$replacement = array_map(array('Datasource', 'escapeCommas'), $replacement);
							if(count($replacement) > 1) $replacement = implode(',', $replacement);
							else $replacement = end($replacement);
						}

						if(!is_null($replacement)) break;
					}

					$string = str_replace($source, $replacement, $string);
				}
			}

			return $string;
		}

		public static function resolveParameter($param, Register $DataSourceParameterOutput = null) {
			//	TODO: Allow resolveParamter to check the stack, ie: $ds-blog-tag:$ds-blog-id
			$param = trim($param, '$');

			if(isset(Frontend::Parameters()->{$param})) return Frontend::Parameters()->{$param}->value;

			if(isset($DataSourceParameterOutput->{$param})) return $DataSourceParameterOutput->{$param}->value;

			return null;
		}


		public static function escapeCommas($string){
			return preg_replace('/(?<!\\\\),/', "\\,", $string);
		}

		public static function removeEscapedCommas($string){
			return preg_replace('/(?<!\\\\)\\\\,/', ',', $string);
		}
/*
		protected function __findParameterInEnv($needle, $env){

			if(isset($env['env']['url'][$needle])) return $env['env']['url'][$needle];

			if(isset($env['env']['pool'][$needle])) return $env['env']['pool'][$needle];

			if(isset($env['param'][$needle])) return $env['param'][$needle];

			return NULL;

		}
*/
	}

