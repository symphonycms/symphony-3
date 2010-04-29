<?php
/*----------------------------------------------------------------------------*/
	
	require_once BITTER_FORMAT_PATH . '/default.php';
	
/*----------------------------------------------------------------------------*/
	
	class BitterFormatTabsizeFour extends BitterFormatDefault {
		protected $tabsize = 4;
		
		public function process($source) {
			$this->output = $source;
			
			$this->processTabs();
			$this->processLines();
			
			return $this->output;
		}
	}
	
/*----------------------------------------------------------------------------*/
	
	return new BitterFormatTabsizeFour();
	
/*----------------------------------------------------------------------------*/
?>
