<?php

require_once(__DIR__.'/config.php');
require_once(__DIR__.'/include/ArrestDB.php');
require_once(__DIR__.'/include/ApiResponse.php');
require_once(__DIR__.'/include/SwaggerHelper.php');
require_once(__DIR__.'/include/ResterController.php');
require_once(__DIR__.'/include/ApiCacheManager.php');
require_once(__DIR__.'/include/model/RouteCommand.php');

//TODO; Make this smarter
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

$resterController = new ResterController();

if(isset($_GET["cacheClear"])) {
	ApiCacheManager::clear();
	exit(ArrestDB::Reply("Cache Clear!"));
}

if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('Rester should not be run from CLI.' . PHP_EOL);
}

if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

else if (array_key_exists('HTTP_X_HTTP_METHOD_OVERRIDE', $_SERVER) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

//File processor
$resterController->addFileProcessor("imagenes_poi", "imagen");
$resterController->addFileProcessor("poi", "imagenDefecto");


$loginCommand = new RouteCommand("POST", "usuarios", "login", function($params = NULL) {
	error_log("Processing login");
	global $resterController;
	
	$filter["login"]=$params["login"];
	$filter["password"]=md5($params["password"]);
	
	$result = $resterController->getObjectsFromRouteName("usuarios", $filter);

	$resterController->showResult($result);
}, array("login", "password"), "Method to login users");

$resterController->addRouteCommand($loginCommand);

$poisRouteCommand = new RouteCommand("GET", "ruta", "getRutaWithPois", function($params = NULL) {
	error_log("Processing ruta pois");
	
	global $resterController;
	
	$result = $resterController->getObjectsFromRouteName("ruta", $params);
	
	$resultWithChilds = array();
	
	foreach($result as $row) {
		$filter = array("ruta" => $row["id"]);
	
		$childs = $resterController->getObjectsFromRouteName("poi_ruta", $filter);
		
		$pois = array();
		
		foreach($childs as $c) {
			$pois[] = $c["poi"];
		}
		
		$row["pois"]=$pois;
		$resultWithChilds[]=$row;
	}

	$resterController->showResult($resultWithChilds, true);
	
});

$resterController->addRouteCommand($poisRouteCommand);
 
 
 $routePoisCommand = new RouteCommand("GET", "poi", "getPoiWithRutas", function($params = NULL) {
	error_log("Processing getPoiWithRutas");
	
	global $resterController;
	
	$result = $resterController->getObjectsFromRouteName("poi", $params);
	
	$resultWithChilds = array();
	
	foreach($result as $row) {
		$filter = array("poi" => $row["id"]);
	
		$childs = $resterController->getObjectsFromRouteName("poi_ruta", $filter);
		
		$rutas = array();
		if(isset($childs) && count($childs) > 0) {		
			foreach($childs as $c) {
				$rutas[] = $c["ruta"];
			}
		}
		
		$row["rutas"]=$rutas;
		$resultWithChilds[]=$row;
	}

	$resterController->showResult($resultWithChilds, true);
	
});

$resterController->addRouteCommand($routePoisCommand);

//Do the work
$resterController->processRequest($requestMethod);

$result = ApiResponse::errorResponse(405);

exit(ArrestDB::Reply($result));

