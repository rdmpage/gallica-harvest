<?php

// process

$field_to_ris_key = array(
	'genre'		=> 'TY',
	'title' 	=> 'TI',
	'authors'	=> 'AU',
	'journal' 	=> 'JO',
	'issn' 		=> 'SN',
	'volume' 	=> 'VL',
	'issue' 	=> 'IS',
	'spage' 	=> 'SP',
	'epage' 	=> 'EP',
	'year' 		=> 'Y1',
	'date' 		=> 'PY',
	'url'		=> 'UR',
	'pdf'		=> 'L1',
	'doi'		=> 'DO'
	);


$basedir = dirname(__FILE__);

// base id for journal

$base_ark = 'cb34378174n';

$cache_dir = $basedir . '/' . $base_ark;


// 1.get metadata for journal

$filename = $cache_dir . '/' . $base_ark . '.oai.xml';

$xml = file_get_contents($filename);

// echo $xml;

$dom = new DOMDocument;
$dom->loadXML($xml, LIBXML_NOCDATA); // Elsevier wraps text in <![CDATA[ ... ]]>
$xpath = new DOMXPath($dom);

$xpath->registerNamespace('dc',    				'http://purl.org/dc/elements/1.1/');

$journal = new stdclass;

foreach ($xpath->query('//dc:title') as $node)
{
	$journal->title = $node->firstChild->nodeValue;
}

foreach ($xpath->query('//dc:identifier') as $node)
{
	if (preg_match('/ISSN\s+([0-9]{4})([0-9]{3}[\d+|X])/i', $node->firstChild->nodeValue, $m))
	{
		$journal->issn = $m[1] . '-' . $m[2];
	}
}

// print_r($journal);

$files = scandir($cache_dir);

$arks = array();

foreach ($files as $filename)
{
	if (preg_match('/^(b.*)\.oai\.xml/', $filename, $m))
	{
		$arks[] = $m[1];
	}
}

$arks = array_unique($arks);

// print_r($arks);

// test

//$arks = array('bpt6k6556442c');

$arks = array('bpt6k65565345');

$arks = array('bpt6k6555617p');

foreach ($arks as $ark)
{
	// issue metadata
	
	$filename = $cache_dir . '/' . $ark . '.oai.xml';
	
	$xml = file_get_contents($filename);
	
	// echo $xml;

	$dom = new DOMDocument;
	$dom->loadXML($xml, LIBXML_NOCDATA); // Elsevier wraps text in <![CDATA[ ... ]]>
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace('dc',    				'http://purl.org/dc/elements/1.1/');

	$issue = new stdclass;

	foreach ($xpath->query('//dc:date') as $node)
	{
		$issue->date = $node->firstChild->nodeValue;
		if (preg_match('/[0-9]{4}-[0-9]{2}/', $node->firstChild->nodeValue))
		{
			$issue->date .= '-00';
		}
	}
	
	foreach ($xpath->query('//dc:description') as $node)
	{
		// tome
		if (preg_match('/T(\d+)/', $node->firstChild->nodeValue, $m))
		{
			$issue->volume = $m[1];
		}
		
		// fasicule
		if (preg_match('/FASC(\d+)/', $node->firstChild->nodeValue, $m))
		{
			$issue->number = $m[1];
		}
		
		// a?
		if (preg_match('/A(\d+)/', $node->firstChild->nodeValue, $m))
		{
			if (!isset($issue->volume))
			{
				$issue->volume = $m[1];
			}
		}
		
	}
	

	foreach ($xpath->query('//dc:identifier') as $node)
	{
		$issue->url = $node->firstChild->nodeValue;
	}

	// print_r($issue);
	
	// article metadata
	$filename = $cache_dir . '/' . $ark . '.xml';
	
	$xml = file_get_contents($filename);
	
	//$xml = mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1');
	
	// echo $xml;
	
	$dom = new DOMDocument;
	$dom->loadXML($xml, LIBXML_NOCDATA); // Elsevier wraps text in <![CDATA[ ... ]]>
	$xpath = new DOMXPath($dom);


	foreach ($xpath->query('//row') as $row)
	{
		$reference = new stdclass;
		$reference->genre = 'JOUR';
		
		foreach ($xpath->query('cell[1]', $row) as $cell)
		{
		
			foreach ($xpath->query('seg/title', $cell) as $node)
			{
				$reference->title = $node->firstChild->nodeValue;
			}
		
			foreach ($xpath->query('seg/persName', $cell) as $node)
			{
				$authorstring = trim($node->textContent);
				
				
				$authorstring = preg_replace('/\s+\((.*)/', ", $1", $authorstring);
				$authorstring = preg_replace('/\)/', "", $authorstring);
				$authorstring = preg_replace('/\.\./', ".", $authorstring);
				
				$authorstring = mb_convert_case($authorstring, MB_CASE_TITLE);
				
				$reference->authors[] = $authorstring;
			}
				
		}
		
		foreach ($xpath->query('cell[2]', $row) as $cell)
		{
			$reference->spage = $cell->firstChild->nodeValue;
			
			foreach ($xpath->query('xref/@from', $cell) as $node)
			{
				$image = $node->firstChild->nodeValue;
				
				if (preg_match('/\/0+(\d+)\.TIF/', $image, $m))
				{
					$reference->url = $issue->url . '/f' . $m[1];
				}
			}
		}
		
		if (isset($journal->title))
		{
			$reference->journal = $journal->title;
		}

		if (isset($journal->issn))
		{
			$reference->issn = $journal->issn;
		}

		if (isset($issue->date))
		{
			$reference->date = $issue->date;
		}
				
		if (isset($issue->volume))
		{
			$reference->volume = $issue->volume;
		}
		
		if (isset($issue->number))
		{
			$reference->issue = $issue->number;
		}
		
		// print_r($reference);
		
		if (isset($reference->title))
		{
		
			foreach ($field_to_ris_key as $k => $v)
			{			
				if (isset($reference->{$k}))
				{
					switch ($k)
					{
						case 'authors':
							foreach ($reference->{$k} as $a)
							{
								echo $field_to_ris_key[$k] . '  - ' . $a . "\n";							
							}
							break;
						
						case 'date':
							if (strlen($reference->{$k}) == 4)
							{
								echo $field_to_ris_key[$k] . "  - " . $reference->{$k} . "///\n";
							}
							else
							{
								echo $field_to_ris_key[$k] . "  - " . str_replace('-', '/', $reference->{$k}) . "/\n";						
							}					
							break;
						
						default:
							echo $field_to_ris_key[$k] . '  - ' . $reference->{$k} . "\n";
							break;
					}
			
			
				}
			
			}
			echo "ER  - \n\n";
		}
		
	}
	
	

}

?>

