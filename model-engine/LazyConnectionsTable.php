<?php
// BRUDNY HACK
require_once 'mvc/models/User.php';
/** Class used to implement a lazy table interface for storing actual comments.
  * @author Killavus
  * @since 0.1
  * @category Model
  * @package Killavus
  */
class LazyConnectionsTable implements Iterator,ArrayAccess,Countable {
	/** Array used for storing data IDs.
	  * @returns Array
	  * @since 0.1
	  */
	protected $data;
	/** Array used for storing virtual records.
	  * @returns Array
	  * @since 0.1
	  */
	protected $virtualRecords;
	/** Boolean used to determine if lazy table is in virtual adding mode or not. <strong>Virtual mode means if a new ID is added, it'll be saved in a database.</strong>
     * @returns boolean
	  * @since 0.1
	  */
	protected $virtualMode;
	/** Boolean used to determine if a lazy table should load a model when you try to obtain an ID.
	  * @returns boolean
	  * @since 0.1
	  */
	protected $loadWhenGet;
	/** A name of the parent used when we want to save data.
	  * @returns string
	  * @since 0.1
	  */	
	protected $parentName;
	/** A name of the connection used when we want to save data.
	  * @returns string
	  * @since 0.1
	  */	
	protected $connectionName;
	/** A position value used in an Iterator interface.
	  * @returns integer
	  * @since 0.1
	  */
	protected $position;

	/** Constructs a new lazy table. Takes an optional connection name and the parent name.
	  * @param string $connectionName Used to determine which data IDs are stored in a lazy table. <strong>If you don't set this one, auto-getting (based by a model) will be disabled.</strong>
	  * @param string $parentName Used to determine the parent of data. <strong>If you don't set this one, saving will be disabled.</strong>
	  * @since 0.1
	  */
	public function __construct( $connectionName = NULL, $parentName = NULL ) {
    spl_autoload_register( Array( get_class($this), 'autoload' ) );
		$this->data = Array();
		$this->virtualRecords = Array();
		$this->virtualMode = TRUE;
		$this->loadWhenGet = TRUE;
		$this->parentName = $parentName;
		$this->connectionName = $connectionName;
		$this->position = 0;
	}
  
  /** Loads models automatically.
    * @param string $className A class name of class being constructed.
    * @since 0.2
    */
  protected static function autoload( $className ) {
    $className = ucfirst($className);
    if( file_exists( MVC_MAIN_DIRECTORY . MVC_MODELS . $className . '.php' ) ) 
      require_once MVC_MAIN_DIRECTORY . MVC_MODELS . $className . '.php';
  }
  
   /** Sets a parent name after a lazy table is constructed.
     * @param string $name Parent name to be set.
     * @since 0.1
     */
   public function setParent($name) {
      $this->parentName = $name;
   }
	
	/** Used for an ArrayAccess interface, Shouldn't be used.
	  * @returns boolean
	  * @since 0.1
	  */
	public function offsetExists( $offset ) {
		return isSet($this->data[$offset]);
	}
	
	/** Used for an ArrayAccess interface, Shouldn't be used.
	  * @returns NULL
	  * @since 0.1
	  */	
	public function offsetSet( $offset, $value ) {
		$value = ( $value instanceof AbstractModel )? $value->id : $value;
		if( is_null($offset) ) {
			$this->data[] = $value;
			if( $this->virtualMode ) $this->virtualRecords[] = $value;
		}
		else return NULL;
	}
	
	/** Used for an ArrayAccess interface, Shouldn't be used.
	  * @returns Model|integer
	  * @since 0.1
	  */	
	public function offsetGet( $offset ) {
		return !isSet($this->data[$offset])? NULL : 
			( (isSet($this->connectionName) && $this->loadWhenGet)? new $this->connectionName($this->data[$offset]) : $this->data[$offset]);
	}
	
	/** Used for an ArrayAccess interface, Shouldn't be used.
	  * @since 0.1
	  */
	public function offsetUnset( $offset ) { 
		$temp = $this->data[ count( $this->data )-1 ];
		unset( $this->data[$offset] );
		$this->data[$offset] = $temp;
	}
	

	/** Saves all virtual records in a database. Returns true if saving is done.
	  * @param PDO $dbHandler Database handler used for connections.
	  * @param integer $parentId Parent ID used to determine how it should be saved.
	  * @throws ModelException If something went wrong, ie. parent, connection name isn't set or database handler is not valid.
	  * @returns boolean
	  * @since 0.1
	  */	
	public function save( $dbHandler, $parentId ) {
		if( is_null( $this->parentName ) || is_null( $this->connectionName ) ) {
			throw new ModelException( "can't save a lazy connections table without a parent or connection." );
			return FALSE;
		}
		
		$connectionTable = $this->tableName($this->parentName) . '_' . $this->tableName($this->connectionName);

		if( !($dbHandler instanceof PDO) ) {
			throw new ModelException( 'invalid database handler in LCT->save().' );
			return FALSE;
		}

		try {
			$sql = $dbHandler->prepare( 'INSERT INTO ' . $connectionTable . ' VALUES( ?, ? )' );

			foreach( $this->virtualRecords as $virtualRecord ) {
				$sql->execute( Array( $parentId, $virtualRecord ) );

				if( $dbHandler->errorCode() != '00000' ) {
					$dbHandler->rollBack();
					return FALSE;
					global $g_debug;
					$g_debug->logRaw( var_export( $dbHandler->errorInfo() ) );
				}
			}
		}
		catch( PDOException $pdo ) {
			throw new DatabaseException( 'failed to save a virtual records: ' . $pdo->getMessage() . ' [SQL ERR: ' . $pdo->__toString() . ']' );
			return FALSE;
		}
		
		unset( $this->virtualRecords );
		$this->virtualRecords = Array();
		return TRUE;
	}
	
	/** Used for an Iterator interface. Shouldn't be used.
	  * @since 0.1
	  */
	public function rewind() {
		$this->position = 0;
	}
	
	/** Used for an Iterator interface. Shouldn't be used.
	  * @since 0.1
	  */	
	public function current() {
		return $this->offsetGet($this->position);
	}

	/** Used for an Iterator interface. Shouldn't be used.
	  * @since 0.1
	  */	
	public function key() {
		return $this->position;
	}

	/** Used for an Iterator interface. Shouldn't be used.
	  * @since 0.1
	  */	
	public function next() {
		++$this->position;
	}

	/** Used for an Iterator interface. Shouldn't be used.
	  * @since 0.1
	  */	
	public function valid() {
		return isSet($this->data[$this->position]);
	}

	/** Disables the virtual mode. By default it's set.
	  * @since 0.1
	  */
	public function disableVirtualMode() { $this->virtualMode = FALSE; }

	/** Enables the virtual mode. By default it's set.
	  * @since 0.1
	  */	
	public function enableVirtualMode() { $this->virtualMode = TRUE; }
	
	/** Enables loading when get. By default it's set.
	  * @since 0.1
	  */
	public function enableLoading() { $this->loadWhenGet = TRUE; }
	
	/** Disables loading when get. By default it's set.
	  * @since 0.1
	  */
	public function disableLoading() { $this->loadWhenGet = FALSE; }
	
	/** Additional method used to determine conventional table name.
	  * @param NULL|string $name A model name used to create a table name from it. If NULL, actual class' name is assumed.
	  * @returns string
	  * @since 0.1
	  */
	protected function tableName($name = NULL) {
		if( !$name ) $name = get_class($this);
		$o = new $name();
		return $o->tableName();
	}

	/** Returns raw IDs of connected table.
	  * @returns array
	  * @since 0.2
	  */
	public function rawIDs() {
		return $this->data;
	}

  
  public function count() {
    return count($this->data);
  }
}
?>
