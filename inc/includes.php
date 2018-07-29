<?php

require_once('modules.php');
require_once('mpcf-admin.php');
require_once('mpcf-options.php');
require_once('mpcf-register-metaboxes.php');
require_once('gui.php');



function mpcf_helper_exists_checker() {
	return true;
}

function mpcf_mknice($value) {
	if (!is_array($value)) 	return htmlspecialchars_decode(stripslashes($value));
	else 					return array_map('mpcf_mknice', $value);
}

function mpcf_mksafe($value) {
	if (!is_array($value)) 	return htmlspecialchars($value);
	else 					return array_map('mpcf_mksafe', $value);
}

function mpcf_beautify_string($string) {
	$string = strtolower(htmlentities($string));
	$string = str_replace(get_html_translation_table(), '-', $string);
	$string = str_replace(' ', '-', $string);
	return preg_replace('/[-]+/i', '-', $string);
}


/*********************************************************
	Supplies an array of all dashicons
 *********************************************************/

function mpcf_get_all_dashicons() {
	$icons = array('menu', 'admin-site','dashboard','admin-media','admin-page','admin-comments','admin-appearance','admin-plugins','admin-users','admin-tools','admin-settings','admin-network','admin-generic','admin-home','admin-collapse','filter','admin-customizer','admin-multisite','admin-links','admin-post','format-image','format-gallery','format-audio','format-video','format-chat','format-status','format-aside','format-quote','welcome-write-blog','welcome-add-page','welcome-view-site','welcome-widgets-menus','welcome-comments','welcome-learn-more','image-crop','image-rotate','image-rotate-left','image-rotate-right','image-flip-vertical','image-flip-horizontal','image-filter','undo','redo','editor-bold','editor-italic','editor-ul','editor-ol','editor-quote','editor-alignleft','editor-aligncenter','editor-alignright','editor-insertmore','editor-spellcheck','editor-expand','editor-contract','editor-kitchensink','editor-underline','editor-justify','editor-textcolor','editor-paste-word','editor-paste-text','editor-removeformatting','editor-video','editor-customchar','editor-outdent','editor-indent','editor-help','editor-strikethrough','editor-unlink','editor-rtl','editor-break','editor-code','editor-paragraph','editor-table','align-left','align-right','align-center','align-none','lock','unlock','calendar','calendar-alt','visibility','hidden','post-status','edit','sticky','external','arrow-up','arrow-down','arrow-left','arrow-right','arrow-up-alt','arrow-down-alt','arrow-left-alt','arrow-right-alt','arrow-up-alt2','arrow-down-alt2','arrow-left-alt2','arrow-right-alt2','leftright','sort','randomize','list-view','excerpt-view','grid-view','move','hammer','art','migrate','performance','universal-access','universal-access-alt','tickets','nametag','clipboard','heart','megaphone','schedule','wordpress','wordpress-alt','pressthis','update','screenoptions','cart','feedback','cloud','translation','tag','category','archive','tagcloud','text','media-archive','media-audio','media-code','media-default','media-document','media-interactive','media-spreadsheet','media-text','media-video','playlist-audio','playlist-video','controls-play','controls-pause','controls-forward','controls-skipforward','controls-back','controls-skipback','controls-repeat','controls-volumeon','controls-volumeoff','yes','no','no-alt','plus','plus-alt','plus-alt2','minus','dismiss','marker','star-filled','star-half','star-empty','flag','info','warning','share','share1','share-alt','share-alt2','twitter','rss','email','email-alt','facebook','facebook-alt','networking','googleplus','location','location-alt','camera','images-alt','images-alt2','video-alt','video-alt2','video-alt3','vault','shield','shield-alt','sos','search','slides','analytics','chart-pie','chart-bar','chart-line','chart-area','groups','businessman','id','id-alt','products','awards','forms','testimonial','portfolio','book','book-alt','download','upload','backup','clock','lightbulb','microphone','desktop','laptop','tablet','smartphone','phone','smiley','index-card','carrot','building','store','album','palmtree','tickets-alt','money','thumbs-up','thumbs-down','layout','paperclip','email-alt2','menu-alt','plus-light','trash','heading','insert','saved','align-full-width','button','align-wide','ellipsis','buddicons-activity','buddicons-buddypress-logo','buddicons-community','buddicons-forums','buddicons-friends','buddicons-groups','buddicons-pm','buddicons-replies','buddicons-topics','buddicons-tracking','admin-site-alt','admin-site-alt2','admin-site-alt3','html','rest-api','editor-ltr','yes-alt','buddicons-bbpress-logo','tide'
	);

	return array_map(function($icon) { return 'dashicons-' . $icon; }, $icons);
}



?>