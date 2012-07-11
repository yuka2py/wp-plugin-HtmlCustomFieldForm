<?php
/*
Plugin Name: Html Custom Field Form
Plugin URI: http://www.lurala.com/
Description: カスタムフィールドのテンプレート作成を、見慣れた xhtml の基本で実現します。xhtml や css を記述できる方であれば、レイアウトを自由に記述しつつ、また最小限の学習コストでカスタムフィールドを利用することが出来ます。
Version: 0.1.0
Author: Yuka2py
Author URI: http://www.lurala.com/
License: MIT
Copyright: Yuka2py
*/

require_once 'HtmlCustomFieldForm.php';

define('HTML_CUSTOM_FIELD_FORM', 'HTML_CUSTOM_FIELD_FORM');

add_action('admin_menu', 'hcff_add_admin_menu');
add_action('add_meta_boxes', 'hcff_add_meta_boxes');
add_action('save_post', 'hcff_save_post_meta');


function hcff_add_admin_menu() {
	add_menu_page(
		'Html Custom Field Form',
		'Html Custom Field Form',
		'administrator',
		'html_custom_field_form',
		'hcff_admin_form_edit'
	);
}

function hcff_admin_form_edit() {
	if (isset($_POST['hcff'])) {
		$hcff = stripslashes_deep($_POST['hcff']);
		update_option(HTML_CUSTOM_FIELD_FORM, $hcff);
	} else {
		$hcff = (array) get_option(HTML_CUSTOM_FIELD_FORM);
	}
	
	try {
		$hcfform = new HtmlCustomFieldForm();
		$hcfform->loadHTML($hcff['template']);
	} catch (InvalidXhtmlFormatException $e) {
		#FIXME: Display errors
		var_dump($xmlParseErrors);
	}
	
	include '_admin_edit_form.phtml';
}


function hcff_add_meta_boxes() {
	$hcff = (array) get_option(HTML_CUSTOM_FIELD_FORM);
	add_meta_box(
		'hcff-meta-box-id',
		$hcff['title'],
		'hcff_print_meta_box',
		'post',
		'normal',
		'high');
}

/**
 * @param object $post
 */
function hcff_print_meta_box($post) {
	wp_nonce_field('hcff_meta_box_nonce', 'hcff_meta_box_nonce');
	$hcff = (array) get_option(HTML_CUSTOM_FIELD_FORM);
	$values = (array) get_post_meta($post->ID, $hcff['slag'], true);
	$hcfform = new HtmlCustomFieldForm();
	$hcfform->loadHTML($hcff['template']);
	$hcfform->setValues($values);
	echo $hcfform->saveHTML();
}


/**
 * @param integer $post_id
 */
function hcff_save_post_meta($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE
			or !isset($_POST['hcff_meta_box_nonce'])
			or !wp_verify_nonce($_POST['hcff_meta_box_nonce'], 'hcff_meta_box_nonce')
			or !current_user_can('edit_post' )) {
				return;
	}
	
	$hcff = (array) get_option(HTML_CUSTOM_FIELD_FORM);
	$hcfform = new HtmlCustomFieldForm();
	$hcfform->loadHTML($hcff['template']);
	$hcfform->setValues($_POST);
	$values = $hcfform->getValues();
	update_post_meta($post_id, $hcff['slag'], $values);
}
