<?php
/** Root of a model classes - defines an interface and (possibly) basic functions indepentent from data retrieval methods.
 * @author Killavus
 * @package Killavus
 * @category Model
 * @version 0.1
 */
require_once 'ModelConstants.php';

abstract class AbstractModel {
    /** An array defining a structure of the data.
      * @returns Array
      * @since 0.1
      */
	protected static $structure;
    /** An array defining connections between datasets.
      * @returns Array
      * @since 0.1
      */
	protected static $connections;
	/** An array defining used validators in the model.
	  * @returns Array
	  * @since 0.1
	  */
	protected static $validators;
	/** An array defining used filters in the model.
	  * @returns Array
	  * @since 0.1
	  */
	protected static $filters;
	
	/** An array containing retrieved (or put into a dataset by user) data.
	  * @returns Array
	  * @since 0.1
	  */
	protected $data;
	/** Defines whether an object is virtual (do not exist in dataset) or actually exists in the dataset.
	  * @returns boolean
	  * @since 0.1
	  */
	protected $virtual;
	
	/** Constructs a new model object.
	  * @param integer $id ID of an object - if NULL, object is created as a virtual one.
	  * @since 0.1
	  */
	public function __construct( $id = NULL ) {
		self::$structure[get_class($this)] = $this->structure();
		self::$connections[get_class($this)] = $this->connections();
		self::$validators[get_class($this)] = $this->validators();
		self::$filters[get_class($this)] = $this->filters();
		
		$this->virtual = FALSE;
		
		if( $id && $this->validID($id) ) $this->virtual = $this->load($id)? FALSE : TRUE;
		else $this->virtual = TRUE;
	}
	
	/** Creates a search object used for further retrieving data from dataset.
	  * @returns SearchEngine
	  * @since 0.1
	  */
	abstract public function search();
	
	/** Loads data from the dataset. All previous data will be erased. 
	  * <strong>Connections are lazy - retrieval of a new object will be performed only on demand.</strong> Returns TRUE if load is succesful - FALSE otherwise.
	  * @returns boolean
	  * @since 0.1
	  */
	abstract public function load( $id );
	/** Saves a current object in a database. If ID is NULL, it'll be created a new one with ID given by the dataset (if virtual) or saved on a current ID (if loaded). Returns FALSE if save can not be performed - TRUE otherwise.
	 * @returns boolean
	 * @since 0.1
	 */
	abstract public function save( $id = NULL );
	/** Deletes actual object from the dataset. Returns FALSE if delete can not be performed - TRUE otherwise.
	  * @returns boolean
	  * @since 0.1
	  */
	abstract public function delete();
	
	/** Defines a structure of data in the dataset. It should be used to fill up the $structure array.
	  * @see AbstractModel::$structure
	  * @returns void
	  * @since 0.1
	  */
	abstract protected function structure();
	/** Defines a structure of connections with other datasets. It should be used to fill up the $connections array.
	  * @see AbstractModel::$connections
	  * @returns void
	  * @since 0.1
	  */	
	abstract protected function connections();
	/** Used to set up validators for data keys. It should be used to fill up the $validators array.
	  * @see AbstractModel::$validators
	  * @returns void
	  * @since 0.1
	  */	
	abstract protected function validators();
	/** Used to set up filters for data keys. It should be used to fill up the $filters array.
	  * @see AbstractModel::$filters
	  * @returns void
	  * @since 0.1
	  */		
	abstract protected function filters();
	
	/** Performs the filtering of an output data. Returns the filtered data.
	  * @param string $key A key for data which should be filtered.
	  * @returns mixed
	  * @since 0.1
	  */
	protected function filteredData( $key ) {
		if( !isSet($this->data[$key]) ) return NULL;
		if( $this->data[$key] instanceof LazyConnectionsTable ) return $this->data[$key];
		$filtered = $this->data[$key];
		if( isSet( self::$filters[get_class($this)][$key] ) ) {
			foreach( self::$filters[get_class($this)][$key] as $filter ) {
				$filtered = call_user_func_array( $filter, Array($this->data[$key]) );
			}
		}
		return $filtered;
	}
	
	/** Performs the validating of an input data. Returns TRUE when a data candidate passed all validators. Otherwise, FALSE is returned.
	  * @param string $key string A key for data which should be validated.
	  * @param string $dataCandidate A candidate for being a new data.
	  * @returns boolean
	  * @since 0.1
	  */
	protected function applyValidators( $key, $dataCandidate ) {
		$validationStatus = TRUE;
		if( !isSet( self::$validators[$key] ) ) return TRUE;
		
		foreach( self::$validators[get_class($this)][$key] as $validator ) {
			list( $validatorCallback, $validatorType ) = $validator;
			switch( $validatorType ) {
				case ValidatorType::BOOLEAN_TYPE:
					$return = call_user_func_array( $validatorCallback, Array($dataCandidate) );
					$validationStatus |= $return;
				break;
				
				case ValidatorType::SANITIZOR:
					call_user_func_array( $validatorCallback, Array(&$dataCandidate) );
				break;
			}
		}
		return $validationStatus;
	}
	
	public function __get( $key ) {
		return $this->filteredData( $key );
	}
	
	public function __set( $key, $data ) {
		if( $data instanceof LazyConnectionsTable || $this->applyValidators( $key, $data ) ) $this->data[$key] = $data;
		else return NULL;
	}
	
	/** Validates the passed ID. Used in a construtor - if an ID is not valid, it'll not be loaded.
	  * @returns boolean
	  * @param integer $id ID which will be validated.
	  * @since 0.1
	  */
	private function validID( $id ) {
		$id = intval($id);
		return $id > 0;
	}
	
	/** Checks whether an object is an existing in dataset object, or a non-existing one (for example, created for saving, or not loaded).
	 * @returns boolean
	 * @since 0.1
	 */
	public function loaded() {
		return !$this->virtual;
	}
}
?>
