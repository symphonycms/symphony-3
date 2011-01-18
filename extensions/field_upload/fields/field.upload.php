<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	class FieldUpload extends Field {
		protected $_mimes = array();
		protected $Symphony = null;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct() {
			parent::__construct();

			$this->_name = 'Upload';
			$this->_mimes = array(
				'image'	=> array(
					'image/bmp',
					'image/gif',
					'image/jpg',
					'image/jpeg',
					'image/png'
				),
				'text'	=> array(
					'text/plain',
					'text/html'
				)
			);
		}

		public function create(){
			return Symphony::Database()->query(sprintf('
				CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`entry_id` int(11) unsigned NOT NULL,
					`name` text DEFAULT NULL,
					`path` text DEFAULT NULL,
					`file` text DEFAULT NULL,
					`size` int(11) unsigned DEFAULT NULL,
					`type` varchar(255) DEFAULT NULL,
					`meta` text DEFAULT NULL,
					PRIMARY KEY (`id`),
					UNIQUE KEY `entry_id` (`entry_id`),
					FULLTEXT KEY `name` (`name`),
					FULLTEXT KEY `path` (`path`),
					FULLTEXT KEY `file` (`file`)
				) ENGINE=MyISAM;',
				$this->section,
				$this->{'element-name'}
			));
		}

		public function allowDatasourceParamOutput() {
			return true;
		}

		public function canFilter() {
			return true;
		}

		public function canImport() {
			return true;
		}

		public function isSortable() {
			return true;
		}

		public function getExampleFormMarkup() {
			$handle = $this->{'element-name'};

			$label = Widget::Label($this->{'publish-label'});
			$label->appendChild(Widget::Input('fields[{$handle}]', null, 'file'));

			return $label;
		}

		public function sanitizeDataArray(&$data) {
			if (!isset($data->file) or $data->file == '') return false;

			if (!isset($data->name) or $data->name == '') {
				$data->name = basename($data->file);
			}

			if (!isset($data->size) or $data->size == '') {
				$data->size = 0;
			}

			if (!isset($data->mimetype) or $data->mimetype == '') {
				$data->mimetype = 'application/octet-stream';
			}

			return true;
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function validateSettings(MessageStack $messages, $checkForDuplicates = true) {
			if (!is_writable(DOCROOT . $this->{'destination'} . '/')) {
				$messages->append('destination', __('Folder is not writable. Please check permissions.'));
			}

			return parent::validateSettings($messages, $checkForDuplicates);
		}

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
			parent::displaySettingsPanel($wrapper, $messages);

			$order = $this->sortorder;

		// Destination --------------------------------------------------------

			$ignore = array(
				'events',
				'data-sources',
				'text-formatters',
				'pages',
				'utilities'
			);
			$directories = General::listDirStructure(WORKSPACE, true, 'asc', DOCROOT, $ignore);

			$label = Widget::Label('Destination Directory');

			$options = array(
				array('/workspace', false, '/workspace')
			);

			if (!empty($directories) and is_array($directories)) {
				foreach ($directories as $d) {
					$d = '/' . trim($d, '/');

					if (!in_array($d, $ignore)) {
						$options[] = array($d, ($this->destination == $d), $d);
					}
				}
			}

			$label->appendChild(Widget::Select('destination', $options));

			if ($messages->{'destination'}) {
				$label = Widget::wrapFormElementWithError($label, $messages->{'destination'});
			}

			$wrapper->appendChild($label);

		// Validator ----------------------------------------------------------

			$this->appendValidationSelect($wrapper, $this->validator, 'validator', __('Validation Rule'), 'upload');

			$options_list = $wrapper->ownerDocument->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

		// Serialise ----------------------------------------------------------

			$label = Widget::Label(__('Serialise file names'));
			$input = Widget::Input(
				'serialise', 'yes', 'checkbox'
			);

			if ($this->serialise == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$options_list->appendChild($label);

			$wrapper->appendChild($options_list);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null) {
			/*if (!$errors->valid() and !is_writable(DOCROOT . $this->destination . '/')) {
				$errors->append(
					null, (object)array(
					 	'message' => __(
					 		'Destination folder, "%s", is not writable. Please check permissions.',
					 		array(trim($this->destination, '/'))
					 	),
						'code' => self::ERROR_INVALID
					)
				);
			}*/

			$driver = Extension::load('field_upload');
			$driver->addHeaders();

			$handle = $this->{'element-name'};
			$document = $wrapper->ownerDocument;
			$filepath = null;

			if (isset($data->path, $data->file)) {
				$filepath = DOCROOT . '/' . trim($data->path, '/') . '/' . $data->file;
			}

		// Preview ------------------------------------------------------------

			$label = $document->createElement('div',
				(isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0
					? $this->{'publish-label'}
					: $this->name)
			);
			$label->setAttribute('class', 'label');

			if ($this->required != 'yes') {
				$label->appendChild($document->createElement('em', 'Optional'));
			}

			if (!$errors->valid() and $data->file) {
				$file = $document->createElement('div');
				$file->setAttribute('class', 'file');
				$path = substr($filepath, strlen(DOCROOT));

				###
				# Delegate: UploadField_PreviewFile
				# Description: Allow other extensions to add media previews.
				Extension::notify(
					'UploadField_PreviewFile',
					'/publish/', array(
						'data'		=> $data,
						'field'		=> $this,
						'entry'		=> $entry,
						'wrapper'	=> $wrapper
					)
				);

				if (!is_file($filepath)) {
					$errors->append(
						null, (object)array(
						 	'message' => __('Destination file could not be found.'),
							'code' => self::ERROR_MISSING
						)
					);
				}

				$name = $document->createElement('p');
				$link = Widget::Anchor($data->{'name'}, URL . $path);
				$name->appendChild($link);
				$file->appendChild($name);

				$list = $document->createElement('dl');

				$list->appendChild($document->createElement('dt', __('Size:')));
				$list->appendChild($document->createElement('dd', General::formatFilesize($data->size)));

				$list->appendChild($document->createElement('dt', __('Type:')));
				$list->appendChild($document->createElement('dd', $data->type));

				// Meta data:
				if ($meta = unserialize($data->meta) and is_array($meta)) {
					$meta = (object)$meta;
				}

				if (isset($meta->width, $meta->height)) {
					$list->appendChild($document->createElement('dt', __('Width:')));
					$list->appendChild($document->createElement('dd', sprintf(
						'%dpx', $meta->width
					)));
					$list->appendChild($document->createElement('dt', __('Height:')));
					$list->appendChild($document->createElement('dd', sprintf(
						'%dpx', $meta->height
					)));
				}

				$file->appendChild($list);
				$label->appendChild($file);
			}

		// Upload -------------------------------------------------------------

			$upload = $document->createElement('div');
			$upload->setAttribute('class', 'upload');

			if(!is_writable(DOCROOT . $this->destination . '/')){
				$upload->setValue(__(
			 		'Destination folder, "%s", is not writable. Please check permissions.',
			 		array(trim($this->destination, '/'))
			 	));
			}
			else{
				$input = Widget::Input(
					"fields[{$handle}]", $filepath,
					($filepath ? 'hidden' : 'file')
				);

				$upload->appendChild($input);
			}

			$label->appendChild($upload);

			if($errors->valid()){
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		protected function getHashedFilename($filename) {
			preg_match('/(.*?)(\.[^\.]+)$/', $filename, $meta);

			$filename = sprintf(
				'%s-%s%s',
				Lang::createHandle($meta[1]),
				dechex(time()), $meta[2]
			);

			return $filename;
		}

		protected function getMetaInformation(Entry $entry, STDClass $data, $file) {

			$meta = array(
				'type'		=> 'application/octet-stream'
			);

			// Find best type:
			if (isset($data->type) and $data->type) {
				$meta['type'] = $data->type;
			}

			if(file_exists($file)) {

				// Get image meta information:
				if (strlen(trim($file)) > 0 && $basic = getimagesize($file)) {
					$meta['type'] = $basic['mime'];
					$meta['dimension'] = array(
						'width'		=> $basic[0],
						'height'	=> $basic[1]
					);
					$meta['bits-per-channel'] = (
						isset($basic['bits'])
						? $basic['bits'] : null
					);
				}

				$meta['creation'] = DateTimeObj::get('c', filemtime($file));
			}

			###
			# Delegate: UploadField_AppendMetaInformation
			# Description: Allow other extensions to add media previews.
			Extension::notify(
				'UploadField_AppendMetaInformation',
				'/publish/', array(
					'data'		=> $data,
					'entry'		=> $entry,
					'field'		=> $this,
					'file'		=> $file,
					'meta'		=> &$meta
				)
			);

			return $meta;
		}

		public function processData($data, Entry $entry = null) {
			$result = (object)array();
			$existing = null;

			if (isset($entry->data()->{$this->{'element-name'}})) {
				$existing = $entry->data()->{$this->{'element-name'}};
				$existing->meta = unserialize($existing->meta);
			}

			// Recieving file:
			if (is_array($data)) {
				$result = (object)$data;

				if (isset($result->error)) switch ($result->error) {
					case UPLOAD_ERR_NO_FILE:
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
					case UPLOAD_ERR_PARTIAL:
					case UPLOAD_ERR_NO_TMP_DIR:
					case UPLOAD_ERR_CANT_WRITE:
					case UPLOAD_ERR_EXTENSION:
						return $result;
						break;
				}

				// Accept a new file:
				if (isset($result->name) and trim($result->name) != '') {
					$result = (object)$data;
					$result->path = '/' . trim($this->destination, '/');
					$result->file = $result->name;

					if ($this->serialise == 'yes') {
						$result->file = $this->getHashedFilename($result->file);
					}
				}
			}

			// Filename given, check if it is existing:
			else if (is_string($data)) {
				$existing_file = null;

				if (isset($existing->file, $existing->path)) {
					$existing_file = DOCROOT . '/' . trim($existing->path, '/') . '/' . $existing->file;
				}

				// Existing data:
				if ($existing_file === $data) {
					$result = $existing;
				}

				// Find new data:
				else if (file_exists($data)) {
					$result->name = basename($data);
					$result->path = substr(dirname($data), strlen(DOCROOT));
					$result->file = basename($data);
					$result->size = filesize($data);
					$result->existing = $data;
				}
			}

			// No data given, use existing:
			else if (isset($existing->file, $existing->path)) {
				$result = $existing;
			}

			// Force correct ID to be used:
			if (isset($existing->id)) {
				$result->id = $existing->id;
			}

			// Track existing file:
			if (isset($existing->file, $existing->path)) {
				$result->existing = DOCROOT . '/' . $existing->path . '/' . $existing->file;
			}

			// At least have a null existing file:
			if (!isset($result->existing)) {
				$result->existing = null;
			}

			// Update meta data:
			$result->meta = $this->getMetaInformation(
				$entry, $result,
				(isset($result->tmp_name) ? $result->tmp_name : $result->existing)
			);

			if (isset($result->meta['type']) and !empty($result->meta['type'])) {
				$result->type = $result->meta['type'];
			}

			// Make sure we have a type:
			if (!isset($result->type) or empty($result->type)) {
				$result->type = 'application/octet-stream';
			}

			return $result;
		}

		public function validateData(MessageStack $errors, Entry $entry = null, $data = null) {
			if ($data->error == UPLOAD_ERR_NO_FILE) {
				if ($this->required == 'yes') {
					$errors->append(
						null, (object)array(
						 	'message' => __(
						 		"'%s' is a required field.",
						 		array($this->{'publish-label'})
						 	),
							'code' => self::ERROR_MISSING
						)
					);

					return self::STATUS_ERROR;
				}

				return self::STATUS_OK;
			}

			if (!is_object($data)) return self::STATUS_OK;

			if (!is_writable(DOCROOT . $this->destination . '/')) {
				$errors->append(
					null, (object)array(
					 	'message' => __(
					 		"Destination folder, <code>%s</code>, is not writable. Please check permissions.",
					 		array($this->destination)
					 	),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			if ($data->error != UPLOAD_ERR_NO_FILE and $data->error != UPLOAD_ERR_OK) {
				switch($data->error) {
					case UPLOAD_ERR_INI_SIZE:
						$size = (
							is_numeric(ini_get('upload_max_filesize'))
							? General::formatFilesize(ini_get('upload_max_filesize'))
							: ini_get('upload_max_filesize')
						);
						$errors->append(
							null, (object)array(
							 	'message' => __(
									'File chosen in \'%s\' exceeds the maximum allowed upload size of %s specified by your host.',
									array($this->{'publish-label'}, $size)
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_FORM_SIZE:
						$size = General::formatFilesize(Symphony::Configuration()->core()->symphony->{'maximum-upload-size'});
						$errors->append(
							null, (object)array(
							 	'message' => __(
									'File chosen in \'%s\' exceeds the maximum allowed upload size of %s, specified by Symphony.',
									array($this->{'publish-label'}, $size)
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_PARTIAL:
					case UPLOAD_ERR_NO_TMP_DIR:
						$errors->append(
							null, (object)array(
							 	'message' => __(
									'File chosen in \'%s\' was only partially uploaded due to an error.',
									array($this->{'publish-label'})
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_CANT_WRITE:
						$errors->append(
							null, (object)array(
							 	'message' => __(
									'Uploading \'%s\' failed. Could not write temporary file to disk.',
									array($this->{'publish-label'})
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_EXTENSION:
						$errors->append(
							null, (object)array(
							 	'message' => __(
									'Uploading \'%s\' failed. File upload stopped by extension.',
									array($this->{'publish-label'})
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;
				}

				return self::STATUS_ERROR;
			}

			if ($this->validator != null) {
				$rule = $this->validator;

				if (!General::validateString($data->file, $rule)) {
					$errors->append(
						null, (object)array(
						 	'message' => __(
								'File chosen in \'%s\' does not match allowable file types for that field.',
								array($this->{'publish-label'})
						 	),
							'code' => self::ERROR_INVALID
						)
					);

					return self::STATUS_ERROR;
				}
			}

			$file = DOCROOT . '/' . $data->path . '/' . $data->file;

			// Make sure we don't upload over the top of a pervious file:
			if (isset($data->tmp_name) and $data->existing != $file and file_exists($file)) {
				$errors->append(
					null, (object)array(
					 	'message' => __(
							'A file with the name %s already exists in %s. Please rename the file first, or choose another.',
							array($data->name, trim($this->destination, '/'))
					 	),
						'code' => self::ERROR_INVALID
					)
				);

				return self::ERROR_INVALID;
			}

			return self::STATUS_OK;
		}

		public function saveData(MessageStack $errors, Entry $entry, $data = null) {
			$permissions = Symphony::Configuration()->core()->symphony->{'file-write-mode'};
			$data->entry_id = $entry->id;

			###
			# Delegate: UploadField_PreUploadFile
			# Description: Allow extensions to manipulate saved data before the file is saved to disk.
			Extension::notify(
				'UploadField_PreUploadFile',
				'/publish/', array(
					'data'	=> $data,
					'field'	=> $this,
					'entry'	=> $entry
				)
			);

			$file = DOCROOT . '/' . $data->path . '/' . $data->file;

			// Upload the file:
			if ($data->tmp_name and $data->error == 0) {
				if (!General::uploadFile(DOCROOT . '/' . $data->path, $data->file, $data->tmp_name, $permissions)) {
					$errors->append(
						null, (object)array(
						 	'message' => __(
								'There was an error while trying to upload the file <code>%s</code> to the target directory <code>%s</code>.',
								array($data->name, trim($data->path, '/'))
						 	),
							'code' => self::ERROR_INVALID
						)
					);

					return self::STATUS_ERROR;
				}

				// Remove file being replaced:
				if (isset($data->existing) and is_file($data->existing)) {
					$this->cleanupData($entry, $data, $data->existing);
				}
			}

			// Remove data that can't be saved:
			unset($data->existing);
			unset($data->error);
			unset($data->tmp_name);

			// Make sure we save null values:
			if (!isset($data->path)) {
				$data->path = null;
			}

			if (!isset($data->file)) {
				$data->file = null;
			}

			###
			# Delegate: UploadField_PostUploadFile
			# Description: Allow extensions to manipulate saved data after the file is saved to disk.
			Extension::notify(
				'UploadField_PostUploadFile',
				'/publish/', array(
					'data'	=> $data,
					'field'	=> $this,
					'entry'	=> $entry
				)
			);

			try {
				$data->meta = serialize($data->meta);

				Symphony::Database()->insert(
					sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'}),
					(array)$data,
					Database::UPDATE_ON_DUPLICATE
				);

				return self::STATUS_OK;
			}

			catch (Exception $e) {
				$errors->append(
					null, (object)array(
					 	'message' => __(
							'There was an error while trying to upload the file <code>%s</code> to the target directory <code>workspace/%s</code>.',
							array($data->name, $path)
					 	),
						'code' => self::ERROR_INVALID
					)
				);
			}

			// Remove uploaded file:
			if (isset($file) and is_file($file)) {
				$this->cleanupData($entry, $data, $file);
			}

			return self::STATUS_ERROR;
		}

		protected function cleanupData($entry, $data, $file) {
			###
			# Delegate: UploadField_PreCleanupFile
			# Description: Allow extensions to manipulate saved data after the file is saved to disk.
			Extension::notify(
				'UploadField_PreCleanupFile',
				'/publish/', array(
					'data'	=> $data,
					'field'	=> $this,
					'entry'	=> $entry
				)
			);

			General::deleteFile($file);

			###
			# Delegate: UploadField_PostCleanupFile
			# Description: Allow extensions to manipulate saved data after the file is saved to disk.
			Extension::notify(
				'UploadField_PostCleanupFile',
				'/publish/', array(
					'data'	=> $data,
					'field'	=> $this,
					'entry'	=> $entry
				)
			);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(SymphonyDOMElement $wrapper, $data, $encode = false, $mode = null, Entry $entry = null) {
			if (!$this->sanitizeDataArray($data)) return null;

			$document = $wrapper->ownerDocument;
			$meta = unserialize($data->meta);

			if (!is_array($meta)) $meta = array();

			$meta['size'] = General::formatFilesize($data->size);
			$meta['type'] = $data->type;

			ksort($meta);

			$field = $document->createElement($this->{'element-name'});
			$field->appendChild($document->createElement('file', $data->name, array(
				'path'		=> trim($data->path, '/'),
				'name'		=> $data->file
			)));

			$element = $document->createElement('meta');

			foreach ($meta as $key => $value) {
				if ($key == 'creation' or $key == 'type') {
					$element->setAttribute($key, $value);
				}

				else if ($key == 'size') {
					$bits = explode(' ', $value);

					if (count($bits) != 2) continue;

					$element->appendChild($document->createElement(
						'size', $bits[0], array(
							'unit'	=> $bits[1]
						)
					));
				}

				else if (is_array($value)) {
					$element->appendChild($document->createElement(
						$key, null, $value
					));
				}

				else {
					$element->appendChild($document->createElement(
						$key, (string)$value
					));
				}
			}

			$field->appendChild($element);

			###
			# Delegate: UploadField_AppendFormattedElement
			# Description: Allow other extensions to add media previews.
			Extension::notify(
				'UploadField_AppendFormattedElement',
				'/publish/', array(
					'data'		=> $data,
					'entry'		=> $entry,
					'field'		=> $this,
					'wrapper'	=> $field
				)
			);

			$wrapper->appendChild($field);
		}

		public function prepareTableValue(StdClass $data, SymphonyDOMElement $link = null) {
			$dummy = (object)array(
				'value'		=> $data->name
			);

			if (!$link) {
				$path = substr($data->path, strlen(DOCROOT));
				$link = Widget::Anchor('', URL . $data->path . '/' . $data->file);
			}

			return parent::prepareTableValue($dummy, $link);
		}

		public function getParameterOutputValue(StdClass $data, Entry $entry = null) {
			return rtrim($data->path, '/') . '/' . $data->file;
		}
	}

	return 'FieldUpload';
