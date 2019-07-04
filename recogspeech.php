#!/usr/bin/php -q
<?php
require_once('phpagi.php');
$agi = new AGI();

$agi->answer();

// $agi->exec('festival',"prueba\ de\ audio\ con\ festival");
// $agi->text2wav("Por favor, diga su nombre.");
// exit;


$nombre = recognize(['ct' => 0.5, 'bool'],"custom/nombre");
// $edad   = recognize(['num', 'ct' => 0.6],"custom/edad");
// $sexo   = recognize(['ct' => .3],"custom/sexo", 10);
// $agi->stream_file("custom/edad");
// $agi->stream_file("custom/sexo");
echo "Nombre: $nombre, Edad: $edad, Sexo: $sexo";

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
 */
function recognize($rules = [],$prompt = false, $num_rep = 2, $timeout = 15){
    if($num_rep == 0) return false;
    global $agi;
    if($prompt) $agi->stream_file($prompt);
    list($phrase,$confidence) = recognizeRequest($timeout);
    // $phrase = "";
    // $confidence = 0;
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

function recognizeRequest($timeout = 15){
    global $agi;
    $api_key = "";
    $agi->record_file("tmp/temp_file","wav","#", $timeout*1000, NULL, FALSE, 2);
    $file = file_get_contents('/var/lib/asterisk/sounds/tmp/temp_file.wav');
    $data = [
        "config" => [
            "encoding"=>"LINEAR16",
            "sampleRateHertz"=> 8000,
            "languageCode"=> "es-CO",
            "enableWordTimeOffsets"=> false
        ],
        "audio" => [
            "content" => base64_encode($file)
        ]
    ];

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
    $responseData = json_decode($response, TRUE);
    $phrase = "";
    $confidence = 0;
    foreach ($responseData['results'] as $result)
        foreach($result["alternatives"] as $altern){
            $phrase .= $altern['transcript'];
            $confidence = $altern['confidence'];
        }
            //floatval($altern['confidence'])*100;//number_format( floatval($altern['confidence'])*100,2,',');
    $agi->verbose("FRASE ENCONTRADA: $phrase EXACTITUD: $confidence");
    return [$phrase, $confidence];
}
?>