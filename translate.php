#!/usr/bin/php

<?php
if($argc < 5) {
  echo "IL String Translator - uses Google Translate to translate strings in an IL disassembly file.\n";
  echo "Written by Tucker Osman\n";
  echo "\n";
  echo $argv[0] . " <input file> <output file> <source language> <api key>\n";
  echo "see https://cloud.google.com/translate/docs/languages for valid values for 'source language'\n";
  echo "go to https://console.cloud.google.com for a valid API key. see the README for more information.\n\n";
  exit(1);
}

if(PHP_VERSION_ID < 70400) {
    echo "IL String Translator requires PHP 7.4+\n";
    exit(2);
}

$content = file_get_contents($argv[1]);
$re = '/ldstr\s*(bytearray \((?<bytes>([^\)]|\n)*) \)|"(?<text>.*)")/m';
$count = 0;

global $translated_characters;
$translated_characters = 0;

function google_translate($input) {
  global $translated_characters;
  global $argv;

  // do not make a request on an empty string, if there are no language characters, or if there is a backslash (meaning this could be a path)
  if (empty(trim($input)) || !preg_match("/\p{L}/", $input) || str_contains($input, "\\")) return $input;

  $url = "https://translation.googleapis.com/language/translate/v2?target=en&key=". $argv[4] . "&q=".urlencode($input)."&source=".$argv[3];

  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = json_decode(curl_exec($curl));
  curl_close($curl);

  $translated_characters += strlen($input);

  echo "translated " . $translated_characters . " characters with Google        \r";

  return htmlspecialchars_decode($response->data->translations[0]->translatedText, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
}

function translate($match) {
  $bytes_re = '/([0-9A-F])([0-9A-F])/m';

  if (isset($match["text"])) {
    /*
      these are just ASCII strings, so we can just translate them and put them back.
    */

    $string = $match["text"];
    $translated = google_translate($string);
    return "ldstr      \"". $translated ."\"";
  } else if (isset($match["bytes"])) {
    /*
      the IL compiler encodes strings with non-ASCII characters as UTF-16LE byte arrays.
      this converts it to UTF-8, translates it, and then writes it back as an ASCII string.
      see https://stackoverflow.com/a/9113641 for more details on this format.
    */

    $bytes = array();
    preg_match_all($bytes_re, $match["bytes"], $bytes);
    $string = iconv("UTF-16LE", "UTF-8", pack("H*", join($bytes[0])));

    if(empty(trim($string))) {
        return $match[0];
    }

    // translate $string, convert to UTF-16LE, unpack as hex, write the ASCII string back
    $translated = google_translate($string);

    return "ldstr      \"". $translated ."\"";
  }
}

$out = preg_replace_callback($re, "translate", $content, -1, $count, PREG_UNMATCHED_AS_NULL);

file_put_contents($argv[2], $out);

echo "\ndone! - processed ". $count ." strings\n\n";
exit(0);
?>
