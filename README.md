# Transcribe audio in Asterisk with Google Speech Recognition

This repository is an example of how to use Google Speech Recogntion in Asterisk to transcribe audio voice.

Tools that are used here :
- Asterisk PHP-AGI (PHP Asterisk Gateway Interface)

# Set up Asterisk

Edit your `extensions.conf` according to the example given in this repository :

```
exten =>1234,1,answer
same=>n,agi(speechrecog.php)
same=>n,Hangup()
```
Copy the `speechrecog.php` of this repository to `/var/lib/asterisk/agi-bin/transcribeWithGoogle.eagi`.

Call extension 1234 and inspect your Asterisk CLI to get the transcription, the `PHP` script print the result.