**zip** is a class defined in zip.php that allows zip creation with a little resource consumition; it flushes all data at the moment it is provided, so it can't reverted or deleted.

# Usage #

```

// STEP 0 (sending http headers; optional)
zip::httpStartSend(); // Sends http headers to send zip file as an octect/stream

// STEP 1 (starting zip)
$zip = new zip(); // Starts a new zip file that will flush on to standard output
$zip = new zip(fopen('file.zip', 'wb')); // Starts a new file that will flush on to file.zip file

// STEP 2 (adding files)
$zip->addFile('../localfile.dat', 'folder/zipfile.dat'); // Put in the zip a file "folder/zipfile.dat" with the contents and the time/date of the local "../localfile.dat"
$zip->addData("DATA\0\x01\x02", 'binary.raw', time()); // Create a file called "binary.raw" in zip with the contents of the first string and the current time as the modified time of the file specified as unix timestamp.
$zip->addStream(fopen('php://filter/write=string.rot13/resource=test.txt', 'rb'), 'test_rev.txt', filemtime('test.txt')); // Creates test_rev.txt file in zip with the contents of the test.txt aplying a rot13 filter.

// STEP 3 (finalizing zip)
$zip->finalize(); // finalizes zip writting (writting control directory and final data)

```