<?php 

if(session_id() == '')
{
    session_start(); 
}
/**
 * Prevent Spam on comment form
 */
class Common_Security_Checklist_Comment_Form
{
	public function add_captcha($fields) {
		
		$fields['comment_captcha'] = '<p class="comment-form-comment_captcha"><label for="comment_captcha"> '. self::question() .' = ?</label>
	        <input type="text" name="comment_captcha" id="comment_captcha" /></p>';
		
		return $fields;
	}

	public function save_comment_meta_data( $comment_id ) {

	  if ( ( isset( $_POST['comment_captcha'] ) ) && ( $_POST['comment_captcha'] != '') )
	  {
	  	$comment_captcha = wp_filter_nohtml_kses($_POST['comment_captcha']);
	  	add_comment_meta( $comment_id, 'comment_captcha', $comment_captcha );
	  }	  
	}

	public static function question()
	{

	    $digit1 = mt_rand(1,20);
	    $digit2 = mt_rand(1,20);
	    if( mt_rand(0,1) === 1 ) {
	            $math = sprintf('%d + %d', abs($digit1), abs($digit2));
	            $_SESSION['csc_comment_captcha_answer'] = (abs($digit1) + $digit2);
	    } else {
	             $math = sprintf('%d - %d', abs($digit1), abs($digit2));
	            $_SESSION['csc_comment_captcha_answer'] = (abs($digit1) - $digit2);
	    }

	    return $math;
	}

	public function custom_preprocess_comment_handler( $commentdata ) {
	    
	    if ( empty( $_POST['comment_captcha'] ) ) {
	    	wp_die( __( 'Error: Please, fill the captcha field.' ) );
	    }

	    if ( !empty( $_POST['comment_captcha'] ) && ( $_POST['comment_captcha'] != $_SESSION['csc_comment_captcha_answer'] ) ) {
	    	wp_die( __( 'Error: Your captcha answer was incorrect.' ) );
	    }

	    //return new WP_Error( 'empty_captcha', __('<strong>ERROR</strong>: please solve the math question', 'common-security-checklist') );
	    return $commentdata;
	}
}