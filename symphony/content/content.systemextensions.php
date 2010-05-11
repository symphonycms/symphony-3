<?php

	require_once(LIB . '/class.administrationpage.php');

	Class contentSystemExtensions extends AdministrationPage{
	
		protected $lists = array();

		function view(){
		
		## Setup page
		
			$filter = ($this->_context[0] == 'type' || $this->_context[0] == 'status' ? $this->_context[0] : NULL);
			$value = (isset($this->_context[1]) ? $this->_context[1] : NULL);

			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Extensions'))));
			$this->appendSubheading(__('Extensions'));

			$path = URL . '/symphony/system/extensions/';
			$this->Form->setAttribute('action', Administration::instance()->getCurrentPageURL());
			
		## Define layout
			
			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$left->setAttribute('class', 'column small filters');
			$right = $layout->createColumn(Layout::LARGE);
			
		## Process extensions and build lists
		
//			$extensions = ExtensionManager::instance()->listAll();
			
//			foreach($extensions as $e){
			foreach(new ExtensionIterator(ExtensionIterator::FLAG_STATUS, Extension::STATUS_ENABLED) as $extension){
				
			/* Someone should look all this over. My thinking was that it'd
			be nice to only have to loop through the extensions once. Maybe
			that's stupid? */
			
			$e = (array)$extension->about();
			
			## Build lists by status
				switch(Extension::status(Extension::getHandleFromPath(Extension::getPathFromClass(get_class($extension))))){
					case Extension::STATUS_ENABLED:
						$this->lists['status']['enabled'][] = $e;
						break;
							
					case Extension::STATUS_DISABLED:
						$this->lists['status']['disabled'][] = $e;
						break;
						
					case Extension::STATUS_NOT_INSTALLED:
						$this->lists['status']['installable'][] = $e;
						break;
						
					case Extension::STATUS_REQUIRES_UPDATE:
						$this->lists['status']['updateable'][] = $e;
				}

			## Build lists by type
				if(!empty($e['type'])){
					foreach($e['type'] as $t){
						if(!isset($this->lists['type'][$t])) {
							$this->lists['type'][$t][] = $e;
						}
						else {
							array_push($this->lists['type'][$t], $e);
						}
					}
				}
			}
		
		## Build status filter menu
		
			$h4 = $this->createElement('h4', __('Filter by Status'));
			$left->appendChild($h4);
			
			$ul = $this->createElement('ul');
			
			## Main status overview
			$li = $this->createElement('li', Widget::Anchor(__('Overview'), $path));
			if(is_null($filter)){
				$li->setAttribute('class', 'active');
			}
			$ul->appendChild($li);
			
			foreach($this->lists['status'] as $list => $extensions) {
				if(!empty($extensions)){
					$li = $this->createElement('li', Widget::Anchor(ucwords($list), $path . 'status/' . $list));
					if($value == $list){
						$li->setAttribute('class','active');
					}
					
					$count = $this->createElement('span', (string) count($extensions));

					$li->appendChild($count);
				
					$ul->appendChild($li);
				}
			}
	
			$left->appendChild($ul);
			
		## Build type filter menu
		
			$h4 = $this->createElement('h4', __('Filter by Type'));
			$left->appendChild($h4);
			
			$ul = $this->createElement('ul');
			
			foreach($this->lists['type'] as $list => $extensions) {
				if(!empty($extensions)){
					$li = $this->createElement('li', Widget::Anchor(ucwords($list), $path . 'type/' . $list));
					if($value == $list){
						$li->setAttribute('class','active');
					}
					
					$count = $this->createElement('span', (string) count($extensions));

					$li->appendChild($count);
				
					$ul->appendChild($li);
				}
			}
			
			$left->appendChild($ul);
			
		## If a filter and value are specified...

			if(!is_null($filter) && !is_null($value)){
			
			## If there are extensions in the list, build the table
				if(isset($this->lists[$filter][$value])){
					$right->appendChild($this->buildTable($this->lists[$filter][$value]));
				} else {
					## Otherwise pass an empty array so we get the
					## 'No Records Found' message
					$right->appendChild($this->buildTable(array()));
				}
				
			## and append table actions
			
				$tableActions = $this->createElement('div');
				$tableActions->setAttribute('class', 'actions');

				$options = array(
					array(NULL, false, __('With Selected...')),
					array('enable', false, __('Enable')),
					array('disable', false, __('Disable')),
					array('uninstall', false, __('Uninstall'), 'confirm'),
				);

				$tableActions->appendChild(Widget::Select('with-selected', $options));
				$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

				$right->appendChild($tableActions);
				
		## Otherwise, build the overview
		
			} else {
				
				## Updateable
				if(!empty($this->lists['status']['updateable'])) {
					$count = count($this->lists['status']['updateable']);
				
					$div = $this->createElement('div');
					$div->setAttribute('class', 'tools');
					$h4 = $this->createElement('h4', __('Updates'));
					$message = __('%s %s %s updates available.', array(
						$count,
						($count > 1 ? 'extensions' : 'extension'),
						($count > 1 ? 'have' : 'has')
					));
					$p = $this->createElement('p', $message);
					$view = Widget::Anchor(__('View Details'), $path . 'status/updateable/');
					$view->setAttribute('class', 'button');
				
					$div->appendChild($view);
					$div->appendChild($h4);
					$div->appendChild($p);
				
					$right->appendChild($div);
				}
				
				## Installable
				if(!empty($this->lists['status']['installable'])) {
					$count = count($this->lists['status']['installable']);
				
					$div = $this->createElement('div');
					$div->setAttribute('class', 'tools');
					$h4 = $this->createElement('h4', __('Not Installed'));
					$message = __('%s %s %s not installed.', array(
						$count,
						($count > 1 ? 'extensions' : 'extension'),
						($count > 1 ? 'are' : 'is')
					));
					$p = $this->createElement('p', $message);
					$install = $this->createElement('button', __('Install All'));
					$install->setAttribute('class', 'create');
					$view = Widget::Anchor(__('View Details'), $path . 'status/installable/');
					$view->setAttribute('class', 'button');
				
					$div->appendChild($install);
					$div->appendChild($view);
					$div->appendChild($h4);
					$div->appendChild($p);
				
					$right->appendChild($div);
				}
				
				## Disabled
				if(!empty($this->lists['status']['disabled'])) {
					$count = count($this->lists['status']['disabled']);
				
					$div = $this->createElement('div');
					$div->setAttribute('class', 'tools');
					$h4 = $this->createElement('h4', __('Disabled'));
					$message = __('%s %s %s disabled.', array(
						$count,
						($count > 1 ? 'extensions' : 'extension'),
						($count > 1 ? 'are' : 'is')
					));
					$p = $this->createElement('p', $message);
					$install = $this->createElement('button', __('Install All'));
					$install->setAttribute('class', 'create');
					$uninstall = $this->createElement('button', __('Uninstall All'));
					$uninstall->setAttribute('class', 'delete');
					$view = Widget::Anchor(__('View Details'), $path . 'status/disabled/');
					$view->setAttribute('class', 'button');
				
					$div->appendChild($install);
					$div->appendChild($uninstall);
					$div->appendChild($view);
					$div->appendChild($h4);
					$div->appendChild($p);
				
					$right->appendChild($div);
				}
				
				## Nothing to show
				if(empty($this->lists['status']['updateable']) && empty($this->lists['status']['installable']) && empty($this->lists['status']['disabled'])) {
					$div = $this->createElement('div');
					$div->setAttribute('class', 'tools');
					$p = $this->createElement('p', __('All of your extensions are installed and enabled.'));
					$view = $this->createElement('button', __('View Details'));
				
					$div->appendChild($view);
					$div->appendChild($p);
				
					$right->appendChild($div);
				}
			}
		
			$layout->appendTo($this->Form);

		}
		
		function buildTable($extensions, $prefixes=false){
			
			## Sort by extensions name:
			//uasort($extensions, array('ExtensionManager', 'sortByName'));

			$aTableHead = array(
				array(__('Name'), 'col'),
				array(__('Version'), 'col'),
				array(__('Author'), 'col'),
				array(__('Actions'), 'col', array('class' => 'row-actions'))
			);

			$aTableBody = array();
			$colspan = count($aTableHead);

			if(!is_array($extensions) || empty($extensions)){
				$aTableBody = array(Widget::TableRow(
					array(
						Widget::TableData(__('None found.'), array(
								'class' => 'inactive',
								'colspan' => $colspan
							)
						)
					), array(
						'class' => 'odd'
					)
				));
			}

			else foreach($extensions as $name => $about){

				// TODO: Remove need to convert to an array
				$about['author'] = (array)$about['author'];

				$fragment = $this->createDocumentFragment();

				if(!empty($about['table-link']) && $about['status'] == Extension::STATUS_ENABLED) {

					$fragment->appendChild(
						Widget::Anchor($about['name'], Administration::instance()->getCurrentPageURL() . 'extension/' . trim($about['table-link'], '/'))
					);
				}
				else {
					$fragment->appendChild(
						new DOMText($about['name'])
					);
				}

				if($prefixes && isset($about['type'])) {
					$fragment->appendChild(
						$this->createElement('span', ' &middot; ' . $about['type'][0])
					);
				}

				## Setup each cell
				$td1 = Widget::TableData($fragment);
				$td2 = Widget::TableData($about['version']);

				$link = $about['author']['name'];

				if(isset($about['author']['website'])){
					$link = Widget::Anchor($about['author']['name'], General::validateURL($about['author']['website']));
				}

				elseif(isset($about['author']['email'])){
					$link = Widget::Anchor($about['author']['name'], 'mailto:' . $about['author']['email']);
				}

				$td3 = Widget::TableData($link);

				$td3->appendChild(Widget::Input('items['.$about['handle'].']', 'on', 'checkbox'));

				// TODO: Fix me please
				switch(Extension::STATUS_ENABLED){
					case Extension::STATUS_ENABLED:
						$td4 = Widget::TableData(Widget::Anchor(__('Uninstall'), '#', array('class' => 'button delete')));
						$td4->appendChild(Widget::Anchor(__('Disable'), '#', array('class' => 'button')));
						break;

					case Extension::STATUS_DISABLED:
						$td4 = Widget::TableData(Widget::Anchor(__('Enable'), '#', array('class' => 'button create')));
						break;

					case Extension::STATUS_NOT_INSTALLED:
						$td4 = Widget::TableData(Widget::Anchor(__('Install'), '#', array('class' => 'button create')));
						break;

					case Extension::STATUS_REQUIRES_UPDATE:
						$td4 = Widget::TableData(Widget::Anchor(__('Update'), '#', array('class' => 'button create')));
				}

				## Add a row to the body array, assigning each cell to the row
				$aTableBody[] = Widget::TableRow(
					array($td1, $td2, $td3, $td4),
					($about['status'] == Extension::STATUS_NOT_INSTALLED ? array('class' => 'inactive') : array())
				);
			}

			$table = Widget::Table(Widget::TableHead($aTableHead), NULL, Widget::TableBody($aTableBody));

			return $table;
		}

		function action(){
			$checked  = array_keys($_POST['items']);

			if(isset($_POST['with-selected']) && is_array($checked) && !empty($checked)){

				$action = $_POST['with-selected'];

				switch($action){

					case 'enable':

						## FIXME: Fix this delegate
						###
						# Delegate: Enable
						# Description: Notifies of enabling Extension. Array of selected services is provided.
						#              This can not be modified.
						//Extension::notify('Enable', getCurrentPage(), array('services' => $checked));

						foreach($checked as $name){
							if(ExtensionManager::instance()->enable($name) === false) return;
						}
						break;


					case 'disable':

						## FIXME: Fix this delegate
						###
						# Delegate: Disable
						# Description: Notifies of disabling Extension. Array of selected services is provided.
						#              This can be modified.
						//Extension::notify('Disable', getCurrentPage(), array('services' => &$checked));

						foreach($checked as $name){
							if(ExtensionManager::instance()->disable($name) === false) return;
						}
						break;

					case 'uninstall':

						## FIXME: Fix this delegate
						###
						# Delegate: Uninstall
						# Description: Notifies of uninstalling Extension. Array of selected services is provided.
						#              This can be modified.
						//Extension::notify('Uninstall', getCurrentPage(), array('services' => &$checked));

						foreach($checked as $name){
							if(ExtensionManager::instance()->uninstall($name) === false) return;
						}

						break;
				}

				redirect(Administration::instance()->getCurrentPageURL());
			}
		}

		/*function __viewDetail(){

			$date = Administration::instance()->getDateObj();

			if(!$extension_name = $this->_context[1]) redirect(ADMIN_URL . '/system/extensions/');

			if(!$extension = ExtensionManager::instance()->about($extension_name)) Administration::instance()->customError(E_USER_ERROR, 'Extension not found', 'The Symphony Extension you were looking for, <code>'.$extension_name.'</code>, could not be found.', 'Please check it has been installed correctly.');

			$link = $extension['author']['name'];

			if(isset($extension['author']['website']))
				$link = Widget::Anchor($extension['author']['name'], General::validateURL($extension['author']['website']));

			elseif(isset($extension['author']['email']))
				$link = Widget::Anchor($extension['author']['name'], 'mailto:' . $extension['author']['email']);

			$this->setPageType('form');
			$this->setTitle('Symphony &ndash; Extensions &ndash; ' . $extension['name']);
			$this->appendSubheading($extension['name']);

			$fieldset = new XMLElement('fieldset');

			$dl = new XMLElement('dl');

			$dl->appendChild(new XMLElement('dt', 'Author'));
			$dl->appendChild(new XMLElement('dd', (is_object($link) ? $link->generate(false) : $link)));

			$dl->appendChild(new XMLElement('dt', 'Version'));
			$dl->appendChild(new XMLElement('dd', $extension['version']));

			$dl->appendChild(new XMLElement('dt', 'Release Date'));
			$dl->appendChild(new XMLElement('dd', $date->get(true, true, strtotime($extension['release-date']))));

			$fieldset->appendChild($dl);

			$fieldset->appendChild((is_object($extension['description']) ? $extension['description'] : new XMLElement('p', strip_tags(General::sanitize($extension['description'])))));

			switch($extension['status']){

				case Extension::DISABLED:
				case Extension::ENABLED:
					$fieldset->appendChild(new XMLElement('p', '<strong>Uninstall this Extension, which will remove anything created by it, but will leave the original files intact. To fully remove it, you will need to manually delete the files.</strong>'));
					$fieldset->appendChild(Widget::Input('action[uninstall]', 'Uninstall Extension', 'submit'));
					break;

				case Extension::REQUIRES_UPDATE:
					$fieldset->appendChild(new XMLElement('p', '<strong>Note: This Extension is currently disabled as it is ready for updating. Use the button below to complete the update process.</strong>'));
					$fieldset->appendChild(Widget::Input('action[update]', 'Update Extension', 'submit'));
					break;

				case Extension::NOT_INSTALLED:
					$fieldset->appendChild(new XMLElement('p', '<strong>Note: This Extension has not been installed. If you wish to install it, please use the button below.</strong>'));
					$fieldset->appendChild(Widget::Input('action[install]', 'Install Extension', 'submit'));
					break;

			}

			$this->Form->appendChild($fieldset);
		}

		function __actionDetail(){

			if(!$extension_name = $this->_context[1]) redirect(ADMIN_URL . '/system/extensions/');

			if(!$extension = ExtensionManager::instance()->about($extension_name)) Administration::instance()->customError(E_USER_ERROR, 'Extension not found', 'The Symphony Extension you were looking for, <code>'.$extension_name.'</code>, could not be found.', 'Please check it has been installed correctly.');

			if(isset($_POST['action']['install']) && $extension['status'] == Extension::NOT_INSTALLED){
				ExtensionManager::instance()->enable($extension_name);
			}

			elseif(isset($_POST['action']['update']) && $extension['status'] == Extension::REQUIRES_UPDATE){
				ExtensionManager::instance()->enable($extension_name);
			}

			elseif(isset($_POST['action']['uninstall']) && in_array($extension['status'], array(Extension::ENABLED, Extension::DISABLED))){
				ExtensionManager::instance()->uninstall($extension_name);
			}
		}*/
	}
