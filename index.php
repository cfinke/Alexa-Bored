<?php

require "./config.php";

// https://github.com/korra88/amazon-alexa-php
require "../lib/amazon-alexa-php/src/Request/Request.php";
require "../lib/amazon-alexa-php/src/Request/Application.php";
require "../lib/amazon-alexa-php/src/Request/Certificate.php";
require "../lib/amazon-alexa-php/src/Request/IntentRequest.php";
require "../lib/amazon-alexa-php/src/Request/LaunchRequest.php";
require "../lib/amazon-alexa-php/src/Request/Session.php";
require "../lib/amazon-alexa-php/src/Request/SessionEndedRequest.php";
require "../lib/amazon-alexa-php/src/Request/User.php";

require "../lib/amazon-alexa-php/src/Response/Response.php";
require "../lib/amazon-alexa-php/src/Response/OutputSpeech.php";
require "../lib/amazon-alexa-php/src/Response/Card.php";
require "../lib/amazon-alexa-php/src/Response/Reprompt.php";

ob_start();

$raw_request = file_get_contents("php://input");

try {
	$alexa = new \Alexa\Request\Request( $raw_request, APPLICATION_ID );
	
	$alexaRequest = $alexa->fromData();

	$things_to_do = array(
		"play hide-and-seek",
		"clean the bathroom",
		"run around the house",
		"see how fast you can say the alphabet",
		"write a letter to your best friend",
		"make a stop-motion movie",
		"play a game of tag",
		"climb a tree",
		"make a friendship bracelet",
		"read a book",
		"play catch",
		"build a blanket fort",
		"play frisbee",
		"listen to some music",
		"play simon says",
		"do a puzzle",
		"plan a scavenger hunt",
		"create a time capsule",
		"make up a secret handshake",
		"draw with sidewalk chalk",
		"paint your nails",
		"make up your own mad libs",
		"play cards",
		"jump rope",
		"write in your diary",
		"write a song",
		"check the couch for loose change",
		"write a poem",
		"play piano",
		"write a letter to your self " . rand( 2, 10 ) . " years in the future",
		"start a neighborhood newspaper",
		"make a rock collection",
		"write a fan letter to a famous person",
		"pretend to be a snake",
		"plan out your perfect day",
		"write to your congressional representative",
		"recreate a photo of yourself from the past",
		"roll down a hill",
		"fly a kite",
		"bury some treasure",
		"try and catch an animal",
		"take a photo of someone doing something ordinary",
		"check if the mail has arrived",
		"see how still you can lay for 10 minutes",
		"find a bug",
		"lick your elbow",
		"clean up your room for five minutes",
		"draw a picture of your favorite person",
		"do a summersault",
		"put socks on your hands",
		"hug somebody",
		"play hopscotch",
		"draw a self portrait",
		"count the wrinkles in your elbow",
		"find a toy to donate to charity",
		"stand on your head",
		"wear five shirts at one time",
		"do ten math problems",
	);

	$animals = array_map( 'trim', file( "data/animals.txt" ) );
	shuffle( $animals );
	$animals = array_slice( $animals, 0, 5 );

	foreach ( $animals as $animal ) {
		if ( preg_match( '/^[aeiou]/i', $animal ) ) {
			$things_to_do[] = "draw a picture of an " . $animal;
		}
		else {
			$things_to_do[] = "draw a picture of a " . $animal;
		}
	}

	$intros = array(
		"Why don't you",
		"You could",
		"Why not",
		"Here's an idea:",
	);

	$outros = array(
		"Wouldn't that be fun?",
		"You haven't done that in a while.",
		"I wish I could do that too, but I'm way up here in the cloud.",
		"Let me know how it goes.",
	);

	$thing_to_do = $things_to_do[ array_rand( $things_to_do ) ];

	$response = new \Alexa\Response\Response;
	$response->respond( $intros[ array_rand( $intros ) ] . " " . $thing_to_do . ". " . $outros[ array_rand( $outros ) ] );
	$response->withCard( "Here's something to do: " . $thing_to_do . "." );
	$response->endSession( true );

	echo json_encode( $response->render() );

} catch ( Exception $e ) {
	header( "HTTP/1.1 400 Bad Request" );
	exit;
}

$output = ob_get_clean();
ob_end_flush();

header( 'Content-Type: application/json' );
echo $output;

exit;