<?php
if (!class_exists('cfct_module_image')) {
    if ( defined( 'CFCT_BUILD_DIR' ) ) {
        require_once( CFCT_BUILD_DIR.'/modules/image/image.php' );
    } else {
        require_once( dirname(dirname(__FILE__)).'/image/image.php' );
    }
}
if (!class_exists('cfct_module_slideshow') && class_exists('cfct_module_image')) {
	class cfct_module_slideshow extends cfct_module_image {
		/**
		 * Set up the module
		 */
		public function __construct() {
			$this->pluginDir		= basename(dirname(__FILE__));
			$this->pluginPath		= WP_PLUGIN_DIR . '/' . $this->pluginDir;
			$this->pluginUrl 		= WP_PLUGIN_URL.'/'.$this->pluginDir;	

			$opts = array(
				'url' => $this->pluginUrl, 
				'view' => 'wp-cb-slideshow/slideshow-view.php',				
				'description' => __('Select and insert images as a gallery.', 'carrington-build'),
				'icon' => 'wp-cb-slideshow/slideshow-icon.png'
			);
			cfct_build_module::__construct('cfct-module-slideshow', __('Slideshow', 'carrington-build'), $opts);
		}
		
		/**
		 * Display the module content in the Post-Content
		 * 
		 * @param array $data - saved module data
		 * @return array string HTML
		 */
		public function display($data) {
			global $cfct_build;

			$cfct_build->loaded_modules[$this->basename] = $this->pluginPath;
			$cfct_build->module_paths[$this->basename] = $this->pluginPath;
			$cfct_build->module_urls[$this->basename] = $this->pluginUrl;
			
			$link = ($data["cfct-module-options"]["slideshow-display-option"]["slideshow-link"][0] != '') ?  $data["cfct-module-options"]["slideshow-display-option"]["slideshow-link"][0] : 'nothing';		
			$display = ($data["cfct-module-options"]["slideshow-display-option"]["slideshow-display"][0] != '') ?  $data["cfct-module-options"]["slideshow-display-option"]["slideshow-display"][0] : 'false';
			$random_slide = ($data["cfct-module-options"]["slideshow-display-option"]["slideshow-random"][0] != '') ?  $data["cfct-module-options"]["slideshow-display-option"]["slideshow-random"][0] : 'no';
			if ($display == 'items') {
				$display = '"'.$display.'"';
			}
			$autoplay = ($data["cfct-module-options"]["slideshow-autoplay-option"]["slideshow-autoplay"][0] != '') ?  $data["cfct-module-options"]["slideshow-autoplay-option"]["slideshow-autoplay"][0] : 'true';
			$autoplay_delay = ($data["cfct-module-options"]["slideshow-autoplay-option"]["slideshow-autoplay-delay"][0] != '') ?  $data["cfct-module-options"]["slideshow-autoplay-option"]["slideshow-autoplay-delay"][0] : 3;			
			
			$transition = ($data["cfct-module-options"]["slideshow-transition-option"]["slideshow-transition"][0] != '') ? $data["cfct-module-options"]["slideshow-transition-option"]["slideshow-transition"][0] : 'fade';
			$transition_delay = ($data["cfct-module-options"]["slideshow-transition-option"]["slideshow-transition-delay"][0] != '') ? $data["cfct-module-options"]["slideshow-transition-option"]["slideshow-transition-delay"][0] : 1;
			
			if (!empty($data[$this->get_field_name('post_image')])) {
				$slideshow_atts = array(
					'id' => $data["module_id"],
					'include' => $data[$this->get_field_name('post_image')],
					'size' => $data[$this->get_field_name('post_image').'-size'],
					'linkurl' => $link,
                    'randomize' => $random_slide
				);

				remove_filter('post_gallery', 'cfct_post_gallery', 10, 2);
				$slideshow_html = $this->slideshow_shortcode($slideshow_atts);
				add_filter('post_gallery', 'cfct_post_gallery', 10, 2);
			}
			else {
				$slideshow_html = null;
			}
				return $this->load_view($data, compact('slideshow_html', 'display', 'random_slide', 'autoplay', 'autoplay_delay','transition', 'transition_delay'));
		}

		function slideshow_shortcode($attr) {
			global $post, $wp_locale;
			
			// Allow plugins/themes to override the default gallery template.
			$output = apply_filters('post_gallery', '', $attr);
			if ( $output != '' )
				return $output;

			// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
			if ( isset( $attr['orderby'] ) ) {
				$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
				if ( !$attr['orderby'] )
					unset( $attr['orderby'] );
			}

			extract(shortcode_atts(array(
				'order'      => 'ASC',
				'orderby'    => 'menu_order ID',
				'id'         => $post->ID,
				'itemtag'    => 'li',
				'captiontag' => 'dd',
				'columns'    => 3,
				'size'       => 'thumbnail',
				'include'    => '',
				'exclude'    => '',
				'linkurl' => 'nothing',
			), $attr));
			
			// $id = intval($id);
			if ( 'RAND' == $order )
				$orderby = 'none';

			if ( !empty($include) ) {
				$include = preg_replace( '/[^0-9,]+/', '', $include );
				$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
                if ($attr['randomize'] == 'yes') shuffle($_attachments);
                
				$attachments = array();
				foreach ( $_attachments as $key => $val ) {
					$attachments[$val->ID] = $_attachments[$key];
				}
			} elseif ( !empty($exclude) ) {
				$exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
				$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
			} else {
				$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
			}

			if ( empty($attachments) )
				return '';
				
			$itemtag = tag_escape($itemtag);
			$captiontag = tag_escape($captiontag);
			$selector = "slideshow-{$id}";
			$output = apply_filters('gallery_style', "<ul id='$selector' class='slideshow slideshowid-{$id}'>");

			$i = 0;
			foreach ( $attachments as $id => $attachment ) {
				switch ($linkurl) {
				case 'nothing':
					$link = wp_get_attachment_image($id, $size, false, false);												
					break;
				case 'lightbox':
					$link = wp_get_attachment_link($id, $size, false, false);				
					break;				
				default:
					$link = wp_get_attachment_link($id, $size, true, false);								
				}
				$output .= "<{$itemtag} class='gallery-item'>";
				$output .= "$link";
				if (strlen($attachment->post_excerpt)) {
                    $output .= '<div class="description">';
                    $output .= '<div class="content"><b>'.$attachment->post_excerpt.'</b>';
                    if (strlen($attachment->post_content)) {
                        $output .= '<br/>'.$attachment->post_content;
                    }
                    $output .= '</div></div>';
				}
				$output .= "</{$itemtag}>";
			}
			$output .= "</ul>\n";
			return $output;
		}
		
		
		/**
		 * Build the admin form
		 * 
		 * @param array $data - saved module data
		 * @return string HTML
		 */
		public function admin_form($data) {
			cfct_module_register_extra('slideshow-display-option', 'slideshow_display_option');	//							
			cfct_module_register_extra('slideshow-autoplay-option', 'slideshow_autoplay_option');	//					
			cfct_module_register_extra('slideshow-transition-option', 'slideshow_transition_option');	//											
			
			$html = '
					<div id="'.$this->id_base.'-post-image-wrap">
						'.$this->post_image_selector($data, true).'
					</div>											
				';
			return $html;
		}
		
		/**
		 * Return a textual representation of this module.
		 *
		 * @param array $data - saved module data
		 * @return string text
		 */
		public function text($data) {
			$items = __('No Images Selected', 'carrington-build');
			if (!empty($data[$this->get_field_name('post_image')])) {
				$num_items = count(explode(',', $data[$this->get_field_name('post_image')]));
				$items = $num_items > 1 ? __('1 Image Selected', 'carrington-build') : sprintf(__('%b Images Selected', 'carrington-build'), $num_items);
			}
			return strip_tags('Slideshow: '.$items);
		}

		/**
		 * Modify the data before it is saved, or not
		 *
		 * @param array $new_data 
		 * @param array $old_data 
		 * @return array
		 */
		public function update($new_data, $old_data) {
			cfct_module_register_extra('slideshow-display-option', 'slideshow_display_option');	//									
			cfct_module_register_extra('slideshow-autoplay-option', 'slideshow_autoplay_option');	//					
			cfct_module_register_extra('slideshow-transition-option', 'slideshow_transition_option');	//								
			return $new_data;
		}
		
		/**
		 * Add custom javascript to the post/page admin
		 *
		 * @return string JavaScript
		 */
		public function admin_js() {
			$js = '
				cfct_builder.addModuleSaveCallback("'.$this->id_base.'", function() {
					// find the non-active image selector and clear his value
					$("#'.$this->id_base.'-image-selectors .cfct-module-tab-contents>div:not(.active)").find("input:hidden").val("");
					return true;
				});
			';
			// @deprecated
			#$js .= $this->post_image_selector_js('post_image', array('direction' => 'horizontal'));
			$js .= $this->global_image_selector_js('global_image', array('direction' => 'horizontal'));
			return $js;
		}
	}
		
	// register the module with Carrington Build
	cfct_build_register_module('cfct-module-slideshow', 'cfct_module_slideshow');
}

if (!class_exists('slideshow_display_option')) {
	class slideshow_display_option extends cfct_module_option {
		protected $link_url_options = array('nothing' => 'Nothing',
											'lightbox' => 'Lightbox',
											'url' => 'Link URL');
		
		public function __construct() {
			parent::__construct('Display Options', 'slideshow-display-option');
			add_filter('cfct-build-module-class', array($this, 'apply_classes'), 10, 2);					
		}

		public function apply_classes($class, $data) {
			if (!empty($data['cfct-module-options'][$this->id_base]['slideshow-display'])) {
				$class .= ' '.implode(' ', $data['cfct-module-options'][$this->id_base]['slideshow-display']);
			}
			
			if (!empty($data['cfct-module-options'][$this->id_base]['slideshow-random'])) {
				$class .= ' '.implode(' ', $data['cfct-module-options'][$this->id_base]['slideshow-random']);
			}
			return $class;
		}

		protected function dropdown($field_name, $options, $value = false, $args = '') {
			$defaults = array(
				'label' => '', // The text for the label element  
				'default' => null, // Add a default option ('all', 'none', etc.)
				'excludes' => array() // values to exclude from options
			);
			$args = array_merge($defaults, $args);
			extract($args);
			
			$options = (is_array($options)) ? $options : array();
			
			
			// Set a label if there is one
			$html = (!empty($label)) ? '<label for="'.$this->get_field_id($field_name).'">'.$label.' </label>' : '';
			// Start off the select element
			$html .= '
				<select class="'.$field_name.'-dropdown" name="'.$this->get_field_name($field_name).'" id="'.$this->get_field_id($field_name).'">
					';

			// Set a default option that's not in the list of options (i.e., all, none)
			if (is_array($default)) {
				$html .= '<option value="'.$default['value'].'"'.selected($default['value'], $value, false).'>'.esc_html($default['text']).'</option>';
			}
			
			// Loop through our options
			foreach ($options as $k => $v) {
				if (!in_array($k, $excludes)) {
					$html .= '<option value="'.$k.'"'.selected($k, $value, false).'>'.esc_html($v).'</option>';
				}
			}	
			
			// Close off our select element	
			$html .= '
				</select>
			';
			return $html;
		}				
		
		public function get_autoplay_type($data) {
			$args = array(
				'label' => __('Link slides to', 'carrington-build'),
				'default' => (($data != '') ? $data : 'nothing')
			);
			$value = (($data != '') ? esc_attr($data) : 'true');
			return $this->dropdown('slideshow-link', $this->link_url_options, $value, $args);
		}
		
		private function slide_indicator($data = array()) {
			return (!empty($data['slideshow-display']) ? implode(' ', array_map('esc_attr', $data['slideshow-display'])) : 'false');
		}
		
		// Added by Steven for Randomize Slide
		private function slide_randomize($data = array()) {
			return (!empty($data['slideshow-random']) ? implode(' ', array_map('esc_attr', $data['slideshow-random'])) : 'no');
		}
		
		public function form($data) {
			$slideshow_link = '';
			if (!empty($data['slideshow-link'])) {
				$slideshow_link = implode(' ', array_map('esc_attr', $data['slideshow-link']));
			}
			
			// set default link target
			$slide_indicator = $this->slide_indicator($data);
			$randomize_slide = $this->slide_randomize($data);
			$html = '
					<label for="">Slide Indicator</label> 
					<div class="cfct-select-menu-wrapper">
						<ul style="display:inline">
							<li style="display:inline">
								<input type="radio" name="'.$this->get_field_name('slideshow-display').'" value="items" id="'.$this->get_field_name('slideshow-display').'_items" '.checked('items', $slide_indicator, false).' />
								<label for="'.$this->get_field_name('slideshow-display').'_items">'.__('Show', 'carrington-build').'</label>
							</li>
							<li style="display:inline">
								<input type="radio" name="'.$this->get_field_name('slideshow-display').'" value="false" id="'.$this->get_field_name('slideshow-display').'_false" '.checked('false', $slide_indicator, false).' />
								<label for="'.$this->get_field_name('slideshow-display').'_false">'.__('Hide', 'carrington-build').'</label>
							</li>
						</ul>
					</div>
					<div style="margin: 12px 0;">'					
						.$this->get_autoplay_type($slideshow_link).																				
					'</div>
					<label for="">Randomize Slides</label> 
					<div class="cfct-select-menu-wrapper">
						<ul style="display:inline">
							<li style="display:inline">
								<input type="radio" name="'.$this->get_field_name('slideshow-random').'" value="yes" id="'.$this->get_field_name('slideshow-random').'_yes" '.checked('yes', $randomize_slide, false).' />
								<label for="'.$this->get_field_name('slideshow-random').'_yes">'.__('Yes', 'carrington-build').'</label>
							</li>
							<li style="display:inline">
								<input type="radio" name="'.$this->get_field_name('slideshow-random').'" value="no" id="'.$this->get_field_name('slideshow-random').'_no" '.checked('no', $randomize_slide, false).' />
								<label for="'.$this->get_field_name('slideshow-random').'_no">'.__('No', 'carrington-build').'</label>
							</li>
						</ul>
					</div>
					';
			return $html;
		}
		
		public function admin_js() {
			$js = '
	// Module Extra: Custom CSS			
		// show/hide the pre-defined css list from toggle button
		$("#'.$this->get_field_id('class-list-toggle').'").live("click", function() {
			var tgt = $(this).siblings("div.cfct-select-menu");
			
			// check to see if any pre-defined class names need toggling before opening the drawer
			if (tgt.is(":hidden")) {
				toggle_css_module_options_list_use();
			}
			
			tgt.toggle();
			return false;
		});
		
		// show the pre-defined css list when input is focused
		$("#'.$this->get_field_id('slideshow-display').'").live("click", function(e) {
			var tgt = $(this).siblings("div.cfct-select-menu");
			if (tgt.is(":hidden")) {
				toggle_css_module_options_list_use();
				tgt.show();
			}
			return false;
		});
		
		// show the pre-defined css list when input is focused
		$("#'.$this->get_field_id('slideshow-random').'").live("click", function(e) {
			var tgt = $(this).siblings("div.cfct-select-menu");
			if (tgt.is(":hidden")) {
				toggle_css_module_options_list_use_random();
				tgt.show();
			}
			return false;
		});
		
		
		$("#'.$this->get_field_id('slideshow-display').'").live("keyup", function() {
			setTimeout(toggle_css_module_options_list_use, 200);
		});
		
		$("#'.$this->get_field_id('slideshow-random').'").live("keyup", function() {
			setTimeout(toggle_css_module_options_list_use_random, 200);
		});
		
		
		// catch a click in the popup and close the flyout
		$("#cfct-popup").live("click", function(){
			$("#'.$this->get_field_id('class-list-menu').':visible").hide();
		});

		var toggle_css_module_options_list_use = function() {
			var classes = $("#'.$this->get_field_id('slideshow-display').'").val().split(" ");
			$("#'.$this->get_field_id('class-list-menu').' a").each(function(){
				var _this = $(this);
				if ($.inArray(_this.text(),classes) == -1) {
					_this.removeClass("inactive");
				}
				else {
					_this.addClass("inactive");
				}
			});
		}
		
		var toggle_css_module_options_list_use_random = function() {
			var classes = $("#'.$this->get_field_id('slideshow-random').'").val().split(" ");
			$("#'.$this->get_field_id('class-list-menu').' a").each(function(){
				var _this = $(this);
				if ($.inArray(_this.text(),classes) == -1) {
					_this.removeClass("inactive");
				}
				else {
					_this.addClass("inactive");
				}
			});
		}

		// insert the clicked item in to the text-input
		$("#'.$this->get_field_id('class-list-menu').' a").live("click", function(e) {
			_this = $(this);
			if (!_this.hasClass("inactive")) {
				_this.addClass("inactive");
				var tgt = $("#'.$this->get_field_id('slideshow-display').'");
				tgt.val(tgt.val() + " " +_this.text());
			}
			return false;
		});
		
		$("#'.$this->get_field_id('class-list-menu').'").live("click", function() {
			return false;
		});	
				';
			return $js;
		}
		
		public function update($new_data, $old_data) {
			global $slideshow_autoplay;			
			$ret = array();
			$classes = explode(' ', $new_data['slideshow-display']);
			if (is_array($classes)) {
				foreach($classes as $class) {
					$ret['slideshow-display'][] = sanitize_title_with_dashes(trim(strip_tags($class)));
				}
			}
			
			$classes = explode(' ', $new_data['slideshow-link']);
			if (is_array($classes)) {
				foreach($classes as $class) {
					$ret['slideshow-link'][] = sanitize_title_with_dashes(trim(strip_tags($class)));
				}
			}

			// For Randomize Slide
			$classes = explode(' ', $new_data['slideshow-random']);
			if (is_array($classes)) {
				foreach($classes as $class) {
					$ret['slideshow-random'][] = sanitize_title_with_dashes(trim(strip_tags($class)));
				}
			}
			return $ret;
		}		
	}	

}

if (!class_exists('slideshow_autoplay_option')) {
	class slideshow_autoplay_option extends cfct_module_option {
		protected $autoplay_type_options = array('true' => 'Automatically', 'false' => 'Do not automatically');
	
		public function __construct() {
			parent::__construct('Manage Slide Rotation', 'slideshow-autoplay-option');
			add_filter('cfct-build-module-class', array($this, 'apply_classes'), 10, 2);					
		}

		public function apply_classes($class, $data) {
			if (!empty($data['cfct-module-options'][$this->id_base]['slideshow-autoplay'])) {
				$class .= ' '.implode(' ', $data['cfct-module-options'][$this->id_base]['slideshow-autoplay']);
			}
			return $class;
		}

		protected function dropdown($field_name, $options, $value = false, $args = '') {
			$defaults = array(
				'label' => '', // The text for the label element  
				'default' => null, // Add a default option ('all', 'none', etc.)
				'excludes' => array() // values to exclude from options
			);
			$args = array_merge($defaults, $args);
			extract($args);
			
			$options = (is_array($options)) ? $options : array();
			
			
			// Set a label if there is one
			$html = (!empty($label)) ? '<label for="'.$this->get_field_id($field_name).'">'.$label.' </label>' : '';
			// Start off the select element
			$html .= '
				<select class="'.$field_name.'-dropdown" name="'.$this->get_field_name($field_name).'" id="'.$this->get_field_id($field_name).'">
					';

			// Set a default option that's not in the list of options (i.e., all, none)
			if (is_array($default)) {
				$html .= '<option value="'.$default['value'].'"'.selected($default['value'], $value, false).'>'.esc_html($default['text']).'</option>';
			}
			
			// Loop through our options
			foreach ($options as $k => $v) {
				if (!in_array($k, $excludes)) {
					$html .= '<option value="'.$k.'"'.selected($k, $value, false).'>'.esc_html($v).'</option>';
				}
			}	
			
			// Close off our select element	
			$html .= '
				</select>
			';
			return $html;
		}				
		
		public function get_autoplay_type($data) {
			$args = array(
				'label' => __(' ', 'carrington-build'),
				'default' => (($data != '') ? $data : 'true')
			);

			$value = (($data != '') ? esc_attr($data) : 'true');
			return $this->dropdown('slideshow-autoplay', $this->autoplay_type_options, $value, $args);
		}
		
		public function form($data) {
			$slideshow_autoplay = '';
			if (!empty($data['slideshow-autoplay'])) {
				$slideshow_autoplay = implode(' ', array_map('esc_attr', $data['slideshow-autoplay']));
			}

			$slideshow_autoplay_delay = 3;
			if (!empty($data['slideshow-autoplay-delay'])) {
				$slideshow_autoplay_delay = implode(' ', array_map('esc_attr', $data['slideshow-autoplay-delay']));
			}
			
			$html = '
					<div class="cfct-select-menu-wrapper">'
						.$this->get_autoplay_type($slideshow_autoplay).									
						'<label for="">go to the next slide after</label>
						<input type="text" maxlength="3" style="width:40px;height:30px;text-align:right" class="no-button" name="'.$this->get_field_name('slideshow-autoplay-delay').'" id="'.$this->get_field_id('slideshow-autoplay-delay').'" value="'.$slideshow_autoplay_delay.'"  autocomplete="off" />
						<label for="">seconds.</label>						
					</div>';
			return $html;			
		}
		
		public function admin_js() {
			$js = '
	// Module Extra: Custom CSS			
		// show/hide the pre-defined css list from toggle button
		$("#'.$this->get_field_id('class-list-toggle').'").live("click", function() {
			var tgt = $(this).siblings("div.cfct-select-menu");
			
			// check to see if any pre-defined class names need toggling before opening the drawer
			if (tgt.is(":hidden")) {
				toggle_css_module_options_list_use();
			}
			
			tgt.toggle();
			return false;
		});
		
		// show the pre-defined css list when input is focused
		$("#'.$this->get_field_id('slideshow-autoplay').'").live("click", function(e) {
			var tgt = $(this).siblings("div.cfct-select-menu");
			if (tgt.is(":hidden")) {
				toggle_css_module_options_list_use();
				tgt.show();
			}
			return false;
		});
		
		$("#'.$this->get_field_id('slideshow-autoplay').'").live("keyup", function() {
			setTimeout(toggle_css_module_options_list_use, 200);
		});
		
		// catch a click in the popup and close the flyout
		$("#cfct-popup").live("click", function(){
			$("#'.$this->get_field_id('class-list-menu').':visible").hide();
		});

		var toggle_css_module_options_list_use = function() {
			var classes = $("#'.$this->get_field_id('slideshow-autoplay').'").val().split(" ");
			$("#'.$this->get_field_id('class-list-menu').' a").each(function(){
				var _this = $(this);
				if ($.inArray(_this.text(),classes) == -1) {
					_this.removeClass("inactive");
				}
				else {
					_this.addClass("inactive");
				}
			});
		}

		// insert the clicked item in to the text-input
		$("#'.$this->get_field_id('class-list-menu').' a").live("click", function(e) {
			_this = $(this);
			if (!_this.hasClass("inactive")) {
				_this.addClass("inactive");
				var tgt = $("#'.$this->get_field_id('slideshow-autoplay').'");
				tgt.val(tgt.val() + " " +_this.text());
			}
			return false;
		});
		
		$("#'.$this->get_field_id('class-list-menu').'").live("click", function() {
			return false;
		});	
				';
			return $js;
		}
		
		public function update($new_data, $old_data) {
			global $slideshow_autoplay;			
			$ret = array();
			$classes = explode(' ', $new_data['slideshow-autoplay']);
			if (is_array($classes)) {
				foreach($classes as $class) {
					$ret['slideshow-autoplay'][] = sanitize_title_with_dashes(trim(strip_tags($class)));
				}
			}

			$classes = explode(' ', $new_data['slideshow-autoplay-delay']);
			if (is_array($classes)) {
				foreach($classes as $class) {
					$ret['slideshow-autoplay-delay'][] = sanitize_title_with_dashes(trim(strip_tags($class)));
				}
			}						
			
			return $ret;
		}		
	}	

}
	
if (!class_exists('slideshow_transition_option')) {
	class slideshow_transition_option extends cfct_module_option {
		protected $transition_type_options = array(
			'fade' => 'Fade',
			'slide' => 'Slide',
			'drop' => 'Drop'		
		);
	
		public function __construct() {
			parent::__construct('Manage Transition Effect', 'slideshow-transition-option');
			add_filter('cfct-build-module-class', array($this, 'apply_classes'), 10, 2);					
		}

		public function apply_classes($class, $data) {
			if (!empty($data['cfct-module-options'][$this->id_base]['slideshow-transition'])) {
				$class .= ' '.implode(' ', $data['cfct-module-options'][$this->id_base]['slideshow-transition']);
			}
			return $class;
		}

		protected function dropdown($field_name, $options, $value = false, $args = '') {
			$defaults = array(
				'label' => '', // The text for the label element  
				'default' => null, // Add a default option ('all', 'none', etc.)
				'excludes' => array() // values to exclude from options
			);
			$args = array_merge($defaults, $args);
			extract($args);
			
			$options = (is_array($options)) ? $options : array();
			
			
			// Set a label if there is one
			$html = (!empty($label)) ? '<label for="'.$this->get_field_id($field_name).'">'.$label.' </label>' : '';
			// Start off the select element
			$html .= '
				<select class="'.$field_name.'-dropdown" name="'.$this->get_field_name($field_name).'" id="'.$this->get_field_id($field_name).'">
					';

			// Set a default option that's not in the list of options (i.e., all, none)
			if (is_array($default)) {
				$html .= '<option value="'.$default['value'].'"'.selected($default['value'], $value, false).'>'.esc_html($default['text']).'</option>';
			}
			
			// Loop through our options
			foreach ($options as $k => $v) {
				if (!in_array($k, $excludes)) {
					$html .= '<option value="'.$k.'"'.selected($k, $value, false).'>'.esc_html($v).'</option>';
				}
			}	
			
			// Close off our select element	
			$html .= '
				</select>
			';
			return $html;
		}				
		
		public function get_transition_type($data) {
			$args = array(
				'label' => __(' ', 'carrington-build'),
				'default' => (($data != '') ? $data : 'fade')
			);

			$value = (($data != '') ? esc_attr($data) : 'fade');
			return $this->dropdown('slideshow-transition', $this->transition_type_options, $value, $args);
		}
		
		public function form($data) {
			$slideshow_transition = '';
			if (!empty($data['slideshow-transition'])) {
				$slideshow_transition = implode(' ', array_map('esc_attr', $data['slideshow-transition']));
			}

			$slideshow_transition_delay = 1;
			if (!empty($data['slideshow-transition-delay'])) {
				$slideshow_transition_delay = implode(' ', array_map('esc_attr', $data['slideshow-transition-delay']));
			}
			
			$html = '
					<label for="">Show</label> 
					<div class="cfct-select-menu-wrapper">'
						.$this->get_transition_type($slideshow_transition).									
						'<label for="">efect between slides for</label>
						<input type="text" maxlength="3" style="width:40px;height:30px;text-align:right" class="no-button" name="'.$this->get_field_name('slideshow-transition-delay').'" id="'.$this->get_field_id('slideshow-transition-delay').'" value="'.$slideshow_transition_delay.'"  autocomplete="off" />
						<label for="">seconds.</label>						
					</div>';
			return $html;
		}
		
		public function admin_js() {
			$js = '
	// Module Extra: Custom CSS			
		// show/hide the pre-defined css list from toggle button
		$("#'.$this->get_field_id('class-list-toggle').'").live("click", function() {
			var tgt = $(this).siblings("div.cfct-select-menu");
			
			// check to see if any pre-defined class names need toggling before opening the drawer
			if (tgt.is(":hidden")) {
				toggle_css_module_options_list_use();
			}
			
			tgt.toggle();
			return false;
		});
		
		// show the pre-defined css list when input is focused
		$("#'.$this->get_field_id('slideshow-transition').'").live("click", function(e) {
			var tgt = $(this).siblings("div.cfct-select-menu");
			if (tgt.is(":hidden")) {
				toggle_css_module_options_list_use();
				tgt.show();
			}
			return false;
		});
		
		$("#'.$this->get_field_id('slideshow-transition').'").live("keyup", function() {
			setTimeout(toggle_css_module_options_list_use, 200);
		});
		
		// catch a click in the popup and close the flyout
		$("#cfct-popup").live("click", function(){
			$("#'.$this->get_field_id('class-list-menu').':visible").hide();
		});

		var toggle_css_module_options_list_use = function() {
			var classes = $("#'.$this->get_field_id('slideshow-transition').'").val().split(" ");
			$("#'.$this->get_field_id('class-list-menu').' a").each(function(){
				var _this = $(this);
				if ($.inArray(_this.text(),classes) == -1) {
					_this.removeClass("inactive");
				}
				else {
					_this.addClass("inactive");
				}
			});
		}

		// insert the clicked item in to the text-input
		$("#'.$this->get_field_id('class-list-menu').' a").live("click", function(e) {
			_this = $(this);
			if (!_this.hasClass("inactive")) {
				_this.addClass("inactive");
				var tgt = $("#'.$this->get_field_id('slideshow-transition').'");
				tgt.val(tgt.val() + " " +_this.text());
			}
			return false;
		});
		
		$("#'.$this->get_field_id('class-list-menu').'").live("click", function() {
			return false;
		});	
				';
			return $js;
		}
		
		public function update($new_data, $old_data) {
			$ret = array();
			$classes = explode(' ', $new_data['slideshow-transition']);
			if (is_array($classes)) {
				foreach($classes as $class) {
					$ret['slideshow-transition'][] = sanitize_title_with_dashes(trim(strip_tags($class)));
				}
			}
			
			$classes = explode(' ', $new_data['slideshow-transition-delay']);
			if (is_array($classes)) {
				foreach($classes as $class) {
					$ret['slideshow-transition-delay'][] = sanitize_title_with_dashes(trim(strip_tags($class)));
				}
			}			
			return $ret;
			
		}		
	}	

}

?>