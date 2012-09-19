<?php	
	class Database {
		protected $handlers;
		
		public function __construct() {
			$this->handlers = Array();
			if( DATABASE_BY_DEFAULT ) $this->addHandler( DATABASE_PDO, DATABASE_USER, DATABASE_PASSWORD );
		}
		
		public function addHandler( $pdoString, $userName = NULL, $password = NULL, $attributes = NULL ) {
			try {
				$this->handlers[] = new PDO( $pdoString, $userName, $password, $attributes );
			}
			catch( PDOException $e ) {
				global $g_debug;
				$g_debug->logMethod( __METHOD__, $e->getMessage() );
				throw new DatabaseException( 'failed to add a new database handler' );
				return false;
			}
		}
		
		public function removeHandler( $index = NULL ) {
			if( $index == NULL ) $index = count( $this->handlers ) - 1;
			if( $index < 0 or $index >= count( $this->handlers ) ) {
				global $g_debug;
				$g_debug->logMethod( __METHOD__, 'index out of bounds ( ' . $index . ' for possible range from 0 to ' . ( count( $this->handlers ) - 1 ) . ' )' );
				return false;
			}
			
			$this->handlers[ $index ] = $this->handlers[ count( $this->handlers ) - 1 ];
			array_pop( $this->handlers );
			return true;
		}
		
		public function handlerCount() { return count( $this->handlers ); }
		
		public function handler( $index = 0 ) {
			if( count( $this->handlers ) <= $index ) {
				global $g_debug;
				$g_debug->logMethod( __METHOD__, 'index out of bounds ( ' . $index . ' for possible range from 0 to ' . ( count( $this->handlers ) - 1 ) . ' )' );
				return NULL;
			}
			
			return $this->handlers[$index]; 
		}
		
		public function handlers() { return $this->handlers; }
	}
?>