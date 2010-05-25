<?php

	set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../symphony/lib/'));

	require_once('include.utilities.php');
	require_once('class.htmldocument.php');
	require_once('class.widget.php');
	require_once('class.datetimeobj.php');
	require_once('class.messagestack.php');
	require_once('class.general.php');
	require_once('class.dbc.php');
	require_once('class.lang.php');	


	$clean_path = rtrim($_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\');
	$clean_path = preg_replace(array('/\/{2,}/i', '/\/install$/i'), array('/', NULL), $clean_path);

	define('DOMAIN', $clean_path); 
	define('URL', 'http://' . $clean_path);
	define('VERSION', '3.0.0alpha');
	
	Lang::load(realpath('../symphony/lang') . '/lang.en.php', 'en', true);
	
	function createPanel(DOMElement &$wrapper, $heading, $tooltip, $error_message=NULL){
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
		
		if(!is_null($error_message)){
			
			$extended = $wrapper->ownerDocument->createElement('div');
			$extended->setAttribute('class', 'extended error');
			$panel->appendChild($extended);
			$div = $wrapper->ownerDocument->createElement('div');
			$extended->appendChild($div);

			$div->appendChild($wrapper->ownerDocument->createElement('p', $error_message));
			
			$panel->appendChild($extended);
		}
		
		return $panel;
	}
	
	function missing($value){
		if(!is_array($value)) $value = (array)$value;
		
		foreach($value as $v){
			if(strlen(trim($v)) == 0) return true;
		}
		
		return false;
	}
	
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
	    'use-compatibility-mode' => 'no',
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
	
	$errors = new MessageStack;
	
	if(isset($_POST['action']['install'])){
		
		$settings = $_POST;
		
		// Website Preferences -------------------------------------------------------------------------------------------
		
			$settings['website-preferences'] = array_map('trim', $settings['website-preferences']);
		
			// Missing Sitename
			// Missing root path
			if(missing(array($settings['website-preferences']['name'], $settings['website-preferences']['path']))){
				$errors->append('website-preferences', 'Name and Path are both required fields.');
			}
		
			// Root Path does not exist or is not writable
			elseif(!is_dir($settings['website-preferences']['path']) || !is_writable($settings['website-preferences']['path'])){
				$errors->append('website-preferences', 'Path specified does not exist, or is not writable. Please check permissions on that location.');
			}
		
			// Root Path contains another install of Symphony
			elseif(file_exists(sprintf('%s/manifest/core.xml', rtrim($settings['website-preferences']['path'], '/')))){
				$errors->append('website-preferences', 'An installation of Symphony already exists at that location.');
			}
			
		
		// Database --------------------------------------------------------------------------------------------------
		
			$settings['database'] = array_map('trim', $settings['database']);
			
			// Missing Database
			// Missing username
			// Missing password
			// Missing host
			// Missing port
			// Missing table prefix
			if(missing(array(
				$settings['database']['database'],
				$settings['database']['username'],
				$settings['database']['password'],
				$settings['database']['host'],
				$settings['database']['port'],
				$settings['database']['table-prefix']
			))){
				$errors->append('database', 'Database, Username, Password, Host, Port and Table Prefix are all required fields.');
			}
		
			// Database doesnt exist
			// Username+Password combo invalid
			// Invalid database host or port
			// Prefix in use
			
			
		// User ------------------------------------------------------------------------------------------------------
		
			$settings['user'] = array_map('trim', $settings['user']);
		
			// Missing username
			// Missing password
			// Missing first name
			// Missing Last name
			// Missing Email Address
			if(missing(array(
				$settings['user']['username'],
				$settings['user']['password'],
				$settings['user']['first-name'],
				$settings['user']['last-name'],
				$settings['user']['email-address']
			))){
				$errors->append('user', 'Username, Password, First Name, Last Name and Email Address are all required fields.');
			}
		
			// Invalid username
			elseif(preg_match('/[\s]/i', $settings['user']['username'])){
				$errors->append('user', 'Username is invalid.');
			}
			
			// Passwords do not match
			elseif($settings['user']['password'] != $settings['user']['confirm-password']){
				$errors->append('user', 'Passwords do not match.');
			}
			
			// Invalid Email address
			elseif(!preg_match('/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i', $settings['user']['email-address'])){
				$errors->append('user', 'Email Address is invalid.');
			}

		if($errors->length() == 0){
			
			/// Create a DB connection --------------------------------------------------------------------------
				$db = new DBCMySQLProfiler;

				$db->character_encoding = 'utf8';
				$db->character_set = 'utf8';
				$db->force_query_caching = false;
				$db->prefix = $settings['database']['table-prefix'];
			
				$connection_string = sprintf(
					'mysql://%s:%s@%s:%s/%s/',
					$settings['database']['username'],
					$settings['database']['password'],
					$settings['database']['host'],
					$settings['database']['port'],
					$settings['database']['database']
				);

				try{
					$db->connect($connection_string);
				}
				catch(DatabaseException $e){
					$errors->append('database', 'Could not establish database connection. The following error was returned: ' . $e->getMessage());
				}

			if($errors->length() == 0){
				
				$permission = intval($settings['website-preferences']['directory-permissions'], 8);
				
				/// Create the .htaccess ------------------------------------------------------------------
					$rewrite_base = preg_replace('/\/install$/i', NULL, dirname($_SERVER['PHP_SELF']));

					$htaccess = sprintf(
						file_get_contents('assets/template.htaccess.txt'), 
						empty($rewrite_base) ? '/' : $rewrite_base
					);

					// Cannot write .htaccess
					if(!General::writeFile(sprintf('%s/.htaccess', rtrim($settings['website-preferences']['path'], '/')), $htaccess, $settings['website-preferences']['file-permissions'])){
						throw new Exception('Could not write .htaccess file. TODO: Handle this by recording to the log and showing nicer error page.');
					}
				
				
				/// Create Folder Structures ---------------------------------------------------------------
				
					// These folders are necessary, and can be created if missing
					$folders = array(
						'workspace', 'workspace/views', 'workspace/utilities', 'workspace/sections', 'workspace/data-sources', 'workspace/events',
						'manifest', 'manifest/conf', 'manifest/logs', 'manifest/templates', 'manifest/tmp', 'manifest/cache'
					);
				
					foreach($folders as $f){
						$path = realpath("../{$f}");
						if(!is_dir($path) && !mkdir($path, $permission)){
							throw new Exception('Could not create directory '.$f.'. TODO: Handle this by recording to the log and showing nicer error page.');
						}
					}
				
				/// Save the config ------------------------------------------------------------------------
					$config_core = sprintf(
						file_get_contents('assets/template.core.xml'), 
						VERSION,
						$settings['website-preferences']['name'],
						$settings['website-preferences']['file-permissions'],
						$settings['website-preferences']['directory-permissions'],
						$settings['date-time']['time-format'],
						$settings['date-time']['date-format'],
						$settings['date-time']['region']
					);
			
					$config_db = sprintf(
						file_get_contents('assets/template.db.xml'), 
						$settings['database']['host'],
						$settings['database']['port'],
		 				$settings['database']['username'],
						$settings['database']['password'],
						$settings['database']['database'],
						$settings['database']['table-prefix']
					);

					// Wite the core config file
					if(!General::writeFile(
						sprintf('%s/manifest/conf/core.xml', rtrim($settings['website-preferences']['path'], '/')), 
						$config_core, 
						$settings['website-preferences']['file-permissions']
					)){
						throw new Exception('Could not write manifest/conf/core.xml file. TODO: Handle this by recording to the log and showing nicer error page.');
					}
			
					// Wite the core config file
					if(!General::writeFile(
						sprintf('%s/manifest/conf/db.xml', rtrim($settings['website-preferences']['path'], '/')), 
						$config_db, 
						$settings['website-preferences']['file-permissions']
					)){
						throw new Exception('Could not write manifest/conf/db.xml file. TODO: Handle this by recording to the log and showing nicer error page.');
					}
			}
			
			/// Import the Database --------------------------------------------------------------------------------
				if($errors->length() == 0){
					try{

						$queries = preg_split('/;[\\r\\n]+/', file_get_contents('assets/install.sql'), -1, PREG_SPLIT_NO_EMPTY);

						if(!is_array($queries) || empty($queries) || count($queries) <= 0){
							throw new Exception('install/assets/install.sql file contained no queries.');
						}
						
						foreach($queries as $sql){
							$db->query($sql);
						}

						// Create the default user
						$db->insert('tbl_users', array(
							'username' => $settings['user']['username'],
							'password' => md5($settings['user']['password']),
							'first_name' => $settings['user']['first-name'],
							'last_name' => $settings['user']['last-name'],
							'email' => $settings['user']['email-address'],
							'default_section' => 'articles',
							'auth_token_active' => 'no',
							'language' => 'en'
						));
					}
					catch(Exception $e){
						$errors->append('database', $e->getMessage());
					}
				}
			
			if($errors->length() == 0){
				redirect(URL . '/symphony');
			}
			
		}
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
	
	$Document->insertNodeIntoHead($Document->createStylesheetElement('assets/styles.css'));
	
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
	$panel = createPanel($layout, 'Website Preferences', 'Install Symphony at the following location', $errors->{'website-preferences'});
	
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
	$panel = createPanel($layout, 'Date and Time', 'Region and format for the admin', $errors->{'date-time'});

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
	$panel = createPanel($layout, 'Database Connection', 'Database access details for Symphony', $errors->{'database'});

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
	$input = Widget::Input('database[use-compatibility-mode]', 'yes', 'checkbox');
	if($settings['database']['use-compatibility-mode'] == 'yes'){
		$input->setAttribute('checked', 'checked');
	}
	$label->appendChild($input);
	$label->appendChild(new DOMText(' Use compatibility mode?'));
	$div->appendChild($label);
	$div->appendChild($Document->createElement('p', 'With compatibility mode enabled, Symphony will use the default character encoding of your database instead of overriding it with UTF-8 encoding.', array('class' => 'description')));
	
	
	// User Information ----------------------------------------------------------------------------------------------
	$panel = createPanel($layout, 'User Information', 'Login access details for the admin', $errors->{'user'});
	
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
