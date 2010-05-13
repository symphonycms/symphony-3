<?php

	require_once(LIB . '/class.administrationpage.php');

	class contentSystemSettings extends AdministrationPage {
		public function __construct(){
			parent::__construct();
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Settings'))));
			/*
			$element = $this->createElement('p');
			$element->appendChild(new DOMEntityReference('ndash'));
			$this->Body->appendChild($element);*/
		}

		## Overload the parent 'view' function since we dont need the switchboard logic
		public function __viewIndex() {
			$this->appendSubheading(__('Settings'));

			$path = URL . '/symphony/system/settings/';
			
			if(Extension::delegateSubscriptionCount('AddSettingsFieldsets', '/system/settings/extensions/') > 0){
			
				$viewoptions = array(
					'Preferences'	=> $path,
					'Extensions'	=> $path . 'extensions/'
				);

				$this->appendViewOptions($viewoptions);
			
			}
			
			if (!is_writable(CONFIG)) {
		        $this->alerts()->append(
					__('The core Symphony configuration file, /manifest/conf/core.xml, is not writable. You will not be able to save any changes.'), AlertStack::ERROR
				);
			}

			// Status message:
			$callback = Administration::instance()->getPageCallback();

			if(isset($callback['flag']) && !is_null($callback['flag'])){

				switch($callback['flag']){

					case 'saved':

						$this->alerts()->append(
							__(
								'System settings saved at %1$s.',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__)
								)
							),
							AlertStack::SUCCESS);

						break;

				}
			}

		// SETUP PAGE
			$layout = new Layout();
			$left = $layout->createColumn(Layout::LARGE);
			$center = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::LARGE);
		
		// SITE SETUP
			$helptext = 'Symphony version: ' .Symphony::Configuration()->core()->symphony->version;
			$fieldset = Widget::Fieldset(__('Site Setup'), $helptext);

			$label = Widget::Label(__('Site Name'));
			$input = Widget::Input('settings[symphony][sitename]', Symphony::Configuration()->core()->symphony->sitename);
			$label->appendChild($input);
			
			if(isset($this->errors->{'symphony::sitename'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{'symphony::sitename'});
			}
			
			$fieldset->appendChild($label);

		    // Get available languages
		    $languages = Lang::getAvailableLanguages(true);

			if(count($languages) > 1) {
			    // Create language selection
				$label = Widget::Label(__('Default Language'));

				// Get language names
				asort($languages);

				foreach($languages as $code => $name) {
					$options[] = array($code, $code == Symphony::Configuration()->core()->symphony->lang, $name);
				}
				$select = Widget::Select('settings[symphony][lang]', $options);
				unset($options);
				$label->appendChild($select);
				//$group->appendChild(new XMLElement('p', __('Users can set individual language preferences in their profiles.'), array('class' => 'help')));
				// Append language selection
				$fieldset->appendChild($label);
			}
			$left->appendChild($fieldset);

		// REGIONAL SETTINGS

			$fieldset = Widget::Fieldset(__('Date & Time Settings'));

			// Date and Time Settings
			$label = Widget::Label(__('Date Format'));
			$input = Widget::Input('settings[region][date-format]', Symphony::Configuration()->core()->region->{'date-format'});
			$label->appendChild($input);
			if(isset($this->errors->{'region::date-format'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{'region::date-format'});
			}
			$fieldset->appendChild($label);

			$label = Widget::Label(__('Time Format'));
			$input = Widget::Input('settings[region][time-format]', Symphony::Configuration()->core()->region->{'time-format'});
			$label->appendChild($input);
			if(isset($this->errors->{'region::time-format'})) {
				$label = Widget::wrapFormElementWithError($label, $this->errors->{'region::time-format'});
			}
			$fieldset->appendChild($label);

			$label = Widget::Label(__('Timezone'));

			$timezones = timezone_identifiers_list();
			foreach($timezones as $timezone) {
				$options[] = array($timezone, $timezone == Symphony::Configuration()->core()->region->timezone, $timezone);
				}
			$select = Widget::Select('settings[region][timezone]', $options);
			unset($options);
			$label->appendChild($select);
			$fieldset->appendChild($label);

			$center->appendChild($fieldset);

		// PERMISSIONS

			$fieldset = Widget::Fieldset(__('Permissions'));

			$permissions = array(
				'0777',
				'0775',
				'0755',
				'0666',
				'0644'
			);

			$fileperms = Symphony::Configuration()->core()->symphony->{'file-write-mode'};
			$dirperms = Symphony::Configuration()->core()->symphony->{'directory-write-mode'};

			$label = Widget::Label(__('File Permissions'));
			foreach($permissions as $p) {
				$options[] = array($p, $p == $fileperms, $p);
			}
			if(!in_array($fileperms, $permissions)){
				$options[] = array($fileperms, true, $fileperms);
			}
			$select = Widget::Select('settings[symphony][file-write-mode]', $options);
			unset($options);
			$label->appendChild($select);
			$fieldset->appendChild($label);

			$label = Widget::Label(__('Directory Permissions'));
			foreach($permissions as $p) {
				$options[] = array($p, $p == $dirperms, $p);
			}
			if(!in_array($dirperms, $permissions)){
				$options[] = array($dirperms, true, $dirperms);
			}
			$select = Widget::Select('settings[symphony][directory-write-mode]', $options);
			unset($options);
			$label->appendChild($select);
			$fieldset->appendChild($label);

			$right->appendChild($fieldset);

			$layout->appendTo($this->Form);

			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');

			$attr = array('accesskey' => 's');
			
			if(!is_writable(CONFIG)) $attr['disabled'] = 'disabled';
			
			$div->appendChild(
				Widget::Submit(
					'action[save]', __('Save Changes'),
					$attr
				)
			);

			$this->Form->appendChild($div);
		}

		public function __viewExtensions() {
			$this->appendSubheading(__('Settings'));
			
			$path = URL . '/symphony/system/settings/';

			if(Extension::delegateSubscriptionCount('AddSettingsFieldsets', '/system/settings/extensions/') <= 0){
				// No settings for extensions here
				redirect($path);
			}
			
			// TODO: Check if there are any extensions that will append their junk before adding tabs
			$viewoptions = array(
				'Preferences'	=> $path,
				'Extensions'	=> $path . 'extensions/'
			);

			$this->appendViewOptions($viewoptions);
			
			/*
			if (!is_writable(CONFIG)) {
		        $this->alerts()->append(
					__('The core Symphony configuration file, /manifest/conf/core.xml, is not writable. You will not be able to save any changes.'), AlertStack::ERROR
				);
			}
			*/
			
			// Status message:
			$callback = Administration::instance()->getPageCallback();

			if(isset($callback['flag']) && !is_null($callback['flag'])){

				switch($callback['flag']){

					case 'saved':

						$this->alerts()->append(
							__(
								'System settings saved at %1$s.',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__)
								)
							),
							AlertStack::SUCCESS);

						break;

				}
			}

			$extension_fieldsets = array();

			###
			# Delegate: AddSettingsFieldsets
			# Description: Add Extension settings fieldsets. Append fieldsets to the array provided. They will be distributed evenly accross the 3 columns
			Extension::notify('AddSettingsFieldsets', '/system/settings/extensions/', array('fieldsets' => &$extension_fieldsets));

			if(empty($extension_fieldsets)) redirect($path);
			
			$layout = new Layout();
			$left = $layout->createColumn(Layout::LARGE);
			$center = $layout->createColumn(Layout::LARGE);
			$right = $layout->createColumn(Layout::LARGE);

			foreach($extension_fieldsets as $index => $fieldset){
				$index += 1;
				if($index % 3 == 0) $right->appendChild($fieldset);
				elseif($index % 2 == 0) $center->appendChild($fieldset);
				else $left->appendChild($fieldset);
			}
			
			$layout->appendTo($this->Form);
			
			$div = $this->createElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Submit(
					'action[save]', __('Save Changes'),
					array(
						'accesskey' => 's'
					)
				)
			);

			$this->Form->appendChild($div);
		}
		
		public function __actionExtensions() {
			###
			# Delegate: CustomSaveActions
			# Description: This is where Extensions can hook on to custom actions they may need to provide.
			Extension::notify('CustomSaveActions', '/system/settings/extensions/');
			
			if (isset($_POST['action']['save']) && isset($_POST['settings'])) {
				$settings = $_POST['settings'];
				
				if ($this->errors->length() <= 0) {

					if(is_array($settings) && !empty($settings)){
						foreach($settings as $set => $values) {
							foreach($values as $key => $val) {
								Symphony::Configuration()->core()->set->$key = $val;
							}
						}
					}

					Symphony::Configuration()->save();

					redirect(ADMIN_URL . '/system/settings/extensions/:saved/');
				}
				else{
					$this->alerts()->append(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), AlertStack::ERROR, $this->errors);
				}
			}
		}

		public function __actionIndex() {
			
			if (!is_writable(CONFIG)) {
				return;
			}

			if (isset($_POST['action']['save'])) {
				$settings = $_POST['settings'];

				###
				# Delegate: Save
				# Description: Saving of system preferences.
				Extension::notify('Save', '/system/settings/', array('settings' => &$settings, 'errors' => &$this->errors));
				
				// Site name
				if(strlen(trim($settings['symphony']['sitename'])) == 0){
					$this->errors->append('symphony::sitename', __("'%s' is a required field.", array('Site Name')));
				}
				
				// Date format
				// TODO: Figure out a way to check date formats to ensure they are valid
				if(strlen(trim($settings['region']['date-format'])) == 0){
					$this->errors->append('region::date-format', __("'%s' is a required field.", array('Date Format')));
				}
				//elseif(!date_parse(DateTimeObj::get($settings['region']['date-format'] . 'H:m:s'))){
				//	$this->errors->append('region::date-format', __("Invalid date format specified."));
				//}
				
				// Time format
				// TODO: Figure out a way to check time formats to ensure they are valid
				if(strlen(trim($settings['region']['time-format'])) == 0){
					$this->errors->append('region::time-format', __("'%s' is a required field.", array('Time Format')));
				}
				//elseif(!date_parse(DateTimeObj::get('Y-m-d' . $settings['region']['time-format']))){
				//	$this->errors->append('region::time-format', __("Invalid time format specified."));
				//}
				
				if ($this->errors->length() <= 0) {

					if(is_array($settings) && !empty($settings)){
						foreach($settings as $set => $values) {
							foreach($values as $key => $val) {
								Symphony::Configuration()->core()->$set->$key = $val;
							}
						}
					}

					Symphony::Configuration()->save();

					redirect(ADMIN_URL . '/system/settings/:saved/');
				}
				else{
					$this->alerts()->append(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), AlertStack::ERROR, $this->errors);
				}
			}
		}
	}
