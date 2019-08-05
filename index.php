<?php
/*
Plugin Name: ZacWP Sentiment Analysis
Description: Adds sentiment analysis for wp posts/pages and comments, and ability to filter based on sentiment.
Version: 1.0
Author: Zacchaeus Bolaji
Author URI: https://github.com/makinde2013
*/
// Exit if accessed directly
defined( 'ABSPATH' ) or exit;


/**Remove any block contents from the string
 * @param $string
 *
 * @return string
 */
function zacwp_sa_clean_string($string) {
	$string = str_split($string);
	$paren_num = 0;
	$new_string = '';
	foreach($string as $char) {
		if ($char == '[') $paren_num++;
		else if ($char == ']') $paren_num--;
		else if ($paren_num == 0) $new_string .= $char;
	}
	return trim($new_string);
}

/**Perform actual sentiment analysis on string
 * @param $string
 *
 * @return mixed
 */
function zacwp_sa_sentiment_analysis($string) {

	require_once (plugin_dir_path(__FILE__).'/inc/autoload.php');
	$sentiment = new \PHPInsight\Sentiment();
	$string = zacwp_sa_clean_string($string);;
	// calculations:
	$result['score'] = $sentiment->score($string);
	$result['category'] = $sentiment->categorise($string);
	// output:

	return $result;
}

/**
 *Remove plugin added post/comment meta
 */
function zacwp_sa_run_at_uninstall() {
	global $wpdb;
	$wpdb->query("DELETE FROM ".$wpdb->prefix."postmeta WHERE `meta_key`='zacwp_sa_post_category' OR `meta_key`='zacwp_sa_post_score'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."commentmeta WHERE `meta_key`='zacwp_sa_comment_category' OR `meta_key`='zacwp_sa_comment_score'");
}

/**Add sentiment score column to the admin comments table
 * @param $columns
 *
 * @return mixed
 */
function zacwp_sa_comments_add_sentiment_score_column( $columns )
{
	$columns['zacwp_sa_comment_score'] = __( 'Sentiment Score' );
	return $columns;
}

/**Add sentiment score column to the admin posts/pages table
 * @param $columns
 *
 * @return mixed
 */
function zacwp_sa_posts_add_sentiment_score_column( $columns )
{
	$columns['zacwp_sa_post_score'] = __( 'Sentiment Score' );
	return $columns;
}

/**Runs for each comment row
 * Calculate and save sentiment analysis for those without
 * Display sentiment analysis for current row
 * @param $column
 * @param $comment_ID
 */
function zacwp_sa_comments_add_sentiment_analysis_row( $column, $comment_ID )
{
	if ( 'zacwp_sa_comment_score' == $column ) {
		$score = get_comment_meta( $comment_ID, 'zacwp_sa_comment_score', true );
		if ( empty($score)) {
			$comment = get_comment( $comment_ID, ARRAY_A);
			$meta = zacwp_sa_sentiment_analysis($comment['comment_content']);

			$score = zacwp_sa_set_score($meta['score']);

			update_comment_meta($comment_ID, 'zacwp_sa_comment_score', $score);
			update_comment_meta($comment_ID, 'zacwp_sa_comment_category', $meta['category']);

		}

		echo $score;

	}

}

/**Runs for each post column
 * Calculate and save sentiment analysis for those without
 * Display sentiment analysis for current row
 *
 * @param $column
 * @param $post_ID
 */
function zacwp_sa_posts_add_sentiment_analysis_row( $column, $post_ID )
{
	if ( 'zacwp_sa_post_score' == $column) {
		$score = get_post_meta( $post_ID, 'zacwp_sa_post_score', true );
		if ( empty($score)) {
			$post = get_post( $post_ID, ARRAY_A);
			$meta = zacwp_sa_sentiment_analysis($post['post_content']);
			$score = zacwp_sa_set_score($meta['score']);
			update_post_meta($post_ID, 'zacwp_sa_post_score', $score);
			update_post_meta($post_ID, 'zacwp_sa_post_category', $meta['category']);

		}

		echo $score;

	}
}

/**Return formatted sentiment category with score percent
 * @param $array
 *
 * @return string
 */
function zacwp_sa_set_score($array) {
	$max = max($array);

	$percent = round($max * 100, 2);

	$score = array_keys($array, $max)[0];

	switch ($score) {
		case 'neg':
			return "<div style='background-color:red;color:white;text-align: center;'>NEUTRAL ($percent%)</div>";
			break;
		case 'pos':
			return "<div style='background-color:green;color:white;text-align: center;'>POSITIVE ($percent%)</div>";
			break;
		case 'neu':
			return "<div style='background-color:grey;color:white;text-align: center;'>NEUTRAL ($percent%)</div>";
			break;
	}
}

/**Update specified post sentiment score and category
 * @param $post_ID
 */
function zacwp_sa_update_post_sentiment($post_ID) {
	$post = get_post( $post_ID, ARRAY_A);
	$meta = zacwp_sa_sentiment_analysis($post['post_content']);
	$score = zacwp_sa_set_score($meta['score']);
	update_post_meta($post_ID, 'zacwp_sa_post_score', $score);
	update_post_meta($post_ID, 'zacwp_sa_post_category', $meta['category']);
}

/**Update specified comment sentiment score and category
 *
 * @param $comment_ID
 */
function zacwp_sa_update_comment_sentiment($comment_ID) {
	$post = get_post( $comment_ID, ARRAY_A);
	$meta = zacwp_sa_sentiment_analysis($post['comment_content']);
	$score = zacwp_sa_set_score($meta['score']);
	update_comment_meta($comment_ID, 'zacwp_sa_comment_score', $score);
	update_comment_meta($comment_ID, 'zacwp_sa_comment_category', $meta['category']);
}


/**Add sentiment analysis filter for post
 * @param $query
 */
function zacwp_sa_admin_posts_filter( $query )
{
	global $pagenow;
	if ( is_admin() && $pagenow=='edit.php' && isset($_GET['post_sentiment']) && $_GET['post_sentiment'] != '') {
		$query->query_vars['meta_key'] = $_GET['post_sentiment'];
		if (isset($_GET['ADMIN_FILTER_FIELD_VALUE']) && $_GET['ADMIN_FILTER_FIELD_VALUE'] != '')
			$query->query_vars['meta_value'] = $_GET['ADMIN_FILTER_FIELD_VALUE'];
	}
}

/**Filter comments based on specified sentiment
 *
 * @param $comments
 *
 * @return mixed
 */
function zacwp_sa_filter_comments($comments){
	global $pagenow;
	if($pagenow == 'edit-comments.php'
	   && isset($_GET['sentiment_type'])
	   && !empty($_GET['sentiment_type'])
	){
		foreach($comments as $i => $comment){

			if(get_comment_meta($comment->comment_ID, 'zacwp_sa_comment_category', true) != sanitize_text_field($_GET['sentiment_type'])) unset($comments[$i]);
		}
	}
	return $comments;
}

/**Add sentiment filter form to the posts page
 *
 * @return mixed
 */
function zacwp_sa_posts_add_sentiment_score_filter_form()
{
	$values = [
		'neu' => 'Neutral',
		'pos' => 'Positive',
		'neg' => 'Negative'
	];
	?>
    <select name="post_sentiment_type">
        <option value=""><?php _e('Filter By Sentiment', 'commmm'); ?></option>
		<?php
		$current = isset($_GET['post_sentiment_type'])? $_GET['post_sentiment_type']:'';
		foreach ($values as $key => $value) {
			printf
			(
				'<option value="%s"%s>%s</option>',
				$key,
				$key == $current ? ' selected="selected"' : '',
				$value
			);

		}
		?>
    </select>
	<?php
}

/**Add sentiment filter form to the comments page
 *
 * @return mixed
 */
function zacwp_sa_comments_add_sentiment_score_filter_form()
{


	$values = [
		'neu' => 'Neutral',
		'pos' => 'Positive',
		'neg' => 'Negative'
	];
	?>
    <select name="sentiment_type">
        <option value=""><?php _e('Filter By Sentiment', 'commmm'); ?></option>
		<?php
		$current = isset($_GET['sentiment_type'])? $_GET['sentiment_type']:'';
		foreach ($values as $key => $value) {
			printf
			(
				'<option value="%s"%s>%s</option>',
				$key,
				$key == $current ? ' selected="selected"' : '',
				$value
			);

		}
		?>
    </select>
	<?php
}

/**Add sentiment analysis meta to post if not exist
 *
 * @param $post_id
 *
 * @return mixed
 */
function zacwp_sa_analyze_new_edited_post( $post_id ) {

	// If this is a revision, don't send the email.
	if ( wp_is_post_revision( $post_id ) )
		return;

	$score = get_post_meta( $post_id, 'zacwp_sa_post_score', true );
	if ( empty($score)) {
		$post = get_post( $post_id, ARRAY_A);
		$meta = zacwp_sa_sentiment_analysis($post['post_content']);
		$score = zacwp_sa_set_score($meta['score']);
		update_post_meta($post_id, 'zacwp_sa_post_score', $score);
		update_post_meta($post_id, 'zacwp_sa_post_category', $meta['category']);

	}

}

/**Add sentiment analysis meta to comment if not exist
 *
 * @param $comment_id
 * @param $comment_approved
 *
 * @return mixed
 */
function zacwp_sa_analyze_new_edited_comment( $comment_id, $comment_approved ) {

	$score = get_post_meta( $comment_id, 'zacwp_sa_comment_score', true );
	if ( empty($score)) {
		$post = get_comment( $comment_id, ARRAY_A);
		$meta = zacwp_sa_sentiment_analysis($post['comment_content']);
		$score = zacwp_sa_set_score($meta['score']);
		update_comment_meta($comment_id, 'zacwp_sa_comment_score', $score);
		update_comment_meta($comment_id, 'zacwp_sa_comment_category', $meta['category']);

	}

}

//run when plugin is uninstalled
register_uninstall_hook( __FILE__, 'zacwp_sa_run_at_uninstall' );

//add sentiment filters
add_filter( 'parse_query', 'zacwp_sa_admin_posts_filter' );
add_filter('the_comments', 'zacwp_sa_filter_comments');

//add filter form
add_action( 'restrict_manage_posts', 'zacwp_sa_posts_add_sentiment_score_filter_form' );
add_action( 'restrict_manage_comments', 'zacwp_sa_comments_add_sentiment_score_filter_form' );

//perform when comment/post is created/updated
add_action( 'wp_insert_post', 'zacwp_sa_analyze_new_edited_post', 10, 3 );
add_action( 'comment_post', 'zacwp_sa_analyze_new_edited_comment', 10, 2 );

//add sentiment score column to admin tables
add_filter( 'manage_edit-comments_columns', 'zacwp_sa_comments_add_sentiment_score_column' );
add_filter( 'manage_posts_columns', 'zacwp_sa_posts_add_sentiment_score_column' );
add_filter( 'manage_pages_columns', 'zacwp_sa_posts_add_sentiment_score_column' );

//deduce and display sentiment score for each post/comment
add_filter( 'manage_comments_custom_column', 'zacwp_sa_comments_add_sentiment_analysis_row', 10, 2 );
add_filter( 'manage_posts_custom_column', 'zacwp_sa_posts_add_sentiment_analysis_row', 11, 2 );
add_filter( 'manage_pages_custom_column', 'zacwp_sa_posts_add_sentiment_analysis_row', 12, 2 );


?>