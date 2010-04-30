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
				'video'	=> array(
					'video/quicktime'
				),
				'text'	=> array(
					'text/plain',
					'text/html'
				)
			);
		}

		public function create(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						`entry_id` int(11) unsigned NOT NULL,
						`name` text,
						`path` text,
						`file` text,
						`size` int(11) unsigned NOT NULL,
						`type` varchar(255) NOT NULL,
						`meta` varchar(255) DEFAULT NULL,
						PRIMARY KEY (`id`),
						UNIQUE KEY `entry_id` (`entry_id`),
						FULLTEXT KEY `name` (`name`),
						FULLTEXT KEY `path` (`path`),
						FULLTEXT KEY `file` (`file`)
					)',
					$this->section,
					$this->{'element-name'}
				)
			);
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

			$label = Widget::Label($this->label);
			$label->appendChild(Widget::Input('fields[{$handle}]', null, 'file'));

			return $label;
		}

		public function entryDataCleanup($entry_id, $data) {
			$file_location = WORKSPACE . '/' . ltrim($data['file'], '/');

			if (is_file($file_location)) General::deleteFile($file_location);

			parent::entryDataCleanup($entry_id);

			return true;
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
				$messages->append(null, __('Folder is not writable. Please check permissions.'));
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
			//if (!$error and !is_writable(DOCROOT . $this->destination . '/')) {
			//	$error = 'Destination folder, <code>'.$this->destination.'</code>, is not writable. Please check permissions.';
			//}
			
			$driver = ExtensionManager::instance()->create('field_upload');
			$driver->addHeaders();
			
			$handle = $this->{'element-name'};
			$document = $wrapper->ownerDocument;
			$filepath = null;
			
			if (isset($data->path, $data->file)) {
				$filepath = realpath($data->path . '/' . $data->file);
			}

		// Preview ------------------------------------------------------------

			$label = $document->createElement('div', $this->label);
			$label->setAttribute('class', 'label');

			if ($this->required != 'yes') {
				$label->appendChild($document->createElement('i', 'Optional'));
			}
			
			if (!$errors->valid() and $filepath) {
				$file = $document->createElement('div');
				$file->setAttribute('class', 'file');
				$path = substr($filepath, strlen(DOCROOT));
				
				###
				# Delegate: UploadField_PreviewFile
				# Description: Allow other extensions to add media previews.
				ExtensionManager::instance()->notifyMembers(
					'UploadField_PreviewFile',
					'/publish/', array(
						'data'		=> $data,
						'field'		=> $this,
						'entry'		=> $entry,
						'wrapper'	=> $wrapper
					)
				);
				
				//if (!is_file(WORKSPACE . $data->{'file'})) {
				//	$error = __('Destination file could not be found.');
				//}
				
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
			$input = Widget::Input(
				"fields[{$handle}]", $filepath,
				($filepath ? 'hidden' : 'file')
			);
			
			$upload->appendChild($input);
			$label->appendChild($upload);
			
			if ($errors->valid()) {
				$error = $errors->current();
				$label = Widget::wrapFormElementWithError($label, $error['message']);
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
		
		protected function getMimeType($file) {
			if (in_array('image/' . General::getExtension($file), $this->_mimes['image'])) {
				return 'image/' . General::getExtension($file);
			}
			
			return 'application/octet-stream';
		}
		
		protected function getMetaInfo($file, $type) {
			// TODO: Remove @
			
			$meta = array(
				'creation'	=> DateTimeObj::get('c', @filemtime($file))
			);

			if (in_array($type, $this->_mimes['image'])) {
				if (!$data = @getimagesize($file)) return $meta;

				$meta['width']	= $data[0];
				$meta['height']   = $data[1];
				$meta['type']	 = $data[2];
				$meta['channels'] = (isset($data['channels']) ? $data['channels'] : null);
			}

			return $meta;
		}
		
		public function processFormData($data, Entry $entry = null) {
			$result = (object)array();
			$existing = null;
			
			if (isset($entry->data()->{$this->{'element-name'}})) {
				$existing = $entry->data()->{$this->{'element-name'}};
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
					$result->path = DOCROOT . '/' . trim($this->destination, '/');
					$result->meta = $this->getMetaInfo($result->tmp_name, $result->type);
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
					$existing_file = $existing->path . '/' . $existing->file;
				}
				
				// Existing data:
				if ($existing_file === $data) {
					$result = $existing;
				}
				
				// Find new data:
				else if (file_exists($data)) {
					$result->name = basename($data);
					$result->path = dirname($data);
					$result->file = basename($data);
					$result->size = filesize($data);
					$result->type = $this->getMimeType($data);
					$result->meta = $this->getMetaInfo($data, $result->type);
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
				$result->existing = $existing->path . '/' . $existing->file;
			}
			
			// Make sure meta data is serialized:
			if (isset($result->meta) and is_array($result->meta)) {
				$result->meta = serialize($result->meta);
			}
			
			// At least have a null existing file:
			if (!isset($result->existing)) {
				$result->existing = null;
			}
			
			return $result;
		}
		
		public function validateData(MessageStack $errors, Entry $entry = null, $data = null) {
			if (empty($data) or $data->error == UPLOAD_ERR_NO_FILE) {
				if ($this->required == 'yes') {
					$errors->append(
						null, array(
						 	'message' => __(
						 		"'%s' is a required field.",
						 		array($this->label)
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
					null, array(
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
							null, array(
							 	'message' => __(
									'File chosen in \'%s\' exceeds the maximum allowed upload size of %s specified by your host.',
									array($this->label, $size)
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_FORM_SIZE:
						$size = General::formatFilesize(Symphony::Configuration()->core()->symphony->{'maximum-upload-size'});
						$errors->append(
							null, array(
							 	'message' => __(
									'File chosen in \'%s\' exceeds the maximum allowed upload size of %s, specified by Symphony.',
									array($this->label, $size)
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_PARTIAL:
					case UPLOAD_ERR_NO_TMP_DIR:
						$errors->append(
							null, array(
							 	'message' => __(
									'File chosen in \'%s\' was only partially uploaded due to an error.',
									array($this->label)
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_CANT_WRITE:
						$errors->append(
							null, array(
							 	'message' => __(
									'Uploading \'%s\' failed. Could not write temporary file to disk.',
									array($this->label)
							 	),
								'code' => self::ERROR_INVALID
							)
						);
						break;

					case UPLOAD_ERR_EXTENSION:
						$errors->append(
							null, array(
							 	'message' => __(
									'Uploading \'%s\' failed. File upload stopped by extension.',
									array($this->label)
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
				
				if (!General::validateString($data->name, $rule)) {
					$errors->append(
						null, array(
						 	'message' => __(
								'File chosen in \'%s\' does not match allowable file types for that field.',
								array($this->label)
						 	),
							'code' => self::ERROR_INVALID
						)
					);
					
					return self::STATUS_ERROR;
				}
			}
			
			$file = $data->path . '/' . $data->file;
			
			if ($data->existing != $file and file_exists($file)) {
				$errors->append(
					null, array(
					 	'message' => __(
							'A file with the name %s already exists in %s. Please rename the file first, or choose another.',
							array($data->name, $this->destination)
					 	),
						'code' => self::ERROR_INVALID
					)
				);
				
				return self::ERROR_INVALID;
			}
			
			return self::STATUS_OK;
		}
		
		public function saveData(MessageStack $errors, Entry $entry, $data = null) {
			$permissions = Symphony::Configuration()->core()->{'file-write-mode'};
			$data->entry_id = $entry->id;
			
			###
			# Delegate: UploadField_PreUploadFile
			# Description: Allow extensions to manipulate saved data before the file is saved to disk.
			ExtensionManager::instance()->notifyMembers(
				'UploadField_PreUploadFile',
				'/publish/', array(
					'data'	=> $data,
					'field'	=> $this,
					'entry'	=> $entry
				)
			);
			
			$file = $data->path . '/' . $data->file;
			
			// Upload the file:
			if (isset($data->tmp_name)) {
				if (!General::uploadFile($data->path, $data->file, $data->tmp_name, $permissions)) {
					$errors->append(
						null, array(
						 	'message' => __(
								'There was an error while trying to upload the file <code>%s</code> to the target directory <code>workspace/%s</code>.',
								array($data->name, $path)
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
			
			unset($data->existing);
			unset($data->error);
			unset($data->tmp_name);
			
			###
			# Delegate: UploadField_PostUploadFile
			# Description: Allow extensions to manipulate saved data after the file is saved to disk.
			ExtensionManager::instance()->notifyMembers(
				'UploadField_PostUploadFile',
				'/publish/', array(
					'data'	=> $data,
					'field'	=> $this,
					'entry'	=> $entry
				)
			);
			
			try{
				Symphony::Database()->insert(
					sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'}),
					(array)$data,
					Database::UPDATE_ON_DUPLICATE
				);
				
				return self::STATUS_OK;
			}
			
			catch (Exception $e) {
				$errors->append(
					null, array(
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
			ExtensionManager::instance()->notifyMembers(
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
			ExtensionManager::instance()->notifyMembers(
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

		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			if (!$this->sanitizeDataArray($data)) return null;

			$document = $wrapper->ownerDocument;

			$item = $document->createElement($this->{'element-name'});
			$item->setAttributeArray(array(
				'size'	=> General::formatFilesize($data->size),
				'type'	=> General::sanitize($data->mimetype),
				'name'	=> General::sanitize($data->name)
			));

			$item->appendChild(
				$document->createElement('path', str_replace(WORKSPACE, NULL, dirname(WORKSPACE . $data->file)))
			);
			$item->appendChild(
				$document->createElement('file', General::sanitize(basename($data->file)))
			);

			$meta = unserialize($data->meta);

			if (is_array($meta) and !empty($meta)) {
				$item->appendChild(
					$document->createElement('meta', null, $meta)
				);
			}

			###
			# Delegate: UploadField_AppendFormattedElement
			# Description: Allow other extensions to add media previews.
			ExtensionManager::instance()->notifyMembers(
				'UploadField_AppendFormattedElement',
				'/frontend/', array(
					'data'		=> $data,
					'entry_id'	=> $entry_id,
					'field_id'	=> $this->id,
					'wrapper'	=> $item
				)
			);

			$wrapper->appendChild($item);
		}

		public function prepareTableValue($data, SymphonyDOMElement $link = null) {
			if (!$this->sanitizeDataArray($data)) return null;
			
			if ($link) {
				$link->setValue($data->name);
				
				return $link;
			}
			
			else {
				$path = substr($data->path, strlen(DOCROOT));
				$link = Widget::Anchor($data->name, URL . $path . '/' . $data->file);

				return $link;
			}
		}
	}

	return 'FieldUpload';
