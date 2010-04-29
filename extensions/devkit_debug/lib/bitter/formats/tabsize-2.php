<?php
/*----------------------------------------------------------------------------*/
	
	require_once BITTER_FORMAT_PATH . '/default.php';
	
/*----------------------------------------------------------------------------*/
	
	class BitterFormatTabsizeTwo extends BitterFormatDefault {
		protected $tabsize = 2;
		
		public function process($source) {
			$this->output = $source;
			
			$this->processTabs();
			$this->processLines();
			
			return $this->output;
		}
	}
	
/*----------------------------------------------------------------------------*/
	
	return new BitterFormatTabsizeTwo();
	
/*----------------------------------------------------------------------------*/
?>