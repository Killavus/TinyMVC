<?php
	class Debug {
		protected $debugDirectory;
		
		public function __construct( $debugDir = LOGS_DIRECTORY ) {
			$this->debugDirectory = $debugDir;
		}
		
		public function logMethod( $methodClass, $msg ) {
			$this->writeToFile( date( '[e H:i:s]' ) . ' ( ' . $methodClass . ' ): ' . $msg );
		}
		
		public function logClass( $class, $msg ) {
			$this->writeToFile( date( '[e H:i:s]' ) . ' ( ' . $class . ' ): ' . $msg );
		}
		
		public function logRaw( $msg ) {
			$this->writeToFile( date( '[e H:i:s]' ) . ': ' . $msg );
		}
		
		protected function writeToFile( $msg ) {
			$file = date( 'd-m-Y' ) . '.log';
			$fileHandler = fopen( $this->debugDirectory . '/' . $file, 'ab' );
			if( $fileHandler !== FALSE ) {
				fwrite( $fileHandler, $msg . "\n" );
			}
		}
	}
?>