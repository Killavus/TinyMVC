<?php
	abstract class ControllerHandler {
		protected $v;
		
		public function __construct() {
			spl_autoload_register( Array( 'ControllerHandler', 'autoloadModels' ) );
			$this->v = Array(); 
		}
		
		protected function args() {
			global $g_router;
			return $g_router->args();
		}
		
		protected function render() {
			$v = $this->v;
			
			global $g_router;
			$controller = strtolower( $g_router->controller() );
			$action = $g_router->action();
			
			if( !file_exists( MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php' ) ) {
				throw new ControllerException( 'render not found: ' . MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php' );
				foreach( $v as $key => $v ) unset( $this->vars[ $key ] );
				return false;
			}
			
			require MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php';
			foreach( $v as $key => $v ) unset( $this->v[ $key ] );
			return true;
		}
		
		protected function renderPart( $partName ) {
			$v = $this->v;
			
			global $g_router;
			$controller = strtolower( $g_router->controller() );
			if( !file_exists( MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . 'part' . '_' . $partName . '.php' ) ) {
				throw new ControllerException( 'render part not found: ' . MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '_' . $partName . '.php' );
				return false;
			}
			require MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . 'part' . '_' . $partName . '.php';
			return true;
		}
		
		protected function renderPartTo( &$var, $partName ) {
			ob_start();
			$v = $this->v;
			
			global $g_router;
			$controller = strtolower( $g_router->controller() );
			$action = $g_router->action();
			if( !file_exists( MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '_' . $partName . '.php' ) ) {
				throw new ControllerException( 'render part not found: ' . MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '_' . $partName . '.php' );
				return false;
			}
			require MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php';
			$var = ob_get_contents();
			ob_end_clean();
			return true;		
		}
		
		protected function renderTo( &$var ) {
			ob_start();
			$v = $this->v;
			
			global $g_router;
			$controller = strtolower( $g_router->controller() );
			$action = $g_router->action();
			
			if( !file_exists( MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php' ) ) {
				throw new ControllerException( 'render not found: ' . MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php' );
				foreach( $v as $key => $v ) unset( $this->vars[ $key ] );
				return false;
			}
			
			require MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php';
			$var = ob_get_contents();
			ob_end_clean();
			return true;
		}
		
		protected function renderExternal( $action, $controller = NULL ) {
			$v = $this->v;
			
			global $g_router;
			$controller = !$controller? $g_router->controller() : $controller;
			
			if( !file_exists( MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php' ) ) {
				throw new ControllerException( 'render not found: ' . MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php' );
				foreach( $v as $key => $v ) unset( $this->vars[ $key ] );
				return false;
			}
			
			require MVC_MAIN_DIRECTORY . MVC_VIEWS . $controller . '_' . $action . '.php';
			return true;
		}
		
		protected static function autoloadModels( $model ) {
			if( file_exists( MVC_MAIN_DIRECTORY . MVC_MODELS . $model . '.php' ) ) require_once MVC_MAIN_DIRECTORY . MVC_MODELS . $model . '.php';
		}
	}
?>