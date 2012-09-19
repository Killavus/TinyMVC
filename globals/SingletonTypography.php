<?php
class Typography {
   public static function typoValidator( $cont ) {
      $cont = str_replace( "...", "&hellip;", $cont );
      $cont = self::fixQuotationMarksSeparators( $cont );
      return $cont;
   }
   
   public static function fixQuotationMarksSeparators($text, $start = 0, $firstQuotation = -1) {      
      // Works O[n]. All can work O[n^2] so beware.
      $length = strlen($text);
      
      $htmlTagEnabled = FALSE;
      $firstQuotation = -1;
      $secondQuotation = -1;
      
      for( $i = $start; $i < $length; ++$i ) {
         if( $text[$i] == '<' ) {
            $htmlTagEnabled = TRUE;               
            $firstQuotation = -1;
         }
         if( $text[$i] == '>' ) $htmlTagEnabled = FALSE;
         if( $text[$i] == '"' ) {
            if( $htmlTagEnabled ) continue;
            if( $firstQuotation >= 0 ) {
               $secondQuotation = $i;
               $text = substr( $text, 0, $firstQuotation ) . "&ldquo;" . substr( $text, $firstQuotation+1, $secondQuotation-$firstQuotation-1 ) 
                  . "&rdquo;" . substr( $text, $secondQuotation + 1, $secondQuotation - 1 );
                  
               return self::fixQuotationMarksSeparators( $text, $firstQuotation );                  
            }
            else $firstQuotation = $i;
         }
         if( $i != 0 and $i != $length-1 and $text[$i] == '-' and $text[$i-1] == ' ' and $text[$i+1] == ' ' ) {
            $text = substr( $text, 0, $i ) . "&mdash;" . substr( $text, $i+1, $length - $i - 1 );
            return self::fixQuotationMarksSeparators( $text, $i, $firstQuotation );
         }
      }         
      return $text;
   }   
}
?>
