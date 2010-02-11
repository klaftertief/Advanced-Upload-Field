<?php

	class extension_advanceduploadfield extends Extension {

		public function about() {
			return array(
				'name'			=> 'Field: Advanced Upload',
				'version'		=> '1.0',
				'release-date'	=> '2010-02-11',
				'author'		=> array(
					'name'			=> 'Jonas Coch',
					'website'		=> 'http://klaftertief.de',
					'email'			=> 'jonas@klaftertief.de'
				),
				'description'	=> 'Upload field with the capability to resize images proportionally to maximum dimensions.'
			);
		}

		public function uninstall() {
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_advancedupload`");
		}

		public function install() {
			return $this->_Parent->Database->query("CREATE TABLE `tbl_fields_advancedupload` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`field_id` int(11) unsigned NOT NULL,
				`destination` varchar(255) NOT NULL,
				`validator` varchar(50) default NULL,
				`max_width` int(11) default NULL,
				`max_height` int(11) default NULL,
				PRIMARY KEY (`id`),
				KEY `field_id` (`field_id`))"
			);
		}

	}
