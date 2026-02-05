<?php

require_once "vendor/autoload.php";

// ============ CONFIGURATION ============

// Device configuration
$USB_DEVICE = "/dev/input/by-id/usb-2022_0202-event-kbd";
$SERIAL_DEVICE = "/dev/ttyACM0";
$SERIAL_BAUD = 9600; // Adjust if your scanner uses different baud rate

// Auto-detect mode or use command line argument
$MODE = $argv[1] ?? 'auto';

// =======================================

function insertEntry($user,$upc,$data,$weight) {
    include('db.php');

    $query = "INSERT INTO `food` (`user`,`upc`,`data`,`weight`) VALUES (:user,:upc,:data,:weight) RETURNING id;";

    $qd = [
        'user' => $user,
        'upc' => $upc,
        'data' => $data,
        'weight' => $weight	
    ];
    
    $stmt = $dbconn->prepare($query);                
    if ($stmt->execute($qd)) {
        return true;
    }
    return false;	
}

function processUPC($code) {
    $weightInGrams = 'NA';

    try {
        $reader = \USBScaleReader\Reader::fromDevice('/dev/scale');
        $weightInGrams = $reader->getWeight();
        var_dump($reader, $weightInGrams);
    } catch (ScaleException $e) {
        $weightInGrams = 'NA';
        error_log("Scale Error: " . $e->getMessage());
        echo "Could not read from scale. Please check the connection or permissions.";
    } catch (\Throwable $t) {
        $weightInGrams = 'NA';
        echo "A critical error occurred: " . $t->getMessage();
    }

    return [$weightInGrams, json_encode(json_decode(file_get_contents("https://world.openfoodfacts.org/api/v0/product/$code.json"),true), JSON_PRETTY_PRINT)];
}

// ============ USB MODE ============
function runUSBMode($device_file) {
    $event_format = "qsec/qusec/vtype/vcode/ivalue";
    $event_size = 24;

    $linuxKeyMap = [
        // Numbers
        2  => '1', 3  => '2', 4  => '3', 5  => '4', 6  => '5', 
        7  => '6', 8  => '7', 9  => '8', 10 => '9', 11 => '0',

        // Letters (QWERTY Order in Kernel)
        16 => 'Q', 17 => 'W', 18 => 'E', 19 => 'R', 20 => 'T', 
        21 => 'Y', 22 => 'U', 23 => 'I', 24 => 'O', 25 => 'P',
        30 => 'A', 31 => 'S', 32 => 'D', 33 => 'F', 34 => 'G', 
        35 => 'H', 36 => 'J', 37 => 'K', 38 => 'L',
        44 => 'Z', 45 => 'X', 46 => 'C', 47 => 'V', 48 => 'B', 
        49 => 'N', 50 => 'M',

        // Essential modifiers to detect Case
        42 => 'LEFT_SHIFT',
        54 => 'RIGHT_SHIFT',
        58 => 'CAPS_LOCK'
    ];

    $fd = fopen($device_file, "rb");
    if (!$fd) {
        die("Cannot open $device_file. Check permissions or if the file exists.\n");
    }

    echo "USB Mode: Listening for events on $device_file...\n";
    $kstr = '';

    while (true) {
        $ev = fread($fd, $event_size);

        if (strlen($ev) != $event_size) {
            continue;
        }

        $event = unpack($event_format, $ev);

        if ($event['type'] == 1) { // EV_KEY
            if ($kstr && $event['value'] == 0 && $event['code'] == 28) {
                echo "Scanned barcode: $kstr\n";
                list($weight, $data) = processUPC($kstr);
                insertEntry('default', $kstr, $data, $weight);
                $kstr = '';
            } elseif ($event['value'] == 1) {
                $kstr .= $linuxKeyMap[$event['code']] ?? '';
            }
        }
    }

    fclose($fd);
}

// ============ SERIAL MODE ============
function runSerialMode($device_file, $baud_rate) {
    // Configure the serial port
    exec("stty -F $device_file $baud_rate cs8 -cstopb -parenb");

    $fd = fopen($device_file, "r");
    if (!$fd) {
        die("Cannot open $device_file. Check permissions or if the file exists.\n");
    }

    echo "Serial Mode: Listening for barcodes on $device_file (Baud: $baud_rate)...\n";

    while (true) {
        $barcode = fgets($fd);
        
        if ($barcode === false) {
            continue;
        }
        
        $barcode = trim($barcode);
        
        if (!empty($barcode)) {
            echo "Scanned barcode: $barcode\n";
            list($weight, $data) = processUPC($barcode);
            insertEntry('default', $barcode, $data, $weight);
        }
    }

    fclose($fd);
}

// ============ AUTO-DETECT MODE ============
function detectMode($usb_device, $serial_device) {
    $usb_exists = file_exists($usb_device);
    $serial_exists = file_exists($serial_device);
    
    if ($usb_exists && $serial_exists) {
        echo "Warning: Both USB and Serial devices detected.\n";
        echo "USB Device: $usb_device\n";
        echo "Serial Device: $serial_device\n";
        echo "Defaulting to USB mode. Use 'php script.php serial' to override.\n";
        return 'usb';
    } elseif ($usb_exists) {
        echo "Detected USB device: $usb_device\n";
        return 'usb';
    } elseif ($serial_exists) {
        echo "Detected Serial device: $serial_device\n";
        return 'serial';
    } else {
        die("Error: Neither USB ($usb_device) nor Serial ($serial_device) device found.\n" .
            "Please check your connections and device paths.\n");
    }
}

// ============ MAIN ============
if ($MODE === 'auto') {
    $MODE = detectMode($USB_DEVICE, $SERIAL_DEVICE);
}

if ($MODE === 'usb') {
    runUSBMode($USB_DEVICE);
} elseif ($MODE === 'serial') {
    runSerialMode($SERIAL_DEVICE, $SERIAL_BAUD);
} else {
    die("Invalid MODE setting. Use 'usb', 'serial', or 'auto'\n");
}

?>