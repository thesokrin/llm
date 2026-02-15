<?php

// pn - probably can remove since no large db pulls will be used
ini_set('memory_limit', '4G');

// Allow different global variables based on the filename allowing for easy transition between development
// and production settings and operations. Eg. verbose error outputting for dev debugging versus quiet output
// for production operation

$invocation_magic = __FILE__; // Make sure nothing else is going to overwrite
// This file could just be included in this one, however, I have it external for security
include 'kcs.php';

// Set a base timezone, however, the timezone will adjust based on the DID user's preferences
// Will be used in future features such as watch party scheduling
date_default_timezone_set('UTC'); 

// `composer` library integration
include __DIR__.'/vendor/autoload.php';

use Discord\Discord;
// use Discord\Http\Client;
use Discord\Http;
// use Discord\Http\Drivers\React;
use Discord\Parts\User\User;
use Discord\Parts\User\Client;
use Discord\Parts\User\Member;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Channel\Reaction;
use Discord\Parts\WebSockets\MessageReaction;
use Discord\WebSockets\Intents;
use Discord\WebSockets\Event;
use Discord\Builders\MessageBuilder;
use Discord\Builders\Components;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\SelectMenu;
// use Discord\Builders\Components\Option;


use Discord\Parts\Interactions\Command\Option;



// use Discord\Builders\MessageBuilder;
// // use Discord\Builders\MessageBuilder;
// use Discord\Builders\Components;
// use Discord\Builders\Components\ActionRow;
// use Discord\Builders\Components\Button;
// use Discord\Builders\Components\SelectMenu;
// use Discord\Builders\Components\Option;

use Discord\Parts\Interactions\Command\Command; 	// Please note to use this correct namespace!
// use Discord\Parts\Interactions\Command\CommandBuilder; // Or similar class for building commands
// use Discord\Parts\Interactions\Command\Command; 

use Discord\Builders\CommandBuilder;
use Discord\Parts\Interactions\Interaction;

use YouTube\YouTubeDownloader;
use YouTube\Exception\YouTubeException;

// pn - move to functions script
if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle) {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
  }
}

// Send a message to a discord user. 
function sendMsg($id, $msg, $type = 'user', $server = 788607168228229160) {
	$embed = null;
	// If $msg is an array, it contains an embed. 
	if (is_array($msg)) {
		// Split the array. 0 being the message and 1 being the embed
		$embed = $msg[1];
		$msg = $msg[0];
	}
	// Attach the app signature to the end of the message to be sent
	$msg .= "\n".tacoGen();
	
	if (strlen($msg) > 950) {
		sendReply($id,$msg);
		return;
	}
	// bring the current discord loop in
	global $discord;
	
	// adjust destination based on function argument input
	if ( $type == 'channel' ) { 
		$guild = $discord->guilds->get('id', $server);
		$message = $guild->channels->get('id', $id);
	}
	if ( $type == 'user' ) {
		$message = $discord->factory(\Discord\Parts\User\User::class, [
			'id' => $id, //'380675774794956800',
		]);
	}
	if (!$msg) { $msg = "Message is null?"; } 
	$message->sendMessage($msg,false,$embed)->then(function(Message $message) {
		echo "\nMessage sent!\n";
		var_dump($message['id']);
	});
}

if (!$ws = findWorkspace('global','player')) {
// $ws = json_decode(file_get_contents('ws.json'),true)) {
	$ws = [];
} // else {


// function wsGlobal($name) {
	
	
	
// }


// Check to see if a message is a workspace
function checkWorkspace($rdata,$name = '') {
	global $wsLines;

	$wid = $rdata['message_id'];
	$cid = $rdata['channel_id'];
	var_dump('checkWS',$wid,$cid,$name);

	if (in_array($cid,array_keys($wsLines)) && isset($wsLines[$cid][$name]) && isset($wsLines['wsnames'][$cid][$wid])) {
		// var_dump('$wsLines[$cid][$name]');
		// var_dump($wsLines[$cid][$name]);
		$name = $wsLines['wsnames'][$cid][$wid];
		if ($wsLines[$cid][$name]['wid'] == $wid) { if ($name == '') { $name =  true; } return $name; }
	}

	include('db.php');
	$data = ['wid' => $wid,'cid' => $cid];
	$pre = $GLOBALS['filePrefix'];
	$query = "SELECT * FROM `".$pre."workspaces` WHERE cid=:cid AND wid=:wid"; // $andgid";

	var_dump($data);
	$res = $dbconn->prepare($query);
	$res->execute($data);
	if ($results = $res->fetch(PDO::FETCH_ASSOC)) {
		var_dump('$results');
		var_dump($results);
		$dbcid = $results['cid'];
		$dbwid = $results['wid'];
		$dbname = $results['name'];
		if ($wid == $dbwid && $cid == $dbcid) { if ($dbname == '') { $dbname =  true; } return $dbname; }
	} else {
		var_dump('FALSE $results');
		var_dump($results);
	}
	return false;
}

// Check if a workspace exists for current channel
function findWorkspace($data,$name = '') {
	$pre = $GLOBALS['filePrefix'];
	$andgid = '';

	$isGlobal = false;
	if ($name == 'player' && is_string($data) && $data == 'global') {
		$data = ['name' => $name];
		$query = "SELECT * FROM `".$pre."workspaces` WHERE name=:name"; // $andgid";
		$isGlobal = true;
	} else {
	// if (isset($da
		$cid = $data['channel_id'];
		$gid = $data['guild_id'];
		$data = ['cid' => $cid,'name' => $name];
		$query = "SELECT cid,wid,name FROM `".$pre."workspaces` WHERE cid=:cid AND name=:name"; // $andgid";
	}



	var_dump($data);
	include('db.php');
	$res = $dbconn->prepare($query);
	$res->execute($data);
	$dbname = false;
	$wid = false;
	
	// $res->fetchAll(PDO::FETCH_ASSOC);

	if ($results = $res->fetchAll(PDO::FETCH_ASSOC)) {
		global $ws;
		
		if (!$isGlobal) {
			if (!$results || !count($results)) {
				return false;
			}
			$results = $results[0];
			var_dump($results);
			$wid = $results['wid'];
			$cid = $results['cid'];
			$dbname = $results['name'];
			if ($dbname == 'player') { $ws[$cid] = $wid; }
			return $wid;
		}
		$r = $results;
		$ws = array_combine(array_column($r,'cid'),array_column($r,'wid'));
		file_put_contents('ws.json',json_encode($ws, JSON_PRETTY_PRINT));
		var_dump($ws);
		return $ws;
	}
	return false;
}

// Create or reset a workspace and populate with $output
function initWorkspace($data, $wid = null, $new = false, $output = null, $name = '') {
	$ooutput = $output;
	// var_dump('$output 0',$output);

	if ($name == 'player') {
		var_dump('--------------------$data 0',$data);
	}
	
	$reset = false;
	$curwid = null;
	if ($wid == 'reset') {
		$reset = true;
		$wid = null;
	}
	if ($wid == null && !$new) {
		$wid = findWorkspace($data,$name);
		if (!$wid) {
			echo "workspace id not found for channel\n";
			$new = true;
		} else {
			echo "workspace id found! $wid\n";
			$curwid = $wid;
		}
	}

	$channel = getChannel($data);
	if (!$channel) {
		return "ERR84389743";
	}
	if ($wid) {
		$message = $channel->messages->get('id', $wid);
	}
	var_dump('$reset');
	var_dump($reset);
	//var_dump('$output 1',$output);
	if ($reset && !$new) {
		$curwid = $reset = $wid;
	}
	if ($reset && !$new && $output == null) {
		$reset = $wid;
		$message = $channel->messages->get('id', $wid);
		var_dump('$message->content');
		if ($output == NULL && $message !== NULL) { $output = $message->content; }
		if ($output == NULL) { $output = ""; }
		$new = true;
	}
		// var_dump('$output 2',$output);
	// if ($output == null) { $output = "Base Template for Workspace Modules\n".date('Y-m-d H:i:s'); }
	if ($output == null) { $output = ($name == 'player')?refreshPlayerStatus('return'):""; }
	if ($new || $reset) {
		var_dump('$new $reset',$new, $reset);
		if (!$output) { $output = ' '; }
		
		$outs = splitMsg($output);
		$out = (isset($outs[0]))?$outs:['-'];
		$channel->sendMessage($out[0])->then(function (Message $message) use ($data,$output,$reset,$curwid,$name) {
			$wid = $message['id'];
			$cid = $message['channel_id'];
			$gid = $message['guild_id'];

			include('db.php');
			$pre = $GLOBALS['filePrefix'];
			$query = "INSERT INTO `".$pre."workspaces` (`wid`,`cid`,`gid`,`name`) VALUES (:wid,:cid,:gid,:name) ON DUPLICATE KEY UPDATE wid=:wid";
			echo "saving workspace data\n";
			$res = $dbconn->prepare($query);
			//	echo "s98fd7s09d8yf0s8yf0s98ssssssssssssssssssssssssssssssssssssssss";
			$err = 0;
		//	var_dump("===================== WID $curwid | $reset | $wid =================================");
			
			$wsarrname = 'playlist';
			if ($name == 'player') {
				global $lastStatusData;
				global $ws;
				$ws[$cid] = $wid;
				file_put_contents('ws.json',json_encode($ws, JSON_PRETTY_PRINT));

				//var_dump('6666666666666666666666666666666666666666666',$lastStatusData,$data);
				$lastStatusData = $message;
				$wsarrname = 'player';
			}
			foreach ($GLOBALS[$wsarrname.'Array'] AS $emotename => $emote) {
				//var_dump($wsarrname,$emotename,$emote);
				$message->react($emote);
			}
			if (!$status = $res->execute(['cid' => $cid,'gid' => $gid,'wid' => $wid,'name'=>$name])) {
			//echo "88888888888888888888888888888888888888888888888eeeeer66";
			
			$err = 1;
				
			} else if ($reset) {
				$channel = getChannel($data);
				//var_dump("WID $wid =================================");
				
				$channel->messages->fetch($reset)->then(function (Message $oldMsg) use ($reset) {
					//var_dump("delete $reset =================================");
					$oldMsg->delete()->then(function () {
						//var_dump("delete 0000000000000000=================================");
					});
				});
			}
			if ($output !== null) { outputWorkspace($data,$output,$name); }
		});
	} else {
		//$output = "UPDATED!!!!!! $output";
		$channel = getChannel($data);
		outputWorkspace($data,$output,$name);
	}
}


if (!$wsLines = json_decode(file_get_contents('wslines.json'),true)) {
	$wsLines = [];
	file_put_contents('wslines.json',json_encode($wslines, JSON_PRETTY_PRINT));
}

function searchArr($search, $cat = 'tv',$col = 'label') {
	global $kodi;
	global $kcache;
	
	if (!$kcache = json_decode(file_get_contents('kcache.json'),true)) {
		$kcache = [];
	}
	
	if (startsWith($search,'search%3A')) {
		$search = urldecode($search);
		list($actn,$cat,$search) = explode(':',$search);
	}
			
	if (!isset($kodi['npaths'][$cat])) {
		return "Invalid category";
	}
	// $path = stripcslashes($kodi['npaths'][$cat],'\\');
		$path = $kodi['npaths'][$cat];
	// $media = ($cat == 'music')?"music":"video";
	// $path = $kodi['npaths'][$cat];
	// $sort = ',"sort":{"method":"label","order":"ascending"}';
	// $filter = '	"filter": {		"field": "title",		"operator": "contains",		"value": "'.$text.'"	},';
	// // $json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1747936376779","params":{"directory":"multipath://smb%3a%2f%2f192.168.12.100%2fD%2fmovies%2f/%2fvar%2fdata%2fmedia%2fmovies%2f/smb%3a%2f%2f192.168.12.100%2fE%2fmovies%2f/smb%3a%2f%2f192.168.12.100%2fF%2fmovies%2f/smb%3a%2f%2f192.168.12.100%2fM%2fmovies%2f/smb%3a%2f%2f192.168.12.100%2fG%2fmovies%2f/","media":"video","properties":["title","file","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}'
	// // $json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1747936376779","params":{'.$filter.'"directory":"'.$path.'","media":"'.$media.'","properties":["title","file","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
	// // $json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1747936376779","params":{'.$filter.'"directory":"'.$path.'","media":"'.$media.'","properties":["title","file","mimetype","thumbnail","dateadded"]}}';
	// $json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{'.$filter.'"directory":"'.addcslashes($path,'\\').'","media":"video","properties":["title"]}}';

	// var_dump($json);
		// sendReply($data, $json."\n".json_encode(json_decode($json,true), JSON_PRETTY_PRINT ));

	// $output = $_Kodi->sendJson($json);
	
	//var_dump($output);
	//global $kcache;
	if (!isset($kcache[$path]['content']['result']['files'])) {
		cacheKdir($path,$cat);
	}
	$my_array = $kcache[$path]['content']['result']['files'];
	
	//sendReply($data, count($music));


	// $needle = explode(' ',$search); 
	$needle = preg_split('/"([^"]*)"|\h+/', $search, -1, 
                   PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
		if (isset($my_array[0]['s'.$col])) {
			$col = 's'.$col;
		}
	
	var_dump($needle);
//$wordarray = array('check','cheque','czech');
	// $pattern = implode("|",$needle);
	// if (preg_match("/($pattern)/", $my_array,$pmatch)){
   // echo "Word is not found";
	// } else {
    // echo"Word is not found"; 	
	// }

	$combo = $my_array;
	$cats = [];
	// $tcats = [];
	$marr = [];
	$tn = '';
	$counts = [];
	foreach ($needle AS $n) {

		$cats[$n] = array_filter($my_array, fn($item) => false !== stripos($item[$col], $n));
		$counts[$n] = count($cats[$n]);

		if (count($cats[$n])) { $marr = array_merge_recursive($marr,$cats[$n]); }
		if ($tn.$n !== $n) {
			
			$cats[$tn.$n] = array_filter($cats[$tn], fn($item) => false !== stripos($item[$col], $n));
			$counts[$tn.$n] = count($cats[$tn.$n]);
			if (count($cats[$tn.$n])) { $marr = array_merge_recursive($cats[$tn.$n],$marr); }
		}
			//$cats[$tn.$n] = array_merge_recursive($cats[$tn],$catresults);
		// } else {
			//$cats[$tn.$n] = $cats[$n];
		// $cats[$tn] = array_filter( $cats[$tn], fn($item) => false !== stripos($item[$col], $n));
		$tn .= $n;
		// if (!count($combo) || !$combo = array_filter($combo, fn($item) => false !== stripos($item[$col], $n))) {
		// // $combo = array_merge_recursive($combo,$match);
			// $match = array_filter($my_array, fn($item) => false !== stripos($item[$col], $n));
			// $marr = array_merge_recursive($marr,$match);
		// } else {
			// $marr = array_merge_recursive($marr,$combo);
		// }
	}
		// print_r($match);

	// $hasNeedle = !!$matches;
	// if ($combo) {
		// $marr = $combo;
	// }
	$marr = array_map("unserialize", array_unique(array_map("serialize", $marr)));
	ksort($cats);
	$cats = array_reverse($cats);
	
	var_dump($marr);
	file_put_contents('searchres.json',json_encode([$counts,$cats],true));
	return (count($marr))?$marr:false;
}



// Pagination action for workspaces
function wsPages($data,$lines = false,$pag = 0,$name = '') {
	global $wsLines;
	global $kodi;
	global $lastStatusData;
	global $ws;
	$cwsl = false;

	$isGlobal = false;
	$bdata = false;
	$crc = $total = null;
	if ( $name == '' && is_string($lines) && strlen($lines) < 200) {
		playerMsg($lines); return;
	}
	
	if ( is_string($data) && $data == 'global') {
		$wid = $cid = 'global';
		$isGlobal = true;
		$channels = $ws;
	} else {
		$cid = $data['channel_id'];
		// $did = $data['user_id'];
		// if (!$did && isset($data['author']['id'])) {
			// $did = $data['author']['id'];
		// }
		$wid = findWorkspace($data,$name);
		$channels = [$cid => $wid];
	}
	
	$did = false;
	if (isset($data['user_id'])) {
		$did = $data['user_id'];
	} else if (isset($data['author']['id'])) { 
		$did = $data['author']['id'];
	} else if (isset($lastStatusData['user_id'])) {
		$did = $lastStatusData['user_id'];
	} else if (isset($lastStatusData['author']['id'])) {
		$did = $lastStatusData['author']['id'];	
	}
	
	file_put_contents('wsdata.json',print_r([$did,$data, $lastStatusData ],true));

	if ($lines !== NULL && $lines !== false) {
		if (!$wid) {
			initWorkspace($data,null, true, $lines,$name);
			return;
		}
	}
	if ($lines) {
		if (!is_array($lines) && is_string($lines)) {
			$lines = splitMsg($lines);
		}		
		$crc = crc32(json_encode($lines));
		$total = count($lines);
	}

	$curpage = $cwsl = false;
	if ($pag !== 0 && !$lines && isset($wsLines[$cid][$name]) && is_array($wsLines[$cid][$name]['lines'])) {
		$cwsl = $wsLines[$cid][$name];
		$gcwsl = $wsLines['global'][$name];
		$lines = $cwsl['lines'];
		$total = count($cwsl['lines']);
		$curpage = $page = intval($cwsl['page']);
		$wid = $cwsl['wid'];
		$crc = $cwsl['crc'];
		if (!isset($did) && isset($cwsl['did'])) {
			$did = $cwsl['did'];
		}
	} else {
		$curpage = 1;
	}

	if (is_array($lines) && (!count($lines) || !isset($lines[0]) || !trim($lines[0]))) {
		echo "\n LINES ARRAY TOO FUNKY IN WS OUTPUT \n"; return;
	}

	if ($lines === NULL) {
		echo "\n NULL LINES IN WS OUTPUT \n"; return;
	}

	if ($lines === false) {
		echo "\n FALSE value LINES IN WS OUTPUT \n"; return;
	}
	
	$wsLines['wsnames'][$cid][$wid] = $name;
	$wsLines['wsids'][$name][$cid] = $wid;

	if (!isset($page)) { $page = 1; }
	if (!is_numeric($pag)) {
		if ($pag == 'n') { $page++; }
		if ($pag == 'b') { $page--; }
		if ($pag == 's') { $page = 1; }
		if ($pag == 'e') { $page = $total; }
	} else {
		$page = intval($pag);
	}	
	
	$page = intval($page);
	if ($page > $total) {
		$page = $total;
	}
	if ($page < 1) {
		$page = 1;
	}
		
	if ($lines == '') { return; } 
		
	$wsl = [
		'crc' => $crc,
		'name' => $name,
		'lines' => $lines,
		'total' => $total,
		'wid' => $wid,
		'page' => $page
	];
	if ($did) {
		$wsl['did'] = $did;		
	}
	
	var_dump('$wsl["page"]',$wsl["page"]);
	
	$wsLines[$cid][$name] = $wsl;
	
	$pageInfo = "\n Page $page of $total\n";
	
	if ($total == 1 || $name == 'player') {
		$pageInfo = '';
	}
	if (( !isset($kodi['menu']['did']) || !$kodi['menu']['did']) && $did !== false) { $kodi['menu']['did'] = $did; }
	if ($did !== false && $curpage !== $page && $kodi['path'] && isset($kodi['uhist'][$did])) {
		// function searchForKey($id, $array, $col,$which = 'last') {
		$key = searchForKey(stripcslashes($kodi['path']),$kodi['uhist'][$did],'file');
		if ($key !== false) {
			$kodi['uhist'][$did][$key]['page'] = $page;
			file_put_contents('uhist.json',json_encode($kodi['uhist'], JSON_PRETTY_PRINT));	
		} else {
			var_dump("KEYNOTFOUND wspages",$curpage,$page,$kodi['path'],$did); //,$kodi['uhpointer'][$did]);
		}
	}

	$kodi['menu']['page'] = $page;
	$kodi['menu']['autoq'] = $kodi['autoq'];
	$kodi['menu']['autoplay'] = $kodi['autoplay'];
	$kodi['menu']['playing'] = $kodi['playing'];
	file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));	
	$apmode = '';
	
	// $ap = activePlayer();
	
	if ($name == 'player') { 
		$plid = (isset($kodi['plid']))?$kodi['plid']:activePlayer();
		$plt = '';
		if (is_numeric($kodi['qlmode'])) { $plt = ($kodi['qlmode'] === 1)?"Video ":"Music "; }

		$autoNext = ($kodi['qlmode'] === false)?"AutoNext: ".(($kodi['autoplay'])?"🟢":"🔴"):$plt."Queue List Mode (".$kodi['qlmode'].")";

		$spkr = "🔈";
		if ($kodi['vol'][1] > 33) {
			$spkr = ($kodi['vol'][1] < 66)?"🔉":"🔊";
		}
		if (!isset($kodi['shuffle'][$plid])) { $shf = '❔'; } //$kodi['shuffle'][$plid] = false; }
		else { $shf = ($kodi['shuffle'][$plid])?"🟢":"🔴"; }
		$shf .= " ($plid)";

		if (!isset($kodi['repeat'][$plid])) { 
			$rpt = '❔'; 
		}	else { 
			$rpt = ($kodi['repeat'][$plid] === "all")?"🔁":(($kodi['repeat'][$plid] === "one")?"🔂":"🔴"); 
		}
		
		// $rpt .= " ($plid)";		
		
		$aq = ($kodi['autoq'])?$kodi['autoq']:"🔴";
		$isMuted = ($kodi['vol'][0])?"🔇":$spkr; 
		$apmode = "Loop: ".$kodi['loopstat']." | Volume: ".$kodi['vol'][1]."% $isMuted | $autoNext | Repeat: $rpt | Shuffle: $shf | AP: $plid | Play/Queue: $aq | "; }
	global $plmodeIcons;
	if ($lines[$page-1]) { $pageInfo = "\n$apmode Mode: ".$plmodeIcons[$kodi['plmode']]." $pageInfo"; }
	$output = $lines[$page-1].$pageInfo;
	if (!$isGlobal && isset($ws[$cid]) && $ws[$cid] == $wid	) {
		$wsLines['global'][$name] =	$wsl;
		$wsLines['global'][$name]['wid'] = $wid;
	}

	foreach ($channels AS $gcid => $gwid) {
		if ($isGlobal) {
			$wsLines[$gcid][$name] = $wsl;
			$wsLines[$gcid][$name]['wid'] = $gwid;
		}
		if (!($channel = getChannel($gcid)) || (!($channel->messages))) { var_dump('error 7869',$gcid); continue; }
		$channel->messages->fetch($gwid)->then(function (Message $Msg) use ($output) {
			//$rateLimitLimit = $Msg->getResponse()->getHeader('X-RateLimit-Limit');
			//var_dump('$rateLimitLimit',$rateLimitLimit);
			$Msg->edit(MessageBuilder::new()->setContent($output)); 
		})->otherwise(function ($e) {
    // Check for rate limits
			if ($e instanceof \Discord\Exceptions\DiscordRequestException) {
					$rateLimitReset = $e->getResponse()->getHeader('X-RateLimit-Reset');
					$rateLimitLimit = $e->getResponse()->getHeader('X-RateLimit-Limit');
					$rateLimitRemaining = $e->getResponse()->getHeader('X-RateLimit-Remaining');
					echo "Rate Limit Info:\n";
					echo "Limit: $rateLimitLimit\n";
					echo "Remaining: $rateLimitRemaining\n";
					echo "Reset: $rateLimitReset\n";
			}

			echo "An error occurred: {$e->getMessage()}\n";
			exit;
		});
	}
	file_put_contents('wslines.json',json_encode($wsLines, JSON_PRETTY_PRINT));
}

// Send output to workspace, paginate as necessary
function outputWorkspace($data,$output,$name = "",$page = 0) {
	if (!is_string($data) && !isset($data['channel_id'])) {
		// var_dump('$data,$output,$name');
		// var_dump($data,$output,$name,'AAAAAAAAAAAA');
		return;
	} else if (!is_string($data)) {
		// var_dump($data,gettype($data));
		$cid = $data['channel_id'];
	}
	$wid = findWorkspace($data,$name);
	if (!is_string($data) && !$wid) {
		// global $ws;
		// $ws = $wid;
		// $wid = $ws[$cid];
		// $data['global'] = $ws;
	// } else {
			initWorkspace($data,null,true, $output,$name);
			return;
		// }
	}
	wsPages($data,$output,$page,$name);
}

// Good spelling means good communication!
function spellCheck($word,$good = false) {
	$pspell = pspell_new("en");
	$ret = false;
	if (!pspell_check($pspell, $word)) {
    $suggestions = pspell_suggest($pspell, $word);
		if (count($suggestions)) { $bsuggestions = $suggestions; $bsuggestions[0] = '**'.$bsuggestions[0].'**'; }
		$ret = "Did you mean ".niceList($bsuggestions,'','or')."?";
		if ($good == 'array' && count($suggestions) == 1) {
			$ret = $suggestions[0];
		}
	} else if ($good) {
		$ret = "**$word** is spelled correctly!";
	}		
	return $ret;
}

// Message signature
function tacoGen() {
	$tmews = rand(1,3);
	$mews = "";
	for ($k = 0 ; $k < $tmews; $k++){ $mews .='meow '; }
	return "\n*".ucfirst(trim($mews))."!*";
}

// Parse and prepare a channel object from almost any breadcrumb
function getChannel($data) {
	global $discord;
	if(isset($data['channel_id'])) { 
		$channel = $discord->getChannel($data['channel_id']);
		return $channel;
	}
	if(is_numeric($data)) {
		if (!$channel = $discord->getChannel($data)) {
		$channel = $discord->factory(\Discord\Parts\User\User::class, [
			'id' => preg_replace("/[^0-9]/", "", $data), //'380675774794956800',
		]);
		}
	} else if(!is_object($data) || !$data->channel->guild_id) { 
		$channel = $discord->getChannel($data['channel_id']);
	} else if (isset($data['guild_id'])) {
		$guild = $discord->guilds->get('id', $data['guild_id']);
		$channel = $guild->channels->get('id', $data['channel_id']); 
	} else {
		$channel = $discord->getChannel($data['channel_id']);
	}
	return $channel;
}

// Parse and prepare a guild object from almost any breadcrumb
function getGuild($data) {
	global $discord;
	$guild = null;
	if(is_numeric($data) || $data->channel->guild_id === NULL) {
		return $guild;
	} else {
		$guild = $discord->guilds->get('id', $data['guild_id']);
	}
	return $guild;
}

// Patch a message
function updateMsg($data, $message = '', $embed = NULL) {
	var_dump($embed);
	
	if (!$message && !$embed) { return;}
	global $discord;

	//$msg = $message;
	$channel = getChannel($data);
	
	// $channel->sendMessage($message, false, $embed);
	$channel->messages->fetch($data)->then(function (Message $Msg) use ($message,$embed) {
		$Msg->edit(MessageBuilder::new()->setContent($message)); 
	});
}

// Prepare messages for discord character limit
function splitMsg($message) {
	if (is_array($message)) {
		return $message;
	}
	$lines = null;
	if (strlen($message) > 1950) {
		$x = 1910;
		$message = str_replace(" ","===SPACE===",$message);
		$message = str_replace("\n"," ",$message);
		$message = wordwrap($message, $x,'===LINEBREAK===');
		$message = str_replace(" ","\n",$message);
		$message = str_replace("===SPACE===",' ',$message);
		$lines = explode('===LINEBREAK===', $message);
	} else {
		$lines = [$message];
	}
	return (isset($lines[0]))?$lines:[''];
}

// Reply to received message
function sendReply($data, $message = '', $embed = NULL) {
	if (!$message && !$embed) { return;}

	if (filter_var($message, FILTER_VALIDATE_URL) === false) {
		$message .= " ".tacoGen();
	}
	$lines = splitMsg($message);
	global $discord;
	$channel = getChannel($data);
	if ($channel == NULL) {
		return;
	}
	$index = 0;
	if (!is_array($lines)) {
		$lines = [$lines];
	}
	foreach ($lines AS $message) {
		$index++;
		if (strlen($message) > 2000) {
			var_dump('error strlen($message)',strlen($message));
			$message = substr($message,0,1990);
		}
		if ($index == count($lines)) {
			$channel->sendMessage($message, false, $embed)->then(function(Message $message) {
				echo "\nMessage sent!\n";
				return;
			});
		} else {
			$channel->sendMessage($message)->then(function(Message $message) {
				echo "\nMessage sent!\n";
				return;
			});
		}
	}
	unset($embed);
}

// Cheap AI chatbot. So so cheap....
if (!$chatbot = json_decode(file_get_contents('chatbot.json'),true)) {
	$chatbot = ['uid'=> '887d79e5bbec1ee2','sid'=> []];
	file_put_contents('chatbot.json',json_encode($chatbot));
}

$airl = false;
$tokens = [];

function chatBot($arg,$did = false) {
	global $tokens;
	$botName = "cgpt";
	$message = "Error processing information for $botName";
	if ($botName == 'cgpt') {
		$headers = ["Content-Type: application/json",
			"Authorization: Bearer ".$GLOBALS['aiToken']
		];
		$post = [
		 "model" => "gpt-4o-mini", 
		 "store" => true, 
		 "messages" => [
					 [
							"role" => "user", 
							"content" => $arg 
					 ] 
				] 
		]; 

		//var_dump($botName,json_encode($post,JSON_PRETTY_PRINT));
		$post = json_encode($post);

		global $airl;

		if ($airl !== false) {
			$message = 'rate limit currently engaged. please wait to send a message';
		} else {
			$curl = curl("https://api.openai.com/v1/chat/completions",$post, $headers);
			var_dump($curl);
			$curl = json_decode($curl['content'],true);
			$tokens[$botName]['curl'] = $curl;
			if (isset($curl['error'])) {
				$message = '🙀 '.$curl['error']['code'];
				if ($curl['error']['code'] === 'rate_limit_exceeded') {
					$message = 'rate limit exceeded. will resend message in 8 minutes';
					$info = [$arg,$data];
					resendMessage($info);
				} else {
					$message = json_encode($curl, JSON_PRETTY_PRINT);
				}
			} else {
				$message = $curl['choices'][0]['message']['content'];
			}
		}
	} else if ($botName == 'gemini') {
		$headers = ['Content-Type: application/json'];
		$post = [];
		$post['contents'][0]['parts'][0]['text'] = $arg;
		var_dump($botName,json_encode($post,JSON_PRETTY_PRINT));
		$post = json_encode($post);
	
		$curl = curl("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=".$GLOBALS['aiToken'],$post,$headers);
		$curl = json_decode($curl['content'],true);
		$tokens[$botName]['curl'] = $curl;
		var_dump($curl);
		$message = $curl["candidates"][0]["content"]["parts"][0]["text"];
		//return $message;
	}
	file_put_contents('KKtokens.json',json_encode($tokens, JSON_PRETTY_PRINT));
	return $message;
	
}

function jsonWrap($arg,$did = false,$data = false,$fancy = false) {
	global $ytmap;
	global $kodi;
	$jreq = ' output only json format where "songtitle" is the array key for the title and "songartist" is the array key for the artist and "reason" is the array key for the explanation of why the song was picked';
	$orig = $ret = $out = chatBot($arg.$jreq,$did);
	$pattern = '/```json(.*)```/';
	$out = stripcslashes(str_replace("\n",'',$out));

	preg_match($pattern,$out,$matches);
	if (!$matches) { 
		$ret = "could not decode json | ".$out; 
	}	else {
		$ret = json_decode($matches[1],true);
		if ($je = json_last_error()) {
			$ret = $je." | could not decode json | ".$out;
		} else {
			
			
			
			// $ret = (isset($ret['song']))?$ret['song']:$ret;
			// $ret = (isset($ret['songs']))?$ret['songs']:$ret;
			if (!isset($ret['songtitle'])) {
				$fkey = array_key_first($ret);
				if (isset($ret[$fkey][0]['songtitle'])) {
					$ret = $ret[$fkey];
				}
				var_dump('ret f key',$fkey,$ret);
			}
			
			// isset($ret[$fkey]['songtitle']) || 
			
			$c = 0;
			if (isset($ret['songtitle'])) { 
				$rets = [$ret]; 
			}	else {
				$rets = $ret;
			}
			$pq = 'play';
			global $_Kodi;
			// $plid = (startsWith($path,'smb://192.168.12.100/E/music/'))?0:1;
			$plid = 1;		
			$state = getVidTimes(true);
			$t = count($rets);
			$fret = '';
			
			
			foreach ($rets AS $ret) {
				$c++;
				sleep(1);
				//if ($output = $_Kodi->sendJson($json) && isset($output['result'])) { return ($q)?"Added $vid to queue!":null; }
				$title = (isset($ret['songtitle']))?$ret['songtitle']:'';
				$artist = (isset($ret['songartist']))?$ret['songartist']:'';
				$reason = (isset($ret['reason']))?$ret['reason']:'';
				if (is_array($title)) { $title = array_values($title)[0]; }
				if (is_array($artist)) { $artist = array_values($artist)[0]; }
				if (is_array($reason)) { $reason = array_values($reason)[0]; }
				
				if ($search = trim("$artist $title")) {
					if ($kodi['dj']) { $kodi['djsongs'][] = "$artist - $title"; }
					// $search = [true,$search];
					$path = "plugin://plugin.video.youtube/kodion/search/query/?q=$search&type=video";
					$json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"'.rand(0,1024).'","params":{"directory":"'.$path.'","media":"video","properties":["title","file","artist","duration","comment","description","runtime","playcount","mimetype","thumbnail","dateadded"]}}';
					sleep(2);
					$yts = $_Kodi->sendJson($json);
					if (is_array($yts['result']) && is_array($yts['result']['files']) && $key = searchForKey('file',$yts['result']['files'],'filetype',"first")) {
						$title = $yts['result']['files'][$key]['label'];
						$play = $yts['result']['files'][$key]['file'];
						$vid = explode('=',$play)[1];
						$ytmap[$vid] = $title;
						$ytlink = "$title [$vid](<https://www.youtube.com/watch?v=$vid>)";
						$json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"file":"'.$play.'"}},"id":"'.rand(0,1024).'"}';
						if ($c > 1 || $state !== "Stopped") { 
							$pq='queue';
							$json = '{ "id" : '.rand(0,1024).',"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$play.'" },"playlistid" : '.$plid.'}}'; 
					// } else {
						// $kodi['plmode'] = 'yt';
						}
						sendReply($data, ucfirst($pq)."ing ($c/$t) $ytlink now...");
						$fret .= ucfirst($pq)."ing **$title** by **$artist**.\n$reason\n";
						sleep(1);
						$out = $_Kodi->sendJson($json);
					// $dir = [];
					// $dir['result']['files'] = cacheYTNames($yts);
					//var_dump('YOUTUBESEARCHHHHHHHHHHHHHHHH',$path,$json,$yts);
						// if ($output = $_Kodi->sendJson($json) && isset($output['result'])) { return ($q)?"Added $vid to queue!":null; }
						// var_dump($out);
					} else {
						sendReply($data, "($c/$t) Could not $pq $title by $artist!");
						$fret .= "\n($c/$t) Could not $pq $title by $artist!\n";
						
						
					}
					
					// $output = renderDir($dir,$path,kodiCurItem(),$data);



					// kodi('ytsearch',$search,$data);
				} else {
					var_dump($rets,$ret,'666666666666666666666666666666666666666666666666666666666666666');
					sendReply($data, "($c/$t) ER- $title - $artist -");
				}
			}
			$ret = $orig;
			if ($fancy) { $ret = $fret; }
		}
	}
	file_put_contents('ytmap.json',json_encode($ytmap, JSON_PRETTY_PRINT));
	return $ret;
}

// Prepare output for discord
function strip_tags_content($text) {
  return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
}

function validVideoId($id) {
	return !!(getimagesize("http://img.youtube.com/vi/$id/mqdefault.jpg")[0]);
}

// function curl( $url,$curl_data = null,$timeout = 120 ) {
  // $options = array(
    // CURLOPT_RETURNTRANSFER => true,         // return web page
    // CURLOPT_HEADER         => false,        // don't return headers
    // CURLOPT_FOLLOWLOCATION => true,         // follow redirects
    // CURLOPT_ENCODING       => "",           // handle all encodings
    // CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:109.0) Gecko/20100101 Firefox/111.0",     // who am i
    // CURLOPT_AUTOREFERER    => true,         // set referer on redirect
    // CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
    // CURLOPT_TIMEOUT        => $timeout,          // timeout on response
    // CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
    // CURLOPT_SSL_VERIFYHOST => 2,            // don't verify ssl
    // CURLOPT_SSL_VERIFYPEER => true,        //
    // CURLOPT_VERBOSE        => 0                //
  // );
  // $ch = curl_init($url);
  // curl_setopt_array($ch,$options);
	// if ($curl_data != null) {
		// if (!is_array($curl_data)) { $curl_data = json_encode($curl_data); }
		// //curl_setopt($ch, CURLOPT_POST, 1);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, $curl_data);
		// curl_setopt($ch, CURLOPT_HTTPHEADER, 
			// array (
			// 'Content-Type: application/json',
			// 'Content-Length: ' . strlen($curl_data)
			// )
		// );
	// }

  // $ret['content'] = curl_exec($ch);
  // $ret['err']     = curl_errno($ch);
  // $ret['errmsg']  = curl_error($ch);
  // $ret['header']  = curl_getinfo($ch);
  // curl_close($ch);
  // return $ret;
// }

function curl( $url,$curl_data = null,$header_data = false ) {
	// global $user;
	// if (!$username) {
		// $username = $user;
	// }
  $options = array(
    CURLOPT_RETURNTRANSFER => true,         // return web page
    CURLOPT_HEADER         => false,        // don't return headers
    CURLOPT_FOLLOWLOCATION => true,         // follow redirects
    CURLOPT_ENCODING       => "",           // handle all encodings
    CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:109.0) Gecko/20100101 Firefox/111.0",     // who am i
    CURLOPT_AUTOREFERER    => true,         // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
    CURLOPT_TIMEOUT        => 120,          // timeout on response
    CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
    CURLOPT_SSL_VERIFYHOST => 2,            // don't verify ssl
    CURLOPT_SSL_VERIFYPEER => true,        //
    CURLOPT_VERBOSE        => 0             //
  );
  $ch = curl_init($url);
  curl_setopt_array($ch,$options);
	if ($curl_data != null) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $curl_data);
	}

	if ($header_data) {
		// curl_setopt($ch, CURLOPT_HTTPHEADER, implode("\n",$header_data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header_data);
	}

  curl_setopt( $ch, CURLOPT_COOKIEJAR,  'csbot.cookies');
  curl_setopt( $ch, CURLOPT_COOKIEFILE, 'csbot.cookies' );
		
  $ret['content'] = curl_exec($ch);
  $ret['err']     = curl_errno($ch);
  $ret['errmsg']  = curl_error($ch) ;
  $ret['header']  = curl_getinfo($ch);
  curl_close($ch);
  //  $header['errno']   = $err;
  //  $header['errmsg']  = $errmsg;
  //  $header['content'] = $content;
  return $ret;
}


if (!$kcache = json_decode(file_get_contents('kcache.json'),true)) {
	$kcache = [];
}

if (!$playlists = json_decode(file_get_contents('playlists.json'),true)) {
	$playlists = [];
}

// curl 'http://vpc.local:8080/jsonrpc?FileCollection' \
  // -X POST \
  // -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:139.0) Gecko/20100101 Firefox/139.0' \
  // -H 'Accept: application/json, text/javascript, */*; q=0.01' \
  // -H 'Accept-Language: en-US,en;q=0.5' \
  // -H 'Accept-Encoding: gzip, deflate' \
  // -H 'Content-Type: application/json;charset=UTF-8' \
  // -H 'X-Requested-With: XMLHttpRequest' \
  // -H 'Origin: http://vpc.local:8080' \
  // -H 'Connection: keep-alive' \
  // -H 'Referer: http://vpc.local:8080/' \
  // -H 'Priority: u=0' \
  // --data-raw '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1747832073869","params":{"directory":"smb://192.168.12.100/E/music/","media":"music","properties":["title","file","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}'
function cacheKdir($path,$name) {
  global $_Kodi;
  global $kodi;
  global $kcache;

	if (!$kcache = json_decode(file_get_contents('kcache.json'),true)) {
		$kcache = [];
	}

	if (isset($kcache[$path]) && isset($kcache[$path]['content']['result']) && isset($kcache[$path]['content']['result']['files'])) { // && ((new DateTime('UTC')->format('U') - $kcache[$path]['t']) < 172800) ) {
		return $kcache[$path]['content'];
	}
	//$tdiff = (new DateTime('UTC')->format('U') - $kcache[$path]['t']);
	// var_dump($tdiff); exit;
	$IP = $kodi['httpIP'];
	$dpath = addcslashes($path,'\\');

	// $data = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","resume","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
	//$data = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"173869","params":{"directory":"'.$path.'","media":"'.$name.'","properties":["title","file","mimetype","thumbnail","dateadded"]}}'	;
						$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";
	$mdata = '{"jsonrpc":"2.0","id":"6446","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"]}}';
//	$data = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"'.$media.'","properties":["title","file","resume","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
	$data = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
	$gdata = (startsWith('smb://192.168.12.100/E/music/',$path))?$mdata:$data;

	$foo = 'multipath://smb%3a%2f%2f192.168.12.100%2fC%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fE%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fD%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fF%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fM%2ftv%2f/%2fvar%2fdata%2fmedia%2ftv%2f/';
				 // "multipath://smb%3a%2f%2f192.168.12.100%2fC%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fE%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fD%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fF%2ftv%2f/smb%3a%2f%2f192.168.12.100%2fM%2ftv%2f/%2fvar%2fdata%2fmedia%2ftv%2f/"
	// var_dump($IP,$data,$path,$dpath,$name);
	// function curl( $url,$curl_data = null,$header_data = false ) {
	if (!($res = $curl = curl("http://$IP/jsonrpc?request=" . urlencode($gdata),null)) || (!is_array($curl['content']) && !($res = json_decode($curl['content'],true)))) {
		var_dump($curl,$res,json_decode($data,true));
		return "cannot decode response";
	}
	// $content = json_decode($res['content'],true);
	if (!isset($res['result'])) {
		var_dump($data,$mdata,$gdata,$res,$path,$foo); exit;
	}
	$content = $res; //['content'];
	$kcache[$path] = ['content' => $content, 'name' => $name, 'path' => $path, 't' => new DateTime('UTC')->format('U')];
	$kcache['names'][$path] = $name;
	
	file_put_contents('kcache.json',json_encode($kcache, JSON_PRETTY_PRINT));
	// var_dump($res, $content, $curl,json_decode($data,true),$path,$dpath);
	// exit;
	return $content; 
}

// function getLineWithString($lines, $str) {
    // if (is_string($lines)) {
			// $lines = explode("\n",$lines);
		// }
    // if (!is_array($lines)) {
			// $lines = [$lines];
			// var_dump($lines,$str);
		// }
    // foreach ($lines AS $lineNumber => $line) {
        // if (strpos($line, $str) !== false) {
            // return $line;
        // }
    // }
    // return -1;
// }

function percentage($partialValue, $totalValue, $round = true) {
  $val = ($partialValue / $totalValue)*100; //*100);
	$rval = $val;
	if ($round) { $rval = round($val); }
	if ($rval == 100 && $val != 100 && $round) {
		return $val;
	} else if ($round) {
		return $rval;
	} else {
		return $val;
	}
}

$acct = [];
$timezone = "UTC";

function duration($odate, $cdate) {
	$extraday = 0;
	if ($odate > $cdate) {$extraday = 1; }
	$start_date = new DateTime($odate);
	$end_date = new DateTime($cdate);
	$end_date->modify("+$extraday day");
	$since_start = $start_date->diff($end_date);
	$minutes = $since_start->h * 60;
	$minutes += $since_start->i;
	return $minutes;
}

function human_time_diff($time,$now = '',$cprefix = false) {
		global $timezone;
		$return = [];
		date_default_timezone_set($timezone);
		if ($now == '') { 
			$now = new \DateTime();
		} else if (is_string($now)) { 
			$now = new \DateTime($now); 
		}
		if (is_string($time)) { 
			$time = new \DateTime($time); 
		}
		$when = $time->format('Y-m-d');
		$interval = $now->diff($time);

		$yesterday = new \DateTime();
		$yesterday = $yesterday->sub(new DateInterval('P1D'))->format('Y-m-d');
		$tomorrow = new \DateTime();
		$tomorrow = $tomorrow->add(new DateInterval('P1D'))->format('Y-m-d');
		$yestermorrow = '';
		if ($yesterday == $when) {$yestermorrow = 'yesterday '; }
		if ($tomorrow == $when) {$yestermorrow = 'tomorrow '; }

		$prefix = '';
		$suffix = '';
			// $interval = date_create('now')->diff( $datetime );
			if ($cprefix) { $prefix = $cprefix; } else 
			{
				$suffix = ( $interval->invert ? ' ago' : '' );
				$prefix = ( $interval->invert ? '' : 'in ' );
			}
			if ( $v = $interval->y >= 1 ) $return[] = pluralize( $interval->y, 'year' );
			if ( $v = $interval->m >= 1 ) $return[] = pluralize( $interval->m, 'month' );
			if ( $v = $interval->d >= 1 ) $return[] = pluralize( $interval->d, 'day' );
			if ( $v = $interval->h >= 1 ) $return[] = pluralize( $interval->h, 'hour' );
			if ( $v = $interval->i >= 1 || count($return) == 0 ) $return[] = pluralize( $interval->i, 'minute' );
			//$return[] = pluralize( $interval->i, 'minute' );
			// $return[] = pluralize( $interval->s, 'second' );
			return $yestermorrow.$prefix.niceList($return).$suffix;
	}

function pluralize( $count, $text ) {
		return $count . ( ( $count == 1 ) ? ( " $text" ) : ( " ${text}s" ) );
	}

function reducearray($array) {
	foreach ($array AS $data => $index) {
		$index = array_intersect_key($index, array_unique(array_map('serialize', $index)));
		$narray[$data] = $index;
	}
	return $narray;
}

$edataset = [];

require("phpKodi-api.php");

function cacheYTNames($dir) {
	if ($kerr = kodiError($dir)) { return $kerr; }

	file_put_contents('ytnames.json',json_encode($dir, JSON_PRETTY_PRINT));
	global $_Kodi;
	global $ytmap;
	$ndir = ['ytparsed'=>true];
	$page = 1;
	$vcount = 0;
	$nextpage = false;
	while (($vcount < 400 && $page < 4) && !isset($dir['error'])) {
 		$files = $dir;
		if (isset($dir['result'])) {
			$files = $dir['result']['files'];
		} else	if (isset($dir['files'])) {
			$files = $dir['files'];
		}
		if (!is_object($files) && !is_array($files)) {
			$files = [$files];
		}
		foreach ($files AS $key => $item) {
			if (is_string($item)) {
				var_dump("ITEM IS STRING",$item,$files); return $item;
			}
			$filename = $item['file'];
			$type = $item['filetype'];
			$file = false;
			if (isset($item['mediapath'])) {
				$file = $item['mediapath'];
			}
			if (!$file) { $file = $filename; }
			$file = stripslashes($file);
			$artist = [];
			if (isset($item['artist'])) {
				$artist = $item['artist'];
			}
			$label = $item['label'];
			$title = $item['title'];
			$name = $title;
			if (!$name) { $name = $label; }
			if (!$name) { $name = $filename; }
			$name = kodiTitle($name,$artist,$file);
	
			if ($type == 'directory' && startsWith($item['title'],"Next page") && startsWith($file,'plugin://plugin.video.youtube/kodion/search/query')) {
				$nextpage = $file;
			} else if ($type == 'directory') {
				$ndir[] = $item;
			}
			if (startsWith($file,'plugin://plugin.video.youtube/play/?video_id=')) {
				$ndir[] = $item;
				if ($type == 'file') {
					$vid = explode('=',$file)[1];
					$ytmap[$vid] = $name;
					$vcount++;
				}
			}
		}
		$page++;
		if (!$nextpage) {
			break;
		}
		$npath = $nextpage;
		$json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1743456435344","params":{"directory":"'.$npath.'","media":"video","properties":["title","file","artist","duration","runtime","playcount","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
		$dir = $_Kodi->sendJson($json);
		var_dump('page vcount',$page,$vcount);
	}
	file_put_contents('ndir.json',json_encode($ndir, JSON_PRETTY_PRINT));
	file_put_contents('ytmap.json',json_encode($ytmap, JSON_PRETTY_PRINT));
	var_dump($ytmap);
	return $ndir;
}

function searchForKey($id, $array, $col,$which = 'last') {
	$foundKey = false;
	$keys = [];
	foreach ($array as $key => $val) {
		if ($val[$col] === $id) {
			$foundKey = $key;
			$keys[] = $key;
			if ($which == 'first') { return $foundKey; }
		}
	}
	if ($which == 'all') { return $keys; }
	return $foundKey;
}

$tidy = true;
$kodi = [];
$kodi['plid'] = 0;
$kodi['qlmode'] = null;
$kodi['autoq'] = "queue";
$kodi['dj'] = false;
$kodi['djdata'] = [];
$kodi['noq'] = false;
$kodi['paths'] = null;
$kodi['sources'] = null;
$kodi['hist'] = ['sources'];
// $kodi['uhist'] = [];
$kodi['playrandom'] = false;
$kodi['queuerandom'] = false;
$kodi['queuelist'] = null;
$kodi['qindex'] = [null,null];
$kodi['playing'] = null;
$kodi['playfile'] = null;
$kodi['playfilename'] = null;
$kodi['playpic'] = null;
$kodi['vol'] = [false,0];
$kodi['autoplay'] = false;
$kodi['IP'] = 'mikey:8080';
$kodi['httpIP'] = 'mikey:8080';
$kodi['wsIP'] = 'mikey:9090';
$kodi['tmppaths'] = false;
$kodi['resumeData'] = false;
$kodi['shuffle'] = [null,null];
$kodi['repeat'] = [null,null];

if (!$kodi['uhist'] = json_decode(file_get_contents('uhist.json'),true)) {
	$kodi['uhist'] = [];
	file_put_contents('uhist.json',json_encode($kodi['uhist'], JSON_PRETTY_PRINT));
}

if (!$kodi['gvar'] = json_decode(file_get_contents('gvarf.json'),true)) {
	$kodi['gvar'] = [];
	//file_put_contents('uhist.json',json_encode($kodi['uhist'], JSON_PRETTY_PRINT));
}

if (!$kodi['menu'] = json_decode(file_get_contents('menu.json'),true)) {
	$kodi['menu'] = [];
} else {
	$kodi['path'] = $kodi['menu']['path'];
	$kodi['autoplay'] =	!(!$kodi['menu']['autoplay']);
	if ($kodi['autoplay'] == 'stop') {
		$kodi['autoplay'] = true;
	}
	$kodi['plmode'] =	$kodi['menu']['mode'];
	$kodi['autoq'] = $kodi['menu']['autoq'];
	if (!isset($kodi['autoq'])) { $kodi['autoq'] = "queue"; }
}	

function kodiTitle($name,$artist = [],$file = false) {
	if (is_array($artist) && count($artist)) {
		$artist = preg_replace("/ - Topic$/",'',$artist[0]);
		if ($artist) {
			$name = str_replace($artist,'',$name);
		} else {
			$artist = false;
		}
	} else {
		$artist = false;
	}
	$name = trim(preg_replace("/^ - /",'',$name));
	$name = str_replace(['[B]','[/B]'],'**',$name);

	$vid = false;
	if ($file && startsWith($file,'plugin://plugin.video.youtube/play/?video_id=')) {
		$vid = explode('=',$file)[1];
	}
	if ($vid) { 
		preg_match("/\\\u[0-f].../",trim(json_encode($name),'"'),$matches);
		if (count($matches) || strpos($name,':')) {
			$name = " $name [$vid](<https://www.youtube.com/watch?v=$vid>)";
		} else {
			$name = " [$name](<https://www.youtube.com/watch?v=$vid>)";
		}
	}

	if ($artist) {
		$name = '['.$artist.']'.$name;
	}
	$name = trim(preg_replace("/(\|\|)/",' | ',$name));
	return $name;	
}

function sourcePather($path) {
	global $kodi;
	$opaths = $paths = $kodi['npaths'];
	for ($i = 0; $i < 5; $i++) {
		$paths = array_map('urldecode',$paths);
		$path = urldecode($path);
  }
	$npath = $opaths[array_keys(preg_grep('%'.$path.'%',$paths))[0]];
	if (!$npath) { $npath = false; }
	return $npath;
}

function secsToTimeArray($secs,$assoc = false) {
	$newtime = explode(':',gmdate("H:i:s", $secs));
	if ($assoc) { return ['hours'=>intval($newtime[0]),'minutes'=>intval($newtime[1]),'seconds' => intval($newtime[2]),'milliseconds'=>00]; }
	return $newtime;
}

function usortLabel($a, $b) { return (preg_replace('/[^a-z\d ]/i','',$a['filetype'].strtolower($a['label'])) <=> preg_replace('/[^a-z\d ]/i','',$b['filetype'].strtolower($b['label']))); }

function renderDir($dirs,$curpath = null,$curitem = null,$paginate = false) {

	if ($path = sourcePather($curpath)) {
		// $json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["video"],"id":1}'; 
		$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";
		$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"]}}';
		//$json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["video"],"id":1}'; 
		global $_Kodi;
		$dirs = $_Kodi->sendJson($json);
		$curpath = $path;
	}


	if (!$dirs || !is_array($dirs) || !count($dirs)) {
		var_dump('error DIRS NOT ARRAY',$dirs,$curpath);
		$fcurpath = str_ireplace('smb://192.168.12.100/','',urldecode($curpath));
		$output = "0 results. Path: $fcurpath \n";
		return $output;
	}

	global $kodi;
	global $nums;
	global $lastStatusData;
	global $ytmap;
	global $plmodeIcons;

	if ($kerr = kodiError($dirs)) { return $kerr; }
	
	// $did = false;
	$did = getDid($paginate) ?? false;
	if (!$did) {
		if (isset($paginate['user_id'])) {
			$did = $paginate['user_id'];
		} else if (isset($paginate['author']['id'])) { 
			$did = $paginate['author']['id'];
		} else if (isset($lastStatusData['user_id'])) {
			$did = $lastStatusData['user_id'];
		} else if (isset($lastStatusData['author']['id'])) {
			$did = $lastStatusData['author']['id'];	
		} else if (isset($kodi['menu']['did']) && $kodi['menu']['did']) {
			$did = $kodi['menu']['did'];
		}
	}
	

	if ($curitem !== null) { 
		$curitem = stripslashes($curitem); 
	}	else {
		$curitem = kodiCurItem();
	}

	$files = $dirs;
	if (isset($dirs['result'])) {
		$files = $dirs['result']['files'];
	} else	if (isset($dirs['files'])) {
		$files = $dirs['files'];
	}
	$kodi['plmode'] = "files";
	$fcurpath = $curpath;

	if (is_string($curpath) && startsWith($curpath,'plugin://plugin.video.youtube/')) {
		$kodi['plmode'] = "yt";
		if (!isset($files['ytparsed'])) {
			if (!$files = cacheYTNames($files)) { return "E4744"; }
			unset($files['ytparsed']);
		}

		if (startsWith(stripcslashes($curpath),'plugin://plugin.video.youtube/kodion/search/query/?q=')) {
			$fcurpath = trim(pathinfo($curpath)['filename'],'\\');
			if (isset($purl)) { unset($purl); }
			parse_str($fcurpath,$purl);
			$purl = array_map('ucwords',$purl);
			// var_dump($label, $purl); exit;
			$s = (isset($purl['?q']))?": **".$purl['?q'].'**':'';
			$t = (isset($purl['type']))?" ".$purl['type']:'';
			$fcurpath = "Youtube$t Search$s";
			// if (preg_match('/\?q=(.*)&type=(\w.*)/',$curpath,$m)) {
			// $t = ucfirst($m[2]);
			// $s = urldecode($m[1]);
			// $label = $file;
			// if (startsWith($curpath,'plugin://plugin.video.youtube')) {
			// if (preg_match('/\?q=(.*)&type=(\w.*)/',$label,$m)) {
			// $t = ucfirst($m[2]);
			// $s = urldecode($m[1]);
			// $name = "Youtube $t Search: $s";
			// }

			// if (preg_match('/\?q=/',$fcurpath,$m)) {
		}
	}
	
	if (isset($files['ytparsed'])) {
		unset($files['ytparsed']);
	}

	$dirs = $files;
	if ($curpath == 'bookmarks') {
		$kodi['plmode'] = "bookmarks";
	}

	$highlight = false;
	
	if (startsWith($curpath,'uplay:')) {
		$kodi['plmode'] = "bookmarks";
		list($uplay,$udid) = $upath = explode(':',$curpath);
		if (!isset($upath[2])) {
			$fcurpath = "Playlists for <@$udid>";
		} else {
			$pln = ucwords($upath[2]);
			$fcurpath = "<@$udid>: $pln";
		}
	}
	
	if (startsWith($curpath,'uhist:')) {
		$kodi['plmode'] = "history";
		$dirs = array_reverse($dirs);
		$fcurpath = "Watch History for <@".explode(':',$curpath)[1].'>';
	}
	
	if (startsWith($curpath,'search%3A')) {
		$sarray = explode(':',urldecode($curpath));
		$sarray = array_splice($sarray,1);
		$smode = array_shift($sarray);
		$swords = implode(':',$sarray);
		$swordsarr = preg_split('/"([^"]*)"|\h+/', $swords, -1, 
                   PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
		$sarr = array_map(fn($v)=> ("**$v**"), $swordsarr);
		$fcurpath = $plmodeIcons['search'].$plmodeIcons[$smode].": ".niceList($sarr);
		$highlight = implode('|',$swordsarr);
		$kodi['plmode'] = "search";
	}
	if ($curpath == 'favs') {
		$fcurpath = $plmodeIcons['favs']." Favorites";
		$kodi['plmode'] = "favs";
	}
	if ($curpath == 'music' || startsWith($curpath,'smb://192.168.12.100/E/music/')) {
		$kodi['plmode'] = "music";
	}

	if (	$kodi['plmode'] == 'files') {
		
		if ($curpath !== 'sources') {
			usort($dirs,'usortLabel');
			file_put_contents('dirfiles.json',json_encode($dirs, JSON_PRETTY_PRINT));
		}
	}

	if ($curpath === $kodi['npaths']['tv']) {
		$fcurpath = "TV Shows";
	}
	if ($curpath === $kodi['npaths']['movies']) {
		$fcurpath = "Movies";
	}

	$play = '▶';
	$pause = '⏸';
	
	$kodi['path'] = $curpath;

	// if (!count($kodi['hist']) || $kodi['hist'][array_key_last($kodi['hist'])] !== $curpath) {

	//if ($did && !in_array($curpath,array_column($kodi['uhist'][$did],0))) {
	if (!$kodi['playrandom'] && !$kodi['queuerandom'] && !startsWith($curpath,'uhist:') && $did ) {
		if (!isset($kodi['uhist'][$did])) { // || !in_array($selected[1],array_column($kodi['uhist'][$did],1)))) {
			$kodi['uhist'][$did] = [];
		}

		$uh = $kodi['uhist'][$did];
		//$uh = arrayfilter($uh,fn($k,$o)=>$o['filetype'] == 'directory');
		$uh = arrayfilter($uh,fn($k,$o)=> isset($o['filetype']) && $o['filetype'] == 'directory');

		if (!isset($kodi['uhpointer'][$did]) || !$kodi['uhpointer'][$did]) {
			$kodi['uhpointer'][$did] = array_key_last($uh);
		}
		$kodi['uhpointer'][$did] = (string)$kodi['uhpointer'][$did];
		
		file_put_contents('uh3.json',json_encode([$kodi['uhpointer'][$did],$uh], JSON_PRETTY_PRINT));
		if (isset($kodi['uhpointer'][$did])) {
			$uhp = $kodi['uhpointer'][$did];
				// while ($uhk == null && $uhp > -1) {
				// $uhp--;
				// $uhk = getPrevKey($uhp, $uh);
				// }
				//$kodi['uhpointer'][$did] = $uhp;
			if (!$uh[$uhp]) {
				$uhp = (string) array_key_last($uh);
			}
			$entry = $uh[$uhp];
			$uhpath = $entry['file'];
			if ($uhpath !== $curpath) {
					//	}
			//		}
				$label = trim(pathinfo($curpath)['filename'],'\\');
				if (startsWith($curpath,'plugin:\/\/plugin.video.youtube\/kodion\/search\/query\/?q=')) {
					if (preg_match('/\?q=',$curpath,$m)) {
						if (isset($purl)) { unset($purl); }
						parse_str($curpath,$purl);
						$purl = array_map('ucwords',$purl);
						$s = (isset($purl['?q']))?": ".$purl['?q']:'';
						$t = (isset($purl['type']))?" ".$purl['type']:'';
						$label = "Youtube$t Search$s";
					}
				}
				
				$direntry =	[
							"filetype"=> "directory",
							"file"=> $curpath,
							"label"=> $label,
							"mimetype" => "x-directory/normal",
							"thumbnail" => "",
							"title" => "",
							"type" => "unknown"
					];

				
				$key = Date('U'). rand(100,999);
				// $kodi['uhist'][$did][$key] = $selected[5];
				// $kodi['uhist'][$did][$key]['key'] = $key;

				$kodi['uhist'][$did][$key] = $direntry;
				$kodi['uhpointer'][$did] = $key;
				$uhist = $kodi['uhist'][$did];
				$kodi['uhist'][$did] = array_reverse(array_map("unserialize", array_unique(array_map("serialize", array_reverse($uhist)))));
				file_put_contents('uhistnofilt.json',json_encode($uhist, JSON_PRETTY_PRINT));
				unset($uhist);
				file_put_contents('uhist.json',json_encode($kodi['uhist'], JSON_PRETTY_PRINT));
			}
		}
	} else {
		var_dump('||||||||||||||||||!!!!!!!!!!!!!!!!!!!!!!!!',$paginate, $lastStatusData);
	}

	if (!$paginate) { $paginate = true; }
	$kodi['hist'][] = $curpath;
	// }
	$kodi['hist'] = array_unique($kodi['hist']);
	// if ($curpath == "sources" || $curpath == '' && $dirs[0]['label'] == "Video add-ons") { $paginate = true;array_shift($dirs); }
	if ($curpath == "sources") {
		$dirs = array_filter($dirs,function($x) {return !startsWith($x['file'], "addons://sources/");});
		//&& $dirs[0]['label'] == "Video add-ons") { $paginate = true;array_shift($dirs); }
	}
	$olddid = $kodi['menu']['did'];
	$oldpath = $kodi['menu']['path'];
	file_put_contents('oldmenu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));
	$kodi['menu'] = ['did' => $olddid,'oldpath' => $oldpath,'path' => $curpath,'autoplay' => $kodi['autoplay']];

	
	$page = 0;
	if ($paginate) {
			// if (startsWith($curpath,'search%3A')) {
			// $curpath = urldecode
			// }
		$fcurpath = str_ireplace('smb://192.168.12.100/','',urldecode($fcurpath));

		// var_dump($curpath,preg_match("/^multipath(?:\S+\\/tv\\/|\S+\\/media\\/tv\\/)/",$curpath));exit;
		if (preg_match("/^multipath(?:\S+\\/tv\\/|\S+\\/media\\/tv\\/)/",$fcurpath)) {
			$fcurpath = "TV Shows";
		}
		if (preg_match("/^multipath(?:\S+\/movies\/|\S+\/media\/movies\/)/",$fcurpath)) {
			$fcurpath = "Movies";
		}

		$output[$page] = count($dirs)." results. Path: $fcurpath \n";
	} else {
		$output = '';
	}
	$ic = 1;
	$ick = 0;
	foreach ($dirs AS $key => $item) {
		if ($item === null) {
			var_dump("NULL IN RENDER DIR");
		}
			
		$filename = $item['file'];
		$file = false;
		if (isset($item['mediapath'])) {
			$file = $item['mediapath'];
		}
		if (!$file) { $file = $filename; }
		$artist = [];
		if (isset($item['artist'])) {
			$artist = $item['artist'];
		}
		$title = (isset($item['title']))?$item['title']:false;
		$name = $item['label'];
		if (!$name && $title) { $name = $title; }
		if (!$name) { $name = $filename; }
		$vid = false;
		$file = stripslashes($file);


		if ($kodi['plmode'] == 'history') {
			if (startsWith($name,'uplay:')) {
				list($uplay,$udid) = $upath = explode(':',$name);
				if (!isset($upath[2])) {
					$name = "Playlists for <@$udid>";
				} else {
					$pln = ucwords($upath[2]);
					$name = "<@$udid>: $pln";
				}
			}

			if (isset($item['page'])) {
				$name .= " (Page ".$item['page'].")";
			}
			if (startsWith($name,'search%3A')) {
				$name = explode(':',urldecode($name));
				$sarray = array_splice($name,1);
				$smode = array_shift($sarray);
				$swords = implode(':',$sarray);
				$name = $plmodeIcons['search'].$plmodeIcons[$smode].": ** $swords ** ";
			} else if (startsWith($name,'?q=') || startsWith($file,'plugin://plugin.video.youtube/kodion/search/query?q=')) {
				$label = $file;
				if (startsWith($label,'plugin://plugin.video.youtube')) {
					$label = trim(pathinfo($label)['filename'],'\\');
				}
				// if (preg_match('/\?q=(.*)&type=(\w.*)/',$label,$m)) {
					// $t = ucfirst($m[2]);
					// $s = urldecode($m[1]);
					// $name = "Youtube $t Search: $s";
				// }

				if (preg_match('/\?q=/',$label,$m)) {
					if (isset($purl)) { unset($purl); }
					parse_str($label,$purl);
					$purl = array_map('ucwords',$purl);
					// var_dump($label, $purl); exit;
					$s = (isset($purl['?q']))?": ".$purl['?q']:'';
					$t = (isset($purl['type']))?" ".$purl['type']:'';
					$name = "Youtube$t Search$s";
				}


			} else {
				$name = str_ireplace(['/var/data/media/','smb://192.168.12.100/'] ,'',urldecode($name));
			}
		}
		
		if ($name == 'favs') {
			$name = $plmodeIcons['favs']." Favorites";
		}
		
		$name = kodiTitle($name,$artist,$file);

		$path = stripslashes($file);
		if (!isset($item['filetype'])) {
			var_dump('error no filetype',$item);
			if ($path) { $item['filetype'] = (endsWith($path,'/'))?"directory":"file"; }

		}			
		$type = ($curpath == 'sources')?'directory':$item['filetype'];
		$pic = (isset($item['thumbnail']))?$item['thumbnail']:false;
		//$pic = $item['thumbnail'];
		if (!$name) { $name = $path; }
		$watchedbool = false;
		$watched = '🔲';
		
		
		if (startsWith($file,'plugin://plugin.video.youtube/play/?video_id=')) {
			$ndir[] = $item;
			if ($type == 'file') {
				$vid = explode('=',$file)[1];
				$ytmap[$vid] = $name;
			}
		}
	
		if (isset($item['playcount']) && $item['playcount'] == 1) {
			$watchedbool = true;
			$watched = "✅";
		}
		$totaltime = '';
		if (isset($item['totaltime']))	{
			$totaltime = json_encode($item['totaltime']);
		}
		$dur = '';
		if (isset($item['runtime']) && $item['runtime'])	{
			$totaltime = secsToTimeArray($item['runtime']);
			$dur = gmdate("H:i:s", $item['runtime']);
		}
		$resume = 0;
		if (isset($item['resume']) && isset($item['resume']['position']) && is_numeric($item['resume']['position']) && intval($item['resume']['position']) > 0)	{
			var_dump('777777777777777777',$item['resume']['position']);
			$watched = $pause;
			if (!$dur && isset($item['resume']['total'])) {
				$totaltime = secsToTimeArray($item['resume']['total']);
				$dur = gmdate("H:i:s", $item['resume']['total']);
			}
			$resume = $item['resume']['position'];
			if ($dur) { 
				$dur = gmdate("H:i:s", $resume)." / ".$dur; 
			}	else { $dur = gmdate("H:i:s", $resume); }
		}
		if ($type == 'directory') {
			$watched = '📁';
		}
		if ($path == $curitem) {
			$watchedbool = 'playing';
			if (!$kodi['playing']) {
				$kodi['playing'] = $key;
			}
			$watched = $play;
		}
		
		$code = '';

		// $chan = (isset($item['channel']))?": <".$item['channel'].">":':';
		$chan = ":"; //(isset($item['channel']))?": <".$item['channel'].">":':';

		$hname =  $name;
		if ($highlight) {
			// $hname = str_replace('****','** **',preg_replace("/($highlight)/i","**$1**", $name));
			$hname = str_replace('****','',preg_replace("/($highlight)/i","**$1**", $name));
		}
		if ($paginate) {
			if ($ic >= 11) { $page++; $ic = 1;
				$code = $nums[$ic];
				$output[$page] = "$code $watched$chan $hname $dur\n";
			} else {
				$code = $nums[$ic];
				$output[$page] .= "$code $watched$chan $hname $dur\n";
			}
		} else {
			$output .= "$watched$ick: $hname $dur\n";
		}
		if (is_array($totaltime)) {$totaltime = json_encode($totaltime); }
		$kodi['menu'][$ick] = [$type,$path,$watchedbool,$name,intval($resume),$totaltime,$code,[$page,$ic],$item];
		$ic++;
		$ick++;
	}
	$kodi['menu']['path'] = $curpath;
	$kodi['menu']['mode'] = $kodi['plmode'];
	file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));
	file_put_contents('ytmap.json',json_encode($ytmap, JSON_PRETTY_PRINT));
	return $output;
}

$nums = ["0️⃣","1️⃣","2️⃣","3️⃣","4️⃣","5️⃣","6️⃣","7️⃣","8️⃣","9️⃣","🔟"];

$ssaveron = false;

function renderQueue($items,$curitem = null) {
	$play = '▶';
	global $ytmap;
	global $kodi;
	global $_Kodi;

	file_put_contents('queueritems.json',json_encode($items, JSON_PRETTY_PRINT));
	
	if (!isset($items['music'])) {
		$nitems = [];
		$nitems['videos'] = $items;
		// $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"]}}';
		$json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":0,"properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"]}}';
		$nitems['music'] = $_Kodi->sendJson($json);
		$items = $nitems;
		unset($nitems);
	}

	if (!isset($items['videos']['result']) && !isset($items['music']['result'])) {
		var_dump($items,'queue render ERROR');
		return false;
	}

	$plid = (isset($kodi['plid']))?$kodi['plid']:activePlayer();
	$nitems = [];
	$plid = 0;
	$offset = [0,0];
	if ($plid == 0) {
		if (isset($items['music']['result']['items'])) {
			$nitems[] = "music";
			$offset = [0,count($items['music']['result']['items'])];
			$nitems = array_merge($nitems,$items['music']['result']['items']);
		}
		if (isset($items['videos']['result']['items'])) {
			$nitems[] = "videos";
			$nitems = array_merge($nitems,$items['videos']['result']['items']);
		}
	} else {
		if (is_array($items['videos']['result']['items']) && count($items['videos']['result']['items'])) {
			$nitems[] = "videos";
			$plid = 1;
			$offset = [1,count($items['videos']['result']['items'])];
			$nitems = array_merge($nitems,$items['videos']['result']['items']);
			// $nitems += $items['videos']['result']['items'];
		}
		if (isset($items['music']['result']['items']) && count($items['music']['result']['items'])) {
			$nitems[] = "music";	
			$nitems = array_merge($nitems,$items['music']['result']['items']);
			// $nitems += $items['music']['result']['items'];
		}
	}
	$items = $nitems;
	unset($nitems);
	file_put_contents('queuecitems.json',json_encode($items, JSON_PRETTY_PRINT));
	
	$kodi['plmode'] = "queue";
	
	$kodi['hist'][] = "queue";
	$kodi['hist'] = array_unique($kodi['hist']);
	
	if (!isset($did) || !$did) { $did = getDid(); }
	if ($did) { // || !in_array($selected[1],array_column($kodi['uhist'][$did],1)))) {
		if (!isset($kodi['uhist'][$did])) { // || !in_array($selected[1],array_column($kodi['uhist'][$did],1)))) {
			$kodi['uhist'][$did] = [];
		}
		// $kodi['uhist'][$did][] = [...$selected];
		$key = Date('U'). rand(100,999);

			$direntry =	[
            "filetype"=> "directory",
            "file"=> 'queue',
            "label"=> 'Player Queue',
            "mimetype" => "x-directory/normal",
            "thumbnail" => "",
            "title" => "",
            "type" => "unknown"
        ];

		if ($kodi['uhist'][$did][array_key_last($kodi['uhist'][$did])]['file'] === 'queue') {
			var_dump("skipping dupe uhist entry for queue");
		} else {
			$kodi['uhist'][$did][$key] = $direntry;
		}
//		$kodi['uhist'][$did][$key]['key'] = $key;
		file_put_contents('uhist.json',json_encode($kodi['uhist'], JSON_PRETTY_PRINT));
	}
	
	
	
	$kodi['menu'] = $kodi['queue'] = [];

	$output = "";

	if (!count($items)) {
		// $fcurpath = str_ireplace('smb://192.168.12.100/','',urldecode($curpath));
		$output .= "0 results.";
	}

	// var_dump($ytmap);
	$ick = 0;
	$lines = [];
	$page = 0;
	global $nums;
	foreach ($items AS $key => $item) {
			// $offset = [1,count($items['music']['result']['items'])];
			// $queueindex = 
		if ($item == 'music') {
			$plid = 0;
			$line = " **__Music Queue List__** \n";
			// $line = "$code $watched: $name \n";
			if (!isset($lines[$page])) {
				$lines[$page] = $line;
			} else {
				$lines[$page] .= $line;
			}
			continue;
		} else if ($item == 'videos') {
			$plid = 1;
			$line = " **__Video Queue List__** \n";
			if (!isset($lines[$page])) {
				$lines[$page] = $line;
			} else {
				$lines[$page] .= $line;
			}
			continue;
		} 
		$pkey = $ick;
		if ($pkey >= $offset[1]) {$pkey -= $offset[1]; }
		$queueindex = array_merge([$plid,$key,$pkey],$offset);
		if (isset($item['mediapath']) && $item['mediapath']) {
			$path = $item['mediapath'];
		} else {
			$path = $item['file'];
		}
		$path = stripslashes($path);

		var_dump('AAAAAAAAAAAAAAAAAAAAA',$key,$ick,$curitem,$path);

		$name = $item['title'];
		$label = $item['label'];
		if (!$name) {	$name = $item['label'];	}
		if (!$name) {	$name = $item['file'];	}
		if (startsWith($path,'plugin://plugin.video.youtube/play/?video_id=') ) {
			$vid = explode('=',$path)[1];
			if (isset($ytmap[$vid])) {
				$name = $ytmap[$vid];
			}
		}
		$pic = $item['thumbnail'];
		$watchedbool = false;
		$watched = '🔲';
		if (isset($item['playcount']) && $item['playcount'] == 1) {
			$watchedbool = true;
			$watched = "✅";
		}
		if ($path == $curitem) {
			$watchedbool = 'playing';
			$watched = $play;
		}
		list($page,$pick) = explode('.',number_format(($ick/10.0),1)); 
		$code = $nums[$pick+1];
		
		$kodi['menu'][$ick] = ['file',$path,$watchedbool,$name,false,false,$code,[$plid,$pkey],$item];

		$kodi['queue'][$ick] = ['file',$path,$watchedbool,$name,$pic,[$plid,$pkey]];
		
		$line = " $watched$ick [$plid,$pkey]: $name \n";
		$output .= $line;


		$line = " $code $watched: $name \n";
		if (!isset($lines[$page])) {
			$lines[$page] = $line;
		} else {
			$lines[$page] .= $line;
		}
		$ick++;
	}

	// $kodi['menu'] = $kodi['queue'];
	$kodi['menu']['path'] = 'queue';
	$kodi['menu']['mode'] = $kodi['plmode'];
	file_put_contents('qlines.json',json_encode($lines, JSON_PRETTY_PRINT));
	file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));
	file_put_contents('queuemenu.json',$curitem."\n\n".json_encode($kodi['queue'], JSON_PRETTY_PRINT));
	file_put_contents('queueitems.json',json_encode($items, JSON_PRETTY_PRINT));
	if (!count($items)) {
		return [$output];
	}
	return $lines;
}

// function array_columns($array, $cols, $index = null) {




	// if (is_string($cols)) {
		// $cols = [$cols];
	// }
	// $arr = [];
	// $keys = [];
	// $keys = array_column($array,$index);
	// foreach($array AS $k => $v) {
		// $arrcol = [];
		// foreach($cols AS $col) {

		// }
		// $arr[] = $arrcol;
	// }
	
	// //array_combine(array_keys($arr),array_map('array_combine', $arr,array_fill(0,count($arr),$cols));
// //	return array_map(function($value,$key) {    return array_combine($key,$value) ;}, $moo,array_fill(0,count($moo),$cols)))}
// }

function filterArrayByKeyName($array, $keys, $index = false) {
  if (!is_array($array)) { return $array; }
	// if ($intKeys) { $array = intKeyArrayFilter($array); }
	if (is_string($keys)) {
		$keys = [$keys];
	}
	return array_filter($array, function($value,$key) use ($keys) { return ( in_array($key,$keys));  },ARRAY_FILTER_USE_BOTH );
}

function filterArrayByKeyValue($array, $key, $keyValue, $intKeys = false) {
  if (!is_array($array)) { return $array; }
	if ($intKeys) { $array = intKeyArrayFilter($array); }
	return array_filter($array, function($value,$k) use ($key, $keyValue, $intKeys) {
    // return ((!$intKeys || is_numeric($k)) && $value[$key] == $keyValue); 
    return ( $value[$key] == $keyValue); 
  },ARRAY_FILTER_USE_BOTH );
}

function getPrevKey($key, $hash = array()) {
    $keys = array_keys($hash); // Get all keys
    $found_index = array_search($key, $keys); // Find index of specified key

    if ($found_index === false || $found_index === 0) {
        return false; // Key not found or is the first key
    }

    return $keys[$found_index - 1]; // Return the previous key
}

function intKeyArrayFilter($array) {
  if (!is_array($array)) { return $array; }
	return array_filter($array, function($value, $key) {
    return (is_numeric($key)); 
  },ARRAY_FILTER_USE_BOTH);
}

$curpath = '';

function padInt($val) {
	return str_pad($val,2,'0',STR_PAD_LEFT);
}

function connectKodi($verbose = false) {
	global $_Kodi;
	global $kodi;
	$IP = $kodi['IP'];
	if ($verbose) {
		sendMsg('380675774794956800',  "Connecting to $IP...");
	}
	$_Kodi = new Kodi($IP);
	if (isset($_Kodi->error)) { 
		sendMsg('380675774794956800',  print_r($_Kodi->error,true));
	} else {
		kodiVol();
		kodiShuffle();
		kodi(false);
		if ($verbose) {
			sendMsg('380675774794956800',  "Connected to $IP \n".print_r($kodi['vol'],true));
		}
	}
}

function activePlayer($failout = false) {
	global $_Kodi;
	global $kodi;
	$rplid = (isset($kodi['plid']))?$kodi['plid']:1;
	if ($failout) {$rplid = -1; }
	$json = '{"jsonrpc":"2.0","method":"Player.GetActivePlayers","id":95646}';
	//if (($ret = $_Kodi->sendJson($json)) && isset($ret['result']['playerid'])) {
	$ret = $_Kodi->sendJson($json);
	if (isset($ret['result'][0]['playerid'])) {
		$kodi['plid'] = $rplid = intval($ret['result'][0]['playerid']);
		var_dump('plid set!!!! ',$ret,$kodi['plid']);
	} else {
		var_dump('no active players. plid not set',$ret,$rplid);
	}
	return $rplid;
}

function kodiShuffle($plids = null,$bool = 'get') {
	global $_Kodi;
	global $kodi;
	$aplid = activePlayer() ?? 0;
	if ($plids == 'active') {
		$plids = [$aplid ?? 0]; //[activePlayer() ?? 1];
	} else if ($plids == null) {
		$plids = [(isset($kodi['plid']))?$kodi['plid']:activePlayer()];
	} else if (is_string($plids) && $splids = 'all') {
		$plids = [0,1];
	} else if (!is_array($plids)) {
		$plids = [intval($plids)];
	}

	$gotten = [];
	foreach ([0,1] AS $plid) {
		if (!isset($kodi['shuffle'][$plid]) || $kodi['shuffle'][$plid] === null) {
			$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","shuffled","repeat","canshuffle"]],"id":11}';
			if (!($props = $_Kodi->sendJson($json)) || !isset($props['result']['shuffled'])) { continue; }
			$kodi['shuffle'][$plid] = $props['result']['shuffled'];
			$gotten[$plid] = true;
			var_dump('initial set| shuffle, $plid',$kodi['shuffle'][$plid],$plid); //,$props);
		}
		if (!isset($kodi['repeat'][$plid]) || $kodi['repeat'][$plid] === null) {
			$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","shuffled","repeat","canshuffle"]],"id":11}';
			if (!($props = $_Kodi->sendJson($json)) || !isset($props['result']['repeat'])) { continue; }
			$kodi['repeat'][$plid] = $props['result']['repeat'];
			// $gotten[$plid] = true;
			var_dump('initial set| repeat, $plid',$kodi['repeat'][$plid],$plid); //,$props);
		}
	}
	if ($bool == 'toggle') {
		foreach ($plids AS $plid) {
			$bool = !$kodi['shuffle'][$plid];
			$sbool = ($bool)?'true':'false';
			$json = '{"jsonrpc":"2.0","method":"Player.SetShuffle","params":['.$plid.','.$sbool.'],"id":10}';
			$props = $_Kodi->sendJson($json);
			// if (!$props = $_Kodi->sendJson($json) || !isset($props['result']) || $props['result'] !== "OK" ) { 
			if ($props = $_Kodi->sendJson($json) && isset($props['result']) && $props['result']!== "OK" ) { 
				$kodi['shuffle'][$plid] = $bool;
			}
		}
		var_dump('!!!!!!!!!!!!!!!!!!!!!!!! $plid,$props',$kodi['shuffle'][$plid],$plid); //,$props);
		return $kodi['shuffle'][$aplid];
	}
	
	if ($bool === true || $bool === false ) {
		foreach ($plids AS $plid) {
			$sbool = ($bool)?'true':'false';
			$json = '{"jsonrpc":"2.0","method":"Player.SetShuffle","params":['.$plid.','.$sbool.'],"id":10}';
			if ($props = $_Kodi->sendJson($json) && isset($props['result']) && $props['result']!== "OK" ) { 
				$kodi['shuffle'][$plid] = $bool;
			}
		}
		var_dump('$plid,$props',$kodi['shuffle'][$plid],$plid); //,$props);
	}
	
	foreach ($plids AS $plid) {
		if (isset($gotten[$plid]) && $gotten[$plid] === true) { continue; }
		$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","shuffled","canshuffle"]],"id":11}';
		if (!($props = $_Kodi->sendJson($json)) || !isset($props['result']['shuffled'])) { continue; }
		// $props = $_Kodi->sendJson($json)['result'];
		$kodi['shuffle'][$plid] = $props['result']['shuffled'];
		var_dump('$plid,$props',$kodi['shuffle'][$plid],$plid); //,$props);
	}
}

function kodiRepeat($plids = null,$arg = 'get') {
	global $_Kodi;
	global $kodi;
	if ($plids == null) {
		$plids = [ activePlayer() ?? $kodi['plid'] ];
	} else if (is_string($plids) && $splids = 'all') {
		$plids = [0,1];
	} else if (!is_array($plids)) {
		$plids = [intval($plids)];
	}
	var_dump('start| $repeat, $arg, $plid',$kodi['repeat'][$kodi['plid']],$arg,$kodi['plid']); //,$props);

	$gotten = [];
	foreach ([0,1] AS $plid) {
		// if (!isset($kodi['shuffle'][$plid]) || $kodi['shuffle'][$plid] === null) {
			// $json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","shuffled","repeat","canshuffle"]],"id":11}';
			// if (!($props = $_Kodi->sendJson($json)) || !isset($props['result']['shuffled'])) { continue; }
			// $kodi['shuffle'][$plid] = $props['result']['shuffled'];
			// $gotten[$plid] = true;
		// }
		if (!isset($kodi['repeat'][$plid]) || $kodi['repeat'][$plid] === null) {
			$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","shuffled","repeat","canshuffle"]],"id":11}';
			if (!($props = $_Kodi->sendJson($json)) || !isset($props['result']['repeat'])) { continue; }
			$kodi['repeat'][$plid] = $props['result']['repeat'];
			$gotten[$plid] = true;
			var_dump('initial set| repeat, $plid',$kodi['repeat'][$plid],$plid); //,$props);
		}
	}
	if ($arg == 'toggle') {
		foreach ($plids AS $plid) {
			$bool = $kodi['repeat'][$plid];
			if ($bool === "one") { $bool = "off";} else
			if ($bool === "all") { $bool = "one";} else 
			if ($bool === "off") { $bool = "all";}  
			// $sbool = (!is_bool($bool))?$bool:(($bool)?'on':'off');
			//$sbool = $bool;
			$json = '{"jsonrpc":"2.0","method":"Player.SetRepeat","params":['.$plid.',"cycle"],"id":10}';
			// $json = '{"jsonrpc":"2.0","method":"Player.SetRepeat","params":['.$plid.',"'.$bool.'"],"id":10}';
			// $props = $_Kodi->sendJson($json);
			// if (!$props = $_Kodi->sendJson($json) || !isset($props['result']) || $props['result'] !== "OK" ) { 
			$props = $_Kodi->sendJson($json);
			if (isset($props['result']) && $props['result']=="OK") { 
				// $kodi['repeat'][$plid] = $props['result']['repeat'];
				var_dump('repeat set',$bool);
			} else {
				var_dump('repeat not set - $props,$plid,$bool,$json',$props,$plid,$bool,$json);
			}
		} 
		var_dump('toggle repeat: $plid,$repeat',$plid,$kodi['repeat'][$plid]); //,$props);
		return;
	}
	
	if ($arg === "one" || $arg === "all" || $arg === "off" ) {
		foreach ($plids AS $plid) {
			// $sbool = ($bool)?'true':'false';
			$bool = $kodi['repeat'][$plid] = $arg;
			// if ($bool === "off") { $bool = "on";} else 
			// if ($bool === "on") { $bool = "one";} else 
			// if ($bool === "one") { $bool = "off";}
			// $sbool = (!is_bool($bool))?$bool:(($bool)?'on':'off');
			//$sbool = $bool;
			$json = '{"jsonrpc":"2.0","method":"Player.SetRepeat","params":['.$plid.',"'.$bool.'"],"id":10}';
			if ($props = $_Kodi->sendJson($json) && isset($props['result']['repeat'])) { 
				$kodi['repeat'][$plid] = $bool = $props['result']['repeat'];
			}
		}
		var_dump('abs repeat: $plid,$props',$kodi['repeat'][$plid],$plid); //,$props);
	}
	
	foreach ($plids AS $plid) {
		if (isset($gotten[$plid]) && $gotten[$plid] === true) { continue; }
		$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","repeat","canrepeat"]],"id":131}';
		if (!($props = $_Kodi->sendJson($json)) || !isset($props['result']['repeat'])) { 
			var_dump('get repeat| $props,$plid',$props,$plid,$json);
			continue; 
		}
		// $props = $_Kodi->sendJson($json)['result'];
		$kodi['repeat'][$plid] = $props['result']['repeat'];
		var_dump('get repeat: $plid,$props',$kodi['repeat'][$plid],$plid); //,$props);
	}
}

function fixKodiAudio($arg = false) {
	global $kodi;
	global $_Kodi;

	if ((isset($kodi['playing']) && startsWith($kodi['playing'],'plugin://plugin.video.youtube/')) || (isset($kodi['path']) && startsWith($kodi['path'],'plugin://plugin.video.youtube/play/?video_id=') )) {
		var_dump("I sense youtube is afoot. dipping");
		return;
	}
	$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[1,["audiostreams","currentaudiostream"]],"id":9}';
	// $props = $_Kodi->sendJson($json)['result'];
	$props = $_Kodi->sendJson($json);
	var_dump($props);
	if (!isset($props['result'])) { return "815:Query error while getting audio stream options";	}
	$props = $props['result'];
	if (isset($props['currentaudiostream']['language']) && $props['currentaudiostream']['language'] !== 'eng') {
		$engstreams = filterArrayByKeyValue($props['audiostreams'],'language','eng');
		if (!count($engstreams)) {
			$b = array_column($props['audiostreams'],'name','language');
			$def = array_column($props['audiostreams'],'isdefault','language');
			$streamnames = array_map(fn($v,$k,$i,$d)=> ("[$i|$k]$v ".(($d)?"(Default)":'')), $b, array_keys($b),range(0,count($b)-1),$def);
			// $streamnames = array_map(fn($v,$k,$i,$d)=> ("[$i|$k]$v ".($d)?"(Default)":''), $b, array_keys($b),range(0,count($b)-1),$def);
			$strstr = niceList($streamnames);
			$ret = "No english audio stream found! The available audio stream language is ";	
			$plur = "No english audio stream found! Available audio stream languages are ";	
			return ((count($streamnames) == 1)?$ret:$plur).$strstr;
		}
		$aindex = $engstreams[array_keys($engstreams)[0]]['index'];
		$json = '{"jsonrpc":"2.0","method":"Player.SetAudioStream","params":[1,'.intval($aindex).'],"id":10}';
		$props = $_Kodi->sendJson($json);
	}
	return $props;
}

$bmmap = [];
$fmap = [];
if (!$ytmap = json_decode(file_get_contents('ytmap.json'),true)) {
	$ytmap = [];
}

function highlightArr($array) {
	return array_map(fn($v)=> ("**$v**"), $array);
}

function strtoTimeSecs($str) {
	$curtime = getVidTimes()[1];
	$time = explode(':',date('H:i:s',strtotime("$curtime ".$str)));
	var_dump('time str',$time,$str);
	// exit;
	return timeArrayToSecs($time);
}

function timeArrayToSecs($time) {
	if (is_string($time) && trim($time) == "") { return 0; }
	if (is_array($time)) {
		if (isset($time['hours'])) {
			unset($time['milliseconds']);
		}
		$time = implode(':',array_map('padInt',$time));
	}
	$sec = 0;
	var_dump($time);
	if (is_string($time) && trim($time) == "") { return 0; }
	foreach (array_reverse(explode(':', $time)) as $k => $v) $sec += pow(60, $k) * $v;
	return $sec;
}

function renderBMS($did = false) {
	if (!$did) { if ($kodi['menu']['did']) {$did = $kodi['menu']['did'];} }
	if (!$did) { return "ERR:84-DID"; }
	return renderDir(popBMMap($did,true),"bookmarks",kodiCurItem(),$did);
}

function popBMMap($did,$data = false) {
	global $fmap;
	global $bmmap;
	$query = "SELECT * FROM bookmarks WHERE did=:did";
	include('db.php');
	$stmt = $dbconn->prepare($query);                
	if (!$stmt->execute(['did' => $did])) {
		var_dump("$did Data error 42");
		$bms = false;
		// return "Data error 42";
	} else {
		$bms = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}


	// $results = "__**Bookmarks**__\n";

	$ikey = 0;
	$bmmap[$did] = [];
	$dirs = [];
	if (!isset($fmap[$did])) {
		popFMap($did);
	}
	if (count($fmap[$did])) {
		$dirs[] = json_decode('{ "filetype": "directory",
            "file": "favs",
            "label": "favs",
            "mimetype": "x-directory\/normal",
            "thumbnail": "",
            "title": "",
						"type": "unknown" }',true);
	} 
	
	if ($pldirs = uPlaylists($did,null,$data)) {
		$dirs =  array_merge($dirs,$pldirs);
	}
	
	if (!$bms && !count($dirs)) {
		var_dump("$did has no bookmarks yet");
		$bmmap[$did] = [];
		return "You have no bookmarks saved yet!";
	}
	
	file_put_contents('bmsitems.json',json_encode($bms, JSON_PRETTY_PRINT));
	foreach ($bms AS $key => $bm) {
		$ikey++;
		$id = $bm['id'];
		$type = $bm['type'];
		if (!$type) { $type = "file"; }
		$n = $bm['name'];
		$f = $bm['file'];
		$dirTemplate = [];

		$bmmap[$did][$ikey] = [$id,$f];

		$time = $ttime = '';
		$position = $total = false;
		if ($bm['time']) {
			$t = json_decode($bm['time'],true);
			unset($t['milliseconds']);
			$time = implode(':',array_map('padInt',$t));
			$position = timeArrayToSecs($t);
		}
		if ($bm['totaltime']) {
			$tt = json_decode($bm['totaltime'],true);
			unset($tt['milliseconds']);
			$ttime = implode(':',array_map('padInt',$tt));
			$total = timeArrayToSecs($tt);
		}
		if ($time && $ttime) {
			$time = "$time / $ttime";
		} else if ($ttime) {
			$time = "00:00:00 / $ttime";
		}
		//$bmmap[$did][$ikey] = [$id,$f];
		// $results .= "[$ikey]: $n $time \n";


	$dirTemplate = [
		"title"=> "",
		"thumbnail"=> "",
		"file"=> $f,
		"filetype"=> $type,
		"label"=> $n
	];

		if ($total) {
			$dirTemplate['runtime'] = $total;
			$dirTemplate['resume']['total'] = $position;
		}
		if ($position) {
			$dirTemplate['resume']['position'] = $position;
		}

		$dirs[] = $dirTemplate;
	}
	file_put_contents('bmsdirs.json',json_encode($dirs, JSON_PRETTY_PRINT));
	var_dump($bmmap[$did]);
	return $dirs;
}

function genDirs($array,$pathPrefix = '') {
	
	if (!$array['all']) $array['all'] = array_merge(...array_values($array));
	
	$ikey = 0;
	$dirs = [];
	foreach ($array AS $key => $chan) {
		$ikey++;
		$n = $key;
		$f = "$pathPrefix:$n";
		$c = count($chan);
		$t = "$n ($c)";

	$dirTemplate = [
		"title"=> $t,
		"mimetype"=> "x-directory/normal",
		"type"=> "unknown",
		"thumbnail"=> "",
		"file"=> $f,
		"filetype"=> "directory",
		"label"=> $t
	];


// [
    // {
        // "filetype": "directory",
        // "file": "favs",
        // "label": "favs",
        // "thumbnail": "",
        // "title": "",
    // },



		// if ($total) {
			// $dirTemplate['runtime'] = $total;
			// $dirTemplate['resume']['total'] = $position;
		// }
		// if ($position) {
			// $dirTemplate['resume']['position'] = $position;
		// }

		$dirs[] = $dirTemplate;
	}
	
	return $dirs;
	
	
	
}

function uPlaylists($did = false,$pl = null, $data = false) {
	// $did = $arg[0];
	// $pl = $arg[1];
	global $playlists;
	global $lastStatusData;

	if (!$data) {
		$data = $lastStatusData;
	}
	
	if (!$did) {
		$did = getDid();
	}

	if (!$playlists = json_decode(file_get_contents('playlists.json'),true)) {
		$playlists = [];
	}

	if (!isset($playlists[$did]) || !count($playlists[$did])) {
		return false;
	}		


	$dirs = [];

	if (!$pl) {
		//$output = "**pls for <@$did>**\n";
		foreach(array_keys($playlists[$did]) AS $list) {
			
			$label = ucfirst($list);
			$path = "uplay:$did:$list";

			$direntry =	[
            "filetype"=> "directory",
            "file"=> $path,
            "label"=> $label,
            "mimetype" => "x-directory/normal",
            "thumbnail" => "",
            "title" => "",
            "type" => "unknown"
        ];



			$dirs[] = $direntry;
			//$output .= "$list\n";
		}
		return $dirs;
	} else {
		if (!isset($playlists[$did][$pl])) {
			var_dump('error: playlists did pl unset',$did,$pl);
			return false;
		}
		// return renderDir($playlists[$did][$pl],"uplay:$did:$pl",kodiCurItem(),$data);
		return $playlists[$did][$pl];
	}
}


function renderFAVS($did = false) {
	if (!$did) { 
		global $kodi;
		$did = $kodi['menu']['did']; 
	}
	if (!$did) { 
		return "ERR:F84-DID"; 
	}
	return renderDir(popFMap($did,true),"favs",kodiCurItem(),true);
}

function popFMap($did,$array = false) {
	global $fmap;
	$query = "SELECT * FROM favs WHERE did=:did  ORDER BY `favs`.`timestamp` DESC";
	include('db.php');
	$stmt = $dbconn->prepare($query);                
	if (!$stmt->execute(['did' => $did])) {
		var_dump("$did Data error f42");
		return "Data error f42";
	}

	$fms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$fms) {
		var_dump("$did has no favorites yet");
		$fmap[$did] = [];
		return "You have no favourites saved yet!";
	}
	$results = "__**Favourites**__\n";

	$ikey = 0;
	$fmap[$did] = [];
	$dirs = [];
	file_put_contents('fmsitems.json',json_encode($fms, JSON_PRETTY_PRINT));
	foreach ($fms AS $key => $fm) {
		$id = $fm['id'];
		$type = $fm['type'];
		if (!$type) { $type = "file"; }
		$n = $fm['name'];
		$f = $fm['file'];
		$dirTemplate = [];
		$fmap[$did][$ikey] = [$id,$f];
		$time = $ttime = '';
		$position = $total = false;
		if ($fm['time']) {
			$t = json_decode($fm['time'],true);
			unset($t['milliseconds']);
			$time = implode(':',array_map('padInt',$t));
			$position = timeArrayToSecs($t);
		}
		if ($fm['totaltime']) {
			$tt = json_decode($fm['totaltime'],true);
			unset($tt['milliseconds']);
			$ttime = implode(':',array_map('padInt',$tt));
			$total = timeArrayToSecs($tt);
		}
		if ($time && $ttime) {
			$time = "$time / $ttime";
		} else if ($ttime) {
			$time = "00:00:00 / $ttime";
		}
		$fmap[$did][$ikey] = [$id,$f];
		$results .= "[$ikey]: $n $time \n";

		$ikey++;
		$dirTemplate = [
			"title"=> "",
			"thumbnail"=> "",
			"file"=> $f,
			"filetype"=> $type,
			"label"=> $n
		];

		if ($total) {
			$dirTemplate['runtime'] = $total;
			$dirTemplate['resume']['total'] = $position;
		}
		if ($position) {
			$dirTemplate['resume']['position'] = $position;
		}
		$dirs[] = $dirTemplate;
	}
	file_put_contents('fmap.json',json_encode($fmap, JSON_PRETTY_PRINT));
	file_put_contents('fsdirs.json',json_encode($dirs, JSON_PRETTY_PRINT));
	var_dump($fmap[$did]);
	if ($array) { return $dirs; }
	return $results;	
}

function kodiCurItem($gettitle = false) {
	global $_Kodi;
	global $kodi;
	global $ytmap;
	// $json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","mediapath","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
	// $mjson = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[0,["title","thumbnail","mediapath","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":20}';
	// $alljson = "[$json,$mjson]";
	$plid = activePlayer(true);
			// $plids = [intval($plid['result']['playerid'])];
			// $kodi['plid'] = intval($plid['result']['playerid']);


	$ftitle = $curitem = null;
	if ($plid !== -1) {
		$alljson = '[{"jsonrpc":"2.0","method":"Player.GetItem","params":['.$plid.',["title","thumbnail","mediapath","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10},{"jsonrpc":"2.0","method":"Player.GetActivePlayers","id":9}]';
		$ress = $_Kodi->sendJson($alljson);
		if ( isset($ress[1]['result'][0]['playerid'])) {
			$plid = $kodi['plid'] = $ress[1]['result'][0]['playerid'];
		}
		if ( isset($ress[1])) {
			var_dump("33333333333333333333333333333333333333333333333333333333333333333333333!!!!!!!!!!!!!!!!!",$ress[1],$plid);
		}
		// var_dump($alljson,json_decode($alljson,true),$ress); exit;

		$res['result'] = null;
		if (isset($ress[0]['result']['item']['file']) && !empty($ress[0]['result']['item']['file'])) {
			$kodi['plmode'] = 'music';
			$res = $ress[0]; 
		} // else if (isset($ress[1]['result']['item']['file']) && !empty($ress[1]['result']['item']['file'])) {
			// $res = $ress[1]; 
		// }
		
		// if (!$res['result']) {
			// $res = $_Kodi->sendJson($json);
			// if ($res['result']) {
			// }
		// }

		var_dump("ALLJSON RES",$alljson,$res);
		if ($res['result']) {
			if ($gettitle) {
				$item = $res['result']['item'];
				$filename = $item['file'];
				$file = false;
				if (isset($item['mediapath'])) {
					$file = $item['mediapath'];
				}
				if (!$file) { $file = $filename; }
				$artist = [];
				if (isset($item['artist'])) {
					$artist = $item['artist'];
				}
				$title = $item['title'];
				$name = $item['label'];
				if (!$name) { $name = $title; }
				if (!$name) { $name = $filename; }
				$ftitle = kodiTitle($name,$artist,$file);


			}
			if ($res['result']['item']['mediapath']) {
				$curitem = $res['result']['item']['mediapath'];
			} else {
				$curitem = $res['result']['item']['file'];
			}
		}
		if ($gettitle) {
			if (startsWith(stripcslashes($curitem),'plugin://plugin.video.youtube/play/?video_id=')) {
				$vid = explode('=',$curitem)[1];
				if (!isset($ytmap[$vid])) {
					$ytmap[$vid] = $ftitle;
					file_put_contents('ytmap.json', jenc($ytmap));
				}
			}
			$curitem = [$curitem,$ftitle];
		}
	} else if ($gettitle) {
		$curitem = [$curitem,$ftitle];
	}
	return $curitem;
}



$lastStatusData = [];
$lastStatusPlayer = ["Stopped","","00:00:00","00:00:00",0,"",""];
function playerStatus($status = false,$data = false) {
	global $lastStatusData;
	global $lastStatusPlayer;
	//var_dump('1 $lastStatusPlayer',$lastStatusPlayer,$data);
//	var_dump('1 $lastStatusData',$lastStatusData,$data);
	$retStr = false;
	if ($data == 'return') { $retStr = true; $data = false; }
	
	if (!$data){
		$data = $lastStatusData;
	} else if (isset($lastStatusData->channel_id)) {
		$lastStatusData =	$data;		
	}
	if (!$status) {
		$status = kodi('seek',null,$data);
	} else if (is_array($status)) {
		list($state,$play,$pcnt,$curtime,$endtime,$message) = $status;
		if ($message && !preg_match("/^(\s?)\n/",$message,$m)) { $message = "\n".$message; }
		$lastStatusPlayer = $status;
		$status = "[$state] $play \n $curtime / $endtime $pcnt% $message";
		$lastStatusPlayer[6] = $status;
	} else if (is_string($status) && $status == 'useArray') {
		list($state,$play,$curtime,$endtime,$pcnt,$message) = $lastStatusPlayer;
		if ($state !== "Stopped" && !$play) { $play = kodiCurItem(true)[1]; }
		//var_dump($state,$play);
		if ($message && !preg_match("/^(\s?)\n/",$message,$m)) { $message = "\n".$message; }
		$status = "[$state] $play \n $curtime / $endtime $pcnt% $message";
	} else if (!is_string($status)) {
		return "Status type error";
	}
	
	//if ($stoploop) 
	//global $ws;	
	//$data['global'] = $ws;
	// var_dump('2 $lastStatusPlayer',$lastStatusPlayer);
	if ($retStr) { return $status; }
	outputWorkspace('global',$status,'player');
	return $lastStatusPlayer;
}

function kodiVol($arg = false) {
	global $kodi;
	global $_Kodi;
	$output = false;
	if (!$arg || $kodi['vol'] == null || !isset($kodi['vol']) || !is_array($kodi['vol']) || count($kodi['vol']) !== 2) {
		$getvol = $json = '{"jsonrpc": "2.0", "method": "Application.GetProperties", "params" : { "properties" : [ "volume", "muted" ] }, "id" : 1 }';
		$output = $_Kodi->sendJson($json);
		if (isset($output['result']['muted'])) { $kodi['vol'][0] = $output['result']['muted']; }
		if (isset($output['result']['volume'])) { $kodi['vol'][1] = $output['result']['volume']; }
		if (!$arg) { return; }
	}
	
	// if (!$arg) {
		 // $getvol = $json = '{"jsonrpc": "2.0", "method": "Application.GetProperties", "params" : { "properties" : [ "volume", "muted" ] }, "id" : 1 }';
		// if (!$output) { $output = $_Kodi->sendJson($json); 
			// if (isset($output['result']['muted'])) { $kodi['vol'][0] = $output['result']['muted']; }
			// if (isset($output['result']['volume'])) { $kodi['vol'][1] = $output['result']['volume']; }
		// }
	// } else {
		$json = false;
		if ($arg == "up") {
			$kodi['vol'][1] += 10;
			if ($kodi['vol'][1] > 100) { $kodi['vol'][1] = 100; }
			$json = '{"jsonrpc":"2.0","method":"Application.SetVolume","params":{"volume":'.$kodi['vol'][1].'},"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
		} else if ($arg == "down") {
			$kodi['vol'][1] -= 10;
			if ($kodi['vol'][1] < 0) { $kodi['vol'][1] = 0; }
			$json = '{"jsonrpc":"2.0","method":"Application.SetVolume","params":{"volume":'.$kodi['vol'][1].'},"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
		} else if ($arg = "mute") {
		 $isMuted = $kodi['vol'][0] = !$kodi['vol'][0];
		 var_dump($_Kodi->setMute($isMuted));
		}
	if ($json) { $_Kodi->sendJson($json); }
	// }
	//var_dump(json_decode($json, true),  '-------------------============-------------------',$output,$kodi['vol'],"12123425AAAAAAAAAAAAAAAAAAAA"); if ($arg == "mute") { exit; }
}

function srcPaths($src = false) {
	global $kodi;
	if (!$src) {
		$srcs = json_decode(file_get_contents('srcs.json'),true);
		$msrcs = json_decode(file_get_contents('msrcs.json'),true);
		$kodi['tmppaths'] = true;
	} else {
		list($srcs,$msrcs) = $src;
	}
	$kodi['sources'] = array_merge($srcs['result']['sources'],$msrcs['result']['sources']);
	$kodi['paths'] = [$srcs['result']['sources'][0]['file'],$srcs['result']['sources'][1]['file'],$msrcs['result']['sources'][0]['file']];
	$kodi['npaths'] = ['tv' => $srcs['result']['sources'][0]['file'],'movies' => $srcs['result']['sources'][1]['file'],'music' => $msrcs['result']['sources'][0]['file']];
}

function pathMask($path,$data = false,$did = false, $dirs = false) {
	var_dump('PATH MASK',$path, $did);
	global $_Kodi;
	global $kodi;
	
	if (!$did) { $did = getDid($data); }

	if (startsWith($path,'search%3A')) {
		$output = searchArr($path); //,$did = false);
		if (!$dirs) { $output = renderDir($output,$path,kodiCurItem(),$data); }
		return $output;
	}

	if (startsWith($path,'uplay:')) {
		$args = explode(':',$path);
		if (!isset($args[2])) { $args[2] = false; }
		$output = uPlaylists($did,$args[2],$data);
		var_dump($args,$did,'4222222222222222200000000');
		if (!$dirs) { $output = renderDir($output,$path,kodiCurItem(),$data); }
		return $output;
	}

	if (startsWith($path,'uhist:')) {
		$uhist = explode(':',$path);
		// $output = searchArr($path); //,$did = false);
		// $output = renderDir($output,$path,kodiCurItem(),$data);
		$uhistdid = $uhist[1];
		if (isset($did) && !empty($did) && (is_string($did) || is_numeric($did)) && $did !== false && $did !== $uhist[1]) {
			$uhistdid = $did;
		}
		if (!isset($kodi['uhist'][$uhistdid])) { return false; }
		$output = $kodi['uhist'][$uhistdid];
		if (!$dirs) { $output = renderDir($kodi['uhist'][$uhistdid],$path,kodiCurItem(),$data); }
		return $output;
	}

	
	if (startsWith($path,'serveryt')) {
		//$output = renderBMS($did);
		$p = explode(':',$path);
		
			if (!$kodi['gvar']['ytdirs']['all']) $kodi['gvar']['ytdirs']['all'] = array_merge(...array_values($kodi['gvar']['ytdirs']));

		$output = (isset($p[1]))?renderDir(cacheYTNames($kodi['gvar']['ytdirs'][$p[1]]),$path,kodiCurItem(),$did):renderDir(genDirs($kodi['gvar']['ytdirs'],'serveryt'),$path,kodiCurItem(),$did);
		// $output = renderDir(cacheYTNames($kodi['gvar']['ytdirs'][$chan]),$path,kodiCurItem(),$did);
		return $output;
	}



// function renderBMS($did = false) {
	// if (!$did) { if ($kodi['menu']['did']) {$did = $kodi['menu']['did'];} }
	// if (!$did) { return "ERR:84-DID"; }
//}

	
	if ($path == 'bookmarks') {
		$output = renderBMS($did);
		return $output;
	}
	if ($path == 'favs') {
		$output = renderFAVS($did);
		return $output;
	}

	if ($path == 'queue') {
		$json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"]}}';
		$output = $_Kodi->sendJson($json);
		$output = renderQueue($output,kodiCurItem());
		return $output;
	}

	if ($path == 'sources') {
		//,{"jsonrpc":"2.0","method":"Files.GetSources","params":["music"],"id":2},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.audio","unknown",true,["path","name"]],"id":3},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.video","unknown",true,["path","name"]],"id":4}]';
		$json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["video"],"id":1}'; 
		
		$dirs = $_Kodi->sendJson($json);
		$dirs['result']['files'] = $dirs['result']['sources'];
		$output = $dirs;
		if (!$dirs) { $output = renderDir($dirs,'sources',kodiCurItem(),$data); }
		return $output;
	}
	// if (startsWith($path,'smb://') || startsWith($path,'multipath://') || startsWith($path,'/var/data/')) {
		// $media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";
		// $json = '{"jsonrpc":"2.0","id":"175675","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
		// $dirs = $_Kodi->sendJson($json);
		// $output = renderDir($dirs,$path,kodiCurItem(),$data);
		// // var_dump($json,$dirs,$output,"FUCKDDS89FDSUY897GTFYD98F67D");exit;
		// return $output;
	// }
	//var_dump("89 coooooooooooooooooooooooooosrfdg9fuy8d-8uycg89osfc9f8d90yreydo0t");exit;
	return false;
}

function getDid($data = null) {
	global $lastStatusData;
	$ldata = $lastStatusData;
	if (!$data) { $data = $lastStatusData; }
	//var_dump( $ldata,$data, $did,$arg); exit;
	$did = false;
	if (isset($data['author']['id'])) { 
		$did = $data['author']['id'];
	} else if (isset($data['user_id'])) {
		$did = $data['user_id'];
	}	else if (isset($ldata['author']['id'])) { 
		$did = $ldata['author']['id'];
	} else if (isset($ldata['user_id'])) {
		$did = $ldata['user_id'];
	}
	return $did;
}

function qlMode() {
	global $_Kodi;
	global $kodi;
	$json = '[{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742813","params":{"playlistid":0,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}},';
	$json .= '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"174813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}},';
	$json .= '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[0,["playlistid","position"]],"id":11},';
	$json .= '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[1,["playlistid","position"]],"id":11}]';
						// $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}}';
	// $inum = count($output = $_Kodi->sendJson($json)['result']['items']);
	// $vids = $_Kodi->sendJson($json);
	// $items = array_merge_recursive($_Kodi->sendJson($json),$_Kodi->sendJson($mjson));
	var_dump('qlmode',$json);
	$items = $_Kodi->sendJson($json);
	
	$qi = [false,false];
	$qindex = [false, false];
	if (isset($items[0]['result']) && $items[0]['result'] !== null) { $qi[0] = $kodi['queueitems'][0] = array_column($items[0]['result']['items'],'file'); }
	if (isset($items[1]['result']) && $items[1]['result'] !== null) { $qi[1] = $kodi['queueitems'][1] = array_column($items[1]['result']['items'],'file');}
	if (isset($items[2]['result']) && $items[2]['result'] !== null) { $qindex[0] = $items[2]['result']['position']; }
	if (isset($items[3]['result']) && $items[3]['result'] !== null) { $qindex[1] = $items[3]['result']['position']; }

	if ((!$qi[0] || count($qi[0]) < 2) && (!$qi[1] || count($qi[1]) < 2)) {
		$queuelistMode = false;
	} else if (($qi[0] && count($qi[0]) > 1) && (!$qi[1] || count($qi[1]) < 2)) {
		$queuelistMode = 0;
	} else if ((!$qi[0] || count($qi[0]) < 2) && ($qi[1] && count($qi[1]) > 1)) {
		$queuelistMode = 1;
	} else if (($qi[0] && count($qi[0]) > 1) && ($qi[1] && count($qi[1]) > 1)) {
		$queuelistMode = true;
	}
	$kodi['qlmode'] = $queuelistMode;
	$kodi['qindex'] = $qindex;
	file_put_contents('qlmode.json',json_encode([$json,$items,$qi,$kodi['qindex'],$kodi['qlmode']], JSON_PRETTY_PRINT));	
	return $kodi['qlmode'];	
}

function kodi($action = "playPause",$arg = null,$data = false) {
	$gowspage = 0;
	global $_Kodi;
	global $kodi;
	global $lastStatusData;
	global $lastStatusPlayer;
	global $curpath;
	global $ssaveron;
	$fav = false;
	$array = $return = false;
//	$queuelistMode = false;
	$queuecmd = false;
	if (is_string($data) && $data == 'return') {
		$data = false;
		$return = true;
	} else if (is_string($data) && $data == 'returnarray') {
		$array = true;
		$data = false;
		$return = true;
	}
	
	$did = false;
	if ($data !== null && $data !== false && ((is_array($data)&&isset($data['user_id']))||(is_object($data)&&(isset($data['user_id']) || isset($data['author']['id']))))) {
		if (isset($data['author']['id'])) { 
			$did = $data['author']['id'];
		} else if (isset($data['user_id'])) {
			$did = $data['user_id'];
		}
		file_put_contents('kodidata.json',json_encode($data, JSON_PRETTY_PRINT));		
		if ($did || (is_array($data) && isset($data['channel_id']) && (!is_array($lastStatusData) || !isset($lastStatusData['channel_id'])) )) {
			$lastStatusData = $data;
		}
	}
	$ldata = $lastStatusData;
	//var_dump( $ldata,$data, $did,$arg); exit;
	if (!$did) {
		if (isset($data['author']['id'])) { 
			$did = $data['author']['id'];
		} else if (isset($data['user_id'])) {
			$did = $data['user_id'];
		}	else if (isset($ldata['author']['id'])) { 
			$did = $ldata['author']['id'];
		} else if (isset($ldata['user_id'])) {
			$did = $ldata['user_id'];
		}
	}
	// var_dump( $ldata,$data, $did,$arg); exit;

	if ($did) { $kodi['menu']['did'] = $did; }
	$queuelistMode = $kodi['qlmode'];
	if ($kodi['qlmode'] === null || (is_string($action) && in_array($action,['next','prev','previous']))) {
		$queuelistMode = qlMode();
	}

	global $reactioned;
	$reactioned = true;

		
	// $json = '[{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742813","params":{"playlistid":0,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}},';
		// $json .= '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"174813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}},';
		// $json .= '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[0,["playlistid","position"]],"id":11},';
		// $json .= '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[1,["playlistid","position"]],"id":11}]';
							// // $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}}';
		// // $inum = count($output = $_Kodi->sendJson($json)['result']['items']);
		// // $vids = $_Kodi->sendJson($json);
		// // $items = array_merge_recursive($_Kodi->sendJson($json),$_Kodi->sendJson($mjson));
		// var_dump($json);
		// $items = $_Kodi->sendJson($json);
		
		// $qi = [false,false];
		// if (isset($items[0]['result']) && $items[0]['result'] !== null) { $qi[0] = $kodi['queueitems'][0] = array_column($items[0]['result']['items'],'file'); }
		// if (isset($items[1]['result']) && $items[1]['result'] !== null) { $qi[1] = $kodi['queueitems'][1] = array_column($items[1]['result']['items'],'file');}
		// if (isset($items[2]['result']) && $items[2]['result'] !== null) { $qindex[0] = $items[2]['result']['position']; }
		// if (isset($items[3]['result']) && $items[3]['result'] !== null) { $qindex[1] = $items[3]['result']['position']; }

		// if ((!$qi[0] || count($qi[0]) < 2) && (!$qi[1] || count($qi[1]) < 2)) {
			// $queuelistMode = false;
		// } else if (($qi[0] && count($qi[0]) > 1) && (!$qi[1] || count($qi[1]) < 2)) {
			// $queuelistMode = 0;
		// } else if ((!$qi[0] || count($qi[0]) < 2) && ($qi[1] && count($qi[1]) > 1)) {
			// $queuelistMode = 1;
		// } else if (($qi[0] && count($qi[0]) > 1) && ($qi[1] && count($qi[1]) > 1)) {
			// $queuelistMode = true;
		// }
		// $kodi['qlmode'] = $queuelistMode;
		// // if (isset($items['result']) && is_array($items['result']) && $items['result']!==null&&isset($items['result']['items'])) { $kodi['queueitems'] = array_column($items['result']['items'],'file'); }
		// // var_dump('78243784784638956356934',$json,$items);
		// // if (isset($_Kodi->error)) { 
			// // return print_r($_Kodi->error,true);
		// // }
		
		// // if (!isset($items['result']) || !isset($items['result']['items'])) {
			// // var_dump($items,'queue render ERROR');
			// // return "Query error 48".intval(!isset($items['result'])).intval(!isset($items['result']['items']));
		// // }
		// // if ($queuelistMode = (count($items['result']['items']) > 1)) {
			// // if (($qindex = $_Kodi->sendJson($json)|| $qindex = $_Kodi->sendJson($mjson)) && isset($qindex['result']['position'])) {
				// // $qindex = $qindex['result']['position'];
			// // } else {
				// // $qindex = false;
			// // }
			// // var_dump($qindex,"FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF");

		// // }
		

	var_dump("KODI",$action,$arg);

	if ($kodi['paths'] == null || $kodi['tmppaths']) {
		$json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["music"],"id":1}'; //,{"jsonrpc":"2.0","method":"Files.GetSources","params":["music"],"id":2},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.audio","unknown",true,["path","name"]],"id":3},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.video","unknown",true,["path","name"]],"id":4}]';
		$msrcs = $_Kodi->sendJson($json);
		$json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["video"],"id":1}'; //,{"jsonrpc":"2.0","method":"Files.GetSources","params":["music"],"id":2},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.audio","unknown",true,["path","name"]],"id":3},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.video","unknown",true,["path","name"]],"id":4}]';
		$srcs = $_Kodi->sendJson($json);
		$kodi['tmppaths'] = false;
		// // $srcs = file_get_contents('srcs.json',json_encode($srcs, JSON_PRETTY_PRINT));
		// $srcs = json_decode(file_put_contents('srcs.json'),true);
		// $msrcs = json_decode(file_put_contents('msrcs.json'),true);
		
		var_dump($srcs);
		var_dump($msrcs);
		srcPaths([$srcs,$msrcs]);
		//exit;
		if (!isset($srcs['result']) && !isset($msrcs['result'])) {
			return "data error 1";
		}
	
	}

	if ($action === false) { return; }

	$playcmd = false;
	$output = '';
	$addq = '';

	switch ($action) {
		case "vol":
			if (!$arg) {
				 $json = '{"jsonrpc": "2.0", "method": "Application.GetProperties", "params" : { "properties" : [ "volume", "muted" ] }, "id" : 1 }';
			} else {
				$json = '{"jsonrpc":"2.0","method":"Application.SetVolume","params":{"volume":"increment"},"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
				if ($arg == "down") {
					$json = '{"jsonrpc":"2.0","method":"Application.SetVolume","params":{"volume":"decrement"},"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
				} else if ($arg = "mute") {
				 $isMuted = $kodi['vol'][0] = !$kodi['vol'][0];
				 var_dump($_Kodi->setMute($isMuted));
				}
			}
			// $json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[1,["volume"],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
			// $output = $_Kodi->sendJson($json)['result'];
			$output = $_Kodi->sendJson($json)['result'];
			if (isset($output['volume'])) { $kodi['vol'] = [$output['muted'],$output['volume']]; }
			
			//var_dump($json,$output);exit;
			// $output = json_encode(fixKodiAudio());
			// $data = false;
			// $return = true;
			return $output;
		break;
		
		case "search":
			list($cat,$text) = $arg;
			$did = (isset($data['user_id']))?$data['user_id']:false;
			$output = searchArr($text,$cat);
			// if (!$output)
			file_put_contents('ksearch.json',json_encode([$text,$cat,$output], JSON_PRETTY_PRINT));
			$output = renderDir($output,urlencode("search:$cat:$text"),kodiCurItem(),$data);
			var_dump($output);
		break;

		case "showdir":
			$media = (startsWith($arg,'smb://192.168.12.100/E/music/'))?"music":"video";
			$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($arg,'\\').'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"]}}';
			$dirs = $_Kodi->sendJson($json);
			$output = renderDir($dirs,$arg,kodiCurItem(),$data);
			var_dump($output);
		break;

		case "audiostream":
			$output = json_encode(fixKodiAudio(), JSON_PRETTY_PRINT);
			$data = false;
			$return = true;
		break;

		case "resume":
			if (!$arg[2]) {
				return "You must be in the tv room channel to do this!";
			}

			$key = $arg[0];
			$did = $arg[1];
			global $bmmap;
			if (!isset($bmmap[$did])) {
				popBMMap($did);
			}
			
			$id = $bmmap[$did][$key][0];

			$query = "SELECT name,file,time,type FROM bookmarks WHERE did=:did AND id=:id";

			include('db.php');
			$stmt = $dbconn->prepare($query);                
			if (!$stmt->execute(['id' => $id,'did' => $did])) {
				return "Data error 42";
			}

			$bms = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$bms) {
				return "Bookmark for $key not found!";
			}
			$f = $bms['file'];
			$type = $bms['type'];
			if ($type == 'directory') {
				$path = $f;
				$output = "\n";
				$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";

				$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"]}}';
				$dirs = $_Kodi->sendJson($json);
				file_put_contents('bmrdirs.json',json_encode($dirs, JSON_PRETTY_PRINT));
				var_dump('$path $dirs2',$path,$dirs,$json);

				$output = renderDir($dirs,$path,kodiCurItem(),$data);
			} else {
				// if (!$kodi['autoplay']) { $_Kodi->stop();
					// usleep(1000000);
				// }

				$play = $_Kodi->openFile(addcslashes($f,'\\'));
				var_dump($play);
				
				$t = false;
				if ($bms['time']) {
					$t = json_decode($bms['time'],true);
				}
				global $gseek;
				$gseek = $t;
			var_dump($bms,'00000000000000',$gseek);
				$n = $bms['name'];
				// setVoiceStatus("Playing $n");



				return false;
	}
		break;
		case "fav":

			$did = $arg[1];
			global $fmap;
			if (!isset($fmap[$did])) {
				popFMap($did);
			}
			// if (count($bmmap[$did]) > 9) {
				// return "Maximum number of bookmarks saved. Please remove one before adding another";
			// }
			
			$key = $arg[0];
			if (!$key && $key !== 0) {
				if (!$arg[2]) {	return "You must be in the tv room channel to do this!"; }
				$type = 'file';
				$json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","mediapath","resume","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
				$mjson = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[0,["title","thumbnail","mediapath","resume","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
				$res = $_Kodi->sendJson($json);
				$plid = 1;
				if (!$res || isset($res['error'])) {
					$res = $_Kodi->sendJson($mjson);
					$plid = 0;
				}
				file_put_contents('favs5435.json',json_encode($res, JSON_PRETTY_PRINT));
				if ($res['result'] && isset($res['result']['item']['file'])) {
					$filename = $res['result']['item']['file'];
					$file = $res['result']['item']['mediapath'];
					$artist = $res['result']['item']['artist'];
					$title = $res['result']['item']['title'];
					$name = $res['result']['item']['label'];
					if (!$name) { $name = $title; }
					if (!$name) { $name = $filename; }
					if (count($artist)) {
						$artist = $artist[0];
						if ($artist) {
							$name = $artist.$name;
						}
					}
				} else {
					return "Data error 420";
				}
				if (!isset($res['result']['item']['file']) || empty($res['result']['item']['file'])) {
					return "Nothing is currently playing";
				}
				
				$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","speed","position","totaltime","time","percentage","shuffled","repeat","canrepeat","canshuffle","canseek","partymode"]],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
				$props = $_Kodi->sendJson($json)['result'];
				if (!isset($props['time'])) {
					return "Data error 960";
				}
				$time = json_encode($props['time']);
				$totaltime = json_encode($props['totaltime']);
			} else {
				if (!isset($kodi['menu'][$key])) {
					return "7890f80: Invalid selection: $arg";			
				}
				$sel = $kodi['menu'][$key];
				$name = $sel[3];
				$file = $sel[1];
				$type = $sel[0];
				$totaltime = $sel[5];
				if (count(array_filter($fmap[$did], fn($val) => $val[1] == $file ))) {
					return "$name already in your favorites!";
				}
				$time = false;
			}
				
			include('db.php');

			$query = "INSERT INTO `favs` (`did`,`name`,`file`,`time`,`totaltime`,`type`) VALUES (:did,:name,:file,:time,:totaltime,:type) RETURNING id;";

			$qd = [
			'did' => $did,
			'name' => $name,
			'file' => $file,
			'time' => $time,
			'totaltime' => $totaltime,
			'type' => $type
			];
			
			$stmt = $dbconn->prepare($query);                
			if ($stmt->execute($qd)) {
				$id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
				var_dump("-----------ID-----------",$id);
				$key = intval(array_key_last($fmap[$did]));
				while (isset($fmap[$did][$key]) && $key < 200) {	$key++;	}
				$fmap[$did][$key] = [$id,$file];
				var_dump($fmap[$did]);
				return "$name added to your favorites!";
			} else {
				return "Data error 4096";
			}
		break;
		case "bookmark":

			$did = $arg[1];
			global $bmmap;
			if (!isset($bmmap[$did])) {
				popBMMap($did);
			}
			if (count($bmmap[$did]) > 9) {
				return "Maximum number of bookmarks saved. Please remove one before adding another";
			}
			
			$attime = '';
			$key = $arg[0];
			if (!$key) {
				if (!$arg[2]) {	return "You must be in the tv room channel to do this!"; }
				$type = 'file';




				// $json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","mediapath","resume","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
				// $res = $_Kodi->sendJson($json);


				$json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","mediapath","resume","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
				$mjson = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[0,["title","thumbnail","mediapath","resume","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
				$res = $_Kodi->sendJson($json);
				$plid = 1;
				if (!$res || isset($res['error'])) {
					$res = $_Kodi->sendJson($mjson);
					$plid = 0;
				}




				file_put_contents('bookmark5435.json',json_encode($res, JSON_PRETTY_PRINT));
				if ($res['result'] && isset($res['result']['item']['file'])) {
					$filename = $res['result']['item']['file'];
					$file = $res['result']['item']['mediapath'];
					$artist = $res['result']['item']['artist'];
					$title = $res['result']['item']['title'];
					$name = $res['result']['item']['label'];
					if (!$name) { $name = $title; }
					if (!$name) { $name = $filename; }
					if (count($artist)) {
						$artist = $artist[0];
						if ($artist) {
							$name = $artist.$name;
						}
					}
				} else {
					return "Data error 420";
				}
				if (!isset($res['result']['item']['file'])) {
					return "Nothing is currently playing";
				}
				
				$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","speed","position","totaltime","time","percentage","shuffled","repeat","canrepeat","canshuffle","canseek","partymode"]],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
				$props = $_Kodi->sendJson($json)['result'];
				if (!isset($props['time'])) {
					return "Data error 960";
				}
				$time = array_map('padInt',$props['time']);
				$curtime = implode(':',[$time['hours'],$time['minutes'],$time['seconds']]);
				$attime = " at $curtime";
				$time = json_encode($props['time']);
				$totaltime = json_encode($props['totaltime']);
			} else {
				if (!isset($kodi['menu'][$key])) {
					return "7890f80: Invalid selection: $arg";			
				}
				$sel = $kodi['menu'][$key];
				$name = $sel[3];
				$file = $sel[1];
				$type = $sel[0];
				$totaltime = $sel[5];
				if (count(array_filter($bmmap[$did], fn($val) => $val[1] == $file ))) {
					return "$name already in your bookmarks!";
				}
				$time = false;
			}
				
			include('db.php');

			$query = "INSERT INTO `bookmarks` (`did`,`name`,`file`,`time`,`totaltime`,`type`) VALUES (:did,:name,:file,:time,:totaltime,:type) RETURNING id;";

			$qd = [
			'did' => $did,
			'name' => $name,
			'file' => $file,
			'time' => $time,
			'totaltime' => $totaltime,
			'type' => $type
			];
			
			$stmt = $dbconn->prepare($query);                
			if ($stmt->execute($qd)) {
				$id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
				var_dump("-----------ID-----------",$id);
				$key = intval(array_key_last($bmmap[$did]));
				while (isset($bmmap[$did][$key]) && $key < 200) {	$key++;	}
				$bmmap[$did][$key] = [$id,$file];
				var_dump($bmmap[$did]);
				return "$name$attime has been added to your bookmarks!";
			} else {
				return "Data error 4096";
			}
		break;
		case "unfav":

			//$id = array_search(56,$blah);
			$key = $arg[0];
			$did = $arg[1];
			global $fmap;
			if (!isset($fmap[$did])) {
				popFMap($did);
			}

			if (!isset($fmap[$did][$key])) {
				return "Favorite for $key not found";
			}

			$id = $fmap[$did][$key][0];

			include('db.php');
			$stmt = $dbconn->prepare("DELETE FROM favs WHERE id=:id AND `did`=:did");
			if ($stmt->execute(['did' => $did,'id'=>$id])) {
				$arr = array_filter($fmap[$did], fn($val) => $val[0] !== $id);
				array_unshift($arr,"");
				$fmap[$did] = array_values($arr);
				unset($fmap[$did][0]);
				return "Favorite removed!";
			} else {
				return "Data error 8192";
			}
		break;
		case "unbookmark":

			//$id = array_search(56,$blah);
			$key = $arg[0];
			$did = $arg[1];
			global $bmmap;
			if (!isset($bmmap[$did])) {
				popBMMap($did);
			}

			if (!isset($bmmap[$did][$key])) {
				return "Bookmark for $key not found";
			}

			$id = $bmmap[$did][$key][0];

			include('db.php');
			$stmt = $dbconn->prepare("DELETE FROM bookmarks WHERE id=:id AND `did`=:did");
			if ($stmt->execute(['did' => $did,'id'=>$id])) {
				$arr = array_filter($bmmap[$did], fn($val) => $val[0] !== $id);
				array_unshift($arr,"");
				$bmmap[$did] = array_values($arr);
				unset($bmmap[$did][0]);
				if ($kodi['plmode'] == 'queue') {
				// $output = kodi('bookmarks',null,$data);
				$output = kodi('bookmarks',[null,$data['user_id'],false],$data);
				break;
				} else {
				return "Bookmark removed!";
					
				}
			} else {
				return "Data error 8192";
			}
		break;
		case "favs":
			$fav=true;
		case "bookmarks":
			list($table,$fname) = ($fav)?['favs','favorites']:['bookmarks','bookmarks'];
			$query = "SELECT * FROM $table WHERE did=:did";
			// var_dump( $ldata,$data, $did,$arg); exit;
			if ($arg[1] && !$did) { $did = $arg[1]; }

			include('db.php');
			$stmt = $dbconn->prepare($query);                
			if (!$stmt->execute(['did' => $did])) {
				return "Data error 42 $table";
			}

			$bms = $stmt->fetchAll(PDO::FETCH_ASSOC);

			if (!$bms) {
				$output = "You have no $fname yet"; break;
			}
			var_dump($bms,'00000000000000');

			if ($fav) { $dirs = popFMap($did,true); } else {	$dirs = popBMMap($did,true); }
			$output = renderDir($dirs,$table,kodiCurItem(),$data);
			var_dump($dirs,$output,$did);
		break;
		case "btn":
			$btnaction = '';
			if (is_string($arg) && in_array($arg,['play','pause','back'])) {
				$btnaction = $arg;
				// global $kodi['resumeData'];
				if ( $btnaction == "play" && $kodi['resumeData'] && is_array($kodi['resumeData']) && getVidTimes()[0] == "Playing") {
					
					if ($kodi['resumeData'][0] == kodiCurItem()) { // return;
						kodi('seek',['abs',$kodi['resumeData'][1]]);
						$lastStatusPlayer[5] = "";
					} else {
						$lastStatusPlayer[5] = "Resume Data Error";
					}
					$kodi['resumeData'] = false;
					return playerStatus('useArray');
				}
				$json = '{"jsonrpc":"2.0","method":"Input.ExecuteAction","params":["'.$btnaction.'"],"id":31}';
				$output = $_Kodi->sendJson($json);
				if ($ssaveron) {
					sleep(1);
					// $json = '{ "jsonrpc": "2.0", "method": "Input.ExecuteAction", "params": { "action": "skipnext" }, "id": 16343 }';
					$output = $_Kodi->sendJson($json);
				}


				var_dump('PLAY BUTTON OUTPUT',$json,$output);
				return $output;
			}
		break;
		case "osd":
			$json = '{"jsonrpc":"2.0","method":"Input.ExecuteAction","params":["osd"],"id":31}';
			$output = $_Kodi->sendJson($json);
			var_dump($output);
		break;
		case "unqueue":
			if ($arg == 'all') {
				$arg = 'clear';
			} else {
				$json = '[{"jsonrpc":"2.0","method":"Playlist.Remove","params":[1,'.intval($arg).'],"id":112}]';
				$unq = $_Kodi->sendJson($json);
				var_dump($unq);
				$arg = null;
			}
		case "queuefrom":
			if (is_numeric($arg)) {
				// $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}}';
				// $inum = count($output = $_Kodi->sendJson($json)['result']['items']);
				$start = intval($arg);
				foreach ($kodi['menu'] AS $arg => $selection) {
					if (!intval($arg) || intval($arg) < $start) { continue; }
					
					$selection = $kodi['menu'][$arg];
					if ($selection[0] == 'file') {
						$file = stripslashes($selection[1]);
					} else {
						var_dump("selection is not a file",$arg,$selection);
						continue;
					}
					var_dump('FOOOOOOOOOOOO FROMM          00000000000000000',$inum,$json,$output);


					$plid = (startsWith($file,'smb://192.168.12.100/E/music/'))?0:1;
					$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$file.'" },"playlistid" : '.$plid.'}}';


					//$json = '[{"jsonrpc":"2.0","method":"Playlist.Insert","params":[1,'.intval($inum).',{"file":"'.addcslashes($file,'\\').'"}],"id":2209}]';
					$inum++;
					var_dump('FOOOOOOOOOOOO13333333333333333333333',$inum,$json);
					$addq = $_Kodi->sendJson($json);
					var_dump($addq);
				}
				$arg = null;
			}
		case "queue":
			if ($arg !== null) {
				if (startsWith($arg,'clear')) {
					$json = '[{"jsonrpc":"2.0","method":"Playlist.Clear","params":[0],"id":16},';
					$json .= '{"jsonrpc":"2.0","method":"Playlist.Clear","params":[1],"id":16}]';
					if ($arg == 'clear0') {
						$json = '{"jsonrpc":"2.0","method":"Playlist.Clear","params":[0],"id":16}';
					}
					if ($arg == 'clear1') {
						$json = '{"jsonrpc":"2.0","method":"Playlist.Clear","params":[1],"id":16}';
					}
						$clearq = $_Kodi->sendJson($json);
						var_dump($json,$clearq);
						if ($data === null) {
							qlMode();
							return "D46346 NULL";
						}
						$kodi['qlmode'] = false;
						if (!count($kodi['hist'])) {
							$path = $kodi['paths'][0];
						} else {
							$path = array_pop($kodi['hist']);
						}
						$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";

							if ($output = pathMask($path,$data,$did)) {
								break;
							}

							// $plid = (startsWith($file,'smb://192.168.12.100/E/music/'))?0:1;
						$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
						$dirs = $_Kodi->sendJson($json);
						var_dump($path,$media,$json,$dirs);

						$curitem = null;

						// $json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$file.'" },"playlistid" : '.$plid.'}}';


						// $json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","mediapath","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
						// $res = $_Kodi->sendJson($json);
						// if ($res['result']) {
							// $curitem = $res['result']['item']['file'];
							$curitem = kodiCurItem();
						// }

						$output = renderDir($dirs,$path,$curitem,$data);
						break;
					
				} else if ($arg == 'all') {
					
					// $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}}';
					// $inum = count($output = $_Kodi->sendJson($json)['result']['items']);
					$bucket = (count($kodi['menu']) > 50);
					
					
					$bitems = [];
					foreach ($kodi['menu'] AS $arg => $selection) {
						// if ($arg == 'path' ) { continue; }
						if (!intval($arg)) { continue; }
				
						$selection = $kodi['menu'][$arg];
						if ($selection[0] == 'file') {
							$file = stripslashes($selection[1]);
							// $file = $selection[1];
						} else {
							var_dump("selection is not a file",$arg,$selection);
							continue;
						}
						var_dump('FOOOOOOOOOOOO ALLL00000000000000000',$json,$output);
						
						$plid = (startsWith($file,'smb://192.168.12.100/E/music/'))?0:1;
						$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$file.'" },"playlistid" : '.$plid.'}}';

						// $json = '[{"jsonrpc":"2.0","method":"Playlist.Insert","params":[1,'.intval($inum).',{"file":"'.addcslashes($file,'\\').'"}],"id":2209}]';
						// $inum++;
						// var_dump('FOOOOOOOOOOOO111111111111111111111',$inum,$json);
						if ($bucket) { 
							$bitems[] = $json; 
						} else {
							$addq = $_Kodi->sendJson($json);
						}
						var_dump(count($bitems),$addq);
					}
					if ($bucket) { 
						$kodi['buckets'][] = $bitems;
						global $bucketTimer; 
						if ($bucketTimer === NULL) { bucketLooper(); }
						return;
					}
					
				} else {
					if (!isset($kodi['menu'][$arg])) {
						return "9fa78: Invalid selection: $arg";			
					}
					$selection = $kodi['menu'][$arg];
					if ($selection[0] == 'file') {
						//$file = $selection[1];
						$file = stripslashes($selection[1]);
					} else {
						return "selection is not a file";
					}
					// $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}}';
					// $inum = count($output = $_Kodi->sendJson($json)['result']['items']);
					// var_dump('FOOOOOOOOOOOO',$inum,$json,$output);


					// $json = '[{"jsonrpc":"2.0","method":"Playlist.Insert","params":[1,'.intval($inum).',{"file":"'.addcslashes($file,'\\').'"}],"id":2209}]';
					$plid = (startsWith($file,'smb://192.168.12.100/E/music/'))?0:1;

					$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$file.'" },"playlistid" : '.$plid.'}}';

					var_dump('FOOOOOOOOOOOO111111111111111111111',$inum,$json);
					$addq = $_Kodi->sendJson($json);
					var_dump($addq);
				}
			}
		case "getqueuelist":
			$curitem = kodiCurItem();
			
			$json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"]}}';
			$mjson = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":0,"properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"]}}';
			var_dump($json,$mjson);
			$output = ['videos' => $_Kodi->sendJson($json),'music' => $_Kodi->sendJson($mjson)];

			$items = array_merge_recursive($output['videos'],$output['music']);
			if (isset($items['result']) && is_array($items['result']) && $items['result']!==null&&isset($items['result']['items'])) { $kodi['queueitems'] = array_column($items['result']['items'],'file'); }
			// if (isset($items['result'])) { $kodi['queueitems'] = array_column($items,'file'); }


			$output = renderQueue($output,$curitem);
			qlMode();
			var_dump('3333333333333333333333333333333',$output);
		break;
		case "getplaylist":
			$did = $arg[0];
			$pl = $arg[1];
			
			// global $playlists;

			// if (!$playlists = json_decode(file_get_contents('playlists.json'),true)) {
				// $playlists = [];
			// }



			if (!$pl) {
				if ($pldirs = uPlaylists($did,null,$data)) {
					$plpath = "uplay:$did";
				} else {
					return "Error loading playlists!";
				}
				// $output = "**pls for <@$did>**\n";
				// foreach(array_keys($playlists[$did]) AS $list) {
					// $output .= "$list\n";
				// }
			} else {
				$plpath = "uplay:$did:$pl";
				if (!isset($playlists[$did][$pl])) {
					return "Error loading playlist: $pl";
				}
				$pldirs = $playlists[$did][$pl];
			}
			$output = renderDir($pldirs,$plpath,kodiCurItem(),$data);
		break;
		case 'playPause':
			$playpause = '[
				{
					"id": 2240,
					"jsonrpc": "2.0",
					"method": "Player.PlayPause",
					"params": [
						1,
						"toggle"
					]
				}
			]';

			$output = $_Kodi->sendJson($playpause);
			$output = "Toggle Play/Pause";
		break;
		case "movies":
			$path = $kodi['paths'][1];
			$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"video","properties":["title","file","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
			$dirs = $_Kodi->sendJson($json);
			var_dump($dirs,'=====MOVIES======',$json);
			$output = renderDir($dirs,$path,kodiCurItem(),$data);
		break;
		case "seek":
			//$playfile = "unknown";
			list($curitem,$playfile) = kodiCurItem(true);

			$plid = activePlayer() ?? $kodi['plid'];
			
			$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","speed","position","totaltime","time","percentage","shuffled","repeat","canrepeat","canshuffle","canseek","partymode"]],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
			$props = $_Kodi->sendJson($json)['result'];

			// if (!$props) {
				// $json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[0,["playlistid","speed","position","totaltime","time","percentage","shuffled","repeat","canrepeat","canshuffle","canseek","partymode"]],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
				// $props = $_Kodi->sendJson($json)['result'];
			// }


			file_put_contents('seekprops.json',json_encode($props));
			var_dump('TIME000000000000000000000000',$lastStatusPlayer,$props);
			if ($props == NULL) { 
			// 		list($state,$play,$curtime,$endtime,$pcnt,$message) = $lastStatusPlayer;

				$lastStatusPlayer[0] = "Stopped";
				$lastStatusPlayer[1] = "";
				$lastStatusPlayer[2] = "00:00:00";
				$lastStatusPlayer[3] = "00:00:00";
				$lastStatusPlayer[4] = 0;
				$lastStatusPlayer[5] = "";
				// setVoiceStatus('');
				playerStatus('useArray');
	
				return false; 
			}
				
			$kodi['plid'] =	$plid = $props['playlistid'];
			$kodi['shuffle'][$plid] = $props['shuffled'];
			
			if ($arg == NULL || $arg[0] == 'show') {
				
				$time = $props['time'];
				$time = array_map('padInt',$time);
				$curtime = implode(':',[$time['hours'],$time['minutes'],$time['seconds']]);
				$time = $props['totaltime'];
				$time = array_map('padInt',$time);
				$endtime = implode(':',[$time['hours'],$time['minutes'],$time['seconds']]);

				$pcnt = round($props['percentage'],2);
				
				// if (is_array($arg) && $arg[0] == 'show') {
					// $json = '{"jsonrpc":"2.0","method":"Input.ExecuteAction","params":["osd"],"id":31}';
					// $osd = $_Kodi->sendJson($json);
				// }
				
				$rplayfile=$playfile;

				$spd = $props['speed'];
				$pos = $props['position'];
				//$pcntr = round($props['percentage']);
				$pcnt = round($props['percentage'],2);

				//$state = ($spd)?(($pos == -1 || !$pcntr )?"Paused":"Playing"):"Stopped";

				if ($pos == -1 && !$spd && $pcnt == 0) {
					$state = "Stopped";
				} else if (!$spd && ($pos || $pcnt > 0)) {
					$state = "Paused";
				} else {
					$state = "Playing";
				}



				if (!$playfile) { $rplayfile = ''; }
				$play = $rplayfile;
		// 		list($state,$play,$curtime,$endtime,$pcnt,$message) = $lastStatusPlayer;

				$lastStatusPlayer[0] = $state;
				$lastStatusPlayer[1] = $play;
				$lastStatusPlayer[2] = $curtime;
				$lastStatusPlayer[3] = $endtime;
				$lastStatusPlayer[4] = $pcnt;
				
				return "[$state] $play \n".$curtime." / ".$endtime. " $pcnt%";
			}
			if ($arg[0] == 'pcnt') {
				$percent = $arg[1];
				$json = '{"jsonrpc":"2.0","method":"Player.Seek","params":['.$plid.',{"percentage":'.$percent.'}],"id":8}';
				$dirs = json_encode($_Kodi->sendJson($json));
				var_dump($dirs);
			}
			// } else {
			if ($arg[0] == 'time') {
				$time = $props['time'];
				$curtime = implode(':',[$time['hours'],$time['minutes'],$time['seconds']]);
				var_dump($arg,'-333333333--------ARG1',$arg[1][0]);
				
				$newtime = explode(':',date('H:i:s',strtotime("$curtime ".$arg[1][0])));
				
				
				$json = ["id"=>0,"jsonrpc"=>"2.0","method"=>"Player.Seek","params"=>[$plid]];
				$json["params"][1] = ["time" => ['hours'=>intval($newtime[0]),'minutes'=>intval($newtime[1]),'seconds' => intval($newtime[2]),'milliseconds'=>00]];
				var_dump("SEEK TIME 1",$json);
				$dirs = $_Kodi->sendJson(json_encode($json));
				var_dump("SEEK TIME 2",$dirs);
			} else if ($arg[0] == 'abs') {
				$json = ["id"=>0,"jsonrpc"=>"2.0","method"=>"Player.Seek","params"=>[$plid]];
				if (is_numeric($arg[1])) {
					$newtime = secsToTimeArray($arg[1]);
					$json["params"][1] = ["time" => ['hours'=>intval($newtime[0]),'minutes'=>intval($newtime[1]),'seconds' => intval($newtime[2]),'milliseconds'=>00]];
				} else if(is_array($arg[1])) {
					$json["params"][1] = ["time" => $arg[1]];
				}
				// $json["params"][1] = ["time" => ['hours'=>intval($newtime[0]),'minutes'=>intval($newtime[1]),'seconds' => intval($newtime[2]),'milliseconds'=>00]];
				var_dump("SEEK TIME 3",$json);
				$dirs = $_Kodi->sendJson(json_encode($json));
				var_dump($dirs);
			}
			// setVoiceStatus("Playing $playfile");
		break;
		case "music":
			// $json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["video"],"id":1}'; //,{"jsonrpc":"2.0","method":"Files.GetSources","params":["music"],"id":2},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.audio","unknown",true,["path","name"]],"id":3},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.video","unknown",true,["path","name"]],"id":4}]';

			// $dirs = $_Kodi->sendJson($json);
			// $dirs['result']['files'] = $dirs['result']['sources'];
			// var_dump($dirs);
			// $output = renderDir($dirs,'sources',kodiCurItem(),($data));
			$path = $kodi['paths'][2];
			$dirs = cacheKdir($kodi['paths'][2],'music');
			// $json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"music","properties":["title","artist","file","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
			// $dirs = $_Kodi->sendJson($json);
			// var_dump($dirs);
			$output = renderDir($dirs,"music",kodiCurItem(),$data);
		break;
		case "files":
		case "sources":
			$json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["video"],"id":1}'; //,{"jsonrpc":"2.0","method":"Files.GetSources","params":["music"],"id":2},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.audio","unknown",true,["path","name"]],"id":3},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.video","unknown",true,["path","name"]],"id":4}]';

			$dirs = $_Kodi->sendJson($json);
			$dirs['result']['files'] = $dirs['result']['sources'];
			var_dump("sources dirs",$dirs);
			$output = renderDir($dirs,'sources',kodiCurItem(),$data);
		break;
		case "shows":
			$path = $kodi['paths'][0];
			$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"video","properties":["title","artist","file","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
			$dirs = $_Kodi->sendJson($json);
			var_dump('shows dirs',$dirs);
			$output = renderDir($dirs,$path,kodiCurItem(),$data);
		break;
		case "previous":
		case "prev":
			if ($kodi['qlmode'] !== false && ($kodi['qlmode'] === true || $kodi['plid'] === $kodi['qlmode'])) {
			// if ($kodi['qlmode'] === true || $kodi['qlmode'] === $kodi['plid'] ) {
				$plid = activePlayer() ?? $kodi['plid'];
				$qindex = $kodi['qindex'];
				$qindex[$plid]--;
				if ($qindex[$plid] < 1) { $qindex[$plid] = 1; }
				$json = '{"jsonrpc":"2.0","method":"Player.GoTo","params":['.$plid.','.$qindex[$plid].'], "id":2}';
				$output = $_Kodi->sendJson($json);

				// var_dump($json,$output,'66666666666666666666666666');
				$kodi['onPlay'] = 'getqueuelist';
				$kodi['data'] = $data;
				// break;
				return;
			}

			if (!isset($kodi['playing']) || $kodi['playing'] == null) {
				$output = "sd89f: Invalid selection";
			} else {
				$arg = intval($kodi['playing'])-1;
				if (!isset($kodi['menu'][$arg])) {
					$kodi['autoplay'] = false;
					$output = "Invalid menu selection. Autonext disabled.";
				} else {
					$kodi['playing'] = intval($arg);
					$selection = $kodi['menu'][$arg];
					$kodi['playfile'] = $selection[1];
					$kodi['playfilename'] = $selection[3];
					$kodi['noq'] = $selection[1];
					kodi('play',$arg);
				}
			}
		break;
		case "next":
			var_dump("NEXT QUEUE CHECK",$kodi['noq'],$kodi['plid'],$kodi['qlmode']);
			if ($arg !== 'autoplay' && $kodi['qlmode'] !== false && ($kodi['qlmode'] === true || $kodi['plid'] === $kodi['qlmode'])) {
				$json = '{ "jsonrpc": "2.0", "method": "Input.ExecuteAction", "params": { "action": "skipnext" }, "id": 16343 }';
				$output = $_Kodi->sendJson($json);
				if ($ssaveron) {
					sleep(1);
					// $json = '{ "jsonrpc": "2.0", "method": "Input.ExecuteAction", "params": { "action": "skipnext" }, "id": 16343 }';
					$output = $_Kodi->sendJson($json);
				}
				$kodi['onPlay'] = 'getqueuelist';
				$kodi['data'] = $data;
				var_dump('TNEXT',$json,$output);
				
				// break;
				return;
			}
			if (!$kodi['playing'] && $kodi['path'] == 'sources') { 
				$curitem = kodiCurItem(true);
				$p = explode('/',$curitem[0]);
				array_pop($p);
				$p = implode('/',$p);
				$curitem[2] = $p;
				$kodi['hist'][] = $p;
				$kodi['path'] = $p;
				$output = kodi('showlist',null,$data);
				var_dump('tnext2',$json,$output);
				var_dump($curitem,$kodi); //exit;

				// $kodi['path'] =  
				// $kodi['playing'] =  
			}
			// var_dump($kodi);exit;
			if (!isset($kodi['playing']) || $kodi['playing'] == null) {
				$output = "p98sdg: Invalid selection";
				$kodi['autoplay'] = false;

				$curitem = kodiCurItem(true);
				$p = explode('/',$curitem[0]);
				array_pop($p);
				$p = implode('/',$p);
				$curitem[2] = $p;
				$kodi['path'] = $p;
				$kodi['hist'][] = $p;

				$json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1743603938944","params":{"directory":"'.$p.'","media":"video","properties":["title","file","artist","duration","comment","description","runtime","playcount","mimetype","thumbnail","dateadded"]}}';
				$dirs = $_Kodi->sendJson($json);
				var_dump('tnext3',$json,$dirs,$output);
				$output = renderDir($dirs,$p,kodiCurItem(),$data);


				// renderDir($_Kodi)
				kodi('refresh',null,$data);
				var_dump($curitem,$kodi['playing']); // exit;
			// } else {
			}
			if ($kodi['playing']) {
				$arg = $kodi['playing'];
			} else if ($kodi['menu']['pointer']) {
				$arg = $kodi['menu']['pointer'];
			}
			
			$arg = intval($arg)+1;
			if (!isset($kodi['playingmenu'][$arg])) {
				$kodi['autoplay'] = false;
				$output = "24232: Invalid menu selection";
			} else {
				$kodi['playing'] = intval($arg);
				$selection = $kodi['playingmenu'][$arg];
				$kodi['playfile'] = $selection[1];
				$kodi['noq'] = $selection[1];
				$kodi['playfilename'] = $selection[3];
				kodi('play',$arg);
				
				$p = dirname($kodi['playfile']);
				if ($kodi['path'] == $p) {
					$json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1743603938944","params":{"directory":"'.$p.'","media":"video","properties":["title","file","artist","duration","comment","description","runtime","playcount","mimetype","thumbnail","dateadded"]}}';
					$dirs = $_Kodi->sendJson($json);
					var_dump('tnext3',$json,$dirs,$output);
					$output = renderDir($dirs,$p,kodiCurItem(),$data);
					// break;
				}
			}
		break;
		case "showlist":
			$listpath = array_filter($kodi['hist'] , fn($o) => !in_array(trim($o),['playlists','favs','queue','bookmarks']));
		case "refresh":
			$path = false;
			if (!isset($listpath)) {
				$listpath = $kodi['hist'];
				if (isset($kodi['path'])) {
					$path = $kodi['path'];
					if (isset($kodi['menu']['page'])) {
						$gowspage = $kodi['menu']['page'];
					}
				}
			}
			if (!$path &&count($listpath)) {
				$path = array_pop($listpath);
			} else if (!$path) {
				$path = $kodi['paths'][0];
			}

			if (!isset($did)) { $did = false; }
			$did = ($arg)?$arg:$did;

			// if (startsWith($path,'uhist:')) {
				// $uhist = explode(':',$path);
				// // $output = searchArr($path); //,$did = false);
				// // $output = renderDir($output,$path,kodiCurItem(),$data);
				// $output = renderDir($kodi['uhist'][$uhist[1]],$path,kodiCurItem(),$data);
				// break;
			// }

			// if (startsWith($path,'search%3A')) {
				// $output = searchArr($path); //,$did = false);
				// $output = renderDir($output,$path,kodiCurItem(),$data);
				// break;
			// }

			// if ($path == 'queue') {
				// $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"]}}';
				// $output = $_Kodi->sendJson($json);
				// $output = renderQueue($output,kodiCurItem());
				// break;
			// }

			// if ($path == 'sources') {
				// $json = '{"jsonrpc":"2.0","method":"Files.GetSources","params":["video"],"id":1}'; //,{"jsonrpc":"2.0","method":"Files.GetSources","params":["music"],"id":2},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.audio","unknown",true,["path","name"]],"id":3},{"jsonrpc":"2.0","method":"Addons.GetAddons","params":["xbmc.addon.video","unknown",true,["path","name"]],"id":4}]';
				// $dirs = $_Kodi->sendJson($json);
				// $dirs['result']['files'] = $dirs['result']['sources'];
				// $output = renderDir($dirs,'sources',kodiCurItem(),$data);
				// break;
			// }

			// if ($path == 'bookmarks') {
				// $output = renderBMS($did);
				// break;
			// }

			// if ($path == 'favs') {
				// $output = renderFAVS($did);
				// break;
			// }

				if ($out = pathMask($path,$data,$did)) {
				var_dump("PATHMASK", !!($out));
				$output = $out;
				break;
			}
			
	
			if (startsWith($path,'smb://192.168.12.100/E/music/')) {
				$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"music","properties":["title","artist","file","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
			} else {
				$json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1743603938944","params":{"directory":"'.$path.'","media":"video","properties":["title","file","artist","duration","comment","description","runtime","playcount","mimetype","thumbnail","dateadded"]}}';
			}
			var_dump($json,$path);
			$dirs = $_Kodi->sendJson($json);
			$output = renderDir($dirs,$path,kodiCurItem(),$data);
		break;
		case "queuerandom":
			$queuecmd = true;
			$arg = 'random';
		case "continue":
			if (!$queuecmd) { $resumefile = true; }
		case "play":
			$playcmd = true;
			if (($arg == 'random' && $action == 'play') || $queuecmd) {
				$noqhist = array_filter($kodi['hist'] , fn($o) => $o != 'queue');

				if ($queuecmd) {
					if ($kodi['queuerandom'] === false) {
						if (isset($kodi['path']) && $kodi['path'] !== 'queue') {
							$path = $kodi['path'];
						} else if (count($noqhist)) {
							$path = array_pop($noqhist);
						} else {
							$path = $kodi['paths'][0];
						}
						$kodi['queuerandom'] = $path;
					}
				} else {
					if ($kodi['playrandom'] === false) {
						if (isset($kodi['path']) && $kodi['path'] !== 'queue') {
							$path = $kodi['path'];
						} else if (count($noqhist)) {
							$path = array_pop($noqhist);
						} else {
							$path = $kodi['paths'][0];
						}
						$kodi['playrandom'] = $path;
					}
				}
			}
		case "select":
			playerMsg("Loading...");
			$lkodi = $kodi;
			file_put_contents('lkodi.json',json_encode($lkodi, JSON_PRETTY_PRINT));
			$noqhist = array_filter($kodi['hist'] , fn($o) => $o != 'queue');
			$curitem = $curpath = null;
			$curitem = kodiCurItem();
			if (!is_array($arg)) {
				if ($arg == 'random') {
					if (isset($kodi['path']) && $kodi['path'] !== 'queue') {
						$path = $kodi['path'];
					} else if (count($noqhist)) {
						$path = array_pop($noqhist);
					} else {
						$path = $kodi['paths'][0];
					}
					if (!isset($lkodi['dirs']) || !is_array($lkodi['dirs']) || !is_array($lkodi['menu']) || $lkodi['dirspath'] != $path) {
						// $media = (startsWith('smb:\/\/192.168.12.100\/E\/music\/',$path))?"music":"video";
						$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";

						
						$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","resume","playcount","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
						$dirs = $_Kodi->sendJson($json);
						$output = renderDir($dirs,$path,$curitem,$data);
						$menu = $kodi['menu']; 
					} else {
						$menu = $lkodi['menu']; 
					}
					// var_dump($menu,$path);
					if (isset($dirs['error'])) { var_dump("DIRS ERROR",$json); }

					$menu = intKeyArrayFilter($menu); //,2,false,true);

					if (count($res = filterArrayByKeyValue($menu,2,false))) {
						// var_dump('RES',$res);
						$arg = array_rand($res);
					} else if (count($menu)) {
						$arg = rand(0,count($menu)-1);
					} else {
						if ($kodi['queuerandom']) {
							$kodi['path'] = $kodi['queuerandom'];
							$kodi['queuerandom'] = false;
							return kodi('queuerandom',null,$data);
						} else if ($kodi['playrandom']) {
							$kodi['path'] = $kodi['playrandom'];
							$kodi['playrandom'] = false;
							return kodi('play','random',$data);
						}
						return "s7df89d: Invalid selection: $arg";			
					}
				}
				if (!isset($kodi['menu'][$arg])) {
					return "asd9f7: Invalid selection: $arg";			
				}
				$selection = $kodi['menu'][$arg];
			} else {
				$selection = $arg;
				$arg = $selection[5];
				unset($selection[5]);
			}
			var_dump($selection);
			if ($selection[0] == 'directory') {
				var_dump('$selection',$selection);
				$path = $selection[1];
				if (isset($selection[8]['page'])) {
					$gowspage = $selection[8]['page'];
				}
				if ($out = pathMask($path,$data,$did)) {
					$output = $out;
					// break;
				} else {
					$output = "\n";
					$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";
					$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
					//$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.stripcslashes($path).'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
					//$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"smb://192.168.12.100/C/","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
					$dirs = $_Kodi->sendJson($json);
					$kodi['dirs'] = $dirs;
					$kodi['dirspath'] = $path;
					
					file_put_contents('dirs.json',json_encode($dirs, JSON_PRETTY_PRINT));

					if ($kerr = kodiError($dirs)) { 
						var_dump('FUCK YOUR MOTHERS ROSE GARDEN  7896777777777777$path $dirs1',$path,$dirs,$json);exit;
					
					return $kerr; }

					$output = renderDir($dirs,$path,$curitem,$data);
				}
				$curpath = $path;
				if ($kodi['queuerandom']) {
					$output = kodi('queuerandom',null,$data);
				} else if ($kodi['playrandom']) {
					$lpath = array_reverse(explode('/',rtrim(urldecode($path),'/')))[0];
					$output = kodi('play','random',$data);
				} else if (isset($dirs['result']['files']) && !isset($dirs['error'])) {
					$l = $dirs['result']['files'];
					$ac = array_column($l,'filetype');
					$r = array_count_values($ac);
					if (isset($r['file']) && $r['file'] == 1 && (!isset($r['directory']) || $r['directory'] < 3)) {
						$k = array_keys($ac,'file')[0];
						$key = $arg;
						$arg = $kodi['menu'][$k];
						$arg[5] = $key;
						kodi('play',$arg);
						$kodi = $lkodi;
						$path = $kodi['path'];

						$kodi['playing'] = intval($arg[5]);
						$selection = $arg;
						$kodi['playfile'] = $selection[1];
						$kodi['playfilename'] = $selection[3];

						$output = renderDir($l,$path,$curpath,$data);
					}
				}
			} else {
				if (!$kodi['queuerandom']) {
					// if ($curitem) { 
						// // $_Kodi->stop();
						// if (!$kodi['autoplay']) { $_Kodi->stop(); 
							// usleep(1000000);
						// }
					// }


					// list($kodi['plmode'],$kodi['playing'],$kodi['playfile'],$kodi['playfilename'],$kodi['resumeData']) = $selected;

					// $selected = ['queue',intval($arg),$selection[1],$selection[3],[$selection[1],$selection[4]],$selection[8]];
					$selected = [$kodi['plmode'],intval($arg),$selection[1],$selection[3],[$selection[1],$selection[4]],$selection[8]];
					// $kodi['playing'] = intval($arg);
					// $kodi['playfile'] = $selection[1];
					// $kodi['playfilename'] = $selection[3];
					$t =  $selection[4];
					if ($t > 0) {
						$ttime = implode(':',array_map('padInt',secsToTimeArray($t)));
						$lastStatusPlayer[5] = "You can resume where you left off at $ttime by clicking Play";
						$kodi['resumeData'] = [$selection[1],$t];
						//$kodi['resumeData'][1] = $t;
					}
				}
				$autoq = true;
				if ($kodi['queuerandom']) {
					$kodi['path'] = $kodi['queuerandom'];
					$kodi['queuerandom'] = false;
											//$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";

					if (!$kodi['queueitems']) {
						$json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":1,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}}';
						$mjson = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":0,"properties":["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"],"limits":{"start":0}}}';
						//$kodi['queueitems'] = $_Kodi->sendJson($json)['result']['items'];
					// } else {
						$kodi['queueitems'] = array_column(array_merge_recursive($_Kodi->sendJson($json),$_Kodi->sendJson($mjson))['result']['items'],'file');
					}
					$inum = count($kodi['queueitems']);
					
					
					
					// var_dump('FOOOOOOOOOOOO',$inum,$json,$output);
					$selectionone = $selection[1];
					if (in_array($selectionone,$kodi['queueitems'])) {
						$kodi['path'] = $kodi['queuerandom'];
						$output = kodi('queuerandom',null,$data);
						return;
					}
					// $json = '[{"jsonrpc":"2.0","method":"Playlist.Insert","params":[1,'.intval($inum).',{"file":"'.addcslashes($selection[1],'\\').'"}],"id":2209}]';
					$plid = (startsWith($selection[1],'smb://192.168.12.100/E/music/'))?0:1;
					$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add",
						"params" : { "item" : { "file" : "'.$selection[1].'" },"playlistid" : '.$plid.'}}';
    // "properties": ["playcount", "size"],
						
						// $json = '{
  // "jsonrpc": "2.0",
  // "method": "Files.GetFileDetails",
  // "params": {
    // "file": "'.$path.'",
    // "media": "video",
		// "properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year",
		// "rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid",
		// "tvshowid"]},
  // "id": 1}';
					
					$addq = $_Kodi->sendJson($json);
					$selected = [$kodi['plmode'],intval($arg),$selection[1],$selection[3],[$selection[1],$selection[4]],$selection[8]];
					// $plid = (startsWith($path,'smb://192.168.12.100/E/music/'))?0:1;

					$lastStatusPlayer[5] = $selected[3]." has been added to the ".(($plid == 1)?"video":"music")." queue!";
					// setVoiceStatus('');
					playerStatus('useArray');
					var_dump("33333333333333333",$json,$addq);

					// $json = '{"jsonrpc":"2.0","method":"Playlist.GetItems","id":"1742821847813","params":{"playlistid":'.$plid.',"properties":["title","showtitle","thumbnail","mediapath","file","resume","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid"]}}';
					// $output = $_Kodi->sendJson($json);
					// $output = renderQueue($output,$curitem);
					// var_dump('3333333333333333333333333333333',$output);
					break;
				} else if ($kodi['playrandom']) {
					$kodi['path'] = $kodi['playrandom'];
					$kodi['playrandom'] = false;
					kodiMsg($selection[3],"Randomly Playing");
					$autoq = false;
				}
				$pkey = false;
				if (is_numeric($arg) && isset($kodi['menu'][$arg])) {
					$selection = $kodi['menu'][$arg];
					if (is_array($selection[7])) { list($plid,$pkey) = $selection[7]; }
					 else if (is_array($selection[5])) { list($plid,$pkey) = $selection[5]; }
				}
				if (($kodi['plmode'] == 'queue' || $kodi['menu']['path'] == 'queue' || $kodi['path'] == 'queue') && $pkey !== false) {

					// $did = false;
					if (!isset($did)) {
						if (isset($data['user_id'])) {
							$did = $data['user_id'];
						} else if (isset($lastStatusData['user_id'])) {
							$did = $lastStatusData['user_id'];
						}
					}

					//$selected = ['queue',intval($arg),$selection[1],$selection[3],$selection[4],$selection[8]];
					$selected = ['queue',intval($arg),$selection[1],$selection[3],[$selection[1],$selection[4]],$selection[8]];
					$json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"position":'.$pkey.',"playlistid":'.$plid.'}},"id":174603}';
					// $output = $_Kodi->sendJson($json);
					$output = kodiPlay($json,$did,$selected,false);
					var_dump('444444',$json,$arg,$output);
					// return;
				} else {
					// $json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"position":'.$pkey.',"playlistid":'.$plid.'}},"id":174603}';

					// $output = $_Kodi->sendJson($json);
					$json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"file":"'.$selection[1].'"}},"id":"1"}';
					$output = kodiPlay($json,$did,$selected,$autoq);
					//$_Kodi->openFile(addcslashes($selection[1],'\\'));
				}

				// setVoiceStatus("Playing ".$selection[3]);
				// var_dump(fixKodiAudio());
				// if ($data) { $data = null; }
			}
		
		break;
		case "back":
			var_dump($kodi['hist']);
			$path = array_pop($kodi['hist']);
			if ($kodi['path'] == $path) {
				$path = array_pop($kodi['hist']);
			}
			if (!$path && !count($kodi['hist'])) {
				$path = $kodi['paths'][0];
			}
			
			// $did = false;
			if ($did === false && isset($data['user_id'])) {
				$did = $data['user_id'];
			}

			if (isset($kodi['uhist'][$did])) {
				if (!isset($kodi['uhpointer'][$did]) || $kodi['uhpointer'][$did] == null) {
					$uh = $kodi['uhist'][$did];
					//$uh = arrayfilter($uh,fn($k,$o)=>$o['filetype'] == 'directory');
					$uh = arrayfilter($uh,fn($k,$o)=> isset($o['filetype']) && $o['filetype'] == 'directory');
					$uhp = $kodi['uhpointer'][$did] = array_key_last($uh);
					$entry = array_pop($uh);
					$path = $entry['file'];
					if (isset($entry['page'])) { $gowspage = $entry['page']; }
					//$page = $entry['path'];
				} else {
					$uh = $kodi['uhist'][$did];
					$uh = arrayfilter($uh,fn($k,$o)=> isset($o['filetype']) && $o['filetype'] == 'directory');

					$uhp = intval($kodi['uhpointer'][$did]);
					$uhp = $uhk = getPrevKey($uhp, $uh);
					// while ($uhk == null && $uhp > -1) {
						// $uhp--;
						// $uhk = getPrevKey($uhp, $uh);
					// }
					$kodi['uhpointer'][$did] = $uhp;
					$entry = $uh[$uhk];
					$path = $entry['file'];
					if (isset($entry['page'])) { $gowspage = $entry['page']; }
				}
				// playerMsg("Back: $uhk $uhp $path");
			}

			if (!$path) {
				$f = kodiCurItem();
				// $path = dirname(kodiCurItem());
				if ($f) { $path = dirname(stripcslashes($f),2);	}
			}

			var_dump('PATH00000000000000000',$path);
		// case "search":
			// list($cat,$text) = $arg;
			// $did = (isset($data['user_id']))?$data['user_id']:false;
			// $output = searchArr($text,$cat);
			// // if (!$output)
			// file_put_contents('ksearch.json',json_encode([$text,$cat,$output], JSON_PRETTY_PRINT));
			// $output = renderDir($output,urlencode("search:$cat:$text"),kodiCurItem(),true);
			// var_dump($output);
		// break;
			
			if ($out = pathMask($path,$data,$did)) {
				$output = $out;
				break;
			}
			
			$media = (startsWith($path,'smb://192.168.12.100/E/music/'))?"music":"video";

			$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.addcslashes($path,'\\').'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
			$dirs = $_Kodi->sendJson($json);
			if ($dirs == NULL) {
				var_dump("!!!!!!!!!!!!!!!!!!!!!! $json");
				// $json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"video","properties":["title","file","playcount","lastplayed","mediapath","artist","duration","runtime","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
				$json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"'.$media.'","properties":["title","file","playcount","runtime","resume","lastplayed","mimetype","thumbnail","dateadded"],"sort":{"method":"none","order":"ascending"}}}';
				$dirs = $_Kodi->sendJson($json);
			}
				// var_dump("!================ $json");
			$output = renderDir($dirs,$path,kodiCurItem(),$data);
		break;
		case "showhist":
			$output = niceList($kodi['hist']);
			sendMsg('380675774794956800', $output);
			return;
		break;
		case "serveryt":
			if ($did === false && isset($arg) && $arg !== null) {
				$did = $arg;
			}
			var_dump('SHOWUHIST DID', $did);
			// if ($did == '380675774794956800') {
				// $output = print_r($kodi['uhist'],true);
				// sendReply('380675774794956800', $output);
			// }
			//if (!isset($kodi['uhist'][$did])) { return; }
			//$output = renderDir($kodi['uhist'][$did],"uhist:$did",kodiCurItem(),$data);
			// return;
			//$output = pathMask
			
										if ($output = pathMask('serveryt',$data,$did)) {
								break;
							}

		break;
		case "showuhist":
			if ($did === false && isset($arg) && $arg !== null) {
				$did = $arg;
			}
			var_dump('SHOWUHIST DID', $did);
			// if ($did == '380675774794956800') {
				// $output = print_r($kodi['uhist'],true);
				// sendReply('380675774794956800', $output);
			// }
			if (!isset($kodi['uhist'][$did])) { return; }
			$output = renderDir($kodi['uhist'][$did],"uhist:$did",kodiCurItem(),$data);
			// return;
		break;
		case "yts":
		case "ytsearch":
			$first = false;
			if (is_array($arg)) {
				$first = $arg[0];
				$arg = $arg[1];
			}
			
			$search = urlencode($arg);
			// $path = addcslashes("plugin://plugin.video.youtube/kodion/search/query/?q=$search&type=video",'//');
			$path = "plugin://plugin.video.youtube/kodion/search/query/?q=$search&type=video";
			$json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1743603938944","params":{"directory":"'.$path.'","media":"video","properties":["title","file","artist","duration","comment","description","runtime","playcount","mimetype","thumbnail","dateadded"]}}';
			$yts = $_Kodi->sendJson($json);
			$dir = [];
			$dir['result']['files'] = cacheYTNames($yts);
			//var_dump('YOUTUBESEARCHHHHHHHHHHHHHHHH',$path,$json,$yts);
			if ($first && ($key = searchForKey('file',$yts['result']['files'],'filetype',"first"))) {
				$play = $yts['result']['files'][$key]['file'];
				$json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"file":"'.$play.'"}},"id":"1"}';
				// if ($output = $_Kodi->sendJson($json) && isset($output['result'])) { return ($q)?"Added $vid to queue!":null; }
				$out = $_Kodi->sendJson($json);
				var_dump($out);
			}
			
			$output = renderDir($dir,$path,kodiCurItem(),$data);

		break;
		case "ytp":
		case "ytplay":
			preg_match(
			"/(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:\S*&)?vi?=|(?:embed|v|vi|user|shorts)\/))([^?&\"'>\s]+)/",
			$arg,$matches);
			if (isset($matches[1])) {
				$vid = $matches[1];
			} else {
				$output = "k-ytp video id error\n".print_r($arg,true)."\n".print_r($data['user_id'],true);
				return $output;
			}
			// var_dump($matches);
			$path = 'plugin://plugin.video.youtube/play/?video_id='.$vid;
			$json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"file":"'.$path.'"}},"id":"1"}';

			// $json = '{
				// "jsonrpc": "2.0",
				// "method": "Files.GetFileDetails",
				// "params": {
					// "file": "'.$path.'",
					// "media": "video",
					// "properties":["title","showtitle","thumbnail","file","resume","artist","genre","year",
					// "rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid",
					// "tvshowid"]},
				// "id": 1
			// }';

			// $res = $_Kodi->sendJson($json);
			// var_dump($res);exit;
			// var_dump(kodiPlay($json,$data, false, true));
			
			$q=false;
			$plid = (startsWith($path,'smb://192.168.12.100/E/music/'))?0:1;
			$state = getVidTimes(true);
			if ( $state !== "Stopped") { 
				$q=true;
				$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$path.'" },"playlistid" : '.$plid.'}}'; 
			// } else {
				// $kodi['plmode'] = 'yt';
			}
			
			if ($output = $_Kodi->sendJson($json) && isset($output['result'])) { return ($q)?"Added $vid to queue!":null; }
			//var_dump($json,$output);
			return;
			// usleep(250000);
			// $playfile = "unknown";
			// $json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
			// $res = $_Kodi->sendJson($json);
			// if ($res['result']) {
				// $kodi['playing'] = null;
				// $kodi['playfile'] = $res['result']['item']['file'];
				// $kodi['playpic'] = $res['result']['item']['thumbnail'];
				// $kodi['playfilename'] = $res['result']['item']['label'];
				// if (isset($res['result']['item']['label'])) {
					// $playfile = $res['result']['item']['label'];
				// } else {
					// $playfile = $res['result']['item']['file'];
				// }
			// }
			// setVoiceStatus("Playing ".$playfile);
		break;
		case "dmp":
		case "dmplay":
			preg_match(
			"~(?:https?:\/\/)?(?:www\.)?dai\.?ly(?:motion)?(?:\.com)?\/?.*(?:video|embed)?(?:.*v=|v\/|\/)([a-z0-9]+)~",
			$arg,$matches);
			var_dump('AND NO SCOOTERS',$matches);
			if (isset($matches[1])) {
				$vid = $matches[1];
			} else {
				$output = "k-dmp video id error\n".print_r($arg,true)."\n".print_r($data['user_id'],true);
				return $output;
			}
			// $path = 'plugin://plugin.video.youtube/play/?video_id='.$vid;
			$path = "plugin:\/\/plugin.video.dailymotion_com\/?url=$vid&mode=playVideo";
			$json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"file":"'.$path.'"}},"id":"1"}';

			// $json = '{
				// "jsonrpc": "2.0",
				// "method": "Files.GetFileDetails",
				// "params": {
					// "file": "'.$path.'",
					// "media": "video",
					// "properties":["title","showtitle","thumbnail","file","resume","artist","genre","year",
					// "rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid",
					// "tvshowid"]},
				// "id": 1
			// }';

			// $res = $_Kodi->sendJson($json);
			// var_dump($res);exit;
			// var_dump(kodiPlay($json,$data, false, true));
			
			$q=false;
			$plid = (startsWith($path,'smb://192.168.12.100/E/music/'))?0:1;
			$state = getVidTimes(true);
			if (	$state !== "Stopped") { 
				$q=true;
				$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$path.'" },"playlistid" : '.$plid.'}}'; 
			// } else {
				// $kodi['plmode'] = 'yt';
			}
			
			if ($output = $_Kodi->sendJson($json) && isset($output['result'])) { return ($q)?"Added $vid to queue!":null; }
			//var_dump($json,$output);
			return;
			// usleep(250000);
			// $playfile = "unknown";
			// $json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
			// $res = $_Kodi->sendJson($json);
			// if ($res['result']) {
				// $kodi['playing'] = null;
				// $kodi['playfile'] = $res['result']['item']['file'];
				// $kodi['playpic'] = $res['result']['item']['thumbnail'];
				// $kodi['playfilename'] = $res['result']['item']['label'];
				// if (isset($res['result']['item']['label'])) {
					// $playfile = $res['result']['item']['label'];
				// } else {
					// $playfile = $res['result']['item']['file'];
				// }
			// }
			// setVoiceStatus("Playing ".$playfile);
		break;
		case "twitch":
			// "~(?:https?:\/\/)?(?:www\.)?dai\.?ly(?:motion)?(?:\.com)?\/?.*(?:video|embed)?(?:.*v=|v\/|\/)([a-z0-9]+)~",
			preg_match(
			"/https:\/\/(?:clips|www)\.twitch\.tv\/(?:(?:[a-zA-Z0-9_]+\/clip\/)?)?([a-zA-Z-]+)/",
			$arg,$matches);
			var_dump('AND NO SCOOTERS',$matches);
			if (isset($matches[1])) {
				$vid = $matches[1];
				if ($vid == 'videos') {
					preg_match(
					"/https?:\/\/www\.twitch\.tv\/videos\/([0-9]{1,10})/",
					$arg,$matches);
					var_dump('AND SCOOTERS FLOOTERS5555555555555',$matches);
					if (isset($matches[1])) {
						$vid = "video_id=".$matches[1];
					}
					
				} else {
					$vid = "slug=$vid";
				}
			} else {
				$output = "k-twt video id error\n".print_r($arg,true)."\n".print_r($data['user_id'],true);
				return $output;
			}
			// $path = 'plugin://plugin.video.youtube/play/?video_id='.$vid;
			// $path = "plugin:\/\/plugin.video.dailymotion_com\/?url=$vid&mode=playVideo";
			$path = 'plugin://plugin.video.twitch/?mode=play&'.$vid;
			$json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"file":"'.$path.'"}},"id":"1"}';
			var_dump('TWATCHHF8DHF8D',$matches,$path,$json);

			// $json = '{
				// "jsonrpc": "2.0",
				// "method": "Files.GetFileDetails",
				// "params": {
					// "file": "'.$path.'",
					// "media": "video",
					// "properties":["title","showtitle","thumbnail","file","resume","artist","genre","year",
					// "rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid",
					// "tvshowid"]},
				// "id": 1
			// }';

			// $res = $_Kodi->sendJson($json);
			// var_dump($res);exit;
			// var_dump(kodiPlay($json,$data, false, true));
			
			$q=false;
			$plid = (startsWith($path,'smb://192.168.12.100/E/music/'))?0:1;
			$state = getVidTimes(true);
			if (	$state !== "Stopped") { 
				$q=true;
				$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$path.'" },"playlistid" : '.$plid.'}}'; 
			// } else {
				// $kodi['plmode'] = 'yt';
			}
			
			if ($output = $_Kodi->sendJson($json) && isset($output['result'])) { return ($q)?"Added $vid to queue!":null; }
			//var_dump($json,$output);
			return;
			// usleep(250000);
			// $playfile = "unknown";
			// $json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';
			// $res = $_Kodi->sendJson($json);
			// if ($res['result']) {
				// $kodi['playing'] = null;
				// $kodi['playfile'] = $res['result']['item']['file'];
				// $kodi['playpic'] = $res['result']['item']['thumbnail'];
				// $kodi['playfilename'] = $res['result']['item']['label'];
				// if (isset($res['result']['item']['label'])) {
					// $playfile = $res['result']['item']['label'];
				// } else {
					// $playfile = $res['result']['item']['file'];
				// }
			// }
			// setVoiceStatus("Playing ".$playfile);
		break;
		case "stop":
			if ($kodi['autoplay']) {
				$kodi['autoplay'] = "stop";
			}
			$output = $_Kodi->stop(); // ['result'];
			return;
		break;
	}
	
	$kodi['menu']['autoplay'] = $kodi['autoplay'];
	file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));	

	
	var_dump('KODI OUTPUT',gettype($output),$return);
	if ($return) {
		return $output;
	}
	if ($data == null) {
		if (!isset($data['interaction'])) { $output = false; }
	} else if ($data && $output) { 
		var_dump("WS OUTPUT FOR $action ".$kodi['plmode']);

		// if (isset($output['page'])) {
			// $gowspage = $output['page'];
			// unset($output['page']);			
		// }
		if (isset($gowspage) && $gowspage !== 0) {
			var_dump('$gowspage',$gowspage);
		}
		if (isset($output['result']) && $output['result'] == 'OK') {
			return "OK";
		}
		outputWorkspace($data,$output,'',$gowspage);
		if (!isset($data['interaction'])) { $output = false; }
	} else if (!$output) {
		var_dump("NOOOOO WS OUTPUT FOR ".$kodi['plmode']);
	}
	return $output;
}

$vidTimesData = [];

function getVidTimes($justState = false) {
	global $_Kodi;
	global $vidTimesData;
	$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[1,["speed","position","totaltime","time","percentage"]],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
	$props = $_Kodi->sendJson($json)['result'];
	if (!$props) {
		$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[0,["speed","position","totaltime","time","percentage"]],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
		$props = $_Kodi->sendJson($json)['result'];
	}
	$spd = $props['speed'];
	$pos = $props['position'];
	$pcnt = round($props['percentage'],2);


	//$state = (($pos == -1 && !$spd && $pcnt == 0)?"$spd $pos $pcnt Stopped":( $spd ))?"$spd $pos $pcnt Playing":"$spd $pos $pcnt Paused";
	// if ($pos == -1 && !$spd && $pcnt == 0) {
	if ( !$spd && $pcnt == 0) {
		$state = "Stopped";
	} else if (!$spd && ($pos > -1 || $pcnt > 0)) {
		$state = "Paused";
	} else {
		$state = "Playing";
	}
	// var_dump('GVIDTIMES ====================',$spd,$pos,$pcnt,$state);

	$vidTimesData[0] = $state;

	if ($justState) { return $state; }
	
	if ($state !== "Stopped" && $props && $props['time']) {
		$time = array_map('padInt',$props['time']);
		$curtime = implode(':',[$time['hours'],$time['minutes'],$time['seconds']]);
		$csecs = timeArrayToSecs($curtime);
		$cstime = $csecs-time();
		$time = array_map('padInt',$props['totaltime']);
		$endtime = implode(':',[$time['hours'],$time['minutes'],$time['seconds']]);
		$esecs = timeArrayToSecs($endtime);
	} else {
		$curtime = $endtime = '00:00:00';
		$csecs = $esecs = $pcnt = 0;
		$cstime = null;
	}

	$vidTimesData = [$state,$curtime,$endtime,$pcnt,$csecs,$esecs,$cstime,$props];
	return $vidTimesData;
	
}

function niceList($array,$appseperator = '',$binder = 'and') {
	if (!is_array($array)) { return $array; }
	// if ($highlight) { $array = highlightArr($array); var_dump($array); }
	// if ($highlight) { 
		// // $array = array_map(fn($v)=> ("**$v**"), $array);
		// $array = highlightArr($array); 
		// // $array = highlightArr($array); 
		// var_dump("highlight nicelist",$array); 
	// }
	
	$lastc = count($array);
	$last = array_pop($array);
	$output = implode(', '.$appseperator, $array);
	if ($output) {
		$output .= " $binder ".$appseperator;
	}
	$output .= $last;
	return $output;
}

function numberfy_array($array) {
	if (count($array) > 1) {
		array_walk($array, function(&$value, $key) { $value = "\n[".($key+1)."] ".preg_replace("/.$/",'',$value); });
		$array[0] = ":\n".$array[0];
	} else {
		$array[0] = lcfirst(preg_replace("/.$/",'',$array[0]));
	}
	return $array;
}

function get_date_diff( $time1, $time2, $precision = 2 ) {
	// If not numeric then convert timestamps
	if( !is_int( $time1 ) ) {
		$time1 = strtotime( $time1 );
	}
	if( !is_int( $time2 ) ) {
		$time2 = strtotime( $time2 );
	}

	// If time1 > time2 then swap the 2 values
	if( $time1 > $time2 ) {
		list( $time1, $time2 ) = array( $time2, $time1 );
	}

	// Set up intervals and diffs arrays
	$intervals = array( 'year', 'month', 'day', 'hour', 'minute', 'second' );
	$diffs = array();

	foreach( $intervals as $interval ) {
		// Create temp time from time1 and interval
		$ttime = strtotime( '+1 ' . $interval, $time1 );
		// Set initial values
		$add = 1;
		$looped = 0;
		// Loop until temp time is smaller than time2
		while ( $time2 >= $ttime ) {
			// Create new temp time from time1 and interval
			$add++;
			$ttime = strtotime( "+" . $add . " " . $interval, $time1 );
			$looped++;
		}

		$time1 = strtotime( "+" . $looped . " " . $interval, $time1 );
		$diffs[ $interval ] = $looped;
	}

	$count = 0;
	$times = array();
	foreach( $diffs as $interval => $value ) {
		// Break if we have needed precission
		if( $count >= $precision ) {
			break;
		}
		// Add value and interval if value is bigger than 0
		if( $value > 0 ) {
			if( $value != 1 ){
				$interval .= "s";
			}
			// Add value and interval to times array
			$times[] = $value . " " . $interval;
			$count++;
		}
	}

	// Return string with times
	return implode( ", ", $times );
}

function unvar_dump($str) {
    if (strpos($str, "\n") === false) {
        //Add new lines:
        $regex = array(
            '#(\\[.*?\\]=>)#',
            '#(string\\(|int\\(|float\\(|array\\(|NULL|object\\(|})#',
        );
        $str = preg_replace($regex, "\n\\1", $str);
        $str = trim($str);
    }
    $regex = array(
        '#^\\040*NULL\\040*$#m',
        '#^\\s*array\\((.*?)\\)\\s*{\\s*$#m',
        '#^\\s*string\\((.*?)\\)\\s*(.*?)$#m',
        '#^\\s*int\\((.*?)\\)\\s*$#m',
        '#^\\s*bool\\(true\\)\\s*$#m',
        '#^\\s*bool\\(false\\)\\s*$#m',
        '#^\\s*float\\((.*?)\\)\\s*$#m',
        '#^\\s*\[(\\d+)\\]\\s*=>\\s*$#m',
        '#\\s*?\\r?\\n\\s*#m',
    );
    $replace = array(
        'N',
        'a:\\1:{',
        's:\\1:\\2',
        'i:\\1',
        'b:1',
        'b:0',
        'd:\\1',
        'i:\\1',
        ';'
    );
    $serialized = preg_replace($regex, $replace, $str);
    $func = create_function(
        '$match', 
        'return "s:".strlen($match[1]).":\\"".$match[1]."\\"";'
    );
    $serialized = preg_replace_callback(
        '#\\s*\\["(.*?)"\\]\\s*=>#', 
        $func,
        $serialized
    );
    $func = create_function(
        '$match', 
        'return "O:".strlen($match[1]).":\\"".$match[1]."\\":".$match[2].":{";'
    );
    $serialized = preg_replace_callback(
        '#object\\((.*?)\\).*?\\((\\d+)\\)\\s*{\\s*;#', 
        $func, 
        $serialized
    );
    $serialized = preg_replace(
        array('#};#', '#{;#'), 
        array('}', '{'), 
        $serialized
    );

    return unserialize($serialized);
}

function guessTZ($in) {
	$tz = strtolower($in);
	// $list = array_keys(DateTimeZone::listAbbreviations());
	$list = [...array_keys(DateTimeZone::listAbbreviations()),...timezone_identifiers_list()];
	$llen = count($list);
	//$in = str_repeat($tz,$llen);
	$in = array_fill(0,$llen,$tz);
	$res = array_map('similar_text',$list,$in);
	$arr = array_combine($list,$res);
	array_multisort($arr);
	$topval = $arr[array_key_last($arr)];
	$ret = array_keys($arr,$topval);
	var_dump($ret);
	return (count($ret) == 1)?array_values($ret)[0]:$ret;
}


$discorderror = 0;
$dchkmsg = '';

function isRunning($pid){
    try{
        $result = shell_exec(sprintf("ps %d", $pid));
        if( count(preg_split("/\n/", $result)) > 2){
            return true;
        }
				error_log($pid);
				print_r($result);
    }catch(Exception $e){}
    return false;
}

$timezones = timezone_abbreviations_list();
$zones = [];
foreach ($timezones as $key => $code) {
	if(strlen($key) != 3) { continue;}
	$name = $code['0']['timezone_id'];
	if ($name == NULL) {continue;}
	$key = strtoupper($key);
	if (!in_array($name, array_keys($zones))) {
		$zones[$name] = $key;
	}
}

function contains($str, array $arr) {
	foreach($arr as $a) {
		if (stripos($str,$a) !== false) return true;
	}
	return false;
}

function endsWith( $haystack, $needle ) {
  $length = strlen( $needle );
  if( !$length ) {
    return true;
  }
  return substr( $haystack, -$length ) === $needle;
}

function stripos_all($haystack, $needle) {
    $offset = 0;
    $allpos = array();
    while (($pos = stripos($haystack, $needle, $offset)) !== FALSE) {
        $offset   = $pos + 1;
        $allpos[] = $pos;
    }
    return $allpos;
}

function startsWith( $haystack, $needle ) {
	$length = strlen( $needle );
	return substr( $haystack, 0, $length ) === $needle;
}

function stripstring($prefix, $str) {
	if (substr($str, 0, strlen($prefix)) == $prefix) {
		$str = substr($str, strlen($prefix));
	} 
	return $str;
}

$nicks = array();
$memberids = array();
function populateNicksIds() {
	global $discord;
	$members = $discord->guilds->get('id', '1119350254693785600')->members;
	global $nicks;
	global $memberids;
	foreach( $members as $member ) {
		$user = $member->user;
		// $avatar = $member->user->getAvatar();
		// $avatar = "https://cdn.discordapp.com/avatars/" . $user->id . "/" . $user->avatar . ".png";
		$nick = $user->username.'#'.$user->discriminator;
		$nicks[$nick] = $user->id;
		$memberids[$user->id] = [$nick];
		$memberids[$user->id]['user'] = $user;
		$memberids[$user->id]['avatar'] = $user->avatar;
	}
}

$channels = array();
$channelids = array();
function populateChannelsIds() {
	global $discord;
	$channeldata = $discord->guilds->get('id', 1119350254693785600)->channels;
	global $channels;
	global $channelids;
	foreach( $channeldata as $channel ) {
		$name = $channel->name;
		$id = $channel->id;
		$channels[$name] = $id;
		$channelids[$id] = $name;
	}
}

function getRandomWeightedElement(array $array) {
	$weightedValues = [];
	foreach($array as $key => $val) {
    if(is_numeric($key) || intval($val) == 1) { continue; }
    if (!isset($val)) { $val = 50; }
		$weightedValues[$key] = intval($val);
	}

	$rand = mt_rand(1, (int) array_sum($weightedValues));
	foreach ($weightedValues as $key => $value) {
		$rand -= $value;
		if ($rand <= 0) {
			return $key;
		}
	}
}

$lastwin = '';

function utd($f) {
	$f = str_replace('&nbsp;',' ',$f);
	return str_ireplace(['<b>','</b>'],'**',$f);
}

function jenc(string|array $string = "[]") {
	return json_encode($string, JSON_PRETTY_PRINT) ?? [];	
}

function linkify($u,$t = false) {
	$t = (!$t)?$u:$t;
	return "<a href='$u'>$t</a>";
}

function array_rebuild($array) {
	$newarray = [];
	foreach ($array AS $item => $weight) {
		if (is_numeric($item)) { continue; }
		if (!isset($array[$weight])) {
			$newarray[$item] = 50;
		} else {
			$newarray[$item] = $array[$item];
		}
	}
	return $newarray;
}

$playlistArray = [
	'showlist' => '📁',
	'playlist' => '📄',
	'bookmarks' => '🔖',
	// 'favs' => '⭐',
	'dice' => '🎲',
	'serveryt' => '🐧',
	'prev' => '⬅',
	'next' => '➡',
	'back' => '🔙',
	'refresh' => '🔃',
		'help' => '❔'
];
	
$playlistArray = $nums + $playlistArray;

$plmodeIcons = [
	'files' => '📁',
	'queue' => '📄',
	'bookmarks' => '🔖',
	'yt' => '<:youtube:1374404772718969082>',
	'movies' => '🎦',
	'music' => '🎶',
	'search' => '🔍',
	'tv' => '📺',
	'favs' => '⭐',
	'history' => '🕰️'
];

$playerArray = [
	'tprev' => '⏮',
	'rw' => '⏪',
	'stop' => '⏹️',
	'play' => '▶️',
	'pause' => '⏸️',
	'ff' => '⏩',
	'tnext' => '⏭️',
	'shuffle' => '🔀',
	'repeat' => '🔁',
	// 'movies' => '🎦',
	'tv' => '📺',
	'music' => '🎶',
	'yt' => ':youtube:1374404772718969082',
	'queuerandom' => '⁉️',
	'showpos' => '💠',
	'autoplay' => '🍏',
  'voldown' => '🔉',
	'volup' => '🔊',
	'volmute' => '🔇',
	'history' => '🕰️',
	'autoq' => '📀'
	// 'autoplayoff' => '🍎'
];

$emoteArray = $playerArray + $playlistArray;
array_shift($playlistArray);

function sendData($channel,$data, $mode) {
	$myToken = $GLOBALS['myToken'];
	if (!is_string($data)) { $data = json_encode($data); }
	$ch = curl_init('https://discord.com/api/v10/channels/' . $channel . "/$mode");
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data),
			'Authorization: Bot ' . $myToken
			)
	);
	$answer  = curl_exec($ch);
	$return = $answer;
	if (curl_error($ch)) {
			$return .= curl_error($ch);
	}
	return $return;
}

function menuComponent() {
	$json = [
			"content"=> "This is a message with components",
			"components" => [
					[
							"type" => 1,
							"components" => [
									[
											"type" => 2,
											"label" => "Click me!",
											"style" => 1,
											"custom_id" => "click_one"
									]
							]

					]
			]
	];
	return $json;
}

$gseek = false;

function setVoiceStatus($status = '',$myChannel = "1274001261976354886",$seek = false) {
	global $gseek;
	global $loop;
	global $kodi;
	global $timer;
	
	if ($myChannel == null) {
		$myChannel = "1274001261976354886";
	}
	
	if ($status && $timer !== NULL) { $loop->cancelTimer($timer); $timer = NULL; 
		var_dump("Clearing voice status clearer timer");
	} 

	if (!empty($status)) {
		$icon = "";
		$playfile = stripslashes($kodi['playfile']);
		if (!$playfile) { $playfile = kodiCurItem(); }
		if ($playfile) { $kodi['playfile'] = $playfile; }
		if (startsWith($playfile,'smb://192.168.12.100/E/music/')) {
			$icon = "music";
		} else if (startsWith($playfile,'plugin://plugin.video.youtube/play/')) {
			$icon = "yt";
		} else {
		// if (startsWith($playfile,'smb://192.168.12.100/E/music/')) {
			$icon = "tv";
		}
		global $plmodeIcons;
		$vicon = (isset($plmodeIcons[$icon]))?$plmodeIcons[$icon]:'';

		$status = $vicon.$status;
		
		var_dump($playfile." $icon $vicon | Setting voice status to ".$status);
	} else {
		var_dump("Clearing voice status.");
	}

	echo sendData($myChannel,array('status' => $status),'voice-status');
	if (!empty($status)) {
		if ($timer !== NULL) { $loop->cancelTimer($timer); $timer = NULL; }
		global $ttt;
		$ttt = [0,0];
	}
}							

$ttt = [0,0];

function seekAndSetTimeout($seek = false) {
	return; //no longer needed with the use of event polling 
	global $kodi;
	if ($kodi['plmode'] == 'music') {
		var_dump('PLMODE MUSIC ccccccccccccccccccc SEEK AND SET TIMEOUT',$seek);
		return;
	}
	var_dump('aaaaaaaaaaaaaaaaaa SEEK AND SET TIMEOUT',$seek);
	global $gseek;
	if (!$seek && $gseek && $gseek !== null) { $seek = $gseek; }
	var_dump('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb SEEK AND SET TIMEOUT',$gseek);
	global $ttt;
	global $loop;
	global $setstuff;
	global $timer;
	global $_Kodi;
	//$ttt = [0,0];


	if ($ttt[0] == 0 && $ttt[1] < 15) {
		$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":[1,["playlistid","speed","position","totaltime","time","percentage","shuffled","repeat","canrepeat","canshuffle","canseek","partymode"]],"id":11}'; //,{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","file","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":12}';
		$props = $_Kodi->sendJson($json);
		if ($props && isset($props['result']) && $props['result'] && $props['result']['speed']) {
			var_dump("SPEED VALUE00000000000000000000000000000000000000000000000",$props['result']['speed']);
			
		}
		if (!$props || !$props['result'] || !$props['result']['totaltime'] || !is_integer($props['result']['totaltime']['hours'])) {
			var_dump("Props failed",$props);
			$ttt[1]++;
			$setstuff = $loop->addTimer(intval(1), function () use ($seek) {
				seekAndSetTimeout($seek);
			});
			return;
		}
		$props = $props['result'];
		$time = (($props['totaltime']['hours']*60)*60);
		$time = $time+($props['totaltime']['minutes']*60);
		$ttime = $time+$props['totaltime']['seconds'];
		$time = (($props['time']['hours']*60)*60);
		$time = $time+($props['time']['minutes']*60);
		$time = $time+$props['time']['seconds'];
		if ($ttime == 0) {
			var_dump($ttime,"Total time is 0. Retrying...",$ttt);
			$ttt[1]++;
			$setstuff = $loop->addTimer(intval(1), function () use ($seek) {
				seekAndSetTimeout($seek);
			});
			return;
		} else {
			$ttt = [$ttime,0];
		}
	}

	var_dump($ttt);
	$ttime = $ttt[0];
	if (isset($ttt[2])) {
		$time = $ttt[2];
	}
	if ($seek && $seek !== null) {
		$t = $seek;
		if (is_array($t)) {
			var_dump("Seeking array position",$seek);
			if ($t['minutes'] > 3) { $t['minutes'] = $t['minutes']-3; }

			$json = ["id"=>0,"jsonrpc"=>"2.0","method"=>"Player.Seek","params"=>[1]];
			$json["params"][1] = ["time" => $t];

			$time = (($t['hours']*60)*60);
			$time = $time+($t['minutes']*60);
			$time = $time+$t['seconds'];
			$t = $time;
			$ttt[2] = $time;
			$json = json_encode($json);
		} else if (is_integer($t)) {
			$ttt[2] = $t;
			$time = $t;
			var_dump("Seeking int position",$seek);
			if ($t > 200) { $t = $t-180; }
			$newtime = explode(':',gmdate("H:i:s", $t));
			$json = ["id"=>0,"jsonrpc"=>"2.0","method"=>"Player.Seek","params"=>[1]];
			$json["params"][1] = ["time" => ['hours'=>intval($newtime[0]),'minutes'=>intval($newtime[1]),'seconds' => intval($newtime[2]),'milliseconds'=>00]];
			$json = json_encode($json);
		} else {
			$json = $t;
		}
		var_dump($json);
		$output = $_Kodi->sendJson($json);
		if (isset($output['error']) && $ttt[1] < 15) {
			var_dump($seek,$output);
			$ttt[1]++;
				$setstuff = $loop->addTimer(intval(1), function () use ($json) {
					seekAndSetTimeout($json);
				});
				return;

		}
		global $gseek;
		$gseek = null;
	} else {
		$time = 0;
		$ttime = $ttt[0];
	}
	var_dump($ttt);
	$ctime = $ttime - $time;
	var_dump("Total time $ttime, current time $time. Clearing voice status in $ctime seconds");
	$ttt = [0,0];
	loopy($ctime);

}

function readLines($file,$linecount = 10,$length = 80) {

	// //how many lines?
	// $linecount=5;

	// //what's a typical line length?
	// $length=40;

	// //which file?
	// $file="test.txt";

	//we double the offset factor on each iteration
	//if our first guess at the file offset doesn't
	//yield $linecount lines
	$offset_factor=1;


	$bytes=filesize($file);

	$fp = fopen($file, "r") or die("Can't open $file");



	$complete=false;
	while (!$complete)
	{
			//seek to a position close to end of file
			$offset = $linecount * $length * $offset_factor;
			fseek($fp, -$offset, SEEK_END);


			//we might seek mid-line, so read partial line
			//if our offset means we're reading the whole file, 
			//we don't skip...
			if ($offset<$bytes)
					fgets($fp);

			//read all following lines, store last x
			$lines=array();
			while(!feof($fp))
			{
					$line = fgets($fp);
					array_push($lines, $line);
					if (count($lines)>$linecount)
					{
							array_shift($lines);
							$complete=true;
					}
			}

			//if we read the whole file, we're done, even if we
			//don't have enough lines
			if ($offset>=$bytes)
					$complete=true;
			else
					$offset_factor*=2; //otherwise let's seek even further back

	}
	fclose($fp);

	//var_dump($lines);	
	return $lines;	
}


$stopLoop = true;
// $stopLoop = false;
$kodi['loopstat'] = ($stopLoop)?'disabled':'stopped';
$setstuff = NULL;
$loop = React\EventLoop\Loop::get();
$bucketTimer = $statusTimer = $timer = null;
$deathroll = 999;
$reactConnector = new \React\Socket\Connector(['dns' => '1.1.1.1', 'timeout' => 10]);
$connector = new \Ratchet\Client\Connector($loop, $reactConnector);


if (!($kevents = json_decode(file_get_contents('kevents.json'),true))) {
	$kevents = [];
}

$connector("ws://".$kodi['wsIP']."/jsonrpc?kodi")->then(function($conn) {
	$conn->on('message', function($msg) use ($conn) {
		$stamp = new DateTime()->format('Y-m-d H:i:s T');
		file_put_contents('wskodi.log', $stamp."|".$msg."\n", FILE_APPEND | LOCK_EX);
		
		global $kevents;
		global $gseek;
		global $kodi;
		global $_Kodi;
		global $statusTimer;
		global $loop;
		global $lastStatusPlayer;
		global $lastStatusData;
		global $ssaveron;

		//var_dump('6666666666666666666666666666666666666666666',$lastStatusData,$reaction);
		


		echo "Received: {$msg}\n";
		$msg = json_decode($msg,true);
		$kevents[$msg['method']] = $msg;
		$kevents['log'][] = $msg;
		file_put_contents('kevents.json',json_encode($kevents, JSON_PRETTY_PRINT));

		if ($msg['method'] == 'GUI.OnScreensaverActivated') {
			$ssaveron = true;
		}
		if ($msg['method'] == 'GUI.OnScreensaverDeactivated') {
			$ssaveron = false;
		}

		if ($msg['method'] == 'Player.OnPropertyChanged') {

            // "params": {
                // "data": {
                    // "player": {
                        // "playerid": 0
                    // },
                    // "property": {
                        // "repeat": "all"
                    // }
                // },


			if (isset($msg['params']['data']['player']['playerid'])) {
				$plid = $msg['params']['data']['player']['playerid'];
			}
			if (isset($msg['params']['data']['property']['shuffled'])) {
				$kodi['shuffle'][$plid] = $msg['params']['data']['property']['shuffled'];
			}

			if (isset($msg['params']['data']['property']['repeat'])) {
				// $plid = $msg['params']['data']['player']['playerid'];
				$kodi['repeat'][$plid] = $msg['params']['data']['property']['repeat'];
			}

			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
			return;
		}

		if ($msg['method'] == 'Player.OnStop' || $msg['method'] == 'Other.playback_stopped') {
			$playfile = stripslashes($kodi['playfile']);

			var_dump("FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",$kodi['playfile'],$playfile);

			// if (startsWith($playfile,'smb://192.168.12.100/E/music/')) {
				// var_dump("EEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEFFF",$kodi['playfile'],stripslashes($kodi['playfile']));
				
				// $json = '{  "jsonrpc": "2.0",  "method": "Files.GetFileDetails",  "params": {    "file": "'.$playfile.'",    "media": "music",		"properties":["title","artist","file","playcount","lastplayed","mimetype","thumbnail","dateadded"]},  "id": 1}';

				// // $json = '{"jsonrpc":"2.0","id":"1","method":"Files.GetDirectory","params":{"directory":"'.$path.'","media":"music",
				// // ,"sort":{"method":"none","order":"ascending"}}}';
				// // "properties":["title","showtitle","thumbnail","file","resume","artist","genre","year",
				// // "rating","album","track","runtime","duration","playcount","dateadded","episode","artistid","albumid",
				// // "tvshowid"]},
						
				// $songinfo = $_Kodi->sendJson($json);

				// //$key = searchForKey(stripcslashes($kodi['path']),$kcache['uhist'][$did],'file');

				// var_dump("FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",$kodi['playfile'],$songinfo);
			// }

			if ($msg['method'] == 'Player.OnStop' ) {
				if ($statusTimer !== NULL) { $loop->cancelTimer($statusTimer); $statusTimer = NULL; }
				global $kodi;
				// $kodi['dj'] = ($arg == 'start');
				$djdata = false;
				if ($kodi['dj']) {
					$author = getDid($kodi['djdata']);
					$djdata = $kodi['djdata'];
					$songs = $kodi['djsongs'] ?? [];
					$jmsg   = jsonWrap("[radio_dj] previously played songs: ".niceList($songs)." pick the next song. song cannot be one already played",$author,$djdata);
					if (is_array($jmsg)) {
						$jmsg = jenc($jmsg);
					}
				} else {
					//kodi('stop');
					$jmsg = "DJ mode stopped";
				
					if ( $kodi['autoplay'] !== false) {
						var_dump("KODI AUTOPLAY",$kodi['autoplay']);
						// if (($state = getVidTimes(true)) && $state == "Stopped") { kodi('next',null,$lastStatusData); }
						if ($kodi['autoplay'] === 'stop') {
							$kodi['autoplay'] = true;
						} else {
							//var_dump('next',"autoplay",$lastStatusData);
							kodi('next',"autoplay",$lastStatusData);
							return;
						}
					}
					setVoiceStatus('');
					qlMode();
					var_dump("KODI AUTOPLAY",$kodi['autoplay']);
					if ($kodi['playfile']) { $kodi['lastplayedfile'] = $kodi['playfile']; }
					// 		list($state,$play,$curtime,$endtime,$pcnt,$message) = $lastStatusPlayer;

					$lastStatusPlayer[0] = "Stopped";
					$lastStatusPlayer[1] = "";
					$lastStatusPlayer[2] = "00:00:00";
					$lastStatusPlayer[3] = "00:00:00";
					$lastStatusPlayer[4] = 0;
					$lastStatusPlayer[5] = "";
					playerStatus('useArray');
					$kodi['playfile'] = $kodi['playfilename'] = null;
				}

				//$msg = kodi('showlist',null,$data);
				if ($djdata) { sendReply($djdata, $jmsg); }
				return $jmsg;
			}
		}
		if ($msg['method'] == 'Player.OnPause') {
			// $lastStatusPlayer = [$state,$play,$pcnt,$curtime,$endtime];
			$lastStatusPlayer[0] = "Paused";
			if (!isset($msg['params']['data']['item']['title'])) {
				list($curitem,$kodi['playfilename']) = kodiCurItem(true);
			}
			if (!$kodi['playfilename']) {
				$kodi['playfilename'] = $msg['params']['data']['item']['title'];
			}
			$lastStatusPlayer[1] = $kodi['playfilename'];
			playerStatus('useArray');
			setVoiceStatus("Paused ".$kodi['playfilename']);
		} else if ($msg['method'] == 'Player.OnResume') {
			if (!$kodi['playfilename']) {
				list($curitem,$kodi['playfilename']) = kodiCurItem(true);
			}
			setVoiceStatus("Playing ".$kodi['playfilename']);
			$lastStatusPlayer[0] = "Playing";
			$lastStatusPlayer[1] = $kodi['playfilename'];
			playerStatus('useArray');
		} 
		if ($msg['method'] == 'Application.OnVolumeChanged') {
			$kodi['vol'] = array_values($msg['params']['data']);
			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
			return;
		}
		
		if ($msg['method'] == 'Player.OnPlay') {
			$kodi['plid'] = $plid = $msg['params']['data']['player']['playerid'];
			var_dump("onplay33333333333333333333333333333333333333333333333333333333333333333333333!!!!!!!!!!!!!!!!!",$msg,$plid);
			fixKodiAudio();

			$gotten = false;
			// foreach ([0,1] AS $plid) {
			global $_Kodi;
			if (!isset($kodi['shuffle'][$plid]) || $kodi['shuffle'][$plid] === null) {
				$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","shuffled","repeat","canshuffle"]],"id":11}';
				// global $_Kodi;
				if (($props = $_Kodi->sendJson($json)) && isset($props['result']['shuffled'])) { 
					$kodi['shuffle'][$plid] = $props['result']['shuffled'];
					$gotten = true;
					var_dump('onplay set| shuffle, $plid',$kodi['shuffle'][$plid],$plid); //,$props);
				}
			}
			if (!isset($kodi['repeat'][$plid]) || $kodi['repeat'][$plid] === null) {
				$json = '{"jsonrpc":"2.0","method":"Player.GetProperties","params":['.$plid.',["playlistid","shuffled","repeat","canshuffle"]],"id":11}';
				if ($props = $_Kodi->sendJson($json) && isset($props['result']['repeat'])) {
					$kodi['repeat'][$plid] = $props['result']['repeat'];
					$gotten = true;
					var_dump('onplay set| repeat, $plid',$kodi['repeat'][$plid],$plid); //,$props);
				}
			}

			list($curitem,$kodi['playfilename']) = kodiCurItem(true);
			if ($kodi['playing'] !== null) {
				$kodi['menu']['pointer'] = $kodi['playing'];
			}
			setVoiceStatus("Playing ".$kodi['playfilename']);
			var_dump("55555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555555 SET VOICE STATUS");
			qlMode();
			if (!startsWith($curitem,'plugin://plugin.video.youtube/play/?video_id')) {
				$pmsg = playerMsg();
				var_dump("playermsg", $pmsg);
				if ($pmsg == "Loading...") {
					playerMsg("Loaded!",false);
					$gotten = true;
				};
				$lastStatusPlayer[0] = "Playing";
				$lastStatusPlayer[1] = $kodi['playfilename'];
				playerStatus('useArray'); $gotten = false;
				if (isset($kodi['onPlay']) && $kodi['onPlay']) {
					kodi($kodi['onPlay'],null,$kodi['data']);
					$kodi['onPlay'] = false;
					unset($kodi['data']);
				}

				if (!$kodi['playfilename'] && isset($msg['params']['data']['item']['title'])) {
					$kodi['playfilename'] = $msg['params']['data']['item']['title'];
				}
				// var_dump('SEEK AND SET TIMEOUT 1',$gseek);
				// if ($gseek !== null) { seekAndSetTimeout($gseek); }
			
				if ($gotten) { refreshPlayerStatus(); }

			}
		}
		if ($msg['method'] == "Player.OnAVStart" || $msg['method'] == 'Other.playback_started') {
			$gotten = false;
			if (isset($msg['params']['data']['player']['playerid'])) {
				$kodi['plid'] = $plid = $msg['params']['data']['player']['playerid'];
			}
			// if (!$kodi['playfilename'] && isset($msg['params']['data']['item']['title'])) {
			list($curitem,$kodi['playfilename']) = kodiCurItem(true);
			if (!$kodi['playfilename'] && isset($msg['params']['data']['item']['title'])) {
				$kodi['playfilename'] = $msg['params']['data']['item']['title'];
			}

			$pmsg = playerMsg();
			var_dump("playermsg_other", $pmsg);
			if ($pmsg == "Loading...") {
				playerMsg("Loaded :)",false);
				$gotten = true;
			};
			statusLooper();

			// $lastStatusPlayer[0] = "Playing";
			// $lastStatusPlayer[1] = $kodi['playfilename'];
			// playerStatus('useArray'); $gotten = false;
			// if (isset($kodi['onPlay']) && $kodi['onPlay']) {
				// kodi($kodi['onPlay'],null,$kodi['data']);
				// $kodi['onPlay'] = false;
				// unset($kodi['data']);
			// }

			// var_dump('SEEK AND SET TIMEOUT 1',$gseek);
			// if ($gseek !== null) { seekAndSetTimeout($gseek); }
			// if ($gotten) { refreshPlayerStatus(); }

			// $gotten = true;
			if (isset($kodi['onPlay']) && $kodi['onPlay']) {
				kodi($kodi['onPlay'],null,$kodi['data']);
				$kodi['onPlay'] = false;
				unset($kodi['data']);
			}
			if ( $lastStatusPlayer[0] !== "Playing" ) {
				$lastStatusPlayer[0] = "Playing";
				$lastStatusPlayer[1] = $kodi['playfilename'];
				playerStatus('useArray');
			}
			var_dump('22222222222222222SEEK AND SET TIMEOUT',$gseek);

			// if ($gseek !== null) { seekAndSetTimeout($gseek); }
			// fixKodiAudio();
		}
		
		if ($msg['method'] == "Player.OnAVChange") {
			if (isset($msg['params']['data']['player']['playerid'])) {
				$kodi['plid'] = $plid = $msg['params']['data']['player']['playerid'];
			}
			if (!$kodi['playfilename'] && isset($msg['params']['data']['item']['title'])) {
				$kodi['playfilename'] = $msg['params']['data']['item']['title'];
			}
		}			
	});
	$conn->send('Hello World!');
}, function ($e) {
	var_dump($e);
	$stamp = new DateTime()->format('Y-m-d H:i:s T');
	file_put_contents('wskodi.log', $stamp." | --ERR--".print_r($e,true)."\n", FILE_APPEND | LOCK_EX);

	echo "Could not connect: {$e->getMessage()}\n";
	exit;
});

function refreshPlayerStatus($data = 'return') {
	global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); return playerStatus('useArray',$data);
}

function arrayfilter(array $array, callable $callback = null) {
    if ($callback == null) {
        $callback = function($key, $val) {
            return (bool) $val;
        };
    }
    $return = array();
    foreach ($array as $key => $val) {
        if ($callback($key, $val)) {
            $return[$key] = $val;
        }
    }
    return $return;
}

function checkRateLimit() {
	$limit = 0;
	$msg = readLines('kodikitty.log',30);
	$matches = preg_grep('/expecting rate limit|hit rate-limit: RATELIMIT Non-global, retry after/',$msg);
	if ($matches) { 
		$match = array_pop($matches);
		preg_match('/(?:timer interval\s|retry after\s)([0-9.]+\s(?:s|ms))/',$match,$limit);
		// $msg = json_encode($matches, JSON_PRETTY_PRINT); }
		//$msg = json_encode([$limit], JSON_PRETTY_PRINT); }
		$msg = $limit[1];
		if (endsWith($msg, 'ms')) { $msg = intval($msg)/1000; } else { $msg = intval($msg); }
		$limit = $msg;
	}
	//if (!$matches) { $msg = json_encode($msg, JSON_PRETTY_PRINT); }
 // $http = $discord->http; // Access the HTTP client under Discord Client
// $msg .= $rl = "\n RL:".$http->rateLimit."\n";
// var_dump($rl);


// file_put_contents($GLOBALS['filePrefix'].'http.json',json_encode((array) 
//$http->rateLimit, JSON_PRETTY_PRINT));

// $msg = $http->rateLimit;
	// var_dump($msg);
	return $limit;
// sendReply($data, $msg);
}

function showHelp($data) {
	$help = " Reactions:
'📁': 	'show local file list' 
 '📄': 	'queue lists' 
 '🔖': 	'bookmarks' 
 '⭐': 	'favorites' 
 '🎲': 	'play random video' 
 '⁉️': 	'queue random video' 
 '⬅': 	'prev' 
 '➡': 	'next' 
 '🔙': 	'back' 
 '🔃' 	'refresh directory listing' 
 '📁': 	'local files' 
 '<:youtube:1374404772718969082>': 	'youtube' 
 '🔍': 	'search (wip)' 
 '🎦': 	'movies' 
 '🎶': 	'music' 
 '📺': 	'tv shows' 
 '🕰️'  	'media history' 
 '⏮': 	'previous track' 
 '⏪': 	'skip back 20 seconds' 
 '⏹️': 	'stop playback' 
 '▶️': 	'play/resume' 
 '⏸️': 	'pause' 
 '⏩': 	'skip forward 20 seconds' 
 '⏭️': 	'next track' 
 '🔀': 	'toggle shuffle' 
 '🔁': 	'cycle repeat' 
 '💠': 	'refresh status' 
 '🍏': 	'autoplay next file in folder' 
 '🔉':   'vol down' 
 '🔊': 	'vol up' 
 '🔇': 	'vol mute' 
 '📀': 	'cycle queue mode' 
 '❔':   'help'
 
 Commands:
 kodi - refreshes interface 
 queue/unqueue/queue show - queue list management
 fav/unfav/favs - favorites management
 search (@tv/movies/music) <keywords> - search local media
 seek (nn%, +/-nn hours/minutes/seconds, xx:xx:xx)";
	// function wsPages($data,$lines = false,$pag = 0,$name = '') {
	wsPages($data,splitMsg($help));
	
}

$reactioned = false;

function reactionAction($emojianame,$reaction,$name = '') {
	global $wsLines;
	global $kodi;
	$page = 1;
	global $reactioned;
	$reactioned = true;

	if ($name == 'player') {
		global $lastStatusData;
		// var_dump('6666666666666666666666666666666666666666666',$lastStatusData,$reaction);
		$lastStatusData = $reaction;
		$wsarrname = 'player';
	}

	var_dump($reaction['channel_id'],$emojianame);
	$cid = $reaction['channel_id'];

	$did = false;
	if (isset($reaction['author']['id'])) {
		$did = $reaction['author']['id'];
	} else if (isset($reaction['user_id'])) {
		$did = $reaction['user_id'];
	} else {
		//var_dump('$reaction',$reaction);
	}
	//$did = $reaction['user_id'];
	if ($did && isset($kodi['menu']['did']) && $kodi['menu']['did'] != $did) { 
		$kodi['menu']['did'] = $did; 
		file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));	
	}
	if (isset($wsLines[$cid][$name]['page'])) {
		$page = intval($wsLines[$cid][$name]['page']);
	}
	if (is_numeric($emojianame)) {
		$inum = intval($emojianame);
		$key = ((($page-1)*10)+($inum - 1));
			
		kodi('select',$key,$reaction);
		return;
	}
	
	switch ($emojianame) {
		case "tv":
			kodi('shows',null,$reaction);
		break;
		case "movies":
			kodi('movies',null,$reaction);
		break;
		case "back":
			kodi('back',null,$reaction);
		break;
		case "autoplay":
			$kodi['autoplay'] = !$kodi['autoplay'];
			$kodi['menu']['autoplay'] = $kodi['autoplay'];
			file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));	
			qlMode();
			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "shuffle":
			kodiShuffle(null, 'toggle');
			// $plid = (isset($kodi['plid']))?$kodi['plid']:1;
			// $kodi['shuffle'][$plid] = !$kodi['shuffle'][$plid];
			// $kodi['menu']['shuffle'] = $kodi['shuffle'];
			// file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));	
		
			// global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "repeat":
			kodiRepeat(null, 'toggle');
			// global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "volup":
			kodiVol("up");
			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "voldown":
			kodiVol("down");
			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "volmute":
			kodiVol("mute");
			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "autoq":
			//$kodi['autoq'] = $kodi['menu']['autoq'] = !$kodi['autoq'];
			$bool = $kodi['autoq'];
			if (!$bool || $bool === "play") { $bool = "queue";} else
			if ($bool === 1 || $bool === true || $bool === "queue") { $bool = "next";} else 
			if ($bool === "next") { $bool = "play";}  
			// if ($bool === "") { $bool = "all";}  

			$kodi['autoq'] = $bool;
			var_dump("autoq",$kodi['autoq']);
			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "bookmarks":
			kodi('bookmarks',[null,$did,false],$reaction);
		break;
		case "favs":
			kodi('favs',[null,$did,false],$reaction);
		break;
		case "history":
			kodi('showuhist',$did,$reaction);
		break;
		case "serveryt":
			kodi('serveryt',$did,$reaction);
		break;
		case "yt":
			kodi('ytsearch',null,$reaction);
		break;
		case "tprev":
			kodi('prev',null,$reaction);
		break;
		case "tnext":
			kodi('next',null,$reaction);
		break;
		case "prev":
			wsPages($reaction,NULL,'b');
		break;
		case "next":
			wsPages($reaction,NULL,'n');
		break;
		case "playlist":
			kodi('queue',null,$reaction);
		break;
		case "queuerandom":
			kodi('queuerandom',null,$reaction);
		break;
		case "refresh":
			kodi('refresh',$did,$reaction);
		break;
		case "showpos":
			// kodi('showlist',null,$reaction);
			global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); $msg = playerStatus('useArray');
		break;
		case "showlist":
			kodi('showlist',null,$reaction);
		break;
		case "sources":
			kodi('sources',null,$reaction);
		break;
		case "music":
			kodi('music',null,$reaction);
		break;
		case "stop":
			kodi('stop',null,$reaction);
		break;
		case "rw":
			$arg  = ['time',[" -25 seconds"]];
			kodi("seek",$arg,$reaction);
		break;
		case "ff":
			$arg  = ['time',[" +25 seconds"]];
			kodi("seek",$arg,$reaction);
		break;
		case "dice":
			// $arg  = ['time',["+25 seconds"]];
			kodi("play",'random',$reaction);
		break;
		case "play":
			kodi('btn','play',$reaction);
		break;
		case "pause":
			kodi('btn','pause',$reaction);
		break;
		case "help":
			showHelp($reaction);
		break;
	}
}

function batchBucket() {
	global $kodi;
	global $_Kodi;
	// global $bucketLoop;
	$count = 0;
	if (!count($kodi['buckets'])) {
		return false;
	}
	
	foreach ($kodi['buckets'] AS $bkey => $bucket) {
		//$json = $bucket['json'];
		while (count($kodi['buckets'][$bkey]) && $count < 50) {
			$json = array_shift($kodi['buckets'][$bkey]);
			//$assets = $item['assets'];
			$output = $_Kodi->sendJson($json);
			var_dump($count,"bucket",$bkey,$json,$output);
			$count++;
		}
		if (!count($kodi['buckets'][$bkey])) {
			unset($kodi['buckets'][$bkey]);
		}
		
		if (!count($kodi['buckets'])) {
			return false;
		}
		
	}
	
	if (!count($kodi['buckets'])) {
		return false;
	}
	return true;
}
	
	

function kodiPlay($json,$data = false,$selected = false, $autoq = true) {
	global $_Kodi;
	global $kodi;
	global $lastStatusData;
	global $lastStatusPlayer;


	var_dump('$selected1',$selected);
	$state = getVidTimes(true);
			
	$q=false;
	$path = $selected[2];
	
	// $plid = ($selected[0] == 'music' || startsWith($path,'smb://192.168.12.100/E/music/'))?0:1;
	$plid = (startsWith(stripcslashes($path),'smb://192.168.12.100/E/music/'))?0:1;

	if ($kodi['autoq'] === 'play') { $autoq = false; }

	var_dump("noq selected 2",$kodi['noq'],$selected[2]);
	if ($kodi['noq'] && $kodi['noq'] == $selected[2]) {
		$autoq = false;
		$kodi['noq'] = false;
	}
	var_dump('$selected3',$selected);
	qlMode();
	$qnext = '';
	if ($autoq && $selected[0] !== 'queue' && $state = getVidTimes(true) !== "Stopped") { 
		$q=true;
		$json = '{ "id" : 1,"jsonrpc" : "2.0","method" : "Playlist.Add","params" : { "item" : { "file" : "'.$path.'" },"playlistid" : '.$plid.'}}';
		
		if ($kodi['autoq'] === 'next') {		
			$qnext = ' next';
			$json = '[{"jsonrpc":"2.0","method":"Playlist.Insert","params":['.$plid.','.intval($kodi['quindex'][$plid])+1 .',{"file":"'.addcslashes($path,'\\').'"}],"id":2209}]'; 
		}
	}
	
	var_dump('$selected2',$selected);

	// $kodi['plmode'] = 'yt';
	var_dump('KODI PLAY -----------------------',$q,$selected,$json,$state);

	// $kodi['playfile']	
	
	// $json = '{"jsonrpc":"2.0","method":"Player.Open","params":{"item":{"position":'.$pkey.',"playlistid":'.$plid.'}},"id":174603}';
	$did = false;
	if ($data && (is_string($data) ||is_numeric($data))) {
		$did = $data;
	} else if (isset($data['user_id'])) {
		$did = $data['user_id'];
	} else if (isset($data['author']['id'])) { 
		$did = $data['author']['id'];
	} else if (isset($lastStatusData['user_id'])) {
		$did = $lastStatusData['user_id'];
	} else if (isset($lastStatusData['author']['id'])) {
		$did = $lastStatusData['author']['id'];	
	} else if (isset($kodi['menu']['did']) && $kodi['menu']['did']) {
		$did = $kodi['menu']['did'];
	}

	var_dump('$selected4',$selected);

	if ($did) { // || !in_array($selected[1],array_column($kodi['uhist'][$did],1)))) {
		if (!isset($kodi['uhist'][$did])) { // || !in_array($selected[1],array_column($kodi['uhist'][$did],1)))) {
			$kodi['uhist'][$did] = [];
		}
		// $kodi['uhist'][$did][] = [...$selected];
		$key = Date('U'). rand(100,999);
		$kodi['uhist'][$did][$key] = $selected[5];
//		$kodi['uhist'][$did][$key]['key'] = $key;
		file_put_contents('uhist.json',json_encode($kodi['uhist'], JSON_PRETTY_PRINT));
	}
	
	if (!$q && $selected) {
		list($kodi['plmode'],$kodi['playing'],$kodi['playfile'],$kodi['playfilename'],$kodi['resumeData']) = $selected;
	}
	if ($q) {
		$lastStatusPlayer[5] = $selected[3]." has been added$qnext to the ".(($plid == 1)?"video":"music")." queue!";
		// setVoiceStatus('');
		playerStatus('useArray');
	} else {
		$kodi['playingmenu'] = $kodi['menu'];
	}

	// $output = $_Kodi->sendJson($json);
	if ($output = $_Kodi->sendJson($json) && isset($output['result']) && $output['result'] !== null) { return ($q)?"Added $vid to queue!":null; }
	if (($ret = kodiError($output)) !== false) { return $ret; }
	// return $kodi['playfilename'];
	return;
}

function playerMsg($msg = false,$flushStatus = true) {
	global $lastStatusPlayer;
	if (!$msg) {
		return trim($lastStatusPlayer[5]);
	}
	$lastStatusPlayer[5] = trim($msg);
	if ($flushStatus) { playerStatus('useArray'); }
	return;
}

function kodiMsg($msg,$title = "",$timeout = 5000) {
	if (!trim($msg)) { return; }
	$json = '{"jsonrpc":"2.0","method":"GUI.ShowNotification","params":["'.$title.'","'.$msg.'","",'.intval($timeout).'],"id":8}';
	global $_Kodi;
	// $out = json_encode($_Kodi->sendJson($json));
	var_dump($json,$out = $_Kodi->sendJson($json));
	// sendReply($data, print_r($out,true));
	return;
}




function updateKTimes() {
	global $lastStatusPlayer; list($lastStatusPlayer[0],$lastStatusPlayer[2],$lastStatusPlayer[3],$lastStatusPlayer[4]) = getVidTimes(); return playerStatus('useArray');
}

function bucketLooper() {
	//global $stopLoop;
	global $loop;
	$looper = 'bucketTimer';
	global $$looper;
	global $kodi;
	//global $lastStatusPlayer; 
	$wait = 1;
	//if (($dq = dQueue()) === 0) {$msg = updateKTimes(); } else { var_dump('DQ',$dq); $wait *= $dq; }
	//if ($stopLoop) {$kodi['loopstat'] = 'disabled'; return; }
	// var_dump($msg);
	if (!batchBucket()) {
		if ($$looper !== NULL) { $loop->cancelTimer($$looper); $$looper = NULL; }
		playerMsg("Bucket empty");
		return;
	}
	// if ($lastStatusPlayer[0] == "Playing") {
		// if ($limit = checkRateLimit()) {
			// $wait += 10+($limit*2);
		// }
		//$kodi['loopstat'] = "looping| $dq | $wait";
		var_dump("HOLD ON WAIT A BUCKETY MINUTE $wait");
		loopyLoop($wait,'bucket');
	// } else {
		// $kodi['loopstat'] = 'stopped';
	// }
	return $wait;
}

function statusLooper() {
	global $stopLoop;
	global $kodi;
	global $lastStatusPlayer; 
	global $reactioned;
	$wait = ($reactioned)?20:10;
	$dq = dQueue();
	$limit = checkRateLimit();
	if (!$reactioned && $dq === 0 && $limit == 0) {$msg = updateKTimes(); } else { var_dump('DQ',$dq); $wait *= $dq; }
	if ($stopLoop) {$kodi['loopstat'] = 'disabled'; return; }
	// var_dump($msg);
	
	if ($lastStatusPlayer[0] == "Playing") {
		if ($limit) {
			$wait += 10+($limit*2);
		}
		$kodi['loopstat'] = "looping | $dq $limit | $wait";
		var_dump("HOLD ON WAIT A MINUTE $wait",$reactioned);
		loopyLoop($wait);
	} else {
		$kodi['loopstat'] = 'stopped';
	}
	$reactioned = false;
	return $wait;
}

function loopyLoop($time,$looper = 'status') {
	global $loop;
	global ${$looper."Timer"};
	if (${$looper."Timer"} !== NULL) { $loop->cancelTimer(${$looper."Timer"}); ${$looper."Timer"} = NULL; }

	${$looper."Timer"} = $loop->addTimer(intval($time), function () use ($loop, $looper) {
		$func = $looper."Looper";
		$func();
	});
	
}

function loopy($time) {
	global $loop;
	global $timer;
	if ($timer !== NULL) { $loop->cancelTimer($timer); $timer = NULL; }

	$timer = $loop->addTimer(intval($time), function () use ($loop, $timer) {
		setVoiceStatus('');
	});
	
}

// Check for an error status and redirect error message parts to admin/user appropriately
function kodiError($return) {
	$error = '';
	if (isset($return['error'])) {
		$json = '';
		if (isset($return['json'])) {
			$json = "\n".$return['json'];
		}
		var_dump($return['error']);
		sendMsg('380675774794956800',  print_r($return['error'],true).$json);
		if (!is_array($return['error'])) { $error = "\n".$return['error']; }
		global $kodi;
		$kodi['autoplay'] = false;
		$kodi['menu']['autoplay'] = $kodi['autoplay'];
		file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));	

		return "There was a problem processing your request!".$error;
	}
	return false;
}

function dQueue() {
	global $discord;
	$http = $discord->http; // Access the HTTP client under Discord Client
	$httpa = jenc( (array) $http);
	if (preg_match('/"\\\u0000\*\\\u0000queue": ({.*}),/',$httpa,$mq)) {
		var_dump('MQ',$mq);
	}
	if (preg_match('/"\\\u0000\*\\\u0000waiting": ([0-9]*),/',$httpa,$ma)) {
		return intval($ma[1]);
	}
	return false;	
}

function numToKey($arg,$data) {
	if (is_numeric($arg)) {
		global $wsLines;
		$cid = $data['channel_id'];
		// $did = $data['user_id'];
		if (isset($wsLines[$cid]['']['page'])) {
			$page = intval($wsLines[$cid]['']['page']);
		}
		$inum = intval($arg);
		$arg = ((($page-1)*10)+($arg - 1));
			
		// kodi('select',$key,$reaction);
		// return;
	}
	return $arg;
}


$discord = new Discord([
	'token' => $GLOBALS['myToken'],
	'storeMessages' => true,
	'loadAllMembers' => true,
	'intents' => 53608447,
	'loop' => $loop
]);

$rateLimit = null;

$discord->on('init', function (Discord $discord) {
	$activity = $discord->factory(\Discord\Parts\User\Activity::class, 
		['name' => $GLOBALS['activityName'], 'type' => 2] //, 'name' => 'Test']
	);
	$discord->updatePresence($activity);

	populateChannelsIds();
	populateNicksIds();

	connectKodi($GLOBALS['amDev']);

	$botready = "KodiKitty is ready!";
	echo $botready;

	$cmds = [];
	// var_dump($discord->application);
	$cmds['kodi'] = CommandBuilder::new()
		->addOption((new Option($discord))
			->setName('content')
			->setDescription('Commands to execute')
			->setType(Option::STRING)
			->setRequired(false)
		)
		->setName('kodi')
		->setContext([0,1,2])
		->setDescription('Kodi Controls');
		// ->toArray();

	// $cmds['gpt'] = CommandBuilder::new()
		// ->addOption((new Option($discord))
			// ->setName('text')
			// ->setDescription('Text Input')
			// ->setType(Option::STRING)
			// ->setRequired(true)
		// )
		// ->setContext([0,1,2])
		// ->setName('gpt')
		// ->setDescription('talk to ai')
		// ->toArray();

	// Get all global commands
	$discord->application->commands->freshen()->then(function ($commands) use ($discord, $cmds) { 

	$commandExists = false;
	$ccmds = [];
	foreach ($commands as $rcmd) {
			$ccmds[$rcmd->name] = $rcmd;
	}

	$cmdupdate = [];
	foreach($cmds AS $cname => $cmd) {

	if (in_array($cname,array_keys($ccmds))) {
		if (in_array($cname,$cmdupdate)) {
			$commandIdToUpdate = $ccmds[$cname]['id'];
			try {
				// $discord->application->commands->save(
						// $cmd, // Convert builder to array for API
						// $commandIdToUpdate // Specify the command ID
				// )->then(function (Command $command) {
						// echo "Global command updated: " . $command->name . PHP_EOL;
				// })->otherwise(function (\Throwable $e) {
						// echo "Error updating global command: " . $e->getMessage() . PHP_EOL;
				// });

				/** @var CommandRepository $commandRepository */
				//$commandRepository = $discord->application->commands;

				$commandPart = $discord->application->commands->create($cmd->toArray());

				// 3. Save the Command part (Discord handles the upsert/update)
				$commands->save($commandPart)->then(function (Command $command) {
						echo "Command '{$command->name}' updated successfully!" . PHP_EOL;
				});

			} catch (\Exception $e) {
					echo "Error updating command: " . $e->getMessage() . "\n";
			}


				// $promise = $discord->application->commands->fetch($commandIdToUpdate);\n    }\n\n    $promise->done(function (Command $command) use ($discord) {\n     				
				//1404265803242668122
				// $discord->application->commands->save($cmd);
					// echo "Successfully updated global command: /{$cmd['name']}\n";
			
		} else {		
			echo "\n Skipping $cname...\n";
		}
		continue;
		
	}

			try {
			 $discord->application->commands->save(
						$discord->application->commands->create($cmd->toArray()					)
				);
					echo "Successfully registered global command: /{$cmd['name']}\n";
			} catch (\Exception $e) {
					echo "Error registering command: " . $e->getMessage() . "\n";
			}
		}
	});











// // <?php
// // use Discord\Builders\MessageBuilder;
// // use Discord\Parts\Interactions\Interaction;
// // use React\Promise\PromiseInterface;

// // --- Handling application command interactions ---
// $discord->listenCommand('kodi', function (Interaction $interaction) use ($discord) {
    // // Acknowledge the interaction immediately with a "Thinking..." state.
    // $interaction->acknowledge();

    // // Get the 'content' option from the user's interaction.
    // $input = $interaction->data->options->get('content')->value ?? "seek";

    // // This section is now inside a promise to handle asynchronous operations.
    // // We are passing the necessary variables to the .then() block.
    // // The `msgCreate` function call is placed here to execute after the deferReply.
    // $iarr = json_decode(json_encode($interaction), true);
    // $iarr['user_id'] = $iarr['author']['id'] = $iarr['user']['id'];
    // $iarr['content'] = "." . $input;
    // $iarr['interaction'] = true;
    
    // // We get the output from your msgCreate function, which is assumed to be synchronous.
    // // If msgCreate is asynchronous, this part needs to be wrapped in a promise.
    // $output = msgCreate($iarr, $discord);
    
    // // Handle the output as a Promise, since `updateOriginalResponse` is asynchronous.
    // (new \React\Promise\Promise(function ($resolve, $reject) use ($output, $interaction) {
        // if (!is_array($output)) {
            // if (!$output) {
                // $output = "(No Output)";
            // }
            // $output = [$output];
        // }

        // // --- Check for 'No Output' condition ---
        // if (trim($output[0]) === '(No Output)') {
            // // Update the message with a green checkmark emoji
            // $interaction->updateOriginalResponse(MessageBuilder::new()->setContent('✅ Command executed successfully.'));
        // } else {
            // // Update the deferred reply with the first part of the response
            // $interaction->updateOriginalResponse(MessageBuilder::new()->setContent($output[0]))
            // ->then(function () use ($output, $interaction) {
                // // Send any remaining parts as follow-up messages
                // for ($i = 1; $i < count($output); $i++) {
                    // $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent($output[$i]));
                // }
            // });
        // }
        // $resolve();
    // }));
// });


// <?php
// use Discord\Builders\MessageBuilder;
// use Discord\Parts\Interactions\Interaction;
// use React\Promise\PromiseInterface;

// --- Handling application command interactions ---
		$discord->listenCommand('kodi', function (Interaction $interaction) use ($discord) {
    // Acknowledge the interaction immediately. This is the fix for older versions of DiscordPHP.
    // It prevents the interaction from timing out.
    $interaction->acknowledge()->then(function () use ($discord, $interaction) {

    // Get the 'content' option from the user's interaction.
    // The DiscordPHP Collection::get() method requires a default value to be passed.
    $contentOption = $interaction->data->options->get('content', null);
    $input = $contentOption ? $contentOption->value : "seek";

		$interaction->updateOriginalResponse(MessageBuilder::new()->setContent("$input ⏳"));

    // This section is now inside a promise to handle asynchronous operations.
    // We are passing the necessary variables to the .then() block.
    // The `msgCreate` function call is placed here to execute after the deferReply.
    $iarr = json_decode(json_encode($interaction), true);
    $did = $iarr['user_id'] = $iarr['author']['id'] = $iarr['user']['id'];
    $iarr['content'] = "." . $input;
    $iarr['interaction'] = true;
    
    // We get the output from your msgCreate function, which is assumed to be synchronous.
    // If msgCreate is asynchronous, this part needs to be wrapped in a promise.
    $output = msgCreate($iarr, $discord);
    
    // Handle the output as a Promise, since `updateOriginalResponse` is asynchronous.
    (new \React\Promise\Promise(function ($resolve, $reject) use ($output, $interaction,$did, $input) {
        if (!is_array($output)) {
            if (!$output) {
                $output = "(No Output)";
            }
            $output = [$output];
        }

        // --- Check for 'No Output' condition ---
        if (trim($output[0]) === '(No Output)') {
					$add = '';
					if ($input == 'next') {
						global $kodi;
						$add = "\n".$kodi['playfilename'];
					}
            // Update the message with a green checkmark emoji
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent("$input ✅".$add));
        } else {
            // Update the deferred reply with the first part of the response
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent("$input ✅\n".$output[0]))
            ->then(function () use ($output, $did, $interaction) {
                // Send any remaining parts as follow-up messages
                for ($i = 1; $i < count($output); $i++) {
                    if ($i > 2) {
											sendReply($did,$output[$i]);
										} else {
											$interaction->sendFollowUpMessage(MessageBuilder::new()->setContent($output[$i]));
										}
								}
            });
        }
        $resolve(true);
    }));
	});
});


	// $discord->listenCommand('kodi', function (Interaction $interaction) use ($discord) {
		// // Get the 'name' option if provided by the user
		// $iarr = json_decode(json_encode( $interaction), true);
		// $input = $iarr['data']['options']['content']['value'] ?? "seek";
		// $output = "``".$input."``";
		// $builder = MessageBuilder::new()->setContent($output);
		// // $interaction->respondWithMessage($builder, false)->then(;
		// $interaction->respondWithMessage($builder, false)->then(function () use ($iarr,$interaction,$discord,$input) {
			// //			$interaction->content = ".".$iarr['data']['options']['
			// // content']['value'];
			// //		$iarr = json_decode(json_encode( $interaction), true);
			// $iarr['user_id'] = $iarr['author']['id'] = $iarr['user']['id']; //['content']['value'];
			// //$iarr['user_id'] = $iarr['user']['id']; //['content']['value'];
			// $iarr['content'] = ".".$input;
			// $iarr['interaction'] = true;
			// file_put_contents('KKinteraction.json',json_encode($iarr, JSON_PRETTY_PRINT));	
			// $output = msgCreate($iarr,$discord);
			// if (!is_array($output)) {
				// if (!$output) {
					// $output = "(No Output)";
				// }
				// $output = [$output];
			// }
			// var_dump('444444444444444',$output,$iarr['data']['options']['content']['name'],$iarr['data']['options']['content']['value'],'55555555555555555');
			// foreach ($output AS $msg) {
				// if (!$msg) { $msg = "(No Output)"; }
				// $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent($msg));
			// }

		// });
	// });
			// $nameOption = $interaction->data->options->get('name','name'); // Accessing option by name
			// var_+dump($nameOption,'4444444444444444444');
			// $userName = $nameOption ? $nameOption->value : $interaction->user->global_name;
			// if (is_numeric($userName)) {
				// $userName = '<@'.$userName.'>';
			// }
			// Create a MessageBuilder for the response

			// Respond to the interaction with the message
			// The second argument 'true' makes the message ephemeral (only visible to the user who used the command)



	// 3. **Example Usage**:
   // If you simply want to avoid hitting the rate limit and are using the DiscordPHP library in a typical way, you might do something like this:

   // ```php
   // use Discord\Discord;

   // $discord = new Discord([
       // 'token' => 'YOUR_BOT_TOKEN',
   // ]);

   // $discord->on('ready', function ($discord) {
       // // Your bot is ready here

			global $rateLimit;
			var_dump($rateLimit);


			// echo "HTTP\n";
      // $http = $discord->http; // Access the HTTP client under Discord Client
			// $httpa = (array) $http;
			// if (preg_match('/"\\\u0000\*\\\u0000waiting": ([0-9]*),/',jenc($httpa),$ma)) {
				// var_dump('MA',$ma[1]);
				// file_put_contents($GLOBALS['filePrefix'].'http-q.json',jenc([ $httpa,$ma ]));
				
				
			// }
			
			// unset($http);			
			// echo "HTTP END\n";


       // Example to get the rate limits for an API call
       // $http = $discord->http; // Access the HTTP client under Discord Client
			// file_put_contents($GLOBALS['filePrefix'].'http.json',json_encode((array) $http, JSON_PRETTY_PRINT));
			// var_dump($http->getRateLimit());
       // You can monitor rate limits like so
       // $http->on('rateLimit', function ($request, $limit) {
					// file_put_contents($GLOBALS['filePrefix'].'ratelimits.json',json_encode([$request,$limit], JSON_PRETTY_PRINT),FILE_APPEND);
					// echo "Rate-limited! Please wait for " . $limit['reset'] . " seconds\n";
			 // });

		$channelid = file_get_contents($GLOBALS['filePrefix'].'lastchan');
		file_put_contents($GLOBALS['filePrefix'].'lastchan','none');
		if ($channelid != 'none' && $channelid != '') {
		$channelid = explode(':',$channelid);
		var_dump($channelid);
		$guildid = $channelid[0];
		$channelid = $channelid[1];
		if ($guildid == 'DM') {
			sendReply($channelid, $botready);
		} else {
			sendReply($discord->guilds->get('id', $guildid)
				->channels->get('id', $channelid),$botready.tacoGen());
				// ->sendMessage($botready.tacoGen());
		}
	}			

	$guild = $discord->guilds->get('id', 788607168228229160);

	if ($guild) {
		$channel = $guild->channels->get('id', 844468574462148658);
		$channel->sendMessage($botready);
	}
	sendMsg("380675774794956800", $botready);

	$discord->on(Event::GUILD_MEMBER_ADD, function (Member $member, Discord $discord) {
		populateNicksIds();
	});
	$discord->on(Event::GUILD_MEMBER_UPDATE, function (Member $new, Discord $discord, $old) {
		populateNicksIds();
	});
	$discord->on(Event::GUILD_MEMBER_REMOVE, function (Member $member, Discord $discord) {
		var_dump(json_encode($member));
		populateNicksIds();
	});

	$discord->on(Event::MESSAGE_REACTION_ADD, function ($reaction, Discord $discord) {
		var_dump('$reaction');
		$dmMode = false;
		$amDev = $GLOBALS['amDev'];
		if(!isset($reaction['guild_id']) || $reaction['guild_id'] === NULL) { 
			$author = $reaction['user_id'];
			error_log("RA-DM mode");
			$dmMode = true;
		} else {
			error_log("RA-Channel mode");
			if ($reaction['author'] !== NULL) {
				$author = $reaction['author']['id'];
			} else if ($reaction['user_id'] !== NULL) {
				$author = $reaction['user_id'];

			} else {
				error_log("reaction author is NULL");
			}
		}

		file_put_contents('reactiondata.json',json_encode($reaction,JSON_PRETTY_PRINT));
		if ($amDev) { var_dump('$author,$GLOBALS["myID"],$GLOBALS["otherID"]',$author,$GLOBALS['myID'],$GLOBALS['otherID']); }
		if ($author == $GLOBALS['myID'] || $author == $GLOBALS['otherID']) {
			if ($amDev) { var_dump('COOOKIESSSSS 899999999999999999'); }
			return;
		}

		$isws = checkWorkspace($reaction);
		if ($amDev) { var_dump('$isws',$isws,$reaction['emoji']->name); }
		var_dump('$reaction');
		global $emoteArray;
		$eaNames = array_flip($emoteArray);
		$emojiname = $reaction['emoji']->name;
		$emojianame = null;
		if (isset($eaNames[$emojiname])) {
			$emojianame = $eaNames[$emojiname];
			if ($emojianame == "taco") {
				sendReply($reaction, "https://www.crystalshouts.com/graha.gif \nOm nom nom!");
				return;
			}
		}					

		if ($emojiname == 'youtube') {
			$emojianame = 'yt';
		}

		if ($isws) {
			if ($isws === true) {
				$isws = "";
			}
			$name = $isws;
			$channel = getChannel($reaction);
			$channel->broadcastTyping();

			$emojiid = $reaction['emoji']->id;
			if ($amDev) { var_dump('000000000001111',$emojiid,$emojianame,$emojiname); }
			if (!$dmMode) {
				$channel = getChannel($reaction);
				$channel->messages->fetch($reaction['message_id'])->then(function (Message $message) use ($reaction,$author,$amDev) {
					// $emojiname = $reaction['emoji']->name;
					$emoji = $reaction['emoji'];
					$message->deleteReaction(Message::REACT_DELETE_ID, $emoji, $author)->then(function ($x) use ($reaction,$amDev){
						if ($amDev) { var_dump('$x',$x); }
					});
				});
			}
			reactionAction($emojianame,$reaction,$name);
			//var_dump($emojiname,$emojianame);
			return;
		}
	});

	$discord->on(Event::MESSAGE_REACTION_REMOVE, function ($reaction, Discord $discord) {
		var_dump('$reaction-REMOVE');
		$dmMode = false;
		if(!isset($reaction['guild_id']) || $reaction['guild_id'] === NULL) { 
			$author = $reaction['user_id'];
			error_log("RA-DM mode");
			$dmMode = true;
		} else {
			error_log("RA-Channel mode");
			if ($reaction['author'] !== NULL) {
				$author = $reaction['author']['id'];
			} else if ($reaction['user_id'] !== NULL) {
				$author = $reaction['user_id'];
			} else {
				error_log("reaction author is NULL");
			}
		}

		var_dump('$author',$author,$GLOBALS['myID'],$GLOBALS['otherID']);
		if ($author == $GLOBALS['myID'] || $author == $GLOBALS['otherID']) {
			var_dump('COOOKIESSSSS 55555555555555555555777777777777777');
			return;
		}

		$isws = checkWorkspace($reaction);
		if ($isws && !$dmMode) {

			file_put_contents('discordvar.json',json_encode($discord, JSON_PRETTY_PRINT));
			file_put_contents('discordvar.txt',print_r($discord, true));


			var_dump('YEEEEEEEEEEEEET COOOKIESSSSS 55555555555555555555777777777777777');
			return;
		}

		var_dump('$isws',$isws);
		var_dump('$reaction-REMOVE-ISWS');
		//var_dump($reaction);
 
		if ($isws && $dmMode) {

			if ($isws === true) {
				$isws = "";
			}
			$name = $isws;

			$channel = getChannel($reaction);
			if ($channel) { $channel->broadcastTyping(); }

			global $emoteArray;
			$eaNames = array_flip($emoteArray);
			$emojiname = $reaction['emoji']->name;
			$emojianame = null;
			if (isset($eaNames[$emojiname])) {
				$emojianame = $eaNames[$emojiname];
			}					
			if ($emojiname == 'youtube') {
				$emojianame = 'yt';
			}

			reactionAction($emojianame,$reaction,$name);
			//var_dump($emojiname,$emojianame);

		}
	});

	// Listen for messages.
	$discord->on(Event::MESSAGE_UPDATE, function (Message $data, Discord $discord, $oldData) {
		if ($data['channel_id'] == '791272018279923755' && isset($data['content'])) {
			print_r('BOT AUDIT: '.$data['content']);
			if (isset($data['embeds']) ) { 
				var_dump('BOT AUDIT - EMBED: ',$data['embeds']);
			}
		}

		if ($data->channel->guild_id != NULL && ( isset($data['author']) && $data['author']['id'] == $GLOBALS['myID']) ) { return; }
		
		if ($data['channel_id'] == '839614327002628107' && $data['author']['id'] == '475744554910351370') {
			// var_dump($data);
			if (!isset($data['embeds']) ) { return;	}
			eventMgr($oldData,$data);
		}
	});

	$discord->on(Event::MESSAGE_CREATE, function($message,$discord) { msgCreate($message,$discord); });
	
	});

	function msgCreate($data, Discord $discord) {
		$tvChannel = ($data['channel_id'] === '1370142425292738673' );
		
		if (!isset($data['content'])) {
			var_dump($data);
			error_log("DATA CONTENT NOT SET");
			return;
		}
			
		if ($tvChannel) { $data['content'] = '.'.$data['content']; }
	
		if ($data['channel_id'] == '791272018279923755' && isset($data['content'])) {
			print_r('BOT AUDIT: '.$data['content']);
			if (isset($data['embeds']) ) { 
				var_dump('BOT AUDIT - EMBED: ',$data['embeds']);
			}
		}

		global $nicks;
		global $tidy;
		global $memberids;

		$dmMode = 0;
		if(!isset($data['guild_id']) || $data['guild_id'] === NULL) { 
			if(isset($data['user_id'])) {
				$author = $data['user_id'];
			} else {
				$author = $data['author']['id'];
			}
			error_log("DM mode $author");
			$dmMode = 1;
		} else {
			$author = $data['author']['id'];
			error_log("Channel mode $author");
		}
		$isMe = ($author === $GLOBALS['myID']);
		
		if ($tidy && $tvChannel && !$isMe) {
			var_dump($data);
			$channel = getChannel($data);
			$channel->messages->fetch($data['id'])->then(function (Message $Msg) {
				//var_dump("delete $reset =================================");
				var_dump("Msg",$Msg);
				$Msg->delete()->then(function () {
					//var_dump("delete 0000000000000000=================================");
				});
			});


		}

		if ($author == $GLOBALS['otherID'] && $data['content'] != '.reset' && !startsWith($data['content'],'.shout') ) { error_log("other bot is talking. ignoring."); return; }

		$isLink = ($author === '380675774794956800');
		$toMe = (strpos($data['content'],"<@".$GLOBALS['myID']."> ") !== false && strpos($data['content'],"<@".$GLOBALS['myID']."> ") >= 0 &&  strpos($data['content'],"<@".$GLOBALS['myID']."> ") < 3);

		// if (!$toMe && (!$dmMode || !$isLink) && $data['channel_id'] != '1370142425292738673' ) {
		if (!$toMe && !$tvChannel) {
			if (($author == '362816681837592586' || $isLink || $data['interaction']) && $dmMode) {
				echo "sliding past the lockout for $author";
			} else {
				var_dump('NOT TV REMOTE CHANNEL');
				echo "\n".'$toMe,$dmMode,$isLink,$tvChannel'."\n";
				echo intval($toMe).'|'.intval($dmMode).'|'.intval($isLink).'|'.intval($tvChannel);
				return;	
			}
		}

		if ( $GLOBALS['commandPrefix'] != '.' ) { 
			$data['content'] = preg_replace('/^\./', 'CMD_PREFIX_IGNORE_THIS', $data['content']);
			$data['content'] = preg_replace('/^'.$GLOBALS['commandPrefix'].'/', '.', $data['content']);
		}

		$commpref = false;

		if (startsWith(strtolower($data['content']),$GLOBALS['commandPrefix']) && !startsWith(strtolower($data['content']),$GLOBALS['commandPrefix'].$GLOBALS['commandPrefix'])) {
			error_log("Command prefix detected. Command issued is: ".$data['content']."\n");
			if (file_exists("/tmp/maintenance.lock")) {
				sendReply($data, "Crystal Systems is in maintenance mode. Please try again in a few minutes.");
				return;
			}
			$commpref = true;
		} else if (	$dmMode == 1 && $data['content'] != 'hello computer' ) { 
			$data['content'] = '.'.$data['content']; 
		}

		if ($dmMode == 1 && $author == $GLOBALS['myID'] ) { error_log("fiiiiiiii"); return; }

		if ($author !== $GLOBALS['otherID'] && $author !== $GLOBALS['myID'] && ($commpref || $dmMode) && isset($data['channel_id']) && !empty($data['channel_id'])) {
			$channel = getChannel($data);
			var_dump($data['channel_id'],$channel);
			if ($channel) { $channel->broadcastTyping(); }
			
		}

		if (($data['channel_id'] == '839614327002628107' && $data['author']['id'] == '475744554910351370' ) || strtolower($data['content']) === '.apollotest') {
			if (!isset($data['embeds']) ) { return;	}
			eventMgr('add',$data);
		}
		
// workspace dev and testing stubs
		if (strtolower($data['content']) === '.testws' || startsWith(strtolower($data['content']),'.workspace') || startsWith(strtolower($data['content']),'.wsout ') || startsWith(strtolower($data['content']),'.wfortune')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				$info = explode(' ',trim($data['content']));
				$wid = null;
				$new = false;
				$output = null;
				$name = '';
				
				if (isset($info[0]) && trim($info[0]) == '.wfortune') {
					$fortune = new Fortune();
					@$msg = $fortune->QuoteFromDir("fortune_data/");
					$content = str_replace(array("<br />", "<br/>", "<br>"), "\n", $msg);
					$remove = array("\r", "<p>", "</p>", "<h1>", "</h1>");
					$msg = str_replace($remove, ' ', $content);
					outputWorkspace($data,$msg);
					return;
				
				}
				if (isset($info[0]) && trim($info[0]) == '.wsout') {
					$cmd = array_shift($info);
					$name = array_shift($info);
					$output = implode(' ',$info);
					var_dump("DATA",$output,$name);
					outputWorkspace($data,$output,$name);
				} else {
					if (isset($info[1]) && $info[1] == 'reset') {
						$wid = 'reset';
					}
					if (isset($info[1]) && $info[1] !== 'reset') {
						$name = $info[1];
						$new = true;
						$output = "TEEEEST8347489";
					}
				// initWorkspace($data,$wid = null, $new = false, $output = null,$name = '') {
					initWorkspace($data,$wid,$new,$output,$name);
				}
				// sendReply($data,print_r($data,true));
				return;
			}
		}

// word spelling checking and correction
		if (( startsWith(strtolower($data['content']),'.spell') || startsWith(strtolower($data['content']),'.spellcheck'))) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				$message = explode(' ',strtolower($data['content']));
				$command = $message[0];
				if (!isset($message[1])) {
					$output = "Command usage: ``.$command <word>``";
					sendReply($data, $output);
					return $output;
				}
				array_shift($message);
				$query = implode(' ',$message);
				$result = spellCheck($query,true);
				// var_dump("spellcheck",$result);
				sendReply($data, $result);
				return $result;
			}
		}

// word defining and auto-correct		
		if (( startsWith(strtolower($data['content']),'.search '))) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				$args = explode(' ',trim(strtolower($data['content'])));
				// var_dump($args,'df8df8d8f78sdf',strpos($data['content'],"<@".$GLOBALS['myID']."> "));
				$cmd = ltrim(array_shift($args),'.');
				$cat = 'tv'; 
				global $kodi;
				if (!isset($kodi['npaths'])) {
					srcPaths();
				}
				
				$cats = array_keys($kodi['npaths']);
				if ( startsWith($args[0],'@')) { 
					$cat = ltrim(array_shift($args),'@');
					if (!in_array($cat,$cats)) {
					//} else {
						$result = "Invalid category: $cat. Try ".niceList($cats,'','or');
						sendReply($data, $result);
						return $result;
					}
				}
				$arg  = implode(' ',$args);
				$result = kodi('search',[$cat,$arg],$data);
				sendReply($data, $result);
				return $result;
			}
		}
		
		if (( startsWith(strtolower($data['content']),'.define'))) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				$message = explode(' ',strtolower($data['content']));
				$command = $message[0];
				if (!isset($message[1])) {
					$output = "Command usage: ``.define <word>``";
					sendReply($data, $output);
					return $output;
				}
				array_shift($message);
				$query = implode(' ',$message);
				$res = json_decode(curl("https://api.dictionaryapi.dev/api/v2/entries/en/$query")['content'],true)[0];
				if ($res == NULL) {
					if (!$res = spellCheck($query,'array')) {
						sendReply($data, "Could not find **$query**!");
						return "Could not find **$query**!";
					}
					$res = json_decode(curl("https://api.dictionaryapi.dev/api/v2/entries/en/$res")['content'],true)[0];
					if ($res == NULL) {
						sendReply($data, "Could not find **$query**!");
						return "Could not find **$query**!";
					}
				}
				$word = $res['word'];
				$phonetic = $res['phonetic'];
				if ($phonetic) $phonetic = " **($phonetic)**";
				$definitions = niceList(numberfy_array(array_column($res['meanings'][0]['definitions'],'definition')),'','or').".";
				$result = "**".ucfirst($word)."**$phonetic, a word here which means $definitions";
				sendReply($data, $result);
				return $result;
			}
		}
		
// limit kodi function access 
		$kodichans = ['1370142425292738673'];

// emergency stop for player status refresher
		if ($author == '380675774794956800' && $data['content'] == '.stoploop' ) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				global $kodi;
				global $stopLoop;
				$stopLoop = !$stopLoop;
				$kodi['loopstat'] = ($stopLoop)?'disabled':'stopped';
				if (!$stopLoop) { 			statusLooper(); }
				sendReply($data, "Stoploop: ".intval($stopLoop)." ".$kodi['loopstat']);
				return intval($stopLoop);
			}
		}

		if (strtolower(trim($data['content'])) == '.autonext' ) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				global $kodi;
				$kodi['autoplay'] = $kodi['menu']['autoplay'] = !$kodi['autoplay'];
				if (is_array($kodi['autoplay'])) {
					$ret = "\n".jenc($kodi['autoplay']);
				} else {
					$ret = "AutoNext ".(($kodi['autoplay'])?" Enabled": " Disabled");
					// $kodi['menu']['autoplay'] = $kodi['autoplay'];
				}
				file_put_contents('menu.json',json_encode($kodi['menu'], JSON_PRETTY_PRINT));	
				var_dump("autonext ret",$ret);
				playerMsg($ret);
				// sendReply($data, $ret);
				return $ret;
			}
		}

		if (strtolower(trim($data['content'])) == '.shuffle' ) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				global $kodi;
				// $ret = $kodi['shuffle']
				$shuf = kodiShuffle('active','toggle');
				$ret = "Shuffle".((!$shuf)?" Enabled": " Disabled");
				var_dump("shuffle ret",$shuf, $ret);
				sendReply($data, $ret);
				return $ret;
			}
		}

		if ($data['content'] == '.autoq' ) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				global $kodi;
				$kodi['autoq'] = $kodi['menu']['autoq'] = !$$kodi['autoq'];
				sendReply($data, intval($kodi['autoq']));
				return intval($kodi['autoq']);
			}
		}

		if ($author == '380675774794956800' && $data['content'] == '.varkodi' ) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				global $kodi;
				file_put_contents('kodi.json',json_encode($kodi, JSON_PRETTY_PRINT));
				sendReply($data, print_r(count($kodi),true));
				return print_r(count($kodi),true);
			}
		}
		

		if ((in_array($data['channel_id'],$kodichans) || $dmMode ) && isset($data['channel_id']) && !empty($data['channel_id'])	&& 
			(!empty($data['content']) || (isset($data->message_reference->type) && $data->message_reference->type == 1))) {
		
			if (isset($data->message_reference->type) && $data->message_reference->type == 1) {
				$cid = $data->message_reference->channel_id;
				$gid = (isset($data->message_reference->guild_id))?$data->message_reference->guild_id:false;
				$mid = $data->message_reference->message_id;

				$channel = ($gid)?$discord->guilds->get('id', $gid)->channels->get('id', $cid): $discord->getChannel($cid);

					var_dump($channel,$gid);
					var_dump(gettype($channel));
					file_put_contents('dmdata.json',json_encode([$cid,$mid,$channel], JSON_PRETTY_PRINT));
				
				
				if (!$channel || gettype($channel) == 'boolean') {
					sendReply($data, "Unable to read forwarded Channel data.");
					return "Unable to read forwarded Channel data.";
				} 
				// $message = $channel->messages->get('id', $mid);
				$channel->messages->fetch($mid)->then(function (Message $Msg) use ($data) {
					$content = $Msg['content'];	
					if (!is_string($content) || !$content) { var_dump("--no content!--"); return; }
					$content = trim($content);
					if (filter_var($content, FILTER_VALIDATE_URL)) {
						preg_match(
						"/(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:\S*&)?vi?=|(?:embed|v|vi|user|shorts)\/))([^?&\"'>\s]+)/",
						$content,$matches);
						if (isset($matches[1]) && validVideoId($matches[1])) {
							$vid = $matches[1];
							
							$playlist = false;
							parse_str(pathinfo($matches[0])['basename'],$qarr);
							if (isset($arr['list'])) { $playlist = $arr['list']; }
							if ($playlist) { 
								$kodi['plmode'] = 'yt';
								kodi('showdir',"plugin://plugin.video.youtube/playlist/$playlist",$data); 
							}
							
							$output = kodi('ytplay','https://www.youtube.com/watch?v='.$vid,$data);
						} else {
							$output = 'MR Video ID error';
							// sendReply($data, $output);
							// return;
						}
						// kodi('ytplay',$vid,$data);
						sendReply($data, $output);
						return $output;
					}
				});
				return;
			} else {
				$content = $data['content'];
			}
			if (!is_string($content) || !$content) { var_dump("--no content!--"); return; }
			$content = trim($content);
			var_dump(filter_var($content, FILTER_VALIDATE_URL));
			if (filter_var($content, FILTER_VALIDATE_URL)) {
				preg_match(
				"/(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:\S*&)?vi?=|(?:embed|v|vi|user|shorts)\/))([^?&\"'>\s]+)/",
				$content,$matches);
				if (isset($matches[1]) && validVideoId($matches[1])) {
					$vid = $matches[1];
					$output = kodi('ytplay','https://www.youtube.com/watch?v='.$vid,$data);
				} else {
					preg_match(
					"~(?:https?:\/\/)?(?:www\.)?dai\.?ly(?:motion)?(?:\.com)?\/?.*(?:video|embed)?(?:.*v=|v\/|\/)([a-z0-9]+)~",
					$content,$matches);
					if (isset($matches[1]) ) {
						$vid = $matches[1];
						$output = kodi('dmplay',$content,$data);
					} else {
						$output = 'Video ID error';
						// sendReply($data, $output);
						// return;
					}
				}
				// kodi('ytplay',$vid,$data);
				sendReply($data, $output);
				return $output;
			}
//				$channel->messages->fetch($mid)->then(function (Message $Msg) use ($data) {
					// var_dump($channel,$message);
					// var_dump(gettype($channel),gettype($message));
					// file_put_contents('dmdata.json',json_encode([$cid,$mid,$data,$message], JSON_PRETTY_PRINT));
					//$Msg->edit(MessageBuilder::new()->setContent($output)); //->then(function (Message $message) {
//					sendReply($data, print_r($stopLoop,true));
//				});
				
			// global $stopLoop;
				// $stopLoop = !$stopLoop;
				// sendReply($data, print_r($stopLoop,true));
			//}
		}
		
// stub for testing purposes
		if ($author == '380675774794956800' && (startsWith(strtolower($data['content']), '.testing ') || $data['content'] == '.testing')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {

				sendReply($data, jenc([$discord->getAPILimit()]));
				return;
					$args = explode(' ',trim($data['content']));
					$cmd = ltrim(array_shift($args),'.');
					$path  = implode(' ',$args);

				// $json = '{"jsonrpc":"2.0","method":"Files.GetDirectory","id":"1743603938944","params":{"directory":"'.$path.'","media":"video","properties":["title","file","artist","duration","comment","description","runtime","playcount","mimetype","thumbnail","dateadded"]}}';
				// $json = '{"jsonrpc":"2.0","method":"Player.GetItem","params":[1,["title","thumbnail","mediapath","file":"'.$path.'","artist","genre","year","rating","album","track","duration","playcount","dateadded","episode","artistid","albumid","tvshowid","fanart"]],"id":10}';

				global $_Kodi;
				// $msg = json_encode($_Kodi->sendJson($json), JSON_PRETTY_PRINT);
				$json = '{"jsonrpc":"2.0","method":"Player.GetActivePlayers","id":95646}';
				$ret = $_Kodi->sendJson($json);

				sendReply($data, jenc([$ret,activePlayer()]));

				// https://www.youtube.com/watch?v=fbyIYXEu-nQ&list=PL-D2eb2vBV7LzsXkzeinc7v1eZ-22AaCs&index=2&pp=iAQBsAQB

				// $limit = false;
				// $msg = readLines('kodikitty.log',30);
				// $matches = preg_grep('/expecting rate limit|hit rate-limit: RATELIMIT Non-global, retry after/',$msg);
				// if ($matches) { 
					// $match = array_pop($matches);
					// if (preg_match('/(?:timer interval\s|retry after\s)([0-9.]+\s(?:s|ms))/',$match,$limit)) {
						// // $msg = json_encode($matches, JSON_PRETTY_PRINT); }
						// //$msg = json_encode([$limit], JSON_PRETTY_PRINT); }
						// $msg = $limit[1];
						// if (endsWith($msg, 'ms')) { $msg = intval($msg)/1000; } else { $msg = intval($msg); }
					// }
				// } else if (!$matches) { $msg = json_encode($msg, JSON_PRETTY_PRINT); }
			// // $msg .= $rl = "\n RL:".$http->rateLimit."\n";
			// // var_dump($rl);
			
			
			
			// //$http->rateLimit, JSON_PRETTY_PRINT));
			
			// // $msg = $http->rateLimit;
			// var_dump($msg);

				//GUI.ShowNotification
				// $json = '{"jsonrpc":"2.0","method":"GUI.ShowNotification","params":["test","test123","",2500],"id":8}';
				// global $_Kodi;
				// $out = json_encode($_Kodi->sendJson($json));
				// sendReply($data, print_r($out,true));
				return;
			}
		}				

// Chatbot initiatior
		if ($author !== $GLOBALS['otherID'] && $author !== $GLOBALS['myID'] && $toMe) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				
				//return;
				//var_dump($args,'df8df8d8f78sdf',strpos($data['content'],"<@".$GLOBALS['myID']."> "));

				$args = explode(' ',trim($data['content']));
				$cmd = ltrim(array_shift($args),'.');
				$arg  = implode(' ',$args);

				if (str_contains($arg,' song') || str_contains($arg,'songs ') || str_contains($arg,'song ')) {
					$msg = jsonWrap($arg,$author,$data);
					if (is_array($msg)) {
						$msg = json_encode($msg, JSON_PRETTY_PRINT);
					}
					var_dump($msg);
				} else {
					$msg = chatBot($arg,$author);
				}
				
				// if (is_array($msg)) {
					
					// foreach ($msg AS $k => $ms) {
						// sendReply($data, $ms);
					// }
					
					
				// } else {
					sendReply($data, $msg);
				// }
				return;
			}
		}

		// kodi controls		
		if (startsWith(strtolower($data['content']), '.dj ')) {
			if (isset($data['channel_id']) && !empty($data['channel_id']) && (in_array($data['channel_id'],$kodichans) || $author == '380675774794956800' )) {
				$args = explode(' ',$data['content']);
				$cmd = ltrim(array_shift($args),'.');
				$arg = trim(implode(' ',$args));

				global $kodi;
				$dj = $kodi['dj'];
				$kodi['dj'] = ($arg == 'start');
				
				if ( !$dj && $kodi['dj'] && $arg !== 'stop') {
					$kodi['djdata'] = $data;
					$msg = jsonWrap("[radio dj for WSTP radio!] pick the next song",$author,$data);
					if (is_array($msg)) {
						$msg = json_encode($msg, JSON_PRETTY_PRINT);
					}
				} else if ($arg == 'stop') {
					kodi('stop');
					$msg = "DJ mode stopped";
				} else if ($arg) {
					$msg = jsonWrap("[radio_dj] pick the next song. use the following prompt to do so: $arg",$author,$data,true);
				}
				//$msg = kodi('showlist',null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (startsWith(strtolower($data['content']),'.page ')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])  && ($author == '380675774794956800' || in_array($data['channel_id'],$kodichans))) {
				$args = explode(' ',$data['content']);
				$pmodes = ['s','e','b','n'];
				$cmd = ltrim(array_shift($args),'.');
				$arg  = implode(' ',$args);
				if (is_numeric($arg) || in_array($arg,$pmodes)) {
					wsPages($data,NULL,$arg);
								global $rateLimit;
			// var_dump('$rateLimit',$rateLimit);

					// sendReply("page $arg", $msg);
				}
				var_dump($args,$arg,$cmd);
				

			}
		}

		if (strtolower(trim($data['content'])) == '.backbutton' ) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
			// if (isset($data['channel_id']) && !empty($data['channel_id']) && (isset($data['interaction']) || in_array($data['channel_id'],$kodichans))) {
				$msg = kodi('btn','back',$data);
				if (is_array($msg)) { $msg = jenc($msg); }
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (strtolower(trim($data['content'])) == '.pause' || strtolower(trim($data['content'])) == '.play' || strtolower($data['content']) === '.playpause') {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
			// if (isset($data['channel_id']) && !empty($data['channel_id']) && (isset($data['interaction']) || in_array($data['channel_id'],$kodichans))) {
				$msg = kodi('playPause');
				sendReply($data, $msg);
				return $msg;
			}
		}

		// if (startsWith(strtolower($data['content']), '.ytplaylist ')) {
			// if (isset($data['channel_id']) && !empty($data['channel_id']) && ($author == '380675774794956800' || in_array($data['channel_id'],$kodichans))) {
				// // $msg = kodi();
				// // sendReply($data, $msg);

				// $args = explode(' ',$data['content']);
				
				// //var_dump($args);
				// $cmd = trim(ltrim(array_shift($args),'.'));
				// $arg  = implode(' ',$args);
				
				// $playlist = false;
				// parse_str(pathinfo($arg)['basename'],$qarr);
				// if (isset($arr['list'])) { $playlist = $arr['list']; }
				// if ($playlist) { kodi('showdir',"plugin://plugin.video.youtube/playlist/$playlist",$data); } else {
					// sendReply($data, "Playlist load issue!");
				// }
				
				// return;
			// }
		// }

		if (strtolower($data['content']) === '.playall') {
			if (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans)) {
				$msg = kodi('playall');
				sendReply($data, $msg);
				return;
			}
		}

		if (strtolower($data['content']) === '.previous') {
			// if (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans)) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi('previous',null,$data);
				sendReply($data, $msg);
			}
		}

		if (strtolower($data['content']) === '.tidy') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				global $tidy;
				$msg = $tidy = !$tidy;
				$msg = ($msg)?"enabled":"disabled";
				sendReply($data, "Tidy $msg");
				return $msg;
			}
		}

		if (strtolower($data['content']) === '.fixaudio' || strtolower($data['content']) === '.kodiaudio') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi('audiostream',null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if ((strtolower($data['content']) === '.bookmarks' || startsWith(strtolower($data['content']),'.bookmark') || startsWith(strtolower($data['content']),'.unbookmark ') || startsWith(strtolower($data['content']),'.resume '))) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$cmds = ['bookmark','unbookmark','bookmarks','resume'];
				$cmdargs = ['unbookmark','resume'];
				$args = explode(' ',$data['content']);
				
				var_dump($args);
				$cmd = trim(ltrim(array_shift($args),'.'));
				$arg  = implode(' ',$args);
				var_dump($cmd);
				if (!in_array($cmd,$cmds)) {
					return;
				}
				if (($cmd == 'bookmark' && !empty($arg) && !is_numeric($arg)) || (in_array($cmd,$cmdargs) && (empty($arg) || !is_numeric($arg)))) {
					sendReply($data, "tp436: invalid selection");
					return;
				}
				
				$arg = numToKey($arg,$data);
				
				$channel = $discord->getChannel('1274001261976354886');
				$members = $channel->members;
				// var_dump($members);
				$members = json_decode(json_encode($members),true);
				var_dump($members);
				$members = array_keys($members);
				var_dump($members);
				// $invc = in_array($author,$members);
				$invc = ($author == '380675774794956800' || in_array($author,$members));
				$args = [$arg,$author,$invc];
				
				$msg = kodi($cmd,$args,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if ((strtolower($data['content']) === '.favs' || startsWith(strtolower($data['content']),'.fav ') || $data['content'] == '.fav' || startsWith(strtolower($data['content']),'.unfav ') || startsWith(strtolower($data['content']),'.selfav '))) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$cmds = ['fav','unfav','favs','selfav'];
				$cmdargs = ['unfav','selfav'];
				$args = explode(' ',$data['content']);
				
				var_dump($args);
				$cmd = ltrim(array_shift($args),'.');
				$arg  = trim(implode(' ',$args));
				var_dump($cmd);
				if (!in_array($cmd,$cmds)) {
					return;
				}
				if (($cmd == 'fav' && !empty($arg) && !is_numeric($arg)) || (in_array($cmd,$cmdargs) && (empty($arg) || !is_numeric($arg)))) {
					sendReply($data, "tp436f: invalid selection: $arg");
					return;
				}
				
				$arg = numToKey($arg,$data);
				
				$channel = $discord->getChannel('1274001261976354886');
				$members = $channel->members;
				$members = json_decode(json_encode($members),true);
				var_dump($members);
				$members = array_keys($members);
				var_dump($members);
				$invc = ($author == '380675774794956800' || in_array($author,$members));
				$args = [$arg,$author,$invc];
				
				$msg = kodi($cmd,$args,$data);
				sendReply($data, $msg);
				return;
			}
		}

		if (strtolower($data['content']) === '.next') {
			if ($data['interaction'] || ($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi('next',null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (strtolower(trim($data['content'])) === '.kodi') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				global $lastStatusData;
				$lastStatusData = $data;
				initWorkspace($data,'reset',false,kodi('refresh',null,"returnarray"));
				initWorkspace($data,'reset',false,kodi('seek',null,$data),'player');
			}
		}

		if (strtolower($data['content']) === '.back') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("back",null,$data);
				sendReply($data, $msg);
			}
		}

		if (strtolower($data['content']) === '.stop') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("stop");
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (strtolower($data['content']) === '.pause') {
			if ((isset($data['interaction']) || $author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("pause",null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if ($author == '380675774794956800' && strtolower($data['content']) === '.showhist') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("showhist");
				sendReply($data, $msg);
			}
		}

		if ($author == '380675774794956800' && strtolower($data['content']) === '.showuhist') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("showuhist",$author,$data);
				sendReply($data, $msg);
			}
		}

		if (strtolower($data['content']) === '.movies') {
			if (isset($data['interaction']) || ($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("movies",null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (strtolower($data['content']) === '.shows') {
			if (isset($data['interaction']) || ($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("shows",null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (strtolower($data['content']) === '.sources') {
			if (isset($data['interaction']) || ($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("sources",null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (startsWith(strtolower($data['content']),'.playlist ') || $data['content'] == '.playlists') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$args = explode(' ',$data['content']);
				var_dump($args);
				$cmd = ltrim(array_shift($args),'.');
				$arg  = (count($args))?trim(implode(' ',$args)):false;
				
				$msg = kodi("getplaylist",[$author,$arg],$data);
				sendReply($data, $msg);
			}
		}

		if (startsWith(strtolower($data['content']),'.queuelist')) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("getqueuelist",null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (strtolower($data['content']) == '.queuerandom') {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$msg = kodi("queuerandom",null,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (startsWith(strtolower($data['content']),'.skipintro')) {
			if (isset($data['interaction']) || ($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$arg  = ['time',["+25 seconds"]];
				$msg = kodi("seek",$arg,$data);
				return $msg;
			}
		}

		if (strtolower($data['content']) == '.osd' ) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				// $args = explode(' ',$data['content']);
				// $arg = (isset($args[1]))?$args[1]:false;
			
				//$arg  = ['time',["+25 seconds"]];
				$msg = kodi("osd",null,$data);
				return $msg;

			}
		}

		if (startsWith(strtolower($data['content']),'.vol')) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$args = explode(' ',$data['content']);
				$arg = (isset($args[1]))?$args[1]:false;
			
				//$arg  = ['time',["+25 seconds"]];
				$msg = kodi("vol",$arg,$data);
				return $msg;

			}
		}
		
		if ($data['content'] == ".seek" || startsWith(strtolower($data['content']),'.seek ')) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) )) {
				$args = explode(' ',$data['content']);
				
				if (isset($args[1])) {
					array_shift($args);
					$args = implode(' ',$args);
					$regex = '/[+,-]?[0-9:]+\s[a-z,A-Z]+/';
					
					if (preg_match($regex,$args,$matches)) {
						$matches = array_map('trim',$matches);
						//array_walk($matches, function(&$value, $key) { $value = strtoTimeSecs($value); } );
							var_dump('$matches,$args,$regex',$matches,$args,$regex);
						// if (count($matches) == 1) {
							// //seekAndSetTimeout($matches[0]);
							// return;
						// }
						$arg  = ['time',$matches];
						var_dump("YES matches",$matches,$args);
					} else if ( preg_match('/[0-9]+%/',$args,$matches)) {
						$arg  = ['pcnt',intval($args)];
						var_dump('pcntmatches',$matches);
						if (count($matches) == 1) {
							// seekAndSetTimeout($matches[0]);
							$percent = floatval($matches[0]);
							global $_Kodi;
							//$mjson = '{"jsonrpc":"2.0","method":"Player.Seek","params":[0,{"percentage":'.$percent.'}],"id":8}';
							$json = '{"jsonrpc":"2.0","method":"Player.Seek","params":['.activePlayer().',{"percentage":'.$percent.'}],"id":8}';
							//if (!($dirs = json_encode($_Kodi->sendJson($json))) || isset($dirs['error'])) {
								$dirs = json_encode($_Kodi->sendJson($json));
							//}
							var_dump($json,$dirs);
							return;
						}

					} else {
						var_dump("matches",$matches);
						sendReply($data, "invalid entry");
						return;
					}
				} else {
					$arg = ['show',null];
				}
				var_dump($arg,$args);
				if ($author !== '380675774794956800' && !in_array($data['channel_id'],$kodichans) && $arg[0] !== 'show') {
					sendReply($data, "Only available in the kodi channel");
					return;
				}
				var_dump("seek arg",$arg);
				$msg = kodi("seek",$arg,$data);
				if ($msg) { sendReply($data, $msg); }
				return $msg;
			}
		}

		if ((startsWith(strtolower($data['content']),'.playfrom ') || startsWith(strtolower($data['content']),'.queuefrom') || startsWith(strtolower($data['content']),'.queue') || startsWith(strtolower($data['content']),'.unqueue '))) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$args = explode(' ',$data['content']);
				$arg = null;
				$qp = 'queue';
				$un = $from = '';
				if (isset($args[0]) && $args[0] == '.playfrom') {
					$qp = 'play';
					$from = 'from';
					global $kodi;

					$args[1] = numToKey(intval($args[1]),$data);
					
					if (!isset($args[1]) || !is_numeric($args[1])) {
						sendReply($data, '5756z: invalid selection: '.$args[1]);
						return 'I5756z: invalid selection: '.$args[1];
					}
					$arg  = intval($args[1]);
					kodi('select',$arg,null);
					usleep(900000);
					kodi('queuefrom',$arg+1,$data);
					return "OK";
					
				} else if (isset($args[0]) && $args[0] == '.unqueue') {
					if (!isset($args[1])) {
						sendReply($data, '756k: invalid selection');
						return 'I756k: invalid selection';
					}
					$un = 'un';
					if ($args[1] == 'all') {
						$arg = 'all';
					} else {						
						$arg  = intval($args[1]);
					}
				} else if (isset($args[0]) && $args[0] == '.queuefrom') {
					if (!isset($args[1])) {
						sendReply($data, '1948s: invalid selection');
						return 'I-1948s: invalid selection';
					}
					$from = 'from';
					$arg  = intval($args[1]);
				} else if (isset($args[1])) {
					if ($args[1] == 'all') {
						$arg = 'all';
					} else if ($args[1] == 'clear') {
						$arg = 'clear';
						if (isset($args[2])) {
							if (is_numeric($args[2])) {
								$arg = 'clear'.$args[2];
							} else if ($args[2] == 'music') {
								$arg = 'clear0';
							} else if ($args[2] == 'video') {
								$arg = 'clear1';
							}
						}
					} else {
						$arg  = intval($args[1]);
					}
				}
				$arg = numToKey($arg,$data);
				$msg = kodi($un.$qp.$from,$arg,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if ((startsWith(strtolower($data['content']),'.select') || startsWith(strtolower($data['content']),'.play ') || startsWith(strtolower($data['content']),'.continue'))) {
			if (isset($data['interaction']) || ($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$cmds = ['play','continue','select'];
				
				$args = explode(' ',$data['content']);
				var_dump($args);
				$cmd = ltrim(array_shift($args),'.');
				$arg  = trim(implode(' ',$args));
				if (!$arg && $arg !== "0") {
					sendReply($data, "8968e: Invalid selection `$arg`");
					return "I8968e: Invalid selection `$arg`";
				}
				
				var_dump($cmd);
				if (!in_array($cmd,$cmds)) {
					return "Syntax error";
				}
				if ($arg == 'random') {
					$arg = 'random';
				} else {
					$arg  = intval($arg);
					$arg = numToKey($arg,$data);
				}
				
				$msg = kodi($cmd,$arg,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (startsWith(strtolower($data['content']),'.yt')) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$ytcmds = ['ytplay','ytsearch','ytplaylist','ytpl','ytp','yts'];
				$args = explode(' ',$data['content']);
				
				var_dump($args);
				$ytcmd = ltrim(array_shift($args),'.');
				var_dump($ytcmd);
				if (!in_array($ytcmd,$ytcmds)) {
					return;
				}
				$arg  = implode(' ',$args);

				if ($ytcmd == 'ytpl' || $ytcmd == 'ytplaylist') {
					$playlist = false;
					parse_str(explode('?',pathinfo($arg)['basename'])[1],$qarr);
					if (isset($qarr['list'])) { $playlist = $qarr['list']; }
					if ($playlist) { $msg = kodi('showdir',"plugin://plugin.video.youtube/playlist/$playlist",$data); } else {
						$msg = "Playlist load issue!";
						sendReply($data, "Playlist load issue!");
					}
					return $msg;
				}
				
				$msg = kodi($ytcmd,$arg,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (startsWith(strtolower($data['content']),'.dmp ')) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$ytcmds = ['dmp'];
				$args = explode(' ',$data['content']);
				
				var_dump($args);
				$ytcmd = ltrim(array_shift($args),'.');
				var_dump($ytcmd);
				if (!in_array($ytcmd,$ytcmds)) {
					return;
				}
				$arg  = implode(' ',$args);

				// if ($ytcmd == 'ytpl' || $ytcmd == 'ytplaylist') {
					// $playlist = false;
					// parse_str(explode('?',pathinfo($arg)['basename'])[1],$qarr);
					// if (isset($qarr['list'])) { $playlist = $qarr['list']; }
					// if ($playlist) { kodi('showdir',"plugin://plugin.video.youtube/playlist/$playlist",$data); } else {
						// sendReply($data, "Playlist load issue!");
					// }
					// return;
				// }
				
				$msg = kodi($ytcmd,$arg,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

		if (startsWith(strtolower($data['content']),'.twitch ')) {
			if (($author == '380675774794956800' && $dmMode) || (isset($data['channel_id']) && !empty($data['channel_id']) && in_array($data['channel_id'],$kodichans))) {
				$ytcmds = ['twitch'];
				$args = explode(' ',$data['content']);
				
				var_dump($args);
				$ytcmd = ltrim(array_shift($args),'.');
				var_dump($ytcmd);
				if (!in_array($ytcmd,$ytcmds)) {
					return;
				}
				$arg  = implode(' ',$args);

				// if ($ytcmd == 'ytpl' || $ytcmd == 'ytplaylist') {
					// $playlist = false;
					// parse_str(explode('?',pathinfo($arg)['basename'])[1],$qarr);
					// if (isset($qarr['list'])) { $playlist = $qarr['list']; }
					// if ($playlist) { kodi('showdir',"plugin://plugin.video.youtube/playlist/$playlist",$data); } else {
						// sendReply($data, "Playlist load issue!");
					// }
					// return;
				// }
				
				$msg = kodi($ytcmd,$arg,$data);
				sendReply($data, $msg);
				return $msg;
			}
		}

// Youtube download
		if (startsWith(strtolower($data['content']), '.dlyt ')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {

				$args = explode(' ',trim($data['content']));
				$cmd = ltrim(array_shift($args),'.');
				$arg  = implode(' ',$args);

				preg_match(
				"/(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:\S*&)?vi?=|(?:embed|v|vi|user|shorts)\/))([^?&\"'>\s]+)/",
				$arg,$matches);
				if (isset($matches[1]) && validVideoId($matches[1])) {
					$vid = $matches[1];
				} else {
					$output = 'dlyt video id error';
					sendReply($data, $arg." ".$output);
					return;
				}

				$escauthor = escapeshellarg($author);
				$vid = escapeshellarg($vid);
				sendReply('380675774794956800', "$escauthor $vid");
				chdir("/home/shayne/vbot/crystalbot/");
				shell_exec("php /home/shayne/vbot/crystalbot/fetchmp3.php '$vid' '$escauthor' >> fetchmp3.log 2>> fetchmp3.log &");
				sendReply($data, "Processing your request. Please hold...");
				return;
			}
		}

// Initiate DM with user (fix for discord DM glitch)
		if (!$dmMode && strtolower($data['content']) === '.juiceme') {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				sendReply($data, "https://tenor.com/view/alone-gif-8541857");
				sendMsg($author,"I am a kitty cat and I dance dance dance!");
				return;
			}
		}

		if (strtolower($data['content']) === '.fortune') {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				$fortune = new Fortune();
				@$msg = $fortune->QuoteFromDir("fortune_data/");
				$content = str_replace(array("<br />", "<br/>", "<br>"), "\n", $msg);
				$remove = array("\r", "<p>", "</p>", "<h1>", "</h1>");
				$msg = str_replace($remove, ' ', $content);
				sendReply($data, $msg);
			}
		}

		if (( strtolower($data['content']) === '.headpats' || strtolower($data['content']) === '.pet' || strtolower($data['content']) === '.joy' || strtolower($data['content']) === '.givepets')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				sendReply($data, "https://www.crystalshouts.com/grahaears.gif");
				return "https://www.crystalshouts.com/grahaears.gif";
			}
		}

		if (( strtolower($data['content']) === '.dance' || strtolower($data['content']) === '.shimmy')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				sendReply($data, "https://www.eorzeanshouts.com/grahashimmy.gif");
				return "https://www.eorzeanshouts.com/grahashimmy.gif";
			}
		}

		if (( strtolower($data['content']) === '.feedthetia' || strtolower($data['content']) === '.givetaco' || strtolower($data['content']) === '.feedgraha')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				//$user = getUser($author);
				sendReply($data, "https://www.crystalshouts.com/graha.gif \nOm nom nom!");
				return "https://www.crystalshouts.com/graha.gif \nOm nom nom!";
			}
		}

		if (strtolower($data['content']) === '.poke') {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				sendReply($data, "https://www.crystalshouts.com/grahatu.gif");
				return "https://www.crystalshouts.com/grahatu.gif";
			}
		}

		if (startsWith(strtolower($data['content']),'.tzconvert')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				$user = getUser($author);
				$tz = explode(' ',str_ireplace(' AM  ','AM ',str_replace(' PM ','PM ',str_replace('  ',' ',str_ireplace(['to ','.tzconvert '],'',strtoupper($data['content']))))));
				if (count($tz) < 3) {
					$time = $tz[0];
					$fromtz= $user['timezone'];
					$totz = $tz[1];
				} else {
					$time = $tz[0];
					$fromtz = $tz[1];
					$totz = $tz[2];
				}
				if (!date_default_timezone_set($totz)) {
					date_default_timezone_set( timezone_name_from_abbr($totz));						
				}
				$out = print_r($tz,true)."$time $fromtz is ".date('h:i A',strtotime($time.' '.$fromtz))." $totz";
				sendReply($data,  $out);
				return $out;
			}
		}

		// if (( strtolower($data['content']) === 'hello computer' || strtolower($data['content']) === '.help')) {
			// if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				// $output = getUser($author);
				// $defaulteurl = "(None)";
				// if ($output['status'] && $output['defaulteurl'] != '') {
					// $defaulteurl = $output['defaulteurl'];
				// }
				// $message = "Use '{blank}' to set a blank value. eg: `.shoutmsg <my-venue-eurl> {blank}`\nGet twitch url: `.twitch <eurl>` or set twitch url: `.twitch <eurl> twitch url`\nGet shout message: `.shoutmsg <eurl>` or  set shout message: `.shoutmsg <eurl> shout message` (discord formatting **will** work, too!)\nRandom fortune cookie message: `.fortune`\n
										// Create a new post with `.new Post Title`. Default eurl will switch to new post. You can modify the default eurl with `.default <eurl>`\n
										// Your current default eurl is: $defaulteurl\n
										// List posts you own with `.myposts`, New posts: `.newposts`, Posts open today: `.opentoday`, and search for posts with `.search`\n
										// You can modify your posts with commands such as:\n
										// `.addpics`, `.application`, `.validate`, `.site`, `.discord`, `.stream`, `.instagram`,`.twitter`, `.shoutmsg`, `.desc`, `.tags`, and `.note`
										// ";

				// sendReply($data, $message);
			// }
		// }

		if ((strtolower($data['content']) === '.botprod' || strtolower($data['content']) === '.botproud' || strtolower($data['content']) === '.pushprod')) {
			if ($author == '380675774794956800') {
				sendReply($data, "Pushing Dev Changes....");
				shell_exec("cd /home/shayne/vbot/crystalbot/ && cp csbot.php csbot.php.bak && cp csbot-dev.php csbot.php");
				sleep(1);
				sendReply($data, ".reset");
				if ($GLOBALS['filePrefix'] != 'DEV-') { $data['content'] = '.reset'; }
			} else { sendReply('380675774794956800', "bad auth from $author <@$author>"); sendReply($data,"https://www.crystalshouts.com/noauth.jpg");	}
		}

		if ((strtolower($data['content']) === '.reboot' || strtolower($data['content']) === '.reset')) {
			if ($author == '380675774794956800') {// || $author = $GLOBALS['otherID']) {
				$channelid = $data['channel_id'];
				$guildid = $data['guild_id'];
				if ($dmMode == 1) { 
					$guildid = "DM"; $channelid = $author; 
					shell_exec("php /home/shayne/vbot/crystalbot/sendmsg.php ".$GLOBALS['filePrefix']."$author Restarting... &");
				} else {
					shell_exec("php /home/shayne/vbot/crystalbot/sendmsg.php ".$GLOBALS['filePrefix'].$guildid."#".$channelid." Restarting... &");
				}
				if (isset($channelid)) { $channelid = $guildid.':'.$data['channel_id']; } 
				file_put_contents($GLOBALS['filePrefix'].'lastchan',$channelid);
				sendReply($data, "Restarting...");
				sleep(3);
				exit;
			} else { sendReply('380675774794956800', "bad auth from $author <@$author>"); sendReply($data,"https://www.crystalshouts.com/noauth.jpg");	}
		}

	if (isset($data['content']) && (  $data['content'] == '.time' || startsWith(strtolower($data['content']),'.time '))) {
		if (isset($data['channel_id']) && !empty($data['channel_id'])) {


			$tz = explode(' ',$data['content']);
			if (!isset($tz[1])) {
				$tz = 'America/Chicago';
			} else {
				array_shift($tz);
				$tz = str_replace(' ','_',implode(' ',$tz));
				if (!in_array(strtolower($tz), array_keys(DateTimeZone::listAbbreviations())) && !in_array(strtolower($tz), array_map('strtolower',timezone_identifiers_list()))) {
					if (is_array($guesses = guessTZ($tz))) {
						sendReply($data, "$tz isn't a valid timezone! Try: ".niceList($guesses,'','or'));
						return; 
					} else {
						$tz = $guesses;
					}
				}	
			}

//					$etTimezone = new DateTimeZone($tz);
				$utcTimezone = new DateTimeZone('UTC');


				$to = new DateTime('now',$utcTimezone);
				$to ->setTimezone(new DateTimeZone($tz));
				$to = $to->format('F jS h:iA T');


			//$user = getUser($author);
			// $tz = trim(explode(' ',$data['content'])[1]);
			// // $tz = explode(' ',str_ireplace(' AM  ','AM ',str_replace(' PM ','PM ',str_replace('  ',' ',str_ireplace(['to ','.tzconvert '],'',strtoupper($data['content']))))));
			// if (!$tz) {
				// //$fromtz= $user['timezone'];
				// $totz = $tz[1];
			// } else {
				// $time = $tz[0];
				// $fromtz = $tz[1];
				// $totz = $tz[2];
			// }
				// $time = $tz[0];


			// if (!$fromtz = explode(' ',$tz[0])[1]) {
				// $tz[0] = $user['timezone'];
			// } else {
				// $tz[0] = $fromtz;
			// }
		
			// $fromtz = $tz[0];
		
				// $totz = $user['timezone'];
			// } else {
				// $totz = $tz[1];
			// }
			// if (!date_default_timezone_set($totz)) {
				// // date_default_timezone_set($user['timezone']);
				// date_default_timezone_set( timezone_name_from_abbr($totz));						
			// }
			
			sendReply($data, $to);
			return $to;
		}
	}


		if (startsWith(strtolower($data['content']),'.happydance')) {
			if (isset($data['channel_id']) && !empty($data['channel_id'])) {
				$hdances = [
					"https://www.crystalshouts.com/happydance.gif",
					"https://www.crystalshouts.com/startrek-dance.gif",
					"https://www.eorzeanshouts.com/grahashimmy.gif",
					"https://c.tenor.com/LHKzT8b-tfcAAAAj/%D8%B5%D8%A8%D9%8A%D9%8A%D8%B4%D8%B7%D8%AD-dance.gif",
					"https://c.tenor.com/2vqE2AJ-6ngAAAAC/woo-seinfeld.gif",
					"https://c.tenor.com/xd2_RUy41sAAAAAC/cute-cat.gif",
					"https://c.tenor.com/2pGUcoYwuhMAAAAC/happy-birthday.gif",
					"https://c.tenor.com/pXnGfrFQgF8AAAAC/dance-emoji.gif"
				];
				$dance = $hdances[array_rand($hdances, 1)];
				sendReply($data, $dance);
				return $dance;
			}
		}
		return "OOP!";
	}




$discord->run();