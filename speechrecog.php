#!/usr/bin/php -q
<?php
/**
 * Author: Alvaro Mazuera Urrego
 * 
 */
require_once('phpagi.php');

/**
 * The API Key Google Cloud Platform
 */
$api_key = "";

/**
 * The enconding of the recorded file to recognize
 * Consult Google Cloud Platform Speecht To Text API Documentation
 * Default for asterisk recorded files: LINEAR16
 */
$encodig = "LINEAR16";

/**
 * The sample rate of the recorded file to recognize
 * Default for asterisk recorded files: 8kHz
 */
$sample_rate = 8000;

/**
 * The language code supported by Google Cloud Platform Speech to Text API
 */
$lang_code = 'en-US';

/**
 * Absolute path for the asterisk sounds
 */
$ast_sounds = "/var/lib/asterisk/sounds";
/**
 * Path with file name without extension for the recorded file to recognize
 */
$rel_path = "tmp_file_speech";

/**
 * The file extension allowed in asterisk for the recorded file, wav and gsm ar commonly used.
 */
$file_ext = "wav";

/**
 * Debug script
 */
$debug = false;

$agi = new AGI();
$agi->answer();

// Get recognized phrase whit a 0.5 confidence threshold and a custom prompt
$data = recognize(['ct' => 0.5],"custom/name");
$agi->verbose("RECOGNIZED DATA: $data");

/**
 * $rules:
 *  ct  => Confidence Threshold  Default: 0.7
 *  num => Numeric Value
 *  str => String Value
 *  min => Minimal Value
 *  max => Maximun Value
 *  bool=> Boolean Value
 *  gt  => Greater Than
 *  gtoe=> Greather Than or Equal
 *  in  => Between Enum Vals
 *  null=> Nullable
 * 
 * $prompt => File name without extension, located in /var/lib/asterisk/sounds, with relative path.
 * $num_rep=> Number of repetitions if the phrase recognized is invalid according to the $rules
 * $timeout=> Max time record file to recognize in seconds.
 * 
 * return $phrase
 */
function recognize($rules = [],$prompt = false, $num_rep = 2, $timeout = 15){
    if($num_rep == 0) return false;
    global $agi;
    if($prompt) $agi->stream_file($prompt);
    list($phrase,$confidence) = recognizeRequest($timeout);
    if(!is_array($rules)) return $data;
    $valid = true;
    if(isset($rules['ct']) and is_numeric($rules['ct']))
        if($confidence < $rules['ct']) $valid = false;
    if(in_array('num', $rules))
        if(!is_numeric($phrase))    $valid = false;
    if(in_array('str', $rules))
        if(!is_string($phrase))     $valid = false;
    if(isset($rules['min']) and is_numeric($rules['min']))
        if($phrase < $rules['min']) $valid = false;
    if(isset($rules['max']) and is_numeric($rules['max']))
        if($phrase > $rules['max']) $valid = false;
    if(in_array('bool', $rules)){
        $booleanWords = ["si", "correcto", "afirmativo", "asi es", "así es", "no", "incorrecto", "negativo", "por supuesto"];
        if(!in_array(strtolower($phrase), $booleanWords)) $valid = false;
    }

    if(!$valid) return recognize($rules, $prompt, $num_rep-1, $timeout);
    else return $phrase;
}

/**
 * $timeout=> Max time record file to recognize in seconds.
 * 
 * return array():
 *  [$phrase (String recognized phrase), $confidence (Float confidence value)]
 */
function recognizeRequest($timeout = 15){
    global $agi, $api_key, $encodig, $sample_rate, $lang_code, $rel_path, $file_ext, $ast_sounds, $debug;

    //Record the speech file
    $agi->record_file($rel_path,$file_ext,"#", $timeout*1000, NULL, FALSE, 2);
    //Get the audio content
    $file = file_get_contents("$ast_sounds/$rel_path.$file_ext");
    //Prepare the json data to send
    $data = [
        "config" => [
            "encoding"              => $encodig,
            "sampleRateHertz"       => $sample_rate,
            "languageCode"          => $lang_code,
            "enableWordTimeOffsets" => false
        ],
        "audio" => [
            "content" => base64_encode($file)
        ]
    ];
    //Init the curl request
    $ch = curl_init("https://speech.googleapis.com/v1/speech:recognize?key=$api_key");
    curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        CURLOPT_POSTFIELDS => json_encode($data)
    ));
    // Send the request
    $response = curl_exec($ch);
    //Decode the json response
    $responseData = json_decode($response, TRUE);
    
    $phrase = "";
    $confidence = 0;
    foreach ($responseData['results'] as $result)
        foreach($result["alternatives"] as $altern){
            $phrase .= $altern['transcript'];
            $confidence = $altern['confidence'];
        }

    if($debug)
        $agi->verbose("PHRASE FOUND: $phrase CONFIDENCE: $confidence");
    return [$phrase, $confidence];
}
?>
