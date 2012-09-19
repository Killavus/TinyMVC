<?php
	class Tools {
		public static function __uriHandler( $str ) {
			global $g_config;
			return $g_config->page->dir . $str;
		}
		
		public static function forDatabase( $str ) {
			return str_replace( ' ', '-', ltrim( rtrim( strtolower( strip_tags( addslashes( $str ) ) ) ) ) );
		}

		public static function checkPostCode( $str ) {
			$matches = Array();
			if( preg_match( "/\d\d-\d\d\d/", $str, $matches ) ) return TRUE;
			return FALSE;
		}
		
		public static function checkLength( $str, $val ) {
			if( is_array( $val ) ) return strlen( $str ) >= $val[0] and strlen( $str ) <= $val[1];
			else return strlen( $str ) <= $val;
		}
		
		public static function checkEmail( $str ) {
			$strfil = filter_var( $str, FILTER_SANITIZE_EMAIL );
			if( empty( $strfil ) or strpos( $strfil, '@' ) === FALSE ) return FALSE;
			return TRUE;
		}
			
		public static function generatePassword( $length ) {
			$iterTimes = floor($length / 32) + 1;
			$generatedPassword = '';
			while( $iterTimes != 0 ) {
				$generatedPassword .= sha1( mt_rand() * rand() );
				--$iterTimes;
			}
			
			return substr( $generatedPassword, 0, $length );
		}
    
    public static function AJAXRequest() {
      if( isSet( $_SERVER['HTTP_X_REQUESTED_WITH'] ) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == "xmlhttprequest" ) return TRUE;
      return FALSE;
		}

		public static function inArray( $needle, $haystack ) {
			if( !($haystack instanceof Iterator) ) return NULL;
			foreach( $haystack as $obj ) {
				if( $obj === $needle ) return true;
			}
			return false;
		}
	}
?>
