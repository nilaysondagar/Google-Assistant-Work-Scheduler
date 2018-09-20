<?php
	/* Initialization Stuff */
	use Google\Spreadsheet\DefaultServiceRequest;
	use Google\Spreadsheet\ServiceRequestFactory;

	require __DIR__ . '/vendor/autoload.php';
	putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/client_secret.json');
	$client = new Google_Client;
	$client->useApplicationDefaultCredentials();
	 
	$client->setApplicationName("ESS Receipt Upload");
	$client->setScopes(['https://www.googleapis.com/auth/drive','https://spreadsheets.google.com/feeds']);
	 
	if ($client->isAccessTokenExpired()) {
	    $client->refreshTokenWithAssertion();
	}
	 
	$accessToken = $client->fetchAccessTokenWithAssertion()["access_token"];
	ServiceRequestFactory::setInstance(
	    new DefaultServiceRequest($accessToken)
	); 

	// Get our spreadsheet
	$spreadsheet = (new Google\Spreadsheet\SpreadsheetService)
   ->getSpreadsheetFeed()
   ->getByTitle('Work Hours');
 
	// Get the first worksheet (tab)
	$worksheets = $spreadsheet->getWorksheetFeed()->getEntries();
	$worksheet = $worksheets[0];
	$listFeed = $worksheet->getListFeed();

	/*My Code That I Actually Kinda Understand*/

	$method = $_SERVER['REQUEST_METHOD']; // server request type

	// process only when method is POST
	if($method == "POST") {
		$requestBody = file_get_contents('php://input'); //idk what this does but I need it for something
		$json = json_decode($requestBody);

		// get variables from POST request from Dialogflow
		$date = $json->result->parameters->date;
		$starttime = $json->result->parameters->starttime;
		$endtime = $json->result->parameters->endtime;

		if($date == "today") {
			$date = date("Y-m-d", strtotime($date));
		}// if

		// calculate time I worked for and how much I made
		$workedHours = (strtotime($endtime) - strtotime($starttime)) / 3600;

		// if Google messes up the timings, fix them!
		if($workedHours < 0) {
			$workedHours += 12;
		} // if

		$dailyTotal = $workedHours * 13;

		// set speech to what Google Assistant will say
		$speech = "Ok, I added that to your spreadsheet. You worked " . $workedHours . " hours and made " . $dailyTotal . " dollars.";

		$listFeed->insert([
			'date' => $date,
			'starttime' => $starttime,
			'endtime' => $endtime,
			'timeworked' => $workedHours,
			'dailytotal' => $dailyTotal
		]);

		// create and send off correct output in JSON format
		$response = new \stdClass();
		$response->speech = $speech;
		$response->displayText = $speech;
		$response->source = "webhook";
		echo json_encode($response);

	} else {
		echo "Method not found";
	}// else if	

?>