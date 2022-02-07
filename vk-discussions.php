<?php
/* @wordpress-plugin
 * Plugin Name:       VK Discussions plugin
 * Plugin URI:        http://khos.ru
 * Description:       Shortcode to add vk duscussions comments to your page. Example: [vk_discussions group_id=72562540 topic_id=31870138 count=40 sort=desc]
 * Version:           1.0.0
 * Author:            Yury Khomich
 * Author URI:        http://khos.ru
 * Text Domain:       vk-discussions
 * Domain Path: /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

function vk_discussions_func( $atts ) {

	wp_enqueue_style('vk-discussions', plugin_dir_url(__FILE__) . 'assets/style.css');	

	wp_enqueue_style('fancybox', plugin_dir_url(__FILE__) . 'assets/fancybox/jquery.fancybox-1.3.4.css');	
	wp_enqueue_script('fancybox', plugin_dir_url(__FILE__) . 'assets/fancybox/jquery.fancybox-1.3.4.pack.js');	

	$result = '';
    
    $a = shortcode_atts( array(
        'group_id' => '',
        'topic_id' => '',
        'count' => 30,
        'sort' => 'asc'
    ), $atts );

    if (empty($a['group_id']) || empty($a['topic_id'])) {
    	return 'No group_id or topic_id information';
    }
	
	$extended = 1; // Будут ли загружены профили
	$need_likes = 1; // Будут ли загружены лайки	
	$version = "5.73"; // Версия VK API
	$offset = 0;

	if (isset($_GET['vkd_offset'])) { // get offset for page redirect to last page		

		$offset = intval($_GET['vkd_offset']);	

	} else { // else redirect to last page

		$page = file_get_contents("https://api.vk.com/method/board.getComments?" 
		  . "group_id=" . $a['group_id'] 
		  . "&topic_id=" . $a['topic_id'] 
		  . "&count=1"
		  . "&offset=0"
		  . "&extended=0"
		  . "&need_likes=0"
		  . "&sort=asc"
		  . "&version=" . $version
		);

		$data = json_decode($page);

		if (!isset($data->response)) {
			return 'Problem with VK API response';
		}

		if (!isset($data->response->comments)) {
			return 'Problem with VK API response';
		}

		$comments_count = array_shift($data->response->comments);

		$num_pages = ceil($comments_count / $a['count']);
		wp_redirect( $_SERVER['SCRIPT_URL'] . '?vkd_offset='.$a['count']*($num_pages-1) );
		exit;

	}
	
	$page = file_get_contents("https://api.vk.com/method/board.getComments?" 
		  . "group_id=" . $a['group_id'] 
		  . "&topic_id=" . $a['topic_id'] 
		  . "&count=" . $a['count'] 
		  . "&offset=" . $offset 
		  . "&extended=" . $extended 
		  . "&need_likes=" . $need_likes 
		  . "&sort=" . $a['sort']
		  . "&version=" . $version
	);

	$data = json_decode($page);

	if (!isset($data->response)) {
		return 'Problem with VK API response';
	}

	if (!isset($data->response->comments)) {
		return 'Problem with VK API response';
	}

	// echo '<div style="display:none;">';
	// print_r($data);
	// echo '</div>';

	$profiles = array();
	// Reorg profiles to array
	foreach ($data->response->profiles as $profile) {
		$profiles[$profile->uid] = $profile;
	}

	$comments_count = array_shift($data->response->comments);

	$result .= '<div class="vk-discussions">';

	foreach ($data->response->comments as $comment) {
		//TODO: Исключить ответы на комментарии
		$result .= '<div class="vkd-comment">';
		$result .= '	<a class="vkd-comment-thumb" target="_blank" href="https://vk.com/'.$profiles[$comment->from_id]->screen_name.'">';
    	$result .= '		<img class="vkd-comment-img" alt="'.$profiles[$comment->from_id]->first_name.' '.$profiles[$comment->from_id]->last_name.'" src="'.$profiles[$comment->from_id]->photo.'">';
    	$result .= '	</a>';
    	$result .= '	<div class="vkd-comment-info">';
    	$result .= '		<div class="vkd-comment-author">';
    	$result .= '			<a href="https://vk.com/'.$profiles[$comment->from_id]->screen_name.'" target="_blank">'.$profiles[$comment->from_id]->first_name.' '.$profiles[$comment->from_id]->last_name.'</a>';
    	$result .= '			<span>'.date("d.m.y в H:i", $comment->date).'</span>';
    	$result .= '		</div>';
		$result .= '		<div class="vkd-comment-text">'.preg_replace('/\[(.*)\|(.*)\]/', "$2", $comment->text).'</div>';

		if (isset($comment->attachments)) {
			$result .= '<div class="vkd-comment-att">';
			foreach ($comment->attachments as $attachment) {				
				$result .= '<a class="fancybox" rel="group'.$comment->id.'" href="'.$attachment->photo->src_big.'"><img src="'.$attachment->photo->src.'" alt="" /></a> ';
			}
			$result .= '</div>';
		}

		$result .= '		<div class="vkd-comment-likes"></div>';
		$result .= '	</div>';		
		$result .= '</div>';		
	}

	// PAGINATION BLOCK
	$result .= '<div class="vkd-pagination">';

	$num_pages = ceil($comments_count / $a['count']);

	$limitend = $offset + $a['count'];
	$cur_page = $limitend / $a['count'];

	if ($num_pages > 1) {

		$result .= '<ul class="vkd-pagination-pages">';

		for ($i=1; $i<=$num_pages; $i++) {

			if ($i == $cur_page) {
				$result .= '<li><span>' . $i . '</span></li>';	
			} else {
				$url = $_SERVER['SCRIPT_URL'];
				$limitstart = ($i - 1) * $a['count'];
				$result .= '<li><a href="'.$url.'?vkd_offset='.$limitstart.'">' . $i . '</a></li>';
			}		

		}

		$result .= '</ul>';
	}

	$result .= '</div>'; #pagination end
 
	$result .= '</div>'; #vk-discussions end

    return $result;
}

add_shortcode( 'vk_discussions', 'vk_discussions_func' );
