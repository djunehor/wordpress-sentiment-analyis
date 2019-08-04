<?php
/*
Plugin Name: ZacWP Sentiment Analysis
Description: Adds sentiment analysis for wp posts/pages and comments
Version: 1.0
Author: Zacchaeus Bolaji
Author URI: https://github.com/makinde2013
*/
// Exit if accessed directly
defined( 'ABSPATH' ) or exit;

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

function zacwp_sa_preprocess_comment_handler( $commentdata ) {
	$datum = zacwp_sa_sentiment_analysis($commentdata['comment_content']);
	$string = zacwp_sa_set_score($datum['score']);

	$meta['zacwp_sa_comment_score'] = $string;
	$meta['zacwp_sa_comment_category'] = $datum['category'];

	return array_merge($commentdata, $meta);
}

function zacwp_sa_filter_post_data( $data , $postarr ) {

	$datum = zacwp_sa_sentiment_analysis($data['post_content']);
	$string = zacwp_sa_set_score($datum['score']);

	$meta['zacwp_sa_post_score'] = $string;
	$meta['zacwp_sa_post_category'] = $datum['category'];

	return array_merge($data, $meta);
}

function zacwp_sa_run_at_activation() {
	global $wpdb;
	zacwp_sa_add_not_exist($wpdb->prefix.'comments', 'zacwp_sa_comment_score', 'VARCHAR');
	zacwp_sa_add_not_exist($wpdb->prefix.'posts', 'zacwp_sa_post_score', 'VARCHAR');
}

function zacwp_sa_run_at_deactivation() {
	global $wpdb;
	zacwp_sa_drop_if_exist($wpdb->prefix.'comments', 'zacwp_sa_comment_score');
	zacwp_sa_drop_if_exist($wpdb->prefix.'posts', 'zacwp_sa_post_score');
}

function zacwp_sa_add_not_exist($table, $field, $type)
{
	global $wpdb;
	$results = $wpdb->get_results("SHOW columns FROM `".$table."` where field='".$field."'");
	if (!count($results))
	{
		$type = strtoupper($type);
		$sql = "ALTER TABLE  `".$table."` ADD `".$field."` ";
		$sql .= $type == 'VARCHAR' ? $type."(255)" : $type == 'INT' ? $type."(11)" : $type;
		$sql .= " NULL";
		$wpdb->query($sql);
	}
}

function zacwp_sa_drop_if_exist ($table, $field)
{
	global $wpdb;
	$results = $wpdb->get_results("SHOW columns FROM `".$table."` where field='".$field."'");
	if (count($results))
	{
		$sql = "ALTER TABLE `$table` DROP COLUMN `$field`;";
		$wpdb->query($sql);
	}
}

//register_activation_hook( __FILE__, 'zacwp_sa_run_at_activation' );
//register_deactivation_hook( __FILE__, 'zacwp_sa_run_at_deactivation' );

function zacwp_sa_comment_columns( $columns )
{
	$columns['zacwp_sa_comment_score'] = __( 'Sentiment Score' );
	return $columns;
}

function zacwp_sa_post_columns( $columns )
{
	$columns['zacwp_sa_post_score'] = __( 'Sentiment Score' );
	return $columns;
}

function zacwp_sa_comment_column( $column, $comment_ID )
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

function zacwp_sa_post_column( $column, $post_ID )
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

function zacwp_update_post_sentiment($post_ID) {
	$post = get_post( $post_ID, ARRAY_A);
	$meta = zacwp_sa_sentiment_analysis($post['post_content']);
	$score = zacwp_sa_set_score($meta['score']);
	update_post_meta($post_ID, 'zacwp_sa_post_score', $score);
	update_post_meta($post_ID, 'zacwp_sa_post_category', $meta['category']);
}

function zacwp_update_comment_sentiment($post_ID) {
	$post = get_post( $post_ID, ARRAY_A);
	$meta = zacwp_sa_sentiment_analysis($post['comment_content']);
	$score = zacwp_sa_set_score($meta['score']);
	update_comment_meta($post_ID, 'zacwp_sa_comment_score', $score);
	update_comment_meta($post_ID, 'zacwp_sa_comment_category', $meta['category']);
}

add_filter( 'parse_query', 'zacwp_sa_admin_posts_filter' );
add_filter('the_comments', 'zacwp_sa_filter_comments');
add_action( 'restrict_manage_posts', 'zacwp_sa_admin_posts_filter_restrict_manage_posts' );
add_action( 'restrict_manage_comments', 'zacwp_sa_admin_comments_filter_restrict_manage_posts' );

function zacwp_sa_admin_posts_filter( $query )
{
	global $pagenow;
	if ( is_admin() && $pagenow=='edit.php' && isset($_GET['post_sentiment']) && $_GET['post_sentiment'] != '') {
		$query->query_vars['meta_key'] = $_GET['post_sentiment'];
		if (isset($_GET['ADMIN_FILTER_FIELD_VALUE']) && $_GET['ADMIN_FILTER_FIELD_VALUE'] != '')
			$query->query_vars['meta_value'] = $_GET['ADMIN_FILTER_FIELD_VALUE'];
	}
}

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

function zacwp_sa_admin_posts_filter_restrict_manage_posts()
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

function zacwp_sa_admin_comments_filter_restrict_manage_posts()
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

add_filter( 'manage_edit-comments_columns', 'zacwp_sa_comment_columns' );
add_filter( 'manage_posts_columns', 'zacwp_sa_post_columns' );
add_filter( 'manage_pages_columns', 'zacwp_sa_post_columns' );

add_filter( 'manage_comments_custom_column', 'zacwp_sa_comment_column', 10, 2 );
add_filter( 'manage_posts_custom_column', 'zacwp_sa_post_column', 11, 2 );
add_filter( 'manage_pages_custom_column', 'zacwp_sa_post_column', 12, 2 );

add_filter( 'wp_insert_post_data' , 'zacwp_sa_filter_post_data' , '99', 2 );
add_filter( 'wp_insert_page_data' , 'zacwp_sa_filter_post_data' , '100', 2 );
add_filter( 'preprocess_comment' , 'zacwp_sa_preprocess_comment_handler' );
do_action( 'edit_comment', 'zacwp_update_comment_sentiment', 10, 2 );
do_action( 'edit_post', 'zacwp_update_post_sentiment', 10, 2 );


?>