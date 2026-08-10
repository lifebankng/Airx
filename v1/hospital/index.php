use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\AuthService;
use App\Services\HospitalService;
use App\Config\Database;

require '../../vendor/autoload.php';
require '../../include/dbsol/conn.php';

$authService = new AuthService();
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

		$return =  array('status' => 'false', 'Description' => 'hospital informations endpoints', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints', 'Message' => 'method allowed,post', 'data' => null);

	return $response->withStatus(200)
		->withHeader('Content-Type', 'application/json')
		->write(json_encode($return));
});

$app->get('/dashboard', function (Request $request, Response $response, array $args) {

	$authHeader = $request->getHeaderLine('Authorization'); // returns a string instead of an array

	try {

		$user = $request->getAttribute('user');
		 $refid = $user['ref_id'];
		//$refid = "416752380";

		$lastSixMonthsUsage = R::getAll("SELECT DATE_FORMAT(FROM_UNIXTIME(o.tym), '%Y') AS month_year, DATE_FORMAT(FROM_UNIXTIME(o.tym), '%M') AS month_name, SUM(o.qty * CAST(REPLACE(ox.size, ' Cubic Meter', '') AS DECIMAL(10,2))) AS total_cubic_meters FROM lifebank_plus.oxygen_order AS o LEFT JOIN lifebank_plus.oxygen AS ox ON o.product = ox.id WHERE FROM_UNIXTIME(o.tym) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND o.order_by = ? GROUP BY YEAR(FROM_UNIXTIME(o.tym)), MONTH(FROM_UNIXTIME(o.tym)) ORDER BY FROM_UNIXTIME(o.tym) DESC", [$refid]);
		$lastSixMonthPredict = R::getAll("SELECT predictions as total_cubic_meters, DATE_FORMAT(FROM_UNIXTIME(tym), '%Y') AS month_year, DATE_FORMAT(FROM_UNIXTIME(tym), '%M') AS month_name FROM `predictions` WHERE hospital_id = ? AND FROM_UNIXTIME(tym) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)", [$refid]);
		$last_order = R::getAll("SELECT *,(SELECT size from lifebank_plus.oxygen WHERE oxygen.id = lifebank_plus.oxygen_order.product)as size FROM lifebank_plus.oxygen_order WHERE order_by = ? ORDER BY tym DESC LIMIT 1", [$refid]);


		if($last_order["schedule_date"] == null || $last_order["schedule_date"] == "0000-00-00"){
			$delivery_day = "No upcoming delivery";
		}else{
			$delivery_day = $last_order["schedule_date"];
		}

		//selet the last perdict from $lastSixMonthPredict
		$lastSixMonthPredict = end($lastSixMonthPredict);

		$top =["stock"=>$lastSixMonthPredict["total_cubic_meters"], "days"=>30, "delivery_day"=>$delivery_day];

		$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints',  'data' => ['lastSixMonthsUsage' => $lastSixMonthsUsage, 'lastSixMonthPredict' => $lastSixMonthPredict, "last_order" => $last_order,"top"=>$top]);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} catch (Exception $e) {

		$response->getBody()->write(json_encode([
			'status' => 'error',
			'message' => 'Token invalid or expired'
		]));
		return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
	}
})->add($authMiddleware);

$app->get('/orders', function (Request $request, Response $response, array $args) {

	try {

		$user = $request->getAttribute('user');
		$refid = $user['ref_id'];
		// $refid = "416752380";

		$orders = R::getAll("SELECT *,(SELECT size from lifebank_plus.oxygen WHERE oxygen.id = lifebank_plus.oxygen_order.product)as size FROM lifebank_plus.oxygen_order WHERE order_by = ? AND FROM_UNIXTIME(tym) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) ORDER BY tym DESC", [$refid]);
		$countCancelled = R::getCell("SELECT COUNT(*) FROM lifebank_plus.`oxy_cancel` WHERE order_by = ? AND FROM_UNIXTIME(tym) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)", [$refid]);


		//count orders with status as completed
		$completed = 0;
		$pending = 0;
		$proccessing = 0;

		foreach ($orders as $order) {

			$status = strtolower($order['order_state']);

			if ($status == "completed") {
				$completed++;
			}

			if ($status  == "awaiting pick up") {
				$pending++;
			}

			if ($status  != "awaiting pick up" && $status  != "completed") {
				$proccessing++;
			}
		}

		$data = ["pending" => $pending, "processing" => $proccessing, "cancelled" => $countCancelled, "completed" => $completed, "orders" => $orders];

		$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints',  'data' => $data);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} catch (Exception $e) {

		$response->getBody()->write(json_encode([
			'status' => 'error',
			'message' => 'Token invalid or expired'
		]));
		return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
	}
})->add($authMiddleware);

$app->get('/product/size', function (Request $request, Response $response, array $args) {

	try {

		$user = $request->getAttribute('user');
		$refid = $user['ref_id'];

		$product_list = R::getAll("SELECT id,size FROM `lifebank_plus`.`oxygen`");

		$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints',  'data' => $product_list);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} catch (Exception $e) {

		$response->getBody()->write(json_encode([
			'status' => 'error',
			'message' => 'Token invalid or expired'
		]));
		return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
	}
})->add($authMiddleware);

$app->post('/pricing', function (Request $request, Response $response, array $args) {

	try {

		$parsedBody = $request->getParsedBody();

		$user = $request->getAttribute('user');
		$refid = $user['ref_id'];

		$productype = $parsedBody['productid'] ?? null;

		$map = ["Large Cylinder" => '8 cubic meter', "Medium Cylinder" => '6 cubic meter', "Small Cylinder" => '2 cubic meter',];
		$productype = $map[$productype] ?? $productype;

		$product_price =  pricing($refid, $productype);

		$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints',  'data' =>  $product_price);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} catch (Exception $e) {

		$response->getBody()->write(json_encode([
			'status' => 'error',
			'message' => 'Token invalid or expired'
		]));
		return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
	}
})->add($authMiddleware);


$app->post('/placeorder', function (Request $request, Response $response) {
    try {
        $parsedBody = $request->getParsedBody() ?? [];

        // ✅ Get user info from JWT
        $user = $request->getAttribute('user');
        if (empty($user) || empty($user['ref_id'])) {
            throw new Exception("User authentication failed or missing reference ID");
        }
        $refid = $user['ref_id'];

        // ✅ Extract request data
        $productType   = trim($parsedBody['productid'] ?? '');
        $qty           = (int)($parsedBody['qty'] ?? 0);
        $payment       = trim($parsedBody['payment'] ?? '');
        $usage         = trim($parsedBody['usage'] ?? '');
        $urgence         = trim($parsedBody['requestType'] ?? '');
        $orderType     = trim($parsedBody['orderType'] ?? '');
        $scheduleDate  = trim($parsedBody['schedule_date'] ?? '0000-00-00');
        $scheduleTime  = trim($parsedBody['schedule_time'] ?? '');
        $discount      = (float)($parsedBody['discount'] ?? 0);
        $requestType   = trim($parsedBody['requestType'] ?? '');
        $requester     = trim($parsedBody['requester'] ?? '');

        // ✅ Static/default fields
        $channel       = "Nerve";
        $channelType   = "AirX";
        $status        = "Awaiting Pick Up";
        $createdAt     = time();

        // ✅ REQUIRED FIELDS CHECK
        $requiredFields = [
            'productid'      => $productType,
            'qty'            => $qty,
            'payment'        => $payment,
            'orderType'      => $orderType,
            'requester'      => $requester
        ];

        $missing = [];
        foreach ($requiredFields as $key => $value) {
            if ($value === '' || $value === null || $value === 0) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            $response->getBody()->write(json_encode([
                'status'  => 'error',
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ], JSON_PRETTY_PRINT));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // ✅ Normalize product names
        $map = [
            "Large Cylinder"  => '8 Cubic Meter',
            "Medium Cylinder" => '6 Cubic Meter',
            "Small Cylinder"  => '2 Cubic Meter'
        ];
        $productType = $map[$productType] ?? $productType;

        $findProductSize = R::getCell("SELECT `size` FROM `lifebank_plus`.`oxygen` WHERE `id` = ?", [$productType]);
        if (!$findProductSize) {
            throw new Exception("Invalid product ID or product not found: $productType");
        }

        
        $productPrice = pricing($refid, $findProductSize);

        // ✅ Normalize schedule date/time
        $scheduleDate = formatDate($scheduleDate);
        $scheduleTime = formatTime($scheduleTime);

        // ✅ Create and save order
		$sql = "INSERT INTO lifebank_plus.oxygen_order  (`order_by`, payment, qty, product, discount, tym, urgency, order_type, schedule_date, schedule_time, order_state, personnel_name, usage_info, channel, order_source, `unitprice`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$js =  R::exec($sql, [$refid, $payment,(int)$qty, $productType, (float)$discount, $createdAt,$urgence, $orderType, $scheduleDate, $scheduleTime, $status, $requester, $usage, $channel, $channelType, (float)$productPrice]);
		$id = R::getInsertID();

        if ($id) {
			notifyLite();
            $payload = [
                'status'   => 'success',
                'message'  => 'Order placed successfully',
                'order_id' => $id,
                'data'     => [
                    'product'  => $productType,
                    'qty'      => $qty,
                    'price'    => $productPrice,
                    'schedule' => trim(($scheduleDate ?? '') . ' ' . ($scheduleTime ?? ''))
                ]
            ];
            $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }
		print_r( $js);

        throw new Exception('Order could not be saved' . $js);

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    } finally {
        R::close();
    }
})->add($authMiddleware);

$app->post('/support', function (Request $request, Response $response) {
    try {
        $parsedBody = $request->getParsedBody() ?? [];

        // ✅ Get user info from JWT
        $user = $request->getAttribute('user');
        if (empty($user) || empty($user['ref_id'])) {
            throw new Exception("User authentication failed or missing reference ID");
        }
        $refid = $user['ref_id'];

		// ✅ Get required fields
		$subject = trim($parsedBody['subject'] ?? '');
		$category = trim($parsedBody['category'] ?? '');
		$message = trim($parsedBody['message'] ?? '');
		
		//check if empty
		if (empty($subject) || empty($category) || empty($message)) {
			$response->getBody()->write(json_encode([
				'status'  => 'error',
				'message' => 'All fields are required'
			]));
			return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
		}
		//Dispense to database 

		$support = R::dispense('support');

		$support->subject = $subject;
		$support->category = $category;
		$support->message = $message;
		$support->ref_id = $refid;
		$support->created_at = date('Y-m-d H:i:s');
		$support->updated_at = date('Y-m-d H:i:s');	
		$id = R::store($support);

		if ($id) {
			$response->getBody()->write(json_encode([
				'status'  => 'success',
				'message' => 'Support request submitted successfully'
			]));
			return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
		}else{
			$response->getBody()->write(json_encode([
				'status'  => 'error',
				'message' => 'Support request could not be submitted'
			]));
			return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
		}
       

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    } finally {
        R::close();
    }
})->add($authMiddleware);

$app->get('/all', function (Request $request, Response $response, array $args) use ($authToken) {


	$authorization_header = $request->getHeader("Authorization");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'hospital informations endpoints', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	$hospitals = R::findAll('hospital');

	if ($hospitals == null) {
		$return =  array('status' => 'false', 'Description' => 'hospital informations endpoints', 'Message' => 'no hospital in the state', 'data' => null);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} else {
		$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints', 'Message' => 'all the hosiptal in the state', 'data' => $hospitals);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}
});

$app->get('/all/{state}', function (Request $request, Response $response, array $args) use ($authToken) {

	$state = $request->getAttribute('state');

	$authorization_header = $request->getHeader("Authorization");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'hospital informations endpoints', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	$hospitals = R::find('hospital', ' state  LIKE ? ', [$state]);

	if ($hospitals == null) {
		$return =  array('status' => 'false', 'Description' => 'hospital informations endpoints', 'Message' => 'no hospital in the state', 'data' => null);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} else {
		$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints', 'Message' => 'all the hosiptal in the state', 'data' => $hospitals);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}
});

$app->get('/single/{id}', function (Request $request, Response $response, array $args) use ($authToken) {

	$id = $request->getAttribute('id');

	$authorization_header = $request->getHeader("Authorization");

	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'hospital informations endpoints', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	$hospitals = R::find('hospital', ' id = ? ', [$id]);

	if ($hospitals == null) {
		$return =  array('status' => 'false', 'Description' => 'hospital informations endpoints', 'Message' => 'no hospital with that id', 'data' => null);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	} else {
		$return =  array('status' => 'success', 'Description' => 'hospital informations endpoints', 'Message' => 'the hosiptal with the id', 'data' => $hospitals);

		return $response->withStatus(200)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}
});

$app->post('/add', function (Request $request, Response $response) use ($authToken) {

	$authorization_header = $request->getHeader("Authorization");

	$name = $request->getParam('name');
	$addressLine1 = $request->getParam('addressLine1');
	$addressLine2 = $request->getParam('addressLine2');
	$city = $request->getParam('city');
	$bedCap = $request->getParam('bedSize');
	$departs = $request->getParam('departments');
	$oxygenSource = $request->getParam('oxygenSource');
	$powerBackup = $request->getParam('powerBackup');
	$technicals = $request->getParam('technicals');
	$contactPerson = $request->getParam('contactPerson');
	$contactRole = $request->getParam('contactRole');
	$contactPhone = $request->getParam('contactPhone');
	$contactEmail = $request->getParam('contactEmail');
	$state = $request->getParam('state');


	if (empty($authorization_header) || ($authorization_header[0] != $authToken)) {

		$return =  array('status' => 'false', 'Description' => 'This is a set of credentials used to authenticate a user', 'Message' => 'Header is missing', 'data' => 'method allowed post');

		return $response->withStatus(401)
			->withHeader('Content-Type', 'application/json')
			->write(json_encode($return));
	}

	try {
		$sql = "INSERT INTO `hospital` (`name`, `addressLine1`, `addressLine2`, `city`, `bedCap`, `departs`, `oxygenSource`, `powerBackup`, `technicals`, `contactPerson`, `contactRole`, `contactPhone`, `contactEmail`, `state`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

		$add = R::exec($sql, [$name, $addressLine1, $addressLine2, $city, $bedCap, $departs, $oxygenSource, $powerBackup, $technicals, $contactPerson, $contactRole, $contactPhone, $contactEmail, $state]);
		$id = R::getInsertID();

		$return = array('status' => 'success', 'Description' => 'hospital informations endpoints', 'Message' => 'hospital was created', 'data' => $id);

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


function pricing($refid, $productype)
{
	$hospital_info = R::getRow("SELECT `secure_login`.`oxygen_premium`, user_info.state FROM `lifebank_plus`.`secure_login` LEFT JOIN `lifebank_plus`.user_info on user_info.ref_id = secure_login.memberid WHERE memberid ='$refid'");
	$city = $hospital_info['state'];
	$class = $hospital_info['oxygen_premium'];

	$product_price =  R::getCell("SELECT cost FROM `lifebank_plus`.`pricing` WHERE `product` like 'oxygen' AND `product_type` like '$productype' AND `city` like '$city' AND `class` = '$class'");

	if ($product_price == null) {
		$product_price = "N/A";
	}

	return $product_price;
}

function formatDate($date)
{
    if (empty($date)) return null;
    try {
        $d = new DateTime($date);
        return $d->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}


function formatTime($time)
{
    if (empty($time)) return null;
    try {
        $t = new DateTime($time);
        return $t->format('H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function notifyLite(){
		$time = date('Y-m-d H:i:s');
		$pdo = "INSERT INTO lifebank_plus.`ordernotify`( `ordertype`, `tym`, `channel`)  VALUES ('Oxygen','$time','AirX')";
		$add = R::exec($pdo);
}