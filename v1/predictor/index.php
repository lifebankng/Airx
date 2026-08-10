use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\AuthService;
use App\Services\PredictorService;
use App\Services\HospitalService;
use App\Config\Database;

require '../../vendor/autoload.php';
require '../../include/dbsol/conn.php';
include_once '../../include/functions/helper.php';

$authService = new AuthService();
$predictorService = new PredictorService();
$hospitalService = new HospitalService();
$authToken = $_ENV['AUTH_TOKEN'] ?? $_ENV['authtoken'] ?? 'test';

$app = new \Slim\App;

$authMiddleware = function ($request, $response, $next) use ($authService) {
	$authHeader = $request->getHeaderLine('Authorization');

	if (!$authHeader || !preg_match('/Bearer\s+(\S+)/i', trim($authHeader), $matches)) {
		$data = [
			'status'  => 'error',
			'code'    => 'AUTH_HEADER_MISSING',
			'message' => 'Missing or invalid authorization token'
		];

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($data, JSON_UNESCAPED_SLASHES));
	}

	$jwt = $matches[1];

	try {
		$decoded = $authService->decodeToken($jwt);
		$request = $request->withAttribute('user', $decoded);
		return $next($request, $response);
	} catch (\Firebase\JWT\ExpiredException $e) {
		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode([
				'status'  => 'error',
				'code'    => 'TOKEN_EXPIRED',
				'message' => 'Token has expired'
			], JSON_UNESCAPED_SLASHES));
	} catch (\Firebase\JWT\SignatureInvalidException $e) {
		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode([
				'status'  => 'error',
				'code'    => 'TOKEN_INVALID_SIGNATURE',
				'message' => 'Invalid token signature'
			], JSON_UNESCAPED_SLASHES));
	} catch (Exception $e) {
		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode([
				'status'  => 'error',
				'code'    => 'TOKEN_INVALID',
				'message' => 'Invalid token: ' . $e->getMessage()
			], JSON_UNESCAPED_SLASHES));
	}
};

$app->get('/', function (Request $request, Response $response, array $args) use ($authToken) {

	$authorization_header = $request->getHeader("Authorization");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'Data Processing.', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	$return =  array('status' => 'success', 'Description' => 'Data Processing.', 'Message' => 'method allowed,post', 'data' => null);

	return $response->withStatus(200)
		->withHeader('Content-Type', 'application/json')
		->write(json_encode($return));
});

$app->post('/run', function (Request $request, Response $response) use ($authToken, $predictorService) {

	$authorization_header = $request->getHeader("Authorization");
	$hospitalid = (int)$request->getParam('hospitalid');
	$time = time();

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {
		$return = array('status' => 'false', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	try {
		$needs = $predictorService->calculateNeeds($request->getParams());
		$predictorService->savePrediction($hospitalid, $needs, $time);

		$return = array('status' => 'success', 'Description' => 'Data Processing.', 'Message' => 'data was successfully processed', 'data' => $needs);

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

$app->post('/run/supervisor', function (Request $request, Response $response) use ($authToken, $predictorService) {

	$authorization_header = $request->getHeader("Authorization");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {
		$return = array('status' => 'false', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	try {
		$needs = $predictorService->calculateSupervisorNeeds($request->getParams());

		$return = array('status' => 'success', 'Description' => 'Data Processing.', 'Message' => 'data was successfully processed', 'data' => $needs);

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

$app->get('/hospitals', function (Request $request, Response $response) use ($hospitalService) {
	try {
		$user = $request->getAttribute('user');
		$ref_id = (int)($user['ref_id'] ?? 0);

		$data = $hospitalService->getDashboardData($ref_id);

		$payload = [
			'status'      => 'success',
			'description' => 'Hospital dashboard data endpoint',
			'data'        => $data
		];

		$response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

		return $response
			->withHeader('Content-Type', 'application/json')
			->withStatus(200);
	} catch (Exception $e) {
		$errorPayload = [
			'status'  => 'error',
			'message' => 'System failure',
			'details' => $e->getMessage()
		];

		$response->getBody()->write(json_encode($errorPayload, JSON_PRETTY_PRINT));

		return $response
			->withHeader('Content-Type', 'application/json')
			->withStatus(500);
	} finally {
		R::close();
	}
})->add($authMiddleware);

$app->get('/hospital/predict', function (Request $request, Response $response) {
	try {
		$user = $request->getAttribute('user');
		$refid = $user['ref_id'] ?? null;

		// $refid = 416752380;

		if (!$refid) {
			return $response->withStatus(400)
				->withHeader('Content-Type', 'application/json')
				->write(json_encode([
					'status' => 'error',
					'message' => 'Missing hospital reference ID'
				]));
		}

		// Validate and sanitize input
		$refid = filter_var($refid, FILTER_VALIDATE_INT);
		if ($refid === false) {
			return $response->withStatus(400)
				->withHeader('Content-Type', 'application/json')
				->write(json_encode([
					'status' => 'error',
					'message' => 'Invalid hospital reference ID'
				]));
		}

		$state = R::getCell("SELECT user_info.state FROM `lifebank_plus`.`secure_login` LEFT JOIN `lifebank_plus`.user_info on user_info.ref_id = secure_login.memberid WHERE memberid = ?", [$refid]);

		// Use prepared statements properly
		$history = R::getAll("
            SELECT 
                DATE_FORMAT(FROM_UNIXTIME(o.tym), '%Y-%m') AS month,
                SUM(o.qty * CAST(REPLACE(ox.size, ' Cubic Meter', '') AS DECIMAL(10,2))) AS total_cubic_meters
            FROM lifebank_plus.oxygen_order AS o
            LEFT JOIN lifebank_plus.oxygen AS ox ON o.product = ox.id
            WHERE o.order_by = ?
            GROUP BY YEAR(FROM_UNIXTIME(o.tym)), MONTH(FROM_UNIXTIME(o.tym))
            ORDER BY FROM_UNIXTIME(o.tym) ASC
            LIMIT 12
        ", [$refid]);

		if (empty($history)) {
			$history = R::getAll("SELECT DATE_FORMAT(date_used, '%Y-%m') AS month, SUM(estimate_need) AS total_cubic_meters FROM `data` WHERE hospitalID = ? GROUP BY month ORDER BY month ASC LIMIT 12", [$refid]);
		} else {
			$result = [
				'status' => 'success',
				'description' => 'Advice for Optimal operation efficeny of the hospital',
				'hospital_id' => $refid,
				'data' => [
					'prediction_cubic_meters' =>  2,
					'method' => "advice",
					'accuracy' => 0.1,
					'history_count' => 0
				]
			];
			return $response->withStatus(200)
				->withHeader('Content-Type', 'application/json')
				->write(json_encode($result));
		}

		// Replace mock data with actual weather API or historical averages
		foreach ($history as &$h) {
			$weather = getWeatherStats($h['month'], $state);
			// TODO: Replace with actual weather data API call
			$h['avg_temp'] = $weather['temperature'];
			$h['avg_humidity'] = $weather['humidity'];
		}

		$locationFactor = 1.0; // Could be fetched from hospital profile

		$prediction = predictMonthlyOxygenNeed($history, $locationFactor);

		$result = [
			'status' => 'success',
			'description' => 'Predicted oxygen requirement for next month',
			'hospital_id' => $refid,
			'data' => [
				'prediction_cubic_meters' => $prediction['prediction'],
				'method' => $prediction['method'],
				'accuracy' => $prediction['accuracy'],
				'history_count' => count($history)
				// Removed coefficients from response for security
			]
		];
		saveRun($refid, $prediction, time());

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($result, JSON_UNESCAPED_SLASHES));
	} catch (Exception $e) {
		error_log("Prediction failed: " . $e->getMessage());
		return $response->withStatus(500)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode([
				'status' => 'error',
				'message' => 'Prediction service temporarily unavailable'
			]));
	}
})->add($authMiddleware);

$app->get('/view/{id}', function (Request $request, Response $response, array $args) use ($authToken) {

	$id = $request->getAttribute('id');

	$authorization_header = $request->getHeader("Authorization");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'Data Processing.', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	try {

		$book  = R::find('predictions', 'hospital_id = ?', [$id]);

		$return =  array('status' => 'success', 'Description' => 'Preciditons history.', 'Message' => 'data was collected', 'data' => $book);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} catch (Exception $e) {
		$error = "system Failure!" . $e;
		return json_encode($error);
	}
});

$app->post('/test', function (Request $request, Response $response) use ($authToken) {
	require_once  '../../vendor/autoload.php';

	$authorization_header = $request->getHeader("Authorization");

	$peaditric = $request->getParam('peaditric');
	$malaria = $request->getParam('malaria');
	$intensive = $request->getParam('intensive');
	$accident = $request->getParam('accident');
	$Theatre = $request->getParam('theatre');
	$Materinity = $request->getParam('materinity');
	$Diabetes = $request->getParam('diabetes');
	$Typhoid = $request->getParam('typhoid');
	$hospitalid = $request->getParam('hospitalid');

	$time = strtotime("now");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	try {


		$stmt = "SELECT (oo.`qty` * SUBSTRING_INDEX(o.`size`, ' ', 1)) AS quantity, oo.`tym` AS 'datetime' FROM `lifebank_plus`.`oxygen_order` oo JOIN `lifebank_plus`.`oxygen` o ON o.id = oo.product WHERE `order_state` = 'completed' AND `order_by` = ?";

		$te = R::getAll($stmt, [$hospitalid]);
		$predicted_quantity = predictNeedForTomorrow(json_encode($te));
		//$needs = (13.1414 + (3.5123 * $peaditric) + (5.4793 * $malaria) + (2.2490 * $intensive) + (6.8767 * $accident) + ($Theatre * 0.1935) + ($Materinity * 5.9922) + ($Typhoid * -10.1190) + ($Diabetes * -6.0203));

		saveRun($hospitalid, $needs, $time);

		$return =  array('status' => 'success', 'Description' => 'Data Processing.', 'Message' => 'data was successfully processed', 'data' => $te);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($te));
	} catch (Exception $e) {
		$error = "system Failure!" . $e;
		return json_encode($error);
	}

	R::close();
});

$app->run();


function saveRun($hospital, $result, $time)
{
	$prediction = R::dispense('predictions');

	$prediction->hospitalID = $hospital;
	$prediction->predictions = $result['prediction'] ?? 0;
	$prediction->method = $result['method'] ?? null;
	$prediction->accuracy = $result['accuracy'] ?? 0;
	$prediction->tym = $time;

	//retrive id
	$id = R::store($prediction);

	//return store id  
	return $id;
}

function predictNeedForTomorrow($json_data)
{
	// Decode JSON data
	$data = json_decode($json_data, true);
	//    $data = $json_data;

	// Initialize arrays to store features and targets
	$features = [];
	$targets = [];

	// Extract features and targets from the data
	foreach ($data as $order) {
		$quantity = intval($order['quantity']);
		$datetime = intval($order['datetime']);

		// Convert Unix timestamp to datetime
		$datetime = new DateTime('@' . $datetime);

		// Extract features
		$features[] = [
			$datetime->format('m'), // Month
			$datetime->format('d'), // Day
			$datetime->format('H') // Hour
		];

		// Targets (quantities)
		$targets[] = $quantity;
	}

	// Initialize and train Support Vector Regression (SVR) model
	$regression = new \Phpml\Regression\SVR(\Phpml\SupportVectorMachine\Kernel::LINEAR);
	$regression->train($features, $targets);

	// Generate predictions for future dates
	$future_date = new DateTime('+1 months');
	$future_features = [
		[
			$future_date->format('m'),
			$future_date->format('d'),
			$future_date->format('H')
		]
	];

	// Predict need for tomorrow
	$predicted_quantities = $regression->predict($future_features);

	return $predicted_quantities[0];
}
