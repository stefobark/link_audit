<?php
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

// It may take a while to crawl a site ...
set_time_limit(10000000);

// Inculde the phpcrawl-mainclass
include("libs/PHPCrawler.class.php");


if (!isset($links)) {
    $links = '';
}

if (empty($id)) {
    $id = 0;
}

// Extend the class and override the handleDocumentInfo()-method 
class MyCrawler extends PHPCrawler
{
    function handleDocumentInfo($DocInfo)
    {
        global $id;
        global $links;
        
        $docid = ++$id;
        
        // Grab the URL	
        $url = $DocInfo->url;
        
        // Loop through all links found from that URL and concatenate them into a big string.
        foreach ($DocInfo->links_found as $value) {
            
            // Get rid of everything that isn't a number or letter
            $cleaner = preg_replace("/[^A-Za-z0-9 ]/", ' ', $value['url_rebuild']);
            $links .= " " . $cleaner . " ";
        }
        
        // Build our tsv file for Sphinx to index
        file_put_contents("linky.tsv", "$docid \t $url \t $links \n", FILE_APPEND);
        echo "Added $url to file <br /><br />";
    }
}

#################################################################################
######## Function to use the CSV we got from Google Webmaster Tools #############
#################################################################################

function csv_to_array($filename = '')
{
    if (!file_exists($filename) || !is_readable($filename))
        return FALSE;
    
    $header = NULL;
    $data   = array();
    if (($handle = fopen($filename, 'r')) !== FALSE) {
        while (($row = fgets($handle)) !== FALSE) {
            $data[] = $row;
        }
        
        fclose($handle);
    }
    $final_data = array();
    foreach ($data as $parse_it) {
        $final_data[] = str_getcsv($parse_it, ',', '"');
    }
    return $final_data;
}

// Point to your csv
$final = csv_to_array('/var/www/html/crawl/backlinks.csv');

// For each of the URLs on our list.. CRAWL
foreach ($final as $row) {
    
    // Now, create a instance of your class, define the behavior
    // of the crawler (see class-reference for more options and details)
    // and start the crawling-process.
    $crawler = new MyCrawler();
    
    // URL to crawl
    $crawler->setURL("$row[0]");
    
    // Only receive content of files with content-type "text/html"
    $crawler->addContentTypeReceiveRule("#text/html#");
    
    // Ignore links to pictures, dont even request pictures
    $crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png)$# i");
    
    // Store and send cookie-data like a browser does
    $crawler->enableCookieHandling(true);
    
    // Set the traffic-limit to 1 MB (in bytes,
    // for testing we dont want to "suck" the whole site)
    $crawler->setTrafficLimit(1000 * 1024);
    
    //yes, obey robots.txt and rel=nofollow
    $crawler->obeyRobotsTxt(true);
    $crawler->obeyNoFollowTags(true);
    
    // Thats enough, now here we go
    $crawler->go();
    
}
?>
