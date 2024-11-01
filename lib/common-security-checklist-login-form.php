<?php 

if(session_id() == '')
{
    session_start(); 
}

class Common_Security_Checklist_Login_Form
{
	public static function add_captcha() {

	?>

	    <p>
	        <label for="captcha"><strong><?=  self::question() .' = ?' ?></strong><br />
	        <input type="text" name="captcha" id="captcha" class="input" size="25" /></label>
	    </p>

	<?php
	}


	public static function question()
	{

	    $digit1 = mt_rand(1,20);
	    $digit2 = mt_rand(1,20);
	    if( mt_rand(0,1) === 1 ) {
	            $math = sprintf('%d + %d', abs($digit1), abs($digit2));
	            $_SESSION['csc_captcha_answer'] = (abs($digit1) + $digit2);
	    } else {
	             $math = sprintf('%d - %d', abs($digit1), abs($digit2));
	            $_SESSION['csc_captcha_answer'] = (abs($digit1) - $digit2);
	    }

	    return $math;
	}


	public function custom_wp_authenticate( $user, $password ) {

		if ( !empty( $_POST['wp-submit'] ) ) {

			if (empty( $_POST['captcha'] )) {
				return new WP_Error( 'empty_captcha', __('<strong>ERROR</strong>: please solve the math question', 'common-security-checklist') );
			}

			if ( !empty($_POST['captcha']) && ( $_POST['captcha'] != $_SESSION['csc_captcha_answer'] ) ) {	    	
				return new WP_Error( 'invalid_captcha', '<strong>ERROR</strong>: Your answer for math question is incorrect' );
			}

		}

	    return $user;
	}
}

 ?>