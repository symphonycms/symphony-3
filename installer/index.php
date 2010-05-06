<?php
	
	function createPanel(DOMElement &$wrapper, $heading, $tooltip){
		$div = $wrapper->ownerDocument->createElement('div');

		$help = $wrapper->ownerDocument->createElement('div');
		$help->setAttribute('class', 'help');
		$help->appendChild($wrapper->ownerDocument->createElement('h2', $heading));
		$help->appendChild($wrapper->ownerDocument->createElement('p', $tooltip));
		$div->appendChild($help);

		$panel = $wrapper->ownerDocument->createElement('div');
		$panel->setAttribute('class', 'panel');	
		$div->appendChild($panel);
		
		$wrapper->appendChild($div);
		
		return $panel;
	}
	
	set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../symphony/lib/'));
	
	require_once('class.htmldocument.php');
	require_once('class.widget.php');
	require_once('class.datetimeobj.php');
	
	$settings = array(
	  'website-preferences' => array(
	    'name' => 'Symphony CMS',
	    'path' => realpath('..'),
	    'file-permissions' => '0755',
	    'directory-permissions' => '0755',
	  ),
	  'date-time' => array(
	    'region' => date_default_timezone_get(),
	    'date-format' => 'Y/m/d',
	    'time-format' => 'H:i:s',
	  ),
	  'database' => array(
	    'database' => NULL,
	    'username' => NULL,
	    'password' => NULL,
	    'host' => 'localhost',
	    'port' => '3306',
	    'table-prefix' => 'sym_',
	    'use-compatiblity-mode' => 'no',
	  ),
	  'user' => array(
	    'username' => NULL,
	    'password' => NULL,
	    'confirm-password' => NULL,
	    'first-name' => NULL,
	    'last-name' => NULL,
	    'email-address' => NULL,
	  )
	);
	
	if(isset($_POST['action']['install'])){
		//header('Content-Type: text/plain; charset=utf-8');
		//var_export($_POST); die();
	}
		
	$Document = new HTMLDocument('1.0', 'utf-8', 'html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"');
	
	$Document->Headers->append('Expires', 'Mon, 12 Dec 1982 06:14:00 GMT');
	$Document->Headers->append('Last-Modified', gmdate('r'));
	$Document->Headers->append('Cache-Control', 'no-cache, must-revalidate, max-age=0');
	$Document->Headers->append('Pragma', 'no-cache');
	
	Widget::init($Document);
	
	$Document->insertNodeIntoHead($Document->createElement('title', 'Install Symphony'));
	
	$meta = $Document->createElement('meta');
	$meta->setAttribute('http-equiv', 'Content-Type');
	$meta->setAttribute('content', 'text/html; charset=UTF-8');
	$Document->insertNodeIntoHead($meta);
	
	$Document->insertNodeIntoHead($Document->createStylesheetElement('styles.css'));
	
	$form = $Document->createElement('form');
	$form->setAttribute('method', 'POST');
	$form->setAttribute('action', '');
	$Document->appendChild($form);
		
	$layout = $Document->createElement('div');
	$layout->setAttribute('id', 'layout');
	$form->appendChild($layout);
	
	// About panel ---------------------------------------------------------------------------------------------------
	$div = $Document->createElement('div');
	$layout->appendChild($div);
	
	$about = $Document->createElement('div');
	$about->setAttribute('class', 'about');
	$about->appendChild($Document->createElement('h1', 'Symphony Installation'));
	$about->appendChild($Document->createElement('p', 'Version 3.0.0 alpha'));
	$div->appendChild($about);
	
	
	// Website Preferences -------------------------------------------------------------------------------------------
	$panel = createPanel($layout, 'Website Preferences', 'Install Symphony at the following location');
	
	$label = Widget::Label('Name', NULL, array('class' => 'input'));
	$input = Widget::Input('website-preferences[name]', $settings['website-preferences']['name']);
	$label->appendChild($input);
	$panel->appendChild($label);
	
	$label = Widget::Label('Root Path', NULL, array('class' => 'input'));
	$input = Widget::Input('website-preferences[path]', $settings['website-preferences']['path']);
	$label->appendChild($input);
	$panel->appendChild($label);
	
	$extended = $Document->createElement('div');
	$extended->setAttribute('class', 'extended');
	$panel->appendChild($extended);
	$div = $Document->createElement('div');
	$extended->appendChild($div);
	$group = $Document->createElement('div');
	$group->setAttribute('class', 'group');
	$div->appendChild($group);
	
	$label = Widget::Label('File Permissions', NULL, array('class' => 'select'));
	$input = Widget::Select('website-preferences[file-permissions]', array(
		array('0755', false, '0755'),
		array('0777', $settings['website-preferences']['file-permissions'] == '0777', '0777')
	));
	$label->appendChild($input);
	$group->appendChild($label);

	$label = Widget::Label('Directory Permissions', NULL, array('class' => 'select'));
	$input = Widget::Select('website-preferences[directory-permissions]', array(
		array('0755', false, '0755'),
		array('0777', $settings['website-preferences']['directory-permissions'] == '0777', '0777')
	));
	$label->appendChild($input);
	$group->appendChild($label);
	
	
	// Date and Time -------------------------------------------------------------------------------------------------
	$panel = createPanel($layout, 'Date and Time', 'Region and format for the admin');

	$label = Widget::Label('Region', NULL, array('class' => 'select'));
	$options = array();
	foreach(DateTimeZone::listIdentifiers() as $t){
		$options[] = array($t, ($t == $settings['date-time']['region']), $t);
	}
	$input = Widget::Select('date-time[region]', $options);
	$label->appendChild($input);
	$panel->appendChild($label);
	
	$extended = $Document->createElement('div');
	$extended->setAttribute('class', 'extended');
	$panel->appendChild($extended);
	$div = $Document->createElement('div');
	$extended->appendChild($div);
	$group = $Document->createElement('div');
	$group->setAttribute('class', 'group');
	$div->appendChild($group);

	$date_formats = array(
		'Y/m/d',
		'm/d/Y',
		'm/d/y',
		'd F Y'
	);

	$label = Widget::Label('Date Format', NULL, array('class' => 'select'));
	$options = array();
	foreach($date_formats as $d){
		$options[] = array($d, $d == $settings['date-time']['date-format'], DateTimeObj::get($d));
	}
	$input = Widget::Select('date-time[date-format]', $options);
	$label->appendChild($input);
	$group->appendChild($label);
	
	
	$time_formats = array(
		'H:i:s',
		'H:i',
		'g:i:s a',
		'g:i a'
	);

	$label = Widget::Label('Time Format', NULL, array('class' => 'select'));
	$options = array();
	foreach($time_formats as $t){
		$options[] = array($t, $t == $settings['date-time']['time-format'], DateTimeObj::get($t));
	}
	$input = Widget::Select('date-time[time-format]', $options);
	$label->appendChild($input);
	$group->appendChild($label);


	// Database Connection -------------------------------------------------------------------------------------------
	$panel = createPanel($layout, 'Database Connection', 'Database access details for Symphony');

	$label = Widget::Label('Database', NULL, array('class' => 'input'));
	$input = Widget::Input('database[database]', $settings['database']['database']);
	$label->appendChild($input);
	$panel->appendChild($label);
	
	$group = $Document->createElement('div');
	$group->setAttribute('class', 'group');

	$label = Widget::Label('Username', NULL, array('class' => 'input'));
	$input = Widget::Input('database[username]', $settings['database']['username']);
	$label->appendChild($input);
	$group->appendChild($label);
	
	$label = Widget::Label('Password', NULL, array('class' => 'input'));
	$input = Widget::Input('database[password]', $settings['database']['password'], 'password');
	$label->appendChild($input);
	$group->appendChild($label);	
	
	$panel->appendChild($group);
	
	$extended = $Document->createElement('div');
	$extended->setAttribute('class', 'extended');
	$panel->appendChild($extended);
	$div = $Document->createElement('div');
	$extended->appendChild($div);
	$group = $Document->createElement('div');
	$group->setAttribute('class', 'group');
	$div->appendChild($group);
	
	$label = Widget::Label('Host', NULL, array('class' => 'input'));
	$input = Widget::Input('database[host]', $settings['database']['host']);
	$label->appendChild($input);
	$group->appendChild($label);
	
	$label = Widget::Label('Port', NULL, array('class' => 'input'));
	$input = Widget::Input('database[port]', $settings['database']['port']);
	$label->appendChild($input);
	$group->appendChild($label);	

	$label = Widget::Label('Table Prefix', NULL, array('class' => 'input'));
	$input = Widget::Input('database[table-prefix]', $settings['database']['table-prefix']);
	$label->appendChild($input);
	$group->appendChild($label);


	$label = Widget::Label(NULL, NULL, array('class' => 'checkbox'));
	$input = Widget::Input('database[use-compatiblity-mode]', 'yes', 'checkbox');
	if($settings['database']['use-compatiblity-mode'] == 'yes'){
		$input->setAttribute('checked', 'checked');
	}
	$label->appendChild($input);
	$label->appendChild(new DOMText(' Use compatibility mode?'));
	$div->appendChild($label);
	$div->appendChild($Document->createElement('p', 'With compatibility mode enabled, Symphony will use the default character encoding of your database instead of overriding it with UTF-8 encoding.', array('class' => 'description')));
	
	
	// User Information ----------------------------------------------------------------------------------------------
	$panel = createPanel($layout, 'User Information', 'Login access details for the admin');
	
	$label = Widget::Label('Username', NULL, array('class' => 'input'));
	$input = Widget::Input('user[username]', $settings['user']['username']);
	$label->appendChild($input);
	$panel->appendChild($label);
	
	$group = $Document->createElement('div');
	$group->setAttribute('class', 'group');

	$label = Widget::Label('Password', NULL, array('class' => 'input'));
	$input = Widget::Input('user[password]', $settings['user']['password'], 'password');
	$label->appendChild($input);
	$group->appendChild($label);
	
	$label = Widget::Label('Confirm Password', NULL, array('class' => 'input'));
	$input = Widget::Input('user[confirm-password]', $settings['user']['confirm-password'], 'password');
	$label->appendChild($input);
	$group->appendChild($label);	
	
	$panel->appendChild($group);
	
	$extended = $Document->createElement('div');
	$extended->setAttribute('class', 'extended');
	$panel->appendChild($extended);
	$div = $Document->createElement('div');
	$extended->appendChild($div);
	$group = $Document->createElement('div');
	$group->setAttribute('class', 'group');
	$div->appendChild($group);
	
	$label = Widget::Label('First Name', NULL, array('class' => 'input'));
	$input = Widget::Input('user[first-name]', $settings['user']['first-name']);
	$label->appendChild($input);
	$group->appendChild($label);
	
	$label = Widget::Label('Last Name', NULL, array('class' => 'input'));
	$input = Widget::Input('user[last-name]', $settings['user']['last-name']);
	$label->appendChild($input);
	$group->appendChild($label);
		
	$label = Widget::Label('Email Address', NULL, array('class' => 'input'));
	$input = Widget::Input('user[email-address]', $settings['user']['email-address']);
	$label->appendChild($input);
	$div->appendChild($label);
		
	// Submit --------------------------------------------------------------------------------------------------------
	$div = $Document->createElement('div');
	$layout->appendChild($div);	
	
	$submit = $Document->createElement('div');
	$submit->setAttribute('class', 'content submission');
	$submit->appendChild($Document->createElement('p', 'Make sure that you delete install.php file after Symphony has installed successfully.'));
	$p = $Document->createElement('p');
	$button = $Document->createElement('button', 'Install Symphony');
	$button->setAttribute('name', 'action[install]');
	$p->appendChild($button);
	$submit->appendChild($p);
	$div->appendChild($submit);	
	
	$output = (string)$Document;
	
	$Document->Headers->append('Content-Length', strlen($output));
	
	$Document->Headers->render();
	echo $output;
	die();
