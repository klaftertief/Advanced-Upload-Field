<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/fields/field.upload.php');
	require_once(EXTENSIONS . '/advanceduploadfield/lib/phpthumb/ThumbLib.inc.php');

	class FieldAdvancedUpload extends FieldUpload {
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Advanced File Upload');
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$max_dimensions = new XMLElement('div', NULL, array('class' => 'group'));
			$label = new XMLElement('label', __('Maximum image width <i>Optional</i>'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][max_width]', $this->get('max_width')?$this->get('max_width'):''));
			if(isset($errors['max_width'])) {
				$max_dimensions->appendChild(Widget::wrapFormElementWithError($label, $errors['max_width']));
			} else {
				$max_dimensions->appendChild($label);
			};
			$label = new XMLElement('label', __('Maximum image height <i>Optional</i>'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][max_height]', $this->get('max_height')?$this->get('max_height'):''));
			if(isset($errors['max_height'])) {
				$max_dimensions->appendChild(Widget::wrapFormElementWithError($label, $errors['max_height']));
			} else {
				$max_dimensions->appendChild($label);
			};
			$wrapper->appendChild($max_dimensions);

		}

		public function checkFields(&$errors, $checkForDuplicates=true){

			if(strlen($this->get('max_width')) > 0 && !is_numeric($this->get('max_width'))){
				$errors['max_width'] = __('Must be a number.');
			}
			if(strlen($this->get('max_height')) > 0 && !is_numeric($this->get('max_height'))){
				$errors['max_height'] = __('Must be a number.');
			}
						
			parent::checkFields($errors, $checkForDuplicates);
		}
		
		function commit(){
			
			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			$fields['destination'] = $this->get('destination');
			$fields['validator'] = ($fields['validator'] == 'custom' ? NULL : $this->get('validator'));
			$fields['max_width'] = $this->get('max_width');
			$fields['max_height'] = $this->get('max_height');
			
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");		
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
					
		}		

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			
			$status = self::__OK__;
			
			## Its not an array, so just retain the current data and return
			if(!is_array($data)){
				
				$status = self::__OK__;
				
				// Do a simple reconstruction of the file meta information. This is a workaround for
				// bug which causes all meta information to be dropped
				return array(
					'file' => $data,
					'mimetype' => self::__sniffMIMEType($data),
					'size' => filesize(WORKSPACE . $data),
					'meta' => serialize(self::getMetaInfo(WORKSPACE . $data, self::__sniffMIMEType($data)))
				);
	
			}

			if($simulate) return;
			
			if($data['error'] == UPLOAD_ERR_NO_FILE || $data['error'] != UPLOAD_ERR_OK) return;
			
			## Sanitize the filename
			$data['name'] = Lang::createFilename($data['name']);
			
			## Resize image, if it's an image
			if (getimagesize($data['tmp_name'])) {
				try {
					$thumb = PhpThumbFactory::create($data['tmp_name']);
				} catch (Exception $e) {
					$message = __('There was an error while trying to resize the image <code>%1$s</code>.', array($data['name']));
					$status = self::__ERROR_CUSTOM__;
					return;
				}
				$thumb->resize($this->get('max_width'), $this->get('max_height'))->save($data['tmp_name']);
			}

			## Upload the new file
			$abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
			$rel_path = str_replace('/workspace', '', $this->get('destination'));

			if(!General::uploadFile($abs_path, $data['name'], $data['tmp_name'], Symphony::Configuration()->get('write_mode', 'file'))){
				
				$message = __('There was an error while trying to upload the file <code>%1$s</code> to the target directory <code>%2$s</code>.', array($data['name'], 'workspace/'.ltrim($rel_path, '/')));
				$status = self::__ERROR_CUSTOM__;
				return;
			}

			$status = self::__OK__;
			
			$file = rtrim($rel_path, '/') . '/' . trim($data['name'], '/');

			if($entry_id){
				$row = $this->Database->fetchRow(0, "SELECT * FROM `tbl_entries_data_".$this->get('id')."` WHERE `entry_id` = '$entry_id' LIMIT 1");
				$existing_file = rtrim($rel_path, '/') . '/' . trim(basename($row['file']), '/');

				if((strtolower($existing_file) != strtolower($file)) && file_exists(WORKSPACE . $existing_file)){
					General::deleteFile(WORKSPACE . $existing_file);
				}
			}

			## If browser doesn't send MIME type (e.g. .flv in Safari)
			if (strlen(trim($data['type'])) == 0){
				$data['type'] = 'unknown';
			}

			return array(
				'file' => $file,
				'size' => $data['size'],
				'mimetype' => $data['type'],
				'meta' => serialize(self::getMetaInfo(WORKSPACE . $file, $data['type']))
			);
			
		}

		private static function __sniffMIMEType($file){
			
			$ext = strtolower(General::getExtension($file));
			
			$imageMimeTypes = array(
				'image/gif',
				'image/jpg',
				'image/jpeg',
				'image/png',
			);
			
			if(General::in_iarray("image/{$ext}", $imageMimeTypes)) return "image/{$ext}";
			
			return 'unknown';
		}
		
		
	}
