<?php
/***************************************/
/* Written by Aimee Blair              */
/* Uses FFXIV API. https://xivapi.com/ */
/***************************************/

include_once("REST.php");
$CURL = REST::create('ff14');

// Find Lodestone ID by Character Name and Server Name //
// Come say hi to me on FFXIV if you use this code! //
$CHARACTER_NAME = "Yukiko Kouri";
$SERVER_NAME = "Faerie";

// Character name spaces need to be replaced with plus signs //
$CHARACTER_NAME_REFORMAT = str_replace(" ", "+", $CHARACTER_NAME);

$URL = "/character/search";
$PARAMS = "?name=" . $CHARACTER_NAME_REFORMAT . "&server=" . $SERVER_NAME;


$RESPONSE = $CURL->sendGET($URL, $PARAMS);
// Put JSON into an array so it is easier to read //
$RESPONSE = json_decode($RESPONSE, 1);

// Save the Lodestone ID for the character //
$LODESTONE_ID = $RESPONSE['Results'][0]['ID'];

// Pull character information by Lodestone ID //
$URL = "/character/" . $LODESTONE_ID;
// You can use an API key with the call, add it to the parameters with: ?private_key= or put in payload //
$PARAMS = "";

$RESPONSE = $CURL->sendGET($URL, $PARAMS);
$RESPONSE = json_decode($RESPONSE, 1);

$CURRENT_CLASS = $RESPONSE['Character']['ActiveClassJob']['UnlockedState']['Name'];
$CURRENT_CLASS_LEVEL = $RESPONSE['Character']['ActiveClassJob']['Level'];

print $CHARACTER_NAME . "<br>";
print "Current Class: " . $CURRENT_CLASS . "<br>";
print "Current Class Level: " . $CURRENT_CLASS_LEVEL;

?>