<?php
	
	Class fieldhtml_panel extends Field{
		
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'HTML Panel';
			$this->_driver = $this->_engine->ExtensionManager->create('html_panel');
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);

			$label = Widget::Label('URL Expression');
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][url_expression]', $this->get('url_expression')));
			$wrapper->appendChild($label);
									
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode=false, $mode=NULL, $entry_id=NULL) {
			if (is_null($data) || !is_array($data)) return;
			
			$wrapper->appendChild(
				new XMLElement(
					$this->get('element_name'),
					($encode ? General::sanitize($data['value']) : $data['value']),
					array('handle' => $data['handle'])
				)
			);
		}
		
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=null) {
			$status = self::__OK__;
			return array(
				'handle' => Lang::createHandle($data),
				'value' => $data
			);
		}
		
		public function commit() {
			if (!parent::commit()) return false;
			
			$id = $this->get('id');
			$handle = $this->handle();
			
			if ($id === false) return false;
			
			$fields = array(
				'field_id'			=> $id,
				'url_expression'	=> $this->get('url_expression')
			);
			
			$this->Database->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");
			
			return $this->Database->insert($fields, "tbl_fields_{$handle}");
		}
		
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
			
			// work out what page we are on, get portions of the URL
			$callback = Administration::instance()->getPageCallback();
			$entry_id = $callback['context']['entry_id'];
			
			// get an Entry object for this entry
			$entryManager = new EntryManager(Administration::instance());
			$entries = $entryManager->fetch($entry_id);
			
			if (is_array($entries)) $entry = reset($entries);
			
			// parse dynamic portions of the HTML Panel URL
			$url = $this->parseExpression($entry, $this->get('url_expression'));
			if (!preg_match('/^http/', $url)) $url = URL . $url;

			$cookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';
			session_write_close();

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $ch, CURLOPT_COOKIE, $cookie);
			$result = curl_exec($ch);
			curl_close($ch);

			//require_once(TOOLKIT . '/class.gateway.php');
			//$ch = new Gateway;
			//$ch->init();
			//$ch->setopt('URL', $url);
			//$ch->setopt('CURLOPT_COOKIE', $cookie);			
			//$fh = fopen(TMP . '/cookie.txt', 'w');
			//fwrite($fh, $cookie);
			//fclose($fh);			
			//$result = $ch->exec();
						
			// a unique name for this panel instance
			$instance_id = $callback['context']['section_handle'] . '_' . $this->get('element_name');
			
			$container = new XMLELement('div', $result);
			$container->setAttribute('id', $instance_id);
			$container->setAttribute('class', 'html-panel');
			
			$label = Widget::Label($this->get('label'));
			$wrapper->appendChild($label);
			$wrapper->appendChild($container);
			
			if ($this->_engine->Page) {
				$asset_index = $this->get('id') * rand(10,100);
				
				// add the general styling
				$this->_engine->Page->addStylesheetToHead(URL . '/extensions/html_panel/assets/html-panel.css', 'screen', $asset_index++);
				
				// add panel-specific styling
				$instance_css = '/html-panel/' . $instance_id . '.css';
				if (file_exists(WORKSPACE . $instance_css)) {
					$this->_engine->Page->addStylesheetToHead(URL . '/workspace' . $instance_css, 'screen', $asset_index++);
				}
				
				// add panel-specific behaviour
				$instance_js = '/html-panel/' . $instance_id . '.js';
				if (file_exists(WORKSPACE . $instance_js)) {
					$this->_engine->Page->addScriptToHead(URL . '/workspace' . $instance_js, $asset_index++);
				}
			}
			
		}
		
		public function createTable(){
			return $this->Database->query(			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `handle` varchar(255) default NULL,
    			  `value` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`)
				) TYPE=MyISAM;"			
			);			
		}
		
		// modified from Reflection Field
		private function parseExpression($entry, $expression) {
		
			$xpath = $this->getXPath($entry);			
			$replacements = array();			
			preg_match_all('/\{[^\}]+\}/', $expression, $matches);
			
			foreach ($matches[0] as $match) {
				$results = @$xpath->query(trim($match, '{}'));				
				if ($results->length) {
					$replacements[$match] = $results->item(0)->nodeValue;
				} else {
					$replacements[$match] = '';
				}
			}
			
			$value = str_replace(
				array_keys($replacements),
				array_values($replacements),
				$expression
			);
			
			return $value;
		}
		
		// modified from Reflection Field
		private function getXPath($entry) {
			
			if (!$entry instanceOf Entry) return new DOMXPath(new DOMDocument());
			
			$entry_xml = new XMLElement('entry');
			$section_id = $entry->_fields['section_id'];
			$data = $entry->getData();			
			$fields = array();

			$entry_xml->setAttribute('id', $entry->get('id'));

			$associated = $entry->fetchAllAssociatedEntryCounts();

			if (is_array($associated) and !empty($associated)) {
				foreach ($associated as $section => $count) {
					$handle = Administration::instance()->Database->fetchVar('handle', 0, "
						SELECT
							s.handle
						FROM
							`tbl_sections` AS s
						WHERE
							s.id = '{$section}'
						LIMIT 1
					");

					$entry_xml->setAttribute($handle, (string)$count);
				}
			}

			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;

				$field =& $entry->_Parent->fieldManager->fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false, null);
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			$dom = new DOMDocument();
			$dom->loadXML($xml->generate(true));

			return new DOMXPath($dom);
		}
						
	}