<?php

	Class AdministrationView extends View {

		public $driver;

		public function __construct() {
		}

		public function loadFromURL($path){
			$parts = preg_split('/\//', $path, -1, PREG_SPLIT_NO_EMPTY);
			$view = NULL;

			while(!empty($parts)){

				$p = array_shift($parts);

				if(!is_dir(CONTENT . $view . "/{$p}")){
					array_unshift($parts, $p);
					break;
				}

				$view = $view . "/{$p}";

			}

			return self::loadFromPath($view, (!empty($parts) ? $parts : NULL));
		}

		public function loadFromPath($path, array $params=NULL) {
			$view = new self;

			$view->path = trim($path, '\\/');

			preg_match('/\/?([^\\\\\/]+)$/', $path, $match); //Find the view handle
			$view->handle = $match[1];

			$view->driver = sprintf('%s/%s/%s.driver.php', CONTENT, $view->path, $view->handle);

			if(!file_exists($view->driver)){
				throw new ViewException(__('View, %s, could not be found.', array($driver)), self::ERROR_VIEW_NOT_FOUND);
			}

			$template = sprintf('%s/%s/%s.xsl', CONTENT, $view->path, $view->handle);
			if(file_exists($template) && is_readable($template)){
				Administration::instance()->setTemplate(file_get_contents($template));
			}

			return $view;
		}

		public function buildOutput(XMLDocument &$Document) {

			if(is_null($Document)){
				$Document = new XMLDocument;
			}

			$root = $Document->documentElement;

			$data = $Document->createElement('data');

			// Figure out how we would want view drivers to append their XML.
			// For now...
			include($this->driver);

			$Document->documentElement->appendChild($data);

		}

	}