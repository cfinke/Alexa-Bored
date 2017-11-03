<?php

require "./config.php";

require "./lib/amazon-alexa-php/src/Request/Request.php";
require "./lib/amazon-alexa-php/src/Request/Application.php";
require "./lib/amazon-alexa-php/src/Request/Certificate.php";
require "./lib/amazon-alexa-php/src/Request/IntentRequest.php";
require "./lib/amazon-alexa-php/src/Request/LaunchRequest.php";
require "./lib/amazon-alexa-php/src/Request/Session.php";
require "./lib/amazon-alexa-php/src/Request/SessionEndedRequest.php";
require "./lib/amazon-alexa-php/src/Request/User.php";

require "./lib/amazon-alexa-php/src/Response/Response.php";
require "./lib/amazon-alexa-php/src/Response/OutputSpeech.php";
require "./lib/amazon-alexa-php/src/Response/Card.php";
require "./lib/amazon-alexa-php/src/Response/Reprompt.php";

ob_start();

$raw_request = file_get_contents("php://input");

try {
	$alexa = new \Alexa\Request\Request( $raw_request, APPLICATION_ID );

	// Generate the right type of Request object
	$request = $alexa->fromData();

	$response = new \Alexa\Response\Response;
	
	// By default, always end the session unless there's a reason not to.
	$response->shouldEndSession = true;

	if ( 'LaunchRequest' === $request->data['request']['type'] ) {
		// Just opening the skill ("Open Activity Book") responds with an activity.
		handleIntent( $request, $response, 'Bored' );
	}
	else {
		handleIntent( $request, $response, $request->intentName );
	}

	// A quirk of the library -- you need to call respond() to set up the final internal data for the response, but this has no output.
	$response->respond();

	echo json_encode( $response->render() );
} catch ( Exception $e ) {
	header( "HTTP/1.1 400 Bad Request" );
	exit;
}

/** 
 * Given an intent, handle all processing and response generation.
 * This is split up because one intent can lead into another; for example,
 * moderating a comment immediately launches the next step of the NewComments
 * intent.
 *
 * @param object $request The Request.
 * @param object $response The Response.
 * @param string $intent The intent to handle, regardless of $request->intentName
 */
function handleIntent( &$request, &$response, $intent ) {
	$user_id = $request->data['session']['user']['userId'];
	$state = get_state( $user_id );

	if ( ! $request->sesssion->new ) {
		switch ( $intent ) {
			case 'AMAZON.StopIntent':
			case 'AMAZON.CancelIntent':
				return;
			break;
		}
	}

	switch ( $intent ) {
		case 'Bored':
			$response = something_to_do_response( $response );
			
			$state->last_response = $response;
			save_state( $user_id, $state );
		break;
		case 'AMAZON.HelpIntent':
			$thing_to_do = something_to_do();
			$response->addOutput( "Activity Book provides you with thousands of things to do when you're bored. Here's one now:" );
			$response->addOutput( $thing_to_do . "." );
			
			$response->addCardTitle( "Using Activity Book" );
			$response->addCardOutput( "Activity Book can give you something to do when you're bored. Just say \"Open Activity Book\" or \"Ask Activity Book for something to do.\"" );
			$response->addCardOutput( "Here's an idea to get you started: " . $thing_to_do . "." );
		break;
		case 'AMAZON.RepeatIntent':
			if ( ! $state || ! $state->last_response ) {
				$response->addOutput( "I'm sorry, I don't know what to repeat." );
			}
			else {
				save_state( $user_id, $state );
				$response->output = $state->last_response->output;
			}
		break;
	}
}

$output = ob_get_clean();

ob_end_flush();

header( 'Content-Type: application/json' );
echo $output;

exit;

function something_to_do_response( $response ) {
	$intros = array(
		"You could",
		"Here's an idea:",
	);

	$outros = array(
		"Wouldn't that be fun?",
		"I wish I could do that, but I'm way up here in the cloud.",
		"",
	);

	$thing_to_do = something_to_do();
	
	$response->addOutput( $intros[ array_rand( $intros ) ] . " " . $thing_to_do . ". " . $outros[ array_rand( $outros ) ] );
	$response->withCard( "Here's something to do: " . $thing_to_do . "." );
	
	return $response;
}

function something_to_do() {
	$things_to_do = array_filter( array_map( 'trim', file( "data/things_to_do.txt" ) ) );

	$animals = array_map( 'trim', file( "data/animals.txt" ) );
	shuffle( $animals );
	$animals = array_slice( $animals, 0, 5 );

	foreach ( $animals as $animal ) {
		if ( preg_match( '/^[aeiou]/i', $animal ) ) {
			$things_to_do[] = "draw a picture of an " . $animal;
			$things_to_do[] = "write a story about an an " . $animal;
			$things_to_do[] = "pretend to be an " . $animal . " and see if anyone can guess what you are";
		}
		else {
			$things_to_do[] = "draw a picture of a " . $animal;
			$things_to_do[] = "write a story about a " . $animal;
			$things_to_do[] = "pretend to be a " . $animal . " and see if anyone can guess what you are";
		}
	}

	return $things_to_do[ array_rand( $things_to_do ) ];
}

function state_file( $user_id ) {
	$state_dir = dirname( __FILE__ ) . "/state";
	
	$state_file = $state_dir . "/" . $user_id;
	
	touch( $state_file );
	
	if ( realpath( $state_file ) != $state_file ) {
		// Possible path traversal.
		return false;
	}
	
	return $state_file;
}

/**
 * Save the state of the session so that intents that rely on the previous response can function.
 *
 * @param string $session_id
 * @param mixed $state
 */
function save_state( $user_id, $state ) {
	$state_file = state_file( $user_id );

	if ( ! $state_file ) {
		return false;
	}
	
	if ( ! $state ) {
		if ( file_exists( $state_file ) ) {
			unlink( $state_file );
		}
	}
	else {
		file_put_contents( $state_file, json_encode( $state ) );
	}
}

/**
 * Get the current state of the session.
 *
 * @param string $session_id
 * @return object
 */
function get_state( $user_id ) {
	$state_file = state_file( $user_id );

	if ( ! $state_file ) {
		return new stdClass();
	}
	
	if ( ! file_exists( $state_file ) ) {
		return new stdClass();
	}

	return (object) json_decode( file_get_contents( $state_file ) );
}