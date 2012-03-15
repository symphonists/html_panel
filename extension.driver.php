<?php

	Class extension_html_panel extends Extension{
		
		public function install() {
			return Symphony::Database()->query("
				CREATE TABLE `tbl_fields_html_panel` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`field_id` int(11) unsigned NOT NULL,
				`url_expression` varchar(255) default NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `field_id` (`field_id`)
				) TYPE=MyISAM
			");
		}
		
		public function uninstall(){
			return Symphony::Database()->query("DROP TABLE `tbl_fields_html_panel`");
		}
	
	}