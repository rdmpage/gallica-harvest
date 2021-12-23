<?php



//----------------------------------------------------------------------------------------
function get($url, $format = '')
{
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	
	if ($format != '')
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: " . $format));	
	}
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	curl_close($ch);
	
	return $response;
}

//----------------------------------------------------------------------------------------

$basedir = dirname(__FILE__);

$count = 1;


// base id for journal

$base_ark = 'cb34378174n'; // Bulletin de la Société d'histoire naturelle de Toulouse
$base_ark = 'cb34349289k'; // Annales de la Société entomologique de France

$cache_dir = $basedir . '/' . $base_ark;

if (!file_exists($cache_dir))
{
	$oldumask = umask(0); 
	mkdir($cache_dir, 0777);
	umask($oldumask);
}	

// 1.get metadata for journal

$filename = $cache_dir . '/' . $base_ark . '.oai.xml';
if (!file_exists($filename))
{
	$url = 'https://gallica.bnf.fr/services/OAIRecord?ark=' . urlencode('ark:/12148/' . $base_ark . '/date');
	
	$xml = get($url);
	
	if ($xml != '')
	{
		file_put_contents($filename, $xml);
	}
}

$xml = file_get_contents($filename);
echo $xml;

// 2. Get dates for each issue

$dom = new DOMDocument;
$dom->loadXML($xml, LIBXML_NOCDATA); // Elsevier wraps text in <![CDATA[ ... ]]>
$xpath = new DOMXPath($dom);

$dates = array();

foreach ($xpath->query('//date') as $node)
{
	$dates[] = $node->firstChild->nodeValue;
}

print_r($dates);

// 3. fetch issue toc and metadata

foreach ($dates as $date)
{
	$parameters = array(
		'ark' => 'ark:/12148/' . $base_ark . '/date',
		'date' => $date	
	);
	
	$url = 'https://gallica.bnf.fr/services/Issues?' . http_build_query($parameters);
	
	echo $url . "\n";
	
	$xml = get($url);
	
	echo $xml;
	
	$dom = new DOMDocument;
	$dom->loadXML($xml, LIBXML_NOCDATA); // Elsevier wraps text in <![CDATA[ ... ]]>
	$xpath = new DOMXPath($dom);

	foreach ($xpath->query('//issue/@ark') as $node)
	{
		$issue_ark = $node->firstChild->nodeValue;
		
		
		// get TOC for this issue
		$issue_filename = $cache_dir . '/' . $issue_ark . '.xml';
		
		if (!file_exists($issue_filename))
		{
			$url = 'https://gallica.bnf.fr/services/Toc?ark=' . urlencode('ark:/12148/' . $issue_ark);

			$xml = get($url);

			if ($xml != '')
			{					
				file_put_contents($issue_filename, $xml);
			}
		}	
		
		// get OAI for this issue
		$meta_filename = $cache_dir . '/' . $issue_ark . '.oai.xml';
		
		if (!file_exists($meta_filename))
		{
			$url = 'https://gallica.bnf.fr/services/OAIRecord?ark=' . urlencode('ark:/12148/' . $issue_ark);

			$xml = get($url);

			if ($xml != '')
			{					
				file_put_contents($meta_filename, $xml);
			}
		}			
				
	}			
	
	// Give server a break every 10 items
	if (($count++ % 10) == 0)
	{
		$rand = rand(1000000, 10000000);
		echo "\n-- ...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
		usleep($rand);
	}		

}




?>

