<?php

/*******************************************************************************
 *
 *  Copyrights 2015 to Present - LinkMyDeals (TM) - ALL RIGHTS RESERVED
 *
 * All information contained herein is, and remains the property of LinkMyDeals,
 * which is a registered trademark of Sellergize Web Technology Services Pvt. Ltd.
 *
 * The intellectual and technical concepts & code contained herein are proprietary
 * to Sellergize Web Technology Services Pvt. Ltd., and are covered and protected
 * by copyright law. Reproduction of this material is strictly forbidden unless prior
 * written permission is obtained from Sellergize Web Technology Services Pvt. Ltd.
 * 
 * ******************************************************************************/
 
global $wpdb;
$wp_prefix = $wpdb->prefix;

if(empty($batchSize)) {
	$batchSize = 750;
}

$count_new = $count_suspended = $count_updated = 0;

wp_defer_term_counting( true );
$wpdb->query( 'SET autocommit = 0;' );

$stores=array();
$storeTerms = get_terms('store');
foreach($storeTerms as $term) {
	$stores[$term->name] = $term->slug;
}

$categories=array();
$categoryTerms = get_terms('listing');
foreach($categoryTerms as $term) {
	$categories[$term->name] = $term->slug;
}

$coupons = $wpdb->get_results("SELECT * FROM  ".$wp_prefix."lmd_upload ORDER BY upload_date LIMIT 0,".$batchSize);

foreach($coupons as $coupon) {
	
	if($coupon->status == 'new') {

		$wpdb->query("INSERT INTO ".$wp_prefix."lmd_logs (microtime,msg_type,message) VALUES (".microtime(true).",'debug','Adding New Coupon (".$coupon->lmd_id.")')");
		
		$post_data = array(
			'ID'             => '',
			'post_title'     => $coupon->title,
			'post_content'   => $coupon->description,
			'post_status'    => 'publish',
			'post_type'      => 'listing_type',
			'post_author'    => get_current_user_id()
		);
		
		$post_id = wp_insert_post($post_data,$wp_error);
		
		if (strpos($coupon->categories, ',') !== FALSE) {
			$cat_names = explode(',',$coupon->categories);
			foreach($cat_names as $cat) {
				wp_set_object_terms($post_id, $cat, 'listing', true);
			}
		} else {
			wp_set_object_terms($post_id, $coupon->categories, 'listing', true);
		}
		
		if (strpos($coupon->store, ',') !== FALSE) {
			$store_names = explode(',',$coupon->store);
			foreach($store_names as $str) {
				wp_set_object_terms($post_id, $str, 'store', true);
			}
		} else {
			wp_set_object_terms($post_id, $coupon->store, 'store', true);
		}
		
		update_post_meta($post_id, 'lmd_id', $coupon->lmd_id);
		update_post_meta($post_id, 'url', $coupon->url);
		update_post_meta($post_id, 'link', $coupon->link);
		update_post_meta($post_id, 'code', $coupon->code);
		update_post_meta($post_id, 'coupon_type', $coupon->coupon_type);
		update_post_meta($post_id, 'coupon_txt', $coupon->coupon_txt);
		update_post_meta($post_id, 'start_date', $coupon->start_date);
		update_post_meta($post_id, 'expiry_date', $coupon->expiry_date);
		update_post_meta($post_id, 'listing_expiry_date', $coupon->listing_expiry_date);
		update_post_meta($post_id, 'featured', $coupon->featured);
		update_post_meta($post_id, 'listing_sticker', $coupon->listing_sticker);

		$wpdb->query("DELETE FROM ".$wp_prefix."lmd_upload WHERE lmd_id = ".$coupon->lmd_id);
		$count_new = $count_new + 1;
		
	} elseif($coupon->status == 'updated') {
		
		$wpdb->query("INSERT INTO ".$wp_prefix."lmd_logs (microtime,msg_type,message) VALUES (".microtime(true).",'debug','Updating Coupon (".$coupon->lmd_id.")')");

		$lmd_id = $coupon->lmd_id;
		$sql_id = "SELECT post_id FROM ".$wp_prefix."postmeta WHERE meta_key = 'lmd_id' AND meta_value = '$lmd_id' LIMIT 0,1";
		$post_id = $wpdb->get_var($sql_id);
		
		$post_data = array(
			'ID'             => $post_id,
			'post_title'     => $coupon->title,
			'post_content'   => $coupon->description,
			'post_status'    => 'publish'
		);
		
		wp_update_post($post_data);
		
		if (strpos($coupon->store, ',') !== FALSE) {
			$post_stores = array();
			$store_names = explode(',',$coupon->store);
			$append = false;
			foreach($store_names as $str) {
				wp_set_object_terms($post_id, $str, 'store', $append);
				$append = true;
			}
		} else {
			wp_set_object_terms($post_id, $coupon->store, 'store', false); // passing 'false' as the last value resets the terms of this post instead of appending. this is good in case of update where we want to put the new store and category for a coupon
		}
		
		if (strpos($coupon->categories, ',') !== FALSE) {
			$post_categories = array();
			$cat_names = explode(',',$coupon->categories);
			$append = false;
			foreach($cat_names as $cat) {
				wp_set_object_terms($post_id, $cat, 'listing', $append);
				$append = true;
			}
		} else {
			wp_set_object_terms($post_id, $coupon->categories, 'listing', false);
		}
		
		update_post_meta($post_id, 'url', $coupon->url);
		update_post_meta($post_id, 'link', $coupon->link);
		update_post_meta($post_id, 'code', $coupon->code);
		update_post_meta($post_id, 'coupon_type', $coupon->coupon_type);
		update_post_meta($post_id, 'coupon_txt', $coupon->coupon_txt);
		update_post_meta($post_id, 'start_date', $coupon->start_date);
		update_post_meta($post_id, 'expiry_date', $coupon->expiry_date);
		update_post_meta($post_id, 'listing_expiry_date', $coupon->listing_expiry_date);
		update_post_meta($post_id, 'featured', $coupon->featured);
		update_post_meta($post_id, 'listing_sticker', $coupon->listing_sticker);

		$wpdb->query("DELETE FROM ".$wp_prefix."lmd_upload WHERE lmd_id = ".$coupon->lmd_id);
		$count_updated = $count_updated + 1;
		
	} elseif($coupon->status == 'suspended') {
		
		$wpdb->query("INSERT INTO ".$wp_prefix."lmd_logs (microtime,msg_type,message) VALUES (".microtime(true).",'debug','Suspending Coupon (".$coupon->lmd_id.")')");

		$lmd_id = $coupon->lmd_id;
		$sql_id = "SELECT post_id FROM ".$wp_prefix."postmeta WHERE meta_key = 'lmd_id' AND meta_value = '$lmd_id' LIMIT 0,1";
		$post_id = $wpdb->get_var($sql_id);
		
		wp_delete_post($post_id,true);

		$wpdb->query("DELETE FROM ".$wp_prefix."lmd_upload WHERE lmd_id = ".$coupon->lmd_id);
		$count_suspended = $count_suspended + 1;
		
	}
		
}

$wpdb->query("INSERT INTO ".$wp_prefix."lmd_logs (microtime,msg_type,message) VALUES (".microtime(true).",'info','Processed Offers - $count_new New , $count_updated Updated , $count_suspended Suspended.')");
	
wp_defer_term_counting( false );
$wpdb->query( 'COMMIT;' );
$wpdb->query( 'SET autocommit = 1;' );
$file_processed = true;

$remainingCoupons = $wpdb->get_var("SELECT count(1) FROM ".$wp_prefix."lmd_upload");
if($remainingCoupons > 0) {
	$loop++;
	wp_schedule_single_event( time() , 'process_batch' , array($loop) ); // process next loop
} else {
	$wpdb->query("DELETE FROM ".$wp_prefix."lmd_logs WHERE logtime < CURDATE() - INTERVAL 30 DAY");
	$wpdb->query("INSERT INTO ".$wp_prefix."lmd_logs (microtime,msg_type,message) VALUES (".microtime(true).",'success','All offers processed successfully.')");
}

?>
