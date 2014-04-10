<?php
/*
* Plugin Name: Isotope Shortcode
* Plugin URI: 
* Description: Isotope shortcode plugin.
* Version: 0.9.5
* Author: UBC CMS + David Brabbins
* Author URI:http://cms.ubc.ca
*
* 
* This program is free software; you can redistribute it and/or modify it under the terms of the GNU
* General Public License as published by the Free Software Foundation; either version 2 of the License,
* or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
* even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*
* You should have received a copy of the GNU General Public License along with this program; if not, write
* to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*
* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


/**
 * Educ_Iso_Shortcode class.
 */
?>
<?php 
class Educ_Iso_Shortcode {
	static $iso_support;
	public $iso_type = null;
	public $odd_or_even = 0;
	public $content = '';
	public $iso_attributes = array();
	public $error = null;
	public $iso_query;
	public $total_pages;
	public $json_output = array();
	public $grid_column = 0;
	public $counter = 0;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		/* Register shortcodes on 'init'. */
		add_action( 'init', array( &$this, 'register_shortcode' ), 20 );

		/* Apply filters to the column content. */
		add_filter( 'iso_content', 'wpautop' );
		add_filter( 'iso_content', 'shortcode_unautop' );
		add_filter( 'iso_content', 'do_shortcode' );
		add_filter( 'iso_content', array( &$this, 'remove_wanted_p' ) );
		
		add_filter( 'post_thumbnail_html',  array( &$this,'feed_post_thumbnail_html' ) , 10 , 5 );
		add_filter( 'the_author', array( &$this,'feed_post_author' ) , 10 , 5);
		add_filter( 'the_modified_author', array( &$this,'feed_post_author' ) , 10 , 5);
		add_filter( 'get_the_author_display_name' , array( &$this,'feed_post_author' ) , 10 , 5);

		add_filter( 'the_author_posts_link' , array( &$this,'feed_post_author_link' ) , 10 , 5);
		add_filter( 'author_link' , array( &$this,'feed_post_author_link' ) , 10 , 5);
		
		//  Register and Enqueue
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'iso_enqueue'));


		

		//  There is no point trying to get links to categories or tags from feed since you can't get one reliably. =(

	}
	
	function remove_wanted_p( $content ){
		
		$content = trim($content);

		// remove the opening <p> tag
		if( strcasecmp(substr($content, 0, 3), '<p>') === 0 || strcasecmp(substr($content, 0, 3), '<P>') === 0 )
			$content = substr($content, 3);
		// remove the closing </p> tag
		if( strcasecmp(substr($content, -4), '</p>') === 0 || strcasecmp(substr($content, -4), '</P>') === 0)
			$content = substr($content, 0, -4);
		
		return $content;
	}

	/**
	 * has_shortcode function.
	 *
	 * @access public
	 * @param mixed $shortcode
	 * @return void
	 */
	function has_shortcode( $shortcode ) {
		global $shortcode_tags;

		return ( in_array( $shortcode, array_keys ($shortcode_tags ) ) ? true : false);
	}

	/**
	 * add_shortcode function.
	 *
	 * @access public
	 * @param mixed $shortcode
	 * @param mixed $shortcode_function
	 * @return void
	 */
	function add_shortcode( $shortcode, $shortcode_function ) {

		if( !$this->has_shortcode( $shortcode ) )
			add_shortcode( $shortcode, array( &$this, $shortcode_function ) );

	}

	/**
	 * register_shortcode function.
	 *
	 * @access public
	 * @return void
	 */
	public function register_shortcode() {
		
		/* don't do anything if the shortcode exists already */
		$this->add_shortcode( 'iso', 'iso_shortcode' );
		$this->add_shortcode( 'odd-even', 'odd_even_shortcode' );
		$this->add_shortcode( 'get_the_date','get_the_date_shortcode');
		$this->add_shortcode( 'plain_tags_slug','get_plain_tags_slug_shortcode');
		$this->add_shortcode( 'plain_cat_slug','get_cat_slug_shortcode');
	}

	/**
	 * odd_even_shortcode function.
	 *
	 * @access public
	 * @return void
	 */
	public function odd_even_shortcode(){

		if ( $this->odd_or_even % 2 )
			return 'odd';
		else
			return 'even alt';

	}
	/**
	 * get_the_date_shortcode function.
	 * Gets post tags in plain text
	 * @access public
	 * @return void
	 */
	function get_the_date_shortcode() {

		return get_the_date();
	}
	/**
	 * get_plain_tags_shortcode function.
	 * Gets post tags in plain text
	 * @access public
	 * @return void
	 */
	function get_plain_tags_shortcode() {

		$htmlstr = '';
		$posttags = get_the_tags();
		if ($posttags) {
			foreach($posttags as $tag) {
				$htmlstr .= $tag->name . ' '; 
			}
		}
		return $htmlstr;
	}
	/**
	 * get_plain_tags_slug_shortcode function.
	 * Gets post tags id
	 * @access public
	 * @return void
	 */
	function get_plain_tags_slug_shortcode() {

		$htmlstr = '';
		$posttags = get_the_tags();
		if ($posttags) {
			foreach($posttags as $tag) {
				$htmlstr .= $tag->slug . ' '; 
			}
		}
		return $htmlstr;
		
	}
	/**
	 * get_category_name_shortcode function.
	 * 
	 * @access public
	 * @param mixed $attr
	 * @return void
	 */
	function get_cat_slug_shortcode() {
		$output_cat = ' ';
		$categories = get_the_category();
			if($categories){
				foreach($categories as $category) {
					$output_cat .= $category->slug.' ';
				}
		}
		return  trim($output_cat);
	}
	
	/**
	 * iso_shortcode function.
	 *
	 * @access public
	 * @param mixed $atts
	 * @param mixed $content (default: null)
	 * @return void
	 */
	public function iso_shortcode( $atts, $content = null ) {
		global $wp_query;
		// $atts['pagination'] = ( isset($atts['pagination']) ? (bool)$atts['pagination']: false );

		// $this->iso_attributes = array(); // always start with an empty array


		$is_page = $wp_query->is_page;
		$is_single = $wp_query->is_single;
		$is_singular = $wp_query->is_singular;
		$wp_query->is_page = false;
		$wp_query->is_single = false;

		$wp_query->is_singular = false;
		$this->content = $content;
		
		$this->iso_attributes = shortcode_atts(array(
				"query" 		=> '',
				//"rss" 			=> '',
				'container'		=> 'iso',
				'iso_object'	=> 'boxey',
				"view" 			=> 'simple',
				"gutter"		=> '20',
				'box_width' 	=> '260',
				'filter'		=> false,
				'filter_by' 	=> "tags",
				'filter_title' 	=> 'Filter the Board:',
				'searchable' 	=> false,
				'show_date' 	=> true,
				'help' 			=> false,
				"pagination" 	=> false,
				"num" 			=> 10,
				"error"			=> '',
				"taxonomy"		=> '',
				'json_var'  	=> 'iso_json',
				'author'	 	=> '',
				'time_after' 	=> '',
				'time_before'	=> '',
				'time_inclusive'=> true
			), $atts );
									
		if( in_array( $this->iso_attributes['pagination'], array( 'false','0','null', false ) ) )
			$this->iso_attributes['pagination'] = false;
		
		if( in_array( $this->iso_attributes['time_inclusive'], array( 'false','0','null', false ) ) )
			$this->iso_attributes['time_inclusive'] = false; 
		
		if( in_array( $this->iso_attributes['help'], array( 'false','0','null', false ) ) )
			$this->iso_attributes['help'] = false;

		if( in_array( $this->iso_attributes['show_date'], array( 'false','0','null', false ) ) )
			$this->iso_attributes['show_date'] = false; 

		if( in_array( $this->iso_attributes['searchable'], array( 'false','0','null', false ) ) )
			$this->iso_attributes['searchable'] = false;
		
		if( in_array( $this->iso_attributes['filter'], array( 'false','0','null', false ) ) )
			$this->iso_attributes['filter'] = false; 
		
		if( empty( $this->iso_attributes['container'] ) ) {
			$this->iso_attributes['container'] = 'iso';
		}
		
		if( empty( $this->iso_attributes['iso_object'] ) ) {
			$this->iso_attributes['iso_object'] = 'boxey';
		}
			
		if( empty( $this->iso_attributes['query'] ) && empty( $this->iso_attributes['rss'] ) ) {
			return '<span class="error no-data">'.__('Please specify a query for your [ iso ] shortcode.', 'iso-shortcode').'</span>';
		}
		
		if ( !empty($this->iso_attributes['error']) )
			$this->error = $this->iso_attributes['error'];

		ob_start();

		if( !empty( $this->iso_attributes['query'] ) ):

			$this->wp_iso();

		elseif( !empty($this->iso_attributes['rss']) ):
			$this->iso_attributes['rss'] = html_entity_decode( $this->iso_attributes['rss'] );

			$this->rss_iso();
		endif;
		// revert back to normal
		$wp_query->is_singular = $is_singular;
		$wp_query->is_page = $is_page;
		$wp_query->is_single = $is_single;
		return 	ob_get_clean();
	}
		
	/**
	 * wp_iso function.
	 *
	 * @access public
	 * @return void
	 */
	function wp_iso(){
		$this->iso_type = 'wp';
		// de-funkify $query - taken from http://digwp.com/2010/01/custom-query-shortcode/ needed to get it working better ideas ?
		$query = html_entity_decode( $this->iso_attributes['query'] );
		$query = preg_replace_callback('~&#x0*([0-9a-f]+);~i', function ($matches) {
            return chr(hexdec($matches[0]));
        }, $query);
		
		$query = preg_replace_callback('~&#0*([0-9]+);~i', function ($matches) {
            return chr($matches[0]);
        }, $query);
		
		if( strpos( $query, 'posts_per_page=' ) === false ):
			 $query .= "&posts_per_page=".$this->iso_attributes['num'];
		endif;
		if( $this->iso_attributes['pagination'] ):
			$query .= "&paged=".get_query_var( 'paged' );
		endif;		
		
		if( $this->iso_attributes['author'] ):
			
			switch( $this->iso_attributes['author'] ) {
				case 'current_user':
					
					$current_user = wp_get_current_user();
					
					if($current_user->ID > 0 ) {
						$query .= "&author=".$current_user->ID;
					} else {
						$this->show_error();
						return;
					}
				break;
				default:
					if( is_numeric( $this->iso_attributes['author']) ) {
						$query .= '&author='.$this->iso_attributes['author'];
					} else {
						$query .= '&author_name='.$this->iso_attributes['author'];
					}
				break;
			}
			
		endif;
		$query_array =  wp_parse_args( $query );
		
		if( $this->iso_attributes['time_before'] || $this->iso_attributes['time_after']):
			$query_array['date_query'] = array(
								array(
									'after'     => $this->iso_attributes['time_after'],
									'before'    => $this->iso_attributes['time_before'],
									'inclusive' => $this->iso_attributes['time_inclusive'],
								),
							);
		endif;
		
		$this->iso_query = new WP_Query( $query_array );
		
		$this->total_pages = $this->iso_query->max_num_pages;
		
		if ( $this->iso_query->have_posts() ) :
		$this->iso_script();
		$this->iso_help();
		$this-> iso_filter();
		$this-> search_box();
		echo '<div class="'. $this->iso_attributes['container'].' '. $this->iso_attributes['view'] .' iso_shortcode" style="display: block; margin: 0 auto;">';
		if ($this->iso_attributes['view'] == 'list') :
			echo '<ul>';
		endif;
			while ( $this->iso_query->have_posts() ) : $this->iso_query->the_post();

				$this->display_output();
				$this->odd_or_even++;
				$this->counter++;
				

			endwhile;
			echo '</div>';
		if ($this->iso_attributes['view'] == 'list') :
			echo '</ul>';
		endif;
			// output json 
			if( !empty( $this->json_output ) && 'json' == $this->iso_attributes['view'] ):
			
				echo '<script type="text/javascript" >';
				echo 'var '.$this->iso_attributes['json_var'].' = '.json_encode( $this->json_output );
				echo '</script>';
				$this->json_output = array();
			endif;
			
			$this->paginate();

		else:
			$this->show_error();
		endif;
		

		wp_reset_query();

	}
	/**
	 * iso_filter function.
	 *
	 * @access public
	 * @return void
	 */
	function iso_filter(){
			switch( $this->iso_attributes['filter'] ){

				default:
				break;

				case "links":
					$this->iso_filter_links();
				break;
				
				case "dropdown":
					$this->iso_filter_dropdown();
				break;
			}
	}
	/**
	 * iso_filter_links function.
	 * Links view for filter
	 * @access public
	 * @return void
	 */
	function iso_filter_links() { ?>
    
			<div class="navbar">
			<div class="iso-nav">
			<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse-iso">
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
				  	</a>
				<a class="filter-title brand" data-filter-value="*"><?php echo $this->iso_attributes['filter_title'] ?></a>
				<div class="services-menu expand nav-collapse collapse nav-collapse-iso">
					<div id="menu-top-service-menu" class="iso-links">
						<a href="#" data-filter-value="*" class="current"><i class="icon-th"></i>&nbsp; All</a>
							<?php switch ( $this->iso_attributes['filter_by'] ){
                                default: "tags";
                                break;
                                        
                                case "tags":
                                case "tag":
                                         $get_arr_tags = ( explode( ',', $this->iso_query->get( 'tag' ) ) );
                                break;
                                        
                                case "category":
                                case "categories":
								case "cat":
                                         $get_arr_tags = ( explode( ',', $this->iso_query->get( 'category' ) ) );
                                break;
                            }
                                            
                       foreach ($get_arr_tags as $var) {
                           $var_rep = str_replace('-', ' ', $var);
                           $var_rep = ucwords(strtolower($var_rep));
			echo '			<a href="#" data-filter-value=".'. $var .'">'. $var_rep .'</a>';
			   } ?>
					</div>
				</div>	
			</div>
			</div>
	<?php } 
	/**
	 * iso_filter_dropdowm function.
	 * Dropdown view for filter
	 * @access public
	 * @return void
	 */
	function iso_filter_dropdown() { ?>
		<div class="btn-group iso-dropdown">
		  <button class="btn btn-large"><a class="filter-title" data-filter-value="*"><?php echo  $this->iso_attributes['filter_title'] ?></a></button>
		  <button class="btn btn-large dropdown-toggle" data-toggle="dropdown">
			<i class="icon-caret-down"></i>
		  </button>
		  <ul class="dropdown-menu iso-links">
				<?php switch ( $this->iso_attributes['filter_by'] ){
					default: "tags";
					break;
							
					case "tags":
					case "tag":
							 $get_arr_tags = ( explode( ',', $this->iso_query->get( 'tag' ) ) );
					break;
							
					case "category":
					case "categories":
					case "cat":
							 $get_arr_tags = ( explode( ',', $this->iso_query->get( 'category' ) ) );
					break;
				}
								
			   foreach ($get_arr_tags as $var) {
				   $var_rep = str_replace('-', ' ', $var);
					echo '	<li><a class="'. $var .'" data-filter-value=".'. $var .'">'. $var_rep .'</a></li>';
				} ?>
		  </ul>
		</div>
	<?php }
	/**
	 * search_box function.
	 * search iso attribute true
	 * @access public
	 * @return void
	 */
	function search_box() { 
		if	($this->iso_attributes['searchable'] == "true") : ?>

		<section>
			<form class="form-search iso-form">
				<input class="search-query iso-text-field" type="text" name="search" id="search" value="" placeholder="Start typing to search..." autocomplete="off" /> 
				<a class="btn iso-search-button" href="#" id="showAll">Show all</a>
			</form>
		</section>

		<p id="noMatches" class="alert alert-info alert-block iso-search-alert" style="display:none;"><i class="icon-meh icon-4x"></i> No matches found. Please delete your results or press the "Show All".</p>

	<?php 
	endif;
	}


	/**
	 * iso_help function.
	 * Dropdown view for filter
	 * @access public
	 * @return void
	 */
	function iso_help() {
		if	($this->iso_attributes['help'] == "true") : ?> 
		<div class="alert alert-block iso-help">
			<h2>
  			Iso Plugin Shortcode Help 
			<span class="icon-stack">
	  			<i class="icon-circle icon-stack-base"></i>
	  			<i class="icon-code" style="color: #FFF"></i>
	  		</span>
  			<small>...because everyone needs a little sometimes.</small></h2>
			<p class="lead">Create a dynamic and interactive way to present posts.</p><hr />
			<h5><i class="icon-envelope"></i> Support, inquires or feedback please email: <a href="mailto:david.brabbins@ubc.ca" title="support">david.brabbins@ubc.ca</a></h5>
			<hr />
		<h3>Breakdown</h3>
			<p>[iso <strong>query</strong>="posts_per_page=5&tag=tag-1,tag2" <strong>container</strong>="bus" <strong>view</strong>="simple_modal" <strong>gutter</strong>="20" <strong>box_width</strong>="265" <strong>filter</strong>="dropdown" <strong>filter_by</strong>="tags" <strong>filter_title</strong>="The Filter Title" <strong>help</strong>="false"]</p><br />
			<h4 style="color: #002145">Attributes</h4>
				<ul>
					<li><strong>query</strong>: please go <a href="http://wiki.ubc.ca/Documentation:UBC_Content_Management_System/CLF_Theme/Loop"><strong>here</strong></a> for options on the query.</li>
					<li><strong>container</strong>: sets the container surrounding the posts boxes. (The default is "iso")</li>
					<li><strong>view</strong>: A preset look and feel controlled by the options listed below (<strong>Please note:</strong> that if no view or a spelling mistake is made then the view will default to custom_modal):
						<ul>
							<li>simple (default): creates a simple clean look.</li>
							<li>simple_modal: creates a simple clean look while activating modal box pop-up to posts.</li>
							<li>block: creates a blocky clean look.</li>
							<li>block_modal: a blocky clean look while activating modal box pop-up to posts.</li>
							<li>custom_modal: a view with minor CSS applied plus modal box pop-up to posts.</li>
							<li>custom: a view with minor CSS.</li>
							<li>list: creates a list of posts that is filterable.</li>
						</ul>
					</li>
					<li><strong>gutter</strong>: specify the gutter width (this option only accepts numerical values)</li>
					<li><strong>box_width</strong>: specify the box width (this option only accepts numerical values)</li>
					<li><strong>filter</strong>: sets up a list of links or a drop down to sort post on the fly. Currently the list generated order they are type in the query. <strong>Filter uses tags or categories for filtering. </strong> Options listed below: 
						<ul>
							<li>links</li>
							<li>dropdown</li>
						</ul>
					</li>
					<li><strong>filter_by</strong>: You can either filter by category or tags. The Filter will use what every category or tags are typed in the query</li>
						<ul>
							<li>cat, categories, category</li>
							<li>tag, tags</li>
						</ul>
					</li>
					<li><strong>filter_title</strong>: customize the title or text before the filter choice (Leave blank to show nothing).</li>
					<li><strong>help</strong>: shows the attributes or settings for the shortcode on the webpage. (This message appears on the page that the iso shortcode is being setup on. Use preview to to view the help info.) Either:</li>
						<ul>
							<li>true</li>
							or
							<li>false</li>
						</ul>
				</ul>
                    <h3>Customizing</h3>
                    <p>New shortcodes have been created so the filter options can be used in a custom setup.</p>
                    <ul>
                        <li><strong>[get_the_date]</strong> : Similar  to [the_date] only this will show the date for each post in the loop; regardless if the post was posted on the same day as the previous post.<br /></li>
                        <li><strong>[plain_tags_slug]</strong> : Gets the post tags slug (<strong>NOTE</strong>: this tag is important to use from within the loop. More below.)<br /></li>
                        <li><strong>[plain_cat_slug]</strong> : Gets the categories slug. <br /></li>
                    </ul>
                    <br /><h4 style="color: #002145">How to:</h4>
                    <h5>Calling Isotope</h5>
	                   <p>When customizing the iso shortcode, it is important to use the <strong>boxey</strong> class in the object or box you with to use with isotope (see example below). <strong>I!f boxey is not used, Isotope may not work properly!</strong><br />
                   	<h5>Custom Filtering</h5>
	               		In order to filter content in custom view, and depending on which <strong>filter_by</strong> option you choose, either use <strong>[plain_tags_shortcode]</strong> or <strong>[plain_cat_slug]</strong> in the object or box that that needs the filter (see example below).<br /></p>
	               		<p>The filter will still need to told what to filter by in the shortcode attribute.</p>
	                   <p><strong>Most other shortcodes that worked in the <strong>loop shortcode</strong> should work here as well.</strong></p>
                   <h5><strong>For Example:</strong></h5>
	                  <p>[iso query="posts_per_page=15&orderby=rand&tag=events,media,news,video-games,alumni" container="iso" gutter="25" view="simple_modal" box_width="260" filter="dropdown" help="true" pagination="false" filter_title="Why not filter something?"]
<span>&#60;div class="<strong>boxey</strong> <strong>[plain_tags_slug]</strong>"&#62;</span>&#60;h2&#62;&#60a href="[permalink]" title="[the_title]">[the_title]&#60/a&#62; &#60;small&#62;<strong>[get_the_date]</strong>&#60;/small&#62;/h2&#62;
[the_excerpt]&#60;/div&#62;
[/iso]</p><br />
					<div class="alert alert-error"><h3 style="color: #FFF"><i class="icon-warning-sign"></i> Rules of Engagement:</h3>
						<p>This shortcode is meant for only one use per page (<strong>Fun results will ensue if you try more than one!</strong>)</p></div>

					<div class="alert alert-info"><h5 class="lead"><i class="icon-envelope"></i> Support, inquires or feedback please email: <a href="mailto:david.brabbins@ubc.ca" title="support">david.brabbins@ubc.ca</a></h5></div>


			</div><?php 
		
		elseif ($this->iso_attributes['help'] == NULL) :
		
	endif;
	}
	/**
	 * rss_iso function.
	 *
	 * @access public
	 * @return void
	 */
//	function rss_iso(){
//		$this->iso_type = 'rss';
//
//		$rss_mock_query = new WP_Query('cat=111111111111111111');
//
//		$rss = fetch_feed( $this->iso_attributes['rss'] );
//		$num = $this->iso_attributes['num'];
//		
//		$paged = get_query_var( 'paged' );
//		
//		if($paged > 0 )
//			$paged--;
//		else
//			$paged = 0;
//		
//		$start = $num*$paged;
		
		// todo: make pagination work for rss as well

//		if (!is_wp_error( $rss ) ) :
//
//			$maxitems = $rss->get_item_quantity();
//			$rss_items = $rss->get_items($start, $num);
//		
//		endif;
//		
//		$this->total_pages = ceil ( $maxitems / $num );
//		$found_posts = 0;
//		foreach ($rss_items as $item):
//
//
//			$post = (object) null;
//			$post->ID = $item->get_id();
//			$post->post_type = 'post';
//			$post->is_iso_shortcode_feed = true;
//			$post->post_title = $item->get_title();
//			$post->guid = $item->get_permalink();
//			$post->post_content = apply_filters('the_content', $item->get_content() );
//			$post->post_excerpt = apply_filters('the_excerpt', $item->get_description() );
//			$post->taxonomy = array();// ();
//			$post->post_date = $item->get_date('Y-m-d H:i:s');
//			$post->post_content_filtered = $item;
//			$rss_mock_query->post = $post;
//
//			array_push($rss_mock_query->posts, $post);
//			$found_posts++;
//
//		endforeach;

		// $rss_mock_query->post_count = $maxitems;
//		$rss_mock_query->found_posts = ''. $found_posts;
//		$rss_mock_query->post_count = $num;
//		
//
//		if ( $rss_mock_query->have_posts() ) :
//			while ( $rss_mock_query->have_posts() ) : $rss_mock_query->the_post();
//
//				$this->display_output();
//				$this->odd_or_even++;
//				$this->counter++;
//
//			endwhile;
//
//			$this->paginate();
//
//		else:
//			$this->show_error();
//		endif;
//		
//		wp_reset_query();
//	}
	
	/**
	 * paginate function.
	 *
	 * @access public
	 * @param mixed $iso_query
	 * @return void
	 */
	function paginate() {

		if( !$this->iso_attributes['pagination'] )
			return;

		global $wp_query, $wp_rewrite;

		$wp_query->query_vars['paged'] > 1 ? $current = $wp_query->query_vars['paged'] : $current = 1;
	
		$pagination = array(
			'before' => '',
			'after'  => '',
			'base' => @add_query_arg('page','%#%'),
			'format' => '',
			'total' => $this->total_pages,
			'current' => $current,
			'show_all' => false,
			'type' => 'list',
			'next_text' => '&raquo;',
			'prev_text' => '&laquo;'
		);
		
		if( !empty( $this->iso_query->query_vars['s'] ) ):
			$pagination['add_args'] = array( 's' => get_query_var( 's' ) );
		endif;

		if( $wp_rewrite->using_permalinks() )
			$pagination['base'] = user_trailingslashit( trailingslashit( remove_query_arg( 's', get_pagenum_link( 1 ) ) ) . 'page/%#%/', 'paged' );
		
		$pagination = apply_filters( "iso-shortcode-pagination", $pagination );
		
 		echo $pagination['before'];
		echo paginate_links( $pagination );
		echo $pagination['after'];
		
	}
	/**
	 * show_error function.
	 *
	 * @access public
	 * @return void
	 */
	function show_error(){ ?>
		<p class="no-data">
			<?php
			if( empty( $this->error ) ):
				 _e('Sorry, no posts matched your criteria.', 'iso-shortcode');
			 else:
			 	echo $this->error;
			 endif;
				?>
		</p><!-- .no-data -->
		<?php
	}
	/**
	 * display_output function.
	 *
	 * @access public
	 * @return void
	 */
	function display_output(){
		global $post;
		
		if( !$post->ID )
			return '';
		
		if( $this->content  ):

			echo apply_filters( 'iso_content', $this->content );

		else:
			switch( $this->iso_attributes['view'] ){
				
				default; 
					$this->custom_modal_output();
				break;
					
				case 'simple':
				case 'simple_modal':
					$this->simple_modal_output();
				break;
				
				case 'block':
				case 'block_modal':
					$this->block_modal_output();
				break;
				
				case 'custom_modal':
				case 'custom':
					$this->custom_modal_output();
				break;
				
				case 'list':
					$this->list_output();
				break;
				
			}
		endif;
	}
	
	function modal_insert() { 
	$adjacent_post = get_adjacent_post(false,'',true);
		// Categories Setup
		$categories = get_the_category();
			$output_cat = ' ';
			if($categories){
				foreach($categories as $category) {
					$output_cat .= $category->slug.' ';
				}
					$the_category_slug =  trim($output_cat);
			}
			
		$the_category_header = get_the_category();
			$output_cat_header = ' ';
			if($the_category_header){
				foreach($the_category_header as $cat_head) {
					$output_cat_header .= '<a href="'.get_category_link( $cat_head->term_id ).'" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $cat_head->name ) ) . '">'.$cat_head->cat_name.'</a>';
				}
					$the_category_link =  trim($output_cat_header);
			}
			
		// Tags Setup
		$output_tag = '';
		$posttags = get_the_tags();
		if ($posttags) {
			foreach($posttags as $tag) {
				$output_tag .= $tag->slug . ' '; 
			}
		}
		$the_tag_slug = $output_tag;
		 
		$the_tag_header = get_the_tags();
			$output_tag_header = ' ';
			if($the_tag_header){
				foreach($the_tag_header as $tag_head) {
					$output_tag_header .= '<a href="'.get_tag_link( $tag_head->term_id ).'" title="' . $tag_head->name . '">'.$tag_head->name.'</a>';
				}
					$the_tag_links =  trim($output_tag_header);
			}

		// Output Setup
		switch ( $this->iso_attributes['filter_by'] ){
        	default: "tags";
            break;
                                        
            case "tags":
            case "tag":
				$the_iso_filter = $the_tag_slug;
				$the_iso_header_tag = $the_tag_links;
            break;
                                        
           case "category":
           case "categories":
		   case "cat":
				$the_iso_filter = $the_category_slug;
				$the_iso_header_tag = $the_category_link;
           break;
       }		
		
		//$iso_excerpt = the_excerpt();
		//$trimmed_excerpt = wp_trim_words( $iso_excerpt, 10, '...' );
	?>
             <!-- Start Modal -->
            <div id="<?php the_ID(); ?>" class="<?php echo $this->iso_attributes['view']; ?> modal fade hide container" tabindex="-1" role="dialog" aria-labelledby="myModalLabel_<?php the_ID(); ?>" aria-hidden="true">
            <div class="<?php echo $the_iso_filter; ?>">
              <div class="modal-header <?php echo $the_iso_filter; ?>">
                  <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                   <h3 id="myModalLabel_<?php the_ID(); ?>" class="modal-label header-tags"> <?php echo $the_iso_header_tag; ?></h3>
              </div>
      		  <!-- end #modal-header -->
              <div class="modal-body">
              	<div class="row-fluid">
                  <?php if ( has_post_thumbnail() ) :?>
                  <div class="span8">
                    <h3 class="post-title"><?php the_title(); ?></h3>
                    <?php if ($this->iso_attributes['show_date'] == 'true') : ?><p class="date"><?php echo get_the_date(); ?></p><?php endif;?>
                    <div class="modal-body-content">
                        <?php the_content(); ?>
                    </div>
                  </div>
                  <!-- end .span9 -->
                  <div class="span4"><?php if ($this->iso_attributes['view'] == "custom_modal" ):
						echo the_post_thumbnail('full', array('class' =>'modal-img-custom'));
					else: 
						echo the_post_thumbnail('full', array('class' =>'img-circle'));
					endif; ?></div>
                  <?php  else: ?>
                  <div>
                     <h3 class="post-title"><?php the_title(); ?></h3>
                     <?php if ($this->iso_attributes['show_date'] == 'true') : ?><p class="date"><?php echo get_the_date(); ?></p><?php endif;?>
                    <div class="modal-body-content">
                        <?php the_content(); ?>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
                <!-- end .row-fluid --> 
              </div>
              <!-- end .modal-body -->
              <div class="modal-footer">
              <div class="nav-previous alignleft">Read Next: <a href="#<?php echo $adjacent_post->ID; ?>" title="Read <?php echo $adjacent_post->post_title; ?>" role="button" data-toggle="modal"><strong><?php echo $adjacent_post->post_title; ?></strong> <i class="icon-chevron-sign-right belize-hole"></i></a></div>
        <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">open full page <i class="icon-share-alt belize-hole"></i></a> <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
            </div>
            <!-- End modal-footer -->
            </div>
        </div>
        <!-- End .modal -->
        
	<?php	
	}
		/**
	 * block_modal_output function.
	 * View as block modal or block
	 * @access public
	 * @return void
	 */
	function block_modal_output() { 
		// Categories Setup
		$categories = get_the_category();
			$output_cat = ' ';
			if($categories){
				foreach($categories as $category) {
					$output_cat .= $category->slug.' ';
				}
					$the_category_slug =  trim($output_cat);
			}
			
		$the_category_header = get_the_category();
			$output_cat_header = ' ';
			if($the_category_header){
				foreach($the_category_header as $cat_head) {
					$output_cat_header .= '<a href="'.get_category_link( $cat_head->term_id ).'" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $cat_head->name ) ) . '">'.$cat_head->cat_name.'</a>';
				}
					$the_category_link =  trim($output_cat_header);
			}
			
		// Tags Setup
		$output_tag = '';
		$posttags = get_the_tags();
		if ($posttags) {
			foreach($posttags as $tag) {
				$output_tag .= $tag->slug . ' '; 
			}
		}
		$the_tag_slug = $output_tag;
		 
		$the_tag_header = get_the_tags();
			$output_tag_header = ' ';
			if($the_tag_header){
				foreach($the_tag_header as $tag_head) {
					$output_tag_header .= '<a href="'.get_tag_link( $tag_head->term_id ).'" title="' . $tag_head->name . '">'.$tag_head->name.'</a>';
				}
					$the_tag_links =  trim($output_tag_header);
			}

		// Output Setup
		switch ( $this->iso_attributes['filter_by'] ){
        	default: "tags";
            break;
                                        
            case "tags":
            case "tag":
				$the_iso_filter = $the_tag_slug;
				$the_iso_header_tag = $the_tag_links;
            break;
                                        
           case "category":
           case "categories":
		   case "cat":
				$the_iso_filter = $the_category_slug;
				$the_iso_header_tag = $the_category_link;
           break;
		}
		$iso_num = 1;
	?>
        <div id="post-<?php the_ID(); ?>" class="<?php echo $the_iso_filter;?> match mix">
  			<?php if( function_exists( 'do_atomic' ) ): ?>
  				<div class="<?php echo $this->iso_attributes['iso_object'] ?> match mix entry-content" style="width:<?php echo $this->iso_attributes['box_width']; ?>px">
				 <small class="header-tags"><?php echo $the_iso_header_tag;?></small>
            		<div class="boxey-inside <?php echo $the_iso_filter; ?>">
						<?php if ( has_post_thumbnail() ) :?>
                       <a title="<?php the_title(); ?>" href="<?php 
					   if ($this->iso_attributes['view'] == "block_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "block" ):
							echo the_permalink();
						endif; ?>" role="button" data-toggle="modal"><?php echo the_post_thumbnail('medium', array('class' =>'img-circle')); ?></a>
                       <?php endif; ?>
      				<div class="boxey-inner">
                		<h3 class="post-title media-title"> <a href="<?php 
					   if ($this->iso_attributes['view'] == "block_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "block" ):
							echo the_permalink();
						endif; ?>" role="button" data-toggle="modal"><span class="iso-title"><?php the_title(); ?></span></a><br />
                        <?php if ($this->iso_attributes['show_date'] == 'true') : ?><small class="date"><?php echo get_the_date(); ?></small><?php endif; ?></h3>
                  		<span class="iso-description"><?php the_excerpt(); ?></span>
                		<a href="<?php 
					   if ($this->iso_attributes['view'] == "block_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "block" ):
							echo the_permalink();
						endif; ?>" role="button" class=" btn btn-small launch-btn" data-toggle="modal"><i class="icon-rocket"></i> Launch</a> </a></div>
      				<!-- end #boxey-inner --> 
    				</div>
            		<!-- end #boxey-inside -->
        	<?php 
			if ($this->iso_attributes['view'] == "block_modal" ):
				$this->modal_insert();
            endif;
            ?>

     </div>
  <!-- .entry-content -->
  
  <?php do_atomic( 'after_entry' ); // hybrid_after_entry ?>
  <?php else: ?>
      <div class="boxey entry-content full_out <?php echo $the_iso_filter; ?>">
                <div class="entry-header">
          <h2 class="post-title entry-title placebo"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
            <?php the_title(); ?>
            </a></h2>
        </div>
                <?php the_content(); ?>
                <?php wp_link_pages( array( 'before' => '<div class="page-links pages">' . __( 'Pages:', 'iso-shortcode' ), 'after' => '</div>' ) ); ?>
                <div class="entry-meta">
          <?php $this->entry_meta(); ?>
          <?php edit_post_link( __( 'Edit', 'iso-shortcode' ), '<span class="edit-link">', '</span>' ); ?>
        </div>
                <!-- .entry-meta --> 
                
              </div>
      <!-- .entry-content -->
      <?php endif; ?>
    </div>
            <!-- .hentry -->
    <?php 
	
	}
	/**
	 * simple_modal_output function.
	 * Returns view based on simple or simple modal
	 * @access public
	 * @return void
	 */
	function simple_modal_output() { 
		// Categories Setup
		$categories = get_the_category();
			$output_cat = ' ';
			if($categories){
				foreach($categories as $category) {
					$output_cat .= $category->slug.' ';
				}
					$the_category_slug =  trim($output_cat);
			}
			
		$the_category_header = get_the_category();
			$output_cat_header = ' ';
			if($the_category_header){
				foreach($the_category_header as $cat_head) {
					$output_cat_header .= '<a href="'.get_category_link( $cat_head->term_id ).'" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $cat_head->name ) ) . '">'.$cat_head->cat_name.'</a>';
				}
					$the_category_link =  trim($output_cat_header);
			}
			
		// Tags Setup
		$output_tag = '';
		$posttags = get_the_tags();
		if ($posttags) {
			foreach($posttags as $tag) {
				$output_tag .= $tag->slug . ' '; 
			}
		}
		$the_tag_slug = $output_tag;
		 
		$the_tag_header = get_the_tags();
			$output_tag_header = ' ';
			if($the_tag_header){
				foreach($the_tag_header as $tag_head) {
					$output_tag_header .= '<a href="'.get_tag_link( $tag_head->term_id ).'" title="' . $tag_head->name . '">'.$tag_head->name.'</a>';
				}
					$the_tag_links =  trim($output_tag_header);
			}

		// Output Setup
		switch ( $this->iso_attributes['filter_by'] ){
        	default: "tags";
            break;
                                        
            case "tags":
            case "tag":
				$the_iso_filter = $the_tag_slug;
				$the_iso_header_tag = $the_tag_links;
            break;
                                        
           case "category":
           case "categories":
		   case "cat":
				$the_iso_filter = $the_category_slug;
				$the_iso_header_tag = $the_category_link;
           break;
		}
	?>    
        <div id="post-<?php the_ID(); ?>" class="<?php echo $the_iso_filter; ?>">
		  <?php if( function_exists( 'do_atomic' ) ): ?>
          <div class="<?php echo $this->iso_attributes['iso_object'] ?> entry-content" style="width:<?php echo $this->iso_attributes['box_width']; ?>px"> 
               <div class="boxey-inside">
                <small class="header-tags"><?php echo $the_iso_header_tag;?></small>
              <div class="boxey-inner">
                <?php if ( has_post_thumbnail() ) :?>
               <a title="<?php the_title(); ?>" href="<?php 
					   if ($this->iso_attributes['view'] == "simple_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "simple" ):
							echo the_permalink();
						endif; ?>" role="button" data-toggle="modal"><?php echo the_post_thumbnail('medium', array('class' =>'img-circle')); ?></a>
               <?php endif; ?>
                        <h3 class="post-title"> <a href="<?php 
					   if ($this->iso_attributes['view'] == "simple_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "simple" ):
							echo the_permalink();
						endif; ?>" role="button" data-toggle="modal"><span class="iso-title"><?php the_title(); ?></span></a><br />
                            <?php if ($this->iso_attributes['show_date'] == 'true') : ?><small class="date">
                                <?php echo get_the_date(); ?><br />
                            </small><?php endif; ?>
                        </h3>
               
                          <div class="excerpt">
                          <span class="iso-description"><?php the_excerpt(); ?></span>
                          </div>
                        <a href="<?php 
					   if ($this->iso_attributes['view'] == "simple_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "simple" ):
							echo the_permalink();
						endif; ?>" role="button" class=" btn launch-btn" data-toggle="modal"> Launch</a>
              </div>
              <!-- end #boxey-inner --> 
              
            </div>
            <!-- end #boxey-inside -->
            
        	<?php 
			if ($this->iso_attributes['view'] == "simple_modal" ):
				$this->modal_insert();
            endif;
            ?>

       </div>
      <!-- .entry-content -->
      
      <?php do_atomic( 'after_entry' ); // hybrid_after_entry ?>
      <?php else: ?>
      <div class="boxey entry-content full_out <?php echo $the_iso_filter; ?>">
                <div class="entry-header">
          <h2 class="post-title entry-title placebo"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
            <?php the_title(); ?>
            </a></h2>
        </div>
                <?php the_content(); ?>
                <?php wp_link_pages( array( 'before' => '<div class="page-links pages">' . __( 'Pages:', 'iso-shortcode' ), 'after' => '</div>' ) ); ?>
                <div class="entry-meta">
          <?php $this->entry_meta(); ?>
          <?php edit_post_link( __( 'Edit', 'iso-shortcode' ), '<span class="edit-link">', '</span>' ); ?>
        </div>
                <!-- .entry-meta --> 
              </div>
      <!-- .entry-content -->
      <?php endif; ?>
    </div>
            <!-- .hentry -->
	<?php
	}
	/**
	 * custom_modal_output function.
	 * Returns view based on custom or custom modal
	 * @access public
	 * @return void
	 */
	function custom_modal_output() { 
		// Categories Setup
		$categories = get_the_category();
			$output_cat = ' ';
			if($categories){
				foreach($categories as $category) {
					$output_cat .= $category->slug.' ';
				}
					$the_category_slug =  trim($output_cat);
			}
			
		$the_category_header = get_the_category();
			$output_cat_header = ' ';
			if($the_category_header){
				foreach($the_category_header as $cat_head) {
					$output_cat_header .= '<a href="'.get_category_link( $cat_head->term_id ).'" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $cat_head->name ) ) . '">'.$cat_head->cat_name.'</a>';
				}
					$the_category_link =  trim($output_cat_header);
			}
			
		// Tags Setup
		$output_tag = '';
		$posttags = get_the_tags();
		if ($posttags) {
			foreach($posttags as $tag) {
				$output_tag .= $tag->slug . ' '; 
			}
		}
		$the_tag_slug = $output_tag;
		 
		$the_tag_header = get_the_tags();
			$output_tag_header = ' ';
			if($the_tag_header){
				foreach($the_tag_header as $tag_head) {
					$output_tag_header .= '<a href="'.get_tag_link( $tag_head->term_id ).'" title="' . $tag_head->name . '">'.$tag_head->name.'</a>';
				}
					$the_tag_links =  trim($output_tag_header);
			}

		// Output Setup
		switch ( $this->iso_attributes['filter_by'] ){
        	default: "tags";
            break;
                                        
            case "tags":
            case "tag":
				$the_iso_filter = $the_tag_slug;
				$the_iso_header_tag = $the_tag_links;
            break;
                                        
           case "category":
           case "categories":
		   case "cat":
				$the_iso_filter = $the_category_slug;
				$the_iso_header_tag = $the_category_link;
           break;
		}
	?>
        <div id="post-<?php the_ID(); ?>" class="<?php echo $the_iso_filter; ?>">
  			<?php if( function_exists( 'do_atomic' ) ): ?>
  				<div class="<?php echo $this->iso_attributes['iso_object'] ?> entry-content" style="width:<?php echo $this->iso_attributes['box_width']; ?>px">
				<small class="header-tags"><?php echo $the_iso_header_tag;?></small>
            		<div class="boxey-inside <?php echo $the_iso_filter; ?>">
						<?php if ( has_post_thumbnail() ) :?>
                       <a title="<?php the_title(); ?>" href="<?php 
					   if ($this->iso_attributes['view'] == "custom_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "custom" ):
							echo the_permalink();
						endif; ?>" role="button" data-toggle="modal"><?php echo the_post_thumbnail('medium', array('class' =>'iso-img-custom')); ?></a>
                       <?php endif; ?>
      				<div class="boxey-inner">
                		<h3 class="post-title media-title modal-title"> <a href="<?php 
					   if ($this->iso_attributes['view'] == "custom_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "custom" ):
							echo the_permalink();
						endif; ?>" role="button" data-toggle="modal"><span class="iso-title"><?php the_title(); ?></span></a><br />
                        <?php if ($this->iso_attributes['show_date'] == 'true') : ?><small class="date"><?php echo get_the_date(); ?></small><?php endif; ?></h3>
                  		<span class="iso-description"><?php the_excerpt(); ?></span>
                		<a href="<?php 
					   if ($this->iso_attributes['view'] == "custom_modal" ):
							echo '#';
							echo the_ID();
						elseif ($this->iso_attributes['view'] == "custom" ):
							echo the_permalink();
						endif; ?>" role="button" class=" btn btn-small launch-btn" data-toggle="modal"><i class="icon-rocket"></i> Launch</a> </a></div>
      				<!-- end #boxey-inner --> 
    				</div>
            		<!-- end #boxey-inside -->
        	<?php 
			if ($this->iso_attributes['view'] == "custom_modal" ):
				$this->modal_insert();
            endif;
            ?>

     </div>
  <!-- .entry-content -->
  
  <?php do_atomic( 'after_entry' ); // hybrid_after_entry ?>
  <?php else: ?>
      <div class="boxey entry-content full_out <?php echo $the_iso_filter; ?>">
                <div class="entry-header">
          <h2 class="post-title entry-title placebo"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
            <?php the_title(); ?>
            </a></h2>
        </div>
                <?php the_content(); ?>
                <?php wp_link_pages( array( 'before' => '<div class="page-links pages">' . __( 'Pages:', 'iso-shortcode' ), 'after' => '</div>' ) ); ?>
                <div class="entry-meta">
          <?php $this->entry_meta(); ?>
          <?php edit_post_link( __( 'Edit', 'iso-shortcode' ), '<span class="edit-link">', '</span>' ); ?>
        </div>
                <!-- .entry-meta --> 
                
              </div>
      <!-- .entry-content -->
      <?php endif; ?>
    </div>
            <!-- .hentry -->
    <?php 
	
	}

	/**
	 * list_output function.
	 *
	 * @access public
	 * @return void
	 */
	function list_output() {
		// Categories Setup
		$categories = get_the_category();
			$output_cat = ' ';
			if($categories){
				foreach($categories as $category) {
					$output_cat .= $category->slug.' ';
				}
					$the_category_slug =  trim($output_cat);
			}
			
		$the_category_header = get_the_category();
			$output_cat_header = ' ';
			if($the_category_header){
				foreach($the_category_header as $cat_head) {
					$output_cat_header .= '<a href="'.get_category_link( $cat_head->term_id ).'" title="' . esc_attr( sprintf( __( "View all posts in %s" ), $cat_head->name ) ) . '">'.$cat_head->cat_name.'</a>';
				}
					$the_category_link =  trim($output_cat_header);
			}
			
		// Tags Setup
		$output_tag = '';
		$posttags = get_the_tags();
		if ($posttags) {
			foreach($posttags as $tag) {
				$output_tag .= $tag->slug . ' '; 
			}
		}
		$the_tag_slug = $output_tag;
		 
		$the_tag_header = get_the_tags();
			$output_tag_header = ' ';
			if($the_tag_header){
				foreach($the_tag_header as $tag_head) {
					$output_tag_header .= '<a href="'.get_tag_link( $tag_head->term_id ).'" title="' . $tag_head->name . '">'.$tag_head->name.'</a>';
				}
					$the_tag_links =  trim($output_tag_header);
			}

		// Output Setup
		switch ( $this->iso_attributes['filter_by'] ){
        	default: "tags";
            break;
                                        
            case "tags":
            case "tag":
				$the_iso_filter = $the_tag_slug;
				$the_iso_header_tag = $the_tag_links;
            break;
                                        
           case "category":
           case "categories":
		   case "cat":
				$the_iso_filter = $the_category_slug;
				$the_iso_header_tag = $the_category_link;
           break;
		}
		
		?>
		<li id="<?php the_ID(); ?>" class="<?php echo $the_iso_filter; ?> iso-list"><h3><small><?php the_tags( ' ',' ' ,' ' ); ?></small><br /><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><span class="iso-title"><?php the_title(); ?></span></a><br />
        <?php if ($this->iso_attributes['show_date'] == 'true') : ?><small class="date"><?php echo get_the_date(); ?></small><?php endif; ?></h3>
        <span class="iso-description"><?php the_excerpt(); ?></span>
        </li>
		<?php
	}
	/* helper filters */
	
	/**
	 * feed_post_thumbnail_html function.
	 *
	 * @access public
	 * @param mixed $html
	 * @param mixed $post_id
	 * @param mixed $post_thumbnail_id
	 * @param mixed $size
	 * @param mixed $attr
	 * @return void
	 */
	function feed_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		global $post;
		if( !isset( $post->is_iso_shortcode_feed) )
			return $html;

			if($enclosure = $post->post_content_filtered->get_enclosure()):

				if($size == 'post-thumbnail') {
					$html = '<img class="feed-thumb post-thumbnail" src="'.$enclosure->thumbnails[0].'" />';

				} else {
					$html = '<img class="feed-image post-full"src="'.$enclosure->link.'" alt="" />';
				}

			endif;

		return $html;
	}

	/**
	 * feed_post_author function.
	 *
	 * @access public
	 * @param mixed $author
	 * @return void
	 */
	function feed_post_author( $author ) {
		global $post;

		if( !isset( $post->is_iso_shortcode_feed) )
			return $author;

		$rss_author = $post->post_content_filtered->get_author();
		return $rss_author->get_name();

	}

	/**
	 * feed_post_author_link function.
	 *
	 * @access public
	 * @param mixed $author_link
	 * @return void
	 */
	function feed_post_author_link( $author_link ){
		global $post;
		if( !isset( $post->is_iso_shortcode_feed) )
			return $author_link;

		$rss_author = $post->post_content_filtered->get_author();


		if(  $rss_author->get_link() )
			return $rss_author->get_link();

		return $post->guid;
	}
	/**
	 * register_script function.
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
//	}
	static function iso_enqueue() { 
		global $post;
		wp_register_style( 'iso-shortcodes',  plugins_url('/css/iso-default.css', __FILE__) );
		wp_register_script( 'iso-shortcode', plugins_url('/js/iso-scripts.js', __FILE__), 'jquery', '1.0', true );
		  if( isset($post->post_content) AND has_shortcode( $post->post_content, 'iso') ) :
				wp_enqueue_style( 'iso-shortcodes' );
				wp_enqueue_script( 'iso-shortcode' );
		  endif;
	}
	/**
	 * iso_script function.
	 *
	 * @access public
	 */
	function iso_script() {
		
		$gutter = $this->iso_attributes['gutter'];
			
			if (is_numeric($gutter)): 
			$gutter = $this->iso_attributes['gutter'];
			
				else: $gutter = 20;
					echo "<div class=\"alert alert-error\"><strong><i class=\"icon-exclamation\"></i> Gutter</strong> needs to be a numeric value.</div>";
			
			endif; 
		  global $post;
		  if( isset($post->post_content) AND has_shortcode( $post->post_content, 'iso') ) :
			
			?>            
    <script>
    var items = [];
    var $container = jQuery('.<?php echo $this->iso_attributes['container']; ?>');
     jQuery(document).ready(function($) {
	  // init Isotope
	  var $container = $('.<?php echo $this->iso_attributes['container']; ?>');
	  $container.imagesLoaded(function () {
        $container.isotope({
			layoutMode: '<?php if ($this->iso_attributes['view'] == 'list'): ?>fitRows<?php  else: ?>masonry<?php endif; ?>',
			<?php	if ($this->iso_attributes['view'] == 'list'): ?>
			itemSelector: '.iso-list',
			<?php endif; ?>
			//itemSelector: '.iso_shortcode',
				getSortData: {
					name: '.boxeytitle',
					symbol: '.lead',
					category: '[data-category]'
				},
			masonry: {
  				isFitWidth: true,
        		gutter: <?php echo $gutter; ?>
			}, 
		});

    <?php	if ($this->iso_attributes['searchable'] == 'true'): ?>
    //Makes the titles searchable
	$('span.iso-title').each(function(){
			var tmp = {};
			<?php if ($this->iso_attributes['view'] == "list"): ?>
			tmp.id = $(this).parent().parent().parent().attr('id');
			<?php else: ?>
			tmp.id = $(this).parent().parent().parent().parent().parent().parent().attr('id');
			<?php endif; ?>
			tmp.name = ($(this).text().toLowerCase());
			items.push( tmp );
		});
	//Makes the excerpt searchable
	$('span.iso-description').each(function(){
			var tmp = {};
			<?php if ($this->iso_attributes['view'] == "block_modal" || $this->iso_attributes['view'] =="custom_modal" || $this->iso_attributes['view'] == "block" || $this->iso_attributes['view'] =="custom"): ?>
			tmp.id = $(this).parent().parent().parent().parent().attr('id');
			<?php elseif ($this->iso_attributes['view'] == "list"): ?>
			tmp.id = $(this).parent().attr('id');
			<?php else: ?>
			tmp.id = $(this).parent().parent().parent().parent().parent().attr('id');
			<?php endif; ?>
			tmp.name = ($(this).text().toLowerCase());
			items.push( tmp );
		});
		//console.log("These are them, the items you see...", items);

		// User types in search box - call our search function and supply lower-case keyword as argument
		$('#search').bind('keyup', function() {
			isotopeSearch( $(this).val().toLowerCase() );
		});
		
		// User clicks 'show all', make call to search function with an empty keyword var
		$('#showAll').click(function(){
			$('#search').val(''); // reset input el value
			isotopeSearch(false); // restores all items
			return false;	
		});

	<?php endif; ?>

 	});


	<?php if ($this->iso_attributes['filter'] ==true): ?>
		<?php if ($this->iso_attributes['filter'] == 'links'): ?>				
		$('.iso-links a').click(function(){
			$('.iso-links .current').removeClass('current');
					$(this).addClass('current');
					 
					var selector = $(this).attr('data-filter-value');
					$('.<?php echo $this->iso_attributes['container']; ?>').isotope({
								filter: selector,
								animationOptions: {
								duration: 750,
								easing: 'linear',
								queue: false
							}
						});
							return false;
						});
	   <?php  endif; 
   
		if ($this->iso_attributes['filter'] == 'dropdown'): ?>				
		// filter functions
		  var filterFns = {
			// show if number is greater than 50
			numberGreaterThan50: function() {
			  var number = $(this).find('.number').text();
			  return parseInt( number, 10 ) > 50;
			},
		  };
		
		  // bind filter button click
		  $('.iso-dropdown').on( 'click', 'a', function() {
			var filterValue = $( this ).attr('data-filter-value');
			// use filterFn if matches value
			filterValue = filterFns[ filterValue ] || filterValue;
			$container.isotope({ filter: filterValue });
		  });
		
		  // change is-checked class on buttons
		  $('.iso-dropdown').each( function( i, buttonGroup ) {
			var $buttonGroup = $( buttonGroup );
			$buttonGroup.on( 'click', 'a', function() {
			  $buttonGroup.find('.current').removeClass('current');
			  $( this ).addClass('current');
			  //$("a.current").attr("data-filter-value", "#")
			});
		
			 
		});
	  
	   $('.disable').click(function () {return false;});
	   
	   <?php  endif; ?>
   	<?php  endif; ?>
  
   <?php	if ($this->iso_attributes['searchable'] == 'true'): ?>
	//Sets up the search box for iso attribute searchable	
	function isotopeSearch(kwd) {
	        // reset results arrays
	        var matches = [];
	        var misses = [];

	        $('.boxey').removeClass('match miss'); // get rid of any existing classes
	        $('#noMatches').hide(); // ensure this is always hidden when we start a new query

	        if ( (kwd != '') && (kwd.length >= 2) ) { // min 2 chars to execute query:

	                // loop through items array             
	                $.each(items, function(i){
						//console.log('THIS IS THE ITEM NAME : '+items[0].name);
	                        if ( items[i].name.indexOf(kwd) !== -1 ) { // keyword matches element
	                                matches.push( $('#'+items[i].id)[0] );
	                        } else {
	                                misses.push( $('#'+items[i].id)[0] );
	                        }
	                });

	                // add appropriate classes and call isotope.filter
	                $(matches).addClass('match');
	                $(misses).addClass('miss');
	                $container.isotope({ filter: $(matches) }); // isotope.filter will take a jQuery object instead of a class name as an argument - sweet!

	                if (matches.length == 0) {
	                        $('#noMatches').fadeIn(250); // deal with empty results set
	                }

	        } else {
	                // show all if keyword less than 2 chars
	                $container.isotope({ filter: '' });
	        }

	}
    <?php endif; ?>

	});

  	</script>
	<?php	
    endif;
	}
} /** KEEP **/
	

new Educ_Iso_Shortcode(); ?>