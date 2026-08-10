<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\AuthService;
use App\Config\Database;

require '../../vendor/autoload.php';
require '../../include/dbsol/conn.php';

$authService = new AuthService();
$authToken = $_ENV['AUTH_TOKEN'] ?? $_ENV['authtoken'] ?? 'test';

$app = new \Slim\App;

$app->get('/', function (Request $request, Response $response, array $args) use ($authToken) {

	$authorization_header = $request->getHeader("Authorization");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	$return =  array('status' => 'success', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'method allowed,post', 'data' => null);

	return $response->withStatus(200)
		->withHeader('Content-Type', 'application/json')
		->write(json_encode($return));
});

$app->post('/login', function (Request $request, Response $response) use ($authService) {

	$user_id = $request->getParam('email');
	$pwd = $request->getParam('password');

	// Ensure that email and password are not empty
	if (empty($user_id) || empty($pwd)) {
		$return = array('status' => 'false', 'Message' => 'email and password are required', 'data' => null);
		return $response->withStatus(400)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	try {
		$mainDb = Database::getMainDbName();
		$login = R::getRow("SELECT * FROM `{$mainDb}`.`secure_login` WHERE `email` = ?", [$user_id]);

		if ($login == null) {
			$return = array('status' => 'false', 'Message' => 'user can not be found.', 'data' => 'Please enter a valid user!');
			return $response->withStatus(401)
				->withHeader('Content-Type', 'application/json')
				->write(json_encode($return));
		} else {
			if ($authService->verifyPassword($pwd, $login['password'])) {
				$jwt = $authService->generateToken([
					'email' => $login['email'],
					'ref_id' => $login['memberid']
				]);

				$return = array('status' => 'success', 'Message' => 'user found.', 'data' => $login, 'token' => $jwt);

				return $response->withStatus(200)
					->withHeader('Content-Type', 'application/json')
					->write(json_encode($return));
			} else {
				$return = array('status' => 'Failed', 'Message' => 'Incorrect Password!', 'data' => null);

				return $response->withStatus(400)
					->withHeader('Content-Type', 'application/json')
					->write(json_encode($return));
			}
		}
	} catch (Exception $e) {
		$error = array('status' => 'error', 'Message' => 'system Failure: ' . $e->getMessage());
		return $response->withStatus(500)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($error));
	} finally {
		R::close();
	}
});

$app->post('/add/user', function (Request $request, Response $response) use ($authService) {

	// Personal information
	$firstname = $request->getParam('firstname');
	$lastname = $request->getParam('lastname');
	$phone = $request->getParam('phone');
	$email = $request->getParam('email');
	$designation = $request->getParam('designation');
	$raw_pwd = $request->getParam('password');
	$pwd = $authService->hashPassword($raw_pwd);

	$contactPerson = $firstname . ' ' . $lastname;

	// Hospital information
	$orgname = $request->getParam('hos_name');
	$address_name = $request->getParam('address');
	$address_name1 = $request->getParam('address_1');
	$hos_type = $request->getParam('type');
	$city = $request->getParam('city');
	$state = $request->getParam('states');
	$bed = $request->getParam('bed');
	$depart = $request->getParam('depart');
	$oSource = $request->getParam('oSource');
	$power = $request->getParam('power');
	$technical = $request->getParam('technical');

	try {
		$org = saveOrg($orgname, $address_name, $address_name1, $hos_type, $city, $state, $bed, $depart, $oSource, $power, $technical, $contactPerson, $designation, $phone, $email);

		saveUser($email, $pwd, $org, 'hospital');

		$return = array('status' => 'success', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'hospital was created.', 'data' => $org);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} catch (Exception $e) {
		$error = array('status' => 'error', 'Message' => 'system Failure: ' . $e->getMessage());
		return $response->withStatus(500)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($error));
	} finally {
		R::close();
	}
});

$app->post('/supervisor/add/user', function (Request $request, Response $response) use ($authService) {

	// Personal information
	$firstname = $request->getParam('firstname');
	$lastname = $request->getParam('lastname');
	$phone = $request->getParam('phone');
	$email = $request->getParam('email');
	$designation = $request->getParam('designation');
	$raw_pwd = $request->getParam('password');
	$pwd = $authService->hashPassword($raw_pwd);

	$contactPerson = $firstname . ' ' . $lastname;

	// Hospital information
	$orgname = $request->getParam('hos_name');
	$address_name = $request->getParam('address');
	$address_name1 = $request->getParam('address_1');
	$hos_type = $request->getParam('type');
	$city = $request->getParam('city');
	$state = $request->getParam('states');
	$bed = $request->getParam('bed');
	$depart = $request->getParam('depart');
	$oSource = $request->getParam('oSource');
	$power = $request->getParam('power');
	$technical = $request->getParam('technical');

	try {
		$org = saveOrg($orgname, $address_name, $address_name1, $hos_type, $city, $state, $bed, $depart, $oSource, $power, $technical, $contactPerson, $designation, $phone, $email);

		saveUser($email, $pwd, $org, 'supervisor');

		$return = array('status' => 'success', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'hospital was created.', 'data' => $org);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} catch (Exception $e) {
		$error = array('status' => 'error', 'Message' => 'system Failure: ' . $e->getMessage());
		return $response->withStatus(500)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($error));
	} finally {
		R::close();
	}
});


$app->run();

function saveOrg($orgname, $address_name, $address_name1, $hos_type, $city, $state, $bed, $depart, $oSource, $power, $technical, $contactPerson, $designation, $phone, $email)
{

	$hospital = R::dispense('hospital');

	$hospital->name = $orgname;
	$hospital->addressLine1 = $address_name;
	$hospital->addressLine2 = $address_name1;
	$hospital->city = $city;
	$hospital->state = $state;
	$hospital->hospitals_type = $hos_type;
	$hospital->bedCap = $bed;
	$hospital->departs = $depart;
	$hospital->oxygenSource = $oSource;
	$hospital->powerBackup = $power;
	$hospital->technicals = $technical;
	$hospital->powerBackup = $power;
	$hospital->contactPerson = $contactPerson;
	$hospital->contactRole = $designation;
	$hospital->contactPhone = $phone;
	$hospital->contactEmail = $email;

	//retrive id
	$id = R::store($hospital);

	//return store id  
	return $id;
}

function saveUser($email, $pwd, $org, $privileges)
{
	$user = R::dispense('user');

	$user->email = $email;
	$user->pwd = $pwd;
	$user->privileges = $privileges;
	$user->org_id = $org;

	//retrive id
	$id = R::store($user);

	//return store id  
	return $id;
}
