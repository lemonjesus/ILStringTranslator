# IL String Translator

Translates strings in a disassembled .NET executable into English.

## To Run (tl;dr)

```
php ./translate.php <input.il> <output.il> <language> <api key>
```

IL String Translator requires PHP 7.4 or newer. If you don't have it or don't want to install it, you can run it with Docker:

```
docker run --rm -w="/app" -v $(pwd):/app php:8.1 php ./translate.php <input.il> <output.il> <language> <api key>
```

where:

* `input.il` is the disassembled .NET executable (from `ildasm.exe`)
* `output.il` is where the translated output will go
* `language` is a [language code Google Translate supports](https://cloud.google.com/translate/docs/languages)
* `api key` is a valid [Google Cloud API Key](https://console.cloud.google.com/apis/credentials) with [Google Translate permissions](https://console.cloud.google.com/apis/api/translate.googleapis.com/overview).

## Detailed Steps For Use

Let's say you have a .NET application that's in another language but you don't have the source code for it. Luckily, .NET applications can be disassembled into an intermediate language with plain text strings that can be replaced and reassembled. Here are the steps I use to disassemble and reassemble applications with new, translated strings:

1. Open Visual Studio 2019, continue without code, and open a [Developer Command Prompt](https://docs.microsoft.com/en-us/visualstudio/ide/reference/command-prompt-powershell?view=vs-2019). If you know where `ilasm.exe` and `ildasm.exe` are, then this step is optional. I just find it's easier this way since everything is on the path in this environment.
1. Run `ildasm.exe`, File -> Open the executable, and then File -> Dump. The default options are okay. Give it a name. For this example, I'll have named it `dump.il`. `ildasm.exe` will make several files. All are required for reassembly, but the one we'll be working with is `dump.il`.
1. Get a Google API Key for Google Translate and note the language code you're translating from. I'm translating from Polish, so my code is `pl`.
1. Run `php ./translate.php dump.il out.il pl <api key>` (or the Docker variant) with your API Key. The Translator will tell you how many characters it's sending to Google as it sends them. Once it's done, it'll tell you how many strings it processed. The translated output will be in `out.il`.
1. Reassemble the program using `ilasm.exe /EXE /RESOURCE=dump.res .\out.il`. The resulting executable will be named the same thing as the IL file, so in this case `out.exe` will be generated.

## Caveats and Troubleshooting

* **If `ilasm.exe` fails to reassemble the output IL file:** it will give you a line number it fails at. The translator probably did something stupid and may require a fix by hand. If you think it's not a crazy edge case, you can file an issue.
* **If the program errors out or silently fails to start:** the translator probably replaced something it shouldn't have. You can debug this by changing the `-1` in the call to `preg_replace_callback` to a positive number and limit the number of strings it converts. It may be useful to bisect the IL listing for the specific offending string that way (if no error information is given). Sometimes just renaming the executable fixes it and I can't explain how that could be.
* **IL String Translator purposefully ignores some strings** like empty strings (only `0x00` bytes or whitespace), strings that don't have language characters in them (as determined by the regex `/\p{L}/`), and strings with backslashes in them (since they could be paths and break things if changed).
* **It uses a Regular Expression to find strings** so obviously it could be very brittle. But it was the quickest thing that worked. PRs welcome, but I think I only ever needed to cover two `ldstr` variants.
* **It only translates to English and assumes everything returned by Google is ASCII.** `ilasm.exe` will fail to reassemble the program if there is a non-ASCII character in a translated `ldstr` instruction. If this is the case, you'll need to convert it to a UTF-16LE byte array. ASCII should cover the vast majority of cases in English, but if you need another target language, it may be best to convert everything to a `bytearray`. Again, PRs are welcome.
* **The Google Translate API is not totally free.** See below.

## Cost to Run

At the time of writing, the Google Translate API is free for up to 500,000 characters per month (in the form of a $10 credit on your bill). Beyond that, Google charges $20 per million characters (up to a billion characters). See the [Google Translate API Pricing Page](https://cloud.google.com/translate/pricing) for more details.

## Why did you write this in PHP?

I was going to write it in Ruby but I got frustrated with its Regular Expression support and I wanted something like `preg_replace_callback`. As I wrote this, it became clear that PHP was the right choice since it has so many other nice, built in features that made dealing with this sort of stuff nice. I loathe PHP's syntax and haphazard function naming conventions, but what're ya gonna do? ¯\\\_(ツ)_/¯

## Further Reading

* [`ilasm.exe` documentation](https://docs.microsoft.com/en-us/dotnet/framework/tools/ilasm-exe-il-assembler)
* [`ildasm.exe` documentation](https://docs.microsoft.com/en-us/dotnet/framework/tools/ildasm-exe-il-disassembler)
* [About the Visual Studio Developer Command Prompt](https://docs.microsoft.com/en-us/visualstudio/ide/reference/command-prompt-powershell?view=vs-2019)
* [Stack Overflow answer detailing the `ldstr bytearray` instruction](https://stackoverflow.com/a/9113641)
* [Stack Overflow answer on how to disassemble and reassemble a .NET executable](https://stackoverflow.com/a/22426970)
* [Documentation for the Google Translate API this uses](https://cloud.google.com/translate/docs/reference/rest/v2/translate)

## License
GPLv3
